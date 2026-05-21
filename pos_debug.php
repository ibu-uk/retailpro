<?php
// DIAGNOSTIC FILE - place in retailpro/ folder and open in browser
// This tells us exactly what's wrong with pos.php
require_once __DIR__ . '/includes/config.php';
require_login();
$db = $pdo ?? db();
$user = current_user();

echo "<h2>POS Diagnostic</h2><pre>";
echo "User: " . $user['name'] . "\n";
echo "Role: " . $user['role'] . "\n";
echo "Branch ID: " . var_export($user['branch_id'], true) . "\n";
echo "\n";

$branch_id = $user['branch_id'] ? (int)$user['branch_id'] : 1;
echo "Resolved branch_id for stock: $branch_id\n\n";

// Count products
$count = $db->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn();
echo "Active products in DB: $count\n";

// Check branch_categories
try {
    $bc = $db->query("SELECT COUNT(*) FROM branch_categories WHERE branch_id=$branch_id")->fetchColumn();
    echo "branch_categories for branch $branch_id: $bc\n";
} catch(Exception $e) {
    echo "branch_categories table missing!\n";
}

// Simulate exact POS query
if ($user['role'] === 'super_admin') {
    $cat_filter = "";
    echo "cat_filter: NONE (super_admin)\n";
} else {
    $bc2 = $db->query("SELECT COUNT(*) FROM branch_categories WHERE branch_id=$branch_id")->fetchColumn();
    $cat_filter = $bc2 > 0 ? "AND p.category_id IN (SELECT category_id FROM branch_categories WHERE branch_id=$branch_id)" : "";
    echo "cat_filter: " . ($cat_filter ?: "NONE") . "\n";
}

$products = $db->query("
    SELECT p.id, p.name, COALESCE(s.qty,0) as stock
    FROM products p
    LEFT JOIN stock s ON s.product_id=p.id AND s.branch_id=$branch_id
    WHERE p.is_active=1 $cat_filter
    ORDER BY p.name
")->fetchAll();

echo "\nProducts returned by query: " . count($products) . "\n\n";
foreach ($products as $p) {
    echo "  [{$p['id']}] {$p['name']} — stock: {$p['stock']}\n";
}

// Check pos.php version
$pos_content = file_get_contents(__DIR__ . '/pos.php');
echo "\n\n--- pos.php VERSION CHECK ---\n";
echo "Has 'products_data': " . (strpos($pos_content, 'products_data') !== false ? "YES ✅ (new version)" : "NO ❌ (OLD version still installed!)") . "\n";
echo "Has 'cat_emojis': "    . (strpos($pos_content, 'cat_emojis') !== false     ? "YES ❌ (OLD version!)" : "NO ✅ (fixed)") . "\n";
echo "Has 'JSON_UNESCAPED_UNICODE': " . (strpos($pos_content, 'JSON_UNESCAPED_UNICODE') !== false ? "YES ✅" : "NO ❌ (OLD version!)") . "\n";
echo "Has 'removeFromCart': " . (strpos($pos_content, 'removeFromCart') !== false ? "YES ✅" : "NO ❌") . "\n";

echo "</pre>";
echo "<br><strong>If 'OLD version still installed' — you need to actually copy the new pos.php to your htdocs folder.</strong>";
