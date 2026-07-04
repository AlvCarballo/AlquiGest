-- ============================================================
--  Migración 002: índices de rendimiento
--  Ejecutar solo en instalaciones existentes que no vengan de
--  un install.php reciente (las nuevas instalaciones ya incluyen
--  estos índices en el DDL de install.php).
--
--  Compatible con MariaDB y MySQL 8+.
--  Para MySQL 5.7, ejecutar desde api.php con try/catch (ya incluido).
-- ============================================================

-- Índices de contratos
CREATE INDEX IF NOT EXISTS `idx_contratos_estado`    ON `contratos`(`estado`);
CREATE INDEX IF NOT EXISTS `idx_contratos_inmueble`  ON `contratos`(`inmueble_id`);
CREATE INDEX IF NOT EXISTS `idx_contratos_inquilino` ON `contratos`(`inquilino_id`);

-- Índices de recibos
CREATE INDEX IF NOT EXISTS `idx_recibos_estado`    ON `recibos`(`estado`);
CREATE INDEX IF NOT EXISTS `idx_recibos_contrato`  ON `recibos`(`contrato_id`);
CREATE INDEX IF NOT EXISTS `idx_recibos_inquilino` ON `recibos`(`inquilino_id`);
CREATE INDEX IF NOT EXISTS `idx_recibos_inmueble`  ON `recibos`(`inmueble_id`);

-- Índice de historial de rentas
CREATE INDEX IF NOT EXISTS `idx_historial_contrato` ON `historial_rentas`(`contrato_id`);
