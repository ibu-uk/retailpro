<?php
require_once __DIR__ . '/../includes/config.php';
require_login();
$currency = get_setting('currency', 'KWD');

$db = db();
$payments = $db->query("
    SELECT p.created_at, p.type, p.amount, p.payment_mode, p.notes,
           CASE WHEN p.type='customer' THEN (SELECT name FROM customers WHERE id=p.reference_id)
                ELSE (SELECT company FROM suppliers WHERE id=p.reference_id) END as entity_name,
           u.name as user_name
    FROM payments p LEFT JOIN users u ON u.id=p.created_by
    ORDER BY p.created_at DESC
")->fetchAll();

$filename = 'RetailPro_Payments_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['Date', 'Entity', 'Type', 'Amount (' . $currency . ')', 'Mode', 'Notes', 'Recorded By']);

foreach ($payments as $p) {
    fputcsv($output, [
        $p['created_at'],
        $p['entity_name'] ?? '—',
        ucfirst($p['type']),
        number_format($p['amount'], 3),
        strtoupper($p['payment_mode']),
        $p['notes'] ?? '',
        $p['user_name'] ?? '—'
    ]);
}

$total = array_sum(array_column($payments, 'amount'));
fputcsv($output, []);
fputcsv($output, ['TOTAL', '', '', number_format($total, 3), '', '', count($payments) . ' payments']);
fclose($output);
exit;
