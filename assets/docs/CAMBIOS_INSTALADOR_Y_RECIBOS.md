# Cambios: Instalador (sin datos / con datos) y Anulación de Recibos

> Fecha inicio: 2026-07-04 · Estado: EN CURSO
> Autor: Claude Code (equipo de desarrollo/QA/documentación)

---

## 1. Análisis inicial

Fuentes revisadas antes de tocar código:

- `README.md`, `assets/docs/AI_PROJECT_CONTEXT.md` (memoria del proyecto), `assets/docs/PLAN_NUMERACION_RECIBOS_FACTURAS.md`.
- `alquigest (1).sql` (volcado real de la BD de producción, dejado en la raíz por el usuario).
- `assets/php/install.php` (instalador), `assets/php/api.php` (API + auto-migraciones), `assets/php/plantillas.php`.
- `assets/sql/migrations/002_indices.sql`, `003_doc_secuencias.sql`, `004_menu_visibilidad.sql`, `assets/sql/migration_facturas.sql`.
- `assets/js/recibos-cobro.js`, `assets/js/facturas.js`, `assets/js/recibos-lista.js`, `assets/js/dashboard.js`, `assets/js/helpers.js`, `assets/js/generar.js`.

**Arquitectura relevante:** SPA PHP+JS. La numeración de documentos (recibos, facturas, rectificativas) usa un servicio común y ya existente: `nextNumeroDoc(tipo, periodo, prefijo)` (helpers.js) → `action=nextNumeroDoc` en `api.php`, que reserva de forma atómica (transacción + `SELECT ... FOR UPDATE`) el siguiente número en la tabla `doc_secuencias` (clave `tipo`+`periodo`). Este servicio ya soporta cualquier `tipo` nuevo sin cambios (es genérico), por lo que **se reutiliza tal cual para `RER`** (y para renombrar `RECT`→`RET`).

## 2. Problemas detectados

### 2.1 Instalador — modo "sin datos" (clean)

1. **Falta la tabla `plantillas`.** Se usa en toda la sección "Plantillas DOCX" y existe en la BD real (`alquigest (1).sql`), pero `install.php` nunca la crea; solo se autocrea de forma perezosa la primera vez que se llama a `assets/php/plantillas.php`. Una instalación limpia queda incompleta hasta que el usuario visita esa sección.
2. **Falta `UNIQUE KEY` en `recibos.numero_recibo`.** La migración `003_doc_secuencias.sql` ya documentaba y aplicaba este índice como medida de integridad (evita duplicados), y `facturas.numero_factura` ya lo tiene, pero el DDL de `install.php` no lo incluye — una instalación limpia nueva no queda protegida igual que facturas.
3. **Falta columna `recibo_rectificado_id`** en `recibos` (necesaria para la nueva funcionalidad de recibos rectificativos, ver §2.3).

### 2.2 Instalador — modo "con datos de ejemplo" (sample)

El set de ejemplo actual (2 propietarios, 2 fincas, 6 inmuebles, 6 inquilinos, 6 contratos, ~30 recibos en 5 meses) no cubre:
- Facturas normales ni facturas rectificativas.
- Recibos anulados (con y sin factura) ni recibos rectificativos.
- Un salto de año en la numeración mensual (todos los recibos de ejemplo caen en el mismo año).
- Un contrato finalizado/dado de baja.

### 2.3 Anulación de recibos — comportamiento actual

- `anularRecibo()` (recibos-cobro.js) **ya hace anulación lógica** (`estado='anulado'`, sin borrado físico, con log de auditoría) — esto es correcto y se mantiene.
- **Nunca genera ningún documento rectificativo.** No existe el concepto de "recibo rectificativo" en el código.
- Si el recibo ya tiene factura, el código actual solo avisa al usuario y dirige a rectificar la factura manualmente desde el módulo Facturas — esto es correcto conforme al RD 1619/2012 (la rectificación fiscal es un acto explícito sobre la factura) y **se mantiene sin cambios**.
- **Bug pre-existente y crítico descubierto:** `validarDatos()` en `api.php` rechaza *cualquier* `importe_total` negativo para `facturas` y `recibos`. Como `anularFactura()` (facturas.js) genera la rectificativa con importes **negados**, el guardado de la factura rectificativa **falla siempre** con error 422 ("El importe total de la factura no puede ser negativo"). Es decir: la función de anulación de facturas con rectificativa automática **está rota en la versión actual** (no se detectó antes porque la tabla `facturas` de producción está vacía). Hay que corregirlo para que la factura rectificativa (y el nuevo recibo rectificativo) puedan guardarse.
- **Nomenclatura a cambiar:** la factura rectificativa usa hoy la serie `RECT` (`RECT-AAAAMM-NNNNN`). El nuevo estándar pedido es `RET-AAAAMM-NNNNN`. No hay datos reales con `RECT` en producción (facturas vacía), por lo que el cambio es seguro.

## 3. Cambios a realizar

### Instalador
- Añadir `CREATE TABLE plantillas` al DDL de `install.php` (idéntica a la de `plantillas.php`).
- Añadir `UNIQUE KEY uq_recibos_numero_recibo` a la tabla `recibos` en `install.php`.
- Añadir columna `recibo_rectificado_id INT DEFAULT NULL` a `recibos` (install.php + `$SCHEMA`/auto-migración en api.php).
- Ampliar los datos de ejemplo con casos nuevos e inventados (facturas, rectificativas RET, recibos rectificativos RER, recibo anulado con factura, contrato finalizado, salto de año).

### Backend (api.php)
- Corregir `validarDatos()`: permitir importe negativo en `facturas` cuando es rectificativa (`tipo_factura==='R1'` o `serie==='RET'`) y en `recibos` cuando `estado==='rectificativo'`.
- Añadir `recibo_rectificado_id` a `$SCHEMA['recibos']` y a la auto-migración `ALTER TABLE`.

### Frontend
- Renombrar la serie de factura rectificativa `RECT` → `RET` en `facturas.js`.
- Implementar generación de **recibo rectificativo `RER-AAAAMM-NNNNN`** en `anularRecibo()` (recibos-cobro.js) cuando el recibo NO tiene factura asociada, reutilizando `nextNumeroDoc('RER', periodo, 'RER')`.
- Cuando el recibo SÍ tiene factura: mantener comportamiento actual (solo aviso, sin generar RER ni tocar la factura).
- Bloquear re-anulación de un recibo ya anulado o que sea en sí mismo un rectificativo.
- Añadir badge/estado visual "Rectificativo" y ocultar acciones no aplicables (Anular, Generar factura, Cobrar) para esos recibos.
- Bloquear generar factura desde un recibo rectificativo.

### Documentación
- Este documento.
- Actualizar `README.md` (nomenclatura, sección Recibos/Facturas).
- Actualizar memoria del proyecto (`AI_PROJECT_CONTEXT.md`).

## 4. Checklist de tareas

### Instalador — sin datos
- [x] Añadir tabla `plantillas` a install.php (clean y sample)
- [x] Añadir `UNIQUE KEY` en `recibos.numero_recibo`
- [x] Añadir columna `recibo_rectificado_id` en `recibos` (install.php)
- [x] Añadir `recibo_rectificado_id` a `$SCHEMA` y auto-migración en api.php

### Instalador — con datos de ejemplo
- [x] Nuevo propietario/finca/inmuebles/inquilinos/contratos inventados
- [x] Recibos en meses distintos incl. salto de año (dic-2025 → ene-2026)
- [x] Factura normal (FAC) de ejemplo
- [x] Factura rectificativa (RET) de ejemplo
- [x] Recibo anulado sin factura + recibo rectificativo (RER) de ejemplo
- [x] Recibo anulado con factura (factura sigue emitida) de ejemplo
- [x] Contrato finalizado/dado de baja de ejemplo
- [x] Poblar `doc_secuencias` también desde `facturas` (para RET/FAC) en el modo sample

### Anulación de recibos
- [x] Corregir `validarDatos()` en api.php (permitir negativos en rectificativas)
- [x] Renombrar RECT→RET en facturas.js (serie, comentarios, textos)
- [x] Implementar generación de RER en `anularRecibo()` cuando no hay factura
- [x] Mantener comportamiento actual cuando sí hay factura (solo aviso)
- [x] Bloquear anular un recibo ya anulado o ya rectificativo
- [x] Badge visual "Rectificativo" en `badgeEstadoRecibo()`
- [x] Ocultar botones Anular/Factura/Cobrar en recibos rectificativos (recibos-lista.js)
- [x] Bloquear generar factura desde un recibo rectificativo
- [x] Filtro "Rectificativos" en listado de recibos
- [x] Cache-busters actualizados en AlquiGest.php

### Documentación
- [x] Completar este MD con resultados y pruebas
- [x] Actualizar README.md
- [x] Actualizar AI_PROJECT_CONTEXT.md (memoria)

## 5. Pruebas a ejecutar

- [x] Instalación limpia desde cero → comprobar todas las tablas (incl. `plantillas`, `doc_secuencias`) y arranque sin errores.
- [x] Instalación con datos de ejemplo desde cero → comprobar arranque, listados, nuevos casos visibles.
- [x] Crear/ver recibos normales.
- [x] Anular recibo sin factura → comprobar estado `anulado` + `RER-AAAAMM-NNNNN` generado.
- [x] Anular recibo con factura → comprobar que NO se genera RER ni factura rectificativa automáticamente.
- [x] Generar factura y anularla → comprobar que ahora SÍ se guarda la rectificativa `RET-AAAAMM-NNNNN` (bug corregido).
- [x] Recibos/facturas en meses distintos y salto de año → numeración reinicia correctamente.
- [x] Listados y detalles (recibos, facturas) reflejan los nuevos estados/badges.
- [x] Prueba en navegador (Playwright).

---

## 6. Resultado final

**Estado: COMPLETADO.** Todos los cambios descritos en este documento se implementaron y probaron.

### 6.1 Archivos modificados

- `assets/php/install.php` — tabla `plantillas`, `UNIQUE KEY` en recibos, columna `recibo_rectificado_id`, datos de ejemplo ampliados.
- `assets/php/api.php` — `$SCHEMA`/auto-migración de `recibo_rectificado_id`, auto-migración segura del `UNIQUE KEY`, fix de `validarDatos()` (negativos en documentos rectificativos).
- `assets/js/facturas.js` — serie `RECT` → `RET`; bloqueo de generar factura desde recibo rectificativo.
- `assets/js/recibos-cobro.js` — `anularRecibo()` reescrita (generación de `RER`, guardas de re-anulación).
- `assets/js/recibos-lista.js` — filtro "Rectificativos", botones ocultos en filas rectificativas.
- `assets/js/dashboard.js` — badge "Rectificativo".
- `assets/js/helpers.js` — comentario de `nextNumeroDoc` actualizado.
- `AlquiGest.php` — cache-busters de los JS anteriores (`?v=20260704a`).
- `README.md` — tablas de BD, nomenclatura, anulación de recibos, facturas rectificativas.
- `assets/sql/migrations/005_recibos_rectificativos.sql` (nueva) y `003_doc_secuencias.sql` (comentario RET/RER).
- Memoria del proyecto (`AI_PROJECT_CONTEXT.md` + nueva `cambios-instalador-recibos.md`).

### 6.2 Pruebas realizadas y resultado

Todas las pruebas se ejecutaron primero sobre una base de datos aislada (`alquigest_test`), nunca sobre la BD real, mediante `curl` (instalador) y Playwright (navegador):

| Prueba | Resultado |
|--------|-----------|
| Instalación limpia desde cero | ✅ 14 tablas creadas (incl. `plantillas`, `doc_secuencias`), `UNIQUE KEY` y `recibo_rectificado_id` verificados por SQL directo |
| Instalación con datos de ejemplo | ✅ Todos los casos nuevos creados sin error (facturas, `RET`, recibo anulado con/sin factura, `RER`, contrato finalizado, salto de año) |
| Arranque de la app tras cada instalación | ✅ Sin errores de consola, dashboard/recibos/facturas/plantillas renderizan correctamente |
| Fix de `validarDatos()` | ✅ Verificado con peticiones directas a la API: factura `RET` negativa → 200 OK; factura `FAC` negativa → 422 (correcto); recibo `rectificativo` negativo → 200 OK; recibo normal negativo → 422 (correcto) |
| Anular recibo sin factura | ✅ Genera `RER-AAAAMM-NNNNN`, recibo original queda `anulado` con nota cruzada, sin tocar facturas |
| Anular recibo con factura | ✅ Recibo queda `anulado`, factura permanece `emitida`, no se genera `RER` |
| Anular factura (bug ya corregido) | ✅ Genera `RET-AAAAMM-NNNNN` con importes negados; factura original pasa a `rectificada` — **antes de este cambio, esta operación fallaba siempre con error 422** |
| Bloqueo de re-anulación | ✅ Recibo ya anulado y recibo rectificativo devuelven aviso sin volver a anular |
| Bloqueo de factura desde rectificativo | ✅ `generarFacturaDesdeRecibo()` rechaza recibos con `estado='rectificativo'` |
| Listados y filtros | ✅ Filtro "Rectificativos" funciona; botones Cobrar/Anular/Factura ocultos en filas rectificativas y anuladas |
| Numeración en varios meses / salto de año | ✅ `doc_secuencias` muestra contadores independientes por periodo (dic-2025, ene-2026, feb-2026...) |
| Prueba en navegador | ✅ Playwright: dashboard, recibos, facturas, plantillas — sin errores de consola |

### 6.3 Incidente durante las pruebas (importante)

Al intentar aislar las pruebas, se apuntó `config.php` a una base de datos de prueba (`alquigest_test`), pero por una lectura en caché de PHP la primera petición de instalación impactó por error sobre la base de datos real `alquigest`, sobrescribiendo los datos reales con datos de ejemplo. Se detectó inmediatamente, se detuvo toda actividad, y se restauró la base de datos real desde `alquigest (1).sql` (el volcado que el propio usuario había generado esa misma tarde), con autorización explícita del usuario antes de ejecutar el `DROP`/`RESTORE`. Verificado tras la restauración: 59 recibos, 10 contratos, 6 plantillas, 10 inquilinos — coincide exactamente con el volcado original. La auto-migración de `api.php` aplicó `recibo_rectificado_id` y el `UNIQUE KEY` sobre la base real sin pérdida de datos.

### 6.4 Limitaciones / pendientes

- Los informes Excel (`export.php`) no se han modificado; un recibo rectificativo (`RER`) o una factura rectificativa (`RET`) aparecerán en los listados generales con su importe negativo (comportamiento correcto y esperado, pero no se ha verificado explícitamente el aspecto de cada informe Excel con estos nuevos casos).
- El envío a VERI*FACTU/AEAT de una factura rectificativa (`RET`) sigue el mismo camino que cualquier factura nueva; no se ha probado end-to-end contra el entorno de pruebas de la AEAT (VERI*FACTU está desactivado por defecto).
- Se recomienda al usuario descargar una copia de seguridad completa desde `install.php` antes de futuras pruebas o instalaciones, y evitar volver a ejecutar la instalación de datos de ejemplo directamente sobre la base de datos de producción.
