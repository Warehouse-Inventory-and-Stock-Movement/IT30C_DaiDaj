DROP DATABASE IF EXISTS warehouse_inventory;
CREATE DATABASE warehouse_inventory
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE warehouse_inventory;

CREATE TABLE categories (
  category_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_name VARCHAR(60)  NOT NULL,
  PRIMARY KEY (category_id),
  UNIQUE KEY uq_category_name (category_name)
) ENGINE=InnoDB;

CREATE TABLE suppliers (
  supplier_code VARCHAR(10)  NOT NULL,
  supplier_name VARCHAR(100) NOT NULL,
  contact_email VARCHAR(120) NULL,
  contact_phone VARCHAR(30)  NULL,
  PRIMARY KEY (supplier_code),
  UNIQUE KEY uq_supplier_name (supplier_name)
) ENGINE=InnoDB;

CREATE TABLE warehouses (
  warehouse_code VARCHAR(10)  NOT NULL,
  warehouse_name VARCHAR(80)  NOT NULL,
  PRIMARY KEY (warehouse_code)
) ENGINE=InnoDB;

CREATE TABLE products (
  sku           VARCHAR(20)  NOT NULL,
  product_name  VARCHAR(100) NOT NULL,
  category_id   INT UNSIGNED NOT NULL,
  supplier_code VARCHAR(10)  NOT NULL,
  reorder_level INT UNSIGNED NOT NULL DEFAULT 10,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (sku),
  CONSTRAINT fk_products_category
    FOREIGN KEY (category_id)   REFERENCES categories (category_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_products_supplier
    FOREIGN KEY (supplier_code) REFERENCES suppliers (supplier_code)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE users (
  user_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_code VARCHAR(20)  NOT NULL,
  full_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('manager','staff') NOT NULL DEFAULT 'staff',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_employee_code (employee_code),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE stock_movements (
  movement_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  movement_date  DATE         NOT NULL,
  sku            VARCHAR(20)  NOT NULL,
  warehouse_code VARCHAR(10)  NOT NULL,
  movement_type  ENUM('IN','OUT','ADJUST') NOT NULL,
  quantity       INT          NOT NULL,
  handled_by     INT UNSIGNED NOT NULL,
  remarks        VARCHAR(255) NULL,
  posted_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (movement_id),
  KEY idx_mov_sku_wh   (sku, warehouse_code),
  KEY idx_mov_date     (movement_date),
  KEY idx_mov_handler  (handled_by),
  CONSTRAINT fk_mov_product   FOREIGN KEY (sku)            REFERENCES products   (sku)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_mov_warehouse FOREIGN KEY (warehouse_code) REFERENCES warehouses (warehouse_code)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_mov_employee  FOREIGN KEY (handled_by)     REFERENCES users      (user_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_mov_quantity CHECK (
    (movement_type IN ('IN','OUT') AND quantity > 0) OR
    (movement_type = 'ADJUST'      AND quantity <> 0)
  )
) ENGINE=InnoDB;


DELIMITER $$

CREATE TRIGGER trg_movements_bi
BEFORE INSERT ON stock_movements
FOR EACH ROW
BEGIN
  DECLARE v_on_hand BIGINT DEFAULT 0;

  IF NEW.movement_type IN ('IN','OUT') AND NEW.quantity <= 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'IN and OUT quantities must be greater than zero.';
  END IF;

  IF NEW.movement_type = 'ADJUST' AND NEW.quantity = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'An ADJUST quantity cannot be zero.';
  END IF;

  IF NEW.movement_type IN ('OUT','ADJUST') THEN
    SELECT COALESCE(SUM(CASE movement_type WHEN 'OUT' THEN -quantity ELSE quantity END), 0)
      INTO v_on_hand
      FROM stock_movements
     WHERE sku = NEW.sku AND warehouse_code = NEW.warehouse_code;

    IF NEW.movement_type = 'OUT' AND v_on_hand - NEW.quantity < 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Insufficient stock: this OUT movement would make stock negative.';
    END IF;

    IF NEW.movement_type = 'ADJUST' AND v_on_hand + NEW.quantity < 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Insufficient stock: this adjustment would make stock negative.';
    END IF;
  END IF;
END$$

CREATE TRIGGER trg_movements_bu
BEFORE UPDATE ON stock_movements
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Posted stock movements cannot be modified. Post an ADJUST movement instead.';
END$$

CREATE TRIGGER trg_movements_bd
BEFORE DELETE ON stock_movements
FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Posted stock movements cannot be deleted. Post an ADJUST movement instead.';
END$$

DELIMITER ;

CREATE VIEW v_stock_on_hand AS
SELECT p.sku,
       p.product_name,
       p.reorder_level,
       p.is_active,
       c.category_id,
       c.category_name,
       s.supplier_code,
       s.supplier_name,
       w.warehouse_code,
       w.warehouse_name,
       COALESCE(SUM(CASE m.movement_type WHEN 'OUT' THEN -m.quantity ELSE m.quantity END), 0) AS on_hand
  FROM products p
  JOIN categories c ON c.category_id   = p.category_id
  JOIN suppliers  s ON s.supplier_code = p.supplier_code
  CROSS JOIN warehouses w
  LEFT JOIN stock_movements m
         ON m.sku = p.sku AND m.warehouse_code = w.warehouse_code
 GROUP BY p.sku, p.product_name, p.reorder_level, p.is_active,
          c.category_id, c.category_name, s.supplier_code, s.supplier_name,
          w.warehouse_code, w.warehouse_name;

INSERT INTO categories (category_id, category_name) VALUES
  (1, 'Staples'),
  (2, 'Grocery'),
  (3, 'Hygiene');

INSERT INTO suppliers (supplier_code, supplier_name) VALUES
  ('SUP-01', 'FarmLink'),
  ('SUP-02', 'FoodSource');

INSERT INTO warehouses (warehouse_code, warehouse_name) VALUES
  ('WH-A', 'Warehouse A'),
  ('WH-B', 'Warehouse B');

INSERT INTO products (sku, product_name, category_id, supplier_code, reorder_level) VALUES
  ('SKU-01', 'Rice 5kg',       1, 'SUP-01', 40),
  ('SKU-02', 'Cooking oil 1L', 2, 'SUP-02', 20),
  ('SKU-03', 'Soap bar',       3, 'SUP-02', 30);

INSERT INTO users (user_id, employee_code, full_name, email, password_hash, role) VALUES
  (1, 'E-01', 'Maria Santos', 'maria@warehouse.test', '$2y$10$uy5ZSpevvPaUctfggt/oTuq.xzaKcX0JCE5CGwsWO/BRXLQu3x/Dy', 'staff'),
  (2, 'E-02', 'Jose Reyes',   'jose@warehouse.test',  '$2y$10$uy5ZSpevvPaUctfggt/oTuq.xzaKcX0JCE5CGwsWO/BRXLQu3x/Dy', 'staff'),
  (3, 'E-03', 'Ana Cruz',     'ana@warehouse.test',   '$2y$10$uy5ZSpevvPaUctfggt/oTuq.xzaKcX0JCE5CGwsWO/BRXLQu3x/Dy', 'manager');

INSERT INTO stock_movements
  (movement_id, movement_date, sku, warehouse_code, movement_type, quantity, handled_by, remarks) VALUES
  (1, '2026-09-01', 'SKU-01', 'WH-A', 'IN',     40, 1, 'Opening receipt from FarmLink'),
  (2, '2026-09-02', 'SKU-02', 'WH-A', 'IN',     50, 2, 'Receipt from FoodSource'),
  (3, '2026-09-04', 'SKU-01', 'WH-A', 'OUT',     8, 1, 'Customer order'),
  (4, '2026-09-07', 'SKU-03', 'WH-B', 'IN',    100, 2, 'Receipt from FoodSource'),
  (5, '2026-09-09', 'SKU-02', 'WH-A', 'OUT',    12, 1, 'Customer order'),
  (6, '2026-09-10', 'SKU-03', 'WH-B', 'ADJUST', -2, 2, 'Damaged units written off');
