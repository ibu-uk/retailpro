<?php
require_once __DIR__ . '/includes/config.php';

$db = db();

echo "<h2>Checking Arabic Data in Database</h2>";

// Check categories
echo "<h3>Categories</h3>";
$cats = $db->query("SELECT id, name, name_ar FROM categories")->fetchAll();
foreach ($cats as $c) {
    $status = $c['name_ar'] ? '✅ Has Arabic' : '❌ Missing Arabic';
    echo "{$c['name']} | {$c['name_ar'] ?? 'NULL'} - $status<br>";
}

// Check products
echo "<h3>Products</h3>";
$prods = $db->query("SELECT id, name, name_ar FROM products LIMIT 5")->fetchAll();
foreach ($prods as $p) {
    $status = $p['name_ar'] ? '✅ Has Arabic' : '❌ Missing Arabic';
    echo "{$p['name']} | {$p['name_ar'] ?? 'NULL'} - $status<br>";
}

// Check customers
echo "<h3>Customers</h3>";
$custs = $db->query("SELECT id, name, name_ar FROM customers")->fetchAll();
foreach ($custs as $c) {
    $status = $c['name_ar'] ? '✅ Has Arabic' : '❌ Missing Arabic';
    echo "{$c['name']} | {$c['name_ar'] ?? 'NULL'} - $status<br>";
}
?>
