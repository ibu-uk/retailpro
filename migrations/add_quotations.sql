-- ============================================================
-- Migration: Add Quotations System
-- Run: mysql -u root -p retailpro < migrations/add_quotations.sql
-- ============================================================

-- Customer Quotations header
CREATE TABLE IF NOT EXISTS quotations (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  quote_number   VARCHAR(30) UNIQUE NOT NULL,
  customer_id    INT NOT NULL,
  branch_id      INT NOT NULL,
  sale_type      ENUM('retail','wholesale') DEFAULT 'retail',
  status         ENUM('draft','sent','accepted','declined','expired') DEFAULT 'draft',
  valid_days     INT DEFAULT 7,
  subtotal       DECIMAL(10,3) DEFAULT 0,
  tax_amount     DECIMAL(10,3) DEFAULT 0,
  total_amount   DECIMAL(10,3) DEFAULT 0,
  notes          TEXT,
  created_by     INT,
  created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id),
  FOREIGN KEY (branch_id)   REFERENCES branches(id),
  FOREIGN KEY (created_by)  REFERENCES users(id)
);

-- Quotation line items
CREATE TABLE IF NOT EXISTS quotation_items (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  quote_id    INT NOT NULL,
  product_id  INT NOT NULL,
  qty         INT DEFAULT 1,
  unit_price  DECIMAL(10,3) DEFAULT 0,
  FOREIGN KEY (quote_id)   REFERENCES quotations(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Add quote prefix to settings (if not already present)
INSERT IGNORE INTO settings (setting_key, setting_value)
VALUES ('quote_prefix', 'QUO-');

-- Verify
SELECT 'quotations table created' as status;
SELECT 'quotation_items table created' as status;
