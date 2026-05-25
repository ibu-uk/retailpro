<?php
require_once __DIR__ . '/../includes/config.php';
require_login();
$_exp_co = preg_replace('/[^A-Za-z0-9_-]/', '_', trim(get_setting('company_name', 'Company')));

$db = db();
$stock = $db->query("
    SELECT p.name, p.sku, c.name as category,
           COALESCE(SUM(s.qty),0) as total_qty,
           COALESCE((SELECT SUM(sm.qty) FROM stock_movements sm WHERE sm.product_id=p.id AND sm.qty>0),0) as total_received,
           COALESCE((SELECT ABS(SUM(sm.qty)) FROM stock_movements sm WHERE sm.product_id=p.id AND sm.type='out'),0) as total_sold
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN stock s ON s.product_id = p.id
    WHERE p.is_active=1
    GROUP BY p.id ORDER BY total_qty ASC
")->fetchAll();

$filename = $_exp_co . '_Inventory_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['Product', 'SKU', 'Category', 'Total Received', 'Total Sold', 'Current Stock', 'Status']);

foreach ($stock as $s) {
    $status = $s['total_qty'] <= 0 ? 'Out of Stock' : ($s['total_qty'] <= 5 ? 'Low' : 'OK');
    fputcsv($output, [
        $s['name'], $s['sku'], $s['category'] ?? '',
        $s['total_received'], $s['total_sold'], $s['total_qty'], $status
    ]);
}

$total_units    = array_sum(array_column($stock, 'total_qty'));
$total_received = array_sum(array_column($stock, 'total_received'));
$total_sold     = array_sum(array_column($stock, 'total_sold'));
fputcsv($output, []);
fputcsv($output, ['TOTAL', '', '', $total_received, $total_sold, $total_units . ' units', count($stock) . ' products']);
fclose($output);
exit;
