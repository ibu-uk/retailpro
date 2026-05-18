<?php
require_once __DIR__ . '/../includes/config.php';
require_login();
$currency = get_setting('currency', 'KWD');

$from      = $_GET['from'] ?? date('Y-m-01');
$to        = $_GET['to'] ?? date('Y-m-d');
$branch_id = (int)($_GET['branch_id'] ?? 0);

$bwhere = $branch_id ? "AND i.branch_id = $branch_id" : "";

$db = db();
$invoices = $db->query("
    SELECT i.invoice_number, c.name as customer, b.name as branch, i.created_at,
           i.payment_mode, i.subtotal, i.discount, i.total, i.status
    FROM invoices i
    JOIN customers c ON c.id = i.customer_id
    JOIN branches b ON b.id = i.branch_id
    WHERE DATE(i.created_at) BETWEEN '$from' AND '$to' $bwhere
    ORDER BY i.created_at DESC
")->fetchAll();

// Generate CSV (opens natively in Excel)
$filename = 'RetailPro_Sales_' . $from . '_to_' . $to . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$output = fopen('php://output', 'w');

// BOM for Excel UTF-8
fwrite($output, "\xEF\xBB\xBF");

// Header row
fputcsv($output, ['Invoice #', 'Customer', 'Branch', 'Date', 'Payment Mode', 'Subtotal (' . $currency . ')', 'Discount (' . $currency . ')', 'Total (' . $currency . ')', 'Status']);

// Data rows
foreach ($invoices as $inv) {
    fputcsv($output, [
        $inv['invoice_number'],
        $inv['customer'],
        $inv['branch'],
        $inv['created_at'],
        strtoupper($inv['payment_mode']),
        number_format($inv['subtotal'], 3),
        number_format($inv['discount'], 3),
        number_format($inv['total'], 3),
        ucfirst($inv['status'])
    ]);
}

// Summary row
$total_sales = array_sum(array_column($invoices, 'total'));
$total_disc  = array_sum(array_column($invoices, 'discount'));
fputcsv($output, []);
fputcsv($output, ['TOTAL', '', '', '', '', '', number_format($total_disc, 3), number_format($total_sales, 3), count($invoices) . ' invoices']);

fclose($output);
exit;
