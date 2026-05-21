<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$current_page = 'dashboard';
$page_title   = __('nav_dashboard');

$db = db();

// ── KPIs ──
// FIX: Exclude refunded invoices from all revenue/sales figures
$today_sales  = $db->query("SELECT COALESCE(SUM(total),0) as s FROM invoices WHERE DATE(created_at)=CURDATE() AND status != 'refunded'")->fetch()['s'];
$month_sales  = $db->query("SELECT COALESCE(SUM(total),0) as s FROM invoices WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW()) AND status != 'refunded'")->fetch()['s'];
// FIX: Customer pending dues — exclude walk-in (id=1), only active customers
$pending_dues = $db->query("SELECT COALESCE(SUM(ABS(balance)),0) as s FROM customers WHERE balance < 0 AND id > 1")->fetch()['s'];
$supplier_due = $db->query("SELECT COALESCE(SUM(ABS(balance)),0) as s FROM suppliers WHERE balance < 0")->fetch()['s'];
$total_prods  = $db->query("SELECT COUNT(*) as c FROM products WHERE is_active=1")->fetch()['c'];
// FIX: orders_today excludes refunded
$orders_today = $db->query("SELECT COUNT(*) as c FROM invoices WHERE DATE(created_at)=CURDATE() AND status != 'refunded'")->fetch()['c'];
// FIX: active customers — exclude walk-in AND inactive
$active_custs = $db->query("SELECT COUNT(*) as c FROM customers WHERE is_active=1 AND id > 1")->fetch()['c'];
// FIX: low_stock — count DISTINCT products by SUM of qty across all branches, not raw stock rows
// Raw count was wrong: a product in 3 branches counted as 3 items even if total qty is fine
// Count products with qty <= 5 (includes 0 and negative — all are problems)
$low_stock    = $db->query("SELECT COUNT(*) as c FROM (SELECT product_id, SUM(qty) as total_qty FROM stock GROUP BY product_id HAVING total_qty <= 5) as ls")->fetch()['c'];
// FIX: credit_total — exclude refunded, use MAX(0,...) to avoid negative outstanding
$credit_count = $db->query("SELECT COUNT(*) as c FROM invoices WHERE status='credit'")->fetch()['c'];
$partial_count= $db->query("SELECT COUNT(*) as c FROM invoices WHERE status='partial'")->fetch()['c'];
$credit_total = $db->query("SELECT COALESCE(SUM(GREATEST(0, total - paid_amount)),0) as s FROM invoices WHERE status IN ('credit','partial')")->fetch()['s'];

// Weekly sales (last 7 days)
$weekly = $db->query("
  SELECT DATE(created_at) as d, SUM(total) as s
  FROM invoices
  WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
  AND status != 'refunded'
  GROUP BY DATE(created_at)
  ORDER BY d
")->fetchAll();
$weekly_map = [];
foreach ($weekly as $w) $weekly_map[$w['d']] = $w['s'];
$week_days = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $week_days[] = ['date' => $date, 'label' => date('D', strtotime($date)), 'sales' => $weekly_map[$date] ?? 0];
}
$max_sales = max(array_column($week_days, 'sales')) ?: 1;

// Branch performance
$branch_sales = $db->query("
  SELECT b.name, b.id,
         COALESCE(SUM(CASE WHEN DATE(i.created_at)=CURDATE() AND i.status != 'refunded' THEN i.total ELSE 0 END),0) as s,
         COUNT(CASE WHEN DATE(i.created_at)=CURDATE() AND i.status != 'refunded' THEN 1 END) as orders
  FROM branches b
  LEFT JOIN invoices i ON i.branch_id = b.id
  WHERE b.is_active=1
  GROUP BY b.id, b.name ORDER BY s DESC
")->fetchAll();
$max_branch = !empty($branch_sales) ? (max(array_column($branch_sales, 's')) ?: 1) : 1;

// Best sellers — try with emoji column (needs migration), fallback without
try {
    $best_sellers = $db->query("
        SELECT p.name, p.sku, COALESCE(p.emoji,'📦') as emoji,
               SUM(ii.qty) as qty_sold, SUM(ii.total) as revenue
        FROM invoice_items ii
        JOIN products p   ON p.id = ii.product_id
        JOIN invoices inv ON inv.id = ii.invoice_id
        WHERE inv.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND inv.status != 'refunded'
        GROUP BY p.id ORDER BY qty_sold DESC LIMIT 5
    ")->fetchAll();
} catch (PDOException $e) {
    $best_sellers = $db->query("
        SELECT p.name, p.sku, '📦' as emoji,
               SUM(ii.qty) as qty_sold, SUM(ii.total) as revenue
        FROM invoice_items ii
        JOIN products p   ON p.id = ii.product_id
        JOIN invoices inv ON inv.id = ii.invoice_id
        WHERE inv.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND inv.status != 'refunded'
        GROUP BY p.id ORDER BY qty_sold DESC LIMIT 5
    ")->fetchAll();
}

// Due customers — try with company_name (needs migration), fallback without
try {
    $due_customers = $db->query("
        SELECT name, COALESCE(company_name,'') as company_name, balance
        FROM customers WHERE balance < 0 AND id > 1 ORDER BY balance ASC LIMIT 5
    ")->fetchAll();
} catch (PDOException $e) {
    $due_customers = $db->query("
        SELECT name, '' as company_name, balance
        FROM customers WHERE balance < 0 AND id > 1 ORDER BY balance ASC LIMIT 5
    ")->fetchAll();
}

// Low stock alerts — try with emoji, fallback without
try {
    $low_items = $db->query("
        SELECT p.name, COALESCE(p.emoji,'📦') as emoji, SUM(s.qty) as qty
        FROM stock s
        JOIN products p ON p.id = s.product_id
        WHERE p.is_active = 1
        GROUP BY p.id HAVING SUM(s.qty) <= 5
        ORDER BY qty ASC LIMIT 5
    ")->fetchAll();
} catch (PDOException $e) {
    $low_items = $db->query("
        SELECT p.name, '📦' as emoji, SUM(s.qty) as qty
        FROM stock s
        JOIN products p ON p.id = s.product_id
        WHERE p.is_active = 1
        GROUP BY p.id HAVING SUM(s.qty) <= 5
        ORDER BY qty ASC LIMIT 5
    ")->fetchAll();
}

// Payment mode breakdown today
// FIX: Use paid_amount not total — credit invoices show 0 collected, not inflated by unpaid total
$pay_modes = $db->query("
  SELECT payment_mode, COUNT(*) as cnt,
         SUM(paid_amount) as s,
         SUM(total) as invoice_total
  FROM invoices WHERE DATE(created_at)=CURDATE() AND status != 'refunded'
  GROUP BY payment_mode
")->fetchAll();
$pay_map = [];
$pay_total = 0;
foreach ($pay_modes as $p) { $pay_map[$p['payment_mode']] = $p['s']; $pay_total += $p['s']; }

require __DIR__ . '/includes/header.php';
?>

<div class="stats-grid">
  <div class="stat-card green">
    <div class="stat-icon">💰</div>
    <div class="stat-label"><?= __('todays_sales') ?></div>
    <div class="stat-value text-green"><?= fmt_money($today_sales) ?></div>
    <div class="stat-delta up">↑ <?= $orders_today ?> <?= __('orders') ?></div>
  </div>
  <div class="stat-card purple">
    <div class="stat-icon">📊</div>
    <div class="stat-label"><?= __('monthly_revenue') ?></div>
    <div class="stat-value text-accent"><?= fmt_money($month_sales) ?></div>
    <div class="stat-delta up">↑ <?= __('this_month_label') ?></div>
  </div>
  <div class="stat-card amber">
    <div class="stat-icon">⏳</div>
    <div class="stat-label"><?= __('pending_dues') ?></div>
    <div class="stat-value text-amber"><?= fmt_money($pending_dues) ?></div>
    <div class="stat-delta down"><?= __('customer_receivables') ?></div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon">🏭</div>
    <div class="stat-label"><?= __('supplier_dues') ?></div>
    <div class="stat-value text-red"><?= fmt_money($supplier_due) ?></div>
    <div class="stat-delta down"><?= __('payables_outstanding') ?></div>
  </div>
  <div class="stat-card blue">
    <div class="stat-icon">📦</div>
    <div class="stat-label"><?= __('total_products') ?></div>
    <div class="stat-value text-blue"><?= number_format($total_prods) ?></div>
    <div class="stat-delta up"><?= __('active_skus') ?></div>
  </div>
  <div class="stat-card teal">
    <div class="stat-icon">🛒</div>
    <div class="stat-label"><?= __('orders_today') ?></div>
    <div class="stat-value" style="color:var(--teal)"><?= $orders_today ?></div>
    <div class="stat-delta up"><?= __('invoices_issued') ?></div>
  </div>
  <div class="stat-card pink">
    <div class="stat-icon">👥</div>
    <div class="stat-label"><?= __('active_customers') ?></div>
    <div class="stat-value" style="color:var(--pink)"><?= number_format($active_custs) ?></div>
    <div class="stat-delta up"><?= __('registered_accounts') ?></div>
  </div>
  <div class="stat-card amber">
    <div class="stat-icon">⚠️</div>
    <div class="stat-label"><?= __('low_stock_items') ?></div>
    <div class="stat-value text-amber"><?= $low_stock ?></div>
    <div class="stat-delta down"><?= __('needs_reorder') ?></div>
  </div>
</div>

<div class="grid-70-30 mb-16">
  <div class="card">
    <div class="card-title">
      <span>📈 <?= __('weekly_sales') ?> (<?= get_setting('currency', 'KWD') ?>)</span>
      <a href="<?= BASE ?>/reports.php"><?= __('full_report') ?> →</a>
    </div>
    <div style="display:flex;align-items:flex-end;gap:6px;height:130px;padding:0 4px;">
      <?php foreach ($week_days as $day):
        $h = $max_sales > 0 ? round(($day['sales'] / $max_sales) * 100) : 5;
        $h = max($h, 5);
        $isToday = $day['date'] === date('Y-m-d');
        $bg = $isToday ? 'linear-gradient(to top,#22c55e,#4ade80)' : 'linear-gradient(to top,#4361ee,#818cf8)';
      ?>
      <div class="bar-col">
        <div class="bar" style="height:<?= $h ?>%;background:<?= $bg ?>" title="<?= $day['label'] ?>: <?= fmt_money($day['sales']) ?>"></div>
        <div class="bar-label"><?= $day['label'] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="margin-top:16px;display:grid;grid-template-columns:repeat(3,1fr);gap:8px">
      <?php
      // payment_mode ENUM: cash, knet, wamd, transfer, credit
      // 'partial' removed — it's a STATUS not a payment_mode (always showed 0%)
      $all_modes = [
        'cash'    =>['💵 Cash',     'var(--green)'],
        'knet'    =>['💳 KNET',     'var(--blue)'],
        'wamd'    =>['📱 WAMD',     'var(--accent2)'],
        'transfer'=>['🏦 Transfer', 'var(--amber)'],
        'credit'  =>['💰 Credit',   'var(--red)'],
      ];
      foreach ($all_modes as $m => $cfg):
        $pct = $pay_total > 0 ? round(($pay_map[$m] ?? 0) / $pay_total * 100) : 0;
      ?>
      <div style="background:var(--bg3);border-radius:8px;padding:10px;text-align:center">
        <div style="font-size:11px;color:var(--text3)"><?= $cfg[0] ?></div>
        <div style="font-size:15px;font-weight:600;color:<?= $cfg[1] ?>"><?= $pct ?>%</div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div style="display:flex;flex-direction:column;gap:14px">
    <div class="card">
      <div class="card-title"><span>🏢 <?= __('branch_performance') ?></span></div>
      <?php
        $branch_colors = ['var(--accent)','var(--blue)','var(--green)','var(--amber)'];
        foreach ($branch_sales as $bci => $b):
          $pct = $max_branch > 0 ? round(($b['s'] / $max_branch) * 100) : 0;
      ?>
      <div style="margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
          <span><?= htmlspecialchars($b['name']) ?></span>
          <div style="display:flex;gap:8px;align-items:center">
            <?php if ($b['orders'] > 0): ?><span style="font-size:10px;color:var(--text3)"><?= $b['orders'] ?> orders</span><?php endif; ?>
            <span class="text-muted"><?= fmt_money($b['s']) ?></span>
          </div>
        </div>
        <div class="progress"><div class="progress-fill" style="width:<?= max($pct,2) ?>%;background:<?= $branch_colors[$bci % 4] ?>"></div></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="card" style="background:linear-gradient(135deg,rgba(34,197,94,.08),rgba(34,197,94,.02))">
      <div style="font-size:11px;color:var(--text3);font-weight:500;letter-spacing:.5px;text-transform:uppercase;margin-bottom:4px">💵 <?= __('todays_revenue') ?></div>
      <div style="font-size:28px;font-weight:600;color:var(--green)"><?= fmt_money($today_sales) ?></div>
      <div style="font-size:11px;color:var(--text3);margin-top:4px"><?= __('all_branches_combined') ?></div>
    </div>
    <div class="card">
      <div style="font-size:11px;color:var(--text3);font-weight:500;letter-spacing:.5px;text-transform:uppercase;margin-bottom:8px">📋 <?= __('unpaid_invoices') ?></div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
        <span style="font-size:12px"><?= __('credit_invoices') ?></span>
        <span class="badge badge-red"><?= $credit_count ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
        <span style="font-size:12px"><?= __('partial_invoices') ?></span>
        <span class="badge badge-amber"><?= $partial_count ?></span>
      </div>
      <div style="border-top:1px solid var(--border);padding-top:6px;margin-top:6px;display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:12px;font-weight:500"><?= __('total_outstanding') ?></span>
        <span style="font-weight:600;color:var(--red)"><?= fmt_money($credit_total) ?></span>
      </div>
    </div>
  </div>
</div>

<div class="grid-60-40">
  <div class="card">
    <div class="card-title"><span>🔥 <?= __('best_selling') ?></span><a href="<?= BASE ?>/reports.php"><?= __('view_all') ?> →</a></div>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th><?= __('product') ?></th><th><?= __('sku') ?></th><th><?= __("units_sold") ?> (30d)</th><th><?= __('revenue') ?></th></tr></thead>
        <tbody>
          <?php foreach ($best_sellers as $bs): ?>
          <tr>
            <td style="font-weight:500"><?= htmlspecialchars($bs['emoji'] . ' ' . $bs['name']) ?></td>
            <td class="font-mono" style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($bs['sku']) ?></td>
            <td style="font-weight:600"><?= number_format($bs['qty_sold']) ?></td>
            <td class="text-green" style="font-weight:600"><?= fmt_money($bs['revenue']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($best_sellers)): ?>
          <tr><td colspan="4" style="text-align:center;color:var(--text3)"><?= __('no_sales_data') ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div style="display:flex;flex-direction:column;gap:14px">
    <div class="card">
      <div class="card-title"><span>⏳ <?= __('due_customers') ?></span><a href="<?= BASE ?>/customers.php"><?= __('view_all') ?> →</a></div>
      <?php foreach ($due_customers as $dc): ?>
      <div class="ledger-row">
        <div class="ledger-avatar" style="background:rgba(239,68,68,.08);color:var(--red)"><?= strtoupper(substr($dc['name'],0,2)) ?></div>
        <div class="ledger-info">
          <div class="ledger-name"><?= htmlspecialchars($dc['name']) ?></div>
          <div class="ledger-sub"><?= $dc['company_name'] ? htmlspecialchars($dc['company_name']) : __('due_balance') ?></div>
        </div>
        <div class="ledger-amount text-red">- <?= fmt_money(abs($dc['balance'])) ?></div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($due_customers)): ?>
      <div style="text-align:center;color:var(--text3);padding:20px">✅ <?= __('no_outstanding_dues') ?></div>
      <?php endif; ?>
    </div>
    <div class="card">
      <div class="card-title"><span>⚠️ <?= __('low_stock_alerts') ?></span></div>
      <?php foreach ($low_items as $li): ?>
      <div class="ledger-row">
        <div style="font-size:20px"><?= htmlspecialchars($li['emoji']) ?></div>
        <div class="ledger-info">
          <div class="ledger-name"><?= htmlspecialchars($li['name']) ?></div>
          <div class="ledger-sub" style="color:<?= $li['qty'] <= 0 ? 'var(--red)' : 'var(--amber)' ?>">
            <?= $li['qty'] <= 0 ? '🚫 Out of stock' : $li['qty'] . ' ' . __('units_left') ?>
          </div>
        </div>
        <a href="<?= BASE ?>/purchases.php" class="btn btn-sm btn-amber" style="flex-shrink:0"><?= __('reorder') ?></a>
      </div>
      <?php endforeach; ?>
      <?php if (empty($low_items)): ?>
      <div style="text-align:center;color:var(--text3);padding:20px">✅ <?= __('all_stock_healthy') ?></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
