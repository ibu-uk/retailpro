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
    $qty    = abs((int)$_POST['qty']);
    $ref    = trim($_POST['reference']);
    $notes  = trim($_POST['notes']);
    $uid    = current_user()['id'];

    if ($qty < 1) {
        header('Location: ' . BASE . '/inventory.php?error=' . urlencode('Quantity must be at least 1'));
        exit;
    }

    if ($type === 'transfer') {
        $to_bid = (int)($_POST['to_branch_id'] ?? 0);
        if (!$to_bid || $to_bid === $bid) {
            header('Location: ' . BASE . '/inventory.php?error=' . urlencode('Transfer requires a different destination branch'));
            exit;
        }
        // Check source has enough stock
        $avail = $db->prepare("SELECT COALESCE(qty,0) FROM stock WHERE product_id=? AND branch_id=?");
        $avail->execute([$pid, $bid]);
        $avail_qty = (int)$avail->fetchColumn();
        if ($avail_qty < $qty) {
            header('Location: ' . BASE . '/inventory.php?error=' . urlencode("Not enough stock — only $avail_qty units available in source branch"));
            exit;
        }
        $note_out = "Transfer OUT to branch #$to_bid" . ($notes ? ": $notes" : '');
        $note_in  = "Transfer IN from branch #$bid" . ($notes ? ": $notes" : '');
        $db->prepare("INSERT INTO stock_movements (product_id,branch_id,type,qty,reference,notes,user_id) VALUES (?,?,'transfer',?,?,?,?)")->execute([$pid,$bid,-$qty,$ref,$note_out,$uid]);
        $db->prepare("INSERT INTO stock_movements (product_id,branch_id,type,qty,reference,notes,user_id) VALUES (?,?,'transfer',?,?,?,?)")->execute([$pid,$to_bid,$qty,$ref,$note_in,$uid]);
        $db->prepare("INSERT INTO stock (product_id,branch_id,qty) VALUES (?,?,?) ON DUPLICATE KEY UPDATE qty=qty+?")->execute([$pid,$bid,-$qty,-$qty]);
        $db->prepare("INSERT INTO stock (product_id,branch_id,qty) VALUES (?,?,?) ON DUPLICATE KEY UPDATE qty=qty+?")->execute([$pid,$to_bid,$qty,$qty]);
        header('Location: ' . BASE . '/inventory.php?success=' . urlencode("Transfer of $qty units completed"));
        exit;
    }

    $delta = in_array($type, ['in','return','adjustment']) ? $qty : -$qty;

    // Prevent stock going negative for out/damage
    if ($delta < 0) {
        $avail = $db->prepare("SELECT COALESCE(qty,0) FROM stock WHERE product_id=? AND branch_id=?");
        $avail->execute([$pid, $bid]);
        $avail_qty = (int)$avail->fetchColumn();
        if ($avail_qty + $delta < 0) {
            header('Location: ' . BASE . '/inventory.php?error=' . urlencode("Cannot remove $qty units — only $avail_qty available"));
            exit;
        }
    }

    $db->prepare("INSERT INTO stock_movements (product_id,branch_id,type,qty,reference,notes,user_id) VALUES (?,?,?,?,?,?,?)")->execute([$pid,$bid,$type,$delta,$ref,$notes,$uid]);
    $db->prepare("INSERT INTO stock (product_id,branch_id,qty) VALUES (?,?,?) ON DUPLICATE KEY UPDATE qty=qty+?")->execute([$pid,$bid,$delta,$delta]);
    header('Location: ' . BASE . '/inventory.php?success=' . urlencode('Stock updated successfully'));
    exit;
}

// Branch filter from GET param (set when clicking Stock button from branches page)
$filter_branch_id = (int)($_GET['branch_id'] ?? 0);
$branch_name_filter = '';
if ($filter_branch_id) {
    $bn = $db->prepare("SELECT name FROM branches WHERE id=?");
    $bn->execute([$filter_branch_id]);
    $branch_name_filter = $bn->fetchColumn() ?: '';
}
$branch_param = $filter_branch_id ? [$filter_branch_id] : [];
$stock_join_branch = $filter_branch_id ? "AND s.branch_id = ?" : "";
$mv_and_branch     = $filter_branch_id ? "AND sm.branch_id = ?" : "";
$stock_where_total = $filter_branch_id ? "WHERE branch_id = ?" : "";
$stock_where_stats = $filter_branch_id ? "AND branch_id = ?" : "";

// Stats (scoped to branch if filtered) — use plain column refs, no table alias
$stmt = $db->prepare("SELECT COALESCE(SUM(qty),0) as s FROM stock $stock_where_total");
$stmt->execute($branch_param);
$total_stock = $stmt->fetch()['s'];

$stmt = $db->prepare("SELECT COUNT(DISTINCT product_id) as c FROM stock WHERE qty > 10 $stock_where_stats");
$stmt->execute($branch_param);
$in_stock = $stmt->fetch()['c'];

$stmt = $db->prepare("SELECT COUNT(DISTINCT product_id) as c FROM stock WHERE qty > 0 AND qty <= 5 $stock_where_stats");
$stmt->execute($branch_param);
$low_stock = $stmt->fetch()['c'];

$stmt = $db->prepare("SELECT COUNT(DISTINCT product_id) as c FROM stock WHERE qty = 0 $stock_where_stats");
$stmt->execute($branch_param);
$out_stock = $stmt->fetch()['c'];

$stmt = $db->prepare("SELECT COUNT(*) as c FROM stock_movements WHERE type='damage' $stock_where_stats");
$stmt->execute($branch_param);
$damaged = $stmt->fetch()['c'];

// Movements filters
$mv_date_from = trim($_GET['mv_from'] ?? '');
$mv_date_to   = trim($_GET['mv_to']   ?? '');
$mv_product   = (int)($_GET['mv_pid']  ?? 0);
$mv_type      = trim($_GET['mv_type']  ?? '');

$mv_conditions = [];
$mv_params     = [];
if ($filter_branch_id)  { $mv_conditions[] = 'sm.branch_id = ?';  $mv_params[] = $filter_branch_id; }
if ($mv_date_from)      { $mv_conditions[] = 'DATE(sm.created_at) >= ?'; $mv_params[] = $mv_date_from; }
if ($mv_date_to)        { $mv_conditions[] = 'DATE(sm.created_at) <= ?'; $mv_params[] = $mv_date_to; }
if ($mv_product)        { $mv_conditions[] = 'sm.product_id = ?';  $mv_params[] = $mv_product; }
if ($mv_type)           { $mv_conditions[] = 'sm.type = ?';         $mv_params[] = $mv_type; }
$mv_where_sql = $mv_conditions ? 'WHERE ' . implode(' AND ', $mv_conditions) : '';

// Movements log with pagination
$mv_page = max(1, (int)($_GET['mp'] ?? 1));
$mv_per  = 20;
$mv_offset = ($mv_page - 1) * $mv_per;
$count_stmt = $db->prepare("SELECT COUNT(*) FROM stock_movements sm $mv_where_sql");
$count_stmt->execute($mv_params);
$total_movements = $count_stmt->fetchColumn();
$total_mv_pages = ceil($total_movements / $mv_per);

$mv_exec_params = $mv_params;
$mv_exec_params[] = $mv_per;
$mv_exec_params[] = $mv_offset;
$mv_stmt = $db->prepare("
    SELECT sm.*, p.name as product_name, b.name as branch_name, u.name as user_name
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    JOIN branches b ON b.id = sm.branch_id
    LEFT JOIN users u ON u.id = sm.user_id
    $mv_where_sql
    ORDER BY sm.created_at DESC LIMIT ? OFFSET ?
");
$mv_stmt->execute($mv_exec_params);
$movements = $mv_stmt->fetchAll();

// Branches and categories for forms
$all_branches   = $db->query("SELECT id, name FROM branches WHERE is_active=1")->fetchAll();
$all_categories = $db->query("SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY name")->fetchAll();

// Stock Levels filters
$sp_search = trim($_GET['sp_q']      ?? '');
$sp_cat    = (int)($_GET['sp_cat']   ?? 0);
$sp_status = trim($_GET['sp_status'] ?? '');

$sp_conditions = ['p.is_active=1'];
$sp_params     = [];
if ($filter_branch_id) { /* handled via $bwhere_stock join */ }
if ($sp_search) { $sp_conditions[] = '(p.name LIKE ? OR p.sku LIKE ?)'; $sp_params[] = "%$sp_search%"; $sp_params[] = "%$sp_search%"; }
if ($sp_cat)    { $sp_conditions[] = 'p.category_id = ?'; $sp_params[] = $sp_cat; }
$sp_where = 'WHERE ' . implode(' AND ', $sp_conditions);

// Stock by product with pagination
$sp_page   = max(1, (int)($_GET['sp'] ?? 1));
$sp_per    = 50;
$sp_offset = ($sp_page - 1) * $sp_per;

$cnt_params = $sp_params;
if ($filter_branch_id) {
    $cnt_params = array_merge([$filter_branch_id], $cnt_params);
}

$cnt_stmt = $db->prepare("SELECT COUNT(DISTINCT p.id) FROM products p LEFT JOIN stock s ON s.product_id = p.id $stock_join_branch $sp_where");
$cnt_stmt->execute($cnt_params);
$total_stock_items = $cnt_stmt->fetchColumn();
$total_sp_pages = max(1, ceil($total_stock_items / $sp_per));

// Status filter applied via HAVING after joins
$having_sql = '';
if ($sp_status === 'out') $having_sql = 'HAVING total_qty <= 0';
elseif ($sp_status === 'low') $having_sql = 'HAVING total_qty > 0 AND total_qty <= 5';
elseif ($sp_status === 'ok')  $having_sql = 'HAVING total_qty > 5';

$stock_derive_where = $filter_branch_id ? "AND branch_id = ?" : "";
$mv_derive_where    = $filter_branch_id ? "AND branch_id = ?" : "";

$st_params = $sp_params;
if ($filter_branch_id) {
    $st_params = array_merge([$filter_branch_id, $filter_branch_id], $st_params);
}
$st_params[] = $sp_per;
$st_params[] = $sp_offset;

$st_stmt = $db->prepare("
    SELECT p.id, p.name, p.sku, COALESCE(c.emoji,'📦') as emoji, COALESCE(c.name,'—') as cat,
           COALESCE(s.total_qty,0) as total_qty,
           COALESCE(sm.total_received,0) as total_received,
           COALESCE(sm.total_sold,0) as total_sold
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN (
        SELECT product_id, SUM(qty) as total_qty
        FROM stock
        WHERE 1=1 $stock_derive_where
        GROUP BY product_id
    ) s ON s.product_id = p.id
    LEFT JOIN (
        SELECT product_id,
               SUM(CASE WHEN qty > 0 THEN qty ELSE 0 END) as total_received,
               SUM(CASE WHEN type='out' THEN ABS(qty) ELSE 0 END) as total_sold
        FROM stock_movements
        WHERE 1=1 $mv_derive_where
        GROUP BY product_id
    ) sm ON sm.product_id = p.id
    $sp_where
    $having_sql
    ORDER BY total_qty ASC
    LIMIT ? OFFSET ?
");
$st_stmt->execute($st_params);
$stock_table = $st_stmt->fetchAll();

// Smart pagination helper
function smart_pages(int $current, int $total, array $extra = []): string {
    if ($total <= 1) return '';
    $qs = $extra ? '&' . http_build_query($extra) : '';
    $out = '<div class="pagination" style="gap:3px">';
    // Prev
    if ($current > 1) $out .= "<a href='?" . ($extra ? http_build_query(array_merge($extra, ['sp' => $current-1])) : "sp=" . ($current-1)) . "' class='page-link'>&laquo;</a>";
    // Pages window
    $window = 3;
    $start  = max(1, $current - $window);
    $end    = min($total, $current + $window);
    if ($start > 1) { $out .= "<a href='?" . ($extra ? http_build_query(array_merge($extra, ['sp'=>1])) : 'sp=1') . "' class='page-link'>1</a>"; if ($start > 2) $out .= "<span style='padding:0 4px;color:var(--text3)'>…</span>"; }
    for ($i = $start; $i <= $end; $i++) {
        $href = $extra ? http_build_query(array_merge($extra, ['sp'=>$i])) : "sp=$i";
        $out .= "<a href='?$href' class='page-link" . ($i===$current?' active':'') . "'>$i</a>";
    }
    if ($end < $total) { if ($end < $total-1) $out .= "<span style='padding:0 4px;color:var(--text3)'>…</span>"; $out .= "<a href='?" . ($extra ? http_build_query(array_merge($extra, ['sp'=>$total])) : "sp=$total") . "' class='page-link'>$total</a>"; }
    // Next
    if ($current < $total) $out .= "<a href='?" . ($extra ? http_build_query(array_merge($extra, ['sp'=>$current+1])) : "sp=" . ($current+1)) . "' class='page-link'>&raquo;</a>";
    $out .= '</div>';
    return $out;
}

require __DIR__ . '/includes/header.php';
?>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success" style="margin-bottom:12px">✅ <?= htmlspecialchars($_GET['success']) ?></div>
<?php elseif (isset($_GET['error'])): ?>
<div class="alert alert-error" style="margin-bottom:12px">❌ <?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<?php if ($filter_branch_id && $branch_name_filter): ?>
<div class="alert" style="background:rgba(67,97,238,.08);border:1px solid rgba(67,97,238,.2);color:var(--accent2);margin-bottom:12px;display:flex;align-items:center;justify-content:space-between">
  <span>🏪 Showing stock for: <strong><?= htmlspecialchars($branch_name_filter) ?></strong></span>
  <a href="<?= BASE ?>/inventory.php" class="btn btn-ghost btn-sm">✕ Show All Branches</a>
</div>
<?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
  <button class="btn btn-primary" onclick="openStockModal('in')">📥 <?= __('stock_in') ?></button>
  <button class="btn btn-ghost" onclick="openStockModal('out')">📤 <?= __('stock_out') ?></button>
  <button class="btn btn-ghost" onclick="openStockModal('adjustment')">🔄 <?= __('adjust_stock') ?></button>
  <!-- Branch quick-filter -->
  <form method="GET" style="display:flex;align-items:center;gap:6px;margin-left:8px">
    <select class="search-input" name="branch_id" style="width:160px;font-size:12px" onchange="this.form.submit()">
      <option value="0" <?= !$filter_branch_id ? 'selected' : '' ?>>🏪 All Branches</option>
      <?php foreach ($all_branches as $ab): ?>
      <option value="<?= $ab['id'] ?>" <?= $filter_branch_id == $ab['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ab['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <div style="margin-left:auto;display:flex;gap:6px">
    <a href="<?= BASE ?>/api/export_inventory.php<?= $filter_branch_id ? '?branch_id='.$filter_branch_id : '' ?>" class="btn btn-ghost btn-sm">📊 Excel</a>
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
    <!-- Stock Levels Filter Bar -->
    <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:12px;padding:10px;background:var(--bg2);border-radius:var(--r)">
      <?php if ($filter_branch_id): ?><input type="hidden" name="branch_id" value="<?= $filter_branch_id ?>"><?php endif; ?>
      <input type="hidden" name="mp" value="<?= $mv_page ?>">
      <div style="display:flex;flex-direction:column;gap:3px;flex:1;min-width:140px">
        <label style="font-size:11px;color:var(--text3)">Search Product / SKU</label>
        <input type="text" class="form-input" name="sp_q" value="<?= htmlspecialchars($sp_search) ?>" placeholder="Name or SKU..." style="font-size:12px;padding:5px 8px">
      </div>
      <div style="display:flex;flex-direction:column;gap:3px">
        <label style="font-size:11px;color:var(--text3)">Category</label>
        <select class="form-select" name="sp_cat" style="width:150px;font-size:12px;padding:5px 8px">
          <option value="">All Categories</option>
          <?php foreach ($all_categories as $cat): ?>
          <option value="<?= $cat['id'] ?>" <?= $sp_cat==$cat['id']?'selected':'' ?>><?= htmlspecialchars($cat['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;flex-direction:column;gap:3px">
        <label style="font-size:11px;color:var(--text3)">Status</label>
        <select class="form-select" name="sp_status" style="width:120px;font-size:12px;padding:5px 8px">
          <option value="">All Status</option>
          <option value="ok"  <?= $sp_status==='ok'?'selected':'' ?>>OK (&gt;5)</option>
          <option value="low" <?= $sp_status==='low'?'selected':'' ?>>Low (1-5)</option>
          <option value="out" <?= $sp_status==='out'?'selected':'' ?>>Out of Stock</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end">🔍 Filter</button>
      <?php if ($sp_search || $sp_cat || $sp_status): ?>
      <a href="?<?= $filter_branch_id ? 'branch_id='.$filter_branch_id.'&' : '' ?>mp=<?= $mv_page ?>" class="btn btn-ghost btn-sm" style="align-self:flex-end">↺ Clear</a>
      <?php endif; ?>
    </form>
    <div class="tbl-wrap">
      <table id="stock-table">
        <thead><tr><th><?= __('product') ?></th><th><?= __('sku') ?></th><th><?= __('category') ?></th><th>Total Received</th><th>Total Sold</th><th><?= __('stock') ?></th><th><?= __('status') ?></th></tr></thead>
        <tbody>
          <?php foreach ($stock_table as $s): ?>
          <tr>
            <td><?= htmlspecialchars($s['emoji'] . ' ' . $s['name']) ?></td>
            <td class="font-mono" style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($s['sku']) ?></td>
            <td><span class="badge badge-gray"><?= htmlspecialchars($s['cat']) ?></span></td>
            <td style="color:var(--blue2);font-weight:500"><?= (int)$s['total_received'] ?></td>
            <td style="color:var(--red);font-weight:500"><?= (int)$s['total_sold'] ?></td>
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
      <span><?= __('showing') ?> <?= count($stock_table) ?> <?= __('of') ?> <?= $total_stock_items ?> (page <?= $sp_page ?> of <?= $total_sp_pages ?>)</span>
      <?= smart_pages($sp_page, $total_sp_pages, array_filter(['mp'=>$mv_page,'branch_id'=>$filter_branch_id?:null,'sp_q'=>$sp_search?:null,'sp_cat'=>$sp_cat?:null,'sp_status'=>$sp_status?:null])) ?>
    </div>
  </div>

  <div class="card">
    <div class="card-title">
      <span>🗄️ <?= __('recent_movements') ?></span>
      <div style="display:flex;gap:6px">
        <?php
          $mv_export_params = array_filter([
            'branch_id' => $filter_branch_id ?: null,
            'date_from' => $mv_date_from ?: null,
            'date_to'   => $mv_date_to   ?: null,
            'product_id'=> $mv_product   ?: null,
            'type'      => $mv_type      ?: null,
          ]);
          $mv_export_qs = $mv_export_params ? '?' . http_build_query($mv_export_params) : '';
        ?>
        <a href="<?= BASE ?>/api/export_movements.php<?= $mv_export_qs ?>" class="btn btn-ghost btn-sm">📊 Excel</a>
        <button type="button" class="btn btn-ghost btn-sm" onclick="printMovements()">🖨️ Print</button>
      </div>
    </div>
    <!-- Movements Filter Bar -->
    <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:12px;padding:10px;background:var(--bg2);border-radius:var(--r)">
      <?php if ($filter_branch_id): ?><input type="hidden" name="branch_id" value="<?= $filter_branch_id ?>"><?php endif; ?>
      <input type="hidden" name="sp" value="<?= $sp_page ?>">
      <div style="display:flex;flex-direction:column;gap:3px">
        <label style="font-size:11px;color:var(--text3)">From Date</label>
        <input type="date" class="form-input" name="mv_from" value="<?= htmlspecialchars($mv_date_from) ?>" style="width:140px;font-size:12px;padding:5px 8px">
      </div>
      <div style="display:flex;flex-direction:column;gap:3px">
        <label style="font-size:11px;color:var(--text3)">To Date</label>
        <input type="date" class="form-input" name="mv_to" value="<?= htmlspecialchars($mv_date_to) ?>" style="width:140px;font-size:12px;padding:5px 8px">
      </div>
      <div style="display:flex;flex-direction:column;gap:3px">
        <label style="font-size:11px;color:var(--text3)">Product</label>
        <select class="form-select" name="mv_pid" style="width:160px;font-size:12px;padding:5px 8px">
          <option value="">All Products</option>
          <?php foreach ($all_products as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $mv_product == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;flex-direction:column;gap:3px">
        <label style="font-size:11px;color:var(--text3)">Type</label>
        <select class="form-select" name="mv_type" style="width:130px;font-size:12px;padding:5px 8px">
          <option value="">All Types</option>
          <option value="in"         <?= $mv_type==='in'?'selected':'' ?>>Stock IN</option>
          <option value="out"        <?= $mv_type==='out'?'selected':'' ?>>Stock OUT</option>
          <option value="transfer"   <?= $mv_type==='transfer'?'selected':'' ?>>Transfer</option>
          <option value="damage"     <?= $mv_type==='damage'?'selected':'' ?>>Damaged</option>
          <option value="return"     <?= $mv_type==='return'?'selected':'' ?>>Returned</option>
          <option value="adjustment" <?= $mv_type==='adjustment'?'selected':'' ?>>Adjustment</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end">🔍 Filter</button>
      <?php if ($mv_date_from || $mv_date_to || $mv_product || $mv_type): ?>
      <a href="?<?= $filter_branch_id ? 'branch_id='.$filter_branch_id.'&' : '' ?>sp=<?= $sp_page ?>" class="btn btn-ghost btn-sm" style="align-self:flex-end">↺ Clear</a>
      <?php endif; ?>
    </form>
    <div class="tbl-wrap">
      <table id="movements-table">
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
      <span><?= __('showing') ?> <?= count($movements) ?> <?= __('of') ?> <?= $total_movements ?> (page <?= $mv_page ?> of <?= $total_mv_pages ?>)</span>
      <?= smart_pages($mv_page, $total_mv_pages, array_filter(['sp'=>$sp_page,'branch_id'=>$filter_branch_id?:null,'mv_from'=>$mv_date_from?:null,'mv_to'=>$mv_date_to?:null,'mv_pid'=>$mv_product?:null,'mv_type'=>$mv_type?:null]), ) ?>
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
        <div class="form-group" style="position:relative">
          <label class="form-label"><?= __('product') ?> *</label>
          <input type="hidden" name="product_id" id="inv-prod-id" required>
          <input class="form-input" type="text" id="inv-prod-search" placeholder="<?= __('search_products') ?>..." oninput="invSearchProduct(this.value)" autocomplete="off" required>
          <div id="inv-prod-results" class="cust-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;background:var(--bg2);border:1px solid var(--border2);border-radius:0 0 var(--r) var(--r);max-height:220px;overflow-y:auto;z-index:30;box-shadow:var(--shadow-md)"></div>
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
            <select class="form-select" name="type" id="mov-type" required onchange="onTypeChange()">
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
        <div id="transfer-dest" style="display:none" class="form-group">
          <label class="form-label">Destination Branch *</label>
          <select class="form-select" name="to_branch_id" id="to-branch-sel">
            <option value="">Select destination...</option>
            <?php foreach ($all_branches as $b): ?>
            <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
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

<?php ob_start(); ?>
<script>

function onTypeChange() {
  var t = document.getElementById("mov-type").value;
  var dest = document.getElementById("transfer-dest");
  var toBranch = document.getElementById("to-branch-sel");
  if (t === "transfer") {
    dest.style.display = "block";
    toBranch.required = true;
  } else {
    dest.style.display = "none";
    toBranch.required = false;
    toBranch.value = "";
  }
}
function openStockModal(type) {
  var sel = document.getElementById("mov-type");
  if (sel) { sel.value = type || "in"; onTypeChange(); }
  var bid = <?= $filter_branch_id ?: 0 ?>;
  if (bid) {
    var bsel = document.querySelector("[name=branch_id]");
    if (bsel) bsel.value = bid;
  }
  var ps = document.getElementById("inv-prod-search");
  var pid = document.getElementById("inv-prod-id");
  if (ps) { ps.value = ""; }
  if (pid) { pid.value = ""; }
  var dd = document.getElementById("inv-prod-results");
  if (dd) { dd.style.display = "none"; }
  openModal("stock-modal");
}

let invSearchTimeout = null;
function invSearchProduct(q) {
  var dd = document.getElementById("inv-prod-results");
  if (!q) { dd.style.display = "none"; return; }
  if (invSearchTimeout) clearTimeout(invSearchTimeout);
  invSearchTimeout = setTimeout(function() {
    var url = "<?= BASE ?>/api/search_products_simple.php?q=" + encodeURIComponent(q) + "&limit=10";
    fetch(url)
      .then(function(r){ return r.json(); })
      .then(function(data) {
        var list = data.products || [];
        if (!list.length) {
          dd.innerHTML = '<div style="padding:10px;text-align:center;font-size:12px;color:var(--text3)"><?= __('no_data') ?></div>';
        } else {
          dd.innerHTML = list.map(function(p) {
            return '<div style="padding:8px 10px;font-size:12px;cursor:pointer;display:flex;align-items:center;gap:8px" onmouseover="this.style.background=\'var(--bg3)\'" onmouseout="this.style.background=\'transparent\'" onclick="invSelectProduct(' + p.id + ', \'' + p.name.replace(/\\'/g, "\\'") + ' (' + p.sku + ')\')">'
              + (p.emoji || '📦') + ' ' + p.name + ' <span style="font-size:10px;color:var(--text3)">' + p.sku + '</span></div>';
          }).join('');
        }
        dd.style.display = "block";
      })
      .catch(function() { dd.style.display = "none"; });
  }, 250);
}

function invSelectProduct(id, label) {
  document.getElementById("inv-prod-id").value = id;
  document.getElementById("inv-prod-search").value = label;
  document.getElementById("inv-prod-results").style.display = "none";
}

const COMPANY_NAME = "<?= htmlspecialchars(get_setting('company_name', APP_NAME)) ?>";
function printMovements() {
  const table = document.getElementById("movements-table");
  const win = window.open("","_blank");
  win.document.write("<html><head><title>Stock Movements</title><style>body{font-family:Arial,sans-serif;padding:20px}table{width:100%;border-collapse:collapse;font-size:12px}th,td{border:1px solid #ddd;padding:6px 8px;text-align:left}th{background:#f0f2f5;font-weight:600}.header{text-align:center;margin-bottom:20px}</style></head><body>");
  win.document.write("<div class=header><h2>" + COMPANY_NAME + " — Stock Movements Report</h2><p>Generated: " + new Date().toLocaleDateString() + "</p></div>");
  win.document.write(table.outerHTML);
  win.document.write("</body></html>");
  win.document.close();
  win.print();
}
function printInventory() {
  const table = document.getElementById("stock-table");
  const win = window.open("","_blank");
  win.document.write("<html><head><title>Inventory</title><style>body{font-family:Arial,sans-serif;padding:20px}table{width:100%;border-collapse:collapse;font-size:12px}th,td{border:1px solid #ddd;padding:6px 8px;text-align:left}th{background:#f0f2f5;font-weight:600}.header{text-align:center;margin-bottom:20px}td:nth-child(4){color:#2563eb}td:nth-child(5){color:#dc2626}</style></head><body>");
  win.document.write("<div class=header><h2>" + COMPANY_NAME + " — Inventory Report</h2><p>Generated: " + new Date().toLocaleDateString() + "</p></div>");
  win.document.write(table.outerHTML);
  win.document.write("</body></html>");
  win.document.close();
  win.print();
}
</script>
<?php
$extra_js = ob_get_clean();
require __DIR__ . '/includes/footer.php'; ?>