<?php
require_once __DIR__ . '/../includes/config.php';
require_login();
$_exp_co = preg_replace('/[^A-Za-z0-9_-]/', '_', trim(get_setting('company_name', 'Company')));

$db = db();
$batches = $db->query("
    SELECT sb.batch_number, p.name as product_name, p.sku,
           s.company as supplier_name, b.name as branch_name,
           sb.qty_received, sb.qty_remaining,
           (sb.qty_received - sb.qty_remaining) as qty_sold,
           sb.cost_price, sb.lot_number,
           sb.received_date, sb.expiry_date, sb.manufacture_date,
           sb.status, po.po_number,
           u.name as received_by
    FROM stock_batches sb
    JOIN products p ON p.id = sb.product_id
    JOIN suppliers s ON s.id = sb.supplier_id
    JOIN branches b ON b.id = sb.branch_id
    LEFT JOIN purchase_orders po ON po.id = sb.po_id
    LEFT JOIN users u ON u.id = sb.received_by
    ORDER BY sb.created_at DESC
")->fetchAll();

$filename = $_exp_co . '_Batches_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['Batch #', 'Product', 'SKU', 'Supplier', 'Branch', 'Received Qty', 'Sold Qty', 'Remaining Qty', 'Cost/Unit', 'Lot Number', 'Received Date', 'Expiry Date', 'Manufacture Date', 'Status', 'PO #', 'Received By']);

foreach ($batches as $b) {
    fputcsv($output, [
        $b['batch_number'],
        $b['product_name'],
        $b['sku'],
        $b['supplier_name'],
        $b['branch_name'],
        $b['qty_received'],
        $b['qty_sold'],
        $b['qty_remaining'],
        $b['cost_price'],
        $b['lot_number'] ?? '',
        $b['received_date'] ?? '',
        $b['expiry_date'] ?? '',
        $b['manufacture_date'] ?? '',
        $b['status'],
        $b['po_number'] ?? '',
        $b['received_by'] ?? '',
    ]);
}

fputcsv($output, []);
fputcsv($output, ['TOTAL', '', '', '', '', array_sum(array_column($batches,'qty_received')), array_sum(array_column($batches,'qty_sold')), array_sum(array_column($batches,'qty_remaining'))]);
fclose($output);
exit;
