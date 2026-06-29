<?php
require_once __DIR__ . '/includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) die('Invoice ID required');
$auto_print = isset($_GET['print']) && $_GET['print'] === '1';
$printer_format = get_setting('printer_format', 'a4'); // Get default from settings
$format_class = ($printer_format === 'thermal' || $printer_format === '80mm') ? 'thermal' : 'a4';

$db = db();
$stmt = $db->prepare("
  SELECT i.*, c.name as customer_name, COALESCE(c.company_name,'') as customer_company, c.name_ar as customer_name_ar, c.address, c.address_ar, c.phone,
         b.name as branch_name, b.address as branch_address, b.phone as branch_phone,
         u.name as created_by_name
  FROM invoices i
  JOIN customers c ON c.id = i.customer_id
  JOIN branches b ON b.id = i.branch_id
  JOIN users u ON u.id = i.created_by
  WHERE i.id = ?
");
$stmt->execute([$id]);
$inv = $stmt->fetch();

if (!$inv) die('Invoice not found');

$stmt = $db->prepare("
  SELECT ii.*, p.name, p.name_ar, p.sku,
         COALESCE(ii.unit_label,'') as unit_label,
         COALESCE(ii.stock_deduct,0) as stock_deduct
  FROM invoice_items ii
  JOIN products p ON p.id = ii.product_id
  WHERE ii.invoice_id = ?
");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

$company_name = get_setting('company_name', get_setting('company_name', APP_NAME));
$company_address = get_setting('address', 'Block 4, Shop 12, Salmiya, Kuwait');
$company_phone = get_setting('phone', '+965 2244-1100');
$invoice_footer    = get_setting('invoice_footer', '');
$refund_days       = (int)get_setting('refund_period_days', 0);
$company_logo = get_setting('company_logo');
$show_logo = get_setting('show_logo_in_invoice') === '1';
$tc        = get_tax_config();
$currency  = $tc['currency'];
$decimals  = $tc['currency_decimals'];
$vat_number = $tc['vat_number'] ?: get_setting('vat_number', '');
$tax_label  = $tc['tax_label'] ?: 'VAT';
$has_tax    = $tc['tax_type'] !== 'none' && $tc['tax_rate'] > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Invoice <?= htmlspecialchars($inv['invoice_number']) ?></title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Courier New', Courier, monospace; background: #e0e0e0; display: flex; justify-content: center; padding: 20px; }
    .receipt { background: #fff; width: 320px; padding: 16px 14px; font-size: 11px; color: #000; line-height: 1.5; }
    .receipt.a4 { width: 210mm; min-height: 297mm; padding: 20mm; font-size: 14px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
    .receipt.a4 .company-name { font-size: 22px; }
    .receipt.a4 .company-arabic { font-size: 16px; }
    .receipt.a4 .company-addr { font-size: 13px; margin-top: 4px; }
    .receipt.a4 .info-table td { font-size: 14px; }
    .receipt.a4 .items-table { font-size: 13px; }
    .receipt.a4 .items-table th,
    .receipt.a4 .items-table td { padding: 6px 0; }
    .receipt.a4 .item-name-ar { font-size: 11px; }
    .receipt.a4 .totals-table td { font-size: 14px; }
    .receipt.a4 .grand-total td { font-size: 16px; padding-top: 6px; }
    .receipt.a4 .footer-msg { font-size: 13px; }
    .receipt.a4 .logo { max-height: 80px; max-width: 120px; }
    .center { text-align: center; }
    .bold { font-weight: bold; }
    .divider { border: none; border-top: 1px dashed #000; margin: 8px 0; }
    .logo { display: block; margin: 0 auto 8px; max-height: 60px; max-width: 80px; }
    .company-name { font-size: 13px; font-weight: bold; margin-bottom: 2px; }
    .company-arabic { font-size: 11px; direction: rtl; }
    .company-addr { font-size: 10px; color: #333; margin-top: 2px; }
    .info-table { width: 100%; border-collapse: collapse; margin: 4px 0; }
    .info-table td { padding: 1px 0; vertical-align: top; font-size: 11px; }
    .info-table td:first-child { width: 55%; }
    .info-table td:last-child { text-align: right; font-weight: bold; }
    .items-table { width: 100%; border-collapse: collapse; margin: 4px 0; font-size: 10.5px; }
    .items-table th { font-weight: bold; padding: 2px 0; border-bottom: 1px dashed #000; }
    .items-table th:last-child, .items-table td:last-child { text-align: right; }
    .items-table th:nth-child(2), .items-table td:nth-child(2) { text-align: center; }
    .items-table th:nth-child(3), .items-table td:nth-child(3) { text-align: center; }
    .items-table td { padding: 3px 0; vertical-align: top; }
    .item-name-ar { font-size: 9.5px; color: #444; direction: rtl; }
    .totals-table { width: 100%; border-collapse: collapse; margin: 4px 0; }
    .totals-table td { padding: 2px 0; font-size: 11px; }
    .totals-table td:last-child { text-align: right; font-weight: bold; }
    .totals-table .grand-total td { font-size: 13px; font-weight: bold; border-top: 1px dashed #000; padding-top: 4px; }
    .footer-msg { margin-top: 6px; font-size: 10px; }
    .no-print { display: flex; gap: 10px; justify-content: center; margin-top: 16px; }
    .no-print button { padding: 8px 18px; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: bold; }
    .btn-print { background: #222; color: #fff; }
    .btn-close { background: #eee; color: #333; }
    @media print {
      body { background: white; padding: 0; }
      .receipt { box-shadow: none; width: 100%; }
      .no-print { display: none; }
    }
  </style>
</head>
<body>
  <div class="receipt <?= $format_class ?>">

    <!-- HEADER -->
    <?php if ($show_logo && $company_logo): ?>
    <img src="<?= htmlspecialchars($company_logo) ?>" alt="Logo" class="logo">
    <?php endif; ?>
    <div class="center">
      <div class="company-name"><?= htmlspecialchars($company_name) ?></div>
      <div class="company-addr">Branch / فرع: <?= htmlspecialchars($inv['branch_name']) ?></div>
      <?php $company_name_ar = get_setting('company_name_ar', ''); if ($company_name_ar): ?>
      <div class="company-arabic"><?= htmlspecialchars($company_name_ar) ?></div>
      <?php endif; ?>
      <div class="company-addr"><?= htmlspecialchars($company_address) ?></div>
      <?php $addr_ar = get_setting('address_ar', ''); if ($addr_ar): ?>
      <div class="company-arabic company-addr"><?= htmlspecialchars($addr_ar) ?></div>
      <?php endif; ?>
      <?php if ($company_phone): ?>
      <div class="company-addr">Tel: <?= htmlspecialchars($company_phone) ?></div>
      <?php endif; ?>
      <?php if ($vat_number && $has_tax): ?>
      <div class="company-addr"><?= $tax_label ?> No: <?= htmlspecialchars($vat_number) ?></div>
      <?php endif; ?>
    </div>

    <hr class="divider">

    <!-- INVOICE INFO -->
    <table class="info-table">
      <tr>
        <td>Invoice # / <span style="direction:rtl">رقم الفاتورة</span></td>
        <td><?= htmlspecialchars($inv['invoice_number']) ?></td>
      </tr>
      <tr>
        <td>Date / التاريخ</td>
        <td><?= date('d/m/Y H:i', strtotime($inv['created_at'])) ?></td>
      </tr>
      <tr>
        <td>Cashier / الكاشير</td>
        <td><?= htmlspecialchars($inv['created_by_name']) ?></td>
      </tr>
      <tr>
        <td>Customer / العميل</td>
        <td><?= htmlspecialchars($inv['customer_name']) ?></td>
      </tr>
      <tr>
        <td>Payment / الدفع</td>
        <td><?= strtoupper($inv['payment_mode']) ?></td>
      </tr>
      <?php if (!empty($inv['payment_ref'])): ?>
      <tr>
        <td>KNET Ref / المرجع</td>
        <td style="font-weight:bold"><?= htmlspecialchars($inv['payment_ref']) ?></td>
      </tr>
      <?php endif; ?>
    </table>

    <hr class="divider">

    <!-- ITEMS -->
    <table class="items-table">
      <thead>
        <tr>
          <th style="text-align:left">Item / الصنف</th>
          <th>Qty<br><span style="font-size:9px">الكمية</span></th>
          <th>Price<br><span style="font-size:9px">السعر</span></th>
          <th>Total<br><span style="font-size:9px">المجموع</span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
        <tr>
          <td>
            <?= htmlspecialchars($item['name']) ?>
            <?php if ($item['name_ar']): ?>
            <div class="item-name-ar"><?= htmlspecialchars($item['name_ar']) ?></div>
            <?php endif; ?>
          </td>
          <td style="text-align:center"><?= $item['qty'] ?></td>
          <td style="text-align:center"><?= number_format($item['unit_price'], $decimals) ?></td>
          <td style="text-align:right"><?= number_format($item['total'], $decimals) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <hr class="divider">

    <!-- TOTALS -->
    <table class="totals-table">
      <?php if ($inv['discount'] > 0): ?>
      <tr>
        <td>Subtotal / المجموع الفرعي</td>
        <td><?= number_format($inv['subtotal'], $decimals) ?> <?= $currency ?></td>
      </tr>
      <tr>
        <td>Discount / الخصم</td>
        <td>-<?= number_format($inv['discount'], $decimals) ?> <?= $currency ?></td>
      </tr>
      <?php endif; ?>
      <?php if ($inv['vat'] > 0 && $has_tax): ?>
      <tr>
        <td><?= $tax_label ?> / الضريبة</td>
        <td><?= number_format($inv['vat'], $decimals) ?> <?= $currency ?></td>
      </tr>
      <?php endif; ?>
      <tr class="grand-total">
        <td>TOTAL / الإجمالي</td>
        <td><?= number_format($inv['total'], $decimals) ?> <?= $currency ?></td>
      </tr>
      <tr>
        <td>Cash Paid / المبلغ المدفوع</td>
        <td><?= number_format($inv['paid_amount'], $decimals) ?> <?= $currency ?></td>
      </tr>
      <?php
        $change = $inv['paid_amount'] - $inv['total'];
        $balance = $inv['total'] - $inv['paid_amount'];
      ?>
      <?php if ($change > 0): ?>
      <tr>
        <td>Change / الباقي</td>
        <td><?= number_format($change, $decimals) ?> <?= $currency ?></td>
      </tr>
      <?php elseif ($balance > 0): ?>
      <tr>
        <td>Balance Due / المتبقي</td>
        <td><?= number_format($balance, $decimals) ?> <?= $currency ?></td>
      </tr>
      <?php endif; ?>
    </table>

    <hr class="divider">

    <!-- FOOTER -->
    <div class="center footer-msg">
      <?php if ($invoice_footer): ?>
      <div><?= htmlspecialchars($invoice_footer) ?></div>
      <?php else: ?>
      <div>Thank you for shopping with <?= htmlspecialchars($company_name) ?>.</div>
      <div style="direction:rtl">شكراً لزيارتكم!</div>
      <?php endif; ?>
      <?php if ($refund_days > 0 && !preg_match('/return|refund/i', $invoice_footer)): ?>
      <div style="margin-top:3px">Returns accepted within <?= $refund_days ?> days with receipt.</div>
      <?php endif; ?>
      <?php if ($company_phone): ?>
      <div style="margin-top:4px"><?= htmlspecialchars($company_phone) ?></div>
      <?php endif; ?>
    </div>

  </div>

  <script>
    <?php if ($auto_print): ?>
    window.print();
    window.onafterprint = function() { window.close(); };
    <?php endif; ?>
  </script>
</body>
</html>
