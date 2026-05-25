<?php
/**
 * quotations.php — Customer Quotations (retail & wholesale)
 * - Create quotes with line items (products, qty, price)
 * - Separate pricing for retail vs wholesale customers
 * - Convert to invoice on approval
 * - Print / PDF quote document
 */
require_once __DIR__ . '/includes/config.php';
require_login();
$current_page = 'quotations';
$page_title   = 'Quotations';
$db = db();
$currency = get_setting('currency', 'KWD');
$decimals = (int)get_setting('currency_decimals', '3');

// ── CREATE QUOTATION ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $q_num = next_quote_number();
    $db->beginTransaction();
    try {
        $db->prepare("INSERT INTO quotations (quote_number,customer_id,branch_id,sale_type,valid_days,notes,created_by)
                      VALUES (?,?,?,?,?,?,?)")->execute([
            $q_num,
            (int)$_POST['customer_id'],
            (int)$_POST['branch_id'],
            $_POST['sale_type'],
            max(1,(int)($_POST['valid_days'] ?? 7)),
            trim($_POST['notes']),
            current_user()['id']
        ]);
        $q_id = $db->lastInsertId();

        $products = $_POST['item_product_id'] ?? [];
        $qtys     = $_POST['item_qty']        ?? [];
        $prices   = $_POST['item_price']      ?? [];
        $subtotal = 0;
        foreach ($products as $i => $prod_id) {
            $prod_id = (int)$prod_id;
            if (!$prod_id) continue;
            $qty   = max(1, (int)($qtys[$i] ?? 1));
            $price = (float)($prices[$i] ?? 0);
            $db->prepare("INSERT INTO quotation_items (quote_id,product_id,qty,unit_price) VALUES (?,?,?,?)")
               ->execute([$q_id, $prod_id, $qty, $price]);
            $subtotal += $qty * $price;
        }

        $tax   = calc_tax($subtotal);
        $total = $tax['total'];
        $db->prepare("UPDATE quotations SET subtotal=?, tax_amount=?, total_amount=? WHERE id=?")->execute([$subtotal, $tax['tax'], $total, $q_id]);
        $db->commit();
        header('Location: ' . BASE . '/quotations.php?success=' . urlencode('Quotation ' . $q_num . ' created'));
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        header('Location: ' . BASE . '/quotations.php?error=' . urlencode('Error: ' . $e->getMessage()));
        exit;
    }
}

// ── DELETE QUOTATION ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $qid = (int)$_POST['quote_id'];
    $db->prepare("DELETE FROM quotation_items WHERE quote_id=?")->execute([$qid]);
    $db->prepare("DELETE FROM quotations WHERE id=?")->execute([$qid]);
    header('Location: ' . BASE . '/quotations.php?success=' . urlencode('Quotation deleted'));
    exit;
}

// ── UPDATE STATUS ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_status') {
    $db->prepare("UPDATE quotations SET status=? WHERE id=?")->execute([$_POST['status'], (int)$_POST['quote_id']]);
    header('Location: ' . BASE . '/quotations.php?success=' . urlencode('Status updated'));
    exit;
}

// ── LIST ───────────────────────────────────────────────────────────────────
$filter = $_GET['status'] ?? '';
$type_f = $_GET['type']   ?? '';
$where  = 'WHERE 1';
$params = [];
if ($filter) { $where .= ' AND q.status=?';    $params[] = $filter; }
if ($type_f) { $where .= ' AND q.sale_type=?'; $params[] = $type_f; }

$page_num = max(1, (int)($_GET['p'] ?? 1));
$per_page = 20;
$offset   = ($page_num - 1) * $per_page;
$cnt_stmt = $db->prepare("SELECT COUNT(*) FROM quotations q $where");
$cnt_stmt->execute($params);
$total_quotes = $cnt_stmt->fetchColumn();
$total_pages  = ceil($total_quotes / $per_page);

$quotes = $db->prepare("
    SELECT q.*, c.name as customer_name, c.type as customer_type,
           b.name as branch_name,
           DATEDIFF(DATE_ADD(q.created_at, INTERVAL q.valid_days DAY), NOW()) as days_remaining
    FROM quotations q
    JOIN customers c ON c.id = q.customer_id
    JOIN branches  b ON b.id = q.branch_id
    $where ORDER BY q.created_at DESC LIMIT $per_page OFFSET $offset
");
$quotes->execute($params);
$quotes = $quotes->fetchAll();

$customers    = $db->query("SELECT id, name, type FROM customers WHERE id>1 AND is_active=1 ORDER BY name")->fetchAll();
$branches     = $db->query("SELECT id, name FROM branches WHERE is_active=1")->fetchAll();
$all_products = $db->query("
  SELECT p.id, p.name, p.sku, p.retail_price, p.wholesale_price,
         COALESCE(SUM(s.qty),0) as stock
  FROM products p LEFT JOIN stock s ON s.product_id=p.id
  WHERE p.is_active=1 GROUP BY p.id ORDER BY p.name
")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success" style="margin-bottom:16px">✅ <?= htmlspecialchars($_GET['success']) ?></div>
<?php elseif (isset($_GET['error'])): ?>
<div class="alert alert-error" style="margin-bottom:16px">❌ <?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<div class="inv-filters">
  <a href="quotations.php" class="filter-chip <?= !$filter?'active':'' ?>">All</a>
  <a href="quotations.php?status=draft"    class="filter-chip <?= $filter==='draft'?'active':'' ?>">Draft</a>
  <a href="quotations.php?status=sent"     class="filter-chip <?= $filter==='sent'?'active':'' ?>">Sent</a>
  <a href="quotations.php?status=accepted" class="filter-chip <?= $filter==='accepted'?'active':'' ?>">Accepted</a>
  <a href="quotations.php?status=expired"  class="filter-chip <?= $filter==='expired'?'active':'' ?>">Expired</a>
  <div style="margin-left:auto;display:flex;gap:6px">
    <select onchange="window.location='quotations.php?type='+this.value+'&status=<?= $filter ?>'" class="form-select" style="height:34px;font-size:12px;padding:0 10px;width:130px">
      <option value="" <?= !$type_f?'selected':'' ?>>All types</option>
      <option value="retail"    <?= $type_f==='retail'?'selected':'' ?>>Retail</option>
      <option value="wholesale" <?= $type_f==='wholesale'?'selected':'' ?>>Wholesale</option>
    </select>
    <button class="btn btn-primary" onclick="openModal('quote-modal')">+ New Quote</button>
  </div>
</div>

<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr>
          <th>Quote #</th><th>Customer</th><th>Type</th><th>Branch</th>
          <th>Date</th><th>Valid</th><th>Total</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($quotes as $q): ?>
        <tr>
          <td class="font-mono" style="font-size:12px"><?= htmlspecialchars($q['quote_number']) ?></td>
          <td><?= htmlspecialchars($q['customer_name']) ?></td>
          <td>
            <?php if ($q['sale_type']==='wholesale'): ?>
            <span class="badge badge-amber">Wholesale</span>
            <?php else: ?>
            <span class="badge badge-green">Retail</span>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($q['branch_name']) ?></td>
          <td style="font-size:11px;color:var(--text3)"><?= date('d M Y', strtotime($q['created_at'])) ?></td>
          <td>
            <?php if ($q['status']==='accepted' || $q['status']==='expired'): ?>
            <span style="color:var(--text3);font-size:11px">—</span>
            <?php elseif ($q['days_remaining'] < 0): ?>
            <span style="color:var(--red);font-size:11px">Expired</span>
            <?php elseif ($q['days_remaining'] === 0): ?>
            <span style="color:var(--amber);font-size:11px">Today</span>
            <?php else: ?>
            <span style="font-size:11px"><?= $q['days_remaining'] ?>d left</span>
            <?php endif; ?>
          </td>
          <td style="font-weight:600"><?= fmt_money($q['total_amount']) ?></td>
          <td>
            <?php
              $qbadge = ['draft'=>'badge-gray','sent'=>'badge-amber','accepted'=>'badge-green','declined'=>'badge-red','expired'=>'badge-gray'];
            ?>
            <span class="badge <?= $qbadge[$q['status']] ?? 'badge-gray' ?>"><span class="dot"></span><?= ucfirst($q['status']) ?></span>
          </td>
          <td>
            <div style="display:flex;gap:4px;align-items:center">
              <a href="<?= BASE ?>/quote_print.php?id=<?= $q['id'] ?>" target="_blank" class="btn btn-ghost btn-sm" title="Print">🖨️</a>
              <?php if ($q['status'] !== 'accepted'): ?>
              <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="set_status">
                <input type="hidden" name="quote_id" value="<?= $q['id'] ?>">
                <select name="status" onchange="this.form.submit()" class="form-select" style="height:28px;font-size:11px;padding:0 6px">
                  <option value="draft"    <?= $q['status']==='draft'?'selected':'' ?>>Draft</option>
                  <option value="sent"     <?= $q['status']==='sent'?'selected':'' ?>>Sent</option>
                  <option value="accepted" <?= $q['status']==='accepted'?'selected':'' ?>>Accepted</option>
                  <option value="declined" <?= $q['status']==='declined'?'selected':'' ?>>Declined</option>
                  <option value="expired"  <?= $q['status']==='expired'?'selected':'' ?>>Expired</option>
                </select>
              </form>
              <?php endif; ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete this quotation?')">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="quote_id" value="<?= $q['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--red)">🗑️</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($quotes)): ?>
        <tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text3)">No quotations found</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($total_pages > 1): ?>
  <div style="display:flex;justify-content:flex-end;margin-top:14px">
    <div class="pagination">
      <?php for ($i=1; $i<=$total_pages; $i++): ?>
      <a href="?status=<?= $filter ?>&type=<?= $type_f ?>&p=<?= $i ?>" class="page-link <?= $i===$page_num?'active':'' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════
     CREATE QUOTATION MODAL
     ══════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="quote-modal">
  <div class="modal" style="width:820px;max-width:96vw">
    <div class="modal-header">
      <div class="modal-title">📋 New Quotation</div>
      <button class="modal-close" onclick="closeModal('quote-modal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="modal-body" style="max-height:80vh;overflow-y:auto">

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Customer *</label>
            <select class="form-select" name="customer_id" id="q-customer" required onchange="onCustomerChange(this)">
              <option value="">Select customer...</option>
              <?php foreach ($customers as $c): ?>
              <option value="<?= $c['id'] ?>" data-type="<?= $c['type'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= ucfirst($c['type']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Sale Type *</label>
            <select class="form-select" name="sale_type" id="q-type" required onchange="onTypeChange(this.value)">
              <option value="retail">Retail</option>
              <option value="wholesale">Wholesale</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Branch *</label>
            <select class="form-select" name="branch_id" required>
              <?php foreach ($branches as $b): ?>
              <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Valid for (days)</label>
            <input class="form-input" name="valid_days" type="number" min="1" max="365" value="7">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea class="form-textarea" name="notes" rows="2"></textarea>
        </div>

        <!-- Line items -->
        <div style="margin-top:20px;margin-bottom:8px;font-weight:600;font-size:13px;color:var(--text1);display:flex;align-items:center;justify-content:space-between">
          <span>📋 Quote Items</span>
          <button type="button" class="btn btn-ghost btn-sm" onclick="addQLine()">+ Add Product</button>
        </div>

        <div style="background:var(--bg2);border-radius:8px;overflow:hidden;border:1px solid var(--border)">
          <table style="width:100%;border-collapse:collapse" id="q-lines-table">
            <thead>
              <tr style="background:var(--bg3)">
                <th style="padding:8px 10px;font-size:11px;font-weight:600;text-align:left">Product</th>
                <th style="padding:8px 10px;font-size:11px;font-weight:600;text-align:center;width:80px">Qty</th>
                <th style="padding:8px 10px;font-size:11px;font-weight:600;text-align:right;width:130px">Unit Price</th>
                <th style="padding:8px 10px;font-size:11px;font-weight:600;text-align:right;width:130px">Line Total</th>
                <th style="padding:8px 10px;width:36px"></th>
              </tr>
            </thead>
            <tbody id="q-lines-body"></tbody>
            <tfoot>
              <tr style="background:var(--bg3);border-top:2px solid var(--border)">
                <td colspan="3" style="padding:10px 12px;font-weight:700">Total</td>
                <td style="padding:10px 12px;font-weight:700;font-size:14px;text-align:right" id="q-grand-total">0.000 <?= $currency ?></td>
                <td></td>
              </tr>
            </tfoot>
          </table>
          <div id="q-empty-msg" style="padding:24px;text-align:center;color:var(--text3);font-size:13px">No items added — click "+ Add Product"</div>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('quote-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">💾 Create Quotation</button>
      </div>
    </form>
  </div>
</div>

<?php
$prods_json = json_encode(array_values($all_products), JSON_HEX_APOS|JSON_HEX_QUOT);
$cur_json   = json_encode($currency);
ob_start(); ?>
<script>
const Q_PRODUCTS = <?= $prods_json ?>;
const Q_CURRENCY = <?= $cur_json ?>;
let qLineCount = 0;
let currentSaleType = 'retail';

function onCustomerChange(sel) {
  const opt = sel.selectedOptions[0];
  if (opt && opt.dataset.type) {
    document.getElementById('q-type').value = opt.dataset.type;
    onTypeChange(opt.dataset.type);
  }
}

function onTypeChange(type) {
  currentSaleType = type;
  // Update all existing line prices
  document.querySelectorAll('[id^="q-price-"]').forEach(input => {
    const idx   = input.id.replace('q-price-', '');
    const prodSel = document.querySelector(`[id^="q-line-${idx}"] select`);
    if (prodSel) {
      const opt = prodSel.selectedOptions[0];
      if (opt && opt.dataset) {
        input.value = type === 'wholesale'
          ? parseFloat(opt.dataset.wholesale || opt.dataset.retail || 0).toFixed(3)
          : parseFloat(opt.dataset.retail || 0).toFixed(3);
        recalcQLine(idx);
      }
    }
  });
}

function addQLine() {
  qLineCount++;
  const idx = qLineCount;
  let opts = '<option value="">Select product...</option>';
  Q_PRODUCTS.forEach(p => {
    opts += `<option value="${p.id}" data-retail="${p.retail_price}" data-wholesale="${p.wholesale_price}" data-stock="${p.stock}">${p.name} (${p.sku}) — Stock: ${p.stock}</option>`;
  });

  const row = document.createElement('tr');
  row.id = `q-line-${idx}`;
  row.style.borderTop = '1px solid var(--border)';
  row.innerHTML = `
    <td style="padding:6px 10px">
      <select name="item_product_id[]" class="form-select" style="margin:0" onchange="onQProductChange(this, ${idx})" required>
        ${opts}
      </select>
    </td>
    <td style="padding:6px 10px;text-align:center">
      <input type="number" name="item_qty[]" id="q-qty-${idx}" value="1" min="1" class="form-input"
             style="margin:0;text-align:center;width:65px" oninput="recalcQLine(${idx})">
    </td>
    <td style="padding:6px 10px">
      <input type="number" name="item_price[]" id="q-price-${idx}" value="0.000" min="0" step="0.001"
             class="form-input" style="margin:0;text-align:right;width:110px" oninput="recalcQLine(${idx})">
    </td>
    <td style="padding:6px 10px;text-align:right;font-weight:600" id="q-line-total-${idx}">0.000 ${Q_CURRENCY}</td>
    <td style="padding:6px 10px;text-align:center">
      <button type="button" onclick="removeQLine(${idx})" style="background:none;border:none;cursor:pointer;color:var(--red);font-size:16px;padding:2px">✕</button>
    </td>
  `;
  document.getElementById('q-lines-body').appendChild(row);
  document.getElementById('q-empty-msg').style.display = 'none';
  recalcQTotal();
}

function onQProductChange(sel, idx) {
  const opt = sel.selectedOptions[0];
  if (opt && opt.dataset) {
    const price = currentSaleType === 'wholesale'
      ? parseFloat(opt.dataset.wholesale || opt.dataset.retail || 0)
      : parseFloat(opt.dataset.retail || 0);
    document.getElementById('q-price-' + idx).value = price.toFixed(3);
    recalcQLine(idx);
  }
}

function recalcQLine(idx) {
  const qty   = parseFloat(document.getElementById('q-qty-'   + idx)?.value || 0);
  const price = parseFloat(document.getElementById('q-price-' + idx)?.value || 0);
  const el    = document.getElementById('q-line-total-' + idx);
  if (el) el.textContent = (qty * price).toFixed(3) + ' ' + Q_CURRENCY;
  recalcQTotal();
}

function recalcQTotal() {
  let grand = 0;
  document.querySelectorAll('[id^="q-line-total-"]').forEach(el => { grand += parseFloat(el.textContent) || 0; });
  document.getElementById('q-grand-total').textContent = grand.toFixed(3) + ' ' + Q_CURRENCY;
}

function removeQLine(idx) {
  const row = document.getElementById('q-line-' + idx);
  if (row) row.remove();
  if (!document.querySelectorAll('[id^="q-line-"]').length) document.getElementById('q-empty-msg').style.display = '';
  recalcQTotal();
}
</script>
<?php
$extra_js = ob_get_clean();
require __DIR__ . '/includes/footer.php'; ?>
