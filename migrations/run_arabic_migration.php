<?php
// Run this script to add Arabic fields to the database
require_once __DIR__ . '/../includes/config.php';

$db = db();

echo "Starting Arabic field migration...\n";

try {
    // Add Arabic name to categories
    $db->exec("ALTER TABLE categories ADD COLUMN name_ar VARCHAR(80) DEFAULT NULL AFTER name");
    echo "✓ Added name_ar to categories\n";
} catch (Exception $e) {
    echo "- name_ar already exists in categories\n";
}

try {
    // Add Arabic name to products
    $db->exec("ALTER TABLE products ADD COLUMN name_ar VARCHAR(150) DEFAULT NULL AFTER name");
    echo "✓ Added name_ar to products\n";
} catch (Exception $e) {
    echo "- name_ar already exists in products\n";
}

try {
    // Add Arabic fields to customers
    $db->exec("ALTER TABLE customers ADD COLUMN name_ar VARCHAR(150) DEFAULT NULL AFTER name");
    echo "✓ Added name_ar to customers\n";
} catch (Exception $e) {
    echo "- name_ar already exists in customers\n";
}

try {
    $db->exec("ALTER TABLE customers ADD COLUMN address_ar TEXT DEFAULT NULL AFTER address");
    echo "✓ Added address_ar to customers\n";
} catch (Exception $e) {
    echo "- address_ar already exists in customers\n";
}

// Update existing categories with Arabic names
$updates = [
    "UPDATE categories SET name_ar = 'حقائب' WHERE name = 'Bags'",
    "UPDATE categories SET name_ar = 'ساعات' WHERE name = 'Watches'",
    "UPDATE categories SET name_ar = 'ملابس' WHERE name = 'Clothes'",
    "UPDATE categories SET name_ar = 'إكسسوارات' WHERE name = 'Accessories'",
    "UPDATE categories SET name_ar = 'أحذية' WHERE name = 'Shoes'",
    "UPDATE categories SET name_ar = 'محافظ' WHERE name = 'Wallets'",
];

foreach ($updates as $sql) {
    $db->exec($sql);
}
echo "✓ Updated categories with Arabic names\n";

// Update existing products with Arabic names
$product_updates = [
    "UPDATE products SET name_ar = 'حقيبة شنيل مصغرة' WHERE name = 'Chanel Mini Bag'",
    "UPDATE products SET name_ar = 'ساعة فوسيل' WHERE name = 'Fossil Watch'",
    "UPDATE products SET name_ar = 'تيشيرت متميز' WHERE name = 'Premium T-Shirt'",
    "UPDATE products SET name_ar = 'محفظة جلدية' WHERE name = 'Leather Wallet'",
    "UPDATE products SET name_ar = 'قبعة صيفية' WHERE name = 'Summer Cap'",
    "UPDATE products SET name_ar = 'نايكي إير ماكس' WHERE name = 'Nike Air Max'",
    "UPDATE products SET name_ar = 'بنطال جينز أسود' WHERE name = 'Black Denim Jeans'",
    "UPDATE products SET name_ar = 'سوار فضي' WHERE name = 'Silver Bracelet'",
    "UPDATE products SET name_ar = 'حقيبة توت كبيرة' WHERE name = 'Tote Bag (L)'",
    "UPDATE products SET name_ar = 'ساعة كاسيو F91W' WHERE name = 'Casio F91W'",
    "UPDATE products SET name_ar = 'تيشيرت بولو' WHERE name = 'Polo Shirt'",
    "UPDATE products SET name_ar = 'نظارة شمسية' WHERE name = 'Sunglasses'",
];

foreach ($product_updates as $sql) {
    $db->exec($sql);
}
echo "✓ Updated products with Arabic names\n";

// Update customers with Arabic names
$customer_updates = [
    "UPDATE customers SET name_ar = 'عميل عابر' WHERE name = 'Walk-in Customer'",
    "UPDATE customers SET name_ar = 'أحمد المطيري' WHERE name = 'Ahmad Al-Mutairi'",
    "UPDATE customers SET name_ar = 'فاطمة الرشيدي' WHERE name = 'Fatima Al-Rashidi'",
    "UPDATE customers SET name_ar = 'الشركة الوطنية الكويتية' WHERE name = 'Kuwait National Co.'",
];

foreach ($customer_updates as $sql) {
    $db->exec($sql);
}
echo "✓ Updated customers with Arabic names\n";

echo "\n✅ Migration completed successfully!\n";
