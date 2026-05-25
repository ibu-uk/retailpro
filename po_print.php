<?php
/**
 * po_print.php — Printable Purchase Order document
 * URL: /po_print.php?id=123
 * Shows company profile (top-left) + supplier info (top-right)
 * with a professional A4 layout.
 */
require_once __DIR__ . '/includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) die('PO ID required');

$db = db();

// Load PO with supplier + branch info
$stmt = $db->prepare("
  SELECT po.*,
         s.company as sup_company, COALESCE(s.company_ar,'') as sup_company_ar,
         s.contact_name as sup_contact, s.phone as sup_phone,
         s.email as sup_email, COALESCE(s.address,'') as sup_address,
         COALESCE(s.vat_number,'') as sup_vat,
         b.name as branch_name, b.address as branch_address, b.phone as branch_phone,
         u.name as created_by_name
  FROM purchase_orders po
  JOIN suppliers s ON s.id = po.supplier_id
  JOIN branches  b ON b.id = po.branch_id
  JOIN users     u ON u.id = po.created_by
  WHERE po.id = ?
");
$stmt->execute([$id]);
$po = $stmt->fetch();
if (!$po) die('Purchase order not found');

// Load items
$items_stmt = $db->prepare("
  SELECT poi.qty_ordered, poi.qty_received, poi.unit_cost,
         p.name, p.sku,
         (poi.qty_ordered * poi.unit_cost) as line_total
  FROM purchase_order_items poi
  JOIN products p ON p.id = poi.product_id
  WHERE poi.po_id = ?
  ORDER BY p.name
");
$items_stmt->execute([$id]);
$items = $items_stmt->fetchAll();

// Company settings
$company_name    = get_setting('company_name', APP_NAME);
$company_address = get_setting('address', '');
$company_phone   = get_setting('phone', '');
$company_logo    = get_setting('company_logo');
$show_logo       = get_setting('show_logo_in_invoice') === '1';
$vat_number      = get_setting('vat_number', '');
$currency        = get_setting('currency', 'KWD');
$decimals        = (int)get_setting('currency_decimals', '3');
$auto_print      = isset($_GET['print']) && $_GET['print'] === '1';

$subtotal = array_sum(array_column($items, 'line_total'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Purchase Order — <?= htmlspecialchars($po['po_number']) ?></title>
<style>
  * { margin:0;padding:0;box-sizing:border-box; }
  body { font-family:'Arial',sans-serif;font-size:12px;color:#222;background:#f0f2f5;padding:20px; }
  .page { max-width:800px;margin:0 auto;background:#fff;padding:36px 40px;box-shadow:0 2px 16px rgba(0,0,0,.12); }

  /* Header */
  .doc-header { display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:20px;border-bottom:3px solid #1a3a5c;margin-bottom:24px; }
  .company-block h1 { font-size:22px;font-weight:700;color:#1a3a5c; }
  .company-block p  { font-size:11px;color:#555;margin-top:2px;line-height:1.6; }
  .po-title { text-align:right; }
  .po-title h2 { font-size:30px;font-weight:700;color:#1a3a5c;letter-spacing:1px; }
  .po-title .po-meta { margin-top:8px;font-size:11px;color:#555;line-height:1.8; }
  .po-title .po-meta strong { color:#222; }

  /* Parties */
  .parties { display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px; }
  .party-box { background:#f7f9fc;border:1px solid #dde2ea;border-radius:6px;padding:14px 16px; }
  .party-box h3 { font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#1a3a5c;margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid #dde2ea; }
  .party-box p  { font-size:11px;color:#444;line-height:1.7; }
  .party-box .big-name { font-size:13px;font-weight:700;color:#111;margin-bottom:4px; }

  /* Status badge */
  .status-badge { display:inline-block;padding:3px 10px;border-radius:12px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px; }
  .status-pending   { background:#fff3cd;color:#856404;border:1px solid #ffc107; }
  .status-partial   { background:#cff4fc;color:#0c5460;border:1px solid #0dcaf0; }
  .status-completed { background:#d1e7dd;color:#0f5132;border:1px solid #198754; }
  .status-cancelled { background:#f8d7da;color:#842029;border:1px solid #dc3545; }

  /* Items table */
  .items-section h3 { font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#1a3a5c;margin-bottom:10px; }
  table { width:100%;border-collapse:collapse; }
  thead th { background:#1a3a5c;color:#fff;padding:9px 11px;font-size:11px;font-weight:600;text-align:left; }
  thead th.text-right { text-align:right; }
  tbody tr:nth-child(even) { background:#f7f9fc; }
  tbody td { padding:9px 11px;border-bottom:1px solid #eaecf0;font-size:11px;vertical-align:middle; }
  tbody td.text-right { text-align:right; }
  tfoot td { padding:9px 11px;font-size:12px; }
  tfoot .total-row td { background:#f0f4f8;font-weight:700;font-size:13px;border-top:2px solid #1a3a5c; }

  /* Totals */
  .totals-wrap { display:flex;justify-content:flex-end;margin-top:4px; }
  .totals-table { width:260px;font-size:12px; }
  .totals-table td { padding:5px 8px; }
  .totals-table .grand td { font-size:14px;font-weight:700;background:#1a3a5c;color:#fff;border-radius:4px; }

  /* Footer */
  .doc-footer { margin-top:36px;padding-top:16px;border-top:1px solid #dde2ea;display:flex;justify-content:space-between;align-items:flex-end; }
  .sig-box { text-align:center;min-width:160px; }
  .sig-box .sig-line { border-top:1px solid #999;margin-top:40px;font-size:10px;color:#777;padding-top:4px; }
  .notes-box { font-size:11px;color:#555;max-width:300px; }
  .notes-box strong { display:block;color:#222;margin-bottom:3px; }

  .print-btn { position:fixed;bottom:20px;right:20px;background:#1a3a5c;color:#fff;border:none;padding:10px 18px;border-radius:6px;cursor:pointer;font-size:13px;box-shadow:0 2px 8px rgba(0,0,0,.2);z-index:100; }
  @media print {
    body { background:#fff;padding:0; }
    .page { box-shadow:none;padding:20px; }
    .print-btn { display:none; }
  }
</style>
</head>
<body>
<div class="page">

  <!-- HEADER: company left, PO info right -->
  <div class="doc-header">
    <div class="company-block">
      <?php if ($show_logo && $company_logo): ?>
      <img src="<?= BASE ?>/uploads/<?= htmlspecialchars($company_logo) ?>" style="max-height:55px;margin-bottom:8px">
      <?php else: ?>
      <h1><?= htmlspecialchars($company_name) ?></h1>
      <?php endif; ?>
      <p>
        <?= nl2br(htmlspecialchars($company_address)) ?><br>
        <?php if ($company_phone): ?>📞 <?= htmlspecialchars($company_phone) ?><br><?php endif; ?>
        <?php if ($vat_number): ?>VAT: <?= htmlspecialchars($vat_number) ?><?php endif; ?>
      </p>
    </div>
    <div class="po-title">
      <h2>PURCHASE ORDER</h2>
      <div class="po-meta">
        <strong><?= htmlspecialchars($po['po_number']) ?></strong><br>
        <span>Date:</span> <strong><?= date('d F Y', strtotime($po['created_at'])) ?></strong><br>
        <span>Branch:</span> <strong><?= htmlspecialchars($po['branch_name']) ?></strong><br>
        <span>Status:</span>
        <span class="status-badge status-<?= $po['status'] ?>"><?= ucfirst($po['status']) ?></span><br>
        <span>Prepared by:</span> <strong><?= htmlspecialchars($po['created_by_name']) ?></strong>
      </div>
    </div>
  </div>

  <!-- PARTIES -->
  <div class="parties">
    <div class="party-box">
      <h3>📤 From (Buyer)</h3>
      <p class="big-name"><?= htmlspecialchars($company_name) ?></p>
      <p>
        <?= nl2br(htmlspecialchars($company_address)) ?><br>
        <?php if ($company_phone): ?>Tel: <?= htmlspecialchars($company_phone) ?><br><?php endif; ?>
        <?php if ($vat_number): ?>VAT No: <?= htmlspecialchars($vat_number) ?><?php endif; ?>
      </p>
    </div>
    <div class="party-box">
      <h3>📥 To (Supplier)</h3>
      <p class="big-name"><?= htmlspecialchars($po['sup_company']) ?>
        <?php if ($po['sup_company_ar']): ?><br><span style="font-size:12px;direction:rtl"><?= htmlspecialchars($po['sup_company_ar']) ?></span><?php endif; ?>
      </p>
      <p>
        <?php if ($po['sup_contact']): ?><?= htmlspecialchars($po['sup_contact']) ?><br><?php endif; ?>
        <?php if ($po['sup_phone']): ?>Tel: <?= htmlspecialchars($po['sup_phone']) ?><br><?php endif; ?>
        <?php if ($po['sup_email']): ?>Email: <?= htmlspecialchars($po['sup_email']) ?><br><?php endif; ?>
        <?php if ($po['sup_address']): ?><?= nl2br(htmlspecialchars($po['sup_address'])) ?><br><?php endif; ?>
        <?php if ($po['sup_vat']): ?>VAT: <?= htmlspecialchars($po['sup_vat']) ?><?php endif; ?>
      </p>
    </div>
  </div>

  <!-- ITEMS TABLE -->
  <div class="items-section">
    <h3>Order Items</h3>
    <?php if (empty($items)): ?>
    <p style="color:#888;text-align:center;padding:20px;background:#f7f9fc;border-radius:6px">No items on this purchase order yet.</p>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th style="width:40px">#</th>
          <th>Product</th>
          <th style="width:70px">SKU</th>
          <th class="text-right" style="width:80px">Qty</th>
          <th class="text-right" style="width:110px">Unit Cost</th>
          <th class="text-right" style="width:120px">Line Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $n => $item): ?>
        <tr>
          <td style="color:#999"><?= $n+1 ?></td>
          <td style="font-weight:500"><?= htmlspecialchars($item['name']) ?></td>
          <td style="color:#666;font-size:10px"><?= htmlspecialchars($item['sku']) ?></td>
          <td class="text-right"><?= number_format($item['qty_ordered']) ?></td>
          <td class="text-right"><?= number_format($item['unit_cost'], $decimals) ?></td>
          <td class="text-right" style="font-weight:600"><?= number_format($item['line_total'], $decimals) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr class="total-row">
          <td colspan="5" style="text-align:right">TOTAL (<?= htmlspecialchars($currency) ?>)</td>
          <td class="text-right"><?= number_format($subtotal, $decimals) ?></td>
        </tr>
        <?php if ($po['paid_amount'] > 0): ?>
        <tr>
          <td colspan="5" style="text-align:right;font-size:11px;color:#555">Paid</td>
          <td class="text-right" style="font-size:11px;color:#555"><?= number_format($po['paid_amount'], $decimals) ?></td>
        </tr>
        <tr>
          <td colspan="5" style="text-align:right;font-weight:600">Balance Due</td>
          <td class="text-right" style="font-weight:600;color:#c0392b"><?= number_format($subtotal - $po['paid_amount'], $decimals) ?></td>
        </tr>
        <?php endif; ?>
      </tfoot>
    </table>
    <?php endif; ?>
  </div>

  <!-- NOTES + SIGNATURES -->
  <div class="doc-footer">
    <div class="notes-box">
      <?php if ($po['notes']): ?>
      <strong>Notes:</strong>
      <?= nl2br(htmlspecialchars($po['notes'])) ?>
      <?php endif; ?>
    </div>
    <div style="display:flex;gap:40px">
      <div class="sig-box">
        <div class="sig-line">Authorized by</div>
      </div>
      <div class="sig-box">
        <div class="sig-line">Supplier Acknowledgement</div>
      </div>
    </div>
  </div>

</div>

<button class="print-btn" onclick="window.print()">🖨️ Print</button>

<?php if ($auto_print): ?>
<script>window.onload = function(){ window.print(); };</script>
<?php endif; ?>
</body>
</html>
