# Plan: Visibilidad del Menú desde Parámetros

**Fecha:** 30/06/2026  
**Estado:** En implementación

---

## 1. Objetivo

Permitir al usuario ocultar visualmente opciones del menú lateral desde la página de Parámetros, sin modificar código. Dashboard y Parámetros son siempre visibles y no aparecen en la configuración.

---

## 2. Diseño de la solución

### Decisiones de arquitectura

| Alternativa | Decisión | Motivo |
|---|---|---|
| Nueva tabla DB vs. tabla `configuracion` | Usar `configuracion` existente | Patrón ya usado para `VisiBorrar*`. Sin migraciones nuevas. |
| Claves de configuración | Prefijo `menu_` + nombre de página | Coherente, fácil de leer, extensible. Ej: `menu_facturas` |
| IDs en el menú | Añadir `id="nav-{page}"` a todos los items | Consistente con `nav-verifactu` y `nav-plantillas` ya existentes. |
| Ocultar grupos vacíos | Detectar y ocultar `nav-group` si todos sus hijos están ocultos | UX limpia: el título del grupo desaparece si no tiene items visibles. |
| Función de aplicación | `_aplicarVisibilidadMenu()` en `helpers.js` | Reutilizable, llamada al arranque y tras guardar configuración. |

### Items controlables (excluidos: dashboard, configuracion)

| Clave config | Etiqueta menú | Grupo |
|---|---|---|
| `menu_propietarios` | Propietarios | Maestros |
| `menu_fincas` | Fincas / Edificios | Maestros |
| `menu_inmuebles` | Pisos / Locales | Maestros |
| `menu_inquilinos` | Inquilinos | Maestros |
| `menu_contratos` | Contratos | Alquileres |
| `menu_recibos` | Recibos | Alquileres |
| `menu_facturas` | Facturas | Alquileres |
| `menu_generar` | Generar Recibos | Alquileres |
| `menu_informes` | Informes Excel | Informes |
| `menu_calendario` | Calendario Cobros | Informes |
| `menu_morosidad` | Morosidad | Informes |
| `menu_actividad` | Actividad | Informes |
| `menu_empresa` | Mi Empresa | Configuración |
| `menu_verifactu` | VERI*FACTU | Configuración |
| `menu_plantillas` | Plantillas | Configuración |

---

## 3. Archivos afectados

| Archivo | Cambio |
|---|---|
| `assets/docs/PLAN_VISIBILIDAD_MENU.md` | Este documento |
| `AlquiGest.php` | Añadir `id="nav-{page}"` e `id="nav-group-{grupo}"` al HTML del menú |
| `assets/js/helpers.js` | Añadir función `_aplicarVisibilidadMenu()` |
| `assets/js/init.js` | Llamar a `_aplicarVisibilidadMenu()` tras `DB.init()` |
| `assets/js/configuracion.js` | Añadir nueva pestaña "Visibilidad del menú" en `_CFG_GRUPOS` |
| `assets/php/install.php` | Añadir los 15 defaults `menu_*` en `$configDefaults` |
| `AlquiGest.php` | Actualizar cache busters de los JS modificados |

---

## 4. Cambios en Base de Datos

No se añade ninguna tabla. Se insertan 15 nuevas filas en la tabla `configuracion`:

```sql
INSERT INTO configuracion (variable, valor, descripcion) VALUES
  ('menu_propietarios', '1', 'Muestra la opción Propietarios en el menú lateral.'),
  ('menu_fincas',       '1', 'Muestra la opción Fincas / Edificios en el menú lateral.'),
  -- ... (ver install.php para lista completa)
ON DUPLICATE KEY UPDATE id = id;
```

Valor `'1'` = visible, `'0'` = oculto. Por defecto todas visibles.

---

## 5. Checklist de tareas

### Análisis
- [x] Analizar estructura del menú en `AlquiGest.php`
- [x] Localizar el patrón `VisiBorrar*` en `configuracion` (sin nueva tabla)
- [x] Verificar que `getConfigVar` y `saveConfigVar` ya existen en `verifactu.js`
- [x] Confirmar que `_cfgGet` en `helpers.js` y `guardarConfigGrupo` en `configuracion.js` son reutilizables
- [x] Decidir prefijo de claves: `menu_*`

### Implementación
- [x] Crear este documento `PLAN_VISIBILIDAD_MENU.md`
- [x] Añadir `id="nav-{page}"` e `id="nav-group-{grupo}"` al HTML del menú en `AlquiGest.php`
- [x] Añadir `_aplicarVisibilidadMenu()` en `helpers.js`
- [x] Llamar a `_aplicarVisibilidadMenu()` en `init.js`
- [x] Añadir nueva pestaña "Visibilidad del menú" en `configuracion.js`
- [x] Hookear re-aplicación del menú tras guardar la pestaña
- [x] Añadir 15 defaults `menu_*` en `install.php`
- [x] Crear migración `004_menu_visibilidad.sql` y ejecutarla en la BD
- [x] Actualizar cache busters en `AlquiGest.php` (v=20260630b)

### Verificación
- [x] Ocultar una opción (Facturas) → desaparece del menú inmediatamente ✅
- [x] Volver a mostrarla → reaparece ✅
- [x] Recargar la app → la configuración persiste (Maestros seguía oculto tras F5) ✅
- [x] Verificar que Dashboard siempre visible ✅
- [x] Verificar que Parámetros siempre visible ✅
- [x] Ocultar todos los items de un grupo → el título del grupo desaparece ✅
- [x] Ocultar todas las opciones de "Maestros" → el grupo "Maestros" desaparece ✅
- [x] Restaurar → el grupo reaparece ✅
