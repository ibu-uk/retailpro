<?php
require_once __DIR__ . '/../includes/config.php';
require_login();
$db  = db();
$pid = (int)($_GET['pid'] ?? 0);
$sid = (int)($_GET['sid'] ?? 0);
$mon = $_GET['month'] ?? date('Y-m');

[$year, $month] = explode('-', $mon . '-0');
$date_from = "$year-$month-01";
$date_to   = date('Y-m-t', strtotime($date_from));

$where = []; $params = [];
if ($pid) { $where[] = "ii.product_id = ?"; $params[] = $pid; }
if ($sid) { $where[] = "ii.supplier_id = ?"; $params[] = $sid; }
$where[] = "DATE(inv.created_at) BETWEEN ? AND ?";
$params[] = $date_from; $params[] = $date_to;
$wsql = implode(' AND ', $where);

// Sales with batch/supplier info
$rows = $db->prepare("
    SELECT inv.invoice_number, inv.created_at, inv.payment_mode,
           c.name as customer_name,
           p.name as product_name, COALESCE(p.emoji,'📦') as emoji, p.sku,
           ii.qty, ii.unit_price, ii.total,
           COALESCE(sb.batch_number,'—') as batch_number,
           COALESCE(sb.expiry_date, NULL) as expiry_date,
           COALESCE(sb.lot_number,'—') as lot_number,
           COALESCE(s.company,'Unknown') as supplier_name,
           b.name as branch_name,
           COALESCE(sb.cost_price, p.cost_price) as cost_price
    FROM invoice_items ii
    JOIN invoices inv ON inv.id = ii.invoice_id
    JOIN products p ON p.id = ii.product_id
    JOIN customers c ON c.id = inv.customer_id
    JOIN branches b ON b.id = inv.branch_id
    LEFT JOIN stock_batches sb ON sb.id = ii.batch_id
    LEFT JOIN suppliers s ON s.id = COALESCE(ii.supplier_id, sb.supplier_id)
    WHERE $wsql
    ORDER BY inv.created_at DESC
    LIMIT 200
");
$rows->execute($params);
$rows = $rows->fetchAll();

$total_qty     = array_sum(array_column($rows,'qty'));
$total_revenue = array_sum(array_column($rows,'total'));
$total_cost    = array_sum(array_map(fn($r) => $r['qty'] * $r['cost_price'], $rows));
$profit        = $total_revenue - $total_cost;

if (empty($rows)) {
    echo '<div style="text-align:center;padding:30px;color:var(--text3)">No sales found for the selected filters and period</div>';
    exit;
}
?>
<!-- Summary -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px">
  <div style="background:var(--bg3);border-radius:var(--r);padding:12px;border-left:3px solid var(--blue)">
    <div style="font-size:10px;color:var(--text3);text-transform:uppercase">Transactions</div>
    <div style="font-size:20px;font-weight:700;color:var(--blue)"><?= count($rows) ?></div>
  </div>
  <div style="background:var(--bg3);border-radius:var(--r);padding:12px;border-left:3px solid var(--green)">
    <div style="font-size:10px;color:var(--text3);text-transform:uppercase">Units Sold</div>
    <div style="font-size:20px;font-weight:700;color:var(--green)"><?= $total_qty ?></div>
  </div>
  <div style="background:var(--bg3);border-radius:var(--r);padding:12px;border-left:3px solid var(--accent)">
    <div style="font-size:10px;color:var(--text3);text-transform:uppercase">Revenue</div>
    <div style="font-size:18px;font-weight:700;color:var(--accent2)"><?= number_format($total_revenue,3) ?></div>
  </div>
  <div style="background:var(--bg3);border-radius:var(--r);padding:12px;border-left:3px solid var(--teal)">
    <div style="font-size:10px;color:var(--text3);text-transform:uppercase">Profit</div>
    <div style="font-size:18px;font-weight:700;color:var(--teal)"><?= number_format($profit,3) ?></div>
  </div>
</div>

<!-- Traceability table -->
<div style="overflow-x:auto">
<table style="width:100%;border-collapse:collapse;font-size:12px">
  <thead>
    <tr style="background:var(--bg3)">
      <th style="padding:8px 10px;text-align:left;color:var(--text3);font-weight:600;white-space:nowrap">Invoice</th>
      <th style="padding:8px 10px;text-align:left;color:var(--text3);font-weight:600">Date</th>
      <th style="padding:8px 10px;text-align:left;color:var(--text3);font-weight:600">Product</th>
      <th style="padding:8px 10px;text-align:left;color:var(--text3);font-weight:600">Customer</th>
      <th style="padding:8px 10px;text-align:left;color:var(--text3);font-weight:600">Batch #</th>
      <th style="padding:8px 10px;text-align:left;color:var(--text3);font-weight:600">Supplier</th>
      <th style="padding:8px 10px;text-align:left;color:var(--text3);font-weight:600">Lot</th>
      <th style="padding:8px 10px;text-align:left;color:var(--text3);font-weight:600">Expiry</th>
      <th style="padding:8px 10px;text-align:right;color:var(--text3);font-weight:600">Qty</th>
      <th style="padding:8px 10px;text-align:right;color:var(--text3);font-weight:600">Revenue</th>
      <th style="padding:8px 10px;text-align:right;color:var(--text3);font-weight:600">Profit</th>
      <th style="padding:8px 10px;text-align:left;color:var(--text3);font-weight:600">Branch</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $r):
      $row_profit = $r['total'] - ($r['qty'] * $r['cost_price']);
      $exp_ok = !$r['expiry_date'] || strtotime($r['expiry_date']) >= strtotime($r['created_at']);
    ?>
    <tr style="border-bottom:1px solid rgba(255,255,255,.03)">
      <td style="padding:7px 10px;font-family:monospace;font-size:11px;color:var(--accent2)"><?= htmlspecialchars($r['invoice_number']) ?></td>
      <td style="padding:7px 10px;white-space:nowrap;color:var(--text3)"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
      <td style="padding:7px 10px;font-weight:500"><?= htmlspecialchars($r['emoji'].' '.$r['product_name']) ?></td>
      <td style="padding:7px 10px"><?= htmlspecialchars($r['customer_name']) ?></td>
      <td style="padding:7px 10px;font-family:monospace;font-size:10px"><?= htmlspecialchars($r['batch_number']) ?></td>
      <td style="padding:7px 10px;font-weight:500;color:var(--blue)"><?= htmlspecialchars($r['supplier_name']) ?></td>
      <td style="padding:7px 10px;font-size:10px;color:var(--text3)"><?= htmlspecialchars($r['lot_number']) ?></td>
      <td style="padding:7px 10px">
        <?php if ($r['expiry_date']): ?>
        <span style="color:<?= $exp_ok ? 'var(--green)' : 'var(--red)' ?>;font-size:11px">
          <?= date('d M Y', strtotime($r['expiry_date'])) ?>
          <?= !$exp_ok ? ' ⚠️' : '' ?>
        </span>
        <?php else: ?>
        <span style="color:var(--text3)">—</span>
        <?php endif; ?>
      </td>
      <td style="padding:7px 10px;text-align:right;font-weight:600"><?= $r['qty'] ?></td>
      <td style="padding:7px 10px;text-align:right;color:var(--green);font-weight:600"><?= number_format($r['total'],3) ?></td>
      <td style="padding:7px 10px;text-align:right;color:<?= $row_profit >= 0 ? 'var(--teal)' : 'var(--red)' ?>;font-weight:600"><?= number_format($row_profit,3) ?></td>
      <td style="padding:7px 10px;font-size:11px;color:var(--text3)"><?= htmlspecialchars($r['branch_name']) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<div style="margin-top:10px;font-size:11px;color:var(--text3);text-align:right">
  Showing <?= count($rows) ?> records · <?= date('d M Y', strtotime($date_from)) ?> – <?= date('d M Y', strtotime($date_to)) ?>
</div>
