-- ============================================================
-- Migración 005: Recibos rectificativos (RER) + tabla plantillas
-- Fecha: 2026-07-04
-- Idempotente: segura para ejecutar varias veces sobre una BD existente.
-- api.php aplica automáticamente estos mismos cambios al arrancar,
-- este fichero se deja como referencia / aplicación manual (phpMyAdmin).
-- ============================================================

-- 1. Columna que enlaza un recibo rectificativo con el recibo que rectifica.
--    NULL en recibos normales; solo se rellena en el recibo rectificativo
--    generado al anular un recibo que todavía no tenía factura emitida.
--    (MySQL 8 soporta ADD COLUMN IF NOT EXISTS; en 5.7/MariaDB antiguos
--    ejecutar solo si la columna no existe todavía — ver comprobación abajo).
ALTER TABLE `recibos`
  ADD COLUMN IF NOT EXISTS `recibo_rectificado_id` INT DEFAULT NULL;

-- 2. Evitar números de recibo duplicados (mismo criterio que facturas).
--    Solo se puede aplicar si no hay ya duplicados en la tabla.
--    Comprobar antes con:
--      SELECT numero_recibo FROM recibos WHERE numero_recibo != ''
--      GROUP BY numero_recibo HAVING COUNT(*) > 1;
ALTER TABLE `recibos`
  ADD UNIQUE KEY `uq_recibos_numero_recibo` (`numero_recibo`);

-- 3. Tabla de plantillas DOCX (si la instalación es antigua y no la tiene).
CREATE TABLE IF NOT EXISTS `plantillas` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `nombre`         VARCHAR(150) NOT NULL,
  `tipo_documento` VARCHAR(50)  NOT NULL DEFAULT 'otro',
  `descripcion`    TEXT         DEFAULT NULL,
  `fichero`        VARCHAR(255) NOT NULL,
  `activa`         TINYINT(1)  DEFAULT 1,
  `por_defecto`    TINYINT(1)  DEFAULT 0,
  `created_at`     DATETIME    DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Nota sobre nomenclatura (ver README.md):
--   Recibo normal:            REC-AAAAMM-NNNNN
--   Factura normal:           FAC-AAAAMM-NNNNN
--   Factura rectificativa:    RET-AAAAMM-NNNNN  (antes "RECT", renombrada en esta versión)
--   Recibo rectificativo:     RER-AAAAMM-NNNNN  (nuevo)
