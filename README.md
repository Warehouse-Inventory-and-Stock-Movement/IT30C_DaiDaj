# Warehouse Inventory and Stock Movement (StockTrack)

PHP + MySQL prototype for the Advanced Database System midterm.
Requires XAMPP with **PHP 8.1 or newer** (MySQL/MariaDB).

## Setup (5 steps)
1. Copy this folder to `C:\xampp\htdocs\warehouse_inventory` (keep the folder name, or change `BASE_URL` in `config/config.php`).
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open http://localhost/phpmyadmin -> **Import** -> choose `database/warehouse_inventory.sql` -> **Go**.
4. Open http://localhost/warehouse_inventory/
5. Sign in with a demo account.

| Role | Email | Password |
|---|---|---|
| Manager | ana@warehouse.test | Password@123 |
| Staff | maria@warehouse.test | Password@123 |
| Staff | jose@warehouse.test | Password@123 |

Register page: everyone becomes **staff**; entering the manager code `WH-MANAGER-2026` (set in `config/config.php`) creates a **manager**.

## Normalization summary
- **UNF**: the Excel ledger (Movement ID, Date, SKU, Product Name, Category, Supplier Code/Name, Warehouse, Type, Quantity, Handled By).
- **1NF**: atomic values, no repeating groups, key = (Movement_ID, SKU).
- **2NF**: Movement_ID -> date, warehouse, type, quantity, employee; SKU -> product name, category, supplier. Split into MOVEMENT and PRODUCT.
- **3NF**: Supplier_Code -> Supplier_Name, category, warehouse and employee become their own tables. Final: categories, suppliers, warehouses, products, users, stock_movements.

## Business rules in the database
- BR1/BR2: primary keys `sku` and `supplier_code`, foreign keys from products.
- BR3: ledger columns + CHECK on quantity sign.
- BR4: triggers reject UPDATE and DELETE on `stock_movements`.
- BR5: BEFORE INSERT trigger rejects negative stock; PHP also locks the product row in a transaction; `handled_by` is a NOT NULL FK.

## Files
- `database/warehouse_inventory.sql` schema, triggers, view, seed data
- `docs/erd.png` and `docs/erd.dot` the ERD
- `includes/bootstrap.php` DB, sessions, CSRF, auth and role checks
- `manager/` manager dashboard, ledger, products, suppliers, users
- `staff/` staff dashboard, record movement, my movements
- `stock.php` shared stock-on-hand page

## Quick defense tests
- As staff, open `/warehouse_inventory/manager/dashboard.php` -> 403.
- Post an OUT larger than stock -> refused.
- In phpMyAdmin run `UPDATE stock_movements SET quantity=1 WHERE movement_id=1;` -> trigger error.
- Run `SELECT * FROM v_stock_on_hand;` to show live quantities.
