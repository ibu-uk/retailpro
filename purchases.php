<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$current_page = 'purchases';
$page_title   = __('purchase_orders');
$db = db();
$currency = get_setting('currency', 'KWD');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {
    $po_num = next_po_number();
    $db->prepare("INSERT INTO purchase_orders (po_number,supplier_id,branch_id,total_amount,notes,created_by) VALUES (?,?,?,?,?,?)")->execute([
        $po_num, (int)$_POST['supplier_id'], (int)$_POST['branch_id'],
        (float)$_POST['total_amount'], trim($_POST['notes']), current_user()['id']
    ]);
    header('Location: ' . BASE . '/purchases.php?success=' . urlencode('Purchase order ' . $po_num . ' created'));
    exit;
}

if (isset($_GET['mark_complete'])) {
    $po_id = (int)$_GET['mark_complete'];
    $db->beginTransaction();
    try {
        $po = $db->prepare("SELECT * FROM purchase_orders WHERE id=? AND status != 'completed'");
        $po->execute([$po_id]);
        $po_row = $po->fetch();
        if ($po_row) {
            // Update stock for all PO items
            $items = $db->prepare("SELECT * FROM purchase_order_items WHERE po_id=?");
            $items->execute([$po_id]);
            $po_items = $items->fetchAll();
            foreach ($po_items as $item) {
                $qty = (int)$item['qty_ordered'];
                if ($qty > 0) {
                    $db->prepare("INSERT INTO stock (product_id,branch_id,qty) VALUES (?,?,?) ON DUPLICATE KEY UPDATE qty=qty+?")
                       ->execute([$item['product_id'], $po_row['branch_id'], $qty, $qty]);
                    $db->prepare("INSERT INTO stock_movements (product_id,branch_id,type,qty,reference,notes,user_id) VALUES (?,?,'in',?,?,?,?)")
                       ->execute([$item['product_id'], $po_row['branch_id'], $qty, $po_row['po_number'], 'Purchase order received', current_user()['id']]);
                }
            }
            // Deduct from supplier balance (we owe them the total_amount)
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

// Edit PO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_po') {
    $db->prepare("UPDATE purchase_orders SET supplier_id=?, branch_id=?, total_amount=?, status=?, notes=? WHERE id=?")->execute([
        (int)$_POST['supplier_id'], (int)$_POST['branch_id'], (float)$_POST['total_amount'],
        $_POST['status'], trim($_POST['notes'] ?? ''), (int)$_POST['po_id']
    ]);
    header('Location: ' . BASE . '/purchases.php?success=' . urlencode('Purchase order updated'));
    exit;
}

// Delete PO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_po') {
    $db->prepare("DELETE FROM purchase_order_items WHERE po_id=?")->execute([(int)$_POST['po_id']]);
    $db->prepare("DELETE FROM purchase_orders WHERE id=?")->execute([(int)$_POST['po_id']]);
    header('Location: ' . BASE . '/purchases.php?success=' . urlencode('Purchase order deleted'));
    exit;
}

// Add PO item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_po_item') {
    $po_id   = (int)$_POST['po_id'];
    $prod_id = (int)$_POST['product_id'];
    $qty     = max(1, (int)$_POST['qty_ordered']);
    $cost    = (float)$_POST['unit_cost'];
    $db->prepare("INSERT INTO purchase_order_items (po_id,product_id,qty_ordered,unit_cost) VALUES (?,?,?,?)
                  ON DUPLICATE KEY UPDATE qty_ordered=qty_ordered+?, unit_cost=?")->execute([$po_id,$prod_id,$qty,$cost,$qty,$cost]);
    // Update PO total_amount
    $db->prepare("UPDATE purchase_orders SET total_amount=(SELECT COALESCE(SUM(qty_ordered*unit_cost),0) FROM purchase_order_items WHERE po_id=?) WHERE id=?")->execute([$po_id,$po_id]);
    header('Location: ' . BASE . '/purchases.php?success=' . urlencode('Item added'));
    exit;
}

// Delete PO item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_po_item') {
    $item_id = (int)$_POST['item_id'];
    $po_id   = (int)$_POST['po_id'];
    $db->prepare("DELETE FROM purchase_order_items WHERE id=?")->execute([$item_id]);
    $db->prepare("UPDATE purchase_orders SET total_amount=(SELECT COALESCE(SUM(qty_ordered*unit_cost),0) FROM purchase_order_items WHERE po_id=?) WHERE id=?")->execute([$po_id,$po_id]);
    header('Location: ' . BASE . '/purchases.php?success=' . urlencode('Item removed'));
    exit;
}

$filter = $_GET['status'] ?? '';
$where  = $filter ? "WHERE po.status = ?" : "";
$params = $filter ? [$filter] : [];

// Pagination
$page_num = max(1, (int)($_GET['p'] ?? 1));
$per_page = 20;
$offset   = ($page_num - 1) * $per_page;
$count_sql = "SELECT COUNT(*) FROM purchase_orders po " . ($filter ? "WHERE po.status = ?" : "");
$count_stmt = $db->prepare($count_sql); $count_stmt->execute($params);
$total_orders = $count_stmt->fetchColumn();
$total_pages = ceil($total_orders / $per_page);

$orders = $db->prepare("
    SELECT po.*, s.company as supplier_name, b.name as branch_name
    FROM purchase_orders po
    JOIN suppliers s ON s.id = po.supplier_id
    JOIN branches b ON b.id = po.branch_id
    $where ORDER BY po.created_at DESC LIMIT $per_page OFFSET $offset
");
$orders->execute($params);
$orders = $orders->fetchAll();

$suppliers = $db->query("SELECT id, company FROM suppliers WHERE is_active=1 ORDER BY company")->fetchAll();
$all_products_po = $db->query("SELECT p.id, p.name, p.sku, p.cost_price, COALESCE(SUM(s.qty),0) as stock FROM products p LEFT JOIN stock s ON s.product_id=p.id WHERE p.is_active=1 GROUP BY p.id ORDER BY p.name")->fetchAll();
$branches  = $db->query("SELECT id, name FROM branches WHERE is_active=1")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success" style="margin-bottom:16px">✅ <?= htmlspecialchars($_GET['success']) ?></div>
<?php elseif (isset($_GET['error'])): ?>
<div class="alert alert-error" style="margin-bottom:16px">❌ <?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<div class="inv-filters">
  <a href="<?= BASE ?>/purchases.php" class="filter-chip <?= !$filter?'active':'' ?>"><?= __('all') ?></a>
  <a href="<?= BASE ?>/purchases.php?status=pending" class="filter-chip <?= $filter==='pending'?'active':'' ?>"><?= __('pending') ?></a>
  <a href="<?= BASE ?>/purchases.php?status=partial" class="filter-chip <?= $filter==='partial'?'active':'' ?>"><?= __('partial') ?></a>
  <a href="<?= BASE ?>/purchases.php?status=completed" class="filter-chip <?= $filter==='completed'?'active':'' ?>"><?= __('completed') ?></a>
  <div style="margin-left:auto;display:flex;gap:6px">
    <a href="<?= BASE ?>/api/export_purchases.php?status=<?= $filter ?>" class="btn btn-ghost btn-sm">📊 Excel</a>
    <button type="button" class="btn btn-ghost btn-sm" onclick="printPO()">🖨️ Print</button>
    <button class="btn btn-primary" onclick="openModal('po-modal')">+ <?= __('add') ?></button>
  </div>
</div>

<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead><tr><th>PO #</th><th><?= __('nav_suppliers') ?></th><th><?= __('nav_branches') ?></th><th><?= __('date') ?></th><th><?= __('total') ?></th><th><?= __('paid') ?></th><th><?= __('status') ?></th><th><?= __('actions') ?></th></tr></thead>
      <!-- table id for print -->
      <colgroup><col><col><col><col><col><col><col><col></colgroup>
      <tbody>
        <?php foreach ($orders as $po): ?>
        <tr>
          <td class="font-mono" style="font-size:12px"><?= htmlspecialchars($po['po_number']) ?></td>
          <td>🏭 <?= htmlspecialchars($po['supplier_name']) ?></td>
          <td><?= htmlspecialchars($po['branch_name']) ?></td>
          <td style="font-size:12px;color:var(--text3)"><?= date('d M Y', strtotime($po['created_at'])) ?></td>
          <td><?= fmt_money($po['total_amount']) ?></td>
          <td><?= fmt_money($po['paid_amount']) ?></td>
          <td>
            <?php
            $badge = ['pending'=>'badge-red','partial'=>'badge-amber','completed'=>'badge-green','cancelled'=>'badge-gray'];
            ?>
            <span class="badge <?= $badge[$po['status']] ?? 'badge-gray' ?>"><span class="dot"></span><?= ucfirst($po['status']) ?></span>
          </td>
          <td>
            <div style="display:flex;gap:4px">
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
        <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text3)"><?= __('no_data') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;font-size:12px;color:var(--text3)">
    <span><?= __('showing') ?> <?= count($orders) ?> <?= __('of') ?> <?= $total_orders ?></span>
    <div class="pagination">
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
      <a href="?status=<?= $filter ?>&p=<?= $i ?>" class="page-link <?= $i === $page_num ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  </div>
</div>

<!-- ADD PO MODAL -->
<div class="modal-backdrop" id="po-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title"><?= __('purchase_orders') ?></div>
      <button class="modal-close" onclick="closeModal('po-modal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label"><?= __('nav_suppliers') ?> *</label>
            <select class="form-select" name="supplier_id" required>
              <option value=""><?= __('select') ?>...</option>
              <?php foreach ($suppliers as $s): ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['company']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label"><?= __('nav_branches') ?> *</label>
            <select class="form-select" name="branch_id" required>
              <?php foreach ($branches as $b): ?>
              <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group"><label class="form-label"><?= __('total') ?> (<?= $currency ?>) *</label><input class="form-input" name="total_amount" type="number" step="0.001" min="0" required value="0.000"></div>
        <div class="form-group"><label class="form-label"><?= __('notes') ?></label><textarea class="form-textarea" name="notes"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('po-modal')"><?= __('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= __('save') ?></button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT PO MODAL -->
<div class="modal-backdrop" id="edit-po-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title"><?= __('edit') ?> <?= __('purchase_orders') ?></div>
      <button class="modal-close" onclick="closeModal('edit-po-modal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="edit_po">
      <input type="hidden" name="po_id" id="epo-id">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('nav_suppliers') ?></label>
            <select class="form-select" name="supplier_id" id="epo-supplier">
              <?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['company']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label class="form-label"><?= __('nav_branches') ?></label>
            <select class="form-select" name="branch_id" id="epo-branch">
              <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('total') ?> (<?= $currency ?>)</label><input class="form-input" name="total_amount" id="epo-total" type="number" step="0.001" min="0"></div>
          <div class="form-group"><label class="form-label"><?= __('status') ?></label>
            <select class="form-select" name="status" id="epo-status">
              <option value="pending">Pending</option><option value="partial">Partial</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label class="form-label"><?= __('notes') ?></label><textarea class="form-textarea" name="notes" id="epo-notes"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('edit-po-modal')"><?= __('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= __('save') ?></button>
      </div>
    </form>
  </div>
</div>

<!-- PO ITEMS MODAL -->
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

<!-- ADD PO ITEM MODAL -->
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
          <select class="form-select" name="product_id" required>
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
        <button type="button" class="btn btn-ghost" onclick="closeModal('add-item-modal')"><?= __('cancel') ?></button>
        <button type="submit" class="btn btn-primary">Add Item</button>
      </div>
    </form>
  </div>
</div>

<?php
ob_start(); ?>
<script>
function editPO(po) {
  document.getElementById("epo-id").value = po.id;
  document.getElementById("epo-supplier").value = po.supplier_id;
  document.getElementById("epo-branch").value = po.branch_id;
  document.getElementById("epo-total").value = parseFloat(po.total_amount).toFixed(3);
  document.getElementById("epo-status").value = po.status;
  document.getElementById("epo-notes").value = po.notes || "";
  openModal("edit-po-modal");
}
function printPO() {
  const table = document.querySelector(".tbl-wrap table");
  const win = window.open("","_blank");
  win.document.write("<html><head><title>Purchase Orders</title><style>body{font-family:Arial,sans-serif;padding:20px}table{width:100%;border-collapse:collapse;font-size:12px}th,td{border:1px solid #ddd;padding:6px 8px;text-align:left}th{background:#f0f2f5;font-weight:600}.header{text-align:center;margin-bottom:20px}</style></head><body>");
  win.document.write("<div class=header><h2>RetailPro — Purchase Orders</h2></div>");
  const clone = table.cloneNode(true);
  clone.querySelectorAll("tr").forEach(row => { const cells = row.querySelectorAll("th,td"); if(cells.length>=8) cells[cells.length-1].remove(); });
  win.document.write(clone.outerHTML);
  win.document.write("</body></html>");
  win.document.close();
  win.print();
}
function viewItems(poId, poNum) {
  document.getElementById("items-modal-title").textContent = "📦 Items — " + poNum;
  document.getElementById("ai-po-id").value = poId;
  const body = document.getElementById("items-modal-body");
  body.innerHTML = "<div style=\"padding:30px;text-align:center;color:var(--text3)\">Loading...</div>";
  openModal("items-modal");
  fetch("<?= BASE ?>/api/po_items.php?po_id=" + poId)
    .then(r => r.json())
    .then(data => {
      if (!data.items || !data.items.length) {
        body.innerHTML = "<div style=\"padding:24px;text-align:center;color:var(--text3)\">No items added yet.<br><button class=\"btn btn-primary\" style=\"margin-top:12px\" onclick=\"openAddItem()\">+ Add Item</button></div>";
        return;
      }
      let html = "<table style=\"width:100%;border-collapse:collapse\"><thead><tr>";
      html += "<th style=\"padding:8px 12px;background:var(--bg3);font-size:11px\">Product</th>";
      html += "<th style=\"padding:8px 12px;background:var(--bg3);font-size:11px\">SKU</th>";
      html += "<th style=\"padding:8px 12px;background:var(--bg3);font-size:11px\">Qty Ordered</th>";
      html += "<th style=\"padding:8px 12px;background:var(--bg3);font-size:11px\">Unit Cost</th>";
      html += "<th style=\"padding:8px 12px;background:var(--bg3);font-size:11px\">Line Total</th>";
      html += "<th style=\"padding:8px 12px;background:var(--bg3);font-size:11px\"></th>";
      html += "</tr></thead><tbody>";
      let grandTotal = 0;
      data.items.forEach(i => {
        const line = i.qty_ordered * i.unit_cost;
        grandTotal += line;
        html += "<tr>";
        html += "<td style=\"padding:8px 12px;font-weight:500\">" + i.name + "</td>";
        html += "<td style=\"padding:8px 12px;font-size:11px;color:var(--text3)\">" + i.sku + "</td>";
        html += "<td style=\"padding:8px 12px\">" + i.qty_ordered + "</td>";
        html += "<td style=\"padding:8px 12px\">" + parseFloat(i.unit_cost).toFixed(3) + "</td>";
        html += "<td style=\"padding:8px 12px;font-weight:600\">" + line.toFixed(3) + "</td>";
        html += "<td style=\"padding:8px 12px\"><form method=\"POST\" style=\"display:inline\" onsubmit=\"return confirm('Remove item?')\"><input type=\"hidden\" name=\"action\" value=\"delete_po_item\"><input type=\"hidden\" name=\"item_id\" value=\""+i.id+"\"><input type=\"hidden\" name=\"po_id\" value=\""+poId+"\"><button type=\"submit\" class=\"btn btn-ghost btn-sm\" style=\"color:var(--red)\">🗑️</button></form></td>";
        html += "</tr>";
      });
      html += "<tr style=\"background:var(--bg3);font-weight:700\"><td colspan=\"4\" style=\"padding:10px 12px\">Total</td><td style=\"padding:10px 12px\">" + grandTotal.toFixed(3) + "</td><td></td></tr>";
      html += "</tbody></table>";
      html += "<div style=\"padding:12px\"><button class=\"btn btn-primary btn-sm\" onclick=\"openAddItem()\">+ Add Item</button></div>";
      body.innerHTML = html;
    })
    .catch(() => { body.innerHTML = "<div style=\"padding:24px;text-align:center;color:var(--red)\">Failed to load items</div>"; });
}
function openAddItem() {
  closeModal("items-modal");
  openModal("add-item-modal");
}
// Auto-fill cost price when product selected
document.addEventListener("change", function(e) {
  if (e.target && e.target.closest("#add-item-modal") && e.target.tagName === "SELECT") {
    const opt = e.target.selectedOptions[0];
    const cost = opt ? opt.dataset.cost : null;
    if (cost) document.getElementById("ai-cost").value = parseFloat(cost).toFixed(3);
  }
});
</script>
<?php
$extra_js = ob_get_clean();
require __DIR__ . '/includes/footer.php'; ?>
