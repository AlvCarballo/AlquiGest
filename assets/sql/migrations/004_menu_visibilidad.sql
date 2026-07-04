-- ============================================================
-- Migración 004: Visibilidad del menú lateral desde Parámetros
-- Fecha: 2026-06-30
-- Descripción: Inserta las claves menu_* en la tabla configuracion
--              con valor '1' (visible) si aún no existen.
--              Usar ON DUPLICATE KEY para que sea re-ejecutable.
-- ============================================================

INSERT INTO `configuracion` (`variable`, `valor`, `descripcion`)
VALUES
  ('menu_propietarios', '1', 'Muestra la opción Propietarios en el menú lateral (1=visible, 0=oculto).'),
  ('menu_fincas',       '1', 'Muestra la opción Fincas / Edificios en el menú lateral (1=visible, 0=oculto).'),
  ('menu_inmuebles',    '1', 'Muestra la opción Pisos / Locales en el menú lateral (1=visible, 0=oculto).'),
  ('menu_inquilinos',   '1', 'Muestra la opción Inquilinos en el menú lateral (1=visible, 0=oculto).'),
  ('menu_contratos',    '1', 'Muestra la opción Contratos en el menú lateral (1=visible, 0=oculto).'),
  ('menu_recibos',      '1', 'Muestra la opción Recibos en el menú lateral (1=visible, 0=oculto).'),
  ('menu_facturas',     '1', 'Muestra la opción Facturas en el menú lateral (1=visible, 0=oculto).'),
  ('menu_generar',      '1', 'Muestra la opción Generar Recibos en el menú lateral (1=visible, 0=oculto).'),
  ('menu_informes',     '1', 'Muestra la opción Informes Excel en el menú lateral (1=visible, 0=oculto).'),
  ('menu_calendario',   '1', 'Muestra la opción Calendario Cobros en el menú lateral (1=visible, 0=oculto).'),
  ('menu_morosidad',    '1', 'Muestra la opción Morosidad en el menú lateral (1=visible, 0=oculto).'),
  ('menu_actividad',    '1', 'Muestra la opción Actividad en el menú lateral (1=visible, 0=oculto).'),
  ('menu_empresa',      '1', 'Muestra la opción Mi Empresa en el menú lateral (1=visible, 0=oculto).'),
  ('menu_verifactu',    '1', 'Muestra la opción VERI*FACTU en el menú lateral (1=visible, 0=oculto).'),
  ('menu_plantillas',   '1', 'Muestra la opción Plantillas en el menú lateral (1=visible, 0=oculto).')
ON DUPLICATE KEY UPDATE `variable` = `variable`;
-- ON DUPLICATE KEY es un no-op si ya existe: nunca sobreescribe el valor del usuario.
