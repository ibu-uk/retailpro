<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$current_page = 'batches';
$page_title   = 'Batch & Supplier Tracking';
$db = db();
$user = current_user();
$branch_id = (int)($user['branch_id'] ?? 0);
$is_super  = ($user['role'] === 'super_admin' && !$user['branch_id']);
$bwhere    = $is_super ? "" : "AND sb.branch_id = $branch_id";
$bwhere2   = $is_super ? "" : "AND branch_id = $branch_id";

// ── ADD BATCH (Receive Stock from Supplier) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'receive') {
    $pid     = (int)$_POST['product_id'];
    $sid     = (int)$_POST['supplier_id'];
    $bid     = (int)$_POST['branch_id'];
    $qty     = (int)$_POST['qty'];
    $cost    = (float)$_POST['cost_price'];
    $expiry  = !empty($_POST['expiry_date'])   ? $_POST['expiry_date']   : null;
    $mfg     = !empty($_POST['mfg_date'])      ? $_POST['mfg_date']      : null;
    $lot     = trim($_POST['lot_number']       ?? '');
    $po_id   = !empty($_POST['po_id'])         ? (int)$_POST['po_id']    : null;
    $notes   = trim($_POST['notes']            ?? '');

    // Generate batch number: BTCH-YYYYMMDD-XXXXX
    $batch_num = 'BTCH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

    $db->beginTransaction();
    try {
        // Create batch
        $db->prepare("
            INSERT INTO stock_batches
              (batch_number,product_id,supplier_id,branch_id,po_id,qty_received,qty_remaining,
               cost_price,expiry_date,manufacture_date,lot_number,received_date,received_by,notes)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,CURDATE(),?,?)
        ")->execute([$batch_num,$pid,$sid,$bid,$po_id,$qty,$qty,$cost,$expiry,$mfg,$lot,$user['id'],$notes]);
        $batch_id = $db->lastInsertId();

        // Add to stock
        $db->prepare("INSERT INTO stock (product_id,branch_id,qty) VALUES (?,?,?)
                      ON DUPLICATE KEY UPDATE qty=qty+?")
           ->execute([$pid,$bid,$qty,$qty]);

        // Log movement
        $db->prepare("INSERT INTO stock_movements
                       (product_id,branch_id,type,qty,reference,notes,user_id,batch_id,supplier_id,expiry_date)
                       VALUES (?,?,'in',?,?,?,?,?,?,?)")
           ->execute([$pid,$bid,$qty,$batch_num,"Received from supplier — Batch $batch_num",$user['id'],$batch_id,$sid,$expiry]);

        // Update product last supplier info
        $db->prepare("UPDATE products SET last_supplier_id=?,last_purchase_price=?,last_purchase_date=CURDATE() WHERE id=?")
           ->execute([$sid,$cost,$pid]);

        // Upsert product_suppliers price
        $db->prepare("INSERT INTO product_suppliers (product_id,supplier_id,cost_price)
                      VALUES (?,?,?) ON DUPLICATE KEY UPDATE cost_price=?, updated_at=NOW()")
           ->execute([$pid,$sid,$cost,$cost]);

        $db->commit();
        header('Location: ' . BASE . '/batches.php?success=' . urlencode("Batch $batch_num received — $qty units added to stock"));
    } catch (Exception $e) {
        $db->rollBack();
        header('Location: ' . BASE . '/batches.php?error=' . urlencode($e->getMessage()));
    }
    exit;
}

// ── SAVE SUPPLIER PRICES ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'save_supplier_price') {
    $pid  = (int)$_POST['product_id'];
    $sid  = (int)$_POST['supplier_id'];
    $cost = (float)$_POST['cost_price'];
    $pref = isset($_POST['is_preferred']) ? 1 : 0;
    $moq  = (int)$_POST['min_order_qty'];
    $lead = (int)$_POST['lead_days'];
    $notes= trim($_POST['notes'] ?? '');

    if ($pref) {
        // Clear other preferred flags for this product
        $db->prepare("UPDATE product_suppliers SET is_preferred=0 WHERE product_id=?")->execute([$pid]);
    }
    $db->prepare("INSERT INTO product_suppliers (product_id,supplier_id,cost_price,is_preferred,min_order_qty,lead_days,notes)
                  VALUES (?,?,?,?,?,?,?)
                  ON DUPLICATE KEY UPDATE cost_price=?,is_preferred=?,min_order_qty=?,lead_days=?,notes=?")
       ->execute([$pid,$sid,$cost,$pref,$moq,$lead,$notes,$cost,$pref,$moq,$lead,$notes]);
    header('Location: ' . BASE . '/batches.php?tab=prices&success=' . urlencode('Supplier price saved')); exit;
}

// ── UPDATE EXPIRY ALERT DAYS ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'set_expiry') {
    $db->prepare("UPDATE products SET has_expiry=1, expiry_alert_days=? WHERE id=?")
       ->execute([(int)$_POST['alert_days'], (int)$_POST['product_id']]);
    header('Location: ' . BASE . '/batches.php?tab=expiry&success=' . urlencode('Expiry settings saved')); exit;
}

// ── LOAD DATA ──
$tab = $_GET['tab'] ?? 'batches';

// Batches list
$search_prod = (int)($_GET['pid'] ?? 0);
$search_sup  = (int)($_GET['sid'] ?? 0);
$where_parts = ["1=1 $bwhere"];
$params_b    = [];
if ($search_prod) { $where_parts[] = "sb.product_id=?"; $params_b[] = $search_prod; }
if ($search_sup)  { $where_parts[] = "sb.supplier_id=?"; $params_b[] = $search_sup; }
$where_b = implode(' AND ', $where_parts);

$page_num  = max(1,(int)($_GET['p'] ?? 1));
$per_page  = 25;
$offset    = ($page_num-1)*$per_page;

$total_batches = $db->prepare("SELECT COUNT(*) FROM stock_batches sb WHERE $where_b");
$total_batches->execute($params_b);
$total_batches = $total_batches->fetchColumn();
$total_pages = max(1,ceil($total_batches/$per_page));

$batches = $db->prepare("
    SELECT sb.*,
           p.name as product_name, COALESCE(p.name_ar,'') as product_name_ar,
           p.sku, COALESCE(p.emoji,'📦') as emoji, p.has_expiry,
           s.company as supplier_name,
           b.name as branch_name,
           u.name as received_by_name,
           po.po_number
    FROM stock_batches sb
    JOIN products p ON p.id = sb.product_id
    JOIN suppliers s ON s.id = sb.supplier_id
    JOIN branches b ON b.id = sb.branch_id
    LEFT JOIN users u ON u.id = sb.received_by
    LEFT JOIN purchase_orders po ON po.id = sb.po_id
    WHERE $where_b
    ORDER BY sb.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$batches->execute($params_b);
$batches = $batches->fetchAll();

// Expiry alerts (next 90 days)
$expiring = $db->prepare("
    SELECT sb.*, p.name as product_name, COALESCE(p.emoji,'📦') as emoji,
           s.company as supplier_name, b.name as branch_name,
           DATEDIFF(sb.expiry_date, CURDATE()) as days_left
    FROM stock_batches sb
    JOIN products p ON p.id = sb.product_id
    JOIN suppliers s ON s.id = sb.supplier_id
    JOIN branches b ON b.id = sb.branch_id
    WHERE sb.expiry_date IS NOT NULL
    AND sb.qty_remaining > 0
    AND sb.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)
    $bwhere
    ORDER BY sb.expiry_date ASC
");
$expiring->execute();
$expiring = $expiring->fetchAll();
$expiry_count = count($expiring);

// Product supplier prices
$prod_prices = $db->query("
    SELECT ps.*, p.name as product_name, COALESCE(p.emoji,'📦') as emoji,
           p.sku, s.company as supplier_name
    FROM product_suppliers ps
    JOIN products p ON p.id = ps.product_id
    JOIN suppliers s ON s.id = ps.supplier_id
    ORDER BY p.name, ps.is_preferred DESC, ps.cost_price ASC
")->fetchAll();

// Stats
$stats = $db->query("
    SELECT
      COUNT(*) as total_batches,
      SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active,
      SUM(CASE WHEN status='depleted' THEN 1 ELSE 0 END) as depleted,
      SUM(CASE WHEN status='expired' THEN 1 ELSE 0 END) as expired,
      SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(),INTERVAL 30 DAY) AND qty_remaining>0 THEN 1 ELSE 0 END) as exp_soon
    FROM stock_batches
" . ($is_super ? "" : "WHERE branch_id=$branch_id"))->fetch();

$all_products = $db->query("SELECT id, name, sku, COALESCE(emoji,'📦') as emoji FROM products WHERE is_active=1 ORDER BY name")->fetchAll();
$all_suppliers= $db->query("SELECT id, company FROM suppliers WHERE is_active=1 ORDER BY company")->fetchAll();
$all_branches = $db->query("SELECT id, name FROM branches WHERE is_active=1")->fetchAll();
$open_pos     = $db->query("SELECT id, po_number, supplier_id FROM purchase_orders WHERE status IN ('pending','partial') ORDER BY created_at DESC")->fetchAll();

// Expiry products
$expiry_prods = $db->query("SELECT id, name, COALESCE(emoji,'📦') as emoji, has_expiry, COALESCE(expiry_alert_days,90) as expiry_alert_days FROM products WHERE is_active=1 ORDER BY has_expiry DESC, name")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<!-- STATS -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr));margin-bottom:16px">
  <div class="stat-card blue"><div class="stat-icon">📦</div><div class="stat-label">Total Batches</div><div class="stat-value text-blue"><?= number_format($stats['total_batches']) ?></div></div>
  <div class="stat-card green"><div class="stat-icon">✅</div><div class="stat-label">Active</div><div class="stat-value text-green"><?= $stats['active'] ?></div></div>
  <div class="stat-card red"><div class="stat-icon">⚠️</div><div class="stat-label">Expiring Soon</div><div class="stat-value text-red"><?= $stats['exp_soon'] ?> <small style="font-size:11px">≤30d</small></div></div>
  <div class="stat-card amber"><div class="stat-icon">📋</div><div class="stat-label">Supplier Prices</div><div class="stat-value text-amber"><?= count($prod_prices) ?></div></div>
  <?php if ($expiry_count > 0): ?>
  <div class="stat-card red"><div class="stat-icon">🚨</div><div class="stat-label">Expiry Alerts</div><div class="stat-value text-red"><?= $expiry_count ?></div><div class="stat-delta down">next 90 days</div></div>
  <?php endif; ?>
</div>

<!-- EXPIRY ALERT BANNER -->
<?php if ($expiry_count > 0): ?>
<div class="alert alert-danger" style="margin-bottom:16px">
  🚨 <strong><?= $expiry_count ?> batch<?= $expiry_count>1?'es':'' ?></strong> expiring within 90 days!
  <?php foreach(array_slice($expiring,0,3) as $ex): ?>
  &nbsp;·&nbsp; <?= htmlspecialchars($ex['emoji'].' '.$ex['product_name']) ?>
  <span class="badge badge-red"><?= $ex['days_left'] <= 0 ? 'EXPIRED' : $ex['days_left'].'d left' ?></span>
  <?php endforeach; ?>
  <?php if ($expiry_count > 3): ?> &nbsp;+<?= $expiry_count-3 ?> more<?php endif; ?>
  <a href="?tab=expiry" style="margin-left:8px;color:inherit;font-weight:600;text-decoration:underline">View All →</a>
</div>
<?php endif; ?>

<div class="tabs">
  <div class="tab <?= $tab==='batches'?'active':'' ?>" onclick="switchTab('tab-batches',this)">📦 Batches</div>
  <div class="tab <?= $tab==='receive'?'active':'' ?>" onclick="switchTab('tab-receive',this)">📥 Receive Stock</div>
  <div class="tab <?= $tab==='prices'?'active':'' ?>" onclick="switchTab('tab-prices',this)">💰 Supplier Prices</div>
  <div class="tab <?= $tab==='expiry'?'active':'' ?>" onclick="switchTab('tab-expiry',this)">
    📅 Expiry Tracking <?php if ($expiry_count > 0): ?><span class="badge badge-red" style="margin-left:4px"><?= $expiry_count ?></span><?php endif; ?>
  </div>
  <div class="tab <?= $tab==='trace'?'active':'' ?>" onclick="switchTab('tab-trace',this)">🔍 Traceability</div>
</div>

<!-- ══ TAB: BATCHES LIST ══ -->
<div id="tab-batches" <?= $tab!=='batches'?'style="display:none"':'' ?>>
  <div class="inv-filters" style="margin-bottom:12px">
    <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;flex:1;align-items:center">
      <input type="hidden" name="tab" value="batches">
      <select class="search-input" name="pid" style="width:200px" onchange="this.form.submit()">
        <option value="">All Products</option>
        <?php foreach ($all_products as $p): ?>
        <option value="<?= $p['id'] ?>" <?= $search_prod==$p['id']?'selected':'' ?>><?= htmlspecialchars($p['emoji'].' '.$p['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select class="search-input" name="sid" style="width:180px" onchange="this.form.submit()">
        <option value="">All Suppliers</option>
        <?php foreach ($all_suppliers as $s): ?>
        <option value="<?= $s['id'] ?>" <?= $search_sup==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['company']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($search_prod || $search_sup): ?><a href="?tab=batches" class="btn btn-ghost btn-sm">↺ Clear</a><?php endif; ?>
    </form>
  </div>

  <div class="card">
    <div class="tbl-wrap">
      <table>
        <thead><tr>
          <th>Batch #</th>
          <th>Product</th>
          <th>Supplier</th>
          <th class="hide-mobile">Branch</th>
          <th>Received</th>
          <th>Remaining</th>
          <th>Cost/unit</th>
          <th>Expiry</th>
          <th>Status</th>
          <th class="hide-tablet">PO #</th>
        </tr></thead>
        <tbody>
          <?php foreach ($batches as $b):
            $days_left = $b['expiry_date'] ? (int)((strtotime($b['expiry_date'])-time())/86400) : null;
            $exp_badge = '';
            if ($days_left !== null) {
              if ($days_left < 0)  $exp_badge = 'badge-red';
              elseif ($days_left <= 30)  $exp_badge = 'badge-red';
              elseif ($days_left <= 90)  $exp_badge = 'badge-amber';
              else $exp_badge = 'badge-green';
            }
            $status_badge = ['active'=>'badge-green','low'=>'badge-amber','depleted'=>'badge-gray','expired'=>'badge-red'][$b['status']] ?? 'badge-gray';
            $pct = $b['qty_received'] > 0 ? round($b['qty_remaining'] / $b['qty_received'] * 100) : 0;
          ?>
          <tr>
            <td><code style="font-size:11px;color:var(--accent2)"><?= htmlspecialchars($b['batch_number']) ?></code>
              <?php if ($b['lot_number']): ?><br><span style="font-size:10px;color:var(--text3)">Lot: <?= htmlspecialchars($b['lot_number']) ?></span><?php endif; ?>
            </td>
            <td>
              <div style="display:flex;align-items:center;gap:6px">
                <span><?= htmlspecialchars($b['emoji']) ?></span>
                <div>
                  <div style="font-weight:500;font-size:13px"><?= htmlspecialchars($b['product_name']) ?></div>
                  <?php if ($b['product_name_ar']): ?><div style="font-size:10px;color:var(--text3);direction:rtl"><?= htmlspecialchars($b['product_name_ar']) ?></div><?php endif; ?>
                  <div style="font-size:10px;color:var(--text3)"><?= htmlspecialchars($b['sku']) ?></div>
                </div>
              </div>
            </td>
            <td style="font-weight:500"><?= htmlspecialchars($b['supplier_name']) ?></td>
            <td class="hide-mobile" style="font-size:12px"><?= htmlspecialchars($b['branch_name']) ?></td>
            <td style="font-size:12px"><?= date('d M Y', strtotime($b['received_date'])) ?>
              <?php if ($b['received_by_name']): ?><br><span style="color:var(--text3);font-size:10px">by <?= htmlspecialchars($b['received_by_name']) ?></span><?php endif; ?>
            </td>
            <td>
              <div style="font-weight:600"><?= $b['qty_remaining'] ?> / <?= $b['qty_received'] ?></div>
              <div class="progress" style="width:70px;margin-top:4px"><div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $pct>50?'var(--green)':($pct>20?'var(--amber)':'var(--red)') ?>"></div></div>
            </td>
            <td style="font-weight:600;color:var(--blue)"><?= fmt_money($b['cost_price']) ?></td>
            <td>
              <?php if ($b['expiry_date']): ?>
              <span class="badge <?= $exp_badge ?>">
                <?= date('d M Y', strtotime($b['expiry_date'])) ?>
              </span>
              <div style="font-size:10px;color:var(--text3);margin-top:2px">
                <?= $days_left < 0 ? '<span style="color:var(--red)">EXPIRED</span>' : ($days_left . 'd left') ?>
              </div>
              <?php else: ?><span style="color:var(--text3)">—</span><?php endif; ?>
            </td>
            <td><span class="badge <?= $status_badge ?>"><?= ucfirst($b['status']) ?></span></td>
            <td class="hide-tablet" style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($b['po_number'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($batches)): ?><tr><td colspan="10" style="text-align:center;padding:30px;color:var(--text3)">No batches yet — receive stock to create your first batch</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;flex-wrap:wrap;gap:8px">
      <span class="page-info"><?= count($batches) ?> of <?= $total_batches ?> batches</span>
      <div class="pagination">
        <?php for ($i=1;$i<=$total_pages;$i++): ?>
        <a href="?tab=batches&pid=<?= $search_prod ?>&sid=<?= $search_sup ?>&p=<?= $i ?>" class="page-link <?= $i==$page_num?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ══ TAB: RECEIVE STOCK ══ -->
<div id="tab-receive" <?= $tab!=='receive'?'style="display:none"':'' ?>>
  <div class="card">
    <div class="card-title"><span>📥 Receive New Stock from Supplier</span></div>
    <form method="POST">
      <input type="hidden" name="action" value="receive">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Product *</label>
          <select class="form-select" name="product_id" id="recv-product" required onchange="loadSupplierPrices(this.value)">
            <option value="">-- Select Product --</option>
            <?php foreach ($all_products as $p): ?>
            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['emoji'].' '.$p['name'].' ('.$p['sku'].')') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Supplier *</label>
          <select class="form-select" name="supplier_id" id="recv-supplier" required>
            <option value="">-- Select Supplier --</option>
            <?php foreach ($all_suppliers as $s): ?>
            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['company']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div id="supplier-price-hint" style="display:none;margin:-8px 0 10px;padding:8px 12px;background:rgba(45,204,122,.08);border:1px solid rgba(45,204,122,.2);border-radius:var(--r);font-size:12px;color:var(--green)"></div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Branch (receiving to) *</label>
          <select class="form-select" name="branch_id" required>
            <?php foreach ($all_branches as $b): ?>
            <option value="<?= $b['id'] ?>" <?= (!$is_super && $branch_id==$b['id'])?'selected':'' ?>><?= htmlspecialchars($b['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Quantity Received *</label>
          <input class="form-input" type="number" name="qty" min="1" required placeholder="e.g. 30 (for 1 box of 30 lenses)">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Cost Price per Unit (<?= get_setting('currency','KWD') ?>) *</label>
          <input class="form-input" type="number" name="cost_price" id="recv-cost" step="0.001" min="0" required placeholder="0.133">
        </div>
        <div class="form-group">
          <label class="form-label">Link to Purchase Order</label>
          <select class="form-select" name="po_id">
            <option value="">-- No PO (direct purchase) --</option>
            <?php foreach ($open_pos as $po): ?>
            <option value="<?= $po['id'] ?>"><?= htmlspecialchars($po['po_number']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">📅 Expiry Date <small style="color:var(--text3)">(required for lenses, food, medicine)</small></label>
          <input class="form-input" type="date" name="expiry_date" id="recv-expiry">
        </div>
        <div class="form-group">
          <label class="form-label">Manufacture Date</label>
          <input class="form-input" type="date" name="mfg_date">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Supplier Lot / Batch Number</label>
          <input class="form-input" name="lot_number" placeholder="e.g. ACU2026-LOT-4421">
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <input class="form-input" name="notes" placeholder="Any notes about this delivery">
        </div>
      </div>
      <div style="background:var(--bg3);border-radius:var(--r);padding:14px;margin-bottom:16px">
        <div style="font-size:12px;color:var(--text3);margin-bottom:6px">📦 What happens when you save:</div>
        <ul style="font-size:12px;color:var(--text2);margin-left:16px;line-height:1.8">
          <li>New batch created with unique batch number</li>
          <li>Stock added to selected branch inventory</li>
          <li>Stock movement logged (type: IN)</li>
          <li>Product's last supplier + last cost price updated</li>
          <li>Supplier price list updated</li>
          <li>Expiry alert activated if expiry date is set</li>
        </ul>
      </div>
      <div class="modal-footer" style="padding:0;border:none">
        <button type="submit" class="btn btn-primary" style="width:100%;padding:12px">📥 Receive Stock & Create Batch</button>
      </div>
    </form>
  </div>
</div>

<!-- ══ TAB: SUPPLIER PRICES ══ -->
<div id="tab-prices" <?= $tab!=='prices'?'style="display:none"':'' ?>>
  <div style="display:flex;gap:8px;margin-bottom:12px;justify-content:flex-end">
    <button class="btn btn-primary btn-sm" onclick="openModal('price-modal')">+ Add Supplier Price</button>
  </div>
  <div class="card">
    <div class="card-title"><span>💰 Supplier Price List — All Products</span></div>
    <div class="tbl-wrap">
      <table>
        <thead><tr>
          <th>Product</th>
          <th>Supplier</th>
          <th>Cost Price</th>
          <th class="hide-mobile">MOQ</th>
          <th class="hide-mobile">Lead Days</th>
          <th>Preferred</th>
          <th class="hide-tablet">Updated</th>
          <th>Action</th>
        </tr></thead>
        <tbody>
          <?php
          $last_product = '';
          foreach ($prod_prices as $pp):
            $is_new_product = $pp['product_name'] !== $last_product;
            $last_product = $pp['product_name'];
          ?>
          <?php if ($is_new_product): ?>
          <tr style="background:var(--bg3)">
            <td colspan="8" style="font-weight:700;font-size:12px;padding:6px 12px;color:var(--text2)">
              <?= htmlspecialchars($pp['emoji'].' '.$pp['product_name']) ?>
              <span style="color:var(--text3);font-weight:400;margin-left:8px"><?= htmlspecialchars($pp['sku']) ?></span>
            </td>
          </tr>
          <?php endif; ?>
          <tr>
            <td style="padding-left:24px;color:var(--text3);font-size:12px">↳</td>
            <td style="font-weight:500"><?= htmlspecialchars($pp['supplier_name']) ?></td>
            <td style="font-weight:700;color:<?= $pp['is_preferred'] ? 'var(--green)' : 'var(--text)' ?>"><?= fmt_money($pp['cost_price']) ?></td>
            <td class="hide-mobile"><?= $pp['min_order_qty'] ?> units</td>
            <td class="hide-mobile"><?= $pp['lead_days'] ?> days</td>
            <td><?= $pp['is_preferred'] ? '<span class="badge badge-green">⭐ Preferred</span>' : '<span class="badge badge-gray">—</span>' ?></td>
            <td class="hide-tablet" style="font-size:11px;color:var(--text3)"><?= $pp['updated_at'] ? date('d M Y', strtotime($pp['updated_at'])) : '—' ?></td>
            <td>
              <button class="btn btn-ghost btn-sm" onclick='fillPriceModal(<?= json_encode($pp, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>✏️</button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($prod_prices)): ?><tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text3)">No supplier prices yet — add prices or receive stock</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ══ TAB: EXPIRY TRACKING ══ -->
<div id="tab-expiry" <?= $tab!=='expiry'?'style="display:none"':'' ?>>
  <div class="card-grid" style="margin-bottom:16px">
    <?php
    $exp30 = array_filter($expiring, fn($e) => (int)((strtotime($e['expiry_date'])-time())/86400) <= 30);
    $exp90 = array_filter($expiring, fn($e) => (int)((strtotime($e['expiry_date'])-time())/86400) > 30);
    $expired = array_filter($expiring, fn($e) => strtotime($e['expiry_date']) < time());
    ?>
    <div class="card red"><h3>🚨 Expired</h3><div style="font-size:28px;font-weight:700;color:var(--red)"><?= count($expired) ?></div><div style="font-size:12px;color:var(--text3)">batches — remove from shelf!</div></div>
    <div class="card amber"><h3>⚠️ Expiring ≤ 30 days</h3><div style="font-size:28px;font-weight:700;color:var(--amber)"><?= count($exp30) ?></div><div style="font-size:12px;color:var(--text3)">act now — discount or return</div></div>
  </div>

  <?php if (!empty($expiring)): ?>
  <div class="card">
    <div class="card-title"><span>📅 Expiry Alert — Next 90 Days</span></div>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Batch #</th><th>Product</th><th>Supplier</th><th class="hide-mobile">Branch</th><th>Qty Left</th><th>Expiry Date</th><th>Days Left</th></tr></thead>
        <tbody>
          <?php foreach ($expiring as $e):
            $dl = (int)((strtotime($e['expiry_date'])-time())/86400);
            $cl = $dl < 0 ? 'var(--red)' : ($dl <= 30 ? 'var(--red)' : 'var(--amber)');
          ?>
          <tr>
            <td><code style="font-size:11px"><?= htmlspecialchars($e['batch_number']) ?></code></td>
            <td><?= htmlspecialchars($e['emoji'].' '.$e['product_name']) ?></td>
            <td><?= htmlspecialchars($e['supplier_name']) ?></td>
            <td class="hide-mobile"><?= htmlspecialchars($e['branch_name']) ?></td>
            <td style="font-weight:600"><?= $e['qty_remaining'] ?></td>
            <td><?= date('d M Y', strtotime($e['expiry_date'])) ?></td>
            <td style="font-weight:700;color:<?= $cl ?>">
              <?= $dl < 0 ? '🔴 EXPIRED' : ($dl === 0 ? '🔴 TODAY' : "⚠️ $dl days") ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php else: ?>
  <div class="card" style="text-align:center;padding:40px">
    <div style="font-size:40px;margin-bottom:10px">✅</div>
    <div style="font-weight:600;margin-bottom:4px">No expiry alerts</div>
    <div style="color:var(--text3);font-size:13px">All batches are within safe expiry range</div>
  </div>
  <?php endif; ?>

  <!-- Expiry settings per product -->
  <div class="card" style="margin-top:16px">
    <div class="card-title"><span>⚙️ Expiry Settings — Per Product</span></div>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Product</th><th>Expiry Tracking</th><th>Alert Before (days)</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($expiry_prods as $ep): ?>
          <tr>
            <td><?= htmlspecialchars($ep['emoji'].' '.$ep['name']) ?></td>
            <td><?= $ep['has_expiry'] ? '<span class="badge badge-green">✅ Enabled</span>' : '<span class="badge badge-gray">Disabled</span>' ?></td>
            <td><?= $ep['has_expiry'] ? $ep['expiry_alert_days'].' days' : '—' ?></td>
            <td>
              <form method="POST" style="display:inline-flex;gap:6px;align-items:center">
                <input type="hidden" name="action" value="set_expiry">
                <input type="hidden" name="product_id" value="<?= $ep['id'] ?>">
                <input type="number" name="alert_days" class="form-input" style="width:70px;padding:4px 8px;font-size:12px"
                       value="<?= $ep['expiry_alert_days'] ?>" min="1" max="365">
                <button type="submit" class="btn btn-ghost btn-sm">Save</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ══ TAB: TRACEABILITY ══ -->
<div id="tab-trace" <?= $tab!=='trace'?'style="display:none"':'' ?>>
  <div class="card">
    <div class="card-title"><span>🔍 Supplier Traceability Report</span></div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:16px">
      <div>
        <label class="form-label">Search by Product</label>
        <select class="form-select" id="trace-product" onchange="loadTrace()">
          <option value="">-- Select Product --</option>
          <?php foreach ($all_products as $p): ?>
          <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['emoji'].' '.$p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label">Search by Supplier</label>
        <select class="form-select" id="trace-supplier" onchange="loadTrace()">
          <option value="">-- Select Supplier --</option>
          <?php foreach ($all_suppliers as $s): ?>
          <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['company']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label">Date Range</label>
        <input class="form-input" type="month" id="trace-month" onchange="loadTrace()" value="<?= date('Y-m') ?>">
      </div>
    </div>
    <div id="trace-results">
      <div style="text-align:center;padding:40px;color:var(--text3)">
        Select a product or supplier above to see traceability report
      </div>
    </div>
  </div>
</div>

<!-- ADD SUPPLIER PRICE MODAL -->
<div class="modal-backdrop" id="price-modal">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <div class="modal-title">💰 Supplier Price</div>
      <button class="modal-close" onclick="closeModal('price-modal')">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="save_supplier_price">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Product *</label>
          <select class="form-select" name="product_id" id="pm-product" required>
            <option value="">-- Select --</option>
            <?php foreach ($all_products as $p): ?>
            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['emoji'].' '.$p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Supplier *</label>
          <select class="form-select" name="supplier_id" id="pm-supplier" required>
            <option value="">-- Select --</option>
            <?php foreach ($all_suppliers as $s): ?>
            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['company']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Cost Price *</label>
            <input class="form-input" name="cost_price" id="pm-cost" type="number" step="0.001" min="0" required>
          </div>
          <div class="form-group">
            <label class="form-label">Min Order Qty</label>
            <input class="form-input" name="min_order_qty" id="pm-moq" type="number" min="1" value="1">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Lead Days</label>
            <input class="form-input" name="lead_days" id="pm-lead" type="number" min="0" value="7">
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:4px">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
              <input type="checkbox" name="is_preferred" id="pm-pref">
              ⭐ Mark as Preferred Supplier
            </label>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <input class="form-input" name="notes" id="pm-notes" placeholder="Optional notes">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('price-modal')">Cancel</button>
        <button type="submit" class="btn btn-primary">💾 Save</button>
      </div>
    </form>
  </div>
</div>

<?php
$prices_json = json_encode($prod_prices, JSON_HEX_APOS|JSON_HEX_QUOT);
$extra_js = '<script>
const allPrices = ' . $prices_json . ';

function switchTab(id, el) {
  ["tab-batches","tab-receive","tab-prices","tab-expiry","tab-trace"].forEach(function(t) {
    document.getElementById(t).style.display = "none";
  });
  document.getElementById(id).style.display = "block";
  document.querySelectorAll(".tab").forEach(function(t) { t.classList.remove("active"); });
  el.classList.add("active");
}

// Show preferred supplier price hint when product selected
function loadSupplierPrices(productId) {
  const hint = document.getElementById("supplier-price-hint");
  const supSel = document.getElementById("recv-supplier");
  const costFld = document.getElementById("recv-cost");
  if (!productId) { hint.style.display="none"; return; }
  const prices = allPrices.filter(function(p) { return p.product_id == productId; });
  if (prices.length === 0) { hint.style.display="none"; return; }
  const preferred = prices.find(function(p) { return p.is_preferred == 1; }) || prices[0];
  const parts = prices.map(function(p) {
    return (p.is_preferred?"⭐ ":"") + p.supplier_name + ": " + p.cost_price;
  });
  hint.textContent = "Known prices — " + parts.join("  ·  ");
  hint.style.display = "block";
  if (preferred) {
    supSel.value = preferred.supplier_id;
    costFld.value = parseFloat(preferred.cost_price).toFixed(3);
  }
}

// Pre-fill price modal
function fillPriceModal(pp) {
  document.getElementById("pm-product").value  = pp.product_id;
  document.getElementById("pm-supplier").value = pp.supplier_id;
  document.getElementById("pm-cost").value     = parseFloat(pp.cost_price).toFixed(3);
  document.getElementById("pm-moq").value      = pp.min_order_qty;
  document.getElementById("pm-lead").value     = pp.lead_days;
  document.getElementById("pm-pref").checked   = pp.is_preferred == 1;
  document.getElementById("pm-notes").value    = pp.notes || "";
  openModal("price-modal");
}

// Traceability AJAX
function loadTrace() {
  const pid  = document.getElementById("trace-product").value;
  const sid  = document.getElementById("trace-supplier").value;
  const mon  = document.getElementById("trace-month").value;
  if (!pid && !sid) {
    document.getElementById("trace-results").innerHTML =
      "<div style=\"text-align:center;padding:40px;color:var(--text3)\">Select a product or supplier above</div>";
    return;
  }
  const params = new URLSearchParams({ pid, sid, month: mon });
  document.getElementById("trace-results").innerHTML = "<div style=\"padding:20px;text-align:center;color:var(--text3)\">Loading...</div>";
  fetch("' . BASE . '/api/trace_report.php?" + params)
    .then(function(r) { return r.text(); })
    .then(function(html) { document.getElementById("trace-results").innerHTML = html; })
    .catch(function() { document.getElementById("trace-results").innerHTML = "<div style=\"color:var(--red);padding:20px\">Error loading report</div>"; });
}
</script>';
require __DIR__ . '/includes/footer.php';
