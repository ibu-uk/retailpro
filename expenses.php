<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$current_page = 'expenses';
$page_title   = __('expense_tracker');
$db = db();
$currency = get_setting('currency', 'KWD');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {
    $db->prepare("INSERT INTO expenses (category,description,amount,branch_id,payment_mode,receipt_ref,created_by) VALUES (?,?,?,?,?,?,?)")->execute([
        trim($_POST['category']), trim($_POST['description']), (float)$_POST['amount'],
        $_POST['branch_id'] ?: null, $_POST['payment_mode'], trim($_POST['receipt_ref']), current_user()['id']
    ]);
    header('Location: ' . BASE . '/expenses.php?success=' . urlencode('Expense recorded'));
    exit;
}

$month_total = $db->query("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE MONTH(created_at)=MONTH(NOW())")->fetchColumn();
$today_total = $db->query("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE DATE(created_at)=CURDATE()")->fetchColumn();

$cat_filter = $_GET['cat'] ?? '';
$where = $cat_filter ? "WHERE e.category = ?" : "";
$params = $cat_filter ? [$cat_filter] : [];

$expenses = $db->prepare("
    SELECT e.*, b.name as branch_name, u.name as user_name
    FROM expenses e
    LEFT JOIN branches b ON b.id = e.branch_id
    LEFT JOIN users u ON u.id = e.created_by
    $where ORDER BY e.created_at DESC LIMIT 50
");
$expenses->execute($params);
$expenses = $expenses->fetchAll();

$cats = $db->query("SELECT DISTINCT category FROM expenses ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$branches = $db->query("SELECT id, name FROM branches WHERE is_active=1")->fetchAll();

// Category summary
$cat_summary = $db->query("
    SELECT category, SUM(amount) as total, COUNT(*) as cnt
    FROM expenses WHERE MONTH(created_at)=MONTH(NOW())
    GROUP BY category ORDER BY total DESC
")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
  <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:0;flex:1">
    <div class="stat-card red"><div class="stat-label"><?= __('this_month') ?></div><div class="stat-value text-red"><?= fmt_money($month_total) ?></div></div>
    <div class="stat-card amber"><div class="stat-label"><?= __('today') ?></div><div class="stat-value text-amber"><?= fmt_money($today_total) ?></div></div>
    <div class="stat-card blue"><div class="stat-label"><?= __('nav_categories') ?></div><div class="stat-value text-blue"><?= count($cat_summary) ?></div></div>
  </div>
</div>

<div class="inv-filters">
  <a href="<?= BASE ?>/expenses.php" class="filter-chip <?= !$cat_filter?'active':'' ?>"><?= __('all') ?></a>
  <?php foreach ($cats as $cat): ?>
  <a href="<?= BASE ?>/expenses.php?cat=<?= urlencode($cat) ?>" class="filter-chip <?= $cat_filter===$cat?'active':'' ?>"><?= htmlspecialchars($cat) ?></a>
  <?php endforeach; ?>
  <div style="margin-left:auto"><button class="btn btn-primary" onclick="openModal('expense-modal')">+ <?= __('add') ?></button></div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-title"><span>💸 <?= __('nav_expenses') ?></span></div>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th><?= __('date') ?></th><th><?= __('category') ?></th><th><?= __('description') ?></th><th><?= __('amount') ?></th><th><?= __('nav_branches') ?></th><th><?= __('payment_mode') ?></th></tr></thead>
        <tbody>
          <?php foreach ($expenses as $e): ?>
          <tr>
            <td class="font-mono" style="font-size:11px;color:var(--text3)"><?= date('d M Y', strtotime($e['created_at'])) ?></td>
            <td><span class="badge badge-gray"><?= htmlspecialchars($e['category']) ?></span></td>
            <td><?= htmlspecialchars($e['description']) ?></td>
            <td class="text-red" style="font-weight:600"><?= fmt_money($e['amount']) ?></td>
            <td style="font-size:12px;color:var(--text3)"><?= htmlspecialchars($e['branch_name'] ?? 'All') ?></td>
            <td><?= strtoupper($e['payment_mode']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($expenses)): ?><tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text3)"><?= __('no_data') ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-title"><span>📊 <?= __('this_month') ?></span></div>
    <?php foreach ($cat_summary as $cs): ?>
    <div style="margin-bottom:12px">
      <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
        <span><?= htmlspecialchars($cs['category']) ?> <span style="color:var(--text3)">(<?= $cs['cnt'] ?>)</span></span>
        <span class="text-red" style="font-weight:600"><?= fmt_money($cs['total']) ?></span>
      </div>
      <div class="progress">
        <div class="progress-fill" style="width:<?= $month_total > 0 ? round($cs['total']/$month_total*100) : 0 ?>%;background:var(--red)"></div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($cat_summary)): ?><div style="text-align:center;color:var(--text3);padding:20px"><?= __('no_data') ?></div><?php endif; ?>
  </div>
</div>

<!-- ADD EXPENSE MODAL -->
<div class="modal-backdrop" id="expense-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title"><?= __('add') ?> <?= __('nav_expenses') ?></div>
      <button class="modal-close" onclick="closeModal('expense-modal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('category') ?> *</label>
            <input class="form-input" name="category" required list="cat-list" placeholder="Rent, Utilities, Salary...">
            <datalist id="cat-list">
              <?php foreach ($cats as $cat): ?><option value="<?= htmlspecialchars($cat) ?>"><?php endforeach; ?>
              <option value="Rent"><option value="Utilities"><option value="Salary"><option value="Marketing"><option value="Maintenance"><option value="Transport">
            </datalist>
          </div>
          <div class="form-group"><label class="form-label"><?= __('amount') ?> (<?= $currency ?>) *</label><input class="form-input" name="amount" type="number" step="0.001" min="0.001" required></div>
        </div>
        <div class="form-group"><label class="form-label"><?= __('description') ?></label><textarea class="form-textarea" name="description"></textarea></div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label"><?= __('nav_branches') ?></label>
            <select class="form-select" name="branch_id">
              <option value=""><?= __('all') ?></option>
              <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label"><?= __('payment_mode') ?></label>
            <select class="form-select" name="payment_mode">
              <option value="cash"><?= __('cash') ?></option><option value="knet"><?= __('knet') ?></option><option value="transfer"><?= __('transfer') ?></option>
            </select>
          </div>
        </div>
        <div class="form-group"><label class="form-label"><?= __('receipt_ref') ?></label><input class="form-input" name="receipt_ref" placeholder="Receipt number..."></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('expense-modal')"><?= __('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= __('save') ?></button>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
