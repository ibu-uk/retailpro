<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$db = db();
$q = trim($_GET['q'] ?? '');
$limit = min(20, max(1, (int)($_GET['limit'] ?? 10)));

if ($q === '') {
    json_response(['products' => []]);
}

$stmt = $db->prepare("SELECT id, name, sku, COALESCE(emoji,'📦') as emoji
                       FROM products
                       WHERE is_active = 1 AND (name LIKE ? OR sku LIKE ? OR barcode LIKE ?)
                       ORDER BY name
                       LIMIT ?");
$stmt->execute(["%$q%", "%$q%", "%$q%", $limit]);
$products = $stmt->fetchAll();

json_response(['products' => $products]);
