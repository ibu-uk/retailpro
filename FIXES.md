# RetailPro — Fix & Enhancement Log
**Version 3.0.0** | Applied comprehensive audit

---

## 🔴 Critical Bugs Fixed

### 1. Invoice Number Duplication (`includes/config.php`)
**Bug:** `next_invoice_number()` used `COUNT(*)` — after deleting invoices, numbers would repeat (e.g., INV-2025-0003 issued twice).  
**Fix:** Now uses `MAX(id) + 1` to always generate a unique sequential number.

### 2. Stock NOT Restored on Invoice Delete (`reports.php`)
**Bug:** The delete handler ran outside a transaction — if any step failed, stock was partially modified. Also: customer balance was NOT reversed for credit/partial invoices on delete.  
**Fix:** Wrapped in `beginTransaction()`, restores stock per item, reverses customer balance for unpaid amount, logs `stock_movements` entry type='return', deletes linked journal entries.

### 3. Negative Quantity in `stock_movements` (`api/sale.php`)
**Bug:** Stock movements for sales stored `qty = -$qty` in an 'out' type row — double-negating the meaning, breaking inventory reports.  
**Fix:** Qty is now stored as positive; `type='out'` already indicates direction.

### 4. No Stock Validation Before Sale (`api/sale.php`)
**Bug:** Sales could proceed even when qty went negative (overselling), no pre-check existed.  
**Fix:** Added `SELECT ... FOR UPDATE` stock check loop before any inserts. Returns specific error message with product name and available/requested quantities.

### 5. SQL Injection in `reports.php`
**Bug:** `$from` and `$to` date parameters were interpolated directly into SQL queries.  
**Fix:** Added `safe_date()` helper in `config.php` that validates date format with regex before use. All queries now use prepared statements.

### 6. Edit Invoice — Balance & Paid Amount Not Updated (`api/edit_invoice.php`)
**Bug:** Editing invoice status to 'paid' updated `paid_amount = total` but never adjusted the customer's `balance` field. Editing paid_amount was impossible.  
**Fix:** Calculates balance diff (`old_owed - new_owed`) and applies it to customer balance in same transaction.

### 7. `session_start()` Called Multiple Times (`includes/config.php`)
**Bug:** `session_start()` was called unconditionally — could fail if session already started.  
**Fix:** Wrapped in `if (session_status() === PHP_SESSION_NONE)`.

### 8. Invoice Number Sequence in `next_po_number()` Same Bug
**Fix:** Updated `next_po_number()` to use `MAX(id)` as well.

---

## 🟡 Logic Issues Fixed

### 9. Stock Movement qty Direction Convention
Standardized: `qty` field is always **positive**. Direction is determined by `type` column (`in`, `out`, `return`, `transfer`, `damage`, `adjustment`).

### 10. Partial Payment Mode Stored Correctly
`pay_mode = 'partial'` is a meta-mode (UI indicator). The actual payment instrument (`cash`, `knet`, etc.) is now captured via `data.partial_mode` and stored in `invoices.payment_mode`.

### 11. Discount Calculation Edge Cases
- `max(0, ...)` guards prevent negative totals
- `min(100, $disc_value)` prevents >100% percent discount

---

## 🟢 New Features Added

### 12. Full Accounting Module (`accounting.php`)
New page accessible from sidebar (📒 Accounting):
- **Income Statement** — Gross sales → Discounts → Net Revenue → COGS → Gross Profit → Operating Expenses → **Net Profit** with margins %
- **Balance Sheet** — Current Assets (Cash, AR, Inventory), Liabilities (AP), Equity  
- **Cash Flow Statement** — Operating activities: inflows from customers, outflows to suppliers + expenses
- **AR Aging** — Outstanding receivables bucketed by 0-30 / 31-60 / 61-90 / 90+ days
- **Monthly Revenue Trend** — Bar chart + table by month for selected year

### 13. Profit & Loss Tab in Reports (`reports.php`)
Added dedicated P&L tab showing revenue, COGS, gross profit, itemized expenses, and net profit for any date range + branch filter.

### 14. Payment Modes Tab in Reports
Breakdown of invoices and revenue by payment method (Cash / KNET / WAMD / Transfer / Credit).

### 15. Role-Based Access Control Helpers (`includes/config.php`)
Added `has_role()` and `require_role()` functions. Delete invoice now requires `manager` or `super_admin` role.

### 16. Database Migration (`migrations/complete_fix.sql`)
Run once to apply all schema fixes:
- Adds `name_ar`, `barcode`, `emoji`, `sub_category_id`, `origin_country` to `products`
- Adds `name_ar`, `parent_id`, `is_active` to `categories`
- Adds `partial` to `invoices.payment_mode` enum
- Creates `units` table (referenced in products but missing)
- Creates `journal_entries` table for double-entry accounting
- Creates `chart_of_accounts` table
- Seeds Arabic names for categories
- Adds `invoice_id` to `payments` for traceability

### 17. Language Files Updated (EN + AR)
Added 50 new translation keys for all new accounting and finance UI strings, fully bilingual.

---

## 📋 How to Apply

```bash
# 1. Apply database migration
mysql -u root -p retailpro < migrations/complete_fix.sql

# 2. Replace files on your server
# (all files in this archive are ready to deploy)

# 3. Access new accounting:
# → Login → sidebar → 📒 Accounting
# → Or directly: /retailpro/accounting.php
```
