<?php
require_once __DIR__ . '/../includes/config.php';
require_login();
$currency = get_setting('currency', 'KWD');

$db = db();
$type = $_GET['type'] ?? 'customer';

if ($type === 'customer') {
    $data = $db->query("SELECT name, phone, type, balance, credit_limit FROM customers WHERE balance < 0 ORDER BY balance ASC")->fetchAll();
    $filename = 'RetailPro_Customer_Dues_' . date('Y-m-d') . '.csv';
    $headers = ['Customer', 'Phone', 'Type', 'Balance Due (' . $currency . ')', 'Credit Limit (' . $currency . ')'];
} else {
    $data = $db->query("SELECT company, contact_name, phone, payment_terms, balance FROM suppliers WHERE balance < 0 ORDER BY balance ASC")->fetchAll();
    $filename = 'RetailPro_Supplier_Dues_' . date('Y-m-d') . '.csv';
    $headers = ['Company', 'Contact', 'Phone', 'Payment Terms', 'Amount Due (' . $currency . ')'];
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, $headers);

if ($type === 'customer') {
    foreach ($data as $row) {
        fputcsv($output, [
            $row['name'], $row['phone'] ?? '', ucfirst($row['type']),
            number_format(abs($row['balance']), 3), number_format($row['credit_limit'], 3)
        ]);
    }
    $total = array_sum(array_map(fn($r) => abs($r['balance']), $data));
    fputcsv($output, []);
    fputcsv($output, ['TOTAL', '', '', number_format($total, 3), count($data) . ' customers']);
} else {
    foreach ($data as $row) {
        fputcsv($output, [
            $row['company'], $row['contact_name'] ?? '', $row['phone'] ?? '',
            $row['payment_terms'] ?? '', number_format(abs($row['balance']), 3)
        ]);
    }
    $total = array_sum(array_map(fn($r) => abs($r['balance']), $data));
    fputcsv($output, []);
    fputcsv($output, ['TOTAL', '', '', '', number_format($total, 3) . ' (' . count($data) . ' suppliers)']);
}

fclose($output);
exit;
