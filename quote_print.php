<?php
/**
 * quote_print.php — Printable Customer Quotation document
 * URL: /quote_print.php?id=123
 */
require_once __DIR__ . '/includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) die('Quote ID required');

$db = db();

$stmt = $db->prepare("
  SELECT q.*,
         c.name as cust_name, c.company_name as cust_company, c.phone as cust_phone,
         c.email as cust_email, c.address as cust_address, c.type as cust_type,
         b.name as branch_name, b.address as branch_address, b.phone as branch_phone,
         u.name as created_by_name
  FROM quotations q
  JOIN customers c ON c.id = q.customer_id
  JOIN branches  b ON b.id = q.branch_id
  JOIN users     u ON u.id = q.created_by
  WHERE q.id = ?
");
$stmt->execute([$id]);
$q = $stmt->fetch();
if (!$q) die('Quotation not found');

$items_stmt = $db->prepare("
  SELECT qi.qty, qi.unit_price, (qi.qty * qi.unit_price) as line_total,
         p.name, p.sku
  FROM quotation_items qi
  JOIN products p ON p.id = qi.product_id
  WHERE qi.quote_id = ?
  ORDER BY p.name
");
$items_stmt->execute([$id]);
$items = $items_stmt->fetchAll();

$company_name    = get_setting('company_name', APP_NAME);
$company_address = get_setting('address', '');
$company_phone   = get_setting('phone', '');
$company_logo    = get_setting('company_logo');
$show_logo       = get_setting('show_logo_in_invoice') === '1';
$vat_number      = get_setting('vat_number', '');
$invoice_footer  = get_setting('invoice_footer', 'Thank you for your business.');
$currency        = get_setting('currency', 'KWD');
$decimals        = (int)get_setting('currency_decimals', '3');
$tc              = get_tax_config();

$subtotal  = array_sum(array_column($items, 'line_total'));
$tax_info  = calc_tax($subtotal);
$valid_until = date('d F Y', strtotime($q['created_at'] . ' + ' . $q['valid_days'] . ' days'));
$auto_print  = isset($_GET['print']) && $_GET['print'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Quotation — <?= htmlspecialchars($q['quote_number']) ?></title>
<style>
  * { margin:0;padding:0;box-sizing:border-box; }
  body { font-family:'Arial',sans-serif;font-size:12px;color:#222;background:#f0f2f5;padding:20px; }
  .page { max-width:800px;margin:0 auto;background:#fff;padding:36px 40px;box-shadow:0 2px 16px rgba(0,0,0,.12); }

  /* Accent color — teal/green for quotes (differs from PO blue) */
  :root { --accent:#1a6e5c; }

  .doc-header { display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:20px;border-bottom:3px solid var(--accent);margin-bottom:24px; }
  .company-block h1 { font-size:22px;font-weight:700;color:var(--accent); }
  .company-block p  { font-size:11px;color:#555;margin-top:2px;line-height:1.6; }
  .doc-title { text-align:right; }
  .doc-title h2 { font-size:30px;font-weight:700;color:var(--accent);letter-spacing:1px; }
  .doc-title .meta { margin-top:8px;font-size:11px;color:#555;line-height:1.9; }
  .doc-title .meta strong { color:#222; }

  .type-badge { display:inline-block;padding:3px 10px;border-radius:12px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px; }
  .type-retail    { background:#d1fae5;color:#065f46;border:1px solid #6ee7b7; }
  .type-wholesale { background:#fef3c7;color:#92400e;border:1px solid #fcd34d; }

  .status-badge { display:inline-block;padding:3px 10px;border-radius:12px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px; }
  .status-draft    { background:#e5e7eb;color:#374151; }
  .status-sent     { background:#dbeafe;color:#1e40af; }
  .status-accepted { background:#d1fae5;color:#065f46; }
  .status-declined { background:#fee2e2;color:#991b1b; }
  .status-expired  { background:#fef9c3;color:#713f12; }

  .parties { display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px; }
  .party-box { background:#f7faf9;border:1px solid #d1ddd9;border-radius:6px;padding:14px 16px; }
  .party-box h3 { font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--accent);margin-bottom:8px;padding-bottom:6px;border-bottom:1px solid #d1ddd9; }
  .party-box .big-name { font-size:13px;font-weight:700;color:#111;margin-bottom:4px; }
  .party-box p { font-size:11px;color:#444;line-height:1.7; }

  .validity-banner { background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:10px 14px;margin-bottom:20px;font-size:11px;display:flex;justify-content:space-between;align-items:center; }
  .validity-banner strong { color:#92400e; }

  table { width:100%;border-collapse:collapse; }
  thead th { background:var(--accent);color:#fff;padding:9px 11px;font-size:11px;font-weight:600;text-align:left; }
  thead th.tr { text-align:right; }
  tbody tr:nth-child(even) { background:#f7faf9; }
  tbody td { padding:9px 11px;border-bottom:1px solid #e5ebe8;font-size:11px; }
  tbody td.tr { text-align:right; }
  tfoot td { padding:9px 11px;font-size:12px; }
  .tfoot-total { background:#f0f7f5;font-weight:700;font-size:14px;border-top:2px solid var(--accent); }

  .terms-box { margin-top:24px;padding:14px 16px;background:#f7faf9;border-radius:6px;border:1px solid #d1ddd9;font-size:11px;color:#555; }
  .terms-box h4 { color:var(--accent);font-size:11px;font-weight:700;margin-bottom:6px; }

  .doc-footer { margin-top:28px;padding-top:16px;border-top:1px solid #d1ddd9;text-align:center;font-size:11px;color:#777; }

  .print-btn { position:fixed;bottom:20px;right:20px;background:var(--accent);color:#fff;border:none;padding:10px 18px;border-radius:6px;cursor:pointer;font-size:13px;box-shadow:0 2px 8px rgba(0,0,0,.2); }
  @media print { body{background:#fff;padding:0} .page{box-shadow:none;padding:20px} .print-btn{display:none} }
</style>
</head>
<body>
<div class="page">

  <!-- HEADER -->
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
    <div class="doc-title">
      <h2>QUOTATION</h2>
      <div class="meta">
        <strong><?= htmlspecialchars($q['quote_number']) ?></strong><br>
        <span>Date:</span> <strong><?= date('d F Y', strtotime($q['created_at'])) ?></strong><br>
        <span>Valid until:</span> <strong><?= $valid_until ?></strong><br>
        <span>Type:</span> <span class="type-badge type-<?= $q['sale_type'] ?>"><?= ucfirst($q['sale_type']) ?></span>
        &nbsp;
        <span class="status-badge status-<?= $q['status'] ?>"><?= ucfirst($q['status']) ?></span><br>
        <span>Prepared by:</span> <strong><?= htmlspecialchars($q['created_by_name']) ?></strong>
      </div>
    </div>
  </div>

  <!-- PARTIES -->
  <div class="parties">
    <div class="party-box">
      <h3>📤 From</h3>
      <p class="big-name"><?= htmlspecialchars($company_name) ?></p>
      <p>
        <?= nl2br(htmlspecialchars($company_address)) ?><br>
        <?php if ($company_phone): ?>Tel: <?= htmlspecialchars($company_phone) ?><br><?php endif; ?>
        <?php if ($vat_number): ?>VAT No: <?= htmlspecialchars($vat_number) ?><?php endif; ?>
      </p>
    </div>
    <div class="party-box">
      <h3>📥 To (Customer)</h3>
      <?php if ($q['cust_company']): ?>
      <p class="big-name"><?= htmlspecialchars($q['cust_company']) ?></p>
      <p><?= htmlspecialchars($q['cust_name']) ?><br>
      <?php else: ?>
      <p class="big-name"><?= htmlspecialchars($q['cust_name']) ?></p>
      <p>
      <?php endif; ?>
        <?php if ($q['cust_phone']): ?>📞 <?= htmlspecialchars($q['cust_phone']) ?><br><?php endif; ?>
        <?php if ($q['cust_email']): ?>✉️ <?= htmlspecialchars($q['cust_email']) ?><br><?php endif; ?>
        <?php if ($q['cust_address']): ?><?= nl2br(htmlspecialchars($q['cust_address'])) ?><?php endif; ?>
      </p>
    </div>
  </div>

  <!-- VALIDITY BANNER -->
  <div class="validity-banner">
    <span>⏱️ This quotation is valid for <strong><?= $q['valid_days'] ?> days</strong> from the date of issue.</span>
    <span>Expires: <strong><?= $valid_until ?></strong></span>
  </div>

  <!-- ITEMS -->
  <?php if (empty($items)): ?>
  <p style="text-align:center;padding:20px;color:#888;background:#f7faf9;border-radius:6px">No items on this quotation.</p>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th style="width:36px">#</th>
        <th>Product / Description</th>
        <th style="width:70px">SKU</th>
        <th class="tr" style="width:70px">Qty</th>
        <th class="tr" style="width:120px">Unit Price</th>
        <th class="tr" style="width:130px">Line Total</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $n => $item): ?>
      <tr>
        <td style="color:#999"><?= $n+1 ?></td>
        <td style="font-weight:500"><?= htmlspecialchars($item['name']) ?></td>
        <td style="color:#666;font-size:10px"><?= htmlspecialchars($item['sku']) ?></td>
        <td class="tr"><?= number_format($item['qty']) ?></td>
        <td class="tr"><?= number_format($item['unit_price'], $decimals) ?></td>
        <td class="tr" style="font-weight:600"><?= number_format($item['line_total'], $decimals) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <?php if ($tax_info['tax'] > 0): ?>
      <tr>
        <td colspan="5" style="text-align:right;color:#666">Subtotal</td>
        <td class="tr"><?= number_format($subtotal, $decimals) ?></td>
      </tr>
      <tr>
        <td colspan="5" style="text-align:right;color:#666"><?= htmlspecialchars($tc['tax_label'] ?: 'Tax') ?> (<?= $tc['tax_rate'] ?>%)</td>
        <td class="tr"><?= number_format($tax_info['tax'], $decimals) ?></td>
      </tr>
      <?php endif; ?>
      <tr class="tfoot-total">
        <td colspan="5" style="text-align:right">TOTAL (<?= htmlspecialchars($currency) ?>)</td>
        <td class="tr"><?= number_format($tax_info['total'], $decimals) ?></td>
      </tr>
    </tfoot>
  </table>
  <?php endif; ?>

  <!-- NOTES + TERMS -->
  <?php if ($q['notes'] || $invoice_footer): ?>
  <div class="terms-box" style="margin-top:20px">
    <?php if ($q['notes']): ?>
    <h4>Notes</h4>
    <p style="margin-bottom:<?= $invoice_footer?'10px':'0' ?>"><?= nl2br(htmlspecialchars($q['notes'])) ?></p>
    <?php endif; ?>
    <?php if ($invoice_footer): ?>
    <h4>Terms & Conditions</h4>
    <p><?= nl2br(htmlspecialchars($invoice_footer)) ?></p>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- SIGNATURE LINE -->
  <div style="display:flex;justify-content:space-between;margin-top:36px">
    <div style="text-align:center;min-width:160px">
      <div style="border-top:1px solid #999;margin-top:40px;font-size:10px;color:#777;padding-top:4px">Authorized Signature</div>
    </div>
    <div style="text-align:center;min-width:160px">
      <div style="border-top:1px solid #999;margin-top:40px;font-size:10px;color:#777;padding-top:4px">Customer Acceptance</div>
    </div>
  </div>

  <div class="doc-footer">
    <?= htmlspecialchars($company_name) ?> &nbsp;·&nbsp; <?= htmlspecialchars($company_phone) ?>
    <?php if ($vat_number): ?>&nbsp;·&nbsp; VAT: <?= htmlspecialchars($vat_number) ?><?php endif; ?>
  </div>

</div>

<button class="print-btn" onclick="window.print()">🖨️ Print / Save PDF</button>
<?php if ($auto_print): ?><script>window.onload=function(){window.print()};</script><?php endif; ?>
</body>
</html>
