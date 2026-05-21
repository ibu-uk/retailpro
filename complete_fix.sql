-- ============================================================
-- RetailPro — Complete Fix Migration
-- Run ONCE after database.sql: mysql -u root -p retailpro < complete_fix.sql
-- ============================================================

-- 1. Add missing columns to categories
ALTER TABLE categories
  ADD COLUMN IF NOT EXISTS name_ar VARCHAR(80) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS parent_id INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1;

-- 2. Add missing columns to products
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS name_ar VARCHAR(150) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS barcode VARCHAR(60) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS emoji VARCHAR(10) DEFAULT '📦',
  ADD COLUMN IF NOT EXISTS sub_category_id INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS origin_country VARCHAR(80) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 3. Fix invoices table - add 'partial' to payment_mode enum
ALTER TABLE invoices
  MODIFY COLUMN payment_mode ENUM('cash','knet','wamd','transfer','credit','partial') DEFAULT 'cash',
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 4. Fix invoice_items - discount field should be % not amount; add item-level disc_pct
ALTER TABLE invoice_items
  ADD COLUMN IF NOT EXISTS disc_pct DECIMAL(5,2) DEFAULT 0 AFTER unit_price;

-- 5. Add missing columns to expenses for full accounting
ALTER TABLE expenses
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 6. Create units table (referenced in products.php but missing from DB)
CREATE TABLE IF NOT EXISTS units (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(40) NOT NULL,
  name_ar VARCHAR(40),
  abbreviation VARCHAR(10),
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
INSERT IGNORE INTO units (name, name_ar, abbreviation) VALUES
  ('Piece','قطعة','pc'),
  ('Box','صندوق','box'),
  ('Kilogram','كيلوغرام','kg'),
  ('Gram','غرام','g'),
  ('Meter','متر','m'),
  ('Dozen','دزينة','doz'),
  ('Pair','زوج','pr');

-- 7. Create journal_entries table (double-entry accounting)
CREATE TABLE IF NOT EXISTS journal_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  entry_date DATE NOT NULL,
  reference VARCHAR(60),
  description TEXT,
  type ENUM('sale','purchase','expense','payment_in','payment_out','adjustment') NOT NULL,
  debit_account VARCHAR(60) NOT NULL,
  credit_account VARCHAR(60) NOT NULL,
  amount DECIMAL(12,3) NOT NULL,
  branch_id INT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (branch_id) REFERENCES branches(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
);

-- 8. Create chart_of_accounts
CREATE TABLE IF NOT EXISTS chart_of_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) UNIQUE NOT NULL,
  name VARCHAR(100) NOT NULL,
  name_ar VARCHAR(100),
  type ENUM('asset','liability','equity','revenue','expense','cogs') NOT NULL,
  category VARCHAR(60),
  is_active TINYINT(1) DEFAULT 1,
  balance DECIMAL(12,3) DEFAULT 0
);
INSERT IGNORE INTO chart_of_accounts (code,name,name_ar,type,category) VALUES
  ('1000','Cash on Hand','النقدية','asset','current_asset'),
  ('1010','KNET Receivable','مستحقات كي-نت','asset','current_asset'),
  ('1020','Accounts Receivable','الذمم المدينة','asset','current_asset'),
  ('1100','Inventory','المخزون','asset','current_asset'),
  ('1200','Prepaid Expenses','المصروفات المدفوعة مقدماً','asset','current_asset'),
  ('2000','Accounts Payable','الذمم الدائنة','liability','current_liability'),
  ('2100','Accrued Expenses','المصروفات المستحقة','liability','current_liability'),
  ('3000','Owner Equity','حقوق الملكية','equity','equity'),
  ('3100','Retained Earnings','الأرباح المحتجزة','equity','equity'),
  ('4000','Sales Revenue','إيرادات المبيعات','revenue','revenue'),
  ('4100','Other Income','إيرادات أخرى','revenue','revenue'),
  ('5000','Cost of Goods Sold','تكلفة البضاعة المباعة','cogs','cogs'),
  ('6000','Rent Expense','مصروف الإيجار','expense','operating'),
  ('6010','Salary Expense','مصروف الرواتب','expense','operating'),
  ('6020','Utilities Expense','مصروف الخدمات','expense','operating'),
  ('6030','Marketing Expense','مصروف التسويق','expense','operating'),
  ('6040','Other Operating Expense','مصروفات تشغيلية أخرى','expense','operating');

-- 9. Add Arabic to categories (seed data)
UPDATE categories SET name_ar = CASE name
  WHEN 'Bags' THEN 'حقائب'
  WHEN 'Watches' THEN 'ساعات'
  WHEN 'Clothes' THEN 'ملابس'
  WHEN 'Accessories' THEN 'إكسسوارات'
  WHEN 'Shoes' THEN 'أحذية'
  WHEN 'Wallets' THEN 'محافظ'
  ELSE name END
WHERE name_ar IS NULL OR name_ar = '';

-- 10. Ensure offers has usage_count for concurrency
ALTER TABLE offers
  ADD COLUMN IF NOT EXISTS min_purchase DECIMAL(10,3) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS max_discount DECIMAL(10,3) DEFAULT 0;

-- 11. Add invoice_id link to payments for full traceability
ALTER TABLE payments
  ADD COLUMN IF NOT EXISTS invoice_id INT DEFAULT NULL AFTER reference_id;

