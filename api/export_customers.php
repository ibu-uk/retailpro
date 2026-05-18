<?php
require_once __DIR__ . '/../includes/config.php';
require_login();
$currency = get_setting('currency', 'KWD');

$db = db();
$customers = $db->query("
    SELECT c.name, c.email, c.phone, c.type, c.balance, c.credit_limit, c.address,
           COUNT(i.id) as invoice_count
    FROM customers c LEFT JOIN invoices i ON i.customer_id = c.id
    WHERE c.id > 1
    GROUP BY c.id ORDER BY c.name
")->fetchAll();

$filename = 'RetailPro_Customers_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['Name', 'Email', 'Phone', 'Type', 'Balance (' . $currency . ')', 'Credit Limit (' . $currency . ')', 'Address', 'Invoices']);

foreach ($customers as $c) {
    fputcsv($output, [
        $c['name'], $c['email'], $c['phone'], ucfirst($c['type']),
        number_format($c['balance'], 3), number_format($c['credit_limit'], 3),
        $c['address'], $c['invoice_count']
    ]);
}

fputcsv($output, []);
fputcsv($output, ['TOTAL', '', '', '', '', '', '', count($customers) . ' customers']);
fclose($output);
exit;
