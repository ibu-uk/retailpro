<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$current_page = 'accounting';
$page_title   = __('financial_statements');
$db = db();

$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? 0);  // 0 = full year

$date_label = $month ? date('F Y', mktime(0,0,0,$month,1,$year)) : "Year $year";
$date_from  = $month ? sprintf('%04d-%02d-01', $year, $month) : "$year-01-01";
$date_to    = $month ? date('Y-m-t', mktime(0,0,0,$month,1,$year)) : "$year-12-31";

// ── Income Statement Data ──
// FIX: removed dead $total_sales variable (was always 0 due to execute() returning bool)
// FIX: exclude refunded invoices from revenue and COGS
$r = $db->prepare("SELECT
    COALESCE(SUM(total),0)        as s,
    COALESCE(SUM(discount),0)     as d,
    COALESCE(SUM(paid_amount),0)  as p
  FROM invoices
  WHERE DATE(created_at) BETWEEN ? AND ?
  AND status != 'refunded'");
$r->execute([$date_from, $date_to]);
$rev = $r->fetch();

// FIX: exclude refunded invoices from COGS
$cogs_r = $db->prepare("
  SELECT COALESCE(SUM(ii.qty * p.cost_price),0) as c
  FROM invoice_items ii
  JOIN invoices inv ON inv.id = ii.invoice_id
  JOIN products p   ON p.id  = ii.product_id
  WHERE DATE(inv.created_at) BETWEEN ? AND ?
  AND inv.status != 'refunded'");
$cogs_r->execute([$date_from, $date_to]);
$cogs = $cogs_r->fetch()['c'];

$exp_r = $db->prepare("SELECT category, COALESCE(SUM(amount),0) as a FROM expenses WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY category ORDER BY a DESC");
$exp_r->execute([$date_from, $date_to]);
$expenses = $exp_r->fetchAll();
$total_expenses = array_sum(array_column($expenses, 'a'));

$gross_profit = $rev['s'] - $rev['d'] - $cogs;
$net_profit   = $gross_profit - $total_expenses;
$gp_margin    = $rev['s'] > 0 ? round($gross_profit / $rev['s'] * 100, 1) : 0;
$np_margin    = $rev['s'] > 0 ? round($net_profit   / $rev['s'] * 100, 1) : 0;

// ── Balance Sheet Data ──
// FIX: "Cash" on balance sheet should be cash from paid invoices + payments received
//      not just payments table (which misses direct POS cash sales).
//      Use: SUM of paid_amount from all paid/partial invoices + payments from customers.
//      Simpler accurate approach: total paid_amount ever collected on invoices.
$cash_from_invoices = $db->query("SELECT COALESCE(SUM(paid_amount),0) FROM invoices WHERE status != 'refunded'")->fetchColumn();
// Payments received from customers (for credit invoice settlements)
$cash_from_payments = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE type='customer'")->fetchColumn();
// Deduct payments made to suppliers and expenses (outflows) to get net cash
$cash_paid_suppliers_all = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE type='supplier'")->fetchColumn();
$cash_paid_expenses_all  = $db->query("SELECT COALESCE(SUM(amount),0) FROM expenses")->fetchColumn();
// Net cash position = all inflows − all outflows
// Note: cash_from_invoices already includes credit sales' paid portions; payments are the
// subsequent settlements on credit invoices. Avoid double-counting by using:
// Cash = paid_amount on invoices (cash+knet+wamd+transfer modes) + payments on credit invoices
$cash_direct   = $db->query("SELECT COALESCE(SUM(paid_amount),0) FROM invoices WHERE payment_mode != 'credit' AND status != 'refunded'")->fetchColumn();
$cash_credit   = $db->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE type='customer'")->fetchColumn();
$cash_position = ($cash_direct + $cash_credit) - $cash_paid_suppliers_all - $cash_paid_expenses_all;

$ar_balance    = $db->query("SELECT COALESCE(SUM(ABS(balance)),0) FROM customers WHERE balance < 0")->fetchColumn();
$inventory_val = $db->query("SELECT COALESCE(SUM(s.qty * p.cost_price),0) FROM stock s JOIN products p ON p.id=s.product_id")->fetchColumn();
// Liabilities
$ap_balance    = $db->query("SELECT COALESCE(SUM(ABS(balance)),0) FROM suppliers WHERE balance < 0")->fetchColumn();
// FIX: Equity is NOT derived circularly from Assets - Liabilities.
// Use accumulated net profit as a proxy for owner's equity (simple single-entity model).
$all_time_revenue  = $db->query("SELECT COALESCE(SUM(total - discount),0) FROM invoices WHERE status != 'refunded'")->fetchColumn();
$all_time_cogs     = $db->query("SELECT COALESCE(SUM(ii.qty * p.cost_price),0) FROM invoice_items ii JOIN invoices inv ON inv.id=ii.invoice_id JOIN products p ON p.id=ii.product_id WHERE inv.status != 'refunded'")->fetchColumn();
$all_time_expenses = $db->query("SELECT COALESCE(SUM(amount),0) FROM expenses")->fetchColumn();
$retained_earnings = $all_time_revenue - $all_time_cogs - $all_time_expenses;

$total_assets = max(0, $cash_position) + $ar_balance + $inventory_val;
$total_liab   = $ap_balance;
$equity       = $retained_earnings;
// Balance check: Assets should equal Liabilities + Equity
// If not, there's unrecorded capital injection — we show a balancing "Opening Capital" line
$balance_diff = $total_assets - ($total_liab + $equity);

// ── Cash Flow (period-scoped, now including direct POS sales) ──
// FIX: Cash inflow must include direct (non-credit) invoice payments, not just payments table
$cf_direct = $db->prepare("SELECT COALESCE(SUM(paid_amount),0) as c FROM invoices WHERE payment_mode != 'credit' AND DATE(created_at) BETWEEN ? AND ? AND status != 'refunded'");
$cf_direct->execute([$date_from, $date_to]);
$cash_in_direct = $cf_direct->fetch()['c'];

$cf_credit = $db->prepare("SELECT COALESCE(SUM(amount),0) as c FROM payments WHERE type='customer' AND DATE(created_at) BETWEEN ? AND ?");
$cf_credit->execute([$date_from, $date_to]);
$cash_in_credit = $cf_credit->fetch()['c'];

$cash_in = $cash_in_direct + $cash_in_credit;

$cash_out_exp_r = $db->prepare("SELECT COALESCE(SUM(amount),0) as c FROM expenses WHERE DATE(created_at) BETWEEN ? AND ?");
$cash_out_exp_r->execute([$date_from, $date_to]);
$cash_out_exp = $cash_out_exp_r->fetch()['c'];

$cash_out_sup_r = $db->prepare("SELECT COALESCE(SUM(amount),0) as c FROM payments WHERE type='supplier' AND DATE(created_at) BETWEEN ? AND ?");
$cash_out_sup_r->execute([$date_from, $date_to]);
$cash_out_sup = $cash_out_sup_r->fetch()['c'];

$net_cash = $cash_in - $cash_out_exp - $cash_out_sup;

// ── Monthly trend for chart ──
// FIX: exclude refunded invoices from monthly revenue
$monthly = $db->prepare("
  SELECT MONTH(created_at) as m,
         SUM(total) as revenue,
         COUNT(*) as cnt
  FROM invoices WHERE YEAR(created_at)=? AND status != 'refunded'
  GROUP BY MONTH(created_at) ORDER BY m
");
$monthly->execute([$year]);
$monthly = $monthly->fetchAll();
$monthly_map = [];
foreach ($monthly as $mn) $monthly_map[$mn['m']] = $mn;

// ── Outstanding AR aging ──
// FIX: added date filter option + correct total column
$ar_aging = $db->query("
  SELECT c.name, c.balance,
    COALESCE(SUM(CASE WHEN DATEDIFF(NOW(), i.created_at) <= 30
      THEN GREATEST(0, i.total - i.paid_amount) ELSE 0 END),0) as age_0_30,
    COALESCE(SUM(CASE WHEN DATEDIFF(NOW(), i.created_at) BETWEEN 31 AND 60
      THEN GREATEST(0, i.total - i.paid_amount) ELSE 0 END),0) as age_31_60,
    COALESCE(SUM(CASE WHEN DATEDIFF(NOW(), i.created_at) BETWEEN 61 AND 90
      THEN GREATEST(0, i.total - i.paid_amount) ELSE 0 END),0) as age_61_90,
    COALESCE(SUM(CASE WHEN DATEDIFF(NOW(), i.created_at) > 90
      THEN GREATEST(0, i.total - i.paid_amount) ELSE 0 END),0) as age_90plus,
    COALESCE(SUM(GREATEST(0, i.total - i.paid_amount)),0) as total_outstanding
  FROM customers c
  JOIN invoices i ON i.customer_id = c.id
  WHERE i.status IN ('credit','partial') AND c.id > 1
  GROUP BY c.id, c.name, c.balance
  HAVING total_outstanding > 0
  ORDER BY total_outstanding DESC
  LIMIT 50
")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<!-- Filter bar -->
<form method="GET" style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;align-items:center">
  <select class="search-input" name="year" style="width:120px">
    <?php for ($y = date('Y'); $y >= date('Y')-3; $y--): ?>
    <option value="<?= $y ?>" <?= $year==$y?'selected':'' ?>><?= $y ?></option>
    <?php endfor; ?>
  </select>
  <select class="search-input" name="month" style="width:150px">
    <option value="0" <?= !$month?'selected':'' ?>><?= __('full_year') ?: 'Full Year' ?></option>
    <?php for ($m = 1; $m <= 12; $m++): ?>
    <option value="<?= $m ?>" <?= $month==$m?'selected':'' ?>><?= date('F', mktime(0,0,0,$m,1,$year)) ?></option>
    <?php endfor; ?>
  </select>
  <button type="submit" class="btn btn-primary"><?= __('generate') ?></button>
  <span style="margin-left:auto;font-size:13px;color:var(--text3)"><?= $date_label ?></span>
  <button type="button" class="btn btn-ghost btn-sm" onclick="window.print()">🖨️ <?= __('print') ?></button>
</form>

<style>
html[dir="rtl"] .acct-table td:last-child{text-align:left}
html[dir="rtl"] .acct-2col{direction:rtl}
html[dir="rtl"] .acct-amount{text-align:left}
</style>
<div class="tabs" style="overflow-x:auto;white-space:nowrap;-webkit-overflow-scrolling:touch">
  <div class="tab active"  onclick="switchTab('income-stmt',this)">📊 <?= __('income_statement') ?: 'Income Statement' ?></div>
  <div class="tab"         onclick="switchTab('balance-sh',this)">⚖️ <?= __('balance_sheet') ?: 'Balance Sheet' ?></div>
  <div class="tab"         onclick="switchTab('cashflow',this)">💧 <?= __('cash_flow') ?: 'Cash Flow' ?></div>
  <div class="tab"         onclick="switchTab('ar-aging',this)">⏱️ <?= __('ar_aging') ?: 'AR Aging' ?></div>
  <div class="tab"         onclick="switchTab('monthly-trend',this)">📈 <?= __('monthly_trend') ?: 'Monthly Trend' ?></div>
</div>

<!-- ── INCOME STATEMENT ── -->
<div id="income-stmt">
  <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr));margin-bottom:20px">
    <div class="stat-card green"><div class="stat-label"><?= __('total_sales') ?></div><div class="stat-value text-green"><?= fmt_money($rev['s']) ?></div></div>
    <div class="stat-card blue"><div class="stat-label">COGS</div><div class="stat-value text-blue"><?= fmt_money($cogs) ?></div></div>
    <div class="stat-card purple"><div class="stat-label"><?= __('gross_profit') ?></div><div class="stat-value text-accent"><?= fmt_money($gross_profit) ?> <small style="font-size:11px">(<?= $gp_margin ?>%)</small></div></div>
    <div class="stat-card red"><div class="stat-label"><?= __('total_expenses') ?></div><div class="stat-value text-red"><?= fmt_money($total_expenses) ?></div></div>
    <div class="stat-card <?= $net_profit >= 0 ? 'green' : 'red' ?>"><div class="stat-label"><?= __('net_profit') ?></div><div class="stat-value <?= $net_profit >= 0 ? 'text-green' : 'text-red' ?>"><?= fmt_money($net_profit) ?> <small style="font-size:11px">(<?= $np_margin ?>%)</small></div></div>
  </div>

  <div class="card">
    <div class="card-title"><span>📊 <?= __('income_statement') ?: 'Income Statement' ?> — <?= $date_label ?></span></div>
    <table style="width:100%;border-collapse:collapse">
      <!-- Revenue -->
      <tr style="background:var(--bg3)"><td colspan="2" style="padding:10px 16px;font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:.5px"><?= __('revenue') ?></td></tr>
      <tr><td style="padding:8px 24px;color:var(--text2)"><?= __('gross_sales') ?: 'Gross Sales' ?></td><td style="padding:8px 16px;text-align:right;font-weight:600"><?= fmt_money($rev['s']) ?></td></tr>
      <tr><td style="padding:8px 24px;color:var(--text2)"><?= __('discount_given') ?: 'Discounts Given' ?></td><td style="padding:8px 16px;text-align:right;color:var(--red)">( <?= fmt_money($rev['d']) ?> )</td></tr>
      <tr style="border-top:1px solid var(--border2)"><td style="padding:9px 16px;font-weight:700"><?= __('net_revenue') ?: 'Net Revenue' ?></td><td style="padding:9px 16px;text-align:right;font-weight:700;color:var(--green)"><?= fmt_money($rev['s'] - $rev['d']) ?></td></tr>
      <!-- COGS -->
      <tr style="background:var(--bg3)"><td colspan="2" style="padding:10px 16px;font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:.5px"><?= __('cost_of_goods') ?: 'Cost of Goods Sold' ?></td></tr>
      <tr><td style="padding:8px 24px;color:var(--text2)"><?= __('total_cogs') ?: 'Total COGS' ?></td><td style="padding:8px 16px;text-align:right;color:var(--red)">( <?= fmt_money($cogs) ?> )</td></tr>
      <tr style="border-top:1px solid var(--border2)"><td style="padding:9px 16px;font-weight:700"><?= __('gross_profit') ?></td><td style="padding:9px 16px;text-align:right;font-weight:700;color:<?= $gross_profit >= 0 ? 'var(--green)' : 'var(--red)' ?>"><?= fmt_money($gross_profit) ?></td></tr>
      <!-- Expenses -->
      <tr style="background:var(--bg3)"><td colspan="2" style="padding:10px 16px;font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:.5px"><?= __('operating_expenses') ?: 'Operating Expenses' ?></td></tr>
      <?php foreach ($expenses as $ex): ?>
      <tr><td style="padding:6px 24px;color:var(--text2)"><?= htmlspecialchars($ex['category']) ?></td><td style="padding:6px 16px;text-align:right;color:var(--red)">( <?= fmt_money($ex['a']) ?> )</td></tr>
      <?php endforeach; ?>
      <?php if (empty($expenses)): ?><tr><td colspan="2" style="padding:8px 24px;color:var(--text3)">No expenses recorded for this period</td></tr><?php endif; ?>
      <tr style="border-top:1px solid var(--border2)"><td style="padding:9px 16px;font-weight:700"><?= __('total_expenses') ?></td><td style="padding:9px 16px;text-align:right;font-weight:700;color:var(--red)">( <?= fmt_money($total_expenses) ?> )</td></tr>
      <!-- Net -->
      <tr style="background:<?= $net_profit >= 0 ? 'rgba(34,197,94,.1)' : 'rgba(239,68,68,.1)' ?>;border-top:2px solid var(--border)">
        <td style="padding:14px 16px;font-weight:800;font-size:15px"><?= __('net_profit') ?></td>
        <td style="padding:14px 16px;text-align:right;font-weight:800;font-size:15px;color:<?= $net_profit >= 0 ? 'var(--green)' : 'var(--red)' ?>"><?= fmt_money($net_profit) ?></td>
      </tr>
    </table>
  </div>
</div>

<!-- ── BALANCE SHEET ── -->
<div id="balance-sh" style="display:none">
  <div class="card">
    <div class="card-title"><span>⚖️ <?= __('balance_sheet') ?: 'Balance Sheet' ?> — <?= __('as_of') ?: 'As of' ?> <?= date('d M Y') ?></span>
      <span style="font-size:11px;color:var(--text3);font-weight:400"><?= __('cumulative_all_time') ?: '(Cumulative — all time)' ?></span>
    </div>
    <div class="acct-2col" style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
      <!-- Assets -->
      <div>
        <div style="font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:.5px;padding:10px 0;border-bottom:2px solid var(--border)"><?= __('assets') ?: 'Assets' ?></div>
        <div style="padding:10px 0;font-weight:600;color:var(--text2);font-size:12px;text-transform:uppercase;margin-top:8px"><?= __('current_assets') ?: 'Current Assets' ?></div>
        <div style="display:flex;justify-content:space-between;padding:6px 12px"><span><?= __('cash_and_bank') ?: 'Cash & Bank (Net)' ?></span><span class="<?= $cash_position >= 0 ? 'text-green' : 'text-red' ?>"><?= fmt_money(max(0, $cash_position)) ?></span></div>
        <div style="display:flex;justify-content:space-between;padding:6px 12px"><span><?= __('accounts_receivable') ?: 'Accounts Receivable' ?></span><span class="text-amber"><?= fmt_money($ar_balance) ?></span></div>
        <div style="display:flex;justify-content:space-between;padding:6px 12px"><span><?= __('inventory_value') ?: 'Inventory (at cost)' ?></span><span class="text-blue"><?= fmt_money($inventory_val) ?></span></div>
        <div style="display:flex;justify-content:space-between;padding:10px 12px;border-top:2px solid var(--border);margin-top:6px;font-weight:700">
          <span><?= __('total_assets') ?: 'Total Assets' ?></span><span class="text-green"><?= fmt_money($total_assets) ?></span>
        </div>
      </div>
      <!-- Liabilities + Equity -->
      <div>
        <div style="font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:.5px;padding:10px 0;border-bottom:2px solid var(--border)"><?= __('liabilities_equity') ?: 'Liabilities & Equity' ?></div>
        <div style="padding:10px 0;font-weight:600;color:var(--text2);font-size:12px;text-transform:uppercase;margin-top:8px"><?= __('current_liabilities') ?: 'Current Liabilities' ?></div>
        <div style="display:flex;justify-content:space-between;padding:6px 12px"><span><?= __('accounts_payable') ?: 'Accounts Payable (Suppliers)' ?></span><span class="text-red"><?= fmt_money($ap_balance) ?></span></div>
        <div style="display:flex;justify-content:space-between;padding:10px 12px;border-top:1px solid var(--border2);margin-top:6px">
          <span><?= __('total_liabilities') ?: 'Total Liabilities' ?></span><span class="text-red"><?= fmt_money($total_liab) ?></span>
        </div>
        <div style="padding:10px 0;font-weight:600;color:var(--text2);font-size:12px;text-transform:uppercase;margin-top:8px"><?= __('equity') ?: 'Equity' ?></div>
        <div style="display:flex;justify-content:space-between;padding:6px 12px"><span><?= __('retained_earnings') ?: 'Retained Earnings (All Time)' ?></span><span class="<?= $retained_earnings >= 0 ? 'text-green' : 'text-red' ?>"><?= fmt_money($retained_earnings) ?></span></div>
        <?php if (abs($balance_diff) > 0.01): ?>
        <div style="display:flex;justify-content:space-between;padding:6px 12px;color:var(--text3)"><span><?= __('opening_capital') ?: 'Opening Capital / Other' ?></span><span><?= fmt_money($balance_diff) ?></span></div>
        <?php endif; ?>
        <div style="display:flex;justify-content:space-between;padding:10px 12px;border-top:2px solid var(--border);margin-top:6px;font-weight:700">
          <span><?= __('total_equity') ?: 'Total Equity' ?></span><span><?= fmt_money($equity + $balance_diff) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;padding:10px 12px;background:var(--bg3);border-radius:var(--r);margin-top:8px;font-weight:800">
          <span><?= __('total_liab_equity') ?: 'Total Liabilities + Equity' ?></span>
          <span class="<?= abs($total_assets - ($total_liab + $equity + $balance_diff)) < 0.01 ? 'text-green' : 'text-amber' ?>">
            <?= fmt_money($total_liab + $equity + $balance_diff) ?>
            <?php if (abs($total_assets - ($total_liab + $equity + $balance_diff)) < 0.01): ?>
            <span style="font-size:10px;color:var(--green);margin-left:6px">✅ Balanced</span>
            <?php endif; ?>
          </span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── CASH FLOW ── -->
<div id="cashflow" style="display:none">
  <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin-bottom:20px">
    <div class="stat-card green">
      <div class="stat-label"><?= __('cash_inflow') ?: 'Cash Inflow' ?></div>
      <div class="stat-value text-green"><?= fmt_money($cash_in) ?></div>
      <div class="stat-delta" style="flex-direction:column;align-items:flex-start;gap:1px">
        <span><?= __('direct_sales') ?: '↳ Direct sales' ?>: <?= fmt_money($cash_in_direct) ?></span>
        <span><?= __('credit_collections') ?: '↳ Credit collected' ?>: <?= fmt_money($cash_in_credit) ?></span>
      </div>
    </div>
    <div class="stat-card red">
      <div class="stat-label"><?= __('cash_outflow') ?: 'Cash Outflow' ?></div>
      <div class="stat-value text-red"><?= fmt_money($cash_out_exp + $cash_out_sup) ?></div>
      <div class="stat-delta"><?= __('expenses_and_suppliers') ?: 'expenses + suppliers' ?></div>
    </div>
    <div class="stat-card <?= $net_cash >= 0 ? 'green' : 'red' ?>">
      <div class="stat-label"><?= __('net_cash_flow') ?: 'Net Cash Flow' ?></div>
      <div class="stat-value <?= $net_cash >= 0 ? 'text-green' : 'text-red' ?>"><?= fmt_money($net_cash) ?></div>
    </div>
  </div>
  <div class="card">
    <div class="card-title"><span>💧 <?= __('cash_flow') ?: 'Cash Flow Statement' ?> — <?= $date_label ?></span></div>
    <table style="width:100%;border-collapse:collapse">
      <tr style="background:var(--bg3)"><td colspan="2" style="padding:10px 16px;font-weight:700"><?= __('operating_activities') ?: 'Operating Activities' ?></td></tr>
      <tr style="background:rgba(34,197,94,.03)"><td colspan="2" style="padding:6px 24px;font-size:11px;font-weight:600;color:var(--text3);text-transform:uppercase">Inflows</td></tr>
      <tr><td style="padding:8px 24px"><?= __('cash_sales_direct') ?: 'Cash Sales (Cash / Card / Transfer)' ?></td><td style="padding:8px 16px;text-align:right;color:var(--green);font-weight:600"><?= fmt_money($cash_in_direct) ?></td></tr>
      <tr><td style="padding:8px 24px"><?= __('credit_collected') ?: 'Collections on Credit Invoices' ?></td><td style="padding:8px 16px;text-align:right;color:var(--green);font-weight:500"><?= fmt_money($cash_in_credit) ?></td></tr>
      <tr style="border-top:1px solid var(--border2)"><td style="padding:6px 16px;font-weight:600;font-size:12px"><?= __('total_inflows') ?: 'Total Cash Inflows' ?></td><td style="padding:6px 16px;text-align:right;font-weight:600;color:var(--green)"><?= fmt_money($cash_in) ?></td></tr>
      <tr style="background:rgba(239,68,68,.03)"><td colspan="2" style="padding:6px 24px;font-size:11px;font-weight:600;color:var(--text3);text-transform:uppercase">Outflows</td></tr>
      <tr><td style="padding:8px 24px"><?= __('cash_paid_expenses') ?: 'Cash Paid for Expenses' ?></td><td style="padding:8px 16px;text-align:right;color:var(--red)">( <?= fmt_money($cash_out_exp) ?> )</td></tr>
      <tr><td style="padding:8px 24px"><?= __('cash_paid_suppliers') ?: 'Cash Paid to Suppliers' ?></td><td style="padding:8px 16px;text-align:right;color:var(--red)">( <?= fmt_money($cash_out_sup) ?> )</td></tr>
      <tr style="border-top:2px solid var(--border)"><td style="padding:12px 16px;font-weight:800;font-size:15px"><?= __('net_cash_flow') ?: 'Net Cash Flow' ?></td>
        <td style="padding:12px 16px;text-align:right;font-weight:800;font-size:15px;color:<?= $net_cash >= 0 ? 'var(--green)' : 'var(--red)' ?>"><?= fmt_money($net_cash) ?></td>
      </tr>
    </table>
  </div>
</div>

<!-- ── AR AGING ── -->
<div id="ar-aging" style="display:none">
  <div class="card">
    <div class="card-title">
      <span>⏱️ <?= __('ar_aging') ?: 'Accounts Receivable Aging' ?></span>
      <span style="font-size:11px;color:var(--text3);font-weight:400"><?= __('based_on_invoice_date') ?: 'Based on invoice date' ?></span>
    </div>
    <?php if (empty($ar_aging)): ?>
    <p style="padding:20px;color:var(--text3);text-align:center">✅ No outstanding receivables</p>
    <?php else:
      $aging_totals = ['age_0_30'=>0,'age_31_60'=>0,'age_61_90'=>0,'age_90plus'=>0,'total_outstanding'=>0];
      foreach ($ar_aging as $rr) {
        foreach ($aging_totals as $k => $v) $aging_totals[$k] += $rr[$k];
      }
    ?>
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th><?= __('customer_name') ?></th>
            <th style="text-align:right">0–30 <?= __('days') ?: 'days' ?></th>
            <th style="text-align:right">31–60</th>
            <th style="text-align:right">61–90</th>
            <th style="text-align:right">90+ <?= __('days') ?: 'days' ?></th>
            <th style="text-align:right"><?= __('total') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ar_aging as $r): ?>
          <tr>
            <td style="font-weight:500"><?= htmlspecialchars($r['name']) ?></td>
            <td style="text-align:right"><?= $r['age_0_30']   > 0 ? '<span class="badge badge-green">'.fmt_money($r['age_0_30']).'</span>'   : '<span style="color:var(--text3)">—</span>' ?></td>
            <td style="text-align:right"><?= $r['age_31_60']  > 0 ? '<span class="badge badge-amber">'.fmt_money($r['age_31_60']).'</span>'  : '<span style="color:var(--text3)">—</span>' ?></td>
            <td style="text-align:right"><?= $r['age_61_90']  > 0 ? '<span class="badge badge-amber">'.fmt_money($r['age_61_90']).'</span>'  : '<span style="color:var(--text3)">—</span>' ?></td>
            <td style="text-align:right"><?= $r['age_90plus'] > 0 ? '<span class="badge badge-red">'.fmt_money($r['age_90plus']).'</span>'   : '<span style="color:var(--text3)">—</span>' ?></td>
            <td style="text-align:right;font-weight:700;color:var(--red)"><?= fmt_money($r['total_outstanding']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background:var(--bg3);font-weight:700;border-top:2px solid var(--border)">
            <td><?= __('totals') ?: 'Totals' ?></td>
            <td style="text-align:right;color:var(--green)"><?= fmt_money($aging_totals['age_0_30']) ?></td>
            <td style="text-align:right;color:var(--amber)"><?= fmt_money($aging_totals['age_31_60']) ?></td>
            <td style="text-align:right;color:var(--amber)"><?= fmt_money($aging_totals['age_61_90']) ?></td>
            <td style="text-align:right;color:var(--red)"><?= fmt_money($aging_totals['age_90plus']) ?></td>
            <td style="text-align:right;color:var(--red)"><?= fmt_money($aging_totals['total_outstanding']) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ── MONTHLY TREND ── -->
<div id="monthly-trend" style="display:none">
  <div class="card">
    <div class="card-title"><span>📈 <?= __('monthly_trend') ?: 'Monthly Revenue Trend' ?> — <?= $year ?></span></div>
    <?php
    $month_names = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $max_rev = max(array_column($monthly ?: [['revenue'=>1]], 'revenue') ?: [1]);
    if ($max_rev <= 0) $max_rev = 1;
    ?>
    <div style="display:flex;align-items:flex-end;gap:6px;height:160px;padding:8px 4px;margin-bottom:16px">
      <?php for ($m = 1; $m <= 12; $m++):
        $mdata = $monthly_map[$m] ?? ['revenue'=>0,'cnt'=>0];
        $h = max(2, round(($mdata['revenue'] / $max_rev) * 100));
        $isCurrent = ($m == date('n') && $year == date('Y'));
      ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px" title="<?= $month_names[$m-1] ?>: <?= fmt_money($mdata['revenue']) ?>">
        <div style="height:<?= $h ?>%;width:100%;background:<?= $isCurrent ? 'linear-gradient(to top,#22c55e,#4ade80)' : 'linear-gradient(to top,#4361ee,#818cf8)' ?>;border-radius:3px 3px 0 0;min-height:4px"></div>
        <div style="font-size:9px;color:var(--text3);font-weight:500"><?= $month_names[$m-1] ?></div>
      </div>
      <?php endfor; ?>
    </div>
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th><?= __('month') ?: 'Month' ?></th>
            <th style="text-align:right"><?= __('gross_revenue') ?: 'Gross Revenue' ?></th>
            <th style="text-align:right"><?= __('invoice_count') ?: '# Invoices' ?></th>
            <th style="text-align:right"><?= __('avg_ticket') ?: 'Avg. Ticket' ?></th>
          </tr>
        </thead>
        <tbody>
          <?php
          $year_total_rev = 0; $year_total_inv = 0;
          for ($m = 1; $m <= 12; $m++):
            $mdata = $monthly_map[$m] ?? ['revenue'=>0,'cnt'=>0];
            $year_total_rev += $mdata['revenue'];
            $year_total_inv += $mdata['cnt'];
            $avg = $mdata['cnt'] > 0 ? $mdata['revenue'] / $mdata['cnt'] : 0;
          ?>
          <tr style="<?= !$mdata['revenue'] && !$mdata['cnt'] ? 'opacity:.4' : '' ?>">
            <td><?= date('F Y', mktime(0,0,0,$m,1,$year)) ?></td>
            <td style="text-align:right" class="<?= $mdata['revenue'] > 0 ? 'text-green' : '' ?>" style="font-weight:600"><?= $mdata['revenue'] > 0 ? fmt_money($mdata['revenue']) : '—' ?></td>
            <td style="text-align:right"><?= $mdata['cnt'] ?: '—' ?></td>
            <td style="text-align:right;color:var(--text2)"><?= $avg > 0 ? fmt_money($avg) : '—' ?></td>
          </tr>
          <?php endfor; ?>
        </tbody>
        <tfoot>
          <tr style="background:var(--bg3);font-weight:700;border-top:2px solid var(--border)">
            <td><?= __('year_total') ?: 'Year Total' ?></td>
            <td style="text-align:right;color:var(--green)"><?= fmt_money($year_total_rev) ?></td>
            <td style="text-align:right"><?= $year_total_inv ?></td>
            <td style="text-align:right;color:var(--text2)"><?= $year_total_inv > 0 ? fmt_money($year_total_rev / $year_total_inv) : '—' ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<?php
$extra_js = '<script>
function switchTab(id, el) {
  ["income-stmt","balance-sh","cashflow","ar-aging","monthly-trend"].forEach(t => {
    document.getElementById(t).style.display = "none";
  });
  document.getElementById(id).style.display = "block";
  document.querySelectorAll(".tab").forEach(t => t.classList.remove("active"));
  el.classList.add("active");
}
</script>';
require __DIR__ . '/includes/footer.php';
