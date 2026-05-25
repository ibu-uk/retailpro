<?php
require_once __DIR__ . '/includes/config.php';
require_login();
require_role('super_admin');
$current_page = 'purchases';
$page_title   = __('purchase_orders');
$db = db();
$currency = get_setting('currency', 'KWD');

// ── CREATE PO (with inline items) ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {
    $po_num = next_po_number();
    $db->beginTransaction();
    try {
        $db->prepare("INSERT INTO purchase_orders (po_number,supplier_id,branch_id,total_amount,notes,created_by) VALUES (?,?,?,?,?,?)")->execute([
            $po_num, (int)$_POST['supplier_id'], (int)$_POST['branch_id'],
            0, trim($_POST['notes']), current_user()['id']
        ]);
        $po_id = $db->lastInsertId();

        // Insert line items submitted with the form
        $products = $_POST['item_product_id'] ?? [];
        $qtys     = $_POST['item_qty']        ?? [];
        $costs    = $_POST['item_cost']       ?? [];
        $total    = 0;
        foreach ($products as $i => $prod_id) {
            $prod_id = (int)$prod_id;
            if (!$prod_id) continue;
            $qty  = max(1, (int)($qtys[$i] ?? 1));
            $cost = (float)($costs[$i] ?? 0);
            $pack = max(1, (int)($_POST['item_pack_size'][$i] ?? 1));
        $db->prepare("INSERT INTO purchase_order_items (po_id,product_id,qty_ordered,unit_cost,pack_size) VALUES (?,?,?,?,?)
                          ON DUPLICATE KEY UPDATE qty_ordered=qty_ordered+?, unit_cost=?, pack_size=?")
               ->execute([$po_id, $prod_id, $qty, $cost, $pack, $qty, $cost, $pack]);
            $total += $qty * $cost;
        }
        $db->prepare("UPDATE purchase_orders SET total_amount=? WHERE id=?")->execute([$total, $po_id]);
        $db->commit();
        header('Location: ' . BASE . '/purchases.php?success=' . urlencode('Purchase order ' . $po_num . ' created'));
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        header('Location: ' . BASE . '/purchases.php?error=' . urlencode('Error: ' . $e->getMessage()));
        exit;
    }
}

// ── MARK COMPLETE (receive stock) ─────────────────────────────────────────
if (isset($_GET['mark_complete'])) {
    $po_id = (int)$_GET['mark_complete'];
    $db->beginTransaction();
    try {
        $po = $db->prepare("SELECT * FROM purchase_orders WHERE id=? AND status != 'completed'");
        $po->execute([$po_id]);
        $po_row = $po->fetch();
        if ($po_row) {
            $items = $db->prepare("SELECT * FROM purchase_order_items WHERE po_id=?");
            $items->execute([$po_id]);
            foreach ($items->fetchAll() as $item) {
                $qty_boxes  = (int)$item['qty_ordered'];
                $box_pack   = max(1, (int)($item['pack_size'] ?? 1));
                $qty = $qty_boxes * $box_pack; // convert boxes → pieces
                if ($qty > 0) {
                    $db->prepare("INSERT INTO stock (product_id,branch_id,qty) VALUES (?,?,?) ON DUPLICATE KEY UPDATE qty=qty+?")
                       ->execute([$item['product_id'], $po_row['branch_id'], $qty, $qty]);
                    $move_note = $box_pack > 1 ? "PO received: {$qty_boxes} boxes × {$box_pack} pcs = {$qty} pieces" : 'Purchase order received';
                    $db->prepare("INSERT INTO stock_movements (product_id,branch_id,type,qty,reference,notes,user_id) VALUES (?,?,'in',?,?,?,?)")
                       ->execute([$item['product_id'], $po_row['branch_id'], $qty, $po_row['po_number'], $move_note, current_user()['id']]);
                }
            }
            $unpaid = $po_row['total_amount'] - $po_row['paid_amount'];
            if ($unpaid > 0) {
                $db->prepare("UPDATE suppliers SET balance = balance - ? WHERE id=?")->execute([$unpaid, $po_row['supplier_id']]);
            }
            $db->prepare("UPDATE purchase_orders SET status='completed' WHERE id=?")->execute([$po_id]);
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        header('Location: ' . BASE . '/purchases.php?error=' . urlencode('Error: ' . $e->getMessage()));
        exit;
    }
    header('Location: ' . BASE . '/purchases.php?success=' . urlencode('Order marked complete — stock updated'));
    exit;
}

// ── EDIT PO ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_po') {
    $db->prepare("UPDATE purchase_orders SET supplier_id=?, branch_id=?, total_amount=?, status=?, notes=? WHERE id=?")->execute([
        (int)$_POST['supplier_id'], (int)$_POST['branch_id'], (float)$_POST['total_amount'],
        $_POST['status'], trim($_POST['notes'] ?? ''), (int)$_POST['po_id']
    ]);
    header('Location: ' . BASE . '/purchases.php?success=' . urlencode('Purchase order updated'));
    exit;
}

// ── DELETE PO ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_po') {
    $db->prepare("DELETE FROM purchase_order_items WHERE po_id=?")->execute([(int)$_POST['po_id']]);
    $db->prepare("DELETE FROM purchase_orders WHERE id=?")->execute([(int)$_POST['po_id']]);
    header('Location: ' . BASE . '/purchases.php?success=' . urlencode('Purchase order deleted'));
    exit;
}

// ── ADD PO ITEM (from items modal) ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_po_item') {
    $po_id   = (int)$_POST['po_id'];
    $prod_id = (int)$_POST['product_id'];
    $qty     = max(1, (int)$_POST['qty_ordered']);
    $cost    = (float)$_POST['unit_cost'];
    $db->prepare("INSERT INTO purchase_order_items (po_id,product_id,qty_ordered,unit_cost) VALUES (?,?,?,?)
                  ON DUPLICATE KEY UPDATE qty_ordered=qty_ordered+?, unit_cost=?")->execute([$po_id,$prod_id,$qty,$cost,$qty,$cost]);
    $db->prepare("UPDATE purchase_orders SET total_amount=(SELECT COALESCE(SUM(qty_ordered*unit_cost),0) FROM purchase_order_items WHERE po_id=?) WHERE id=?")->execute([$po_id,$po_id]);
    header('Location: ' . BASE . '/purchases.php?success=' . urlencode('Item added'));
    exit;
}

// ── DELETE PO ITEM ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_po_item') {
    $item_id = (int)$_POST['item_id'];
    $po_id   = (int)$_POST['po_id'];
    $db->prepare("DELETE FROM purchase_order_items WHERE id=?")->execute([$item_id]);
    $db->prepare("UPDATE purchase_orders SET total_amount=(SELECT COALESCE(SUM(qty_ordered*unit_cost),0) FROM purchase_order_items WHERE po_id=?) WHERE id=?")->execute([$po_id,$po_id]);
    header('Location: ' . BASE . '/purchases.php?success=' . urlencode('Item removed'));
    exit;
}

// ── LIST ──────────────────────────────────────────────────────────────────
$filter = $_GET['status'] ?? '';
$where  = $filter ? "WHERE po.status = ?" : "";
$params = $filter ? [$filter] : [];

$page_num = max(1, (int)($_GET['p'] ?? 1));
$per_page = 20;
$offset   = ($page_num - 1) * $per_page;
$count_stmt = $db->prepare("SELECT COUNT(*) FROM purchase_orders po " . ($filter ? "WHERE po.status = ?" : ""));
$count_stmt->execute($params);
$total_orders = $count_stmt->fetchColumn();
$total_pages  = ceil($total_orders / $per_page);

$orders = $db->prepare("
    SELECT po.*, s.company as supplier_name, b.name as branch_name
    FROM purchase_orders po
    JOIN suppliers s ON s.id = po.supplier_id
    JOIN branches b  ON b.id = po.branch_id
    $where ORDER BY po.created_at DESC LIMIT $per_page OFFSET $offset
");
$orders->execute($params);
$orders = $orders->fetchAll();

$suppliers       = $db->query("SELECT id, company FROM suppliers WHERE is_active=1 ORDER BY company")->fetchAll();
$all_products_po = $db->query("SELECT p.id, p.name, p.sku, p.cost_price, COALESCE(p.unit_type,'pc') as unit, COALESCE(p.default_pack_size,1) as pack_size, COALESCE(SUM(s.qty),0) as stock FROM products p LEFT JOIN stock s ON s.product_id=p.id WHERE p.is_active=1 GROUP BY p.id ORDER BY p.name")->fetchAll();
$branches        = $db->query("SELECT id, name FROM branches WHERE is_active=1")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success" style="margin-bottom:16px">✅ <?= htmlspecialchars($_GET['success']) ?></div>
<?php elseif (isset($_GET['error'])): ?>
<div class="alert alert-error" style="margin-bottom:16px">❌ <?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<div class="inv-filters">
  <a href="<?= BASE ?>/purchases.php" class="filter-chip <?= !$filter?'active':'' ?>">All</a>
  <a href="<?= BASE ?>/purchases.php?status=pending"   class="filter-chip <?= $filter==='pending'?'active':'' ?>">Pending</a>
  <a href="<?= BASE ?>/purchases.php?status=partial"   class="filter-chip <?= $filter==='partial'?'active':'' ?>">Partial</a>
  <a href="<?= BASE ?>/purchases.php?status=completed" class="filter-chip <?= $filter==='completed'?'active':'' ?>">Completed</a>
  <div style="margin-left:auto;display:flex;gap:6px">
    <a href="<?= BASE ?>/api/export_purchases.php?status=<?= $filter ?>" class="btn btn-ghost btn-sm">📊 Excel</a>
    <a href="<?= BASE ?>/quotations.php" class="btn btn-ghost btn-sm">📋 Quotations</a>
    <button class="btn btn-primary" onclick="openModal('po-modal')">+ New PO</button>
  </div>
</div>

<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>PO #</th><th>Supplier</th><th>Branch</th><th>Date</th><th>Items</th><th>Total</th><th>Paid</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach ($orders as $po): ?>
        <?php
          $item_count = $db->prepare("SELECT COUNT(*) FROM purchase_order_items WHERE po_id=?");
          $item_count->execute([$po['id']]);
          $ic = $item_count->fetchColumn();
        ?>
        <tr>
          <td class="font-mono" style="font-size:12px"><?= htmlspecialchars($po['po_number']) ?></td>
          <td>🏭 <?= htmlspecialchars($po['supplier_name']) ?></td>
          <td><?= htmlspecialchars($po['branch_name']) ?></td>
          <td style="font-size:12px;color:var(--text3)"><?= date('d M Y', strtotime($po['created_at'])) ?></td>
          <td><span class="badge badge-gray"><?= $ic ?> item<?= $ic!=1?'s':'' ?></span></td>
          <td><?= fmt_money($po['total_amount']) ?></td>
          <td><?= fmt_money($po['paid_amount']) ?></td>
          <td>
            <?php $badge = ['pending'=>'badge-red','partial'=>'badge-amber','completed'=>'badge-green','cancelled'=>'badge-gray']; ?>
            <span class="badge <?= $badge[$po['status']] ?? 'badge-gray' ?>"><span class="dot"></span><?= ucfirst($po['status']) ?></span>
          </td>
          <td>
            <div style="display:flex;gap:4px">
              <a href="<?= BASE ?>/po_print.php?id=<?= $po['id'] ?>" target="_blank" class="btn btn-ghost btn-sm" title="Print PO">🖨️</a>
              <button class="btn btn-ghost btn-sm" onclick='editPO(<?= json_encode($po, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>✏️</button>
              <button class="btn btn-ghost btn-sm" onclick="viewItems(<?= $po['id'] ?>, '<?= htmlspecialchars(addslashes($po['po_number'])) ?>')">📦</button>
              <?php if ($po['status'] !== 'completed' && $po['status'] !== 'cancelled'): ?>
              <a href="<?= BASE ?>/purchases.php?mark_complete=<?= $po['id'] ?>" class="btn btn-sm btn-green" onclick="return confirm('Mark as completed? This will update stock levels.')">✓ Receive</a>
              <?php endif; ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete this purchase order?')">
                <input type="hidden" name="action" value="delete_po"><input type="hidden" name="po_id" value="<?= $po['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--red)">🗑️</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($orders)): ?>
        <tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text3)">No purchase orders found</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;font-size:12px;color:var(--text3)">
    <span>Showing <?= count($orders) ?> of <?= $total_orders ?></span>
    <div class="pagination">
      <?php for ($i=1; $i<=$total_pages; $i++): ?>
      <a href="?status=<?= $filter ?>&p=<?= $i ?>" class="page-link <?= $i===$page_num?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     CREATE PO MODAL  —  includes inline product line items
     ══════════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="po-modal">
  <div class="modal" style="width:780px;max-width:96vw">
    <div class="modal-header">
      <div class="modal-title">📦 New Purchase Order</div>
      <button class="modal-close" onclick="closeModal('po-modal')">✕</button>
    </div>
    <form method="POST" id="new-po-form">
      <input type="hidden" name="action" value="add">
      <div class="modal-body" style="max-height:80vh;overflow-y:auto">

        <!-- Header fields -->
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Supplier *</label>
            <select class="form-select" name="supplier_id" required>
              <option value="">Select supplier...</option>
              <?php foreach ($suppliers as $s): ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['company']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Branch *</label>
            <select class="form-select" name="branch_id" required>
              <?php foreach ($branches as $b): ?>
              <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea class="form-textarea" name="notes" rows="2"></textarea>
        </div>

        <!-- Product line items -->
        <div style="margin-top:20px;margin-bottom:8px;font-weight:600;font-size:13px;color:var(--text1);display:flex;align-items:center;justify-content:space-between">
          <span>📋 Order Items</span>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addPOLine()">+ Add Product</button>
        </div>

        <div style="background:var(--bg2);border-radius:8px;overflow:hidden;border:1px solid var(--border)">
          <table style="width:100%;border-collapse:collapse" id="po-lines-table">
            <thead>
              <tr style="background:var(--bg3)">
                <th style="padding:8px 10px;font-size:11px;font-weight:600;text-align:left">Product</th>
                <th style="padding:8px 10px;font-size:11px;font-weight:600;text-align:center;width:70px">Qty</th>
                <th style="padding:8px 10px;font-size:11px;font-weight:600;text-align:center;width:68px">Pack Size</th>
                <th style="padding:8px 10px;font-size:11px;font-weight:600;text-align:right;width:100px">Unit Cost</th>
                <th style="padding:8px 10px;font-size:11px;font-weight:600;text-align:right;width:110px">Line Total</th>
                <th style="padding:8px 10px;width:36px"></th>
              </tr>
            </thead>
            <tbody id="po-lines-body">
              <!-- JS-generated rows -->
            </tbody>
            <tfoot>
              <tr style="background:var(--bg3);border-top:2px solid var(--border)">
                <td colspan="4" style="padding:10px 12px;font-weight:700;font-size:13px">Total</td>
                <td style="padding:10px 12px;font-weight:700;font-size:14px;text-align:right" id="po-grand-total">0.000 <?= $currency ?></td>
                <td></td>
              </tr>
            </tfoot>
          </table>
          <div id="po-empty-msg" style="padding:24px;text-align:center;color:var(--text3);font-size:13px">No items added — click "+ Add Product" above</div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('po-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">💾 Create Purchase Order</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT PO MODAL -->
<div class="modal-backdrop" id="edit-po-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">✏️ Edit Purchase Order</div>
      <button class="modal-close" onclick="closeModal('edit-po-modal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit_po">
      <input type="hidden" name="po_id" id="epo-id">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label class="form-label">Supplier</label>
            <select class="form-select" name="supplier_id" id="epo-supplier">
              <?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['company']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Branch</label>
            <select class="form-select" name="branch_id" id="epo-branch">
              <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Total (<?= $currency ?>)</label><input class="form-input" name="total_amount" id="epo-total" type="number" step="0.001" min="0"></div>
          <div class="form-group"><label class="form-label">Status</label>
            <select class="form-select" name="status" id="epo-status">
              <option value="pending">Pending</option><option value="partial">Partial</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label class="form-label">Notes</label><textarea class="form-textarea" name="notes" id="epo-notes"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('edit-po-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- PO ITEMS VIEW/ADD MODAL -->
<div class="modal-backdrop" id="items-modal">
  <div class="modal" style="width:700px">
    <div class="modal-header">
      <div class="modal-title" id="items-modal-title">📦 PO Items</div>
      <button class="modal-close" onclick="closeModal('items-modal')">✕</button>
    </div>
    <div class="modal-body" id="items-modal-body" style="padding:0">
      <div style="padding:20px;text-align:center;color:var(--text3)">Loading...</div>
    </div>
  </div>
</div>

<!-- ADD ITEM TO EXISTING PO MODAL -->
<div class="modal-backdrop" id="add-item-modal">
  <div class="modal" style="width:480px">
    <div class="modal-header">
      <div class="modal-title">Add Item to PO</div>
      <button class="modal-close" onclick="closeModal('add-item-modal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add_po_item">
      <input type="hidden" name="po_id" id="ai-po-id">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Product *</label>
          <select class="form-select" name="product_id" id="ai-product" required>
            <option value="">Select product...</option>
            <?php foreach ($all_products_po as $pr): ?>
            <option value="<?= $pr['id'] ?>" data-cost="<?= $pr['cost_price'] ?>"><?= htmlspecialchars($pr['name']) ?> (<?= $pr['sku'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Qty Ordered *</label><input class="form-input" name="qty_ordered" id="ai-qty" type="number" min="1" value="1" required></div>
          <div class="form-group"><label class="form-label">Unit Cost (<?= $currency ?>)</label><input class="form-input" name="unit_cost" id="ai-cost" type="number" step="0.001" min="0" value="0.000"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('add-item-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Item</button>
      </div>
    </form>
  </div>
</div>

<?php
$products_json = json_encode(array_values($all_products_po), JSON_HEX_APOS|JSON_HEX_QUOT);
$currency_js   = json_encode($currency);
ob_start(); ?>
<script>
const ALL_PRODUCTS = <?= $products_json ?>;
const CURRENCY     = <?= $currency_js ?>;
let poLineCount = 0;

function addPOLine(prodId, qty, cost, packSz) {
  poLineCount++;
  const idx = poLineCount;
  const prod = prodId ? ALL_PRODUCTS.find(p => p.id == prodId) : null;

  let opts = '<option value="">Select product...</option>';
  ALL_PRODUCTS.forEach(p => {
    const sel = (prodId && p.id == prodId) ? ' selected' : '';
    opts += '<option value="' + p.id + '" data-cost="' + p.cost_price + '" data-unit="' + (p.unit||'pc') + '" data-pack="' + (p.pack_size||1) + '"' + sel + '>' + p.name + ' (' + p.sku + ')</option>';
  });

  const qtyVal   = qty  || 1;
  const costVal  = cost || (prod ? parseFloat(prod.cost_price).toFixed(3) : '0.000');
  const isBox    = prod ? prod.unit === 'box' : false;
  const defPack  = packSz || (prod ? (prod.pack_size || 1) : 1);
  const packSzVal = isBox ? defPack : 1;
  const lineTotal = (parseFloat(qtyVal) * parseFloat(costVal)).toFixed(3);
  const pcsWillAdd = isBox ? Math.round(parseFloat(qtyVal) * packSzVal) : null;

  // Build pack cell HTML without nested template literals
  const packCellHtml = isBox
    ? '<input type="number" name="item_pack_size[]" id="po-pack-' + idx + '" value="' + packSzVal + '" min="1" class="form-input" style="margin:0;text-align:center;width:55px" oninput="recalcLine(' + idx + ')"><div style="font-size:10px;color:var(--text3);margin-top:2px">pcs/box</div>'
    : '<input type="hidden" name="item_pack_size[]" value="1"><span style="font-size:11px;color:var(--text3)">—</span>';

  const pcsBadge = pcsWillAdd
    ? '<div style="font-size:10px;color:#0d9488;margin-top:2px">+' + pcsWillAdd + ' pcs</div>'
    : '';

  const row = document.createElement('tr');
  row.id = 'po-line-' + idx;
  row.style.borderTop = '1px solid var(--border)';
  row.innerHTML =
    '<td style="padding:6px 10px">' +
      '<select name="item_product_id[]" class="form-select" style="margin:0" onchange="onPOProductChange(this,' + idx + ')" required>' +
        opts +
      '</select>' +
    '</td>' +
    '<td style="padding:6px 8px;text-align:center">' +
      '<input type="number" name="item_qty[]" id="po-qty-' + idx + '" value="' + qtyVal + '" min="1" class="form-input" style="margin:0;text-align:center;width:60px" oninput="recalcLine(' + idx + ')">' +
      '<div style="font-size:10px;color:var(--text3);margin-top:2px" id="po-qty-lbl-' + idx + '">' + (isBox ? 'boxes' : '') + '</div>' +
    '</td>' +
    '<td style="padding:4px 8px;text-align:center" id="po-pack-cell-' + idx + '">' + packCellHtml + '</td>' +
    '<td style="padding:6px 8px">' +
      '<input type="number" name="item_cost[]" id="po-cost-' + idx + '" value="' + costVal + '" min="0" step="0.001" class="form-input" style="margin:0;text-align:right;width:90px" oninput="recalcLine(' + idx + ')">' +
    '</td>' +
    '<td style="padding:6px 8px;text-align:right;font-weight:600" id="po-line-total-' + idx + '">' + lineTotal + ' ' + CURRENCY + '</td>' +
    '<td style="padding:6px 8px;text-align:center">' +
      pcsBadge +
      '<button type="button" onclick="removePOLine(' + idx + ')" style="background:none;border:none;cursor:pointer;color:var(--red);font-size:16px;padding:2px">✕</button>' +
    '</td>';

  document.getElementById('po-lines-body').appendChild(row);
  document.getElementById('po-empty-msg').style.display = 'none';
  recalcTotal();
}

function onPOProductChange(sel, idx) {
  const opt = sel.selectedOptions[0];
  if (!opt) return;
  const cost  = opt.dataset.cost || '0';
  const unit  = opt.dataset.unit || 'pc';
  const pack  = parseInt(opt.dataset.pack) || 1;
  const isBox = unit === 'box';

  document.getElementById('po-cost-' + idx).value = parseFloat(cost).toFixed(3);

  // Update pack size cell
  const packCell = document.getElementById('po-pack-cell-' + idx);
  if (packCell) {
    packCell.innerHTML = isBox
      ? '<input type="number" name="item_pack_size[]" id="po-pack-' + idx + '" value="' + pack + '" min="1" class="form-input" style="margin:0;text-align:center;width:55px" oninput="recalcLine(' + idx + ')"><div style="font-size:10px;color:var(--text3);margin-top:2px">pcs/box</div>'
      : '<input type="hidden" name="item_pack_size[]" value="1"><span style="font-size:11px;color:var(--text3)">—</span>';
  }
  const qtyLbl = document.getElementById('po-qty-lbl-' + idx);
  if (qtyLbl) qtyLbl.textContent = isBox ? 'boxes' : '';

  recalcLine(idx);
}

function recalcLine(idx) {
  const qty      = parseFloat(document.getElementById('po-qty-' + idx)?.value  || 0);
  const cost     = parseFloat(document.getElementById('po-cost-' + idx)?.value || 0);
  const packInp  = document.getElementById('po-pack-' + idx);
  const packSize = packInp ? parseInt(packInp.value) || 1 : 1;
  const el       = document.getElementById('po-line-total-' + idx);
  if (el) el.textContent = (qty * cost).toFixed(3) + ' ' + CURRENCY;
  // Update pieces badge
  const pcsEl = document.getElementById('po-pcs-' + idx);
  if (pcsEl) pcsEl.textContent = packSize > 1 ? '+' + Math.round(qty * packSize) + ' pcs' : '';
  recalcTotal();
}

function recalcTotal() {
  let grand = 0;
  document.querySelectorAll('[id^="po-line-total-"]').forEach(el => {
    grand += parseFloat(el.textContent) || 0;
  });
  document.getElementById('po-grand-total').textContent = grand.toFixed(3) + ' ' + CURRENCY;
}

function removePOLine(idx) {
  const row = document.getElementById('po-line-' + idx);
  if (row) row.remove();
  if (!document.querySelectorAll('[id^="po-line-"]').length) {
    document.getElementById('po-empty-msg').style.display = '';
  }
  recalcTotal();
}

function editPO(po) {
  document.getElementById("epo-id").value      = po.id;
  document.getElementById("epo-supplier").value = po.supplier_id;
  document.getElementById("epo-branch").value   = po.branch_id;
  document.getElementById("epo-total").value    = parseFloat(po.total_amount).toFixed(3);
  document.getElementById("epo-status").value   = po.status;
  document.getElementById("epo-notes").value    = po.notes || "";
  openModal("edit-po-modal");
}

function viewItems(poId, poNum) {
  document.getElementById("items-modal-title").textContent = "📦 Items — " + poNum;
  document.getElementById("ai-po-id").value = poId;
  const body = document.getElementById("items-modal-body");
  body.innerHTML = '<div style="padding:30px;text-align:center;color:var(--text3)">Loading...</div>';
  openModal("items-modal");
  fetch("<?= BASE ?>/po_items.php?po_id=" + poId)
    .then(r => r.json())
    .then(data => {
      if (!data.items || !data.items.length) {
        body.innerHTML = '<div style="padding:24px;text-align:center;color:var(--text3)">No items added yet.<br><button class="btn btn-primary" style="margin-top:12px" onclick="openAddItem()">+ Add Item</button></div>';
        return;
      }
      let html = '<table style="width:100%;border-collapse:collapse"><thead><tr>';
      html += '<th style="padding:8px 12px;background:var(--bg3);font-size:11px">Product</th>';
      html += '<th style="padding:8px 12px;background:var(--bg3);font-size:11px">SKU</th>';
      html += '<th style="padding:8px 12px;background:var(--bg3);font-size:11px">Qty Ordered</th>';
      html += '<th style="padding:8px 12px;background:var(--bg3);font-size:11px">Unit Cost</th>';
      html += '<th style="padding:8px 12px;background:var(--bg3);font-size:11px">Line Total</th>';
      html += '<th style="padding:8px 12px;background:var(--bg3);font-size:11px"></th>';
      html += '</tr></thead><tbody>';
      let grand = 0;
      data.items.forEach(i => {
        const line = i.qty_ordered * i.unit_cost;
        grand += line;
        html += `<tr>
          <td style="padding:8px 12px;font-weight:500">${i.name}</td>
          <td style="padding:8px 12px;font-size:11px;color:var(--text3)">${i.sku}</td>
          <td style="padding:8px 12px">${i.qty_ordered}</td>
          <td style="padding:8px 12px">${parseFloat(i.unit_cost).toFixed(3)}</td>
          <td style="padding:8px 12px;font-weight:600">${line.toFixed(3)}</td>
          <td style="padding:8px 12px">
            <form method="POST" style="display:inline" onsubmit="return confirm('Remove item?')">
              <input type="hidden" name="action" value="delete_po_item">
              <input type="hidden" name="item_id" value="${i.id}">
              <input type="hidden" name="po_id" value="${poId}">
              <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--red)">🗑️</button>
            </form>
          </td>
        </tr>`;
      });
      html += `<tr style="background:var(--bg3);font-weight:700"><td colspan="4" style="padding:10px 12px">Total</td><td style="padding:10px 12px">${grand.toFixed(3)}</td><td></td></tr>`;
      html += '</tbody></table>';
      html += '<div style="padding:12px"><button class="btn btn-primary btn-sm" onclick="openAddItem()">+ Add Item</button></div>';
      body.innerHTML = html;
    })
    .catch(() => { body.innerHTML = '<div style="padding:24px;text-align:center;color:var(--red)">Failed to load items</div>'; });
}

function openAddItem() {
  closeModal("items-modal");
  openModal("add-item-modal");
}

// Auto-fill cost when product selected in add-item modal
document.getElementById('ai-product')?.addEventListener('change', function() {
  const opt = this.selectedOptions[0];
  if (opt && opt.dataset.cost) {
    document.getElementById('ai-cost').value = parseFloat(opt.dataset.cost).toFixed(3);
  }
});
</script>
<?php
$extra_js = ob_get_clean();
require __DIR__ . '/includes/footer.php'; ?>
