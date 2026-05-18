-- ============================================================
-- Migration: Add Arabic fields for bilingual support
-- Run this to update existing database
-- ============================================================

USE retailpro;

-- Add Arabic name to categories
ALTER TABLE categories ADD COLUMN name_ar VARCHAR(80) DEFAULT NULL AFTER name;

-- Add Arabic name to products
ALTER TABLE products ADD COLUMN name_ar VARCHAR(150) DEFAULT NULL AFTER name;

-- Add Arabic fields to customers
ALTER TABLE customers ADD COLUMN name_ar VARCHAR(150) DEFAULT NULL AFTER name;
ALTER TABLE customers ADD COLUMN address_ar TEXT DEFAULT NULL AFTER address;

-- Update existing categories with Arabic names
UPDATE categories SET name_ar = 'حقائب' WHERE name = 'Bags';
UPDATE categories SET name_ar = 'ساعات' WHERE name = 'Watches';
UPDATE categories SET name_ar = 'ملابس' WHERE name = 'Clothes';
UPDATE categories SET name_ar = 'إكسسوارات' WHERE name = 'Accessories';
UPDATE categories SET name_ar = 'أحذية' WHERE name = 'Shoes';
UPDATE categories SET name_ar = 'محافظ' WHERE name = 'Wallets';

-- Update existing products with Arabic names
UPDATE products SET name_ar = 'حقيبة شنيل مصغرة' WHERE name = 'Chanel Mini Bag';
UPDATE products SET name_ar = 'ساعة فوسيل' WHERE name = 'Fossil Watch';
UPDATE products SET name_ar = 'تيشيرت متميز' WHERE name = 'Premium T-Shirt';
UPDATE products SET name_ar = 'محفظة جلدية' WHERE name = 'Leather Wallet';
UPDATE products SET name_ar = 'قبعة صيفية' WHERE name = 'Summer Cap';
UPDATE products SET name_ar = 'نايكي إير ماكس' WHERE name = 'Nike Air Max';
UPDATE products SET name_ar = 'بنطال جينز أسود' WHERE name = 'Black Denim Jeans';
UPDATE products SET name_ar = 'سوار فضي' WHERE name = 'Silver Bracelet';
UPDATE products SET name_ar = 'حقيبة توت كبيرة' WHERE name = 'Tote Bag (L)';
UPDATE products SET name_ar = 'ساعة كاسيو F91W' WHERE name = 'Casio F91W';
UPDATE products SET name_ar = 'تيشيرت بولو' WHERE name = 'Polo Shirt';
UPDATE products SET name_ar = 'نظارة شمسية' WHERE name = 'Sunglasses';

-- Update customers with Arabic names
UPDATE customers SET name_ar = 'عميل عابر' WHERE name = 'Walk-in Customer';
UPDATE customers SET name_ar = 'أحمد المطيري' WHERE name = 'Ahmad Al-Mutairi';
UPDATE customers SET name_ar = 'فاطمة الرشيدي' WHERE name = 'Fatima Al-Rashidi';
UPDATE customers SET name_ar = 'الشركة الوطنية الكويتية' WHERE name = 'Kuwait National Co.';
