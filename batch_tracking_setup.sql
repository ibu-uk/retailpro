-- ============================================================
-- RetailPro — Batch Tracking + Supplier Traceability + Expiry
-- Run this ONCE in phpMyAdmin → SQL tab
-- ============================================================
USE retailpro;

-- ── 1. PRODUCT SUPPLIERS — price list per supplier per product
CREATE TABLE IF NOT EXISTS product_suppliers (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  product_id      INT NOT NULL,
  supplier_id     INT NOT NULL,
  cost_price      DECIMAL(10,3) NOT NULL DEFAULT 0,
  min_order_qty   INT DEFAULT 1,
  lead_days       INT DEFAULT 7,
  is_preferred    TINYINT(1) DEFAULT 0,
  notes           TEXT,
  updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY ps_unique (product_id, supplier_id),
  FOREIGN KEY (product_id)  REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
);

-- ── 2. STOCK BATCHES — every delivery from a supplier = one batch
CREATE TABLE IF NOT EXISTS stock_batches (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  batch_number    VARCHAR(60) NOT NULL UNIQUE,
  product_id      INT NOT NULL,
  supplier_id     INT NOT NULL,
  branch_id       INT NOT NULL,
  po_id           INT DEFAULT NULL,          -- linked purchase order
  qty_received    INT NOT NULL DEFAULT 0,    -- how many came in
  qty_remaining   INT NOT NULL DEFAULT 0,    -- how many still in stock
  cost_price      DECIMAL(10,3) DEFAULT 0,   -- what you paid per unit
  expiry_date     DATE DEFAULT NULL,         -- expiry (for lenses, food etc)
  manufacture_date DATE DEFAULT NULL,
  lot_number      VARCHAR(80) DEFAULT NULL,  -- supplier's lot/batch ref
  received_date   DATE NOT NULL,
  received_by     INT DEFAULT NULL,
  notes           TEXT,
  status          ENUM('active','low','depleted','expired') DEFAULT 'active',
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id)  REFERENCES products(id),
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
  FOREIGN KEY (branch_id)   REFERENCES branches(id),
  FOREIGN KEY (po_id)       REFERENCES purchase_orders(id) ON DELETE SET NULL,
  FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ── 3. INVOICE ITEMS — add batch tracking columns
ALTER TABLE invoice_items
  ADD COLUMN IF NOT EXISTS batch_id   INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS supplier_id INT DEFAULT NULL;

ALTER TABLE invoice_items
  ADD CONSTRAINT IF NOT EXISTS fk_ii_batch
    FOREIGN KEY (batch_id) REFERENCES stock_batches(id) ON DELETE SET NULL;

-- ── 4. STOCK MOVEMENTS — link to batch
ALTER TABLE stock_movements
  ADD COLUMN IF NOT EXISTS batch_id   INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS supplier_id INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS expiry_date DATE DEFAULT NULL;

-- ── 5. PRODUCTS — add last supplier + expiry tracking flag
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS has_expiry       TINYINT(1) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS expiry_alert_days INT DEFAULT 90,
  ADD COLUMN IF NOT EXISTS last_supplier_id  INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS last_purchase_price DECIMAL(10,3) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS last_purchase_date  DATE DEFAULT NULL;

-- ── 6. PURCHASE ORDER ITEMS — add batch + expiry
ALTER TABLE purchase_order_items
  ADD COLUMN IF NOT EXISTS expiry_date    DATE DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS lot_number     VARCHAR(80) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS batch_id       INT DEFAULT NULL;

-- ── 7. INDEX for performance
CREATE INDEX IF NOT EXISTS idx_batch_product  ON stock_batches(product_id);
CREATE INDEX IF NOT EXISTS idx_batch_supplier ON stock_batches(supplier_id);
CREATE INDEX IF NOT EXISTS idx_batch_expiry   ON stock_batches(expiry_date);
CREATE INDEX IF NOT EXISTS idx_batch_status   ON stock_batches(status);
CREATE INDEX IF NOT EXISTS idx_ii_batch       ON invoice_items(batch_id);

-- ── 8. Auto-update batch status based on qty
DELIMITER $$
DROP TRIGGER IF EXISTS trg_batch_status_update$$
CREATE TRIGGER trg_batch_status_update
BEFORE UPDATE ON stock_batches
FOR EACH ROW
BEGIN
  IF NEW.qty_remaining <= 0 THEN
    SET NEW.status = 'depleted';
  ELSEIF NEW.expiry_date IS NOT NULL AND NEW.expiry_date < CURDATE() THEN
    SET NEW.status = 'expired';
  ELSEIF NEW.qty_remaining <= (NEW.qty_received * 0.1) THEN
    SET NEW.status = 'low';
  ELSE
    SET NEW.status = 'active';
  END IF;
END$$
DELIMITER ;

-- ── VERIFY ──
SELECT 'Tables created successfully' AS status;
SELECT TABLE_NAME, TABLE_ROWS
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'retailpro'
AND TABLE_NAME IN ('stock_batches','product_suppliers')
ORDER BY TABLE_NAME;
