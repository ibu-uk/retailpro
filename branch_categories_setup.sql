-- ============================================================
-- RetailPro — Branch Categories Setup
-- Run this ONCE in phpMyAdmin → SQL tab
-- ============================================================
USE retailpro;

-- Step 1: Create the table
CREATE TABLE IF NOT EXISTS branch_categories (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  branch_id   INT NOT NULL,
  category_id INT NOT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY bc_unique (branch_id, category_id),
  FOREIGN KEY (branch_id)   REFERENCES branches(id)   ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- Step 2: Assign ALL categories to ALL branches (safe default)
INSERT IGNORE INTO branch_categories (branch_id, category_id)
SELECT b.id, c.id
FROM branches b
CROSS JOIN categories c
WHERE b.is_active = 1
AND COALESCE(c.is_active, 1) = 1;

-- ============================================================
-- AFTER RUNNING THE ABOVE:
-- Go to Branches page and click "Edit Categories" on each branch
-- to set which categories each branch can sell.
--
-- OR use the quick SQL commands below:
-- ============================================================

-- Example: Branch 1 sells only Clothes & Bags
-- DELETE FROM branch_categories WHERE branch_id = 1;
-- INSERT IGNORE INTO branch_categories (branch_id, category_id)
-- SELECT 1, id FROM categories WHERE name IN ('Clothes','Bags','Wallets','Accessories');

-- Example: Branch 2 sells only Eye Care
-- DELETE FROM branch_categories WHERE branch_id = 2;
-- INSERT IGNORE INTO branch_categories (branch_id, category_id)
-- SELECT 2, id FROM categories WHERE name IN ('Eye Care');

-- Example: Branch 3 sells everything
-- DELETE FROM branch_categories WHERE branch_id = 3;
-- INSERT IGNORE INTO branch_categories (branch_id, category_id)
-- SELECT 3, id FROM categories WHERE COALESCE(is_active,1) = 1;

-- ============================================================
-- USEFUL QUERIES
-- ============================================================

-- See current assignments:
-- SELECT b.name AS branch, GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') AS categories
-- FROM branch_categories bc
-- JOIN branches b   ON b.id = bc.branch_id
-- JOIN categories c ON c.id = bc.category_id
-- GROUP BY b.id, b.name ORDER BY b.name;

-- Add one new category to a branch:
-- INSERT IGNORE INTO branch_categories (branch_id, category_id) VALUES (2, 7);

-- Remove one category from a branch:
-- DELETE FROM branch_categories WHERE branch_id = 2 AND category_id = 3;

-- When you add a NEW category and want it on ALL branches:
-- INSERT IGNORE INTO branch_categories (branch_id, category_id)
-- SELECT b.id, c.id FROM branches b, categories c
-- WHERE c.name = 'YOUR_NEW_CATEGORY_NAME' AND b.is_active = 1;

