-- ============================================================
-- RetailPro ERP — Clean Database Schema (No Demo Data)
-- Database: shamel (change to your preferred name)
-- Usage: mysql -u root -p < database_clean.sql
-- Default login: admin@retailpro.com / admin123
-- ============================================================

CREATE DATABASE IF NOT EXISTS shamel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE shamel;

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

-- password: admin123
INSERT INTO users (name, email, password, role, branch_id) VALUES
('Super Admin', 'admin@retailpro.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', NULL);

-- ============================================================
-- CATEGORIES
-- ============================================================
CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  name_ar VARCHAR(80),
  emoji VARCHAR(10) DEFAULT '📦',
  parent_id INT DEFAULT NULL,
  sub_category_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- PRODUCTS
-- ============================================================
CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  name_ar VARCHAR(150),
  sku VARCHAR(60) UNIQUE NOT NULL,
  barcode VARCHAR(60),
  category_id INT,
  sub_category_id INT,
  brand VARCHAR(80),
  cost_price DECIMAL(10,3) DEFAULT 0,
  retail_price DECIMAL(10,3) DEFAULT 0,
  wholesale_price DECIMAL(10,3) DEFAULT 0,
  piece_price DECIMAL(10,3) DEFAULT 0,
  piece_wholesale_price DECIMAL(10,3) DEFAULT 0,
  box_price DECIMAL(10,3) DEFAULT 0,
  box_wholesale_price DECIMAL(10,3) DEFAULT 0,
  unit_type ENUM('piece','box','unit','pc','pr','doz') DEFAULT 'piece',
  default_pack_size INT DEFAULT 1,
  color VARCHAR(80),
  size VARCHAR(80),
  emoji VARCHAR(10) DEFAULT '📦',
  description TEXT,
  has_expiry TINYINT(1) DEFAULT 0,
  expiry_alert_days INT DEFAULT 90,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id)
);

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

-- ============================================================
-- STOCK BATCHES (expiry / FIFO tracking)
-- ============================================================
CREATE TABLE stock_batches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  branch_id INT NOT NULL,
  supplier_id INT,
  po_id INT,
  qty_received INT DEFAULT 0,
  qty_remaining INT DEFAULT 0,
  cost_price DECIMAL(10,3) DEFAULT 0,
  expiry_date DATE DEFAULT NULL,
  received_date DATE DEFAULT NULL,
  status ENUM('active','depleted','expired') DEFAULT 'active',
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (branch_id) REFERENCES branches(id)
);

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
  batch_id INT DEFAULT NULL,
  supplier_id INT DEFAULT NULL,
  expiry_date DATE DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (branch_id) REFERENCES branches(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ============================================================
-- CUSTOMERS
-- ============================================================
CREATE TABLE customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  name_ar VARCHAR(150),
  email VARCHAR(150),
  phone VARCHAR(30),
  company_name VARCHAR(150),
  type ENUM('retail','wholesale') DEFAULT 'retail',
  credit_limit DECIMAL(10,3) DEFAULT 0,
  balance DECIMAL(10,3) DEFAULT 0,
  address TEXT,
  address_ar TEXT,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Walk-in customer is required by the system (must be ID = 1)
INSERT INTO customers (name, email, phone, type, credit_limit, balance) VALUES
('Walk-in Customer', '', '', 'retail', 0, 0);

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
  balance DECIMAL(10,3) DEFAULT 0,
  address TEXT,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

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
  unit_label VARCHAR(30) DEFAULT '',
  stock_deduct INT DEFAULT 1,
  batch_id INT DEFAULT NULL,
  supplier_id INT DEFAULT NULL,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id),
  FOREIGN KEY (product_id) REFERENCES products(id)
);

-- ============================================================
-- REFUNDS
-- ============================================================
CREATE TABLE refunds (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  product_id INT NOT NULL,
  branch_id INT NOT NULL,
  qty INT DEFAULT 1,
  refund_amount DECIMAL(10,3) DEFAULT 0,
  reason TEXT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id),
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (branch_id) REFERENCES branches(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
);

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
  applies_to VARCHAR(60) DEFAULT 'all',
  start_date DATE,
  end_date DATE,
  usage_limit INT DEFAULT 0,
  usage_count INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- PAYMENTS LOG
-- ============================================================
CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('customer','supplier') NOT NULL,
  reference_id INT NOT NULL,
  amount DECIMAL(10,3) NOT NULL,
  payment_mode ENUM('cash','knet','wamd','transfer') DEFAULT 'cash',
  notes TEXT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id)
);

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
('company_name',     'Your Company Name'),
('vat_number',       ''),
('address',          ''),
('phone',            ''),
('currency',         'KWD'),
('vat_rate',         '0'),
('invoice_prefix',   'INV-'),
('invoice_footer',   'Thank you for your business.'),
('tax_type',         'exclusive'),
('printer_format',   'a4'),
('app_version',      '2.4.0');

-- ============================================================
-- JOURNAL / ACCOUNTING
-- ============================================================
CREATE TABLE journal_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(60),
  description TEXT,
  debit_account VARCHAR(80),
  credit_account VARCHAR(80),
  amount DECIMAL(10,3) DEFAULT 0,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id)
);

-- ============================================================
-- AUDIT LOG
-- ============================================================
CREATE TABLE audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  action VARCHAR(80),
  table_name VARCHAR(80),
  record_id INT,
  old_data JSON,
  new_data JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ============================================================
-- BRANCH CATEGORIES (optional per-branch product visibility)
-- ============================================================
CREATE TABLE branch_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  branch_id INT NOT NULL,
  category_id INT NOT NULL,
  UNIQUE KEY branch_cat (branch_id, category_id),
  FOREIGN KEY (branch_id) REFERENCES branches(id),
  FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- ============================================================
-- HELD SALES (POS on-hold orders)
-- ============================================================
CREATE TABLE held_sales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  branch_id INT NOT NULL,
  user_id INT NOT NULL,
  customer_id INT DEFAULT 1,
  label VARCHAR(100),
  cart_data JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (branch_id) REFERENCES branches(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ============================================================
-- QUOTATIONS
-- ============================================================
CREATE TABLE quotations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quotation_number VARCHAR(30) UNIQUE NOT NULL,
  customer_id INT NOT NULL,
  branch_id INT NOT NULL,
  subtotal DECIMAL(10,3) DEFAULT 0,
  discount DECIMAL(10,3) DEFAULT 0,
  total DECIMAL(10,3) DEFAULT 0,
  status ENUM('draft','sent','accepted','rejected','expired') DEFAULT 'draft',
  valid_until DATE,
  notes TEXT,
  created_by INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id),
  FOREIGN KEY (branch_id) REFERENCES branches(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE quotation_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quotation_id INT NOT NULL,
  product_id INT NOT NULL,
  qty INT DEFAULT 1,
  unit_price DECIMAL(10,3) DEFAULT 0,
  total DECIMAL(10,3) DEFAULT 0,
  unit_label VARCHAR(30) DEFAULT '',
  FOREIGN KEY (quotation_id) REFERENCES quotations(id),
  FOREIGN KEY (product_id) REFERENCES products(id)
);

-- ============================================================
-- PERFORMANCE INDEXES
-- ============================================================
ALTER TABLE products        ADD INDEX idx_sku (sku);
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
