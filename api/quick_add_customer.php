<?php
require_once __DIR__ . '/../includes/config.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$name   = trim($_POST['name'] ?? '');
$phone  = trim($_POST['phone'] ?? '');
$email  = trim($_POST['email'] ?? '');
$type   = in_array($_POST['type'] ?? '', ['retail','wholesale']) ? $_POST['type'] : 'retail';
$credit = (float)($_POST['credit_limit'] ?? 0);

if (!$name) {
    json_response(['error' => 'Name is required'], 400);
}

$db = db();

try {
    // Try with optional columns, fallback without
    try {
        $db->prepare("INSERT INTO customers (name,email,phone,type,credit_limit,address,name_ar,address_ar) VALUES (?,?,?,?,?,?,?,?)")
           ->execute([$name, $email, $phone, $type, $credit, '', '', '']);
    } catch (PDOException $e) {
        // Fallback if arabic columns don't exist
        $db->prepare("INSERT INTO customers (name,email,phone,type,credit_limit,address) VALUES (?,?,?,?,?,?)")
           ->execute([$name, $email, $phone, $type, $credit, '']);
    }
    $new_id = $db->lastInsertId();

    // Return the new customer in the same format as the CUSTOMERS JS array
    json_response([
        'success'  => true,
        'customer' => [
            'id'      => (int)$new_id,
            'name'    => $name,
            'company' => '',
            'phone'   => $phone,
            'type'    => $type,
            'balance' => 0.0,
        ]
    ]);
} catch (Exception $e) {
    json_response(['error' => $e->getMessage()], 500);
}
