# Retail Pro POS System - Complete User Guide

## Table of Contents
1. [Getting Started](#getting-started)
2. [Dashboard Overview](#dashboard-overview)
3. [Products Management](#products-management)
4. [Categories Management](#categories-management)
5. [Inventory & Stock](#inventory--stock)
6. [POS (Point of Sale)](#pos-point-of-sale)
7. [Customers Management](#customers-management)
8. [Users & Permissions](#users--permissions)
9. [Reports & Analytics](#reports--analytics)
10. [Invoices & Receipts](#invoices--receipts)
11. [Settings](#settings)

---

## Getting Started

### Login
1. Open your browser and go to the system URL
2. Enter your **Username** and **Password**
3. Click **Login**
4. You will see the Dashboard

### Dashboard
The Dashboard shows:
- **Total Sales** - Today's sales amount
- **Total Orders** - Number of orders today
- **Low Stock Products** - Products with less than 10 items
- **Recent Sales** - Latest sales transactions

---

## Dashboard Overview

### Navigation Menu (Left Side)
- **Dashboard** - Main overview page
- **POS** - Point of Sale screen
- **Products** - Manage products
- **Categories** - Manage categories and sub-categories
- **Inventory** - Stock management
- **Customers** - Customer database
- **Sales** - Sales history
- **Invoices** - Invoice management
- **Reports** - Sales reports and analytics
- **Users** - User management
- **Settings** - System configuration

---

## Products Management

### How to Add a New Product

1. Click **Products** in the left menu
2. Click **+ Add Product** button (top right)
3. Fill in the product details:

**Required Fields:**
- **Product Name** - e.g., "Chanel Mini Bag"
- **SKU** - Stock Keeping Unit, e.g., "BAG-2841"

**Optional Fields:**
- **Barcode** - Product barcode number
- **Product Name (Arabic)** - Arabic translation
- **Emoji** - Pick an emoji for visual identification (e.g., 👜)
- **Category** - Select main category
- **Sub-Category** - Select sub-category (if applicable)
- **Brand** - e.g., "Chanel"
- **Origin Country** - Where the product comes from
- **Cost Price** - What you paid for the product
- **Retail Price** - Selling price to customers
- **Wholesale Price** - Price for bulk buyers
- **Color** - Product color
- **Size** - Product size
- **Unit Type** - e.g., "pc" (piece), "kg", "box"
- **Description** - Product details
- **Initial Stock** - Starting quantity for each branch

4. Click **Save**

### How to Edit a Product

1. Go to **Products** page
2. Find the product in the table
3. Click the **✏️ (Edit)** button
4. Make changes to any field
5. Click **Save**

### How to Delete a Product

1. Go to **Products** page
2. Find the product
3. Click the **🗑️ (Delete)** button
4. Confirm deletion

**Note:** Products with sales history cannot be deleted. Use the **Toggle Status** button (🔴/🟢) to disable them instead.

### How to Disable/Enable a Product

1. Go to **Products** page
2. Find the product
3. Click the **🔴/🟢** button
- 🟢 Green = Product is active (shows in POS)
- 🔴 Red = Product is disabled (hidden from POS)

### How to Generate Barcode

1. Go to **Products** page
2. Find the product
3. Click the **🏷️ (Barcode)** button
4. This opens a printable barcode in a new window
5. Print and attach to product

### Product Table Columns

- **Product** - Name with emoji
- **SKU** - Stock Keeping Unit code
- **Barcode** - Barcode number
- **Category** - Product category
- **Unit** - Unit type (pc, kg, etc.)
- **Cost Price** - Purchase price
- **Retail Price** - Selling price
- **Wholesale Price** - Bulk price
- **Stock** - Total stock across all branches
- **Status** - Active/Inactive
- **Actions** - Edit, Toggle, Barcode, Delete

---

## Categories Management

### Understanding Categories

The system has two levels:
- **Main Categories** - Top-level categories (e.g., Bags, Watches, Clothes)
- **Sub-Categories** - Categories under main categories (e.g., Handbags under Bags)

### How to Add a Main Category

1. Click **Categories** in the left menu
2. Click **+ Add Category** button
3. Fill in:
   - **Category Name** - e.g., "Bags"
   - **Emoji** - e.g., 👜
   - **Category Name (Arabic)** - Optional Arabic translation
   - **Description** - Optional description
4. **Do NOT check** "This is a Sub-Category"
5. Click **Save**

### How to Add a Sub-Category

1. Click **Categories** in the left menu
2. Click **+ Add Category** button
3. Fill in:
   - **Category Name** - e.g., "Handbags"
   - **Emoji** - e.g., 👜
   - **Category Name (Arabic)** - Optional
   - **Description** - Optional
4. **Check "This is a Sub-Category"** checkbox
5. **Parent Category** dropdown will appear
6. Select the parent category (e.g., "Bags")
7. Click **Save**

### How to Edit a Category

1. Go to **Categories** page
2. Find the category
3. Click **✏️ (Edit)** button
4. Make changes
5. Click **Save**

### How to Delete a Category

1. Go to **Categories** page
2. Find the category
3. Click **🗑️ (Delete)** button
4. Confirm deletion

**Note:** Categories with products cannot be deleted. Delete or reassign products first.

### Category Table Columns

- **Category** - Name with emoji
- **Type** - "Main" or "Sub"
- **Parent** - Parent category (for sub-categories)
- **Total Products** - Number of products in this category
- **Status** - Active/Inactive
- **Actions** - Edit, Delete

---

## Inventory & Stock

### Understanding Stock

Stock is tracked per branch. Each product has a quantity in each branch.

### How to Add Stock

**Method 1: When Adding Product**
- Set **Initial Stock** when creating a new product
- This adds stock to all active branches

**Method 2: Stock Movement**
1. Go to **Inventory** in the left menu
2. Click **+ Add Stock Movement**
3. Select **Product**
4. Select **Branch**
5. Select **Movement Type**:
   - **In** - Adding stock (purchase, return)
   - **Out** - Removing stock (sale, damage, loss)
6. Enter **Quantity**
7. Enter **Reason** (optional)
8. Click **Save**

### How to Transfer Stock Between Branches

1. Go to **Inventory** page
2. Click **+ Stock Transfer**
3. Select **Product**
4. Select **From Branch** (source)
5. Select **To Branch** (destination)
6. Enter **Quantity**
7. Click **Save**

### Stock Movement History

- View all stock movements in the Inventory page
- See: Date, Product, Branch, Type (In/Out/Transfer), Quantity, Reason

### Low Stock Alerts

- Products with less than 10 items appear in Dashboard
- Also visible in Inventory page with red warning

---

## POS (Point of Sale)

### Opening POS

1. Click **POS** in the left menu
2. Select your **Branch** (if multiple branches)
3. Select **Customer** (optional) or leave as "Walk-in Customer"

### Adding Products to Cart

**Method 1: Click on Product**
1. Click on a product card in the grid
2. Product is added to cart with quantity 1

**Method 2: Search by SKU**
1. Click in the **Search** box
2. Type product SKU or name
3. Press Enter or click the product

**Method 3: Scan Barcode**
1. Click in the **Search** box
2. Scan product barcode with barcode scanner
3. Product is automatically added

### Changing Quantity

1. In the cart, find the product
2. Click **+** to increase quantity
3. Click **-** to decrease quantity
4. Or type the quantity directly

### Removing Product from Cart

1. Click the **🗑️ (Delete)** button next to the product in cart

### Applying Discount

**Extra Discount:**
1. Click the **KWD** button (toggle between percentage and fixed)
   - **%** = Percentage discount
   - **KWD** = Fixed amount discount
2. Enter discount value
3. Discount is applied to total

**Promo Codes:**
1. Enter promo code in the **Promo Code** field
2. Click **Apply**
3. If valid, discount is applied

### Payment Methods

Select payment mode:
- **Cash** - Cash payment
- **Card** - Credit/Debit card
- **K-Net** - Local payment gateway
- **Store Credit** - Use customer's store credit balance
- **Partial** - Split payment (enter amount for each method)

### Completing Sale

1. Review cart items
2. Apply discounts if needed
3. Select payment method
4. Enter amount received (for cash)
5. Click **Complete Sale**
6. Invoice will automatically print

### Sale Buttons Explained

- **+** / **-** - Increase/decrease quantity
- **🗑️** - Remove item from cart
- **KWD/%** - Toggle between percentage and fixed discount
- **Promo Code** - Enter discount code
- **Payment Mode** - Select payment method
- **Complete Sale** - Finalize transaction

---

## Customers Management

### How to Add a Customer

1. Click **Customers** in the left menu
2. Click **+ Add Customer**
3. Fill in:
   - **Name** - Customer name
   - **Phone** - Phone number
   - **Email** - Email address
   - **Type** - Regular, VIP, Wholesale
   - **Address** - Customer address
   - **Store Credit** - Starting credit balance (optional)
4. Click **Save**

### How to Edit Customer

1. Go to **Customers** page
2. Find the customer
3. Click **✏️ (Edit)** button
4. Make changes
5. Click **Save**

### Customer Types

- **Regular** - Standard customer
- **VIP** - Special customer with benefits
- **Wholesale** - Bulk buyer with special pricing

### Store Credit

- Credit balance can be used for payments
- Credit increases when customer returns items
- Credit decreases when used for payment

---

## Users & Permissions

### User Roles

- **Admin** - Full access to all features
- **Manager** - Can manage products, inventory, sales
- **Cashier** - Can only use POS and view sales
- **Viewer** - Read-only access

### How to Add a User

1. Click **Users** in the left menu
2. Click **+ Add User**
3. Fill in:
   - **Username** - Login username
   - **Password** - Login password
   - **Name** - Full name
   - **Email** - Email address
   - **Role** - Select role (Admin, Manager, Cashier, Viewer)
   - **Branch** - Assign branch (if multi-branch)
4. Click **Save**

### How to Edit User

1. Go to **Users** page
2. Find the user
3. Click **✏️ (Edit)** button
4. Make changes
5. Click **Save**

### How to Delete User

1. Go to **Users** page
2. Find the user
3. Click **🗑️ (Delete)** button
4. Confirm

**Note:** Cannot delete the currently logged-in user.

---

## Reports & Analytics

### Sales Report

1. Click **Reports** in the left menu
2. Select **Sales Report**
3. Choose date range
4. Click **Generate**
5. View:
   - Total sales
   - Total orders
   - Average order value
   - Sales by category
   - Sales by payment method

### Inventory Report

1. Click **Reports**
2. Select **Inventory Report**
3. View:
   - Total products
   - Low stock items
   - Stock by branch
   - Stock value

### Customer Report

1. Click **Reports**
2. Select **Customer Report**
3. View:
   - Top customers by spending
   - Customer activity
   - Store credit balances

### Export Reports

1. Generate any report
2. Click **Export** button
3. Choose format (CSV, Excel, PDF)
4. Save file

---

## Invoices & Receipts

### Invoice Printing

After completing a sale:
- Invoice automatically prints (if enabled in settings)
- Shows:
  - Invoice number
  - Date and time
  - Customer details
  - Product list with quantities and prices
  - Subtotal, discounts, taxes
  - Total amount
  - Payment method
  - Change (if cash)

### Invoice Settings

1. Click **Settings** in the left menu
2. Go to **Invoice Settings**
3. Configure:
   - **Default Printer Format** - A4 or Thermal
   - **Show/Hide Logo**
   - **Show/Hide Barcode**
   - **Footer Text**
4. Click **Save**

### Viewing Past Invoices

1. Click **Invoices** in the left menu
2. Find the invoice
3. Click **View** to see details
4. Click **Print** to reprint

---

## Settings

### Company Settings

1. Click **Settings**
2. Go to **Company Settings**
3. Fill in:
   - **Company Name**
   - **Address**
   - **Phone**
   - **Email**
   - **Logo** - Upload company logo
4. Click **Save**

### Invoice Settings

1. Click **Settings**
2. Go to **Invoice Settings**
3. Configure:
   - **Default Printer Format** - A4 or Thermal
   - **Invoice Prefix** - e.g., "INV-"
   - **Footer Text** - Custom message at bottom of invoice
4. Click **Save**

### Currency Settings

1. Click **Settings**
2. Go to **General Settings**
3. Set **Currency** - e.g., "KWD"
4. Click **Save**

### Branch Settings (Multi-Branch)

1. Click **Settings**
2. Go to **Branch Settings**
3. Add branches:
   - **Branch Name**
   - **Address**
   - **Phone**
   - **Manager**
4. Click **Save**

### Tax Settings

1. Click **Settings**
2. Go to **Tax Settings**
3. Set:
   - **Tax Rate** - e.g., 5%
   - **Tax Included in Price** - Yes/No
4. Click **Save**

---

## Quick Tips

### Common Tasks

**Add product quickly:**
- Use keyboard shortcuts in POS
- Scan barcode for fast entry

**Find product:**
- Use search in Products page
- Filter by category

**Check stock:**
- Dashboard shows low stock alerts
- Inventory page shows all stock levels

**Handle returns:**
- Use Stock Movement with "Out" type
- Reason: "Customer Return"

**Manage multiple branches:**
- Always select correct branch in POS
- Use stock transfer to move items

### Troubleshooting

**Product not showing in POS:**
- Check if product is active (green status)
- Check if stock > 0
- Check if assigned to correct branch

**Barcode not scanning:**
- Check barcode scanner is connected
- Ensure cursor is in search box
- Verify barcode is correct

**Discount not applying:**
- Check promo code is valid
- Check promo code is active
- Check expiration date

**Invoice not printing:**
- Check printer is connected
- Check printer format setting
- Check browser pop-up blocker

---

## Keyboard Shortcuts (POS)

- **F2** - Focus search box
- **F4** - Complete sale
- **F8** - Hold current sale
- **Escape** - Clear cart
- **+** - Increase quantity (when item selected)
- **-** - Decrease quantity (when item selected)

---

## Support

If you need help:
1. Check this guide first
2. Contact your system administrator
3. Check for software updates

---

## Security Best Practices

1. **Change passwords regularly**
2. **Don't share login credentials**
3. **Log out when not using system**
4. **Use strong passwords**
5. **Limit user permissions based on role**

---

## Summary

This system provides:
- ✅ Easy product management
- ✅ Category and sub-category organization
- ✅ Real-time inventory tracking
- ✅ Fast POS with barcode support
- ✅ Multiple payment methods
- ✅ Invoice and receipt printing
- ✅ Customer management
- ✅ Sales reporting and analytics
- ✅ Multi-branch support
- ✅ User role-based access

**For training new staff:**
1. Have them read this guide
2. Practice with demo data
3. Start with simple tasks (adding products, basic POS)
4. Gradually introduce advanced features

---

*Last Updated: May 2026*
