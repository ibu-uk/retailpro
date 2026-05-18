<?php
require_once __DIR__ . '/includes/config.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) die('Invoice ID required');

$db = db();
$inv = $db->query("
  SELECT i.*, c.name as customer_name, c.name_ar as customer_name_ar, c.address, c.address_ar, c.phone,
         b.name as branch_name, b.address as branch_address, b.phone as branch_phone,
         u.name as created_by_name
  FROM invoices i
  JOIN customers c ON c.id = i.customer_id
  JOIN branches b ON b.id = i.branch_id
  JOIN users u ON u.id = i.created_by
  WHERE i.id = ?
")->fetch($id);

if (!$inv) die('Invoice not found');

$items = $db->query("
  SELECT ii.*, p.name, p.name_ar, p.sku
  FROM invoice_items ii
  JOIN products p ON p.id = ii.product_id
  WHERE ii.invoice_id = ?
")->fetchAll($id);

$company_name = get_setting('company_name', 'RetailPro Kuwait LLC');
$company_address = get_setting('address', 'Block 4, Shop 12, Salmiya, Kuwait');
$company_phone = get_setting('phone', '+965 2244-1100');
$vat_number = get_setting('vat_number', 'KWT-30082024-00841');
$invoice_footer = get_setting('invoice_footer', 'Thank you for shopping with RetailPro. Returns accepted within 7 days with receipt.');
$company_logo = get_setting('company_logo');
$show_logo = get_setting('show_logo_in_invoice') === '1';
$currency = get_setting('currency', 'KWD');
$decimals = ($currency === 'KWD') ? 3 : 2;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Invoice <?= htmlspecialchars($inv['invoice_number']) ?></title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Arial', sans-serif; font-size: 12px; color: #333; padding: 20px; background: #f5f5f5; }
    .invoice { max-width: 800px; margin: 0 auto; background: white; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
    .company-info h1 { font-size: 24px; font-weight: bold; margin-bottom: 5px; }
    .company-info .arabic { font-family: 'Arial', sans-serif; font-size: 16px; color: #666; direction: rtl; }
    .company-info p { margin: 2px 0; color: #666; }
    .invoice-title { text-align: right; }
    .invoice-title h2 { font-size: 28px; font-weight: bold; color: #333; }
    .invoice-title .arabic { font-size: 18px; direction: rtl; color: #666; margin-top: 5px; }
    .invoice-title p { margin: 5px 0; color: #666; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
    .section-title { font-weight: bold; margin-bottom: 10px; font-size: 13px; color: #333; }
    .section-title .arabic { font-size: 12px; direction: rtl; color: #666; }
    .info-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
    .info-label { color: #666; }
    .info-label .arabic { direction: rtl; }
    .info-value { font-weight: 500; }
    .info-value .arabic { direction: rtl; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    th, td { border: 1px solid #ddd; padding: 10px 12px; text-align: left; }
    th { background: #f8f9fa; font-weight: bold; font-size: 12px; }
    th .arabic { direction: rtl; font-size: 11px; }
    td .arabic { display: block; font-size: 11px; color: #666; direction: rtl; }
    .text-right { text-align: right; }
    .totals { margin-left: auto; width: 250px; }
    .total-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
    .total-row:last-child { border-bottom: none; border-top: 2px solid #333; margin-top: 5px; padding-top: 10px; font-weight: bold; font-size: 14px; }
    .total-label .arabic { direction: rtl; font-size: 11px; color: #666; }
    .status-badge { padding: 4px 12px; border-radius: 4px; font-weight: bold; font-size: 11px; text-transform: uppercase; }
    .status-paid { background: #d4edda; color: #155724; }
    .status-partial { background: #fff3cd; color: #856404; }
    .status-credit { background: #f8d7da; color: #721c24; }
    .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666; font-size: 11px; }
    .footer .arabic { direction: rtl; display: block; margin-top: 5px; }
    @media print { body { background: white; padding: 0; } .invoice { box-shadow: none; max-width: 100%; } }
  </style>
</head>
<body>
  <div class="invoice">
    <!-- Header -->
    <div class="header">
      <?php if ($show_logo && $company_logo): ?>
      <div style="margin-bottom:15px">
        <img src="<?= htmlspecialchars($company_logo) ?>" alt="Company Logo" style="max-height:60px;max-width:150px">
      </div>
      <?php endif; ?>
      <div class="company-info">
        <h1><?= htmlspecialchars($company_name) ?></h1>
        <span class="arabic">شركة ريتيل برو الكويتية</span>
        <p><?= htmlspecialchars($company_address) ?></p>
        <p><?= htmlspecialchars($company_phone) ?></p>
        <p>VAT: <?= htmlspecialchars($vat_number) ?></p>
        <p class="arabic">الرقم الضريبي: <?= htmlspecialchars($vat_number) ?></p>
      </div>
      <div class="invoice-title">
        <h2>INVOICE</h2>
        <span class="arabic">فاتورة</span>
        <p>#<?= htmlspecialchars($inv['invoice_number']) ?></p>
        <p><?= date('d M Y H:i', strtotime($inv['created_at'])) ?></p>
        <span class="status-badge status-<?= $inv['status'] ?>"><?= ucfirst($inv['status']) ?></span>
      </div>
    </div>

    <!-- Customer & Branch Info -->
    <div class="grid-2">
      <div>
        <div class="section-title">
          Bill To | فاتورة إلى
        </div>
        <div class="info-row">
          <span class="info-label">Name | الاسم:</span>
          <span class="info-value"><?= htmlspecialchars($inv['customer_name']) ?><?php if ($inv['customer_name_ar']) echo '<span class="arabic">' . htmlspecialchars($inv['customer_name_ar']) . '</span>'; ?></span>
        </div>
        <?php if ($inv['address']): ?>
        <div class="info-row">
          <span class="info-label">Address | العنوان:</span>
          <span class="info-value"><?= htmlspecialchars($inv['address']) ?><?php if ($inv['address_ar']) echo '<span class="arabic">' . htmlspecialchars($inv['address_ar']) . '</span>'; ?></span>
        </div>
        <?php endif; ?>
        <?php if ($inv['phone']): ?>
        <div class="info-row">
          <span class="info-label">Phone | الهاتف:</span>
          <span class="info-value"><?= htmlspecialchars($inv['phone']) ?></span>
        </div>
        <?php endif; ?>
      </div>
      <div>
        <div class="section-title">
          Branch | الفرع
        </div>
        <div class="info-row">
          <span class="info-label">Location | الموقع:</span>
          <span class="info-value"><?= htmlspecialchars($inv['branch_name']) ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Payment | الدفع:</span>
          <span class="info-value"><?= strtoupper($inv['payment_mode']) ?></span>
        </div>
        <div class="info-row">
          <span class="info-label">Cashier | الكاشير:</span>
          <span class="info-value"><?= htmlspecialchars($inv['created_by_name']) ?></span>
        </div>
      </div>
    </div>

    <!-- Items Table -->
    <table>
      <thead>
        <tr>
          <th>Item | الصنف</th>
          <th>SKU</th>
          <th>Qty | الكمية</th>
          <th>Unit Price | سعر الوحدة</th>
          <th class="text-right">Total | المجموع</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
        <tr>
          <td>
            <?= htmlspecialchars($item['name']) ?>
            <?php if ($item['name_ar']): ?><span class="arabic"><?= htmlspecialchars($item['name_ar']) ?></span><?php endif; ?>
          </td>
          <td><?= htmlspecialchars($item['sku']) ?></td>
          <td><?= $item['qty'] ?></td>
          <td><?= $currency ?> <?= number_format($item['unit_price'], $decimals) ?></td>
          <td class="text-right"><?= $currency ?> <?= number_format($item['total'], $decimals) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Totals -->
    <div class="totals">
      <div class="total-row">
        <span class="total-label">Subtotal | المجموع الفرعي:</span>
        <span><?= $currency ?> <?= number_format($inv['subtotal'], $decimals) ?></span>
      </div>
      <?php if ($inv['discount'] > 0): ?>
      <div class="total-row">
        <span class="total-label">Discount | الخصم:</span>
        <span style="color: #dc3545">-<?= $currency ?> <?= number_format($inv['discount'], $decimals) ?></span>
      </div>
      <?php endif; ?>
      <?php if ($inv['vat'] > 0): ?>
      <div class="total-row">
        <span class="total-label">VAT (5%) | الضريبة:</span>
        <span><?= $currency ?> <?= number_format($inv['vat'], $decimals) ?></span>
      </div>
      <?php endif; ?>
      <div class="total-row">
        <span class="total-label">Total | الإجمالي:</span>
        <span><?= $currency ?> <?= number_format($inv['total'], $decimals) ?></span>
      </div>
      <?php if ($inv['paid_amount'] < $inv['total']): ?>
      <div class="total-row">
        <span class="total-label">Paid | المدفوع:</span>
        <span><?= $currency ?> <?= number_format($inv['paid_amount'], $decimals) ?></span>
      </div>
      <div class="total-row">
        <span class="total-label">Balance | الرصيد المتبقي:</span>
        <span style="color: #dc3545"><?= $currency ?> <?= number_format($inv['total'] - $inv['paid_amount'], $decimals) ?></span>
      </div>
      <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="footer">
      <?= htmlspecialchars($invoice_footer) ?>
      <span class="arabic">شكراً لتسوقكم مع ريتيل برو. يُسمح بإرجاع البضائع خلال 7 أيام مع الإيصال.</span>
    </div>
  </div>

  <script>
    window.print();
    window.onafterprint = function() { window.close(); };
  </script>
</body>
</html>
