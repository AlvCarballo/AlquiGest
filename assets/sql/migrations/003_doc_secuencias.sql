-- Migración 003: secuencias de numeración mensual por tipo de documento
-- Ejecutar UNA SOLA VEZ sobre la BD existente (idempotente: IF NOT EXISTS).
-- Fecha: 2026-06-30

-- 1. Crear tabla de secuencias
CREATE TABLE IF NOT EXISTS `doc_secuencias` (
  `tipo`    VARCHAR(20) NOT NULL COMMENT 'REC=Recibos, FAC=Facturas, RET=Facturas rectificativas, RER=Recibos rectificativos…',
  `periodo` CHAR(6)     NOT NULL COMMENT 'Periodo YYYYMM de emision',
  `ultimo`  INT         NOT NULL DEFAULT 0 COMMENT 'Ultimo numero de secuencia emitido en este periodo',
  PRIMARY KEY (`tipo`, `periodo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Secuencias de numeracion mensual por tipo de documento. Un numero consumido nunca se reutiliza.';

-- 2. Poblar con los maximos actuales de recibos
--    (evita colision con la numeracion ya existente)
INSERT INTO doc_secuencias (tipo, periodo, ultimo)
SELECT
  SUBSTRING_INDEX(numero_recibo, '-', 1)                      AS tipo,
  SUBSTRING(numero_recibo, LOCATE('-', numero_recibo)+1, 6)   AS periodo,
  MAX(COALESCE(numero_seq, 0))                                 AS ultimo
FROM recibos
WHERE numero_recibo REGEXP '^[A-Z]+-[0-9]{6}-'
GROUP BY
  SUBSTRING_INDEX(numero_recibo, '-', 1),
  SUBSTRING(numero_recibo, LOCATE('-', numero_recibo)+1, 6)
ON DUPLICATE KEY UPDATE ultimo = GREATEST(ultimo, VALUES(ultimo));

-- 3. Poblar con los maximos actuales de facturas
INSERT INTO doc_secuencias (tipo, periodo, ultimo)
SELECT
  SUBSTRING_INDEX(numero_factura, '-', 1)                       AS tipo,
  SUBSTRING(numero_factura, LOCATE('-', numero_factura)+1, 6)   AS periodo,
  MAX(COALESCE(numero_seq, 0))                                   AS ultimo
FROM facturas
WHERE numero_factura REGEXP '^[A-Z]+-[0-9]{6}-'
GROUP BY
  SUBSTRING_INDEX(numero_factura, '-', 1),
  SUBSTRING(numero_factura, LOCATE('-', numero_factura)+1, 6)
ON DUPLICATE KEY UPDATE ultimo = GREATEST(ultimo, VALUES(ultimo));

-- 4. Indice UNIQUE en recibos.numero_recibo
--    (las facturas ya tienen uq_facturas_numero_factura)
ALTER TABLE recibos
  ADD UNIQUE KEY `uq_recibos_numero_recibo` (`numero_recibo`);
