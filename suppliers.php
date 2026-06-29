<?php
require_once __DIR__ . '/includes/config.php';
require_login();
require_role('super_admin');
$current_page = 'suppliers';
$page_title   = __('supplier_management');
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'add') {
        try {
            $db->prepare("INSERT INTO suppliers (company,company_ar,contact_name,contact_name_ar,email,phone,payment_terms,address,address_ar) VALUES (?,?,?,?,?,?,?,?,?)")->execute([
                trim($_POST['company']), trim($_POST['company_ar'] ?? ''),
                trim($_POST['contact_name'] ?? ''), trim($_POST['contact_name_ar'] ?? ''),
                trim($_POST['email'] ?? ''), trim($_POST['phone'] ?? ''),
                $_POST['payment_terms'] ?? 'Net 30',
                trim($_POST['address'] ?? ''), trim($_POST['address_ar'] ?? '')
            ]);
            header('Location: ' . BASE . '/suppliers.php?success=' . urlencode('Supplier added'));
        } catch (Exception $e) {
            header('Location: ' . BASE . '/suppliers.php?error=' . urlencode('Failed to add supplier: ' . $e->getMessage()));
        }
        exit;
    }
    if ($act === 'edit') {
        try {
            $db->prepare("UPDATE suppliers SET company=?,company_ar=?,contact_name=?,contact_name_ar=?,email=?,phone=?,payment_terms=?,address=?,address_ar=? WHERE id=?")->execute([
                trim($_POST['company']), trim($_POST['company_ar'] ?? ''),
                trim($_POST['contact_name'] ?? ''), trim($_POST['contact_name_ar'] ?? ''),
                trim($_POST['email'] ?? ''), trim($_POST['phone'] ?? ''),
                $_POST['payment_terms'] ?? 'Net 30',
                trim($_POST['address'] ?? ''), trim($_POST['address_ar'] ?? ''),
                (int)$_POST['supplier_id']
            ]);
            header('Location: ' . BASE . '/suppliers.php?success=' . urlencode('Supplier updated'));
        } catch (Exception $e) {
            header('Location: ' . BASE . '/suppliers.php?error=' . urlencode('Failed to update supplier: ' . $e->getMessage()));
        }
        exit;
    }
    if ($act === 'delete') {
        $sid = (int)$_POST['supplier_id'];
        $has_po = $db->prepare("SELECT COUNT(*) FROM purchase_orders WHERE supplier_id=?");
        $has_po->execute([$sid]);
        if ($has_po->fetchColumn() > 0) {
            header('Location: ' . BASE . '/suppliers.php?error=' . urlencode('Cannot delete: supplier has purchase orders')); exit;
        }
        $db->prepare("UPDATE suppliers SET is_active=0 WHERE id=?")->execute([$sid]);
        header('Location: ' . BASE . '/suppliers.php?success=' . urlencode('Supplier deactivated')); exit;
    }
}

// Stats
$total_sup  = $db->query("SELECT COUNT(*) FROM suppliers WHERE is_active=1")->fetchColumn();
$total_due  = $db->query("SELECT COALESCE(SUM(ABS(balance)),0) FROM suppliers WHERE balance<0 AND is_active=1")->fetchColumn();
$paid_month = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE type='supplier' AND MONTH(created_at)=MONTH(NOW())")->fetchColumn();
$pending_po = $db->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('pending','partial')")->fetchColumn();

// Search
$search = trim($_GET['search'] ?? '');
$where  = "WHERE s.is_active = 1";
$params = [];
if ($search) {
    $where .= " AND (s.company LIKE ? OR s.company_ar LIKE ? OR s.contact_name LIKE ?)";
    $s = "%$search%"; $params = [$s,$s,$s];
}

$suppliers = $db->prepare("
    SELECT s.id,
           s.company, COALESCE(s.company_ar,'') as company_ar,
           s.contact_name, COALESCE(s.contact_name_ar,'') as contact_name_ar,
           COALESCE(s.email,'') as email,
           COALESCE(s.phone,'') as phone,
           COALESCE(s.address,'') as address,
           COALESCE(s.address_ar,'') as address_ar,
           s.payment_terms, s.balance, s.vat_number,
           COUNT(po.id) as po_count
    FROM suppliers s
    LEFT JOIN purchase_orders po ON po.supplier_id = s.id
    $where
    GROUP BY s.id ORDER BY s.company
");
$suppliers->execute($params);
$suppliers = $suppliers->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<!-- Filters -->
<div class="inv-filters">
  <form method="GET" style="display:flex;gap:8px;flex:1;flex-wrap:wrap;align-items:center">
    <input class="search-input" name="search" placeholder="🔍 <?= __('search') ?>..." style="flex:1;min-width:160px" value="<?= htmlspecialchars($search) ?>">
    <button type="submit" class="btn btn-ghost"><?= __('search') ?></button>
    <?php if ($search): ?><a href="<?= BASE ?>/suppliers.php" class="btn btn-ghost">↺</a><?php endif; ?>
  </form>
  <button class="btn btn-primary" onclick="openModal('supplier-modal')">+ <?= __('add') ?></button>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr))">
  <div class="stat-card blue"><div class="stat-label"><?= __('nav_suppliers') ?></div><div class="stat-value text-blue"><?= $total_sup ?></div></div>
  <div class="stat-card red"><div class="stat-label"><?= __('owing') ?></div><div class="stat-value text-red"><?= fmt_money($total_due) ?></div></div>
  <div class="stat-card green"><div class="stat-label"><?= __('paid_this_month') ?></div><div class="stat-value text-green"><?= fmt_money($paid_month) ?></div></div>
  <div class="stat-card amber"><div class="stat-label"><?= __('pending_orders') ?></div><div class="stat-value text-amber"><?= $pending_po ?></div></div>
</div>

<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead><tr>
        <th><?= __('company') ?></th>
        <th class="hide-mobile"><?= __('contact') ?></th>
        <th class="hide-mobile"><?= __('phone') ?></th>
        <th><?= __('owing') ?></th>
        <th class="hide-tablet"><?= __('payment_terms') ?></th>
        <th class="hide-tablet">POs</th>
        <th><?= __('actions') ?></th>
      </tr></thead>
      <tbody>
        <?php foreach ($suppliers as $s): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <div class="ledger-avatar" style="background:rgba(245,166,35,.1);color:var(--amber);font-size:14px;flex-shrink:0">🏭</div>
              <div style="min-width:0">
                <div style="font-weight:500" class="truncate"><?= htmlspecialchars($s['company']) ?></div>
                <?php if ($s['company_ar']): ?><div style="font-size:11px;color:var(--text3);direction:rtl" class="truncate"><?= htmlspecialchars($s['company_ar']) ?></div><?php endif; ?>
              </div>
            </div>
          </td>
          <td class="hide-mobile">
            <div><?= htmlspecialchars($s['contact_name']) ?></div>
            <?php if ($s['contact_name_ar']): ?><div style="font-size:11px;color:var(--text3);direction:rtl"><?= htmlspecialchars($s['contact_name_ar']) ?></div><?php endif; ?>
          </td>
          <td class="hide-mobile" style="font-size:12px;white-space:nowrap"><?= htmlspecialchars($s['phone']) ?></td>
          <td style="font-weight:600;white-space:nowrap;color:<?= $s['balance']<0?'var(--red)':'var(--green)' ?>">
            <?= $s['balance'] < 0 ? fmt_money(abs($s['balance'])) : '<span class="badge badge-green">✓ ' . __('clear') . '</span>' ?>
          </td>
          <td class="hide-tablet"><?= htmlspecialchars($s['payment_terms']) ?></td>
          <td class="hide-tablet"><?= $s['po_count'] ?></td>
          <td>
            <div style="display:flex;gap:4px;flex-wrap:wrap">
              <button class="btn btn-ghost btn-sm" onclick='editSupplier(<?= json_encode($s, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>✏️</button>
              <?php if ($s['balance'] < 0): ?>
              <a href="<?= BASE ?>/payments.php?supplier_id=<?= $s['id'] ?>" class="btn btn-sm btn-green">Pay</a>
              <?php else: ?>
              <a href="<?= BASE ?>/purchases.php" class="btn btn-sm btn-ghost">+ Order</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($suppliers)): ?>
        <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text3)"><?= __('no_data') ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ADD/EDIT SUPPLIER MODAL -->
<div class="modal-backdrop" id="supplier-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="sup-modal-title"><?= __('add') ?> <?= __('nav_suppliers') ?></div>
      <button class="modal-close" onclick="closeModal('supplier-modal')">✕</button>
    </div>
    <form method="POST" id="sup-form">
      <input type="hidden" name="action" value="add" id="sup-action">
      <input type="hidden" name="supplier_id" value="" id="sup-id">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('company') ?> (EN) *</label><input class="form-input" name="company" id="sup-company" required placeholder="Gulf Bags Trading"></div>
          <div class="form-group"><label class="form-label"><?= __('contact') ?></label><input class="form-input" name="contact_name" id="sup-contact" placeholder="Ali Hassan"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('company') ?> (<?= __('arabic') ?>)</label><input class="form-input" name="company_ar" id="sup-company-ar" placeholder="حقائب الخليج" style="direction:rtl"></div>
          <div class="form-group"><label class="form-label"><?= __('contact') ?> (<?= __('arabic') ?>)</label><input class="form-input" name="contact_name_ar" id="sup-contact-ar" placeholder="علي حسن" style="direction:rtl"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('email') ?></label><input class="form-input" name="email" id="sup-email" type="email"></div>
          <div class="form-group"><label class="form-label"><?= __('phone') ?></label><input class="form-input" name="phone" id="sup-phone" placeholder="+965 2200-1100"></div>
        </div>
        <div class="form-group"><label class="form-label"><?= __('payment_terms') ?></label>
          <select class="form-select" name="payment_terms" id="sup-terms">
            <option>Net 15</option><option selected>Net 30</option><option>Net 45</option><option>Net 60</option><option>Cash on Delivery</option>
          </select>
        </div>
        <div class="form-group"><label class="form-label"><?= __('address') ?> (EN)</label><textarea class="form-textarea" name="address" id="sup-address" rows="2"></textarea></div>
        <div class="form-group"><label class="form-label"><?= __('address') ?> (<?= __('arabic') ?>)</label><textarea class="form-textarea" name="address_ar" id="sup-address-ar" placeholder="العنوان..." style="direction:rtl" rows="2"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('supplier-modal')"><?= __('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= __('save') ?></button>
      </div>
    </form>
  </div>
</div>

<?php
$extra_js = '<script>
function editSupplier(s) {
  document.getElementById("sup-action").value = "edit";
  document.getElementById("sup-id").value = s.id;
  document.getElementById("sup-company").value = s.company || "";
  document.getElementById("sup-company-ar").value = s.company_ar || "";
  document.getElementById("sup-contact").value = s.contact_name || "";
  document.getElementById("sup-contact-ar").value = s.contact_name_ar || "";
  document.getElementById("sup-email").value = s.email || "";
  document.getElementById("sup-phone").value = s.phone || "";
  document.getElementById("sup-terms").value = s.payment_terms || "Net 30";
  document.getElementById("sup-address").value = s.address || "";
  document.getElementById("sup-address-ar").value = s.address_ar || "";
  document.getElementById("sup-modal-title").textContent = "' . __('edit') . ' ' . __('nav_suppliers') . '";
  openModal("supplier-modal");
}
// Reset on open for add
document.querySelector("[onclick=\"openModal(\'supplier-modal\')\"]")?.addEventListener("click", function() {
  if (!document.getElementById("sup-id").value) {
    document.getElementById("sup-form").reset();
    document.getElementById("sup-action").value = "add";
    document.getElementById("sup-modal-title").textContent = "' . __('add') . ' ' . __('nav_suppliers') . '";
  }
});
</script>';
require __DIR__ . '/includes/footer.php';
