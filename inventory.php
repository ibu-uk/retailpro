<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$current_page = 'inventory';
$page_title   = __('inventory_management');

$db = db();

// Handle stock adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'adjust') {
    $pid    = (int)$_POST['product_id'];
    $bid    = (int)$_POST['branch_id'];
    $type   = $_POST['type'];
    $qty    = (int)$_POST['qty'];
    $ref    = trim($_POST['reference']);
    $notes  = trim($_POST['notes']);
    $uid    = current_user()['id'];

    $delta = in_array($type, ['in','return']) ? $qty : -$qty;
    $db->prepare("INSERT INTO stock_movements (product_id,branch_id,type,qty,reference,notes,user_id) VALUES (?,?,?,?,?,?,?)")->execute([$pid,$bid,$type,$delta,$ref,$notes,$uid]);
    $db->prepare("INSERT INTO stock (product_id,branch_id,qty) VALUES (?,?,?) ON DUPLICATE KEY UPDATE qty=qty+?")->execute([$pid,$bid,$delta,$delta]);
    header('Location: ' . BASE . '/inventory.php?success=' . urlencode('Stock updated'));
    exit;
}

// Stats
$total_stock  = $db->query("SELECT COALESCE(SUM(qty),0) as s FROM stock")->fetch()['s'];
$in_stock     = $db->query("SELECT COUNT(DISTINCT product_id) as c FROM stock WHERE qty > 10")->fetch()['c'];
$low_stock    = $db->query("SELECT COUNT(DISTINCT product_id) as c FROM stock WHERE qty > 0 AND qty <= 5")->fetch()['c'];
$out_stock    = $db->query("SELECT COUNT(DISTINCT product_id) as c FROM stock WHERE qty = 0")->fetch()['c'];
$damaged      = $db->query("SELECT COUNT(*) as c FROM stock_movements WHERE type='damage'")->fetch()['c'];

// Movements log with pagination
$mv_page = max(1, (int)($_GET['mp'] ?? 1));
$mv_per = 20;
$mv_offset = ($mv_page - 1) * $mv_per;
$total_movements = $db->query("SELECT COUNT(*) FROM stock_movements")->fetchColumn();
$total_mv_pages = ceil($total_movements / $mv_per);

$movements = $db->query("
    SELECT sm.*, p.name as product_name, b.name as branch_name, u.name as user_name
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    JOIN branches b ON b.id = sm.branch_id
    LEFT JOIN users u ON u.id = sm.user_id
    ORDER BY sm.created_at DESC LIMIT $mv_per OFFSET $mv_offset
")->fetchAll();

// Products and branches for form
$all_products = $db->query("SELECT id, name, sku FROM products WHERE is_active=1 ORDER BY name")->fetchAll();
$all_branches = $db->query("SELECT id, name FROM branches WHERE is_active=1")->fetchAll();

// Stock by product with pagination
$sp_page = max(1, (int)($_GET['sp'] ?? 1));
$sp_per = 20;
$sp_offset = ($sp_page - 1) * $sp_per;
$total_stock_items = $db->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn();
$total_sp_pages = ceil($total_stock_items / $sp_per);

$stock_table = $db->query("
    SELECT p.id, p.name, p.sku, c.emoji, c.name as cat,
           COALESCE(SUM(s.qty),0) as total_qty,
           MIN(s.qty) as min_branch_qty
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN stock s ON s.product_id = p.id
    WHERE p.is_active=1
    GROUP BY p.id ORDER BY total_qty ASC LIMIT $sp_per OFFSET $sp_offset
")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
  <button class="btn btn-primary" onclick="openModal('stock-modal')">📥 <?= __('stock_in') ?></button>
  <button class="btn btn-ghost" onclick="openModal('stock-modal')">📤 <?= __('stock_out') ?></button>
  <button class="btn btn-ghost" onclick="openModal('stock-modal')">🔄 <?= __('adjust_stock') ?></button>
  <div style="margin-left:auto;display:flex;gap:6px">
    <a href="<?= BASE ?>/api/export_inventory.php" class="btn btn-ghost btn-sm">📊 Excel</a>
    <button type="button" class="btn btn-ghost btn-sm" onclick="printInventory()">🖨️ Print</button>
  </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(5,1fr)">
  <div class="stat-card blue"><div class="stat-icon">📦</div><div class="stat-label"><?= __('total_stock') ?></div><div class="stat-value text-blue"><?= number_format($total_stock) ?></div><div class="stat-delta"><?= __('units_all_branches') ?></div></div>
  <div class="stat-card green"><div class="stat-icon">✅</div><div class="stat-label"><?= __('healthy_stock') ?></div><div class="stat-value text-green"><?= $in_stock ?></div><div class="stat-delta up">products &gt;10 units</div></div>
  <div class="stat-card amber"><div class="stat-icon">⚠️</div><div class="stat-label"><?= __('low_stock') ?></div><div class="stat-value text-amber"><?= $low_stock ?></div><div class="stat-delta down">products 1-5 units</div></div>
  <div class="stat-card red"><div class="stat-icon">🚫</div><div class="stat-label"><?= __('out_of_stock') ?></div><div class="stat-value text-red"><?= $out_stock ?></div><div class="stat-delta down">products at 0</div></div>
  <div class="stat-card purple"><div class="stat-icon">💔</div><div class="stat-label"><?= __('damage_events') ?></div><div class="stat-value text-accent"><?= $damaged ?></div><div class="stat-delta down">total logged</div></div>
</div>

<div class="grid-2 mb-16">
  <div class="card">
    <div class="card-title"><span>📊 <?= __('stock_levels') ?></span></div>
    <div class="tbl-wrap">
      <table id="stock-table">
        <thead><tr><th><?= __('product') ?></th><th><?= __('sku') ?></th><th><?= __('category') ?></th><th><?= __('stock') ?></th><th><?= __('status') ?></th></tr></thead>
        <tbody>
          <?php foreach ($stock_table as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s['emoji'] . ' ' . $s['name']) ?></td>
            <td class="font-mono" style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($s['sku']) ?></td>
            <td><span class="badge badge-gray"><?= htmlspecialchars($s['cat']) ?></span></td>
            <td style="font-weight:600;color:<?= $s['total_qty']<=0?'var(--red)':($s['total_qty']<=5?'var(--amber)':'var(--text2)') ?>"><?= $s['total_qty'] ?></td>
            <td>
              <?php if ($s['total_qty'] <= 0): ?><span class="badge badge-red"><?= __('out_of_stock') ?></span>
              <?php elseif ($s['total_qty'] <= 5): ?><span class="badge badge-amber"><?= __('low_stock') ?></span>
              <?php else: ?><span class="badge badge-green">OK</span><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;font-size:12px;color:var(--text3)">
      <span><?= __('showing') ?> <?= count($stock_table) ?> <?= __('of') ?> <?= $total_stock_items ?></span>
      <div class="pagination">
        <?php for ($i = 1; $i <= $total_sp_pages; $i++): ?>
        <a href="?sp=<?= $i ?>&mp=<?= $mv_page ?>" class="page-link <?= $i === $sp_page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-title"><span>🗄️ <?= __('recent_movements') ?></span></div>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th><?= __('date') ?></th><th><?= __('product') ?></th><th><?= __('type') ?></th><th><?= __('qty') ?></th><th><?= __('created_by') ?></th></tr></thead>
        <tbody>
          <?php foreach ($movements as $m):
            $type_badges = ['in'=>'badge-green','out'=>'badge-red','transfer'=>'badge-blue','damage'=>'badge-amber','return'=>'badge-purple','adjustment'=>'badge-gray'];
            $type_labels = ['in'=>'Stock IN','out'=>'Stock OUT','transfer'=>'Transfer','damage'=>'Damaged','return'=>'Returned','adjustment'=>'Adjusted'];
          ?>
          <tr>
            <td class="font-mono" style="font-size:11px;color:var(--text3)"><?= date('d M H:i', strtotime($m['created_at'])) ?></td>
            <td style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($m['product_name']) ?></td>
            <td><span class="badge <?= $type_badges[$m['type']] ?? 'badge-gray' ?>"><?= $type_labels[$m['type']] ?? $m['type'] ?></span></td>
            <td style="font-weight:600;color:<?= $m['qty'] > 0 ? 'var(--green)' : 'var(--red)' ?>"><?= $m['qty'] > 0 ? '+' : '' ?><?= $m['qty'] ?></td>
            <td style="font-size:12px;color:var(--text3)"><?= htmlspecialchars($m['user_name'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;font-size:12px;color:var(--text3)">
      <span><?= __('showing') ?> <?= count($movements) ?> <?= __('of') ?> <?= $total_movements ?></span>
      <div class="pagination">
        <?php for ($i = 1; $i <= $total_mv_pages; $i++): ?>
        <a href="?sp=<?= $sp_page ?>&mp=<?= $i ?>" class="page-link <?= $i === $mv_page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
    </div>
  </div>
</div>

<!-- STOCK ADJUSTMENT MODAL -->
<div class="modal-backdrop" id="stock-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title"><?= __('adjust_stock') ?></div>
      <button class="modal-close" onclick="closeModal('stock-modal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="adjust">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label"><?= __('product') ?> *</label>
          <select class="form-select" name="product_id" required>
            <option value=""><?= __('select') ?>...</option>
            <?php foreach ($all_products as $p): ?>
            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name'] . ' (' . $p['sku'] . ')') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label"><?= __('nav_branches') ?> *</label>
            <select class="form-select" name="branch_id" required>
              <?php foreach ($all_branches as $b): ?>
              <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label"><?= __('type') ?> *</label>
            <select class="form-select" name="type" required>
              <option value="in">📥 Stock IN</option>
              <option value="out">📤 Stock OUT</option>
              <option value="transfer">🔄 Transfer</option>
              <option value="damage">💔 Damage</option>
              <option value="return">↩️ Return</option>
              <option value="adjustment">⚙️ Adjustment</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('qty') ?> *</label><input class="form-input" name="qty" type="number" min="1" value="1" required></div>
          <div class="form-group"><label class="form-label"><?= __('reference') ?></label><input class="form-input" name="reference" placeholder="e.g. PO-2025-0041"></div>
        </div>
        <div class="form-group"><label class="form-label"><?= __('notes') ?></label><textarea class="form-textarea" name="notes"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('stock-modal')"><?= __('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= __('save') ?></button>
      </div>
    </form>
  </div>
</div>

<?php
$extra_js = '<script>
function printInventory() {
  const table = document.getElementById("stock-table");
  const win = window.open("","_blank");
  win.document.write("<html><head><title>Inventory</title><style>body{font-family:Arial,sans-serif;padding:20px}table{width:100%;border-collapse:collapse;font-size:12px}th,td{border:1px solid #ddd;padding:6px 8px;text-align:left}th{background:#f0f2f5;font-weight:600}.header{text-align:center;margin-bottom:20px}</style></head><body>");
  win.document.write("<div class=header><h2>RetailPro — Inventory Report</h2><p>Generated: " + new Date().toLocaleDateString() + "</p></div>");
  win.document.write(table.outerHTML);
  win.document.write("</body></html>");
  win.document.close();
  win.print();
}
</script>';
require __DIR__ . '/includes/footer.php'; ?>
