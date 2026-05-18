<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$current_page = 'branches';
$page_title   = __('branch_management');
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {
    $db->prepare("INSERT INTO branches (name,address,phone,manager_name) VALUES (?,?,?,?)")->execute([
        trim($_POST['name']), trim($_POST['address']), trim($_POST['phone']), trim($_POST['manager_name'])
    ]);
    header('Location: ' . BASE . '/branches.php?success=' . urlencode('Branch added'));
    exit;
}

$branches = $db->query("SELECT * FROM branches WHERE is_active=1 ORDER BY id")->fetchAll();

// Today sales per branch
$branch_stats = [];
foreach ($branches as $b) {
    $today = $db->prepare("SELECT COALESCE(SUM(total),0) as s, COUNT(*) as c FROM invoices WHERE branch_id=? AND DATE(created_at)=CURDATE()");
    $today->execute([$b['id']]);
    $row = $today->fetch();
    $cash = $db->prepare("SELECT COALESCE(SUM(total),0) as s FROM invoices WHERE branch_id=? AND payment_mode='cash' AND DATE(created_at)=CURDATE()");
    $cash->execute([$b['id']]);
    $branch_stats[$b['id']] = [
        'sales' => $row['s'],
        'orders' => $row['c'],
        'cash' => $cash->fetch()['s'],
    ];
}

$border_colors = ['var(--accent)', 'var(--blue)', 'var(--green)', 'var(--pink)'];

require __DIR__ . '/includes/header.php';
?>

<div style="display:flex;justify-content:flex-end;margin-bottom:16px">
  <button class="btn btn-primary" onclick="openModal('branch-modal')">+ <?= __('add') ?></button>
</div>

<div class="grid-2">
  <?php foreach ($branches as $i => $b): $s = $branch_stats[$b['id']]; ?>
  <div class="card" style="border-left:3px solid <?= $border_colors[$i % 4] ?>">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px">
      <div style="font-size:24px">🏪</div>
      <div>
        <div style="font-size:15px;font-weight:600"><?= htmlspecialchars($b['name']) ?></div>
        <div style="font-size:12px;color:var(--text3)"><?= htmlspecialchars($b['address']) ?></div>
      </div>
      <span class="badge badge-green" style="margin-left:auto"><?= __('active') ?></span>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:12px">
      <div style="background:var(--bg3);border-radius:8px;padding:10px">
        <div style="font-size:11px;color:var(--text3)"><?= __('todays_sales') ?></div>
        <div style="font-size:16px;font-weight:600;color:var(--green)"><?= fmt_money($s['sales']) ?></div>
      </div>
      <div style="background:var(--bg3);border-radius:8px;padding:10px">
        <div style="font-size:11px;color:var(--text3)"><?= __('cash') ?></div>
        <div style="font-size:16px;font-weight:600;color:var(--blue)"><?= fmt_money($s['cash']) ?></div>
      </div>
      <div style="background:var(--bg3);border-radius:8px;padding:10px">
        <div style="font-size:11px;color:var(--text3)"><?= __('orders') ?></div>
        <div style="font-size:16px;font-weight:600;color:var(--accent2)"><?= $s['orders'] ?></div>
      </div>
    </div>
    <div style="font-size:12px;color:var(--text2)">
      👤 <?= htmlspecialchars($b['manager_name']) ?> &nbsp;|&nbsp; 📞 <?= htmlspecialchars($b['phone']) ?>
    </div>
    <div style="display:flex;gap:8px;margin-top:12px">
      <a href="<?= BASE ?>/reports.php?branch_id=<?= $b['id'] ?>" class="btn btn-ghost btn-sm"><?= __('full_report') ?></a>
      <button class="btn btn-ghost btn-sm" onclick="showToast('Transfer','Stock transfer form opened.','success')"><?= __('transfer') ?></button>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ADD BRANCH MODAL -->
<div class="modal-backdrop" id="branch-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title"><?= __('add') ?> <?= __('nav_branches') ?></div>
      <button class="modal-close" onclick="closeModal('branch-modal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('name') ?> *</label><input class="form-input" name="name" required placeholder="e.g. Jabriya Branch"></div>
          <div class="form-group"><label class="form-label"><?= __('manager') ?></label><input class="form-input" name="manager_name" placeholder="e.g. Ahmad Karim"></div>
        </div>
        <div class="form-group"><label class="form-label"><?= __('phone') ?></label><input class="form-input" name="phone" placeholder="+965 2200-0000"></div>
        <div class="form-group"><label class="form-label"><?= __('address') ?></label><textarea class="form-textarea" name="address" placeholder="Branch address..."></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('branch-modal')"><?= __('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= __('save') ?></button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
