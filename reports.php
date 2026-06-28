<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$current_page = 'reports';
$page_title   = __('sales_reports');
$db = db();

// ── Sanitize date inputs — prevent SQL injection ──
$from = (isset($_GET['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'])) ? $_GET['from'] : date('Y-m-01');
$to   = (isset($_GET['to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']))   ? $_GET['to']   : date('Y-m-d');
$branch_id = (int)($_GET['branch_id'] ?? 0);
$page_num  = max(1, (int)($_GET['p'] ?? 1));
$per_page  = 20;
$offset    = ($page_num - 1) * $per_page;

// ── Handle delete invoice — with transaction & stock return ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_invoice') {
    require_role('super_admin', 'manager');
    $inv_id = (int)$_POST['invoice_id'];
    $db->beginTransaction();
    try {
        $inv_check = $db->prepare("SELECT * FROM invoices WHERE id=? FOR UPDATE");
        $inv_check->execute([$inv_id]);
        $inv_data = $inv_check->fetch();
        if ($inv_data) {
            // Return stock for all items
            $items_q = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id=?");
            $items_q->execute([$inv_id]);
            $items = $items_q->fetchAll();
            foreach ($items as $ii) {
                $db->prepare("UPDATE stock SET qty = qty + ? WHERE product_id = ? AND branch_id = ?")
                   ->execute([$ii['qty'], $ii['product_id'], $inv_data['branch_id']]);
                $db->prepare("INSERT INTO stock_movements (product_id,branch_id,type,qty,reference,notes,user_id)
                              VALUES (?,?,'return',?,?,?,?)")
                   ->execute([$ii['product_id'], $inv_data['branch_id'], $ii['qty'],
                              $inv_data['invoice_number'], 'Invoice deleted', current_user()['id']]);
            }
            // Reverse customer balance if credit/partial
            if (in_array($inv_data['status'], ['credit','partial']) && $inv_data['customer_id'] > 1) {
                $owed = $inv_data['total'] - $inv_data['paid_amount'];
                if ($owed > 0) {
                    $db->prepare("UPDATE customers SET balance = balance + ? WHERE id = ?")
                       ->execute([$owed, $inv_data['customer_id']]);
                }
            }
            $db->prepare("DELETE FROM journal_entries WHERE reference = ?")->execute([$inv_data['invoice_number']]);
            $db->prepare("DELETE FROM invoice_items WHERE invoice_id=?")->execute([$inv_id]);
            $db->prepare("DELETE FROM invoices WHERE id=?")->execute([$inv_id]);
        }
        if ($inv_data) {
            audit_log('delete_invoice', 'invoices', $inv_id, $inv_data, null);
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        header('Location: ' . BASE . '/reports.php?from=' . $from . '&to=' . $to . '&error=' . urlencode($e->getMessage()));
        exit;
    }
    header('Location: ' . BASE . '/reports.php?from=' . $from . '&to=' . $to . '&success=' . urlencode('Invoice deleted and stock restored'));
    exit;
}

// ── Use index-friendly datetime range for all queries ──
$to_next = date('Y-m-d', strtotime($to . ' +1 day'));
$bparam  = $branch_id ?: null;
$bwhere  = $branch_id ? "AND i.branch_id = ?" : "";
$bparam2 = $branch_id ? "AND inv.branch_id = ?" : "";
$p_summary = $branch_id ? [$from, $to_next, $branch_id] : [$from, $to_next];

$summary = $db->prepare("
    SELECT
        COALESCE(SUM(i.total),0)            as total_sales,
        COALESCE(SUM(i.paid_amount),0)      as total_collected,
        COALESCE(SUM(i.total - i.discount),0) as net_sales,
        COALESCE(SUM(i.discount),0)         as total_discount,
        COUNT(i.id)                         as invoice_count,
        COALESCE(AVG(i.total),0)            as avg_ticket,
        COALESCE(SUM(i.total - i.paid_amount),0) as outstanding
    FROM invoices i
    WHERE i.created_at >= ? AND i.created_at < ? $bwhere
");
$summary->execute($p_summary);
$summary = $summary->fetch();

// Gross profit
$p_cost = $branch_id ? [$from, $to_next, $branch_id] : [$from, $to_next];
$cost_stmt = $db->prepare("
    SELECT COALESCE(SUM(ii.qty * p.cost_price),0) as total_cost
    FROM invoice_items ii
    JOIN invoices inv ON inv.id = ii.invoice_id
    JOIN products p   ON p.id  = ii.product_id
    WHERE inv.created_at >= ? AND inv.created_at < ? $bparam2
");
$cost_stmt->execute($p_cost);
$total_cost   = $cost_stmt->fetch()['total_cost'];
$gross_profit = $summary['net_sales'] - $total_cost;
$gp_margin    = $summary['total_sales'] > 0 ? round($gross_profit / $summary['total_sales'] * 100, 1) : 0;

// Expenses in period
$p_exp = $branch_id ? [$from, $to_next, $branch_id] : [$from, $to_next];
$exp_where = $branch_id ? "AND (branch_id = ? OR branch_id IS NULL)" : "";
$period_expenses = $db->prepare("SELECT COALESCE(SUM(amount),0) as e FROM expenses WHERE created_at >= ? AND created_at < ? $exp_where");
$period_expenses->execute($p_exp);
$total_expenses = $period_expenses->fetch()['e'];
$net_profit = $gross_profit - $total_expenses;

// Category breakdown
$cat_sales = $db->prepare("
    SELECT c.emoji, c.name as cat, c.name_ar as cat_ar,
           SUM(ii.qty) as units, SUM(ii.total) as revenue,
           SUM(ii.qty * p.cost_price) as cost
    FROM invoice_items ii
    JOIN invoices inv ON inv.id = ii.invoice_id
    JOIN products p   ON p.id  = ii.product_id
    JOIN categories c ON c.id  = p.category_id
    WHERE inv.created_at >= ? AND inv.created_at < ? $bparam2
    GROUP BY c.id ORDER BY revenue DESC
");
$cat_sales->execute($p_cost);
$cat_sales = $cat_sales->fetchAll();

// Daily trend
$daily = $db->prepare("
    SELECT DATE(i.created_at) as d, SUM(i.total) as s, COUNT(*) as c
    FROM invoices i WHERE i.created_at >= ? AND i.created_at < ? $bwhere
    GROUP BY DATE(i.created_at) ORDER BY d
");
$daily->execute($p_summary);
$daily = $daily->fetchAll();

// Invoice list (paginated)
$p_inv  = $branch_id ? [$from, $to_next, $branch_id] : [$from, $to_next];
$total_inv_stmt = $db->prepare("SELECT COUNT(*) FROM invoices i WHERE i.created_at >= ? AND i.created_at < ? $bwhere");
$total_inv_stmt->execute($p_inv);
$total_inv   = $total_inv_stmt->fetchColumn();
$total_pages = max(1, ceil($total_inv / $per_page));

$inv_stmt = $db->prepare("
    SELECT i.*, c.name as cust_name, COALESCE(c.company_name,'') as cust_company, b.name as branch_name
    FROM invoices i
    JOIN customers c ON c.id = i.customer_id
    JOIN branches b ON b.id = i.branch_id
    WHERE i.created_at >= ? AND i.created_at < ? $bwhere
    ORDER BY i.created_at DESC
    LIMIT $per_page OFFSET $offset
");
$inv_stmt->execute($p_inv);
$invoices = $inv_stmt->fetchAll();

$branches  = $db->query("SELECT id, name FROM branches WHERE is_active=1")->fetchAll();
$daily_vals = array_column($daily, 's');
$max_daily  = !empty($daily_vals) ? max($daily_vals) : 1;
if ($max_daily <= 0) $max_daily = 1;

// Payment mode breakdown — use aliased query to match $bwhere (AND i.branch_id)
$pay_mode_stmt = $db->prepare("
    SELECT i.payment_mode, COUNT(*) as cnt, SUM(i.total) as s
    FROM invoices i WHERE i.created_at >= ? AND i.created_at < ? $bwhere
    GROUP BY i.payment_mode
");
$pay_mode_stmt->execute($p_summary);
$pay_modes = $pay_mode_stmt->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<div class="tabs">
  <div class="tab active"  onclick="switchTab('sales-rpt',this)"><?= __('sales_reports') ?></div>
  <div class="tab"         onclick="switchTab('profit-rpt',this)">💹 <?= __('profit_loss') ?></div>
  <div class="tab"         onclick="switchTab('cat-rpt',this)"><?= __('category_breakdown') ?></div>
  <div class="tab"         onclick="switchTab('daily-rpt',this)"><?= __('daily_trend') ?></div>
  <div class="tab"         onclick="switchTab('paymode-rpt',this)">💳 <?= __('payment_modes') ?></div>
</div>

<!-- FILTERS -->
<form method="GET" style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center">
  <input type="date" class="search-input" name="from" style="width:160px" value="<?= htmlspecialchars($from) ?>">
  <input type="date" class="search-input" name="to"   style="width:160px" value="<?= htmlspecialchars($to) ?>">
  <select class="search-input" name="branch_id" style="width:180px">
    <option value=""><?= __('all') ?></option>
    <?php foreach ($branches as $b): ?>
    <option value="<?= $b['id'] ?>" <?= $branch_id==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn btn-primary"><?= __('generate') ?></button>
  <div style="margin-left:auto;display:flex;gap:6px">
    <a href="<?= BASE ?>/api/export_report.php?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&branch_id=<?= $branch_id ?>&format=excel" class="btn btn-ghost btn-sm">📊 Excel</a>
    <a href="<?= BASE ?>/accounting.php" class="btn btn-ghost btn-sm">📒 <?= __('financial_statements') ?></a>
    <button type="button" class="btn btn-ghost btn-sm" onclick="printReport()">🖨️ <?= __('print') ?></button>
  </div>
</form>

<!-- ── SALES SUMMARY ── -->
<div id="sales-rpt">
  <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(140px,1fr))">
    <div class="stat-card green"><div class="stat-label"><?= __('total_sales') ?></div><div class="stat-value text-green"><?= fmt_money($summary['total_sales']) ?></div></div>
    <div class="stat-card blue"><div class="stat-label"><?= __('total_collected') ?></div><div class="stat-value text-blue"><?= fmt_money($summary['total_collected']) ?></div></div>
    <div class="stat-card amber"><div class="stat-label"><?= __('outstanding') ?></div><div class="stat-value text-amber"><?= fmt_money($summary['outstanding']) ?></div></div>
    <div class="stat-card purple"><div class="stat-label"><?= __('gross_profit') ?></div><div class="stat-value text-accent"><?= fmt_money($gross_profit) ?> <small style="font-size:11px;opacity:.7">(<?= $gp_margin ?>%)</small></div></div>
    <div class="stat-card red"><div class="stat-label"><?= __('total_invoices') ?></div><div class="stat-value text-red"><?= number_format($summary['invoice_count']) ?></div></div>
    <div class="stat-card teal"><div class="stat-label"><?= __('avg_ticket') ?></div><div class="stat-value" style="color:var(--teal)"><?= fmt_money($summary['avg_ticket']) ?></div></div>
  </div>

  <!-- Invoice list -->
  <div class="card">
    <div class="card-title">
      <span>🧾 <?= __('invoice_list') ?> (<?= date('d M', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?>)</span>
    </div>
    <div class="tbl-wrap">
      <table id="invoice-table">
        <thead><tr>
          <th><?= __('invoice') ?> #</th>
          <th><?= __('customer_name') ?></th>
          <th><?= __('nav_branches') ?></th>
          <th><?= __('date') ?></th>
          <th><?= __('payment_mode') ?></th>
          <th><?= __('total') ?></th>
          <th><?= __('paid') ?></th>
          <th><?= __('status') ?></th>
          <th><?= __('actions') ?></th>
        </tr></thead>
        <tbody>
          <?php foreach ($invoices as $inv):
            $badge = ['paid'=>'badge-green','credit'=>'badge-amber','partial'=>'badge-blue','refunded'=>'badge-red'][$inv['status']] ?? 'badge-gray';
          ?>
          <tr>
            <td class="font-mono" style="font-size:11px"><?= htmlspecialchars($inv['invoice_number']) ?></td>
            <td>
                <div style="font-weight:500"><?= htmlspecialchars($inv['cust_name']) ?></div>
                <?php if (!empty($inv['cust_company'])): ?><div style="font-size:11px;color:var(--accent2)">🏢 <?= htmlspecialchars($inv['cust_company']) ?></div><?php endif; ?>
              </td>
            <td><?= htmlspecialchars($inv['branch_name']) ?></td>
            <td style="font-size:12px;color:var(--text3)"><?= date('d M Y H:i', strtotime($inv['created_at'])) ?></td>
            <td><?= strtoupper(htmlspecialchars($inv['payment_mode'])) ?></td>
            <td class="text-green" style="font-weight:600"><?= fmt_money($inv['total']) ?></td>
            <td style="font-weight:500;color:<?= $inv['paid_amount'] >= $inv['total'] ? 'var(--green)' : 'var(--amber)' ?>">
              <?= fmt_money($inv['paid_amount']) ?>
            </td>
            <td><span class="badge <?= $badge ?>"><?= ucfirst($inv['status']) ?></span></td>
            <td>
              <div style="display:flex;gap:4px">
                <button class="btn btn-ghost btn-sm" onclick="window.open('<?= BASE ?>/invoice.php?id=<?= $inv['id'] ?>','_blank')">🖨️</button>
                <button class="btn btn-ghost btn-sm" onclick='editInvoice(<?= json_encode($inv, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>✏️</button>
                <?php if (has_role('super_admin','manager')): ?>
                <form method="POST" style="display:inline" onsubmit="return confirm('<?= addslashes(__('delete_invoice_confirm') ?: 'Delete this invoice and return stock to inventory?') ?>')">
                  <input type="hidden" name="action" value="delete_invoice">
                  <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--red)">🗑️</button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($invoices)): ?>
          <tr><td colspan="9" style="text-align:center;padding:20px;color:var(--text3)"><?= __('no_data') ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;font-size:12px;color:var(--text3)">
      <span><?= __('showing') ?> <?= count($invoices) ?> <?= __('of') ?> <?= $total_inv ?></span>
      <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?from=<?= $from ?>&to=<?= $to ?>&branch_id=<?= $branch_id ?>&p=<?= $i ?>"
           class="page-link <?= $i === $page_num ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
    </div>
  </div>
</div>

<!-- ── PROFIT & LOSS ── -->
<div id="profit-rpt" style="display:none">
  <div class="card">
    <div class="card-title"><span>💹 <?= __('profit_loss') ?> — <?= date('d M Y', strtotime($from)) ?> → <?= date('d M Y', strtotime($to)) ?></span></div>
    <table style="width:100%;border-collapse:collapse">
      <tr style="background:var(--bg3)"><td colspan="2" style="padding:10px 14px;font-weight:700;color:var(--text)"><?= __('revenue') ?></td></tr>
      <tr><td style="padding:8px 20px;color:var(--text2)"><?= __('total_sales') ?></td><td style="padding:8px 14px;text-align:right;font-weight:600;color:var(--green)"><?= fmt_money($summary['total_sales']) ?></td></tr>
      <tr><td style="padding:8px 20px;color:var(--text2)"><?= __('discount_given') ?></td><td style="padding:8px 14px;text-align:right;color:var(--red)">- <?= fmt_money($summary['total_discount']) ?></td></tr>
      <tr style="border-top:2px solid var(--border)"><td style="padding:10px 20px;font-weight:700"><?= __('net_revenue') ?></td><td style="padding:10px 14px;text-align:right;font-weight:700;color:var(--green)"><?= fmt_money($summary['net_sales']) ?></td></tr>

      <tr style="background:var(--bg3)"><td colspan="2" style="padding:10px 14px;font-weight:700;color:var(--text)"><?= __('cost_of_goods') ?></td></tr>
      <tr><td style="padding:8px 20px;color:var(--text2)"><?= __('total_cogs') ?></td><td style="padding:8px 14px;text-align:right;color:var(--red)">- <?= fmt_money($total_cost) ?></td></tr>
      <tr style="border-top:2px solid var(--border)"><td style="padding:10px 20px;font-weight:700"><?= __('gross_profit') ?></td>
        <td style="padding:10px 14px;text-align:right;font-weight:700;color:<?= $gross_profit >= 0 ? 'var(--green)' : 'var(--red)' ?>">
          <?= fmt_money($gross_profit) ?> <small style="opacity:.7">(<?= $gp_margin ?>%)</small>
        </td>
      </tr>

      <tr style="background:var(--bg3)"><td colspan="2" style="padding:10px 14px;font-weight:700;color:var(--text)"><?= __('operating_expenses') ?></td></tr>
      <?php
      $exp_detail = $db->prepare("SELECT category, SUM(amount) as a FROM expenses WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY category ORDER BY a DESC");
      $exp_detail->execute([$from, $to]);
      foreach ($exp_detail->fetchAll() as $exp):
      ?>
      <tr><td style="padding:6px 20px;color:var(--text2)"><?= htmlspecialchars($exp['category']) ?></td><td style="padding:6px 14px;text-align:right;color:var(--red)">- <?= fmt_money($exp['a']) ?></td></tr>
      <?php endforeach; ?>
      <tr style="border-top:2px solid var(--border)"><td style="padding:10px 20px;font-weight:700"><?= __('total_expenses') ?></td><td style="padding:10px 14px;text-align:right;font-weight:700;color:var(--red)">- <?= fmt_money($total_expenses) ?></td></tr>

      <tr style="background:<?= $net_profit >= 0 ? 'rgba(34,197,94,.1)' : 'rgba(239,68,68,.1)' ?>;border-top:2px solid var(--border)">
        <td style="padding:14px 20px;font-weight:800;font-size:16px"><?= __('net_profit') ?></td>
        <td style="padding:14px 14px;text-align:right;font-weight:800;font-size:16px;color:<?= $net_profit >= 0 ? 'var(--green)' : 'var(--red)' ?>"><?= fmt_money($net_profit) ?></td>
      </tr>
    </table>
    <div style="margin-top:12px;font-size:12px;color:var(--text3);padding:0 4px">
      * <?= __('pnl_note') ?: 'Expenses shown are from the selected date range. Unallocated general expenses are included.' ?>
    </div>
  </div>
</div>

<!-- ── CATEGORY BREAKDOWN ── -->
<div id="cat-rpt" style="display:none">
  <div class="card">
    <div class="card-title"><span>📊 <?= __('category_breakdown') ?></span></div>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th><?= __('category') ?></th><th><?= __('units_sold') ?></th><th><?= __('revenue') ?></th><th><?= __('cost') ?></th><th><?= __('profit') ?></th><th><?= __('margin') ?></th><th>%</th></tr></thead>
        <tbody>
          <?php foreach ($cat_sales as $cs):
            $profit = $cs['revenue'] - $cs['cost'];
            $margin = $cs['revenue'] > 0 ? round($profit / $cs['revenue'] * 100) : 0;
            $pct    = $summary['total_sales'] > 0 ? round($cs['revenue'] / $summary['total_sales'] * 100) : 0;
            $cname  = (is_rtl() && $cs['cat_ar']) ? $cs['cat_ar'] : $cs['cat'];
          ?>
          <tr>
            <td><?= htmlspecialchars(($cs['emoji'] ?? '') . ' ' . $cname) ?></td>
            <td><?= number_format($cs['units']) ?></td>
            <td class="text-green" style="font-weight:600"><?= fmt_money($cs['revenue']) ?></td>
            <td class="text-muted"><?= fmt_money($cs['cost']) ?></td>
            <td class="<?= $profit >= 0 ? 'text-green' : 'text-red' ?>"><?= fmt_money($profit) ?></td>
            <td><?= $margin ?>%</td>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <div class="progress" style="width:80px"><div class="progress-fill" style="width:<?= $pct ?>%;background:var(--accent)"></div></div>
                <span><?= $pct ?>%</span>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($cat_sales)): ?><tr><td colspan="7" style="text-align:center;padding:20px;color:var(--text3)"><?= __('no_data') ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ── DAILY TREND ── -->
<div id="daily-rpt" style="display:none">
  <div class="card">
    <div class="card-title"><span>📈 <?= __('daily_trend') ?></span></div>
    <?php if (!empty($daily)): ?>
    <div style="display:flex;align-items:flex-end;gap:4px;height:150px;padding:0 4px;margin-bottom:16px">
      <?php foreach ($daily as $d):
        $h = $max_daily > 0 ? round(($d['s'] / $max_daily) * 100) : 5;
        $isToday = $d['d'] === date('Y-m-d');
      ?>
      <div class="bar-col" style="flex:1" title="<?= $d['d'] ?>: <?= fmt_money($d['s']) ?>">
        <div class="bar" style="height:<?= max($h,2) ?>%;background:<?= $isToday?'linear-gradient(to top,#22c55e,#4ade80)':'linear-gradient(to top,#4361ee,#818cf8)' ?>"></div>
        <div class="bar-label"><?= date('d', strtotime($d['d'])) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th><?= __('date') ?></th><th><?= __('invoice') ?></th><th><?= __('revenue') ?></th></tr></thead>
        <tbody>
          <?php foreach (array_reverse($daily) as $d): ?>
          <tr>
            <td><?= date('l, d M Y', strtotime($d['d'])) ?></td>
            <td><?= $d['c'] ?></td>
            <td class="text-green" style="font-weight:600"><?= fmt_money($d['s']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($daily)): ?><tr><td colspan="3" style="text-align:center;padding:20px;color:var(--text3)"><?= __('no_data') ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ── PAYMENT MODES ── -->
<div id="paymode-rpt" style="display:none">
  <div class="card">
    <div class="card-title"><span>💳 <?= __('payment_modes') ?></span></div>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th><?= __('payment_mode') ?></th><th><?= __('invoice_count') ?></th><th><?= __('total') ?></th><th>%</th></tr></thead>
        <tbody>
          <?php
          $pm_icons = ['cash'=>'💵','knet'=>'💳','wamd'=>'📱','transfer'=>'🏦','credit'=>'💰'];
          foreach ($pay_modes as $pm):
            $pct = $summary['total_sales'] > 0 ? round($pm['s'] / $summary['total_sales'] * 100) : 0;
          ?>
          <tr>
            <td><?= $pm_icons[$pm['payment_mode']] ?? '💱' ?> <?= strtoupper(htmlspecialchars($pm['payment_mode'])) ?></td>
            <td><?= $pm['cnt'] ?></td>
            <td class="text-green" style="font-weight:600"><?= fmt_money($pm['s']) ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <div class="progress" style="width:80px"><div class="progress-fill" style="width:<?= $pct ?>%"></div></div>
                <span><?= $pct ?>%</span>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($pay_modes)): ?><tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text3)"><?= __('no_data') ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- EDIT INVOICE MODAL -->
<div class="modal-backdrop" id="inv-edit-modal">
  <div class="modal" style="width:420px">
    <div class="modal-header">
      <div class="modal-title"><?= __('edit') ?> <?= __('invoice') ?></div>
      <button class="modal-close" onclick="closeModal('inv-edit-modal')">✕</button>
    </div>
    <form method="POST" action="<?= BASE ?>/api/edit_invoice.php">
      <input type="hidden" name="invoice_id" id="edit-inv-id">
      <input type="hidden" name="redirect" value="<?= BASE ?>/reports.php?from=<?= $from ?>&to=<?= $to ?>">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label"><?= __('invoice') ?> #</label>
          <input class="form-input" id="edit-inv-num" disabled style="font-family:var(--mono)">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label"><?= __('payment_mode') ?></label>
            <select class="form-select" name="payment_mode" id="edit-inv-pay">
              <option value="cash">Cash</option><option value="knet">KNET</option>
              <option value="wamd">WAMD</option><option value="transfer">Transfer</option>
              <option value="credit">Credit</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label"><?= __('status') ?></label>
            <select class="form-select" name="status" id="edit-inv-status">
              <option value="paid">Paid</option><option value="partial">Partial</option>
              <option value="credit">Credit</option><option value="refunded">Refunded</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label"><?= __('paid_amount') ?></label>
          <input class="form-input" name="paid_amount" id="edit-inv-paid" type="number" step="0.001" min="0">
        </div>
        <div class="form-group">
          <label class="form-label"><?= __('notes') ?></label>
          <input class="form-input" name="notes" id="edit-inv-notes">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('inv-edit-modal')"><?= __('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= __('save') ?></button>
      </div>
    </form>
  </div>
</div>

<?php ob_start(); ?>
<script>
function switchTab(id, el) {
  ["sales-rpt","profit-rpt","cat-rpt","daily-rpt","paymode-rpt"].forEach(t => {
    document.getElementById(t).style.display = "none";
  });
  document.getElementById(id).style.display = "block";
  document.querySelectorAll(".tab").forEach(t => t.classList.remove("active"));
  el.classList.add("active");
}

function editInvoice(inv) {
  document.getElementById("edit-inv-id").value     = inv.id;
  document.getElementById("edit-inv-num").value    = inv.invoice_number;
  document.getElementById("edit-inv-pay").value    = inv.payment_mode;
  document.getElementById("edit-inv-status").value = inv.status;
  document.getElementById("edit-inv-paid").value   = inv.paid_amount;
  document.getElementById("edit-inv-notes").value  = inv.notes || "";
  openModal("inv-edit-modal");
}

const COMPANY_NAME = "<?= htmlspecialchars(get_setting('company_name', APP_NAME)) ?>";
function printReport() {
  const table = document.getElementById("invoice-table");
  if (!table) return;
  const dateRange  = ' . json_encode($js_date_range) . ';
  const totalSales = ' . json_encode($js_total_sales) . ';
  const invCount   = ' . $js_inv_count . ';
  const netProfit  = ' . json_encode($js_net_profit) . ';
  const win = window.open("", "_blank");
  win.document.write(
    "<html><head><title>Sales Report</title>" +
    "<style>body{font-family:Arial,sans-serif;padding:20px}" +
    "table{width:100%;border-collapse:collapse;font-size:12px}" +
    "th,td{border:1px solid #ddd;padding:6px 8px;text-align:left}" +
    "th{background:#f0f2f5;font-weight:600}" +
    ".header{text-align:center;margin-bottom:20px}" +
    ".header h2{margin:0}.header p{color:#666;margin:4px 0}" +
    "</style></head><body>" +
    "<div class=header><h2>" + COMPANY_NAME + " \u2014 Sales Report</h2>" +
    "<p>" + dateRange + "</p>" +
    "<p>Total: " + totalSales + " | Invoices: " + invCount + " | Net Profit: " + netProfit + "</p>" +
    "</div>"
  );
  const clone = table.cloneNode(true);
  clone.querySelectorAll("tr").forEach(function(row) {
    const cells = row.querySelectorAll("th,td");
    if (cells.length >= 9) cells[cells.length - 1].remove();
  });
  win.document.write(clone.outerHTML);
  win.document.write("</body></html>");
  win.document.close();
  win.print();
}
</script>
<?php
$extra_js = ob_get_clean();
require __DIR__ . '/includes/footer.php';