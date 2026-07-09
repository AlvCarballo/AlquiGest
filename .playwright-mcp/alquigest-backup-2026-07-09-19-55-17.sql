-- ============================================================
-- AlquiGest – Copia de seguridad
-- Generado: 2026-07-09 19:55:17
-- Base de datos: alquigest
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Tabla: configuracion
DROP TABLE IF EXISTS `configuracion`;
CREATE TABLE `configuracion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `variable` varchar(100) NOT NULL,
  `valor` varchar(255) DEFAULT '',
  `descripcion` varchar(500) DEFAULT '',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_variable` (`variable`)
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4;

INSERT INTO `configuracion` (`id`, `variable`, `valor`, `descripcion`, `updated_at`) VALUES
  ('1', 'dash_kpis', '1', 'Muestra las 4 tarjetas de resumen del Dashboard (propietarios, inmuebles, contratos activos, pendiente de cobro) (1=visible, 0=oculto).', '2026-07-09 19:50:09'),
  ('2', 'dash_alerta_ipc', '1', 'Muestra el aviso naranja de revisión IPC/IRAV pendiente este mes en el Dashboard (1=visible, 0=oculto).', '2026-07-09 19:50:09'),
  ('3', 'dash_alerta_backup', '1', 'Muestra el aviso azul de backup desactualizado en el Dashboard (1=visible, 0=oculto).', '2026-07-09 19:50:09'),
  ('4', 'dash_avisos_revision', '1', 'Muestra la tarjeta de revisiones anuales urgentes (aniversario en los próximos 30 días) en el Dashboard (1=visible, 0=oculto).', '2026-07-09 19:50:09'),
  ('5', 'dash_renovaciones', '1', 'Muestra la tabla de próximas renovaciones de contrato (vencidas o en los próximos 6 meses) en el Dashboard (1=visible, 0=oculto).', '2026-07-09 19:50:09'),
  ('6', 'dash_revisiones', '1', 'Muestra la tabla completa de próximas revisiones anuales de todos los contratos activos en el Dashboard (1=visible, 0=oculto).', '2026-07-09 19:50:09'),
  ('7', 'dash_ultimos_recibos', '1', 'Muestra la tabla de últimos 5 recibos en el Dashboard (1=visible, 0=oculto).', '2026-07-09 19:50:09'),
  ('8', 'dash_cobrado_mes', '1', 'Muestra la tarjeta de cobrado este mes y recibos pendientes en el Dashboard (1=visible, 0=oculto).', '2026-07-09 19:50:09'),
  ('9', 'dash_graficos', '1', 'Muestra los gráficos de ingresos y ocupación en el Dashboard (1=visible, 0=oculto).', '2026-07-09 19:50:09'),
  ('10', 'dash_cobros_esperados', '1', 'Muestra el widget de previsión de cobros del mes actual en el Dashboard (1=visible, 0=oculto).', '2026-07-09 19:50:09'),
  ('11', 'dash_backup_dias', '7', 'Número de días sin backup a partir del cual aparece el aviso de backup en el Dashboard. Mínimo: 1.', '2026-07-09 19:50:09'),
  ('12', 'dash_log_actividad', '1', 'Muestra el widget de últimas actividades en el Dashboard (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('13', 'filas_dashboard', '6', 'Número de filas por página en las tarjetas del dashboard (renovaciones y revisiones de renta). Mínimo: 1.', '2026-07-09 19:50:10'),
  ('14', 'filas_recibos', '30', 'Número de filas por página en la tabla de Recibos. Mínimo: 5.', '2026-07-09 19:50:10'),
  ('15', 'filas_propietarios', '20', 'Número de filas por página en la tabla de Propietarios. Mínimo: 5.', '2026-07-09 19:50:10'),
  ('16', 'filas_fincas', '20', 'Número de filas por página en la tabla de Fincas. Mínimo: 5.', '2026-07-09 19:50:10'),
  ('17', 'filas_inmuebles', '20', 'Número de filas por página en la tabla de Pisos / Locales. Mínimo: 5.', '2026-07-09 19:50:10'),
  ('18', 'filas_inquilinos', '20', 'Número de filas por página en la tabla de Inquilinos. Mínimo: 5.', '2026-07-09 19:50:10'),
  ('19', 'filas_contratos', '20', 'Número de filas por página en la tabla de Contratos. Mínimo: 5.', '2026-07-09 19:50:10'),
  ('20', 'filas_facturas', '20', 'Número de filas por página en la tabla de Facturas. Mínimo: 5.', '2026-07-09 19:50:10'),
  ('21', 'VisiGenerarReciboCont', '1', 'Muestra el botón \"Generar recibo\" en la tabla de contratos activos (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('22', 'VisiRenovarCont', '1', 'Muestra el botón \"Renovar\" en la tabla de contratos activos (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('23', 'VisiHistorialCont', '1', 'Muestra el botón \"Historial\" de revisiones de renta en la tabla de contratos activos (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('24', 'VisiBajaCont', '1', 'Muestra el botón \"Baja\" en la tabla de contratos activos (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('25', 'VisiPDFCont', '0', 'Muestra el botón \"PDF\" del contrato (1=visible, 0=oculto). Oculto por defecto.', '2026-07-09 19:50:10'),
  ('26', 'VisiFianzaCont', '1', 'Muestra el botón \"Fianza\" (justificante) cuando el contrato tiene fianza registrada (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('27', 'VisiDocxCont', '1', 'Muestra el botón \"DOCX\" para generar documentos desde plantillas Word (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('28', 'VisiBorrarProp', '1', 'Muestra el botón Eliminar en la tabla de Propietarios (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('29', 'VisiBorrarFinc', '1', 'Muestra el botón Eliminar en la tabla de Fincas (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('30', 'VisiBorrarInm', '1', 'Muestra el botón Eliminar en la tabla de Inmuebles (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('31', 'VisiBorrarInq', '1', 'Muestra el botón Eliminar en la tabla de Inquilinos (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('32', 'VisiBorrarCont', '1', 'Muestra el botón Eliminar en la tabla de Contratos (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('33', 'VisiCobrarReci', '1', 'Muestra los botones \"Cobrar\" / \"Ver cobros\" en la tabla de Recibos (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('34', 'VisiEmailReci', '1', 'Muestra el botón de enviar por email en la tabla de Recibos (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('35', 'VisiImprimirReci', '1', 'Muestra el botón de imprimir/PDF en la tabla de Recibos (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('36', 'VisiFacturaReci', '1', 'Muestra el botón de generar/ver factura en la tabla de Recibos (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('37', 'VisiAnularReci', '1', 'Muestra el botón Anular en la tabla de Recibos (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('38', 'VisiAnularPago', '1', 'Muestra el botón Anular en el panel de cobros de un recibo (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('39', 'VisiImprimirFact', '1', 'Muestra el botón de imprimir/PDF en la tabla de Facturas (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('40', 'VisiEmailFact', '1', 'Muestra el botón de enviar por email en la tabla de Facturas (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('41', 'VisiReciboOrigenFact', '1', 'Muestra el botón para navegar al recibo origen de la factura (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('42', 'VisiAEATFact', '1', 'Muestra el botón de envío a AEAT (VERI*FACTU) en la tabla de Facturas (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('43', 'VisiXMLFact', '1', 'Muestra el botón \"Ver XML AEAT\" en facturas ya enviadas (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('44', 'VisiAnularFact', '1', 'Muestra el botón Anular en facturas emitidas (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('45', 'VisiPagosInq', '1', 'Muestra el botón \"Pagos\" (historial de cobros) en la tabla de Inquilinos (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('46', 'VisiHistorialInq', '1', 'Muestra el botón \"Historial\" completo en la tabla de Inquilinos (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('47', 'VisiIRPFProp', '1', 'Muestra el botón \"IRPF\" (informe fiscal anual) en la tabla de Propietarios (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('48', 'VisiBackupJSON', '0', 'Muestra el botón de descarga de backup JSON en Mi Empresa (1=visible, 0=oculto). Oculto por defecto.', '2026-07-09 19:50:10'),
  ('49', 'whatsappVis', '1', 'Muestra los botones de WhatsApp en la tabla de recibos y en el modal de lote (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('50', 'whatsappPDF', '1', 'Genera y descarga el PDF del recibo al enviar por WhatsApp (1=activo, 0=solo texto sin PDF).', '2026-07-09 19:50:10'),
  ('51', 'whatsappNativo', '1', 'Método de apertura de WhatsApp: 1=ventana emergente (window.open), 0=enlace directo href (nunca bloqueado por el navegador).', '2026-07-09 19:50:10'),
  ('52', 'verifactu_activo', '0', 'VERI*FACTU: 0=desactivado (por defecto), 1=activo. Solo activar cuando el certificado y la configuración estén completos.', '2026-07-09 19:50:10'),
  ('53', 'verifactu_entorno', 'pruebas', 'Entorno AEAT: pruebas (prewww1.aeat.es) o produccion (www1.aeat.es).', '2026-07-09 19:50:10'),
  ('54', 'verifactu_cert_path', '', 'Ruta relativa al certificado .p12/.pfx del emisor (ej: certs/cert_verifactu.p12).', '2026-07-09 19:50:10'),
  ('55', 'verifactu_cert_pass', '', 'Contraseña del certificado digital .p12/.pfx.', '2026-07-09 19:50:10'),
  ('56', 'verifactu_nif_sif', '', 'NIF del obligado de emisión ante el SIF (normalmente igual al NIF de empresa).', '2026-07-09 19:50:10'),
  ('57', 'verifactu_sistema_nombre', 'AlquiGest', 'Nombre del sistema informático de facturación declarado ante AEAT.', '2026-07-09 19:50:10'),
  ('58', 'verifactu_sistema_version', '2.0.0', 'Versión del sistema informático de facturación.', '2026-07-09 19:50:10'),
  ('59', 'verifactu_num_instalacion', '1', 'Número de instalación del sistema (1 para instalación única).', '2026-07-09 19:50:10'),
  ('60', 'docs_plantillas_activas', '1', 'Activa el módulo de plantillas DOCX en toda la aplicación (1=activo, 0=desactivado).', '2026-07-09 19:50:10'),
  ('61', 'docs_permitir_pdf', '0', 'Conversión DOCX→PDF automática (requiere LibreOffice en el servidor; no disponible en MAMP/Windows). 0=desactivado.', '2026-07-09 19:50:10'),
  ('62', 'dash_plantillas_estado', '1', 'Muestra en el Dashboard el número de plantillas DOCX activas (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('63', 'menu_propietarios', '1', 'Muestra la opción Propietarios en el menú lateral (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('64', 'menu_fincas', '1', 'Muestra la opción Fincas / Edificios en el menú lateral (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('65', 'menu_inmuebles', '1', 'Muestra la opción Pisos / Locales en el menú lateral (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('66', 'menu_inquilinos', '1', 'Muestra la opción Inquilinos en el menú lateral (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('67', 'menu_contratos', '1', 'Muestra la opción Contratos en el menú lateral (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('68', 'menu_recibos', '1', 'Muestra la opción Recibos en el menú lateral (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('69', 'menu_facturas', '1', 'Muestra la opción Facturas en el menú lateral (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('70', 'menu_generar', '1', 'Muestra la opción Generar Recibos en el menú lateral (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('71', 'menu_informes', '1', 'Muestra la opción Informes Excel en el menú lateral (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('72', 'menu_calendario', '1', 'Muestra la opción Calendario Cobros en el menú lateral (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('73', 'menu_morosidad', '1', 'Muestra la opción Morosidad en el menú lateral (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('74', 'menu_actividad', '1', 'Muestra la opción Actividad en el menú lateral (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('75', 'menu_empresa', '1', 'Muestra la opción Mi Empresa en el menú lateral (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('76', 'menu_verifactu', '1', 'Muestra la opción VERI*FACTU en el menú lateral (1=visible, 0=oculto).', '2026-07-09 19:50:10'),
  ('77', 'menu_plantillas', '1', 'Muestra la opción Plantillas en el menú lateral (1=visible, 0=oculto).', '2026-07-09 19:50:10');

-- Tabla: contratos
DROP TABLE IF EXISTS `contratos`;
CREATE TABLE `contratos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `inmueble_id` int(11) DEFAULT NULL,
  `inquilino_id` int(11) DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `duracion_anos` int(11) DEFAULT '1',
  `duracion_unidad` varchar(10) DEFAULT 'anos',
  `aviso_recibo` tinyint(1) DEFAULT '1',
  `aviso_factura` tinyint(1) DEFAULT '0',
  `ipc_anio_aplicado` int(11) DEFAULT NULL,
  `renta_base` decimal(10,2) DEFAULT '0.00',
  `iva_pct` decimal(5,2) DEFAULT '0.00',
  `irpf_pct` decimal(5,2) DEFAULT '0.00',
  `fianza` decimal(10,2) DEFAULT '0.00',
  `dia_pago` int(11) DEFAULT '5',
  `estado` varchar(20) DEFAULT 'activo',
  `revision` varchar(50) DEFAULT '',
  `fecha_baja` date DEFAULT NULL,
  `motivo_baja` varchar(100) DEFAULT '',
  `obs_baja` text,
  `observaciones` text,
  `motivo_temporada` text,
  `nombre_fiador` varchar(150) DEFAULT NULL,
  `nif_fiador` varchar(20) DEFAULT NULL,
  `direccion_fiador` text,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contratos_estado` (`estado`),
  KEY `idx_contratos_inmueble` (`inmueble_id`),
  KEY `idx_contratos_inquilino` (`inquilino_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4;

INSERT INTO `contratos` (`id`, `inmueble_id`, `inquilino_id`, `fecha_inicio`, `fecha_fin`, `duracion_anos`, `duracion_unidad`, `aviso_recibo`, `aviso_factura`, `ipc_anio_aplicado`, `renta_base`, `iva_pct`, `irpf_pct`, `fianza`, `dia_pago`, `estado`, `revision`, `fecha_baja`, `motivo_baja`, `obs_baja`, `observaciones`, `motivo_temporada`, `nombre_fiador`, `nif_fiador`, `direccion_fiador`, `updated_at`) VALUES
  ('1', '1', '1', '2024-01-01', '2025-12-31', '2', 'anos', '1', '0', NULL, '700.00', '0.00', '0.00', '1400.00', '5', 'activo', 'Fija', NULL, '', NULL, 'Contrato de 2 años', NULL, NULL, NULL, NULL, '2026-07-09 19:50:10'),
  ('2', '2', '2', '2024-03-01', '2025-02-28', '1', 'anos', '1', '0', NULL, '650.00', '0.00', '0.00', '1300.00', '5', 'activo', 'Sin revision', NULL, '', NULL, '', NULL, NULL, NULL, NULL, '2026-07-09 19:50:10'),
  ('3', '3', '3', '2023-06-01', '2024-05-31', '1', 'anos', '1', '0', NULL, '720.00', '0.00', '0.00', '1440.00', '5', 'activo', 'IPC', NULL, '', NULL, 'Renovado automáticamente', NULL, NULL, NULL, NULL, '2026-07-09 19:50:10'),
  ('4', '4', '4', '2024-09-01', '2025-08-31', '1', 'anos', '1', '0', NULL, '680.00', '0.00', '0.00', '1360.00', '5', 'activo', 'Sin revision', NULL, '', NULL, '', NULL, NULL, NULL, NULL, '2026-07-09 19:50:10'),
  ('5', '5', '5', '2022-01-01', '2024-12-31', '3', 'anos', '1', '0', NULL, '1200.00', '21.00', '0.00', '2400.00', '5', 'activo', 'Fija', NULL, '', NULL, 'Local comercial IVA 21%', NULL, NULL, NULL, NULL, '2026-07-09 19:50:10'),
  ('6', '6', '6', '2024-06-01', '2025-05-31', '1', 'anos', '1', '0', NULL, '800.00', '0.00', '0.00', '1600.00', '5', 'activo', 'IPC', NULL, '', NULL, '', NULL, NULL, NULL, NULL, '2026-07-09 19:50:10'),
  ('7', '7', '7', '2025-11-01', '2026-10-31', '1', 'anos', '1', '0', NULL, '750.00', '0.00', '0.00', '1500.00', '1', 'activo', 'IPC', NULL, '', NULL, 'Historial con salto de año (dic-2025 a ene-2026)', NULL, NULL, NULL, NULL, '2026-07-09 19:50:10'),
  ('8', '8', '8', '2025-12-01', '2026-11-30', '1', 'anos', '1', '0', NULL, '620.00', '0.00', '0.00', '1240.00', '1', 'activo', 'Sin revision', NULL, '', NULL, 'Incluye factura rectificativa (RET) y recibo rectificativo (RER) de ejemplo', NULL, NULL, NULL, NULL, '2026-07-09 19:50:10'),
  ('9', '9', '9', '2024-06-01', '2025-12-31', '1', 'anos', '1', '0', NULL, '900.00', '0.00', '0.00', '1800.00', '1', 'finalizado', 'Fija', '2025-12-31', 'Fin de contrato', NULL, 'Contrato finalizado usado como ejemplo histórico', NULL, NULL, NULL, NULL, '2026-07-09 19:50:10'),
  ('10', '10', '10', '2023-07-09', '2028-07-09', '3', 'anos', '1', '0', NULL, '710.00', '0.00', '0.00', '1420.00', '5', 'activo', 'IPC', NULL, '', NULL, 'Ejemplo: revisión IPC pendiente — aparece en el aviso del Dashboard este mes', NULL, NULL, NULL, NULL, '2026-07-09 19:50:10'),
  ('11', '11', '11', '2022-08-03', '2028-07-09', '4', 'anos', '1', '0', NULL, '640.00', '0.00', '0.00', '1280.00', '5', 'activo', 'IRAV', NULL, '', NULL, 'Ejemplo: revisión IRAV próxima — aniversario dentro de 30 días, aún no corresponde este mes', NULL, NULL, NULL, NULL, '2026-07-09 19:50:10'),
  ('12', '12', '12', '2024-07-09', '2028-07-09', '2', 'anos', '1', '0', '2026', '772.50', '0.00', '0.00', '1500.00', '5', 'activo', 'IPC', NULL, '', NULL, 'Ejemplo: revisión IPC ya aplicada este año — no debe aparecer como pendiente', NULL, NULL, NULL, NULL, '2026-07-09 19:50:10'),
  ('13', '13', '13', '2021-01-09', '2028-07-09', '5', 'anos', '1', '0', NULL, '590.00', '0.00', '0.00', '1180.00', '5', 'activo', 'IRAV', NULL, '', NULL, 'Ejemplo: revisión IRAV lejana — no debe aparecer en ningún aviso', NULL, NULL, NULL, NULL, '2026-07-09 19:50:10'),
  ('14', '14', '14', '2026-01-09', '2028-07-09', '2', 'anos', '1', '0', NULL, '600.00', '0.00', '0.00', '1200.00', '5', 'activo', 'Sin revision', NULL, '', NULL, 'Ejemplo: contrato normal con un solo inquilino, sin secundarios (para comparar)', NULL, NULL, NULL, NULL, '2026-07-09 19:50:10'),
  ('15', '15', '15', '2026-01-09', '2028-07-09', '2', 'anos', '1', '0', NULL, '850.00', '0.00', '0.00', '1700.00', '5', 'activo', 'Sin revision', NULL, '', NULL, 'Ejemplo: contrato con 1 inquilino principal + 1 inquilino secundario', NULL, NULL, NULL, NULL, '2026-07-09 19:50:10'),
  ('16', '16', '16', '2026-01-09', '2028-07-09', '2', 'anos', '1', '0', NULL, '950.00', '0.00', '0.00', '1900.00', '5', 'activo', 'Sin revision', NULL, '', NULL, 'Ejemplo: contrato con 1 inquilino principal + 2 inquilinos secundarios', NULL, NULL, NULL, NULL, '2026-07-09 19:50:10');

-- Tabla: contratos_inq_sec
DROP TABLE IF EXISTS `contratos_inq_sec`;
CREATE TABLE `contratos_inq_sec` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contrato_id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL DEFAULT '',
  `nif` varchar(20) DEFAULT '',
  `direccion` text,
  `telefono` varchar(30) DEFAULT '',
  `email` varchar(100) DEFAULT '',
  `orden` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inq_sec_contrato` (`contrato_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;

INSERT INTO `contratos_inq_sec` (`id`, `contrato_id`, `nombre`, `nif`, `direccion`, `telefono`, `email`, `orden`, `created_at`) VALUES
  ('1', '15', 'Cabrera Morales, Sonia', '97778800U', 'C/ Goya 30 1º A', '677 800 250', 'sonia.cabrera@gmail.com', '1', '2026-07-09 19:50:10'),
  ('2', '16', 'Iglesias Farto, Marcos', '98889901V', 'C/ Goya 30 1º B', '688 800 350', 'marcos.iglesias@gmail.com', '1', '2026-07-09 19:50:10'),
  ('3', '16', 'Iglesias Farto, Elena', '98889902W', 'C/ Goya 30 1º B', '688 800 360', 'elena.iglesias@gmail.com', '2', '2026-07-09 19:50:10');

-- Tabla: doc_secuencias
DROP TABLE IF EXISTS `doc_secuencias`;
CREATE TABLE `doc_secuencias` (
  `tipo` varchar(20) NOT NULL COMMENT 'REC=Recibos, FAC=Facturas, RECT=Rectificativas',
  `periodo` char(6) NOT NULL COMMENT 'Periodo YYYYMM de emision',
  `ultimo` int(11) NOT NULL DEFAULT '0' COMMENT 'Ultimo numero de secuencia emitido en este periodo',
  PRIMARY KEY (`tipo`,`periodo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Secuencias de numeracion mensual. Un numero consumido nunca se reutiliza.';

INSERT INTO `doc_secuencias` (`tipo`, `periodo`, `ultimo`) VALUES
  ('FAC', '202601', '1'),
  ('FAC', '202602', '1'),
  ('REC', '202512', '2'),
  ('REC', '202601', '2'),
  ('REC', '202602', '2'),
  ('REC', '202603', '6'),
  ('REC', '202604', '6'),
  ('REC', '202605', '6'),
  ('REC', '202606', '6'),
  ('REC', '202607', '6'),
  ('RER', '202607', '1'),
  ('RET', '202607', '1');

-- Tabla: empresa
DROP TABLE IF EXISTS `empresa`;
CREATE TABLE `empresa` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) DEFAULT '',
  `cif` varchar(20) DEFAULT '',
  `direccion` varchar(255) DEFAULT '',
  `cp` varchar(10) DEFAULT '',
  `municipio` varchar(100) DEFAULT '',
  `provincia` varchar(100) DEFAULT '',
  `telefono` varchar(30) DEFAULT '',
  `email` varchar(150) DEFAULT '',
  `iban` varchar(50) DEFAULT '',
  `pie_recibo` text,
  `prefijo_recibos` varchar(10) DEFAULT 'R',
  `gmail_user` varchar(150) DEFAULT '',
  `gmail_pass` varchar(150) DEFAULT '',
  `web` varchar(150) DEFAULT '',
  `email_asunto_recibo` text,
  `email_cuerpo_recibo` text,
  `email_asunto_factura` text,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;

INSERT INTO `empresa` (`id`, `nombre`, `cif`, `direccion`, `cp`, `municipio`, `provincia`, `telefono`, `email`, `iban`, `pie_recibo`, `prefijo_recibos`, `gmail_user`, `gmail_pass`, `web`, `email_asunto_recibo`, `email_cuerpo_recibo`, `email_asunto_factura`, `updated_at`) VALUES
  ('1', 'Administración de Fincas López', 'B12345678', 'C/ Gran Vía 10, 2º B', '28013', 'Madrid', 'Madrid', '91 555 1234', 'admin@fincaslopez.es', 'ES91 2100 0418 4502 0005 1332', 'Gracias por su pago puntual.', 'REC', '', '', '', NULL, NULL, NULL, '2026-07-09 19:50:10');

-- Tabla: facturas
DROP TABLE IF EXISTS `facturas`;
CREATE TABLE `facturas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recibo_id` int(11) DEFAULT NULL,
  `contrato_id` int(11) NOT NULL DEFAULT '0',
  `inquilino_id` int(11) NOT NULL DEFAULT '0',
  `inmueble_id` int(11) NOT NULL DEFAULT '0',
  `numero_factura` varchar(50) NOT NULL,
  `numero_seq` int(11) DEFAULT NULL,
  `serie` varchar(20) DEFAULT 'FAC',
  `tipo_factura` varchar(20) DEFAULT 'F1',
  `fecha_emision` date NOT NULL,
  `fecha_operacion` date DEFAULT NULL,
  `periodo_desde` date DEFAULT NULL,
  `periodo_hasta` date DEFAULT NULL,
  `emisor_nombre` varchar(255) DEFAULT NULL,
  `emisor_nif` varchar(50) DEFAULT NULL,
  `emisor_direccion` varchar(255) DEFAULT NULL,
  `emisor_cp` varchar(20) DEFAULT NULL,
  `emisor_municipio` varchar(100) DEFAULT NULL,
  `emisor_provincia` varchar(100) DEFAULT NULL,
  `emisor_email` varchar(150) DEFAULT NULL,
  `emisor_telefono` varchar(50) DEFAULT NULL,
  `emisor_iban` varchar(50) DEFAULT NULL,
  `cliente_nombre` varchar(255) DEFAULT NULL,
  `cliente_nif` varchar(50) DEFAULT NULL,
  `cliente_direccion` varchar(255) DEFAULT NULL,
  `cliente_cp` varchar(20) DEFAULT NULL,
  `cliente_municipio` varchar(100) DEFAULT NULL,
  `cliente_provincia` varchar(100) DEFAULT NULL,
  `cliente_email` varchar(150) DEFAULT NULL,
  `inmueble_direccion` varchar(255) DEFAULT NULL,
  `concepto` text,
  `conceptos_extra` text,
  `notas` text,
  `base_imponible` decimal(12,2) NOT NULL DEFAULT '0.00',
  `iva_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `importe_iva` decimal(12,2) NOT NULL DEFAULT '0.00',
  `irpf_pct` decimal(5,2) NOT NULL DEFAULT '0.00',
  `importe_irpf` decimal(12,2) NOT NULL DEFAULT '0.00',
  `importe_total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `estado` varchar(20) NOT NULL DEFAULT 'emitida',
  `hash_factura` varchar(255) DEFAULT NULL,
  `hash_anterior` varchar(255) DEFAULT NULL,
  `qr_url` text,
  `verifactu_estado` varchar(50) DEFAULT 'no_enviado',
  `verifactu_respuesta` text,
  `factura_rectificada_id` int(11) DEFAULT NULL,
  `fecha_creacion` varchar(50) DEFAULT '',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_facturas_numero_factura` (`numero_factura`),
  KEY `idx_facturas_fecha_emision` (`fecha_emision`),
  KEY `idx_facturas_estado` (`estado`),
  KEY `idx_facturas_inquilino_id` (`inquilino_id`),
  KEY `idx_facturas_contrato_id` (`contrato_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;

INSERT INTO `facturas` (`id`, `recibo_id`, `contrato_id`, `inquilino_id`, `inmueble_id`, `numero_factura`, `numero_seq`, `serie`, `tipo_factura`, `fecha_emision`, `fecha_operacion`, `periodo_desde`, `periodo_hasta`, `emisor_nombre`, `emisor_nif`, `emisor_direccion`, `emisor_cp`, `emisor_municipio`, `emisor_provincia`, `emisor_email`, `emisor_telefono`, `emisor_iban`, `cliente_nombre`, `cliente_nif`, `cliente_direccion`, `cliente_cp`, `cliente_municipio`, `cliente_provincia`, `cliente_email`, `inmueble_direccion`, `concepto`, `conceptos_extra`, `notas`, `base_imponible`, `iva_pct`, `importe_iva`, `irpf_pct`, `importe_irpf`, `importe_total`, `estado`, `hash_factura`, `hash_anterior`, `qr_url`, `verifactu_estado`, `verifactu_respuesta`, `factura_rectificada_id`, `fecha_creacion`, `updated_at`) VALUES
  ('1', '35', '7', '7', '7', 'FAC-202602-00001', '1', 'FAC', 'F1', '2026-02-05', '2026-02-01', '2026-02-01', '2026-02-28', 'Administración de Fincas López', 'B12345678', 'C/ Gran Vía 10, 2º B', '28013', 'Madrid', 'Madrid', 'admin@fincaslopez.es', '91 555 1234', 'ES91 2100 0418 4502 0005 1332', 'Ortega Campos, Beatriz', '66112233H', 'C/ Alcalá 120 3º A', '28009', 'Madrid', 'Madrid', 'beatriz.ortega@gmail.com', 'Alcalá 120 3º A, CP 28009, Madrid', 'Alquiler del inmueble — Febrero 2026', '', '', '750.00', '0.00', '0.00', '0.00', '0.00', '750.00', 'emitida', NULL, NULL, NULL, 'no_enviado', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('2', '34', '8', '8', '8', 'FAC-202601-00001', '1', 'FAC', 'F1', '2026-01-05', '2026-01-01', '2026-01-01', '2026-01-31', 'Administración de Fincas López', 'B12345678', 'C/ Gran Vía 10, 2º B', '28013', 'Madrid', 'Madrid', 'admin@fincaslopez.es', '91 555 1234', 'ES91 2100 0418 4502 0005 1332', 'Navarro Iglesias, Diego', '77223344J', 'C/ Alcalá 120 3º B', '28009', 'Madrid', 'Madrid', 'diego.navarro@outlook.com', 'Alcalá 120 3º B, CP 28009, Madrid', 'Alquiler del inmueble — Enero 2026', '', 'Rectificada por: RET-202607-00001 · emitida el 2026-07-09.', '620.00', '0.00', '0.00', '0.00', '0.00', '620.00', 'rectificada', NULL, NULL, NULL, 'no_enviado', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('3', NULL, '8', '8', '8', 'RET-202607-00001', '1', 'RET', 'R1', '2026-07-09', '2026-01-05', '2026-01-01', '2026-01-31', 'Administración de Fincas López', 'B12345678', 'C/ Gran Vía 10, 2º B', '28013', 'Madrid', 'Madrid', 'admin@fincaslopez.es', '91 555 1234', 'ES91 2100 0418 4502 0005 1332', 'Navarro Iglesias, Diego', '77223344J', 'C/ Alcalá 120 3º B', '28009', 'Madrid', 'Madrid', 'diego.navarro@outlook.com', 'Alcalá 120 3º B, CP 28009, Madrid', 'Rectificación de: Alquiler del inmueble — Enero 2026', '', 'Factura rectificativa de FAC-202601-00001. Anulación total.', '-620.00', '0.00', '0.00', '0.00', '0.00', '-620.00', 'emitida', NULL, NULL, NULL, 'no_enviado', NULL, '2', '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10');

-- Tabla: fincas
DROP TABLE IF EXISTS `fincas`;
CREATE TABLE `fincas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) DEFAULT '',
  `sigla` varchar(10) DEFAULT '',
  `calle` varchar(200) DEFAULT '',
  `numero` varchar(10) DEFAULT '',
  `cp` varchar(10) DEFAULT '',
  `municipio` varchar(100) DEFAULT '',
  `provincia` varchar(100) DEFAULT '',
  `propietario_id` int(11) DEFAULT NULL,
  `observaciones` text,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `eliminado` tinyint(1) NOT NULL DEFAULT '0',
  `eliminado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fincas_eliminado` (`eliminado`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4;

INSERT INTO `fincas` (`id`, `nombre`, `sigla`, `calle`, `numero`, `cp`, `municipio`, `provincia`, `propietario_id`, `observaciones`, `updated_at`, `eliminado`, `eliminado_en`) VALUES
  ('1', 'C/ Mayor 15', 'C', 'Mayor', '15', '28001', 'Madrid', 'Madrid', '1', 'Edificio reformado en 2010', '2026-07-09 19:50:10', '0', NULL),
  ('2', 'Av. Constitución 8', 'AV', 'Constitución', '8', '28002', 'Madrid', 'Madrid', '2', '', '2026-07-09 19:50:10', '0', NULL),
  ('3', 'C/ Alcalá 120', 'AL', 'Alcalá', '120', '28009', 'Madrid', 'Madrid', '3', 'Finca de nueva incorporación', '2026-07-09 19:50:10', '0', NULL),
  ('4', 'C/ Velázquez 22', 'VZ', 'Velázquez', '22', '28006', 'Madrid', 'Madrid', '4', 'Finca con varios contratos sujetos a revisión anual de renta', '2026-07-09 19:50:10', '0', NULL),
  ('5', 'C/ Goya 30', 'GY', 'Goya', '30', '28001', 'Madrid', 'Madrid', '5', 'Finca con contratos de uno y varios inquilinos, para comparar', '2026-07-09 19:50:10', '0', NULL);

-- Tabla: historial_rentas
DROP TABLE IF EXISTS `historial_rentas`;
CREATE TABLE `historial_rentas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contrato_id` int(11) NOT NULL DEFAULT '0',
  `fecha` date NOT NULL,
  `tipo_revision` varchar(50) DEFAULT '',
  `porcentaje` decimal(6,3) DEFAULT '0.000',
  `renta_anterior` decimal(10,2) DEFAULT '0.00',
  `renta_nueva` decimal(10,2) DEFAULT '0.00',
  `observaciones` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_historial_contrato` (`contrato_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;

INSERT INTO `historial_rentas` (`id`, `contrato_id`, `fecha`, `tipo_revision`, `porcentaje`, `renta_anterior`, `renta_nueva`, `observaciones`, `created_at`) VALUES
  ('1', '12', '2026-07-04', 'IPC', '3.000', '750.00', '772.50', 'Revisión IPC aplicada (dato de ejemplo)', '2026-07-09 19:50:10');

-- Tabla: inmuebles
DROP TABLE IF EXISTS `inmuebles`;
CREATE TABLE `inmuebles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `finca_id` int(11) DEFAULT NULL,
  `planta` varchar(20) DEFAULT '',
  `puerta` varchar(10) DEFAULT '',
  `tipo` varchar(50) DEFAULT '',
  `metros` decimal(8,2) DEFAULT '0.00',
  `referencia_catastral` varchar(50) DEFAULT '',
  `cedula` varchar(50) DEFAULT '',
  `observaciones` text,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `eliminado` tinyint(1) NOT NULL DEFAULT '0',
  `eliminado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_inmuebles_eliminado` (`eliminado`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4;

INSERT INTO `inmuebles` (`id`, `finca_id`, `planta`, `puerta`, `tipo`, `metros`, `referencia_catastral`, `cedula`, `observaciones`, `updated_at`, `eliminado`, `eliminado_en`) VALUES
  ('1', '1', '1º', 'A', 'vivienda', '65.00', '', '', 'Exterior, balcón', '2026-07-09 19:50:10', '0', NULL),
  ('2', '1', '1º', 'B', 'vivienda', '62.00', '', '', 'Interior', '2026-07-09 19:50:10', '0', NULL),
  ('3', '1', '2º', 'A', 'vivienda', '70.00', '', '', 'Soleado, orientación sur', '2026-07-09 19:50:10', '0', NULL),
  ('4', '1', '2º', 'B', 'vivienda', '68.00', '', '', '', '2026-07-09 19:50:10', '0', NULL),
  ('5', '2', 'BAJO', 'A', 'local', '90.00', '', '', 'Local comercial con escaparate', '2026-07-09 19:50:10', '0', NULL),
  ('6', '2', '1º', 'A', 'vivienda', '80.00', '', '', '', '2026-07-09 19:50:10', '0', NULL),
  ('7', '3', '3º', 'A', 'vivienda', '75.00', '', '', 'Reformado en 2025', '2026-07-09 19:50:10', '0', NULL),
  ('8', '3', '3º', 'B', 'vivienda', '58.00', '', '', '', '2026-07-09 19:50:10', '0', NULL),
  ('9', '3', '4º', 'A', 'vivienda', '82.00', '', '', 'Ático con terraza', '2026-07-09 19:50:10', '0', NULL),
  ('10', '4', '1º', 'A', 'vivienda', '72.00', '', '', 'Revisión IPC pendiente de aplicar', '2026-07-09 19:50:10', '0', NULL),
  ('11', '4', '1º', 'B', 'vivienda', '66.00', '', '', 'Revisión IRAV próxima (dentro de 30 días)', '2026-07-09 19:50:10', '0', NULL),
  ('12', '4', '2º', 'A', 'vivienda', '78.00', '', '', 'Revisión IPC ya aplicada este año', '2026-07-09 19:50:10', '0', NULL),
  ('13', '4', '2º', 'B', 'vivienda', '64.00', '', '', 'Revisión IRAV lejana, fuera de ventana de aviso', '2026-07-09 19:50:10', '0', NULL),
  ('14', '5', 'BAJO', 'A', 'vivienda', '60.00', '', '', 'Contrato de referencia con un solo inquilino', '2026-07-09 19:50:10', '0', NULL),
  ('15', '5', '1º', 'A', 'vivienda', '85.00', '', '', 'Contrato con un inquilino principal y un inquilino secundario', '2026-07-09 19:50:10', '0', NULL),
  ('16', '5', '1º', 'B', 'vivienda', '95.00', '', '', 'Contrato con un inquilino principal y dos inquilinos secundarios', '2026-07-09 19:50:10', '0', NULL);

-- Tabla: inquilinos
DROP TABLE IF EXISTS `inquilinos`;
CREATE TABLE `inquilinos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) DEFAULT '',
  `nif` varchar(20) DEFAULT '',
  `telefono` varchar(30) DEFAULT '',
  `movil` varchar(30) DEFAULT '',
  `email` varchar(150) DEFAULT '',
  `direccion` varchar(255) DEFAULT '',
  `cp` varchar(10) DEFAULT '',
  `municipio` varchar(100) DEFAULT '',
  `provincia` varchar(100) DEFAULT '',
  `pais` varchar(100) DEFAULT 'España',
  `iban` varchar(50) DEFAULT '',
  `observaciones` text,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `eliminado` tinyint(1) NOT NULL DEFAULT '0',
  `eliminado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_inquilinos_eliminado` (`eliminado`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4;

INSERT INTO `inquilinos` (`id`, `nombre`, `nif`, `telefono`, `movil`, `email`, `direccion`, `cp`, `municipio`, `provincia`, `pais`, `iban`, `observaciones`, `updated_at`, `eliminado`, `eliminado_en`) VALUES
  ('1', 'Rodríguez Pérez, Laura', '11111111C', '611 100 200', '', 'laura.rodriguez@gmail.com', 'C/ Mayor 15 1º A', '28001', 'Madrid', 'Madrid', 'España', 'ES00 1234 5678 9012 3456 7890', '', '2026-07-09 19:50:10', '0', NULL),
  ('2', 'González Sánchez, Pedro', '22222222D', '622 200 300', '', 'pedro.gonzalez@gmail.com', 'C/ Mayor 15 1º B', '28001', 'Madrid', 'Madrid', 'España', '', '', '2026-07-09 19:50:10', '0', NULL),
  ('3', 'Martín López, María', '33333333E', '633 300 400', '', 'maria.martin@outlook.com', 'C/ Mayor 15 2º A', '28001', 'Madrid', 'Madrid', 'España', 'ES11 9876 5432 1098 7654 3210', '', '2026-07-09 19:50:10', '0', NULL),
  ('4', 'Torres Ruiz, Carlos', '44444444F', '644 400 500', '', 'carlos.torres@gmail.com', 'C/ Mayor 15 2º B', '28001', 'Madrid', 'Madrid', 'España', '', 'Paga siempre a principios de mes', '2026-07-09 19:50:10', '0', NULL),
  ('5', 'Comercial Díaz S.L.', 'B98765432', '91 700 1234', '', 'admin@comercialdiaz.es', 'Av. Constitución 8 Bajo A', '28002', 'Madrid', 'Madrid', 'España', 'ES22 5555 6666 7777 8888 9999', 'Local comercial, pago trimestral', '2026-07-09 19:50:10', '0', NULL),
  ('6', 'Jiménez Vega, Sofía', '55555555G', '655 500 600', '', 'sofia.jimenez@gmail.com', 'Av. Constitución 8 1º A', '28002', 'Madrid', 'Madrid', 'España', '', '', '2026-07-09 19:50:10', '0', NULL),
  ('7', 'Ortega Campos, Beatriz', '66112233H', '666 100 200', '', 'beatriz.ortega@gmail.com', 'C/ Alcalá 120 3º A', '28009', 'Madrid', 'Madrid', 'España', 'ES44 3058 0090 1234 5678 9012', '', '2026-07-09 19:50:10', '0', NULL),
  ('8', 'Navarro Iglesias, Diego', '77223344J', '677 200 300', '', 'diego.navarro@outlook.com', 'C/ Alcalá 120 3º B', '28009', 'Madrid', 'Madrid', 'España', '', '', '2026-07-09 19:50:10', '0', NULL),
  ('9', 'Castillo Herrera, Marta', '88334455K', '688 300 400', '', 'marta.castillo@gmail.com', 'C/ Alcalá 120 4º A', '28009', 'Madrid', 'Madrid', 'España', 'ES77 0049 1500 0512 3456 7890', 'Contrato finalizado, referencia histórica', '2026-07-09 19:50:10', '0', NULL),
  ('10', 'Delgado Núñez, Cristina', '91112233L', '611 900 100', '', 'cristina.delgado@gmail.com', 'C/ Velázquez 22 1º A', '28006', 'Madrid', 'Madrid', 'España', '', '', '2026-07-09 19:50:10', '0', NULL),
  ('11', 'Vázquez Roldán, Ismael', '92223344M', '622 900 200', '', 'ismael.vazquez@gmail.com', 'C/ Velázquez 22 1º B', '28006', 'Madrid', 'Madrid', 'España', '', '', '2026-07-09 19:50:10', '0', NULL),
  ('12', 'Bravo Cano, Nuria', '93334455N', '633 900 300', '', 'nuria.bravo@gmail.com', 'C/ Velázquez 22 2º A', '28006', 'Madrid', 'Madrid', 'España', '', '', '2026-07-09 19:50:10', '0', NULL),
  ('13', 'Serra Montoya, Álex', '94445566P', '644 900 400', '', 'alex.serra@gmail.com', 'C/ Velázquez 22 2º B', '28006', 'Madrid', 'Madrid', 'España', '', '', '2026-07-09 19:50:10', '0', NULL),
  ('14', 'Pardo Segura, Lucía', '96667788R', '666 800 100', '', 'lucia.pardo@gmail.com', 'C/ Goya 30 Bajo A', '28001', 'Madrid', 'Madrid', 'España', '', 'Inquilina única — contrato de referencia sin inquilinos secundarios', '2026-07-09 19:50:10', '0', NULL),
  ('15', 'Cabrera Morales, Rubén', '97778899S', '677 800 200', '', 'ruben.cabrera@gmail.com', 'C/ Goya 30 1º A', '28001', 'Madrid', 'Madrid', 'España', '', 'Inquilino principal — contrato con 1 inquilino secundario', '2026-07-09 19:50:10', '0', NULL),
  ('16', 'Iglesias Farto, Patricia', '98889900T', '688 800 300', '', 'patricia.iglesias@gmail.com', 'C/ Goya 30 1º B', '28001', 'Madrid', 'Madrid', 'España', '', 'Inquilina principal — contrato con 2 inquilinos secundarios', '2026-07-09 19:50:10', '0', NULL);

-- Tabla: log_actividad
DROP TABLE IF EXISTS `log_actividad`;
CREATE TABLE `log_actividad` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tipo_accion` varchar(100) NOT NULL DEFAULT '',
  `entidad` varchar(50) DEFAULT '',
  `entidad_id` int(11) DEFAULT NULL,
  `descripcion` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_id` int(11) DEFAULT NULL,
  `usuario_nombre` varchar(150) DEFAULT NULL,
  `usuario_username` varchar(60) DEFAULT NULL,
  `usuario_rol` varchar(20) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_log_fecha` (`fecha`),
  KEY `idx_log_tipo` (`tipo_accion`),
  KEY `idx_log_usuario` (`usuario_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4;

INSERT INTO `log_actividad` (`id`, `fecha`, `tipo_accion`, `entidad`, `entidad_id`, `descripcion`, `created_at`, `usuario_id`, `usuario_nombre`, `usuario_username`, `usuario_rol`, `ip`) VALUES
  ('1', '2026-07-09 20:48:42', 'usuario_creado', 'usuarios', '1', 'Primer administrador creado desde install.php: \"admin\"', '2026-07-09 20:48:42', NULL, 'Sistema', NULL, NULL, '::1'),
  ('2', '2026-07-09 20:49:03', 'login_fallido', 'usuarios', '1', 'Intento fallido para usuario \"admin\"', '2026-07-09 20:49:03', '0', 'admin', 'admin', '', '::1'),
  ('3', '2026-07-09 20:49:15', 'login_correcto', 'usuarios', '1', 'Inicio de sesión de \"admin\"', '2026-07-09 20:49:15', '1', 'Administrador Principal', 'admin', 'admin', '::1'),
  ('4', '2026-07-09 20:53:19', 'usuario_creado', 'usuarios', '2', 'Creado el usuario \"gestor\" (rol: user)', '2026-07-09 20:53:19', '1', 'Administrador Principal', 'admin', 'admin', '::1'),
  ('5', '2026-07-09 20:54:28', 'logout', 'usuarios', '1', 'Cierre de sesión de \"admin\"', '2026-07-09 20:54:28', '1', 'Administrador Principal', 'admin', 'admin', '::1'),
  ('6', '2026-07-09 20:54:58', 'login_correcto', 'usuarios', '2', 'Inicio de sesión de \"gestor\"', '2026-07-09 20:54:58', '2', 'Gestor de Prueba', 'gestor', 'user', '::1');

-- Tabla: plantillas
DROP TABLE IF EXISTS `plantillas`;
CREATE TABLE `plantillas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `tipo_documento` varchar(50) NOT NULL DEFAULT 'otro',
  `descripcion` text,
  `fichero` varchar(255) NOT NULL,
  `activa` tinyint(1) DEFAULT '1',
  `por_defecto` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `eliminado` tinyint(1) NOT NULL DEFAULT '0',
  `eliminado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4;

INSERT INTO `plantillas` (`id`, `nombre`, `tipo_documento`, `descripcion`, `fichero`, `activa`, `por_defecto`, `created_at`, `updated_at`, `eliminado`, `eliminado_en`) VALUES
  ('1', 'Contrato de Vivienda 2026', 'contrato_arrendamiento', 'Contrato de arrendamiento de vivienda (inquilino único)', '20260629202745_0f46bec435f5.docx', '1', '0', '2026-07-09 19:50:10', '2026-07-09 19:50:10', '0', NULL),
  ('2', 'Contrato de Vivienda 2026 (Multi-inquilino)', 'contrato_arrendamiento', 'Contrato de arrendamiento de vivienda con bloque multi-inquilino', '20260629202745_9cd13214a228.docx', '1', '0', '2026-07-09 19:50:10', '2026-07-09 19:50:10', '0', NULL),
  ('3', 'Contrato de Vivienda Temporada 2026', 'contrato_arrendamiento', 'Contrato de arrendamiento de vivienda de temporada (inquilino único)', '20260629202745_3646151941bb.docx', '1', '0', '2026-07-09 19:50:10', '2026-07-09 19:50:10', '0', NULL),
  ('4', 'Contrato de Vivienda Temporada 2026 (Multi-inquilino)', 'contrato_arrendamiento', 'Contrato de arrendamiento de vivienda de temporada con bloque multi-inquilino', '20260629202745_0f945bd575bb.docx', '1', '0', '2026-07-09 19:50:10', '2026-07-09 19:50:10', '0', NULL),
  ('5', 'Inventario del Contrato 2026', 'otro', 'Anexo de inventario y estado del inmueble (inquilino único)', '20260629202745_696725afdf36.docx', '1', '0', '2026-07-09 19:50:10', '2026-07-09 19:50:10', '0', NULL),
  ('6', 'Inventario del Contrato 2026 (Multi-inquilino)', 'otro', 'Anexo de inventario y estado del inmueble con bloque multi-inquilino', '20260629202745_8e862f292091.docx', '1', '0', '2026-07-09 19:50:10', '2026-07-09 19:50:10', '0', NULL);

-- Tabla: propietarios
DROP TABLE IF EXISTS `propietarios`;
CREATE TABLE `propietarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) DEFAULT '',
  `nif` varchar(20) DEFAULT '',
  `telefono` varchar(30) DEFAULT '',
  `email` varchar(150) DEFAULT '',
  `irpf` varchar(1) DEFAULT '',
  `direccion` varchar(255) DEFAULT '',
  `cp` varchar(10) DEFAULT '',
  `municipio` varchar(100) DEFAULT '',
  `provincia` varchar(100) DEFAULT '',
  `pais` varchar(100) DEFAULT 'España',
  `iban` varchar(50) DEFAULT '',
  `observaciones` text,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `eliminado` tinyint(1) NOT NULL DEFAULT '0',
  `eliminado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_propietarios_eliminado` (`eliminado`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4;

INSERT INTO `propietarios` (`id`, `nombre`, `nif`, `telefono`, `email`, `irpf`, `direccion`, `cp`, `municipio`, `provincia`, `pais`, `iban`, `observaciones`, `updated_at`, `eliminado`, `eliminado_en`) VALUES
  ('1', 'García Martínez, Manuel', '12345678A', '650 111 222', 'manuel@email.com', '', '', '', '', '', 'España', '', '', '2026-07-09 19:50:10', '0', NULL),
  ('2', 'Fernández Torres, Ana', '87654321B', '660 333 444', 'ana.fernandez@email.com', '', '', '', '', '', 'España', '', '', '2026-07-09 19:50:10', '0', NULL),
  ('3', 'Ruiz Delgado, Francisco', '66778899Q', '699 222 333', 'francisco.ruiz@email.com', '', '', '', '', '', 'España', '', 'Propietario de alta reciente', '2026-07-09 19:50:10', '0', NULL),
  ('4', 'Molina Vidal, Teresa', '99001122X', '699 555 111', 'teresa.molina@email.com', '', '', '', '', '', 'España', '', 'Propietaria — cartera con contratos indexados a IPC/IRAV', '2026-07-09 19:50:10', '0', NULL),
  ('5', 'Santos Prieto, Álvaro', '95556677Q', '655 777 888', 'alvaro.santos@email.com', '', '', '', '', '', 'España', '', 'Propietario — cartera con contratos multi-inquilino', '2026-07-09 19:50:10', '0', NULL),
  ('7', 'PRUEBA E2E Sin Fincas', '', '', '', '', '', '', '', '', 'España', '', '', '2026-07-09 20:09:19', '1', '2026-07-09 20:09:19');

-- Tabla: recibos
DROP TABLE IF EXISTS `recibos`;
CREATE TABLE `recibos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contrato_id` int(11) DEFAULT NULL,
  `inquilino_id` int(11) DEFAULT NULL,
  `inmueble_id` int(11) DEFAULT NULL,
  `numero_recibo` varchar(50) DEFAULT '',
  `numero_seq` int(11) DEFAULT '0',
  `fecha_emision` date DEFAULT NULL,
  `periodo_desde` date DEFAULT NULL,
  `periodo_hasta` date DEFAULT NULL,
  `concepto_periodo` varchar(100) DEFAULT '',
  `fecha_limite` date DEFAULT NULL,
  `renta_base` decimal(10,2) DEFAULT '0.00',
  `importe_iva` decimal(10,2) DEFAULT '0.00',
  `importe_irpf` decimal(10,2) DEFAULT '0.00',
  `importe_total` decimal(10,2) DEFAULT '0.00',
  `importe_pagado` decimal(10,2) DEFAULT '0.00',
  `conceptos_extra` text,
  `notas` text,
  `pagos` text,
  `estado` varchar(20) DEFAULT 'pendiente',
  `aviso_recibo` tinyint(1) DEFAULT '0',
  `factura_id` int(11) DEFAULT NULL,
  `recibo_rectificado_id` int(11) DEFAULT NULL,
  `fecha_creacion` varchar(50) DEFAULT '',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_recibos_numero_recibo` (`numero_recibo`),
  KEY `idx_recibos_estado` (`estado`),
  KEY `idx_recibos_contrato` (`contrato_id`),
  KEY `idx_recibos_inquilino` (`inquilino_id`),
  KEY `idx_recibos_inmueble` (`inmueble_id`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4;

INSERT INTO `recibos` (`id`, `contrato_id`, `inquilino_id`, `inmueble_id`, `numero_recibo`, `numero_seq`, `fecha_emision`, `periodo_desde`, `periodo_hasta`, `concepto_periodo`, `fecha_limite`, `renta_base`, `importe_iva`, `importe_irpf`, `importe_total`, `importe_pagado`, `conceptos_extra`, `notas`, `pagos`, `estado`, `aviso_recibo`, `factura_id`, `recibo_rectificado_id`, `fecha_creacion`, `updated_at`) VALUES
  ('1', '1', '1', '1', 'REC-202603-00001', '1', '2026-03-01', NULL, NULL, 'Marzo 2026', '2026-03-05', '700.00', '0.00', '0.00', '700.00', '700.00', NULL, NULL, '[{\"fecha\":\"2026-03-05\",\"importe\":700,\"metodo\":\"transferencia\",\"cuenta\":\"ES91 2100 0418\"}]', 'cobrado', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('2', '2', '2', '2', 'REC-202603-00002', '2', '2026-03-01', NULL, NULL, 'Marzo 2026', '2026-03-05', '650.00', '0.00', '0.00', '650.00', '650.00', NULL, NULL, '[{\"fecha\":\"2026-03-05\",\"importe\":650,\"metodo\":\"transferencia\",\"cuenta\":\"ES91 2100 0418\"}]', 'cobrado', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('3', '3', '3', '3', 'REC-202603-00003', '3', '2026-03-01', NULL, NULL, 'Marzo 2026', '2026-03-05', '720.00', '0.00', '0.00', '720.00', '720.00', NULL, NULL, '[{\"fecha\":\"2026-03-05\",\"importe\":720,\"metodo\":\"transferencia\",\"cuenta\":\"ES91 2100 0418\"}]', 'cobrado', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('4', '4', '4', '4', 'REC-202603-00004', '4', '2026-03-01', NULL, NULL, 'Marzo 2026', '2026-03-05', '680.00', '0.00', '0.00', '680.00', '680.00', NULL, NULL, '[{\"fecha\":\"2026-03-05\",\"importe\":680,\"metodo\":\"transferencia\",\"cuenta\":\"ES91 2100 0418\"}]', 'cobrado', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('5', '5', '5', '5', 'REC-202603-00005', '5', '2026-03-01', NULL, NULL, 'Marzo 2026', '2026-03-05', '1200.00', '252.00', '0.00', '1452.00', '1452.00', NULL, NULL, '[{\"fecha\":\"2026-03-05\",\"importe\":1452,\"metodo\":\"transferencia\",\"cuenta\":\"ES91 2100 0418\"}]', 'cobrado', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('6', '6', '6', '6', 'REC-202603-00006', '6', '2026-03-01', NULL, NULL, 'Marzo 2026', '2026-03-05', '800.00', '0.00', '0.00', '800.00', '800.00', NULL, NULL, '[{\"fecha\":\"2026-03-05\",\"importe\":800,\"metodo\":\"transferencia\",\"cuenta\":\"ES91 2100 0418\"}]', 'cobrado', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('7', '1', '1', '1', 'REC-202604-00001', '1', '2026-04-01', NULL, NULL, 'Abril 2026', '2026-04-05', '700.00', '0.00', '0.00', '700.00', '700.00', NULL, NULL, '[{\"fecha\":\"2026-04-05\",\"importe\":700,\"metodo\":\"transferencia\",\"cuenta\":\"ES91 2100 0418\"}]', 'cobrado', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('8', '2', '2', '2', 'REC-202604-00002', '2', '2026-04-01', NULL, NULL, 'Abril 2026', '2026-04-05', '650.00', '0.00', '0.00', '650.00', '650.00', NULL, NULL, '[{\"fecha\":\"2026-04-05\",\"importe\":650,\"metodo\":\"transferencia\",\"cuenta\":\"ES91 2100 0418\"}]', 'cobrado', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('9', '3', '3', '3', 'REC-202604-00003', '3', '2026-04-01', NULL, NULL, 'Abril 2026', '2026-04-05', '720.00', '0.00', '0.00', '720.00', '720.00', NULL, NULL, '[{\"fecha\":\"2026-04-05\",\"importe\":720,\"metodo\":\"transferencia\",\"cuenta\":\"ES91 2100 0418\"}]', 'cobrado', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('10', '4', '4', '4', 'REC-202604-00004', '4', '2026-04-01', NULL, NULL, 'Abril 2026', '2026-04-05', '680.00', '0.00', '0.00', '680.00', '680.00', NULL, NULL, '[{\"fecha\":\"2026-04-05\",\"importe\":680,\"metodo\":\"transferencia\",\"cuenta\":\"ES91 2100 0418\"}]', 'cobrado', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('11', '5', '5', '5', 'REC-202604-00005', '5', '2026-04-01', NULL, NULL, 'Abril 2026', '2026-04-05', '1200.00', '252.00', '0.00', '1452.00', '1452.00', NULL, NULL, '[{\"fecha\":\"2026-04-05\",\"importe\":1452,\"metodo\":\"transferencia\",\"cuenta\":\"ES91 2100 0418\"}]', 'cobrado', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('12', '6', '6', '6', 'REC-202604-00006', '6', '2026-04-01', NULL, NULL, 'Abril 2026', '2026-04-05', '800.00', '0.00', '0.00', '800.00', '800.00', NULL, NULL, '[{\"fecha\":\"2026-04-05\",\"importe\":800,\"metodo\":\"transferencia\",\"cuenta\":\"ES91 2100 0418\"}]', 'cobrado', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('13', '1', '1', '1', 'REC-202605-00001', '1', '2026-05-01', NULL, NULL, 'Mayo 2026', '2026-05-05', '700.00', '0.00', '0.00', '700.00', '700.00', NULL, NULL, '[{\"fecha\":\"2026-05-05\",\"importe\":700,\"metodo\":\"transferencia\",\"cuenta\":\"ES91 2100 0418\"}]', 'cobrado', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('14', '2', '2', '2', 'REC-202605-00002', '2', '2026-05-01', NULL, NULL, 'Mayo 2026', '2026-05-05', '650.00', '0.00', '0.00', '650.00', '650.00', NULL, NULL, '[{\"fecha\":\"2026-05-05\",\"importe\":650,\"metodo\":\"transferencia\",\"cuenta\":\"ES91 2100 0418\"}]', 'cobrado', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('15', '3', '3', '3', 'REC-202605-00003', '3', '2026-05-01', NULL, NULL, 'Mayo 2026', '2026-05-05', '720.00', '0.00', '0.00', '720.00', '720.00', NULL, NULL, '[{\"fecha\":\"2026-05-05\",\"importe\":720,\"metodo\":\"transferencia\",\"cuenta\":\"ES91 2100 0418\"}]', 'cobrado', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('16', '4', '4', '4', 'REC-202605-00004', '4', '2026-05-01', NULL, NULL, 'Mayo 2026', '2026-05-05', '680.00', '0.00', '0.00', '680.00', '680.00', NULL, NULL, '[{\"fecha\":\"2026-05-05\",\"importe\":680,\"metodo\":\"transferencia\",\"cuenta\":\"ES91 2100 0418\"}]', 'cobrado', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('17', '5', '5', '5', 'REC-202605-00005', '5', '2026-05-01', NULL, NULL, 'Mayo 2026', '2026-05-05', '1200.00', '252.00', '0.00', '1452.00', '1452.00', NULL, NULL, '[{\"fecha\":\"2026-05-05\",\"importe\":1452,\"metodo\":\"transferencia\",\"cuenta\":\"ES91 2100 0418\"}]', 'cobrado', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('18', '6', '6', '6', 'REC-202605-00006', '6', '2026-05-01', NULL, NULL, 'Mayo 2026', '2026-05-05', '800.00', '0.00', '0.00', '800.00', '800.00', NULL, NULL, '[{\"fecha\":\"2026-05-05\",\"importe\":800,\"metodo\":\"transferencia\",\"cuenta\":\"ES91 2100 0418\"}]', 'cobrado', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('19', '1', '1', '1', 'REC-202606-00001', '1', '2026-06-01', NULL, NULL, 'Junio 2026', '2026-06-05', '700.00', '0.00', '0.00', '700.00', '0.00', NULL, NULL, '[]', 'pendiente', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('20', '2', '2', '2', 'REC-202606-00002', '2', '2026-06-01', NULL, NULL, 'Junio 2026', '2026-06-05', '650.00', '0.00', '0.00', '650.00', '0.00', NULL, NULL, '[]', 'pendiente', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('21', '3', '3', '3', 'REC-202606-00003', '3', '2026-06-01', NULL, NULL, 'Junio 2026', '2026-06-05', '720.00', '0.00', '0.00', '720.00', '360.00', NULL, NULL, '[{\"fecha\":\"2026-06-10\",\"importe\":360,\"metodo\":\"transferencia\",\"cuenta\":\"ES91 2100 0418\"}]', 'parcial', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('22', '4', '4', '4', 'REC-202606-00004', '4', '2026-06-01', NULL, NULL, 'Junio 2026', '2026-06-05', '680.00', '0.00', '0.00', '680.00', '0.00', NULL, NULL, '[]', 'pendiente', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('23', '5', '5', '5', 'REC-202606-00005', '5', '2026-06-01', NULL, NULL, 'Junio 2026', '2026-06-05', '1200.00', '252.00', '0.00', '1452.00', '0.00', NULL, NULL, '[]', 'pendiente', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('24', '6', '6', '6', 'REC-202606-00006', '6', '2026-06-01', NULL, NULL, 'Junio 2026', '2026-06-05', '800.00', '0.00', '0.00', '800.00', '0.00', NULL, NULL, '[]', 'pendiente', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('25', '1', '1', '1', 'REC-202607-00001', '1', '2026-07-01', NULL, NULL, 'Julio 2026', '2026-07-05', '700.00', '0.00', '0.00', '700.00', '0.00', NULL, NULL, '[]', 'pendiente', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('26', '2', '2', '2', 'REC-202607-00002', '2', '2026-07-01', NULL, NULL, 'Julio 2026', '2026-07-05', '650.00', '0.00', '0.00', '650.00', '0.00', NULL, NULL, '[]', 'pendiente', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('27', '3', '3', '3', 'REC-202607-00003', '3', '2026-07-01', NULL, NULL, 'Julio 2026', '2026-07-05', '720.00', '0.00', '0.00', '720.00', '0.00', NULL, NULL, '[]', 'pendiente', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('28', '4', '4', '4', 'REC-202607-00004', '4', '2026-07-01', NULL, NULL, 'Julio 2026', '2026-07-05', '680.00', '0.00', '0.00', '680.00', '0.00', NULL, NULL, '[]', 'pendiente', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('29', '5', '5', '5', 'REC-202607-00005', '5', '2026-07-01', NULL, NULL, 'Julio 2026', '2026-07-05', '1200.00', '252.00', '0.00', '1452.00', '0.00', NULL, NULL, '[]', 'pendiente', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('30', '6', '6', '6', 'REC-202607-00006', '6', '2026-07-01', NULL, NULL, 'Julio 2026', '2026-07-05', '800.00', '0.00', '0.00', '800.00', '0.00', NULL, NULL, '[]', 'pendiente', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('31', '9', '9', '9', 'REC-202512-00001', '1', '2025-12-01', NULL, NULL, 'Diciembre 2025', '2025-12-01', '900.00', '0.00', '0.00', '900.00', '900.00', NULL, NULL, '[{\"fecha\":\"2025-12-01\",\"importe\":900,\"metodo\":\"transferencia\",\"cuenta\":\"ES91 2100 0418\"}]', 'cobrado', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('32', '7', '7', '7', 'REC-202512-00002', '2', '2025-12-01', NULL, NULL, 'Diciembre 2025', '2025-12-01', '750.00', '0.00', '0.00', '750.00', '750.00', NULL, NULL, '[{\"fecha\":\"2025-12-01\",\"importe\":750,\"metodo\":\"transferencia\",\"cuenta\":\"ES91 2100 0418\"}]', 'cobrado', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('33', '7', '7', '7', 'REC-202601-00001', '1', '2026-01-01', NULL, NULL, 'Enero 2026', '2026-01-01', '750.00', '0.00', '0.00', '750.00', '750.00', NULL, NULL, '[{\"fecha\":\"2026-01-01\",\"importe\":750,\"metodo\":\"transferencia\",\"cuenta\":\"ES91 2100 0418\"}]', 'cobrado', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('34', '8', '8', '8', 'REC-202601-00002', '2', '2026-01-01', NULL, NULL, 'Enero 2026', '2026-01-01', '620.00', '0.00', '0.00', '620.00', '620.00', NULL, NULL, '[{\"fecha\":\"2026-01-01\",\"importe\":620,\"metodo\":\"transferencia\",\"cuenta\":\"ES91 2100 0418\"}]', 'cobrado', '0', '2', NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('35', '7', '7', '7', 'REC-202602-00001', '1', '2026-02-01', NULL, NULL, 'Febrero 2026', '2026-02-01', '750.00', '0.00', '0.00', '750.00', '750.00', NULL, 'Recibo anulado. La factura FAC-202602-00001 sigue emitida; revisar en Facturas si procede rectificarla.', '[{\"fecha\":\"2026-02-01\",\"importe\":750,\"metodo\":\"transferencia\",\"cuenta\":\"ES91 2100 0418\"}]', 'anulado', '0', '1', NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('36', '8', '8', '8', 'REC-202602-00002', '2', '2026-02-01', NULL, NULL, 'Febrero 2026', '2026-02-01', '620.00', '0.00', '0.00', '620.00', '0.00', NULL, 'Rectificado por: RER-202607-00001 · emitido el 2026-07-09.', '[]', 'anulado', '0', NULL, NULL, '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10'),
  ('37', '8', '8', '8', 'RER-202607-00001', '1', '2026-07-09', NULL, NULL, 'Rectificación de: Febrero 2026', '2026-07-09', '-620.00', '0.00', '0.00', '-620.00', '0.00', NULL, 'Recibo rectificativo de REC-202602-00002. Anulación total.', '[]', 'rectificativo', '0', NULL, '36', '2026-07-09T18:50:10+01:00', '2026-07-09 19:50:10');

-- Tabla: usuarios
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT '',
  `username` varchar(60) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol` varchar(20) NOT NULL DEFAULT 'user',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `ultimo_login` datetime DEFAULT NULL,
  `creado_en` datetime DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `eliminado_en` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuarios_username` (`username`),
  KEY `idx_usuarios_rol` (`rol`),
  KEY `idx_usuarios_activo` (`activo`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4;

INSERT INTO `usuarios` (`id`, `nombre`, `email`, `username`, `password_hash`, `rol`, `activo`, `ultimo_login`, `creado_en`, `actualizado_en`, `eliminado_en`) VALUES
  ('1', 'Administrador Principal', 'admin@alquigest.local', 'admin', '$2y$10$6.ZCqjCcHZUbPMQs5iMfQeoch1gEm/iCkiZna32Pl7k.0W3SxRS.q', 'admin', '1', '2026-07-09 20:49:15', '2026-07-09 20:48:42', '2026-07-09 20:49:15', NULL),
  ('2', 'Gestor de Prueba', 'gestor@alquigest.local', 'gestor', '$2y$10$JMngKSNqtUb85w0jsCU52eJ202oD32ffn4LyqezPomIRAB11l8wcO', 'user', '1', '2026-07-09 20:54:58', '2026-07-09 20:53:19', '2026-07-09 20:54:58', NULL);

SET FOREIGN_KEY_CHECKS = 1;
