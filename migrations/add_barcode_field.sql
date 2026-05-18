-- Add dedicated barcode field to products
ALTER TABLE products ADD COLUMN barcode VARCHAR(60) DEFAULT NULL AFTER sku;

-- Update existing products to use SKU as barcode
UPDATE products SET barcode = sku WHERE barcode IS NULL;
