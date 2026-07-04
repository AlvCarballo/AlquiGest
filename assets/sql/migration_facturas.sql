-- ============================================================
--  AlquiGest – Migración: Módulo de Facturas
--  Ejecutar este script sobre instalaciones existentes para:
--    1. Añadir la columna factura_id a la tabla recibos
--    2. Crear la nueva tabla facturas
--
--  Compatible con MySQL 5.7 / 8.0 y MariaDB 10.x
--  Usa IF NOT EXISTS / procedimientos para ser idempotente:
--  puede ejecutarse varias veces sin error ni pérdida de datos.
-- ============================================================

SET NAMES utf8mb4;

-- ── Paso 1: Añadir factura_id a recibos ──────────────────────
-- MySQL 5.7 y MariaDB < 10.3 no soportan ADD COLUMN IF NOT EXISTS,
-- por lo que se usa un bloque con PREPARE/EXECUTE para comprobarlo.
SET @existe_col = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'recibos'
      AND COLUMN_NAME  = 'factura_id'
);

SET @sql_col = IF(
    @existe_col = 0,
    'ALTER TABLE `recibos` ADD COLUMN `factura_id` INT DEFAULT NULL',
    'SELECT ''INFO: columna factura_id ya existe en recibos'' AS nota'
);

PREPARE stmt_col FROM @sql_col;
EXECUTE stmt_col;
DEALLOCATE PREPARE stmt_col;

-- ── Paso 2: Crear tabla facturas ─────────────────────────────
-- CREATE TABLE IF NOT EXISTS es seguro: no hace nada si ya existe.
CREATE TABLE IF NOT EXISTS `facturas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `recibo_id` INT NOT NULL,
    `contrato_id` INT NOT NULL DEFAULT 0,
    `inquilino_id` INT NOT NULL DEFAULT 0,
    `inmueble_id` INT NOT NULL DEFAULT 0,

    -- Numeración legal
    `numero_factura` VARCHAR(50) NOT NULL,
    `numero_seq` INT DEFAULT NULL,
    `serie` VARCHAR(20) DEFAULT 'FAC',
    `tipo_factura` VARCHAR(20) DEFAULT 'F1',

    -- Fechas legales
    `fecha_emision` DATE NOT NULL,
    `fecha_operacion` DATE DEFAULT NULL,
    `periodo_desde` DATE DEFAULT NULL,
    `periodo_hasta` DATE DEFAULT NULL,

    -- Emisor (copia congelada del momento de emisión)
    `emisor_nombre` VARCHAR(255) DEFAULT NULL,
    `emisor_nif` VARCHAR(50) DEFAULT NULL,
    `emisor_direccion` VARCHAR(255) DEFAULT NULL,
    `emisor_cp` VARCHAR(20) DEFAULT NULL,
    `emisor_municipio` VARCHAR(100) DEFAULT NULL,
    `emisor_provincia` VARCHAR(100) DEFAULT NULL,
    `emisor_email` VARCHAR(150) DEFAULT NULL,
    `emisor_telefono` VARCHAR(50) DEFAULT NULL,
    `emisor_iban` VARCHAR(50) DEFAULT NULL,

    -- Cliente (copia congelada del momento de emisión)
    `cliente_nombre` VARCHAR(255) DEFAULT NULL,
    `cliente_nif` VARCHAR(50) DEFAULT NULL,
    `cliente_direccion` VARCHAR(255) DEFAULT NULL,
    `cliente_cp` VARCHAR(20) DEFAULT NULL,
    `cliente_municipio` VARCHAR(100) DEFAULT NULL,
    `cliente_provincia` VARCHAR(100) DEFAULT NULL,
    `cliente_email` VARCHAR(150) DEFAULT NULL,

    -- Descripción del servicio
    `inmueble_direccion` VARCHAR(255) DEFAULT NULL,
    `concepto` TEXT DEFAULT NULL,
    `conceptos_extra` TEXT DEFAULT NULL,
    `notas` TEXT DEFAULT NULL,

    -- Importes fiscales desglosados
    `base_imponible` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `iva_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `importe_iva` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `irpf_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `importe_irpf` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `importe_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,

    -- Estado: emitida | anulada | rectificada
    `estado` VARCHAR(20) NOT NULL DEFAULT 'emitida',

    -- Campos para VERI*FACTU / SIF (uso futuro — no se envían todavía a AEAT)
    `hash_factura` VARCHAR(255) DEFAULT NULL,
    `hash_anterior` VARCHAR(255) DEFAULT NULL,
    `qr_url` TEXT DEFAULT NULL,
    `verifactu_estado` VARCHAR(50) DEFAULT 'no_enviado',
    `verifactu_respuesta` TEXT DEFAULT NULL,

    -- id de la factura que esta rectifica
    `factura_rectificada_id` INT DEFAULT NULL,

    `fecha_creacion` VARCHAR(50) DEFAULT '',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Un recibo solo puede tener una factura; el número de factura es único
    UNIQUE KEY `uq_facturas_recibo_id` (`recibo_id`),
    UNIQUE KEY `uq_facturas_numero_factura` (`numero_factura`),
    INDEX `idx_facturas_fecha_emision` (`fecha_emision`),
    INDEX `idx_facturas_estado` (`estado`),
    INDEX `idx_facturas_inquilino_id` (`inquilino_id`),
    INDEX `idx_facturas_contrato_id` (`contrato_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Paso 3: Añadir variables de VERI*FACTU a configuracion ────
-- INSERT IGNORE no falla si la variable ya existe (UNIQUE KEY sobre 'variable')
INSERT IGNORE INTO `configuracion` (`variable`, `valor`, `descripcion`) VALUES
  ('verifactu_activo',          '0',         'VERI*FACTU: 0=desactivado (por defecto), 1=activo. Solo activar cuando el certificado y la configuración estén completos.'),
  ('verifactu_entorno',         'pruebas',   'Entorno AEAT: pruebas (prewww1.aeat.es) o produccion (www1.aeat.es).'),
  ('verifactu_cert_path',       '',          'Ruta relativa al certificado .p12/.pfx del emisor (ej: certs/cert_verifactu.p12).'),
  ('verifactu_cert_pass',       '',          'Contraseña del certificado digital .p12/.pfx.'),
  ('verifactu_nif_sif',         '',          'NIF del obligado de emisión ante el SIF (normalmente igual al NIF de empresa).'),
  ('verifactu_sistema_nombre',  'AlquiGest', 'Nombre del sistema informático de facturación declarado ante AEAT.'),
  ('verifactu_sistema_version', '3.3',       'Versión del sistema informático de facturación.'),
  ('verifactu_num_instalacion', '1',         'Número de instalación del sistema (1 para instalación única).');

-- ── Verificación ──────────────────────────────────────────────
SELECT 'Migración completada.' AS resultado;
SELECT
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recibos'   AND COLUMN_NAME = 'factura_id') AS recibos_factura_id_ok,
    (SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'facturas') AS tabla_facturas_ok;
