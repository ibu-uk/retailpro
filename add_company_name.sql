-- ============================================================
-- Migration: Add company_name to customers table
-- Run this ONCE on your existing database
-- ============================================================

USE retailpro;

-- Add company_name column after the name column
ALTER TABLE customers
    ADD COLUMN company_name VARCHAR(150) DEFAULT NULL AFTER name;

-- Update existing wholesale customers with their company name
-- (Kuwait National Co. is clearly a company)
UPDATE customers SET company_name = 'Kuwait National Company'
    WHERE name = 'Kuwait National Co.' AND type = 'wholesale';

-- Verify
SELECT id, name, company_name, type FROM customers;
