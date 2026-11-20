-- =====================================================================
--  FerreControl - Base de datos MySQL / MariaDB (XAMPP)
--  Importar desde phpMyAdmin:  Importar > elegir este archivo > Continuar
--
--  ATENCIÓN: este script BORRA y vuelve a crear las tablas de la base
--  `ferrecontrol`. No lo importes de nuevo si ya cargaste datos reales.
--
--  Este archivo NO contiene usuarios ni contraseñas. El administrador
--  inicial se crea ejecutando http://localhost/ferrecontrol/setup.php
--  (la contraseña se guarda con password_hash()).
-- =====================================================================
CREATE DATABASE IF NOT EXISTS `ferrecontrol` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `ferrecontrol`;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_logs, inventory_count_items, inventory_counts, stock_movements,
  cash_movements, sale_return_items, sale_returns, payments, sale_items, sales,
  purchase_items, purchases, payroll_items, payrolls, payroll_concepts, leaves,
  attendances, employees, expense_categories, payment_methods, products, suppliers,
  categories, settings, users;

-- ---------------------------------------------------------------- usuarios
CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(120) NOT NULL,
  username VARCHAR(50) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','deposito','personal') NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login DATETIME NULL,
  failed_attempts INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  setting_key VARCHAR(60) NOT NULL,
  setting_value TEXT NULL,
  description VARCHAR(200) NULL,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- stock
CREATE TABLE categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE suppliers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  cuit VARCHAR(20) NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(120) NULL,
  address VARCHAR(200) NULL,
  contact VARCHAR(100) NULL,
  notes TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_suppliers_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(30) NOT NULL,
  barcode VARCHAR(40) NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  category_id INT UNSIGNED NOT NULL,
  brand VARCHAR(60) NULL,
  unit VARCHAR(20) NOT NULL DEFAULT 'unidad',
  last_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
  avg_cost DECIMAL(14,4) NOT NULL DEFAULT 0,
  sale_price DECIMAL(14,2) NOT NULL DEFAULT 0,
  markup_type ENUM('recargo','margen') NOT NULL DEFAULT 'recargo',
  markup_pct DECIMAL(7,2) NOT NULL DEFAULT 0,
  stock INT NOT NULL DEFAULT 0,
  min_stock INT NOT NULL DEFAULT 0,
  location VARCHAR(60) NULL,
  supplier_id INT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_code (code),
  UNIQUE KEY uq_products_barcode (barcode),
  KEY idx_products_name (name),
  KEY idx_products_category (category_id),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id),
  CONSTRAINT fk_products_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id),
  CONSTRAINT chk_products_stock CHECK (stock >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_methods (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(60) NOT NULL,
  commission_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
  is_cash TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payment_methods_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE expense_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  cost_type ENUM('fijo','variable','mixto') NOT NULL DEFAULT 'variable',
  fixed_pct DECIMAL(5,2) NOT NULL DEFAULT 50,
  active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_expense_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- personal
CREATE TABLE employees (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  dni VARCHAR(15) NOT NULL,
  position VARCHAR(80) NOT NULL,
  hire_date DATE NOT NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(120) NULL,
  address VARCHAR(200) NULL,
  contract_type ENUM('tiempo_completo','part_time','eventual','pasantia') NOT NULL DEFAULT 'tiempo_completo',
  base_salary DECIMAL(14,2) NOT NULL DEFAULT 0,
  status ENUM('activo','licencia','baja') NOT NULL DEFAULT 'activo',
  tasks TEXT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_employees_dni (dni),
  KEY idx_employees_name (last_name, first_name),
  CONSTRAINT fk_employees_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE attendances (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_id INT UNSIGNED NOT NULL,
  att_date DATE NOT NULL,
  shift TINYINT NOT NULL DEFAULT 1,
  check_in TIME NULL,
  check_out TIME NULL,
  status ENUM('presente','ausente','tarde','licencia','franco') NOT NULL DEFAULT 'presente',
  justified TINYINT(1) NOT NULL DEFAULT 0,
  justification VARCHAR(255) NULL,
  user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_attendance (employee_id, att_date, shift),
  KEY idx_attendance_date (att_date),
  CONSTRAINT fk_att_employee FOREIGN KEY (employee_id) REFERENCES employees (id),
  CONSTRAINT fk_att_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leaves (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_id INT UNSIGNED NOT NULL,
  leave_type VARCHAR(60) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  reason VARCHAR(255) NULL,
  status ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  approved_by INT UNSIGNED NULL,
  notes TEXT NULL,
  user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_leaves_employee (employee_id, start_date),
  CONSTRAINT fk_leaves_employee FOREIGN KEY (employee_id) REFERENCES employees (id),
  CONSTRAINT fk_leaves_approver FOREIGN KEY (approved_by) REFERENCES users (id),
  CONSTRAINT fk_leaves_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payroll_concepts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  kind ENUM('remunerativo','no_remunerativo','descuento') NOT NULL,
  calc_type ENUM('fijo','porcentaje_basico','porcentaje_remunerativo') NOT NULL DEFAULT 'fijo',
  value DECIMAL(12,2) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  validated TINYINT(1) NOT NULL DEFAULT 0,
  validated_by INT UNSIGNED NULL,
  validated_at DATETIME NULL,
  notes VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payroll_concepts_name (name),
  CONSTRAINT fk_concepts_validator FOREIGN KEY (validated_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payrolls (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_id INT UNSIGNED NOT NULL,
  period CHAR(7) NOT NULL,
  basic_salary DECIMAL(14,2) NOT NULL,
  total_remunerative DECIMAL(14,2) NOT NULL DEFAULT 0,
  total_non_remunerative DECIMAL(14,2) NOT NULL DEFAULT 0,
  total_deductions DECIMAL(14,2) NOT NULL DEFAULT 0,
  gross_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  net_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  status ENUM('borrador','aprobada','pagada','anulada') NOT NULL DEFAULT 'borrador',
  notes TEXT NULL,
  generated_by INT UNSIGNED NOT NULL,
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  approved_by INT UNSIGNED NULL,
  approved_at DATETIME NULL,
  paid_at DATETIME NULL,
  cash_movement_id INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_payrolls_employee_period (employee_id, period),
  CONSTRAINT fk_payrolls_employee FOREIGN KEY (employee_id) REFERENCES employees (id),
  CONSTRAINT fk_payrolls_generator FOREIGN KEY (generated_by) REFERENCES users (id),
  CONSTRAINT fk_payrolls_approver FOREIGN KEY (approved_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payroll_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  payroll_id INT UNSIGNED NOT NULL,
  concept VARCHAR(120) NOT NULL,
  kind ENUM('remunerativo','no_remunerativo','descuento') NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  detail VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY idx_payroll_items_payroll (payroll_id),
  CONSTRAINT fk_payroll_items_payroll FOREIGN KEY (payroll_id) REFERENCES payrolls (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- compras
CREATE TABLE purchases (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  number VARCHAR(20) NOT NULL,
  supplier_id INT UNSIGNED NOT NULL,
  purchase_date DATE NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
  discount DECIMAL(14,2) NOT NULL DEFAULT 0,
  extra_costs DECIMAL(14,2) NOT NULL DEFAULT 0,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  payment_status ENUM('pagada','pendiente') NOT NULL DEFAULT 'pagada',
  status ENUM('confirmada','anulada') NOT NULL DEFAULT 'confirmada',
  reference VARCHAR(80) NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  voided_at DATETIME NULL,
  voided_by INT UNSIGNED NULL,
  void_reason VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_purchases_number (number),
  KEY idx_purchases_date (purchase_date),
  CONSTRAINT fk_purchases_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id),
  CONSTRAINT fk_purchases_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT fk_purchases_voider FOREIGN KEY (voided_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE purchase_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  purchase_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity INT NOT NULL,
  unit_cost DECIMAL(14,2) NOT NULL,
  line_total DECIMAL(14,2) NOT NULL,
  effective_unit_cost DECIMAL(14,4) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_purchase_items_purchase (purchase_id),
  KEY idx_purchase_items_product (product_id),
  CONSTRAINT fk_purchase_items_purchase FOREIGN KEY (purchase_id) REFERENCES purchases (id),
  CONSTRAINT fk_purchase_items_product FOREIGN KEY (product_id) REFERENCES products (id),
  CONSTRAINT chk_purchase_items_qty CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- ventas
CREATE TABLE sales (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  number VARCHAR(20) NOT NULL,
  sale_date DATETIME NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  customer_name VARCHAR(120) NULL,
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
  discount_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  paid_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  returned_total DECIMAL(14,2) NOT NULL DEFAULT 0,
  payment_status ENUM('pagada','parcial','pendiente') NOT NULL DEFAULT 'pendiente',
  status ENUM('confirmada','anulada') NOT NULL DEFAULT 'confirmada',
  cogs DECIMAL(14,2) NOT NULL DEFAULT 0,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  voided_at DATETIME NULL,
  voided_by INT UNSIGNED NULL,
  void_reason VARCHAR(255) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sales_number (number),
  KEY idx_sales_date (sale_date),
  CONSTRAINT fk_sales_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT fk_sales_voider FOREIGN KEY (voided_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sale_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sale_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(14,2) NOT NULL,
  unit_cost DECIMAL(14,4) NOT NULL,
  line_total DECIMAL(14,2) NOT NULL,
  returned_qty INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_sale_items_sale (sale_id),
  KEY idx_sale_items_product (product_id),
  CONSTRAINT fk_sale_items_sale FOREIGN KEY (sale_id) REFERENCES sales (id),
  CONSTRAINT fk_sale_items_product FOREIGN KEY (product_id) REFERENCES products (id),
  CONSTRAINT chk_sale_items_qty CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sale_id INT UNSIGNED NOT NULL,
  payment_method_id INT UNSIGNED NOT NULL,
  type ENUM('cobro','reembolso') NOT NULL DEFAULT 'cobro',
  amount DECIMAL(14,2) NOT NULL,
  commission DECIMAL(14,2) NOT NULL DEFAULT 0,
  received_amount DECIMAL(14,2) NULL,
  change_amount DECIMAL(14,2) NULL,
  reference VARCHAR(80) NULL,
  paid_at DATETIME NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY idx_payments_sale (sale_id),
  KEY idx_payments_date (paid_at),
  CONSTRAINT fk_payments_sale FOREIGN KEY (sale_id) REFERENCES sales (id),
  CONSTRAINT fk_payments_method FOREIGN KEY (payment_method_id) REFERENCES payment_methods (id),
  CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT chk_payments_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sale_returns (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sale_id INT UNSIGNED NOT NULL,
  return_date DATETIME NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  reason VARCHAR(255) NOT NULL,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  restock TINYINT(1) NOT NULL DEFAULT 1,
  refund_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
  refund_method_id INT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY idx_sale_returns_sale (sale_id),
  CONSTRAINT fk_sale_returns_sale FOREIGN KEY (sale_id) REFERENCES sales (id),
  CONSTRAINT fk_sale_returns_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT fk_sale_returns_method FOREIGN KEY (refund_method_id) REFERENCES payment_methods (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sale_return_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  return_id INT UNSIGNED NOT NULL,
  sale_item_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(14,2) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_sale_return_items_return (return_id),
  CONSTRAINT fk_sri_return FOREIGN KEY (return_id) REFERENCES sale_returns (id),
  CONSTRAINT fk_sri_item FOREIGN KEY (sale_item_id) REFERENCES sale_items (id),
  CONSTRAINT fk_sri_product FOREIGN KEY (product_id) REFERENCES products (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- caja
CREATE TABLE cash_movements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  mov_date DATE NOT NULL,
  type ENUM('ingreso','egreso') NOT NULL,
  concept VARCHAR(200) NOT NULL,
  category VARCHAR(80) NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  status ENUM('cobrado','pagado','pendiente','anulado') NOT NULL,
  payment_method_id INT UNSIGNED NULL,
  cost_type ENUM('fijo','variable','mixto','no_aplica') NOT NULL DEFAULT 'no_aplica',
  fixed_pct DECIMAL(5,2) NULL,
  source ENUM('venta','compra','manual','sueldo','devolucion') NOT NULL DEFAULT 'manual',
  sale_id INT UNSIGNED NULL,
  purchase_id INT UNSIGNED NULL,
  payroll_id INT UNSIGNED NULL,
  payment_id INT UNSIGNED NULL,
  supplier_id INT UNSIGNED NULL,
  reference VARCHAR(80) NULL,
  paid_date DATE NULL,
  user_id INT UNSIGNED NOT NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cash_payment (payment_id),
  KEY idx_cash_date (mov_date),
  KEY idx_cash_type_status (type, status),
  CONSTRAINT fk_cash_method FOREIGN KEY (payment_method_id) REFERENCES payment_methods (id),
  CONSTRAINT fk_cash_sale FOREIGN KEY (sale_id) REFERENCES sales (id),
  CONSTRAINT fk_cash_purchase FOREIGN KEY (purchase_id) REFERENCES purchases (id),
  CONSTRAINT fk_cash_payroll FOREIGN KEY (payroll_id) REFERENCES payrolls (id),
  CONSTRAINT fk_cash_payment FOREIGN KEY (payment_id) REFERENCES payments (id),
  CONSTRAINT fk_cash_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id),
  CONSTRAINT fk_cash_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT chk_cash_amount CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_counts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  count_date DATE NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  status ENUM('pendiente','confirmado','rechazado') NOT NULL DEFAULT 'pendiente',
  notes VARCHAR(255) NULL,
  confirmed_by INT UNSIGNED NULL,
  confirmed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_counts_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT fk_counts_confirmer FOREIGN KEY (confirmed_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_count_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  count_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  system_stock INT NOT NULL,
  counted_stock INT NOT NULL,
  difference INT NOT NULL,
  reason VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY idx_count_items_count (count_id),
  CONSTRAINT fk_count_items_count FOREIGN KEY (count_id) REFERENCES inventory_counts (id),
  CONSTRAINT fk_count_items_product FOREIGN KEY (product_id) REFERENCES products (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_movements (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT UNSIGNED NOT NULL,
  mov_type ENUM('compra','venta','devolucion_cliente','devolucion_proveedor','ajuste_entrada','ajuste_salida','rotura','anulacion_compra','anulacion_venta','inventario_fisico') NOT NULL,
  direction ENUM('entrada','salida') NOT NULL,
  quantity INT NOT NULL,
  stock_before INT NOT NULL,
  stock_after INT NOT NULL,
  reason VARCHAR(255) NULL,
  purchase_id INT UNSIGNED NULL,
  sale_id INT UNSIGNED NULL,
  inventory_count_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NOT NULL,
  mov_datetime DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_stock_mov_product (product_id, mov_datetime),
  KEY idx_stock_mov_date (mov_datetime),
  CONSTRAINT fk_stock_mov_product FOREIGN KEY (product_id) REFERENCES products (id),
  CONSTRAINT fk_stock_mov_purchase FOREIGN KEY (purchase_id) REFERENCES purchases (id),
  CONSTRAINT fk_stock_mov_sale FOREIGN KEY (sale_id) REFERENCES sales (id),
  CONSTRAINT fk_stock_mov_count FOREIGN KEY (inventory_count_id) REFERENCES inventory_counts (id),
  CONSTRAINT fk_stock_mov_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  entity VARCHAR(40) NULL,
  entity_id INT UNSIGNED NULL,
  details TEXT NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_date (created_at),
  KEY idx_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
--  DATOS INICIALES (ficticios, para demostración)
-- =====================================================================

INSERT INTO settings (setting_key, setting_value, description) VALUES
('business_name','Ferretería Demo FerreControl','Nombre del comercio (se muestra en comprobantes)'),
('business_cuit','30-00000000-0 (dato ficticio)','CUIT del comercio'),
('business_address','Av. Siempreviva 742','Dirección del comercio'),
('business_phone','011 4000-0000','Teléfono del comercio'),
('max_discount_deposito','10','Descuento máximo (%) que puede aplicar el encargado de depósito y ventas'),
('deposito_can_create_products','1','1 = el encargado de depósito puede crear productos nuevos'),
('work_start','09:00','Hora de entrada habitual (para marcar llegadas tarde)'),
('late_tolerance_min','10','Minutos de tolerancia antes de marcar llegada tarde'),
('monthly_hours','200','Horas mensuales de referencia para valor hora (VALOR DE EJEMPLO: validar)'),
('overtime_multiplier','1.50','Multiplicador de hora extra (VALOR DE EJEMPLO: validar con el convenio)'),
('absence_day_divisor','30','Divisor del sueldo para descontar un día de ausencia injustificada'),
('default_markup_type','recargo','Método de precio por defecto: recargo o margen'),
('default_markup_pct','40','Porcentaje por defecto para sugerir precios'),
('receipt_footer','Comprobante interno. No válido como factura.','Leyenda al pie del comprobante de venta');

INSERT INTO categories (id, name) VALUES
(1,'Herramientas manuales'),
(2,'Herramientas eléctricas'),
(3,'Tornillería y fijaciones'),
(4,'Pinturería'),
(5,'Construcción'),
(6,'Plomería'),
(7,'Electricidad'),
(8,'Cerrajería'),
(9,'Seguridad e higiene (EPP)');

INSERT INTO suppliers (id, name, cuit, phone, email, address, contact) VALUES
(1,'Distribuidora Herramientas del Sur S.R.L.','30-70000001-1','011 4000-1001','ventas@herrasur.demo','Parque Industrial, Lote 12','Ricardo Paz'),
(2,'Pinturas Colorín S.A.','30-70000002-2','011 4000-1002','pedidos@colorin.demo','Av. Industria 450','Marina Ledesma'),
(3,'Corralón Materiales Norte','30-70000003-3','011 4000-1003','corralon@norte.demo','Ruta 8 km 52','Hugo Sánchez'),
(4,'Electro Insumos del Plata','30-70000004-4','011 4000-1004','info@electroplata.demo','Calle 14 Nº 1320','Carla Medina'),
(5,'Fijaciones y Tornillos Argentinos S.R.L.','30-70000005-5','011 4000-1005','ventas@fijar.demo','Av. Mitre 2210','Jorge Ibarra');

-- El stock y los costos de estos productos se cargan con las compras de demostración (setup.php).
INSERT INTO products (code, name, category_id, brand, unit, sale_price, markup_type, markup_pct, min_stock, location, supplier_id) VALUES
('HM-001','Martillo carpintero 500 g',1,'Stanley','unidad',8400,'recargo',40,8,'A-01',1),
('HM-002','Destornillador Phillips PH2',1,'Tramontina','unidad',3300,'recargo',50,10,'A-02',1),
('HM-003','Destornillador plano 6 mm',1,'Tramontina','unidad',3150,'recargo',50,10,'A-02',1),
('HM-004','Pinza universal 8"',1,'Stanley','unidad',9860,'recargo',45,6,'A-03',1),
('HM-005','Llave inglesa 10"',1,'Bahco','unidad',13780,'recargo',45,5,'A-03',1),
('HM-006','Cinta métrica 5 m',1,'Stanley','unidad',6660,'recargo',55,8,'A-04',1),
('HM-007','Serrucho 20"',1,'Tramontina','unidad',10440,'recargo',45,4,'A-04',1),
('HE-001','Taladro percutor 600 W',2,'Black+Decker','unidad',91800,'recargo',35,3,'B-01',1),
('HE-002','Amoladora angular 4 1/2" 750 W',2,'Bosch','unidad',110700,'recargo',35,3,'B-01',1),
('HE-003','Mecha para hormigón 8 mm',2,'Bosch','unidad',6240,'recargo',60,10,'B-02',1),
('TF-001','Tornillos autoperforantes 5 cm (caja x100)',3,'Fijar','caja',9920,'recargo',60,20,'C-01',5),
('TF-002','Clavos 2" por kg',3,'Fijar','kg',5270,'recargo',55,15,'C-02',5),
('TF-003','Tarugos Nº 8 (bolsa x100)',3,'Fijar','bolsa',4480,'recargo',60,15,'C-03',5),
('TF-004','Bulón 1/4" x 2"',3,'Fijar','unidad',630,'recargo',80,100,'C-04',5),
('TF-005','Arandela plana 1/4" (bolsa x50)',3,'Fijar','bolsa',2720,'recargo',60,12,'C-05',5),
('PI-001','Látex interior blanco 20 L',4,'Alba','balde',132300,'recargo',35,4,'D-01',2),
('PI-002','Látex interior blanco 4 L',4,'Alba','lata',34300,'recargo',40,8,'D-02',2),
('PI-003','Esmalte sintético negro 1 L',4,'Sinteplast','lata',18560,'recargo',45,8,'D-03',2),
('PI-004','Pincel 2"',4,'Tramontina','unidad',4160,'recargo',60,12,'D-04',2),
('PI-005','Rodillo de lana 22 cm',4,'Tramontina','unidad',8370,'recargo',55,8,'D-04',2),
('CO-001','Cemento Portland 50 kg',5,'Loma Negra','bolsa',14380,'recargo',25,30,'P-01',3),
('CO-002','Cal hidratada 25 kg',5,'Cacique','bolsa',8060,'recargo',30,20,'P-02',3),
('CO-003','Arena embolsada 25 kg',5,'—','bolsa',3450,'recargo',50,25,'P-03',3),
('PL-001','Caño PVC 110 mm x 4 m',6,'Tigre','unidad',24980,'recargo',35,6,'E-01',3),
('PL-002','Cinta teflón',6,'Genérica','unidad',1400,'recargo',100,40,'E-02',3),
('PL-003','Canilla de bronce 1/2"',6,'FV','unidad',13350,'recargo',50,6,'E-03',3),
('EL-001','Cable unipolar 2,5 mm (rollo 100 m)',7,'Prysmian','rollo',101400,'recargo',30,3,'F-01',4),
('EL-002','Interruptor simple',7,'Cambre','unidad',4640,'recargo',60,15,'F-02',4),
('EL-003','Tomacorriente doble',7,'Cambre','unidad',5760,'recargo',60,15,'F-02',4),
('EL-004','Lámpara LED 9 W',7,'Philips','unidad',3230,'recargo',70,40,'F-03',4),
('CE-001','Candado 40 mm',8,'Odis','unidad',10700,'recargo',55,8,'G-01',1),
('CE-002','Cerradura de embutir',8,'Trabex','unidad',31180,'recargo',45,4,'G-02',1),
('EP-001','Guantes de seguridad',9,'Libus','par',5120,'recargo',60,15,'H-01',4),
('EP-002','Casco de seguridad',9,'Libus','unidad',11700,'recargo',50,6,'H-02',4),
('EP-003','Antiparras de seguridad',9,'Libus','unidad',4640,'recargo',60,10,'H-03',4);

-- Las comisiones son valores de ejemplo: configurarlas según el contrato con cada medio de pago.
INSERT INTO payment_methods (id, name, commission_pct, is_cash) VALUES
(1,'Efectivo',0,1),
(2,'Transferencia bancaria',0,0),
(3,'Tarjeta de débito',1.5,0),
(4,'Tarjeta de crédito',3.5,0),
(5,'Billetera virtual (QR)',1.0,0);

-- La clasificación fijo/variable/mixto es una propuesta y puede cambiarse en Administración > Configuración.
INSERT INTO expense_categories (name, cost_type, fixed_pct) VALUES
('Alquiler del local','fijo',100),
('Electricidad','mixto',60),
('Agua','fijo',100),
('Internet','fijo',100),
('Sueldos','fijo',100),
('Cargas y contribuciones laborales','fijo',100),
('Impuestos','fijo',100),
('Seguros','fijo',100),
('Transporte','variable',0),
('Mantenimiento','mixto',50),
('Limpieza','fijo',100),
('Bolsas y embalajes','variable',0),
('Gastos de envío','variable',0),
('Otros gastos','variable',0);

-- Datos ficticios de empleados.
INSERT INTO employees (first_name, last_name, dni, position, hire_date, phone, email, contract_type, base_salary, tasks) VALUES
('Marcelo','Duarte','30000001','Encargado general','2019-03-04','011 4000-2001','marcelo@ferrecontrol.demo','tiempo_completo',1450000,'Coordina el local, controla caja y compras importantes.'),
('Silvia','Romero','30000002','Vendedora','2021-08-16','011 4000-2002','silvia@ferrecontrol.demo','tiempo_completo',1150000,'Atención al público y ventas en mostrador.'),
('Julián','Acosta','30000003','Encargado de depósito','2020-02-10','011 4000-2003','julian@ferrecontrol.demo','tiempo_completo',1250000,'Recepción de mercadería, control de stock y reposición.'),
('Paula','Ferreyra','30000004','Administrativa','2022-05-02','011 4000-2004','paula@ferrecontrol.demo','tiempo_completo',1200000,'Gestión de personal, proveedores y documentación.'),
('Nicolás','Vega','30000005','Ayudante de depósito','2024-01-15','011 4000-2005','nicolas@ferrecontrol.demo','part_time',700000,'Carga y descarga, armado de pedidos.');

-- IMPORTANTE: los valores se dejan en 0 y SIN VALIDAR a propósito. Los porcentajes y montos deben cargarse
-- según la normativa y el convenio vigentes, y ser validados por el responsable (Administrador).
INSERT INTO payroll_concepts (name, kind, calc_type, value, validated, notes) VALUES
('Antigüedad','remunerativo','porcentaje_basico',0,0,'Completar según normativa/convenio y validar'),
('Presentismo','remunerativo','porcentaje_basico',0,0,'Completar según normativa/convenio y validar'),
('Bonificación fija','remunerativo','fijo',0,0,'Completar según normativa/convenio y validar'),
('Viáticos','no_remunerativo','fijo',0,0,'Completar según normativa/convenio y validar'),
('Jubilación (retención)','descuento','porcentaje_remunerativo',0,0,'Completar según normativa/convenio y validar'),
('Obra social (retención)','descuento','porcentaje_remunerativo',0,0,'Completar según normativa/convenio y validar'),
('Cuota sindical','descuento','porcentaje_remunerativo',0,0,'Completar según normativa/convenio y validar');
