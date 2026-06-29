-- ============================================================
-- RetailPro ERP — MySQL Database Schema
-- Run this file first: mysql -u root -p < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS retailpro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE retailpro;

-- ============================================================
-- BRANCHES
-- ============================================================
CREATE TABLE branches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  address TEXT,
  phone VARCHAR(30),
  manager_name VARCHAR(100),
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO branches (name, address, phone, manager_name) VALUES
('Main Branch',       'Block 4, Shop 12, Salmiya, Kuwait',      '+965 2244-1100', 'Ahmed Karim'),
('Al-Salmiya',        'Salmiya, Block 7, Shop 21, Kuwait',       '+965 2211-4400', 'Sara Nasser'),
('Hawalli',           'Hawalli, Block 8, Shop 3, Kuwait',        '+965 2255-3300', 'Omar Nasser'),
('Farwaniya',         'Farwaniya, Block 2, Shop 15, Kuwait',     '+965 2299-8800', 'Nadia Saad');

-- ============================================================
-- USERS & ROLES
-- ============================================================
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  role ENUM('super_admin','manager','cashier','inventory') DEFAULT 'cashier',
  branch_id INT,
  is_active TINYINT(1) DEFAULT 1,
  last_login TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (branch_id) REFERENCES branches(id)
);

-- password: admin123 (bcrypt)
INSERT INTO users (name, email, password, role, branch_id) VALUES
('Super Admin',    'admin@retailpro.kw',     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', NULL),
('Ahmed Karim',    'a.karim@retailpro.kw',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager',     1),
('Cashier POS',    'cashier@retailpro.kw',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier',     1),
('Inventory Staff','inventory@retailpro.kw', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'inventory',   NULL);

-- ============================================================
-- CATEGORIES
-- ============================================================
CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  emoji VARCHAR(10) DEFAULT '📦',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO categories (name, emoji) VALUES
('Bags','👜'),('Watches','⌚'),('Clothes','👕'),
('Accessories','💍'),('Shoes','👟'),('Wallets','👛');

-- ============================================================
-- PRODUCTS
-- ============================================================
CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  sku VARCHAR(60) UNIQUE NOT NULL,
  category_id INT,
  brand VARCHAR(80),
  cost_price DECIMAL(10,3) DEFAULT 0,
  retail_price DECIMAL(10,3) DEFAULT 0,
  wholesale_price DECIMAL(10,3) DEFAULT 0,
  color VARCHAR(80),
  size VARCHAR(80),
  unit_type ENUM('piece','box','unit') DEFAULT 'piece',
  description TEXT,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id)
);

INSERT INTO products (name,sku,category_id,brand,cost_price,retail_price,wholesale_price,color,size) VALUES
('Chanel Mini Bag',   'BAG-2841', 1, 'Chanel',  12.000, 25.000, 20.000, 'Black',  'Mini'),
('Fossil Watch',      'WTC-0291', 2, 'Fossil',  15.000, 30.000, 24.000, 'Silver', 'One Size'),
('Premium T-Shirt',   'CLT-8821', 3, 'Generic',  5.000, 12.000,  9.000, 'White',  'M'),
('Leather Wallet',    'WLT-4421', 6, 'Generic',  4.000, 10.000,  8.000, 'Brown',  'Standard'),
('Summer Cap',        'CAP-1102', 4, 'Generic',  2.000,  5.000,  3.500, 'Beige',  'Free Size'),
('Nike Air Max',      'SHO-0812', 5, 'Nike',    18.000, 28.000, 22.000, 'White',  '42'),
('Black Denim Jeans', 'CLT-0441', 3, 'Generic',  8.000, 18.000, 14.000, 'Black',  'M'),
('Silver Bracelet',   'ACC-1201', 4, 'Generic',  3.000,  8.500,  6.000, 'Silver', 'One Size'),
('Tote Bag (L)',       'BAG-3312', 1, 'Generic',  6.000, 15.000, 12.000, 'Tan',    'L'),
('Casio F91W',        'WTC-0080', 2, 'Casio',    2.000,  6.000,  4.500, 'Black',  'One Size'),
('Polo Shirt',        'CLT-9901', 3, 'Generic',  4.000,  9.000,  7.000, 'Navy',   'M'),
('Sunglasses',        'ACC-4401', 4, 'Generic',  5.000, 12.000,  9.000, 'Black',  'One Size');

-- ============================================================
-- STOCK (per branch)
-- ============================================================
CREATE TABLE stock (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  branch_id INT NOT NULL,
  qty INT DEFAULT 0,
  UNIQUE KEY product_branch (product_id, branch_id),
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (branch_id) REFERENCES branches(id)
);

INSERT INTO stock (product_id, branch_id, qty) VALUES
(1,1,18),(1,2,5),(1,3,2),(1,4,1),
(2,1,12),(2,2,8),(2,3,3),(2,4,2),
(3,1,85),(3,2,40),(3,3,20),(3,4,15),
(4,1,34),(4,2,20),(4,3,10),(4,4,8),
(5,1,62),(5,2,30),(5,3,15),(5,4,10),
(6,1,2),(6,2,5),(6,3,1),(6,4,0),
(7,1,3),(7,2,8),(7,3,4),(7,4,2),
(8,1,24),(8,2,12),(8,3,6),(8,4,4),
(9,1,20),(9,2,10),(9,3,5),(9,4,3),
(10,1,40),(10,2,20),(10,3,10),(10,4,8),
(11,1,55),(11,2,25),(11,3,12),(11,4,8),
(12,1,30),(12,2,15),(12,3,8),(12,4,5);

-- ============================================================
-- STOCK MOVEMENTS
-- ============================================================
CREATE TABLE stock_movements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  branch_id INT NOT NULL,
  type ENUM('in','out','transfer','damage','return','adjustment') NOT NULL,
  qty INT NOT NULL,
  reference VARCHAR(60),
  notes TEXT,
  user_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (branch_id) REFERENCES branches(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
);

INSERT INTO stock_movements (product_id,branch_id,type,qty,reference,user_id) VALUES
(1,1,'in',24,'PO-2841',2),
(2,1,'out',-3,'INV-1044',3),
(6,2,'transfer',10,'TRF-0081',2),
(3,3,'damage',-2,'DAM-0012',4),
(4,1,'return',1,'RET-0041',3);

-- ============================================================
-- CUSTOMERS
-- ============================================================
CREATE TABLE customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150),
  phone VARCHAR(30),
  type ENUM('retail','wholesale') DEFAULT 'retail',
  credit_limit DECIMAL(10,3) DEFAULT 0,
  balance DECIMAL(10,3) DEFAULT 0,   -- negative = owes us, positive = advance
  address TEXT,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO customers (name,email,phone,type,credit_limit,balance) VALUES
('Walk-in Customer',     '',                    '',               'retail',     0,       0),
('Ahmad Al-Mutairi',     'ahmad@email.com',     '+965 9988-7766', 'retail',  1000,   -840.000),
('Fatima Al-Rashidi',    'fatima@email.com',    '+965 6677-5544', 'wholesale',5000,   250.000),
('Kuwait National Co.',  'purchasing@knc.kw',   '+965 2244-1100', 'wholesale',20000, -4200.000);

-- ============================================================
-- SUPPLIERS
-- ============================================================
CREATE TABLE suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company VARCHAR(150) NOT NULL,
  contact_name VARCHAR(100),
  email VARCHAR(150),
  phone VARCHAR(30),
  vat_number VARCHAR(60),
  payment_terms VARCHAR(30) DEFAULT 'Net 30',
  balance DECIMAL(10,3) DEFAULT 0,  -- negative = we owe them
  address TEXT,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO suppliers (company,contact_name,email,phone,vat_number,payment_terms,balance) VALUES
('Gulf Bags Trading',   'Ali Hassan',   'ali@gulfbags.kw',     '+965 2200-1100', '30082841KWD1',  'Net 30', -12400.000),
('Dubai Fashion Hub',   'Sara Karim',   'sara@dubaifashion.ae','+971 4-422-8800', 'TRN-29481UAE',  'Net 15',  -8200.000),
('Istanbul Textile Co.','Mehmet Oz',    'mehmet@istextile.tr', '+90 212-440-2200','TUR-1820-2024', 'Net 45',      0.000);

-- ============================================================
-- PURCHASE ORDERS
-- ============================================================
CREATE TABLE purchase_orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  po_number VARCHAR(30) UNIQUE NOT NULL,
  supplier_id INT NOT NULL,
  branch_id INT NOT NULL,
  status ENUM('pending','partial','completed','cancelled') DEFAULT 'pending',
  total_amount DECIMAL(10,3) DEFAULT 0,
  paid_amount DECIMAL(10,3) DEFAULT 0,
  notes TEXT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  FOREIGN KEY (branch_id) REFERENCES branches(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE purchase_order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  po_id INT NOT NULL,
  product_id INT NOT NULL,
  qty_ordered INT DEFAULT 0,
  qty_received INT DEFAULT 0,
  unit_cost DECIMAL(10,3) DEFAULT 0,
  FOREIGN KEY (po_id) REFERENCES purchase_orders(id),
  FOREIGN KEY (product_id) REFERENCES products(id)
);

INSERT INTO purchase_orders (po_number,supplier_id,branch_id,status,total_amount,paid_amount,created_by) VALUES
('PO-2025-0041',1,1,'completed',4800.000,4800.000,2),
('PO-2025-0040',2,1,'partial',8200.000,0,2),
('PO-2025-0039',3,1,'pending',12400.000,0,2);

-- ============================================================
-- INVOICES (Sales)
-- ============================================================
CREATE TABLE invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_number VARCHAR(30) UNIQUE NOT NULL,
  customer_id INT NOT NULL,
  branch_id INT NOT NULL,
  sale_type ENUM('retail','wholesale','credit') DEFAULT 'retail',
  payment_mode ENUM('cash','knet','wamd','transfer','credit') DEFAULT 'cash',
  subtotal DECIMAL(10,3) DEFAULT 0,
  discount DECIMAL(10,3) DEFAULT 0,
  vat DECIMAL(10,3) DEFAULT 0,
  total DECIMAL(10,3) DEFAULT 0,
  paid_amount DECIMAL(10,3) DEFAULT 0,
  status ENUM('paid','partial','credit','refunded') DEFAULT 'paid',
  payment_ref VARCHAR(100) DEFAULT NULL,
  notes TEXT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id),
  FOREIGN KEY (branch_id) REFERENCES branches(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE invoice_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  product_id INT NOT NULL,
  qty INT DEFAULT 1,
  unit_price DECIMAL(10,3) DEFAULT 0,
  discount DECIMAL(10,3) DEFAULT 0,
  total DECIMAL(10,3) DEFAULT 0,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id),
  FOREIGN KEY (product_id) REFERENCES products(id)
);

INSERT INTO invoices (invoice_number,customer_id,branch_id,sale_type,payment_mode,subtotal,vat,total,paid_amount,status,created_by) VALUES
('INV-2025-0001',2,1,'retail','cash',   25.000,1.250,26.250,26.250,'paid',3),
('INV-2025-0002',3,1,'wholesale','knet',60.000,3.000,63.000,63.000,'paid',3),
('INV-2025-0003',4,1,'credit','credit',200.000,10.000,210.000,0,'credit',3);

INSERT INTO invoice_items (invoice_id,product_id,qty,unit_price,total) VALUES
(1,1,1,25.000,25.000),
(2,3,5,12.000,60.000),
(3,9,4,15.000,60.000),(3,1,2,25.000,50.000),(3,3,5,12.000,60.000);

-- ============================================================
-- EXPENSES
-- ============================================================
CREATE TABLE expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category VARCHAR(80) NOT NULL,
  description TEXT,
  amount DECIMAL(10,3) NOT NULL,
  branch_id INT,
  payment_mode ENUM('cash','knet','transfer') DEFAULT 'cash',
  receipt_ref VARCHAR(60),
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (branch_id) REFERENCES branches(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
);

INSERT INTO expenses (category,description,amount,branch_id,payment_mode,created_by) VALUES
('Rent',      'Monthly rent — Main Branch',          850.000, 1, 'transfer', 1),
('Utilities', 'Electricity & Water — Jan 2025',       120.000, 1, 'cash',     1),
('Salary',    'Staff salaries — Jan 2025',           3200.000, 1, 'transfer', 1),
('Marketing', 'Social media ads — Jan 2025',          200.000, NULL,'transfer',1),
('Rent',      'Monthly rent — Al-Salmiya',            650.000, 2, 'transfer', 1);

-- ============================================================
-- OFFERS / PROMOTIONS
-- ============================================================
CREATE TABLE offers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  description TEXT,
  type ENUM('percent','bogo','promo_code','fixed') DEFAULT 'percent',
  discount_value DECIMAL(10,3) DEFAULT 0,
  promo_code VARCHAR(30),
  applies_to VARCHAR(60) DEFAULT 'all',  -- all / category_id / product_id
  start_date DATE,
  end_date DATE,
  usage_limit INT DEFAULT 0,
  usage_count INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO offers (title,description,type,discount_value,applies_to,start_date,end_date,usage_limit,usage_count,is_active) VALUES
('Summer Sale 2025',   '20% off all Bags',          'percent', 20, '1', '2025-01-15','2025-01-31', 100, 65, 1),
('Buy 1 Get 1 Free',   'T-Shirts & Caps',           'bogo',     0, '3', '2025-01-10','2025-01-20', 100, 40, 1),
('Eid Special Bundle', '15% off — Code EID2025',   'promo_code',15,'all','2025-02-01','2025-02-28', 200,  0, 1);

-- ============================================================
-- SETTINGS
-- ============================================================
CREATE TABLE settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(80) UNIQUE NOT NULL,
  setting_value TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO settings (setting_key, setting_value) VALUES
('company_name',     'RetailPro Kuwait LLC'),
('vat_number',       'KWT-30082024-00841'),
('address',          'Block 4, Shop 12, Salmiya, Kuwait'),
('phone',            '+965 2244-1100'),
('currency',         'KWD'),
('vat_rate',         '5'),
('invoice_prefix',   'INV-'),
('invoice_footer',   'Thank you for shopping with RetailPro. Returns accepted within 7 days with receipt.'),
('tax_type',         'exclusive'),
('app_version',      '2.4.0');

-- ============================================================
-- PAYMENTS LOG
-- ============================================================
CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('customer','supplier') NOT NULL,
  reference_id INT NOT NULL,   -- customer_id or supplier_id
  amount DECIMAL(10,3) NOT NULL,
  payment_mode ENUM('cash','knet','wamd','transfer') DEFAULT 'cash',
  notes TEXT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id)
);

-- ============================================================
-- PERFORMANCE INDEXES
-- ============================================================
ALTER TABLE products        ADD INDEX idx_sku (sku);
ALTER TABLE products        ADD INDEX idx_barcode (barcode);
ALTER TABLE products        ADD INDEX idx_category (category_id);
ALTER TABLE products        ADD INDEX idx_active (is_active);
ALTER TABLE stock           ADD INDEX idx_product_branch (product_id, branch_id);
ALTER TABLE stock_movements ADD INDEX idx_product_branch (product_id, branch_id);
ALTER TABLE stock_movements ADD INDEX idx_created (created_at);
ALTER TABLE invoices        ADD INDEX idx_created (created_at);
ALTER TABLE invoices        ADD INDEX idx_branch_created (branch_id, created_at);
ALTER TABLE invoices        ADD INDEX idx_customer_created (customer_id, created_at);
ALTER TABLE invoices        ADD INDEX idx_payment_mode (payment_mode);
ALTER TABLE invoices        ADD INDEX idx_status (status);
ALTER TABLE invoice_items   ADD INDEX idx_invoice (invoice_id);
ALTER TABLE invoice_items   ADD INDEX idx_product_id (product_id);
ALTER TABLE expenses        ADD INDEX idx_created (created_at);
