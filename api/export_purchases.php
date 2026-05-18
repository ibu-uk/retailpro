<?php
require_once __DIR__ . '/../includes/config.php';
require_login();
$currency = get_setting('currency', 'KWD');

$db = db();
$filter = $_GET['status'] ?? '';
$where = $filter ? "WHERE po.status = '" . addslashes($filter) . "'" : "";

$orders = $db->query("
    SELECT po.po_number, s.company as supplier, b.name as branch, po.created_at,
           po.total_amount, po.paid_amount, po.status, po.notes
    FROM purchase_orders po
    JOIN suppliers s ON s.id = po.supplier_id
    JOIN branches b ON b.id = po.branch_id
    $where ORDER BY po.created_at DESC
")->fetchAll();

$filename = 'RetailPro_Purchases_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['PO Number', 'Supplier', 'Branch', 'Date', 'Total (' . $currency . ')', 'Paid (' . $currency . ')', 'Status', 'Notes']);

foreach ($orders as $po) {
    fputcsv($output, [
        $po['po_number'], $po['supplier'], $po['branch'], $po['created_at'],
        number_format($po['total_amount'], 3), number_format($po['paid_amount'], 3),
        ucfirst($po['status']), $po['notes'] ?? ''
    ]);
}

$total = array_sum(array_column($orders, 'total_amount'));
fputcsv($output, []);
fputcsv($output, ['TOTAL', '', '', '', number_format($total, 3), '', count($orders) . ' orders']);
fclose($output);
exit;
