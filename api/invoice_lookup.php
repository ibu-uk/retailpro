<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$q = trim($_GET['q'] ?? '');
if (!$q) json_response(['error' => 'No search query'], 400);

$db = db();

// Search by invoice number
$stmt = $db->prepare("
    SELECT i.*, c.name as customer_name
    FROM invoices i
    LEFT JOIN customers c ON c.id = i.customer_id
    WHERE i.invoice_number LIKE ?
    ORDER BY i.created_at DESC LIMIT 1
");
$stmt->execute(["%$q%"]);
$inv = $stmt->fetch();

if (!$inv) json_response(['error' => 'Invoice not found: ' . $q], 404);
if ($inv['status'] === 'refunded') json_response(['error' => 'This invoice is already fully refunded'], 400);

// Get items
$items_stmt = $db->prepare("
    SELECT ii.*, p.name as product_name
    FROM invoice_items ii
    LEFT JOIN products p ON p.id = ii.product_id
    WHERE ii.invoice_id = ?
");
$items_stmt->execute([$inv['id']]);
$items = $items_stmt->fetchAll();

json_response([
    'invoice' => [
        'id'             => (int)$inv['id'],
        'invoice_number' => $inv['invoice_number'],
        'customer_name'  => $inv['customer_name'],
        'payment_mode'   => $inv['payment_mode'],
        'subtotal'       => $inv['subtotal'],
        'discount'       => $inv['discount'],
        'total'          => $inv['total'],
        'status'         => $inv['status'],
        'created_at'     => $inv['created_at'],
    ],
    'items' => array_map(function($i) {
        return [
            'id'           => (int)$i['id'],
            'product_id'   => (int)$i['product_id'],
            'product_name' => $i['product_name'],
            'qty'          => (int)$i['qty'],
            'unit_price'   => $i['unit_price'],
            'total'        => $i['total'],
        ];
    }, $items)
]);
