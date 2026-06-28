<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

$db = db();
$q = trim($_GET['q'] ?? '');
$limit = min(20, max(1, (int)($_GET['limit'] ?? 10)));

if ($q === '') {
    json_response(['customers' => []]);
}

$stmt = $db->prepare("SELECT id, name, COALESCE(company_name,'') as company, COALESCE(phone,'') as phone, type, balance
                       FROM customers
                       WHERE is_active = 1 AND id != 1 AND (name LIKE ? OR phone LIKE ? OR company_name LIKE ?)
                       ORDER BY name
                       LIMIT ?");
$stmt->execute(["%$q%", "%$q%", "%$q%", $limit]);
$customers = $stmt->fetchAll();

$customers = array_map(function($c) {
    $c['balance'] = (float)$c['balance'];
    return $c;
}, $customers);

json_response(['customers' => $customers]);
