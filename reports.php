<?php
require_once __DIR__ . '/includes/config.php';
require_login();
$current_page = 'reports';
$page_title   = __('sales_reports');
$db = db();

$from     = $_GET['from']      ?? date('Y-m-01');
$to       = $_GET['to']        ?? date('Y-m-d');
$branch_id = (int)($_GET['branch_id'] ?? 0);
$page_num = max(1, (int)($_GET['p'] ?? 1));
$per_page = 20;
$offset   = ($page_num - 1) * $per_page;

$bwhere = $branch_id ? "AND i.branch_id = $branch_id" : "";

// Handle delete invoice
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_invoice') {
    $inv_id = (int)$_POST['invoice_id'];
    $inv_check = $db->prepare("SELECT * FROM invoices WHERE id=?");
    $inv_check->execute([$inv_id]);
    $inv_data = $inv_check->fetch();
    if ($inv_data) {
        // Return stock for all items
        $items_q = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id=?");
        $items_q->execute([$inv_id]);
        foreach ($items_q->fetchAll() as $ii) {
            $db->prepare("UPDATE stock SET qty = qty + ? WHERE product_id = ? AND branch_id = ?")->execute([$ii['qty'], $ii['product_id'], $inv_data['branch_id']]);
        }
        $db->prepare("DELETE FROM invoice_items WHERE invoice_id=?")->execute([$inv_id]);
        $db->prepare("DELETE FROM invoices WHERE id=?")->execute([$inv_id]);
    }
    header('Location: ' . BASE . '/reports.php?from=' . $from . '&to=' . $to . '&success=' . urlencode('Invoice deleted'));
    exit;
}

// Summary
$summary = $db->query("
    SELECT
        COALESCE(SUM(i.total),0) as total_sales,
        COALESCE(SUM(i.total - i.discount),0) as net_sales,
        COALESCE(SUM(i.discount),0) as total_discount,
        COUNT(i.id) as invoice_count,
        COALESCE(AVG(i.total),0) as avg_ticket
    FROM invoices i
    WHERE DATE(i.created_at) BETWEEN '$from' AND '$to' $bwhere
")->fetch();

// Gross profit
$bwhere_inv = $branch_id ? "AND inv.branch_id = $branch_id" : "";
$cost_stmt = $db->query("
    SELECT COALESCE(SUM(ii.qty * p.cost_price),0) as total_cost
    FROM invoice_items ii
    JOIN invoices inv ON inv.id = ii.invoice_id
    JOIN products p ON p.id = ii.product_id
    WHERE DATE(inv.created_at) BETWEEN '$from' AND '$to' $bwhere_inv
");
$total_cost = $cost_stmt->fetch()['total_cost'];
$gross_profit = $summary['net_sales'] - $total_cost;

// Category breakdown
$cat_sales = $db->query("
    SELECT c.emoji, c.name as cat, SUM(ii.qty) as units, SUM(ii.total) as revenue,
           SUM(ii.qty * p.cost_price) as cost
    FROM invoice_items ii
    JOIN invoices inv ON inv.id = ii.invoice_id
    JOIN products p ON p.id = ii.product_id
    JOIN categories c ON c.id = p.category_id
    WHERE DATE(inv.created_at) BETWEEN '$from' AND '$to' $bwhere_inv
    GROUP BY c.id ORDER BY revenue DESC
")->fetchAll();

// Daily trend
$daily = $db->query("
    SELECT DATE(i.created_at) as d, SUM(i.total) as s, COUNT(*) as c
    FROM invoices i WHERE DATE(i.created_at) BETWEEN '$from' AND '$to' $bwhere
    GROUP BY DATE(i.created_at) ORDER BY d
")->fetchAll();

$branches = $db->query("SELECT id, name FROM branches WHERE is_active=1")->fetchAll();
$daily_vals = array_column($daily, 's');
$max_daily = !empty($daily_vals) ? max($daily_vals) : 1;
if ($max_daily <= 0) $max_daily = 1;

require __DIR__ . '/includes/header.php';
?>

<div class="tabs">
  <div class="tab active" onclick="switchTab('sales-rpt',this)"><?= __('sales_reports') ?></div>
  <div class="tab" onclick="switchTab('cat-rpt',this)"><?= __('category_breakdown') ?></div>
  <div class="tab" onclick="switchTab('daily-rpt',this)"><?= __('daily_trend') ?></div>
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
    <a href="<?= BASE ?>/api/export_report.php?from=<?= $from ?>&to=<?= $to ?>&branch_id=<?= $branch_id ?>&format=excel" class="btn btn-ghost btn-sm">📊 Excel</a>
    <button type="button" class="btn btn-ghost btn-sm" onclick="printReport()">🖨️ Print</button>
  </div>
</form>

<!-- SALES SUMMARY -->
<div id="sales-rpt">
  <div class="stats-grid" style="grid-template-columns:repeat(4,1fr)">
    <div class="stat-card green"><div class="stat-label"><?= __('total_sales') ?></div><div class="stat-value text-green"><?= fmt_money($summary['total_sales']) ?></div></div>
    <div class="stat-card purple"><div class="stat-label"><?= __('gross_profit') ?></div><div class="stat-value text-accent"><?= fmt_money($gross_profit) ?></div></div>
    <div class="stat-card blue"><div class="stat-label"><?= __('total_invoices') ?></div><div class="stat-value text-blue"><?= number_format($summary['invoice_count']) ?></div></div>
    <div class="stat-card amber"><div class="stat-label"><?= __('avg_ticket') ?></div><div class="stat-value text-amber"><?= fmt_money($summary['avg_ticket']) ?></div></div>
  </div>

  <!-- Invoice list -->
  <div class="card">
    <div class="card-title"><span>🧾 Invoice List (<?= date('d M', strtotime($from)) ?> – <?= date('d M Y', strtotime($to)) ?>)</span></div>
    <div class="tbl-wrap">
      <table id="invoice-table">
        <thead><tr><th><?= __('invoice') ?> #</th><th><?= __('customer_name') ?></th><th><?= __('nav_branches') ?></th><th><?= __('date') ?></th><th><?= __('payment_mode') ?></th><th><?= __('total') ?></th><th><?= __('paid') ?></th><th><?= __('status') ?></th><th><?= __('actions') ?></th></tr></thead>
        <tbody>
          <?php
          $total_inv = $db->query("SELECT COUNT(*) FROM invoices i WHERE DATE(i.created_at) BETWEEN '$from' AND '$to' $bwhere")->fetchColumn();
          $total_pages = ceil($total_inv / $per_page);
          $invoices = $db->query("
              SELECT i.*, c.name as cust_name, b.name as branch_name
              FROM invoices i JOIN customers c ON c.id=i.customer_id JOIN branches b ON b.id=i.branch_id
              WHERE DATE(i.created_at) BETWEEN '$from' AND '$to' $bwhere ORDER BY i.created_at DESC LIMIT $per_page OFFSET $offset
          ")->fetchAll();
          foreach ($invoices as $inv):
            $badge = ['paid'=>'badge-green','credit'=>'badge-amber','partial'=>'badge-blue','refunded'=>'badge-red'][$inv['status']] ?? 'badge-gray';
          ?>
          <tr>
            <td class="font-mono" style="font-size:11px"><?= htmlspecialchars($inv['invoice_number']) ?></td>
            <td><?= htmlspecialchars($inv['cust_name']) ?></td>
            <td><?= htmlspecialchars($inv['branch_name']) ?></td>
            <td style="font-size:12px;color:var(--text3)"><?= date('d M Y H:i', strtotime($inv['created_at'])) ?></td>
            <td><?= strtoupper($inv['payment_mode']) ?></td>
            <td class="text-green" style="font-weight:600"><?= fmt_money($inv['total']) ?></td>
            <td style="font-weight:500;color:<?= $inv['paid_amount'] >= $inv['total'] ? 'var(--green)' : 'var(--amber)' ?>"><?= fmt_money($inv['paid_amount']) ?></td>
            <td><span class="badge <?= $badge ?>"><?= ucfirst($inv['status']) ?></span></td>
            <td>
              <div style="display:flex;gap:4px">
                <button class="btn btn-ghost btn-sm" onclick="window.open('<?= BASE ?>/invoice.php?id=<?= $inv['id'] ?>', '_blank')">🖨️</button>
                <button class="btn btn-ghost btn-sm" onclick='editInvoice(<?= json_encode($inv, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>✏️</button>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete this invoice and return stock? This cannot be undone.')">
                  <input type="hidden" name="action" value="delete_invoice"><input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                  <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--red)">🗑️</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($invoices)): ?><tr><td colspan="9" style="text-align:center;padding:20px;color:var(--text3)"><?= __('no_data') ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;font-size:12px;color:var(--text3)">
      <span><?= __('showing') ?> <?= count($invoices) ?> <?= __('of') ?> <?= $total_inv ?></span>
      <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?from=<?= $from ?>&to=<?= $to ?>&branch_id=<?= $branch_id ?>&p=<?= $i ?>" class="page-link <?= $i === $page_num ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
      </div>
    </div>
  </div>
</div>

<!-- CATEGORY BREAKDOWN -->
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
          ?>
          <tr>
            <td><?= htmlspecialchars($cs['emoji'] . ' ' . $cs['cat']) ?></td>
            <td><?= number_format($cs['units']) ?></td>
            <td class="text-green" style="font-weight:600"><?= fmt_money($cs['revenue']) ?></td>
            <td class="text-muted"><?= fmt_money($cs['cost']) ?></td>
            <td class="text-green"><?= fmt_money($profit) ?></td>
            <td class="text-green"><?= $margin ?>%</td>
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

<!-- DAILY TREND -->
<div id="daily-rpt" style="display:none">
  <div class="card">
    <div class="card-title"><span>📈 <?= __('daily_trend') ?></span></div>
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
        <div class="form-group"><label class="form-label"><?= __('invoice') ?> #</label><input class="form-input" id="edit-inv-num" disabled style="font-family:var(--mono)"></div>
        <div class="form-row">
          <div class="form-group"><label class="form-label"><?= __('payment_mode') ?></label>
            <select class="form-select" name="payment_mode" id="edit-inv-pay">
              <option value="cash">Cash</option><option value="knet">KNET</option><option value="wamd">WAMD</option><option value="transfer">Transfer</option><option value="credit">Credit</option>
            </select>
          </div>
          <div class="form-group"><label class="form-label"><?= __('status') ?></label>
            <select class="form-select" name="status" id="edit-inv-status">
              <option value="paid">Paid</option><option value="partial">Partial</option><option value="credit">Credit</option><option value="refunded">Refunded</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label class="form-label"><?= __('notes') ?></label><input class="form-input" name="notes" id="edit-inv-notes"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('inv-edit-modal')"><?= __('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= __('save') ?></button>
      </div>
    </form>
  </div>
</div>

<?php
$print_date_range = date('d M Y', strtotime($from)) . ' – ' . date('d M Y', strtotime($to));
$print_summary = 'Total: ' . fmt_money($summary['total_sales']) . ' | Invoices: ' . $summary['invoice_count'] . ' | Profit: ' . fmt_money($gross_profit);
$extra_js = '<script>
function switchTab(id, el) {
  ["sales-rpt","cat-rpt","daily-rpt"].forEach(t => document.getElementById(t).style.display="none");
  document.getElementById(id).style.display="block";
  document.querySelectorAll(".tab").forEach(t=>t.classList.remove("active"));
  el.classList.add("active");
}

function editInvoice(inv) {
  document.getElementById("edit-inv-id").value = inv.id;
  document.getElementById("edit-inv-num").value = inv.invoice_number;
  document.getElementById("edit-inv-pay").value = inv.payment_mode;
  document.getElementById("edit-inv-status").value = inv.status;
  document.getElementById("edit-inv-notes").value = inv.notes || "";
  openModal("inv-edit-modal");
}

function printReport() {
  const table = document.getElementById("invoice-table");
  const win = window.open("","_blank");
  win.document.write("<html><head><title>Sales Report</title>");
  win.document.write("<style>body{font-family:Arial,sans-serif;padding:20px}table{width:100%;border-collapse:collapse;font-size:12px}th,td{border:1px solid #ddd;padding:6px 8px;text-align:left}th{background:#f0f2f5;font-weight:600}.header{text-align:center;margin-bottom:20px}.header h2{margin:0}.header p{color:#666;margin:4px 0}</style>");
  win.document.write("</head><body>");
  win.document.write("<div class=header><h2>RetailPro — Sales Report</h2><p>' . $print_date_range . '</p><p>' . $print_summary . '</p></div>");
  const clone = table.cloneNode(true);
  clone.querySelectorAll("tr").forEach(row => { const cells = row.querySelectorAll("th,td"); if(cells.length>=9) cells[cells.length-1].remove(); });
  win.document.write(clone.outerHTML);
  win.document.write("</body></html>");
  win.document.close();
  win.print();
}
</script>';
require __DIR__ . '/includes/footer.php'; ?>
