<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$current_page = 'customers';
$page_title   = __('customer_management');
$db = db();
$currency = get_setting('currency', 'KWD');

// Add customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {
    $db->prepare("INSERT INTO customers (name,name_ar,email,phone,type,credit_limit,address,address_ar) VALUES (?,?,?,?,?,?,?,?)")->execute([
        trim($_POST['name']), trim($_POST['name_ar'] ?? ''), trim($_POST['email']), trim($_POST['phone']),
        $_POST['type'], (float)$_POST['credit_limit'], trim($_POST['address']), trim($_POST['address_ar'] ?? '')
    ]);
    header('Location: ' . BASE . '/customers.php?success=' . urlencode('Customer added'));
    exit;
}

// Edit customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $db->prepare("UPDATE customers SET name=?,name_ar=?,email=?,phone=?,type=?,credit_limit=?,address=?,address_ar=? WHERE id=?")->execute([
        trim($_POST['name']), trim($_POST['name_ar'] ?? ''), trim($_POST['email']), trim($_POST['phone']),
        $_POST['type'], (float)$_POST['credit_limit'], trim($_POST['address']), trim($_POST['address_ar'] ?? ''), (int)$_POST['customer_id']
    ]);
    header('Location: ' . BASE . '/customers.php?success=' . urlencode('Customer updated'));
    exit;
}

// Delete customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $cid = (int)$_POST['customer_id'];
    $has_inv = $db->prepare("SELECT COUNT(*) FROM invoices WHERE customer_id=?"); $has_inv->execute([$cid]);
    if ($has_inv->fetchColumn() > 0) {
        header('Location: ' . BASE . '/customers.php?error=' . urlencode('Cannot delete: customer has invoices'));
        exit;
    }
    $db->prepare("DELETE FROM customers WHERE id=?")->execute([$cid]);
    header('Location: ' . BASE . '/customers.php?success=' . urlencode('Customer deleted'));
    exit;
}

$search = trim($_GET['search'] ?? '');
$type_f = $_GET['type'] ?? '';
$where  = "WHERE c.id > 1";
$params = [];
if ($search) { $where .= " AND (c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)"; $params = array_fill(0,3,"%$search%"); }
if ($type_f) { $where .= " AND c.type = ?"; $params[] = $type_f; }

// Pagination
$page_num = max(1, (int)($_GET['p'] ?? 1));
$per_page = 20;
$offset   = ($page_num - 1) * $per_page;
$count_stmt = $db->prepare("SELECT COUNT(*) FROM customers c $where");
$count_stmt->execute($params);
$total_custs = $count_stmt->fetchColumn();
$total_pages = ceil($total_custs / $per_page);

$customers = $db->prepare("
    SELECT c.*, COUNT(i.id) as invoice_count, MAX(i.created_at) as last_purchase
    FROM customers c LEFT JOIN invoices i ON i.customer_id = c.id
    $where GROUP BY c.id ORDER BY c.name LIMIT $per_page OFFSET $offset
");
$customers->execute($params);
$customers = $customers->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="inv-filters">
  <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;flex:1">
    <input class="search-input" name="search" placeholder="<?= __('search') ?>..." style="width:260px" value="<?= htmlspecialchars($search) ?>">
    <select class="search-input" name="type" style="width:160px" onchange="this.form.submit()">
      <option value=""><?= __('all') ?></option>
      <option value="retail" <?= $type_f==='retail'?'selected':'' ?>><?= __('retail') ?></option>
      <option value="wholesale" <?= $type_f==='wholesale'?'selected':'' ?>><?= __('wholesale') ?></option>
    </select>
    <button type="submit" class="btn btn-ghost"><?= __('search') ?></button>
    <a href="<?= BASE ?>/customers.php" class="btn btn-ghost">Reset</a>
  </form>
  <div style="display:flex;gap:6px">
    <a href="<?= BASE ?>/api/export_customers.php" class="btn btn-ghost btn-sm">📊 Excel</a>
    <button type="button" class="btn btn-ghost btn-sm" onclick="printCustomers()">🖨️ Print</button>
    <button class="btn btn-primary" onclick="openAddCustomer()">+ <?= __('add') ?> <?= __('customer_name') ?></button>
  </div>
</div>

<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead>
        <tr><th><?= __('customer_name') ?></th><th><?= __('type') ?></th><th><?= __('phone') ?></th><th><?= __('balance') ?></th><th><?= __('credit_limit') ?></th><th><?= __('last_purchase') ?></th><th><?= __('invoice') ?></th><th><?= __('status') ?></th><th><?= __('actions') ?></th></tr>
      </thead>
      <tbody>
        <?php foreach ($customers as $c): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div class="ledger-avatar" style="background:rgba(67,97,238,.08);color:var(--accent);font-size:11px"><?= strtoupper(substr($c['name'],0,2)) ?></div>
              <div>
                <div style="font-weight:500"><?= htmlspecialchars($c['name']) ?><?php if ($c['name_ar']) echo '<br><span style="font-size:11px;color:var(--text3)">' . htmlspecialchars($c['name_ar']) . '</span>'; ?></div>
                <div style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($c['email']) ?></div>
              </div>
            </div>
          </td>
          <td><span class="badge <?= $c['type']==='wholesale'?'badge-purple':'badge-blue' ?>"><?= ucfirst($c['type']) ?></span></td>
          <td class="font-mono" style="font-size:12px"><?= htmlspecialchars($c['phone']) ?></td>
          <td style="font-weight:600;color:<?= $c['balance']<0?'var(--red)':($c['balance']>0?'var(--green)':'var(--text2)') ?>">
            <?= $c['balance'] < 0 ? '-' : '' ?><?= fmt_money(abs($c['balance'])) ?>
          </td>
          <td><?= fmt_money($c['credit_limit']) ?></td>
          <td style="font-size:12px;color:var(--text3)"><?= $c['last_purchase'] ? date('d M Y', strtotime($c['last_purchase'])) : '-' ?></td>
          <td><?= $c['invoice_count'] ?></td>
          <td>
            <?php if ($c['balance'] < 0): ?>
              <span class="badge badge-red"><span class="dot"></span><?= __('owing') ?></span>
            <?php elseif ($c['balance'] > 0): ?>
              <span class="badge badge-green"><span class="dot"></span><?= __('advance') ?></span>
            <?php else: ?>
              <span class="badge badge-gray"><span class="dot"></span><?= __('clear') ?></span>
            <?php endif; ?>
          </td>
          <td>
            <div style="display:flex;gap:4px">
              <button class="btn btn-ghost btn-sm" onclick='editCustomer(<?= json_encode($c, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>✏️</button>
              <a href="<?= BASE ?>/payments.php" class="btn btn-sm btn-green" title="Collect Payment">💰</a>
              <?php if ($c['invoice_count'] == 0): ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Delete this customer?')">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--red)">�️</button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($customers)): ?>
        <tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text3)"><?= __('no_data') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;font-size:12px;color:var(--text3)">
    <span><?= __('showing') ?> <?= count($customers) ?> <?= __('of') ?> <?= $total_custs ?></span>
    <div class="pagination">
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
      <a href="?search=<?= urlencode($search) ?>&type=<?= $type_f ?>&p=<?= $i ?>" class="page-link <?= $i === $page_num ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  </div>
</div>

<!-- CUSTOMER MODAL (Add/Edit) -->
<div class="modal-backdrop" id="customer-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="cust-modal-title"><?= __('add') ?> <?= __('customer_name') ?></div>
      <button class="modal-close" onclick="closeModal('customer-modal')">✕</button>
    </div>
    <form method="POST" id="cust-form">
      <input type="hidden" name="action" value="add" id="cust-action">
      <input type="hidden" name="customer_id" value="" id="cust-id">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('full_name') ?> *</label><input class="form-input" name="name" id="cust-name" required placeholder="Ahmad Al-Mutairi"></div>
          <div class="form-group"><label class="form-label"><?= __('phone') ?></label><input class="form-input" name="phone" id="cust-phone" placeholder="+965 9988-7766"></div>
        </div>
        <div class="form-group"><label class="form-label"><?= __('name') ?> (<?= __('arabic') ?>)</label><input class="form-input" name="name_ar" id="cust-name-ar" placeholder="أحمد المطيري" style="direction:rtl"></div>
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('email') ?></label><input class="form-input" name="email" id="cust-email" type="email" placeholder="email@example.com"></div>
          <div class="form-group"><label class="form-label"><?= __('type') ?></label>
            <select class="form-select" name="type" id="cust-type">
              <option value="retail"><?= __('retail') ?></option>
              <option value="wholesale"><?= __('wholesale') ?></option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('credit_limit') ?> (<?= $currency ?>)</label><input class="form-input" name="credit_limit" id="cust-limit" type="number" step="0.001" min="0" value="0"></div>
        </div>
        <div class="form-group"><label class="form-label"><?= __('address') ?></label><textarea class="form-textarea" name="address" id="cust-address"></textarea></div>
        <div class="form-group"><label class="form-label"><?= __('address') ?> (<?= __('arabic') ?>)</label><textarea class="form-textarea" name="address_ar" id="cust-address-ar" placeholder="العنوان..." style="direction:rtl"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('customer-modal')"><?= __('cancel') ?></button>
        <button type="submit" class="btn btn-primary" id="cust-submit-btn"><?= __('save') ?></button>
      </div>
    </form>
  </div>
</div>

<?php
$extra_js = '<script>
function openAddCustomer() {
  document.getElementById("cust-form").reset();
  document.getElementById("cust-action").value = "add";
  document.getElementById("cust-id").value = "";
  document.getElementById("cust-modal-title").textContent = "' . __('add') . ' ' . __('customer_name') . '";
  document.getElementById("cust-submit-btn").textContent = "' . __('save') . '";
  openModal("customer-modal");
}
function editCustomer(c) {
  document.getElementById("cust-action").value = "edit";
  document.getElementById("cust-id").value = c.id;
  document.getElementById("cust-name").value = c.name;
  document.getElementById("cust-name-ar").value = c.name_ar || "";
  document.getElementById("cust-phone").value = c.phone || "";
  document.getElementById("cust-email").value = c.email || "";
  document.getElementById("cust-type").value = c.type;
  document.getElementById("cust-limit").value = parseFloat(c.credit_limit).toFixed(3);
  document.getElementById("cust-address").value = c.address || "";
  document.getElementById("cust-address-ar").value = c.address_ar || "";
  document.getElementById("cust-modal-title").textContent = "' . __('edit') . ' ' . __('customer_name') . '";
  document.getElementById("cust-submit-btn").textContent = "' . __('save') . '";
  openModal("customer-modal");
}
function printCustomers() {
  const table = document.querySelector(".tbl-wrap table");
  const win = window.open("","_blank");
  win.document.write("<html><head><title>Customers</title><style>body{font-family:Arial,sans-serif;padding:20px}table{width:100%;border-collapse:collapse;font-size:12px}th,td{border:1px solid #ddd;padding:6px 8px;text-align:left}th{background:#f0f2f5;font-weight:600}.header{text-align:center;margin-bottom:20px}</style></head><body>");
  win.document.write("<div class=header><h2>RetailPro — Customer List</h2></div>");
  const clone = table.cloneNode(true);
  clone.querySelectorAll("tr").forEach(row => { const cells = row.querySelectorAll("th,td"); if(cells.length>=9) cells[cells.length-1].remove(); });
  win.document.write(clone.outerHTML);
  win.document.write("</body></html>");
  win.document.close();
  win.print();
}
</script>';
require __DIR__ . '/includes/footer.php'; ?>
