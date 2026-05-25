<?php
require_once __DIR__ . '/../includes/config.php';
require_login();
$_exp_co    = preg_replace('/[^A-Za-z0-9_-]/', '_', trim(get_setting('company_name', 'Company')));
$branch_id  = (int)($_GET['branch_id']  ?? 0);
$date_from  = trim($_GET['date_from']   ?? '');
$date_to    = trim($_GET['date_to']     ?? '');
$product_id = (int)($_GET['product_id'] ?? 0);
$mv_type    = trim($_GET['type']        ?? '');

$conditions = [];
$params     = [];
if ($branch_id)  { $conditions[] = 'sm.branch_id = ?';          $params[] = $branch_id; }
if ($date_from)  { $conditions[] = 'DATE(sm.created_at) >= ?';  $params[] = $date_from; }
if ($date_to)    { $conditions[] = 'DATE(sm.created_at) <= ?';  $params[] = $date_to; }
if ($product_id) { $conditions[] = 'sm.product_id = ?';         $params[] = $product_id; }
if ($mv_type)    { $conditions[] = 'sm.type = ?';               $params[] = $mv_type; }
$where_sql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$db = db();
$stmt = $db->prepare("
    SELECT sm.created_at, p.name as product_name, p.sku,
           b.name as branch_name, sm.type, sm.qty,
           sm.reference, sm.notes, u.name as user_name
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    JOIN branches b ON b.id = sm.branch_id
    LEFT JOIN users u ON u.id = sm.user_id
    $where_sql
    ORDER BY sm.created_at DESC
");
$stmt->execute($params);
$movements = $stmt->fetchAll();

$type_labels = [
    'in'         => 'Stock IN',
    'out'        => 'Stock OUT',
    'transfer'   => 'Transfer',
    'damage'     => 'Damaged',
    'return'     => 'Returned',
    'adjustment' => 'Adjustment',
];

$filename = $_exp_co . '_StockMovements_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['Date', 'Product', 'SKU', 'Branch', 'Type', 'Qty', 'Reference', 'Notes', 'Done By']);

foreach ($movements as $m) {
    fputcsv($output, [
        date('d M Y H:i', strtotime($m['created_at'])),
        $m['product_name'],
        $m['sku'],
        $m['branch_name'],
        $type_labels[$m['type']] ?? $m['type'],
        $m['qty'],
        $m['reference'] ?? '',
        $m['notes'] ?? '',
        $m['user_name'] ?? '',
    ]);
}

fputcsv($output, []);
fputcsv($output, ['TOTAL RECORDS', count($movements)]);
fclose($output);
exit;
