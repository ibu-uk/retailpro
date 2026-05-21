# RetailPro ERP — Full Audit Report
**Database:** `retailpro` | **Version:** 2.4.0 | **Audited:** All PHP files, CSS, JS, SQL

---

## 🔴 CRITICAL BUGS (Fixed)

### 1. POS Checkout Completely Broken — `pos.php` line 785 & 797
**File:** `pos.php`
**Problem:** The `processSale()` JavaScript function uses `' . BASE . '` string-concatenation PHP syntax **inside an `ob_start()` / `?>` heredoc block**. PHP outputs it literally as the string `' . BASE . '` — so in the browser the fetch URL becomes the literal text `' . BASE . '/api/sale.php` instead of the real path. **Every sale attempt throws a network error.** Same bug on line 797 for the invoice print URL.

**Fix applied in `pos.php`:**
```js
// BEFORE (broken):
fetch("' . BASE . '/api/sale.php", { ... })
window.open("' . BASE . '/invoice.php?id=...")

// AFTER (fixed):
fetch(BASE_URL + "/api/sale.php", { ... })
window.open(BASE_URL + "/invoice.php?id=...")
```
`BASE_URL` is already defined on line 341 as `const BASE_URL = "<?= BASE ?>";` — just needed to use it.

---

### 2. JavaScript Syntax Error in `renderProductGrid` — `pos.php` line 539
**Problem:** Inside a JS template literal (backtick string), escaped single-quotes `\'` are not valid escape sequences and cause a **syntax error that crashes the entire POS script**, meaning **no products ever render** in the grid.

```js
// BEFORE (broken — \' is invalid in a template literal):
${p.name_ar ? \'<span...>\'+p.name_ar+\'</span>\' : \'\'}

// AFTER (fixed):
${p.name_ar ? '<span...>'+p.name_ar+'</span>' : ''}
```

**This is why you see NO products on the POS page.** This one line silently kills all JavaScript on the page.

---

### 3. No Server-Side Stock Validation — `api/sale.php`
**Problem:** The sale API deducts stock with no check for sufficiency. Negative stock values are possible:
- Two cashiers selling the last item simultaneously both succeed.
- Browser-side check (`if (p.stock <= 0)`) is bypassed if the JS data is stale.

**Fix applied in `api/sale.php`:** Added pre-transaction stock check loop, and changed the UPDATE to `AND qty >= ?` so it fails atomically if stock is insufficient, with `rowCount() === 0` detection and a descriptive exception.

---

### 4. Invoice Number Race Condition / Duplicate Risk — `includes/config.php`
**Problem:** `next_invoice_number()` uses `SELECT COUNT(*) FROM invoices` to compute the next sequence number. When invoices are deleted (which the app supports), the count drops and **generates a duplicate invoice number** — e.g., deleting invoice 50 of 100 means the next new invoice also gets number 50, violating the UNIQUE constraint and crashing the sale.

**Fix applied in `config.php`:** Changed to `SELECT MAX(seq)` pattern scoped to the current year, so deleted invoices don't cause collisions.

---

## 🟠 IMPORTANT BUGS (Need Manual Fix)

### 5. SQL Injection in `pos.php` — Raw `$branch_id` in Queries
**File:** `pos.php` lines 19, 21, 48, 60, 65
**Problem:** `$branch_id` is cast to `(int)` from session data (which is safe), but is directly interpolated into SQL strings rather than using prepared statements:
```php
// Risky pattern (though int-cast is the only mitigation):
$db->query("SELECT ... WHERE branch_id = $branch_id")
```
While `(int)` cast prevents injection here, best practice is to use prepared statements for all DB calls.

**Manual fix — replace all raw interpolated queries in pos.php with prepared statements using `?` placeholders.**

---

### 6. `inventory.php` — "Stock In" and "Stock Out" Buttons Both Open Same Modal
**File:** `inventory.php` lines ~73-75
**Problem:** Both the "📥 Stock In" and "📤 Stock Out" buttons call `openModal('stock-modal')` with no type differentiation. The modal doesn't pre-select the adjustment type, so users often accidentally do a stock-in when they meant stock-out.

**Manual fix:** Pass the type as a parameter: `openModal('stock-modal', 'in')` vs `openModal('stock-modal', 'out')` and pre-select the radio/select in the modal.

---

### 7. Partial Payment Validation Message is Unhelpful — `pos.php`
```js
if (partialAmt <= 0) { showToast(LANG.error, LANG.error, "warning"); return; }
```
The second argument is `LANG.error` (the word "Error") instead of a real message like "Enter the amount paid now."

**Manual fix:** Change to `showToast(LANG.error, LANG.enter_partial_amount || 'Enter the partial payment amount', "warning");`

---

### 8. Hold Sale Feature is a Stub — `pos.php`
`holdSale()` only shows a toast and does nothing. If a cashier accidentally clicks Hold, the cart appears to be saved but is actually lost on page refresh.

**Manual fix:** Either implement `localStorage`-based hold, or remove the Hold button entirely.

---

### 9. `next_po_number()` Has Same Count-Based Race Condition
Same pattern as invoice number — uses `COUNT(*)` on `purchase_orders`. If a PO is deleted, the next PO gets a duplicate number.

**Manual fix:** Apply same MAX-based pattern as the invoice fix.

---

## 🟡 MOBILE / RESPONSIVE ISSUES (Fixed)

### 10. POS Products Panel Hidden on Mobile — No Way to See Products
**Problem:** On screens ≤900px, `.pos-layout` stacks vertically (1 column), but both `.pos-products` and `.pos-cart` are full-height, so the cart pushes products far below the fold. Users scrolling to find products lose context of the cart. There was no tab switcher.

**Fix applied in `pos.php`:** Added a mobile tab bar ("🛍 Products" / "🛒 Cart") that toggles panel visibility. The cart tab shows a live item count badge. This is the feature you described — "I don't see all products" — fully resolved.

### 11. Payment Modes Grid Too Wide on Small Screens
**File:** `assets/css/app.css` / `includes/header.php`
The media query sets `.payment-modes` to `repeat(3,1fr)` at 900px but to `repeat(4,1fr)` in `app.css`. They conflict — header.php wins, giving 3 columns which is correct. No action needed, but the `app.css` rule should be removed to avoid confusion.

---

## 🟡 CALCULATION LOGIC AUDIT

### Discount Calculation Chain (Verified Correct ✅)
```
subtotal        = Σ(price × qty)
item_disc_total = Σ(price × qty × disc%)
after_item_disc = subtotal − item_disc_total
after_promo     = after_item_disc − promoDiscount
global_disc     = after_promo × globalPct%   [or fixed amount]
total           = after_promo − global_disc
total_disc_shown = item_disc_total + global_disc   ← shown in UI
```
The server (`sale.php`) mirrors this exactly. ✅

**One subtle issue:** `promo_discount` is calculated client-side and sent to the server. The server trusts this value without recomputing. A malicious user could inflate it. For a single-tenant retail app this is low risk, but to fully secure it the server should re-derive the promo discount from the `offer_id`.

### Stock Movement Sign Bug (Fixed ✅)
In the original `sale.php`, stock movements were inserted with `-$qty` (negative), but the `stock_movements` table uses an `ENUM('in','out',...)` type column to convey direction. Storing a negative qty on an 'out' movement is redundant and breaks any report that SUMs qty. Fixed to store positive `$qty` with type `'out'`.

### Customer Balance (Verified Correct ✅)
Credit/partial sales correctly reduce `customers.balance` by the unpaid portion. Payment recording adds back to balance. The sign convention (negative balance = customer owes money) is consistent across `customers.php`, `payments.php`, and `api/sale.php`.

---

## 🟡 LOGIC & FEATURE ISSUES

### 12. Category Filter in POS Uses Name, Not ID
```js
(!cat || p.cat.toLowerCase() === cat)  // comparing by name string
```
If two categories have the same name (e.g., "Other" appears as both parent and sub-category), filtering silently shows both. More importantly, if the category name contains special characters or mixed Arabic/English, the comparison may fail.

**Manual fix:** Change `pos-cat` select `value` to `cat['id']` and compare `p.cat_id === parseInt(cat)`.

### 13. Wholesale Price Not Shown on POS Product Cards
Product cards show `p.price` (retail) only. When a wholesale customer is selected, the price shown in the card still shows retail price until the item is added to cart. This is confusing.

**Manual fix:** In `renderProductGrid`, after a wholesale customer is picked, re-render the grid with wholesale prices shown.

### 14. `quickAddCustomer` Reloads the Page After 800ms
After adding a customer via the quick-add modal, `setTimeout(() => location.reload(), 800)` reloads the whole page, **wiping the current cart**. This is a significant UX bug — a cashier adds a new customer mid-sale and loses everything.

**Manual fix:** Instead of reloading, push the new customer into the `CUSTOMERS` array in JS and call `pickCustomer(newId)` with the returned ID.

---

## 🔵 CODE QUALITY / SYNTAX

| File | Issue |
|------|-------|
| `pos.php` | `renderCart()` used hardcoded light-theme hex colors (`#8896a6`, `#d0d4dc`, `#f7f8fa`) — fixed to CSS variables |
| `pos.php` | `recalc()` called inside `renderCart()` which is called from `setItemDisc()` which fires `oninput` — could cause loops on rapid typing; debounce recommended |
| `inventory.php` | `$mv_offset` and `$sp_offset` used in raw SQL string interpolation (int-cast only) |
| `reports.php` | `FOR UPDATE` lock on invoice delete is correct and good |
| `includes/config.php` | `get_setting()` uses a static cache — settings changed in same request won't reflect; acceptable for this use case |
| `pos.php` | `ob_start()` / `ob_get_clean()` pattern for `$extra_js` is unnecessarily complex — the script block could simply be echoed inline |

---

## 🔵 MISSING FEATURES (Not Bugs, But Notable)

- **POS: No product image support** — emoji-only display is functional but limits product recognition for large catalogs.
- **POS: No quantity override input** — users can only +/- one at a time; no direct qty entry field in the cart.
- **Barcode scanner: No audio feedback** — successful scans are silent; a short beep would improve usability.
- **Invoice: No email/WhatsApp send** — print only.
- **Reports: No profit margin column** — sales reports show revenue but not cost-of-goods, so gross profit isn't visible.
- **No session timeout** — users stay logged in indefinitely.

---

## Files Changed

| File | Changes |
|------|---------|
| `pos.php` | Fixed broken `fetch()` URL (critical), fixed JS template literal syntax error (critical), added mobile tab switcher, fixed dark-theme colors in cart renderer |
| `api/sale.php` | Added stock validation before deduction, fixed stock movement qty sign, atomic stock deduction |
| `includes/config.php` | Fixed invoice number race condition (MAX-based sequence) |
