<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$po_id = (int)($_GET['po_id'] ?? 0);
if (!$po_id) { json_response(['error' => 'po_id required'], 400); }

$db = db();
$items = $db->prepare("
    SELECT poi.id, poi.qty_ordered, poi.qty_received, poi.unit_cost,
           p.name, p.sku
    FROM purchase_order_items poi
    JOIN products p ON p.id = poi.product_id
    WHERE poi.po_id = ?
    ORDER BY p.name
");
$items->execute([$po_id]);

json_response(['items' => $items->fetchAll()]);
