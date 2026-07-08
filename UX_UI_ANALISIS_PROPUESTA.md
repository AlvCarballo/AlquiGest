# UX/UI — Análisis y propuesta de rediseño (AlquiGest v3.0.0)

**Estado: ✅ IMPLEMENTADO en la aplicación real — v3.0.0 (08/07/2026).**
El usuario aprobó la propuesta y se implementaron las Fases 1-5 completas (Recibos, Facturas, Contratos, Propietarios, Fincas, Inquilinos, Plantillas + componente común + QA). Ver §12 al final de este documento para el resumen de lo implementado, lo pendiente y las pruebas realizadas.

Prototipo original (se conserva como referencia de diseño, sigue siendo una página autocontenida sin enlace desde el menú): [`ux_propuesta_redisenio.php`](ux_propuesta_redisenio.php).

---

## 1. Resumen ejecutivo

AlquiGest funciona correctamente, pero varias pantallas — sobre todo Recibos, Contratos, Facturas y Plantillas — muestran demasiadas acciones por fila, todas con el mismo peso visual (mismo tamaño, colores dispersos, iconos y texto mezclados). El resultado es que la tabla deja de leerse como un listado de consulta y se convierte en una pared de botones: cuesta encontrar la acción relevante, las acciones peligrosas (Anular, Eliminar) conviven al lado de las triviales (Editar, Email), y se muestran acciones que ni siquiera aplican al estado actual del registro.

El problema no es solo estético: es de jerarquía. No hay una única acción principal por fila, así que el usuario tiene que leer y descartar entre 4 y 10 controles en cada línea antes de decidir qué pulsar.

## 2. Diagnóstico general — patrones repetidos

Verificado leyendo el código real de cada módulo (`assets/js/*.js`) y navegando la aplicación en el navegador:

1. **Todas las acciones tienen el mismo peso visual.** Ningún módulo distingue una acción principal de las secundarias; todo son botones `btn-sm` del mismo tamaño.
2. **Acciones peligrosas junto a acciones normales.** "Anular" (rojo) y "Eliminar" (rojo) aparecen en la misma fila, a milímetros de "Editar" o "Email", sin ninguna separación.
3. **Se muestran acciones que no aplican al estado.** Mitigado parcialmente (algunos botones ya se ocultan condicionalmente, p. ej. "Cobrar" no aparece en un recibo `anulado`), pero el patrón no es consistente: en Contratos, por ejemplo, "PDF", "Fianza" y "DOCX" se muestran siempre, tenga o no sentido en ese momento.
4. **Demasiados colores sin criterio semántico único.** Verde, azul, rojo, morado y naranja conviven en la misma fila sin que el color comunique siempre lo mismo (ver §6 más abajo, matriz de estados).
5. **Filtros siempre expandidos.** Recibos, Facturas y Actividad muestran 5-6 controles de filtro permanentemente visibles, aunque la mayoría de sesiones solo usan la búsqueda de texto.
6. **Sin acción masiva.** No existe forma de seleccionar varios registros y actuar sobre ellos a la vez (enviar recordatorio a todos los pendientes, exportar varios PDF), lo que obliga a repetir la misma acción fila a fila.
7. **La tabla intenta ser tabla y panel de control a la vez.** No hay una vista de detalle separada; todo vive en la fila, lo que fuerza a comprimir cada vez más iconos en el mismo espacio horizontal.
8. **Inconsistencia de orden entre módulos.** Cada módulo ordena sus botones de forma distinta (a veces Editar antes que Eliminar, a veces PDF antes que Email), lo que obliga a re-aprender el layout en cada pantalla.
9. **Estados con colores duplicados dentro del mismo dominio.** Ejemplos concretos encontrados en el código:
   - `assets/js/dashboard.js` → `badgeEstadoRecibo()`: `parcial` y `rectificativo` usan el mismo `badge-blue`, pese a tener significados opuestos (cobro en curso vs. corrección administrativa).
   - El mismo mapa incluye el estado `devuelto` (`badge-orange`), que **no lo asigna ningún flujo real actual** (los recibos válidos son `pendiente/parcial/cobrado/anulado/rectificativo`, según README §14) — código muerto que puede inducir a error si se reutiliza en el futuro.
   - `assets/js/facturas.js` → `badgeEstadoFactura()` usa `badge-green` para `emitida`; en la misma fila, si VERI\*FACTU está activo, `badgeVF()` también puede mostrar `badge-green` para `enviado` — dos badges verdes con significado distinto compitiendo por la misma señal visual.

## 3. Análisis por pantalla

| ID | Pantalla | Problema | Impacto | Propuesta | Prioridad | Complejidad | Estado |
|----|----------|----------|---------|-----------|-----------|-------------|--------|
| P01 | Recibos | Hasta 7 controles por fila (Cobrar/Ver cobros, Email, WhatsApp, Imprimir, Editar, Anular, Factura), verificado en captura real | Alto — es el módulo más usado a diario | Acción principal según estado + menú "Más" agrupado | **Alta** | Media | ✅ Implementado |
| P02 | Contratos | Hasta 8-10 controles por fila (⚠IPC, Generar recibo, Renovar, Historial, Baja, PDF, Fianza, DOCX, Editar, Eliminar), confirmado en captura real — el peor caso de toda la app | Alto — fila más ancha que la pantalla en resoluciones medias | Acción principal ("Generar recibo") + Más; ⚠IPC se mantiene visible por ser una alerta temporal, no una acción rutinaria | **Alta** | Media-Alta | ✅ Implementado (incl. renombrado "DOCX" → "Contrato en DOCX", petición explícita del usuario) |
| P03 | Facturas | 4-5 iconos azules/rojos sin jerarquía (Imprimir, Email, Ver recibo origen, AEAT, XML, Anular) | Medio-Alto — factura es documento legal, la confusión aquí pesa más | Acción principal ("Imprimir/PDF") + Más; "Anular factura" con confirmación reforzada | **Alta** | Media | ✅ Implementado |
| P04 | Plantillas | 7 botones de texto por fila (Vista previa, DOCX, Renombrar, Duplicar, Desactivar, Por defecto, Eliminar), confirmado en captura real | Medio — módulo de uso puntual, pero muy saturado visualmente cuando se usa | Agrupar en "Usar" (Vista previa+DOCX) / "Gestionar" (Renombrar, Duplicar, Por defecto, Desactivar, Eliminar vía Más) | Media | Baja | ✅ Implementado |
| P05 | Contratos — formulario | Formulario largo con fiador, inquilinos secundarios, revisión de renta, etc. en un único modal | Medio | Dividir en secciones colapsables dentro del modal (datos, económico, revisión, personas adicionales) | Media | Media | ⏳ Pendiente (no incluido en esta ronda, ver §12) |
| P06 | Propietarios / Fincas / Inmuebles | Ya relativamente ligeras (2-4 acciones), pero sin panel de detalle — el "Historial"/navegación a hijos se hace vía botón de texto suelto | Bajo | Aplicar el mismo patrón de panel de detalle por consistencia, sin urgencia | Baja | Baja | ✅ Implementado en Propietarios y Fincas (acción principal + Más). Inmuebles se dejó sin cambios: ya cumplía la regla de máximo 2 acciones visibles (Editar + Eliminar) |
| P07 | Inquilinos | "Pagos" e "Historial" son dos entradas separadas para información relacionada; se suma Editar/Eliminar | Medio | Unificar "Pagos" + "Historial" dentro del panel de detalle (pestañas internas, ya existen en el modal actual) | Media | Baja | ✅ Implementado como acción principal ("Pagos") + Más ("Historial", Editar, Eliminar). No se construyó un panel lateral unificado nuevo — se reutilizó el modal de Historial existente (3 pestañas), que ya cumplía esa función |
| P08 | Dashboard | 9 widgets en una sola columna larga (backup, alerta IPC, KPIs, previsión de cobros, revisiones próximas, renovaciones, revisiones de renta, últimos recibos, gráficos, actividad) | Medio — sobrecarga por scroll más que por botones | Permitir orden/densidad configurable (ya existe activar/desactivar por widget en Parámetros; falta reordenar) | Baja | Media | Ya mitigado parcialmente |
| P09 | Generar Recibos | Flujo de varios pasos con confirmaciones; no se detectaron exceso de botones, pero sí falta de resumen previo claro antes de generar en lote | Bajo-Medio | Añadir resumen "Se van a generar N recibos por valor de X €" antes del botón final | Media | Baja | ⏳ Pendiente (no incluido en esta ronda, ver §12) |
| P10 | Informes | Ya agrupados en "Informes de gestión" / "Informes fiscales" (`infCard`), razonablemente ordenado | Bajo | Mantener agrupación; homogeneizar el selector de año/propietario en un único bloque de filtro plegable | Baja | Baja | Ya bien encaminado |
| P11 | Configuración / Parámetros | 7 pestañas, 77 parámetros — bien organizado por pestaña, con ayuda `?` contextual ya presente | Bajo | Mantener estructura; sin cambios urgentes | Baja | — | Sin cambios |
| P12 | VERI\*FACTU | Estados (No enviado/Pendiente/Enviado/Error) ya usan una paleta de 4 colores coherente y exclusiva (`badgeVF()`) | Bajo | Es el mejor ejemplo actual de badges bien diseñados — usarlo como referencia para el resto de módulos | Baja | — | ✅ Ajuste menor: `no_enviado` gris y `enviado` azul (antes azul y verde) para no competir con el verde de factura "Emitida" en la misma fila |
| P13 | Actividad | Tabla de solo lectura con filtros; no tiene botones de acción, por lo que no sufre el problema principal | Bajo | Aplicar el mismo patrón de filtro plegable si la lista de filtros crece | Baja | Baja | Sin cambios urgentes |

## 4. Propuesta global de diseño

Un único patrón repetido en todos los módulos con tabla:

1. **Fila de tabla:** identificación + 1-2 columnas de datos clave + badge de estado + **una acción principal** (botón con texto, coherente con el estado) + **"Más ▾"**.
2. **Menú "Más":** agrupado siempre en el mismo orden — **Comunicación** (Email, WhatsApp) → **Documentos** (PDF, Factura, DOCX) → **Gestión** (Editar, Historial, Anular/Eliminar). Lo destructivo va al final, en texto rojo, nunca como icono suelto en la fila.
3. **Panel de detalle:** al pulsar "Ver detalle" (o la propia acción principal cuando tiene sentido, p. ej. "Ver cobro"), se abre un panel lateral con resumen, histórico y las mismas acciones agrupadas — sin navegar a otra pantalla ni perder el contexto de la lista.
4. **Acciones masivas:** casilla de selección por fila + barra contextual superior cuando hay ≥1 fila marcada.
5. **Filtros:** buscador de texto siempre visible + resto de filtros plegados en un `<details>` (accesible por teclado de forma nativa, sin JS adicional).
6. **Estados:** paleta de 5 colores con significado exclusivo y no reutilizable dentro del mismo módulo (ver matriz en §6).
7. **Cabecera de módulo:** título + descripción breve + acción principal arriba a la derecha + filtros debajo, siguiendo el mismo patrón en todas las pantallas.

## 5. Reglas de diseño propuestas

- Máximo 2 elementos de acción visibles por fila: 1 acción principal + "Más ▾".
- La acción destructiva nunca es el botón principal visible en la fila; vive dentro de "Más", en rojo solo el texto, y siempre pide confirmación.
- No se muestran acciones que no aplican al estado del registro (p. ej. "Cobrar" no aparece en un recibo `anulado`).
- Todo icono sin texto lleva `title` (tooltip nativo).
- Los botones con texto se reservan para la acción principal de la fila; el resto son iconos o entradas de menú.
- Las acciones legales/fiscales (anular factura, enviar a AEAT) siempre piden confirmación explícita, tal como ya ocurre hoy — esto no cambia.
- Las tablas priorizan lectura: nunca más de 2 badges de estado por fila con el mismo color, y ningún color se reutiliza con significado distinto dentro del mismo módulo.
- Mismo patrón (fila → acción principal + Más → panel de detalle) en todos los módulos con tabla.

## 6. Matriz de acciones por estado

### Recibos

| Estado | Acción principal | Acciones en "Más" | Acciones ocultas/no permitidas |
|--------|------------------|--------------------|-------------------------------|
| Pendiente | Cobrar | Email · WhatsApp · PDF · Generar factura · Editar · Anular | — |
| Parcial | Completar cobro | Email · WhatsApp · PDF · Generar factura · Editar · Anular | — |
| Cobrado | Ver cobro | Email · WhatsApp · PDF · Generar factura · Editar · Anular | Cobrar (ya no aplica) |
| Anulado | Ver | PDF | Cobrar, Anular, Editar, Generar factura, Email, WhatsApp |
| Rectificativo | Ver | PDF | Cobrar, Anular, Editar, Generar factura, Email, WhatsApp (documento interno, ver README §15) |

### Facturas

| Estado | Acción principal | Acciones en "Más" | Acciones ocultas/no permitidas |
|--------|------------------|--------------------|-------------------------------|
| Emitida | Imprimir / PDF | Email · Ver recibo origen · Enviar a AEAT (si VERI\*FACTU activo) · Anular | — |
| Rectificada | Ver | PDF | Anular, Email, AEAT (ya no aplica sobre la original) |
| Anulada | Ver | PDF | Anular, AEAT |

## 7. Propuesta visual

Ver el prototipo interactivo: [`ux_propuesta_redisenio.php`](ux_propuesta_redisenio.php), con 8 pestañas de ejemplo:

1. **Antes / Después** — comparativa lado a lado de una fila de Recibos real (7 controles → 1 acción + Más), y los 5 estados de Recibos ya con el patrón propuesto.
2. **Estados por documento** — tabla de inconsistencias actuales (colores duplicados, estado muerto) y la paleta propuesta.
3. **Menú "Más"** — dropdown interactivo agrupado (Comunicación / Documentos / Gestión), con cierre al hacer clic fuera o pulsar Escape.
4. **Panel de detalle** — panel lateral deslizante con resumen, histórico de cobros, documentos asociados y acciones agrupadas.
5. **Acciones agrupadas** — las 3 categorías (Comunicación, Documentos, Gestión) aplicadas de forma consistente.
6. **Acciones masivas** — selección de filas con casillas + barra contextual "N seleccionados".
7. **Filtros plegables** — comparativa de los 6 controles siempre visibles hoy vs. buscador + `<details>` plegable.
8. **Reglas de diseño** — resumen de las reglas del §5 dentro de la propia pantalla, para que un no técnico entienda el criterio sin leer este documento.

Probado en navegador (ver §10 más abajo): las 8 pestañas cambian correctamente, el menú "Más" abre/cierra, el panel lateral se abre y cierra (botón, overlay y Escape), la selección múltiple actualiza el contador y muestra/oculta la barra, y el `<details>` de filtros expande con la flecha rotando. Sin errores de consola (aparte de un 404 inofensivo de `favicon.ico`).

## 8. Archivos creados/modificados

**Fase de propuesta (prototipo, sin tocar la app real):**
- `ux_propuesta_redisenio.php` (raíz) — prototipo autocontenido, se conserva como referencia de diseño.
- `UX_UI_ANALISIS_PROPUESTA.md` (este documento).

**Fase de implementación real (v3.0.0), tras la aprobación del usuario:**
- `assets/js/tabla.js` — componente común nuevo: `accionesFila()`, menú "Más" (`toggleMenuMas`/`cerrarMenusMas`, con flip-up automático si no cabe debajo), panel lateral genérico (`abrirPanelDetalle`/`cerrarPanelDetalle`), barra de acciones masivas genérica (`actualizarBarraMasiva`, `toggleTodasFilas`, `limpiarSeleccionMasiva`).
- `assets/css/main.css` — estilos nuevos: `.row-actions`, `.dd-more*`, `.panel-lateral*`, `.bulk-bar`, `details.filtros-plegables`, badges `.badge-purple`/`.badge-gray` (+ variantes modo oscuro).
- `assets/js/recibos-lista.js` — Fase 1: `_accionesRecibo()`, `abrirDetalleRecibo()`, `accionMasivaPDFRecibos()`, filtros en `<details>`, columna de selección + barra masiva.
- `assets/js/facturas.js` — Fase 2: `_accionesFactura()`; `badgeEstadoFactura()` con `rectificada` en gris.
- `assets/js/contratos.js` — Fase 2: `_accionesContrato()`; ⚠IPC/IRAV se mantiene siempre visible fuera del menú; "DOCX" renombrado a "Contrato en DOCX".
- `assets/js/propietarios.js`, `assets/js/fincas.js`, `assets/js/inquilinos.js` — Fase 3: filas reescritas con `accionesFila()`.
- `assets/js/plantillas.js` — Fase 4: filas reescritas agrupando "Usar" / "Gestionar".
- `assets/js/dashboard.js` — `badgeEstadoRecibo()`: `parcial` → morado, `anulado` → gris (antes rojo).
- `assets/js/verifactu.js` — `badgeVF()`: `no_enviado` → gris, `enviado` → azul.
- `assets/js/configuracion.js` — textos de ayuda de `VisiDocxCont` actualizados para reflejar el nuevo nombre "Contrato en DOCX".
- `AlquiGest.php` — cache-busters (`?v=`) actualizados en todos los ficheros JS/CSS tocados.
- `README.md` — versión 3.0.0 y descripciones de acciones por fila actualizadas en las secciones 12, 13, 14 y 15.

**No se ha tocado:** `api.php`, ningún otro backend PHP, el esquema de base de datos, ni la lógica de negocio de ninguna acción (cobrar, anular, generar factura, generar DOCX, enviar email/WhatsApp) — todos los `onclick` siguen llamando exactamente a las mismas funciones que antes, solo cambia dónde y cómo se muestran.

## 9. Riesgos de implementar el rediseño completo (y cómo se resolvieron)

- **Botones ocultos tras "Más" que hoy tienen atajos de un clic** — mitigado dejando la acción más frecuente de cada estado como principal visible (ver matriz §6); el resto queda a un clic extra dentro de "Más".
- **Config de visibilidad por botón (`Parámetros → Botones`)** — cada flag `Visi*` existente (`VisiCobrarReci`, `VisiAnularFact`, `VisiDocxCont`, etc.) se respetó y se mapeó a su nueva ubicación (principal o entrada de "Más"); ninguno se eliminó.
- **Cache-busters** — actualizados en `AlquiGest.php` para los 12 ficheros JS/CSS tocados.
- **Menú "Más" recortado por el `overflow-x:auto` de `.table-wrap`** — riesgo real detectado durante las pruebas (el menú usaba `position:absolute` dentro de la celda). Corregido calculando la posición con `position:fixed` en JavaScript, incluyendo un "flip-up" automático si no cabe por debajo del botón (detectado con un viewport de prueba inusualmente bajo, 552px de alto).
- **Cambio de comportamiento real, no solo visual, en Recibos/Facturas "cerrados"** — antes, un recibo `anulado`/`rectificativo` (o una factura `rectificada`/`anulada`) seguía mostrando Email/WhatsApp/Editar sin mirar el estado. Con la matriz del §6 esas acciones dejan de mostrarse en esos estados. Es un cambio deliberado, documentado aquí y en el README (secciones 14 y 15), no un efecto colateral oculto.
- **Móvil/pantallas estrechas** — no se ha probado específicamente en anchos de móvil (fuera del alcance de esta ronda); la app sigue optimizada para escritorio (README §21).
- **Nada de esto afectó a lógica de negocio, cálculos, BD ni backend** — confirmado: `api.php` no se tocó, y cada acción sigue llamando exactamente a la misma función JS que antes (`anularRecibo()`, `generarFacturaDesdeRecibo()`, `anularFactura()`, etc.), verificado con datos reales en la copia de pruebas aislada.

## 10. Plan de implementación

**Fase 1** — Recibos ✅ Completada
- Componente común de acciones de tabla (acción principal + menú "Más") en `tabla.js`.
- Aplicado a `recibos-lista.js`, respetando la matriz de estados del §6 y los flags `Visi*` existentes.
- Panel de detalle para Recibos (`abrirDetalleRecibo()`), con historial de cobros y acciones agrupadas.
- Extra sobre el plan original: selección múltiple + descarga de PDF en lote (`accionMasivaPDFRecibos()`), y filtros plegables en `<details>`.

**Fase 2** — Facturas y Contratos ✅ Completada
- `facturas.js`: acción principal + Más, todas las confirmaciones existentes intactas (anular factura, envío AEAT).
- `contratos.js`: acción principal ("Generar recibo" / "Ver PDF") + Más; `⚠ IPC/IRAV` se mantiene siempre visible fuera del menú (confirmado explícitamente por el usuario durante la implementación). "DOCX" renombrado a "Contrato en DOCX" a petición del usuario.

**Fase 3** — Propietarios, Fincas, Inmuebles, Inquilinos ✅ Completada
- Propietarios, Fincas e Inquilinos migrados al mismo patrón. Inmuebles se dejó tal cual (ya cumplía la regla de máximo 2 acciones).

**Fase 4** — Plantillas ✅ Completada · Dashboard/Parámetros/Informes ⏳ Sin cambios (no lo necesitaban, ver tabla §3)
- Plantillas: agrupadas en "Usar" (Vista previa, Descargar original) y "Gestionar" (Renombrar, Duplicar, Activar/Desactivar, Por defecto, Eliminar).
- Dashboard, Parámetros e Informes: no se tocaron en esta ronda — el diagnóstico original ya los consideraba de baja prioridad/bien encaminados (P08, P10, P11).

**Fase 5** — QA completo ✅ Completada
- Probado en copia aislada del proyecto con BD de prueba propia (`alquigest_test_ux_20260708`), nunca sobre `config.php` ni la BD real, siguiendo el mismo protocolo que otras sesiones de este proyecto.
- Verificado con datos reales: cobrar, anular (con y sin factura, con y sin cobro previo), generar factura, anular factura, editar, eliminar, vista previa de plantilla — todo funciona igual que antes, solo cambia la disposición visual.
- Verificado el menú "Más" (apertura/cierre, clic fuera, Escape, flip-up si no cabe debajo), el panel lateral de Recibos, la barra de acciones masivas (selección, contador, descarga de PDF en lote) y los filtros plegables.
- Verificado que la aplicación real (`http://localhost/AlquiGest_v2/`) carga sin errores de consola tras el despliegue, con datos de producción.

## 11. Criterios de aceptación

- ✅ Ninguna fila de tabla muestra más de 2 controles de acción visibles (excepto el aviso ⚠IPC/IRAV en Contratos, que es una alerta, no una acción).
- ✅ La acción principal mostrada es siempre coherente con el estado del registro (validado contra la matriz del §6 para Recibos/Facturas, y su equivalente para el resto).
- ✅ Ninguna acción destructiva aparece como botón suelto fuera del menú "Más".
- ✅ Todos los flags `Visi*` de `Parámetros → Botones` siguen funcionando.
- ✅ El panel de detalle de Recibos muestra al menos los mismos datos que el modal que complementa.
- ✅ Probado en navegador con datos reales, sin romper cobros, anulaciones, generación de factura, envío de email/WhatsApp ni generación de PDF/DOCX existentes.
- ✅ No quedan errores de consola nuevos tras el cambio (verificado en cada módulo y en la app real).
- ⏳ **Pendiente:** actualizar `assets/docs/ayuda.php` (manual de usuario) y las capturas de `assets/img/` para reflejar el nuevo layout — no se ha tocado en esta ronda; el README ya está actualizado (secciones 12-15).

## 12. Resumen de la implementación (08/07/2026)

El usuario aprobó la propuesta ("Me gusta lo que veo, implementalo todo") y se ejecutaron las Fases 1 a 5 completas en la misma sesión, sobre la aplicación real (versión bumped a 3.0.0 por el usuario en `config.php`).

**Implementado:** P01, P02, P03, P04, P06 (parcial, ver nota), P07 (parcial, ver nota), P12 (ajuste de color).

**No implementado en esta ronda (backlog, sin urgencia según el diagnóstico original):**
- P05 — Formulario de Contratos en secciones colapsables.
- P08 — Reordenación de widgets del Dashboard (drag-and-drop).
- P09 — Resumen previo en Generar Recibos antes de confirmar el lote.
- Actualizar `assets/docs/ayuda.php` y las capturas de `assets/img/` con el nuevo layout.

**Notas de alcance importantes:**
- El componente de acciones masivas se limitó a **descarga de PDF en lote** en Recibos. Se descartó deliberadamente el envío de email en lote: `enviarReciboEmail()` abre un modal de confirmación por recibo (no es una llamada async encadenable sin intervención), y automatizar el envío real de correos en bucle sin ese control por unidad se consideró un riesgo innecesario para una función que toca a inquilinos reales.
- En Recibos y Facturas, los estados "cerrados" (`anulado`, `rectificativo`, `rectificada`) dejaron de mostrar Email/WhatsApp/Editar/Anular/Generar factura — antes se mostraban siempre, sin mirar el estado. Es un cambio de comportamiento real (no solo visual), documentado en el README (secciones 14 y 15) y aquí (§9).
- Todas las pruebas se hicieron contra una copia aislada del proyecto con base de datos de prueba propia, nunca contra `config.php` ni la base de datos real, y se verificó al final que la aplicación real carga sin errores con datos de producción.

---

## Diagnóstico verificado en navegador (antes del prototipo)

Capturas tomadas navegando la aplicación real (`http://localhost/AlquiGest_v2/AlquiGest.php`, datos reales, sin modificar ningún registro):

- **Contratos:** fila con hasta 8 controles simultáneos (⚠ IPC, Generar recibo, Renovar, Historial, Baja, PDF, Fianza, DOCX, Editar, Eliminar según el contrato) — el peor caso real de toda la aplicación.
- **Recibos:** fila con hasta 7 iconos/botones (Cobrar o Ver cobros, Email, WhatsApp, Imprimir, Editar, Anular, Factura), colores verde/azul/rojo/morado conviviendo sin jerarquía.
- **Facturas:** hasta 5 iconos azules/rojos sin acción principal diferenciada.
- **Plantillas:** 7 botones de texto por fila (Vista previa, DOCX, Renombrar, Duplicar, Desactivar, Por defecto, Eliminar), todos con el mismo estilo `btn-secondary` salvo Eliminar.

Estas capturas son la base empírica del diagnóstico del §2 y §3 — no son una suposición, son el estado real de la aplicación en el momento de este análisis (julio 2026).

---

*Documento de propuesta y registro de implementación — v3.0.0 (08/07/2026). Prototipo de referencia: `ux_propuesta_redisenio.php`.*
