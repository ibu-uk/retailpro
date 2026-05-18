<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$current_page = 'suppliers';
$page_title   = __('supplier_management');
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {
    $db->prepare("INSERT INTO suppliers (company,company_ar,contact_name,contact_name_ar,email,phone,payment_terms,address,address_ar) VALUES (?,?,?,?,?,?,?,?,?)")->execute([
        trim($_POST['company']), trim($_POST['company_ar'] ?? ''), trim($_POST['contact_name']), trim($_POST['contact_name_ar'] ?? ''),
        trim($_POST['email']), trim($_POST['phone']), $_POST['payment_terms'], trim($_POST['address']), trim($_POST['address_ar'] ?? '')
    ]);
    header('Location: ' . BASE . '/suppliers.php?success=' . urlencode('Supplier added'));
    exit;
}

$total_sup  = $db->query("SELECT COUNT(*) FROM suppliers WHERE is_active=1")->fetchColumn();
$total_due  = $db->query("SELECT COALESCE(SUM(ABS(balance)),0) FROM suppliers WHERE balance<0")->fetchColumn();
$paid_month = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE type='supplier' AND MONTH(created_at)=MONTH(NOW())")->fetchColumn();
$pending_po = $db->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('pending','partial')")->fetchColumn();

$suppliers = $db->query("
    SELECT s.*, COUNT(po.id) as po_count
    FROM suppliers s LEFT JOIN purchase_orders po ON po.supplier_id = s.id
    WHERE s.is_active = 1 GROUP BY s.id ORDER BY s.company
")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div style="display:flex;justify-content:flex-end;margin-bottom:16px">
  <button class="btn btn-primary" onclick="openModal('supplier-modal')">+ <?= __('add') ?></button>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:16px">
  <div class="stat-card blue"><div class="stat-label"><?= __('nav_suppliers') ?></div><div class="stat-value text-blue"><?= $total_sup ?></div></div>
  <div class="stat-card red"><div class="stat-label"><?= __('owing') ?></div><div class="stat-value text-red"><?= fmt_money($total_due) ?></div></div>
  <div class="stat-card green"><div class="stat-label"><?= __('paid_this_month') ?></div><div class="stat-value text-green"><?= fmt_money($paid_month) ?></div></div>
  <div class="stat-card amber"><div class="stat-label"><?= __('pending_orders') ?></div><div class="stat-value text-amber"><?= $pending_po ?></div></div>
</div>

<div class="card">
  <div class="tbl-wrap">
    <table>
      <thead><tr><th><?= __('company') ?></th><th><?= __('contact') ?></th><th><?= __('phone') ?></th><th><?= __('owing') ?></th><th><?= __('payment_terms') ?></th><th><?= __('purchase_orders') ?></th><th><?= __('actions') ?></th></tr></thead>
      <tbody>
        <?php foreach ($suppliers as $s): ?>
        <tr>
          <td style="font-weight:500">🏭 <?= htmlspecialchars($s['company']) ?><?php if ($s['company_ar']) echo '<br><span style="font-size:11px;color:var(--text3)">' . htmlspecialchars($s['company_ar']) . '</span>'; ?></td>
          <td><?= htmlspecialchars($s['contact_name']) ?><?php if ($s['contact_name_ar']) echo '<br><span style="font-size:11px;color:var(--text3)">' . htmlspecialchars($s['contact_name_ar']) . '</span>'; ?></td>
          <td class="font-mono" style="font-size:12px"><?= htmlspecialchars($s['phone']) ?></td>
          <td style="font-weight:600;color:<?= $s['balance']<0?'var(--red)':'var(--green)' ?>">
            <?= $s['balance'] < 0 ? fmt_money(abs($s['balance'])) : '✓ ' . __('clear') ?>
          </td>
          <td><?= htmlspecialchars($s['payment_terms']) ?></td>
          <td><?= $s['po_count'] ?></td>
          <td>
            <div style="display:flex;gap:4px">
              <button class="btn btn-ghost btn-sm" onclick="showToast('Ledger','Opening supplier ledger...','success')">Ledger</button>
              <?php if ($s['balance'] < 0): ?>
              <a href="<?= BASE ?>/payments.php?supplier_id=<?= $s['id'] ?>" class="btn btn-sm btn-green">Pay</a>
              <?php else: ?>
              <a href="<?= BASE ?>/purchases.php?supplier_id=<?= $s['id'] ?>" class="btn btn-sm btn-ghost">+ Order</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ADD SUPPLIER MODAL -->
<div class="modal-backdrop" id="supplier-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title"><?= __('add') ?> <?= __('nav_suppliers') ?></div>
      <button class="modal-close" onclick="closeModal('supplier-modal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="modal-body" style="max-height:60vh;overflow-y:auto">
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('company') ?> *</label><input class="form-input" name="company" required placeholder="Gulf Bags Trading"></div>
          <div class="form-group"><label class="form-label"><?= __('contact') ?></label><input class="form-input" name="contact_name" placeholder="Ali Hassan"></div>
        </div>
        <div class="form-group"><label class="form-label"><?= __('company') ?> (<?= __('arabic') ?>)</label><input class="form-input" name="company_ar" placeholder="حقائب الخليج للتجارة" style="direction:rtl"></div>
        <div class="form-group"><label class="form-label"><?= __('contact') ?> (<?= __('arabic') ?>)</label><input class="form-input" name="contact_name_ar" placeholder="علي حسن" style="direction:rtl"></div>
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('email') ?></label><input class="form-input" name="email" type="email"></div>
          <div class="form-group"><label class="form-label"><?= __('phone') ?></label><input class="form-input" name="phone" placeholder="+965 2200-1100"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('payment_terms') ?></label>
            <select class="form-select" name="payment_terms">
              <option>Net 15</option><option selected>Net 30</option><option>Net 45</option><option>Net 60</option><option>Cash on Delivery</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label class="form-label"><?= __('address') ?></label><textarea class="form-textarea" name="address"></textarea></div>
        <div class="form-group"><label class="form-label"><?= __('address') ?> (<?= __('arabic') ?>)</label><textarea class="form-textarea" name="address_ar" placeholder="العنوان..." style="direction:rtl"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('supplier-modal')"><?= __('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= __('save') ?></button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
