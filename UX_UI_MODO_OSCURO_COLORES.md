# UX/UI — Modo oscuro, colores, contraste y accesibilidad (AlquiGest v3.0.0)

**Estado: ✅ implementado en la aplicación real (09/07/2026), Fases 1-6 completas.**
Prototipo navegable (histórico, ya no refleja necesariamente el estado final): [`ux_modo_oscuro_colores.php`](ux_modo_oscuro_colores.php) + [`assets/css/ux-theme-proposal.css`](assets/css/ux-theme-proposal.css). Los cambios reales se aplicaron a `assets/css/main.css` y a los ficheros JS listados en la §12.

---

## 0. Resumen de la implementación (09/07/2026)

Las 6 fases del plan (§8) se implementaron y verificaron en una copia aislada de la aplicación (BD y directorio de pruebas independientes, sin tocar datos reales) y después se comprobó que la aplicación real carga sin errores de consola. Detalle completo en §12.

**Modificación pedida por el usuario respecto a la propuesta original:** la fila de recibo `Pendiente` (`.tr-pendiente`) **mantiene su texto en color** — no se dejó neutro como sugería originalmente §3/§6 de este documento. En vez de reutilizar `var(--red)` tal cual (lo que habría duplicado el color de "anulado/error"), se mantuvo `.tr-pendiente { color: var(--red); }` como pidió el usuario explícitamente ("quiero que sigan estando en otro color pero aplicándoles el color danger de la paleta que has propuesto"), sustituyendo únicamente el hex hardcodeado (`#c81e1e`/`#f87171`) por la variable `--red` ya existente. Es decir: se resolvió el problema de *arquitectura* (hex suelto → variable), pero se conservó a propósito la señal roja en la fila pendiente, contradiciendo la recomendación original de §6 de dejarlo neutro.

Durante el QA (Fase 6) se descubrieron dos bugs preexistentes, no causados por esta implementación y **no corregidos** (fuera de alcance de esta revisión de color/tema):
- **`plantillas.js`** — la función de "Vista previa" de plantillas busca `document.querySelector('.modal-body')`, pero el elemento real tiene la clase `ag-modal-body` (el `id`, no la clase, es `modal-body`). El selector nunca coincide, así que el modal se queda bloqueado en "Cargando previsualización…" indefinidamente. Confirmado que el backend (`plantillas.php`) responde 200 OK con JSON válido — el fallo es puramente de front-end.
- **`assets/php/plantillas.php`** — genera HTML con colores de resaltado hardcodeados en el propio PHP (`<mark style="background:#d1fae5">` para variables resueltas, `<mark style="background:#fee2e2;color:#991b1b">` para no resueltas). Nunca se auditó porque el rastreo original de colores (§2) solo cubrió CSS/JS, no PHP.

## 1. Resumen ejecutivo

El sistema de color de AlquiGest ya tiene una base sólida: variables CSS centralizadas en `:root`, una paleta clara/oscura razonable (`assets/css/main.css`), y overrides de modo oscuro para la mayoría de componentes (`body.dark ...`). El problema principal **no es la arquitectura CSS — es que un porcentaje grande del color real que ve el usuario no vive en esa arquitectura**: 246 colores hexadecimales están escritos directamente en JavaScript (`style="color:#..."`, `style="background:#..."`), fuera del alcance de las variables `--*` y, por tanto, invisibles para el modo oscuro. Cuando esos fragmentos aparecen dentro de un modal o una tarjeta que sí cambia a fondo oscuro, el resultado es un "parche claro" incrustado en una pantalla oscura — el defecto más visible y más fácil de reproducir de toda la aplicación.

Además, el README afirma que "los gráficos del Dashboard (Chart.js) adaptan sus colores automáticamente al tema activo". Es **parcialmente cierto**: los gráficos leen el tema correcto la primera vez que se dibujan, pero **no se redibujan si el usuario cambia de tema mientras ya está en el Dashboard** — hay que navegar a otra pantalla y volver para que se actualicen. Verificado en código y en navegador (ver §10).

Por último, hay dos problemas de accesibilidad reales y verificables independientes del color: los badges de estado `Pendiente` y `Anulado`/`Rectificada` (fondo claro) no alcanzan el contraste mínimo WCAG AA para texto normal, y **todo el menú lateral de navegación es inaccesible por teclado** (son `<div onclick>`, no botones ni enlaces, sin `tabindex`).

## 2. Diagnóstico general — patrones encontrados

Verificado leyendo `assets/css/main.css` completo (1519 líneas) y haciendo `grep` de colores hexadecimales en los 21 ficheros de `assets/js/*.js` reales (se excluyen `vendor/*.min.js`):

1. **246 colores hexadecimales hardcodeados en JavaScript**, repartidos en 20 ficheros. La mayoría son parte de HTML generado dinámicamente (`style="color:#...;background:#..."`) que nunca pasa por las variables `--*` de `main.css` y por tanto no reacciona al modo oscuro. `facturas.js` (55), `extras.js` (42), `recibos.js` (21, fichero muerto — no se carga, ver nota en `AI_PROJECT_CONTEXT`), `actividad.js` (19) y `plantillas.js` (18) concentran más de la mitad.
2. **Bloque `.tr-{estado}` completamente duplicado y semánticamente inconsistente** (`main.css` líneas 619-624 en claro, 205-210 en oscuro): son las 6 reglas que colorean el texto de una fila de recibo según su estado. Usan valores hex sueltos (`#c81e1e`, `#1a56db`, `#c27803`, `#9ca3af`...) en vez de `var(--red)`, `var(--blue)`, etc. — y **el recibo `Pendiente` se pinta en rojo**, el mismo color reservado para peligro/error, mientras que su badge de estado (`badgeEstadoRecibo()`) lo pinta en naranja. Dos señales de color distintas para el mismo estado en la misma fila.
3. **"Parches claros" dentro de modales en modo oscuro**: los mensajes de estado (cargando / éxito / error) que aparecen dentro de los modales de "Enviar por email" (`email.js`, `facturas.js`) usan combinaciones fijas como `color:#1e40af;background:#dbeafe` (azul información), `color:#15803d;background:#dcfce7` (verde éxito) y `color:#991b1b;background:#fee2e2` (rojo error). Estas cajas mantienen su fondo claro aunque el modal que las contiene ya esté en modo oscuro — el defecto más visible y fácil de reproducir de toda la revisión (ver captura en §10).
4. **El aviso ⚠ IPC/IRAV de Contratos no tiene versión oscura en absoluto**: el filtro "⚠ Revisión pendiente", el badge de renta con el icono de revisión y el modal "Aplicar subida IPC" completo usan tonos ámbar hardcodeados (`#fef3c7`, `#fbbf24`, `#92400e`, `#f59e0b`, `#78350f`) sin ningún `body.dark` equivalente — en modo oscuro se ven exactamente igual que en modo claro, como una "ventana" clara en medio de la pantalla oscura.
5. **Gráficos del Dashboard: adaptación parcial, no reactiva.** `initDashboardCharts()` sí lee `document.body.classList.contains('dark')` para elegir la paleta correcta — pero solo en el momento de crear el gráfico. `toggleModoOscuro()` (en `ux.js`) solo cambia la clase `dark` del `<body>` y el icono sol/luna; nunca destruye ni redibuja los `Chart` existentes. Si el usuario cambia de tema estando en el Dashboard, el gráfico de barras y el de dona se quedan con los colores del tema anterior hasta que se navega a otra pantalla y se vuelve.
6. **Colores de estado sin token compartido.** El sistema de badges tiene variables de marca para verde/rojo/azul/naranja (`--green`, `--red`, `--blue`, `--orange` + su versión `-light`), pero `.badge-purple` y `.badge-gray` (añadidos en la revisión UX anterior) usan hex sueltos no ligados a ninguna variable — si se decide ajustar la paleta morada o gris en el futuro, hay que buscar y cambiar el valor a mano en vez de tocar una variable.
7. **Proliferación de "naranjas" distintos.** Contando variables y valores sueltos: `--orange` (`#c27803`/`#fbbf24` oscuro), `--color-warn-muted` (`#fcd34d`/`#4d3300`), y además `#9a3412`/`#fb923c` en `.renov-plazo-agotado`, y `#92400e`/`#78350f` en los avisos de Contratos — al menos 4 tonos de "naranja/ámbar" distintos sin relación declarada entre ellos.
8. **Colores de foco débiles.** `input:focus`/`select:focus`/`textarea:focus` quitan el `outline` nativo del navegador y lo sustituyen por un `box-shadow` con solo 12-15% de opacidad (`rgba(26,86,219,.12)` a `.15`) — un anillo de foco muy tenue, casi invisible con brillo de pantalla alto o para usuarios con baja visión. Los botones (`.btn:focus-visible`) sí tienen un anillo sólido de 2px — la inconsistencia es solo en los campos de formulario.
9. **Navegación lateral inaccesible por teclado.** Cada opción del menú (`#nav-propietarios`, `#nav-recibos`, etc., en `AlquiGest.php`) es un `<div class="nav-item" onclick="navigate(...)">` — sin `tabindex`, sin `role="button"`, sin manejador de teclado. No forma parte del orden de tabulación: un usuario que solo usa teclado no puede llegar a ningún módulo de la aplicación (excepto "Inicio", que sí es un `<a href>` real).
10. **Tooltips nativos, no un problema de tema.** El punto "tooltips que no cambian de tema" de la lista de comprobación no aplica como defecto de la app: los tooltips visibles hoy son el atributo `title="..."` del HTML, renderizados enteramente por el sistema operativo/navegador — no se pueden restylear con CSS y no están rotos, es una limitación de la plataforma, no del código. El único tooltip "propio" de la app es el menú "Más" (`dd-more-panel`), que sí respeta el tema correctamente (comprobado en el prototipo anterior).
11. **Buena práctica ya presente, digna de mantener:** `body.dark` fija `color-scheme: dark` — esto hace que controles nativos del navegador (flecha de `<select>`, casillas, selector de fecha, barra de scroll) se rendericen en oscuro automáticamente sin CSS adicional. Y `.tr-anulado` combina color gris **con tachado** (`text-decoration:line-through`), cumpliendo la regla de "no depender solo del color" — es el único estado de fila que ya lo hace bien; el resto (pendiente, parcial, devuelto) dependen únicamente del color del texto.

## 3. Inventario de colores actuales

| Color actual | Archivo/Zona | Uso | Problema | Sustituto propuesto |
|---|---|---|---|---|
| `#c81e1e` / `#f87171` | `main.css` `.tr-pendiente` (claro/oscuro) | Texto de fila "Pendiente" | Duplica `var(--red)`; semánticamente incorrecto (pendiente no es un error) | Quitar la regla; usar solo el badge de estado, o `var(--text-primary)` + badge |
| `#1a56db` / `#60a5fa` | `main.css` `.tr-parcial` | Texto de fila "Parcial" | Duplica `var(--blue)`; no coincide con el badge morado de "Parcial" | `var(--text-primary)` (dejar que el badge comunique el estado) |
| `#c27803` / `#fbbf24` | `main.css` `.tr-devuelto` | Texto de fila para estado `devuelto` | Estado sin flujo real que lo asigne (código muerto, ver `UX_UI_ANALISIS_PROPUESTA.md`) | Eliminar la regla junto con el estado muerto |
| `#9ca3af` / `#6b7280` | `main.css` `.tr-anulado` | Texto de fila "Anulado" | Duplica `var(--gray-400)`/`var(--gray-500)`, aunque el patrón (color+tachado) es correcto | `var(--text-muted)` |
| `#e8f0fe` / `#1e3a5f` | `main.css` `.tr-selected` | Fondo de fila seleccionada | Duplica `var(--blue-light)` | `var(--blue-light)` |
| `#ede9fe` / `#3b0764` | `main.css` `.badge-purple` | Badge "Parcial" | Sin variable de marca `--purple`; morado no definido en `:root` | Añadir `--purple`/`--purple-light` a `:root` y usarlas aquí |
| `#fff7ed` / `#fed7aa` | `main.css` `.aviso-revision-header` (base claro) | Cabecera de aviso IPC en Dashboard | Duplica `var(--orange-light)`; el override oscuro sí usa variables, el base no | `var(--orange-light)` / borde con variable equivalente |
| `linear-gradient(135deg,#f0f9ff,#e0f2fe)` / `#0369a1` / `#bae6fd` | `main.css` `.revisiones-anuales-header` | Cabecera "Revisiones anuales" | Totalmente hardcodeado en claro (el oscuro sí usa `var(--color-info-*)`) | `var(--color-info-light)` (sólido, sin degradado, o degradado entre 2 variables) |
| `#9a3412` / `#fb923c` | `main.css` `.renov-plazo-agotado` | Texto "plazo agotado" en renovaciones | Tercer tono de naranja sin relación con `--orange` | Reutilizar `--orange` (claro) / `--orange` oscuro, o crear `--orange-strong` explícito si se quiere distinguir a propósito |
| `#fff5f5` / `#3d0a18` | `main.css` `.rev-urgente td` | Fondo de fila urgente | Hardcodeado sin variable; el tono oscuro no es el mismo matiz que `--red-light` oscuro | `var(--red-light)` |
| `color:#1e40af;background:#dbeafe` | `email.js`, `facturas.js` (mensajes "Enviando…") | Caja de estado dentro de modal | No cambia con el tema — "parche claro" en modal oscuro | `var(--color-info)` / `var(--color-info-light)` |
| `color:#15803d;background:#dcfce7` | `email.js`, `facturas.js` (mensajes de éxito) | Caja de estado dentro de modal | Igual que el anterior | `var(--green)` / `var(--green-light)` |
| `color:#991b1b;background:#fee2e2` | `email.js`, `facturas.js` (mensajes de error) | Caja de estado dentro de modal | Igual que el anterior | `var(--red)` / `var(--red-light)` |
| `#fef3c7` / `#fbbf24` / `#92400e` / `#f59e0b` / `#78350f` | `contratos.js` (filtro ⚠IPC, badge de renta, modal "Aplicar IPC") | Aviso de revisión de renta pendiente | Sin ninguna adaptación a modo oscuro (0 apariciones en `body.dark`) | `var(--orange)` / `var(--orange-light)` en todos los casos |
| `background:#ef4444` (inline, `style.cssText`) | `facturas.js` (badge numérico sobre "VERI·FACTU" en el menú) | Contador de facturas pendientes de AEAT | Hardcodeado; funciona pero no sigue el sistema de variables | `var(--red)` |
| Colores de `buildFacturaHTML()`/`buildReciboHTML()`/`_facListadoHtml()` (decenas de hex en `facturas.js`, `recibos-pdf.js`, `contratos-pdf.js`) | Generación de HTML para PDF/impresión | Documento imprimible | **No es un problema** — es la misma decisión ya documentada en `main.css` ("Colores hardcoded a propósito: siempre papel blanco"); un recibo o factura debe imprimirse igual sin importar el tema activo de la app | Sin cambios — mantener hardcodeado a propósito |

## 4. Análisis por pantalla

| ID | Pantalla | Problema visual | Modo afectado | Impacto | Propuesta | Prioridad | Estado |
|---|---|---|---|---|---|---|---|
| C01 | Recibos (tabla) | Texto de fila `tr-pendiente` en rojo, contradice el badge naranja del mismo estado | Ambos | Alto — módulo de uso diario | Quitar color de texto por fila; dejar que el badge sea la única señal de color | **Alta** | Propuesto |
| C02 | Recibos / Facturas — modales de email | Caja de estado (cargando/éxito/error) con fondo claro fijo dentro de un modal oscuro | Oscuro | Alto — muy visible, ocurre en una acción frecuente (enviar por email) | Sustituir por `var(--color-info-light)`/`var(--green-light)`/`var(--red-light)` | **Alta** | Propuesto |
| C03 | Contratos — aviso ⚠IPC/IRAV | Filtro, badge de renta y modal "Aplicar subida IPC" sin ninguna versión oscura | Oscuro | Alto — el módulo con más botones de la app, aviso usado cada revisión anual | Reemplazar los 5 hex sueltos por `var(--orange)`/`var(--orange-light)` y añadir overrides `body.dark` donde falten | **Alta** | Propuesto |
| C04 | Dashboard — gráficos | Los gráficos no se redibujan al cambiar de tema sin navegar fuera y volver | Ambos (se nota al cambiar) | Medio-Alto — contradice la afirmación del README | Enganchar `toggleModoOscuro()` a un redibujado de los `Chart` activos (destruir y recrear, o `chart.update()` con las nuevas opciones de color) | **Alta** | Propuesto |
| C05 | Badges de estado (`Pendiente`, `Anulado`/`Rectificada`/`No enviado`) | Contraste texto/fondo por debajo de 4.5:1 en modo claro (ver cálculo §5) | Claro | Medio — legibilidad, especialmente en pantallas con brillo bajo | Oscurecer el texto del badge naranja y gris en modo claro (ver paleta §5) | Alta | Propuesto |
| C06 | Menú lateral (todas las pantallas) | `<div onclick>` sin `tabindex`/`role` — inalcanzable por teclado | Ambos | Alto — bloquea el uso completo por teclado de la aplicación | Añadir `tabindex="0" role="button"` + manejador de `Enter`/`Espacio`, o convertir a `<button>` | **Alta** | Propuesto |
| C07 | Formularios (todos los módulos) | Anillo de foco muy tenue (12-15% opacidad) en inputs/selects/textarea | Ambos | Medio — accesibilidad de teclado | Subir la opacidad del `box-shadow` de foco (p. ej. 35-40%) o añadir un `outline` sutil adicional | Media | Propuesto |
| C08 | Fincas / Inmuebles / Propietarios / Inquilinos | Sin problemas de color específicos detectados más allá de los genéricos de badges/foco | Ambos | Bajo | Se benefician automáticamente de los cambios globales (§8 Fase 1-2) | Baja | Sin cambios específicos |
| C09 | Generar Recibos | Sin colores hardcodeados relevantes detectados | Ambos | Bajo | Sin cambios | Baja | Sin cambios |
| C10 | Informes | Tablas de exportación (Excel) no aplican — son ficheros `.xlsx`, no HTML | — | — | Sin cambios | — | No aplica |
| C11 | Configuración / Parámetros | Sin colores hardcodeados relevantes; ya usa variables | Ambos | Bajo | Sin cambios | Baja | Sin cambios |
| C12 | Plantillas | 18 hex encontrados; la mayoría son iconos de estado del análisis de plantilla (⚠/✓), mismo patrón ámbar/verde que Contratos | Oscuro | Medio | Aplicar el mismo fix de variables que C03 | Media | Propuesto |
| C13 | VERI\*FACTU | Ya es el mejor ejemplo de badges coherentes (`badgeVF()`, ver revisión UX anterior); el badge numérico del menú (`#ef4444` inline) es el único resto hardcodeado | Ambos | Baja | Sustituir ese único valor por `var(--red)` | Baja | Propuesto |
| C14 | Actividad | 19 hex encontrados — a revisar en Fase 2, probablemente iconos/colores por tipo de acción del log | Ambos | Media | Auditar y mapear a variables de estado existentes | Media | Propuesto |
| C15 | Mi Empresa | Sin colores hardcodeados relevantes detectados | Ambos | Baja | Sin cambios | Baja | Sin cambios |
| C16 | Modales (genérico) | El contenedor (`.ag-modal`) ya respeta el tema correctamente; el problema es solo el contenido inyectado por JS (ver C02) | Oscuro | Alto (por repetición en muchos flujos) | Mismo fix que C02, aplicado a todos los modales con mensajes de estado | Alta | Propuesto |
| C17 | Menús desplegables ("Más ▾") | Correctos — ya construidos con variables en la revisión UX anterior, sin hallazgos nuevos | Ambos | — | Sin cambios | — | Sin cambios |
| C18 | Calendario de Cobros | `.cal-recibo-chip` usa `var(--red-light)`/`var(--green-light)` correctamente; sin hallazgos | Ambos | Baja | Sin cambios | Baja | Sin cambios |

## 5. Propuesta de paleta final

Se propone **mantener los nombres de variable ya existentes** en `main.css` (`--bg-app`, `--bg-surface`, `--text-primary`, etc.) en vez de renombrarlos a los sugeridos en el encargo (`--color-bg`, `--color-text`...): ya están implantados en cientos de líneas y funcionan correctamente; renombrarlos no aporta nada visual y solo añade riesgo. La tabla siguiente traduce la nomenclatura pedida a la ya existente, y solo cambia **valores** donde hay un problema real de contraste.

### Modo claro

| Token pedido | Variable real equivalente | Valor actual | Valor propuesto | Cambia? |
|---|---|---|---|---|
| fondo app | `--bg-app` | `#f3f4f6` | `#f3f4f6` | No |
| superficie/card | `--bg-surface` | `#ffffff` | `#ffffff` | No |
| superficie elevada | `--bg-subtle` | `#f9fafb` | `#f9fafb` | No |
| texto principal | `--text-primary` | `#111827` | `#111827` | No |
| texto secundario | `--text-secondary` | `#4b5563` | `#4b5563` | No |
| borde | `--border-default` | `#e5e7eb` | `#e5e7eb` | No |
| primario | `--blue` | `#1a56db` | `#1a56db` | No |
| éxito | `--green` | `#057a55` | `#057a55` | No |
| advertencia (texto sobre `--orange-light`) | `--orange` | `#c27803` | **`#92400e`** | **Sí** — pasa de 3.12:1 a 6.30:1 sobre `--orange-light` |
| peligro | `--red` | `#c81e1e` | `#c81e1e` | No |
| info | `--color-info` | `#0284c7` | `#0284c7` | No |
| gris de badge (texto sobre `--gray-100`) | `--gray-500` en badges | `#6b7280` | **`#4b5563`** (= `--gray-600`) | **Sí** — pasa de 4.18:1 a 6.74:1 |
| morado (nuevo, para no dejarlo huérfano) | `--purple` / `--purple-light` (nuevas) | `#6d28d9` / `#ede9fe` (hardcoded) | Mismos valores, pero como variables | Solo estructura |

### Modo oscuro

| Token pedido | Variable real equivalente | Valor actual | Valor propuesto | Cambia? |
|---|---|---|---|---|
| fondo app | `--bg-app` | `#0f172a` | `#0f172a` | No — ya evita el negro puro |
| superficie/card | `--bg-surface` | `#1e293b` | `#1e293b` | No |
| superficie elevada | `--bg-muted` | `#263348` | `#263348` | No |
| texto principal | `--text-primary` | `#f1f5f9` | `#f1f5f9` | No — ya evita el blanco puro |
| texto secundario | `--text-secondary` | `#94a3b8` | `#94a3b8` | No |
| borde | `--border-default` | `#334155` | `#334155` | No |
| primario | `--blue` | `#4d8ffa` | `#4d8ffa` | No |
| éxito | `--green` | `#22c55e` | `#22c55e` | No |
| advertencia | `--orange` | `#fbbf24` | `#fbbf24` | No — ya pasa AA (8.87:1) |
| peligro | `--red` | `#f87171` | `#f87171` | No |
| info | `--color-info` | `#38bdf8` | `#38bdf8` | No |
| morado (nuevo) | `--purple` / `--purple-light` (nuevas) | `#c4b5fd` / `#3b0764` | Mismos valores, como variables | Solo estructura |

**Conclusión clave:** la paleta oscura ya estaba bien calibrada (evita negro/blanco puro, usa fondos en capas `#0f172a → #1e293b → #263348`, y sus badges pasan WCAG AA). El trabajo real de esta propuesta es (a) **llevar el color que hoy vive en JavaScript a las variables existentes** para que el modo oscuro los alcance, y (b) **oscurecer dos textos de badge en modo claro** para que también pasen AA.

## 6. Reglas de uso de color

- El rojo (`--red`) se usa solo para error, anulación, eliminación o peligro — nunca para un estado neutro como "Pendiente".
- El verde (`--green`) se usa solo para cobro, éxito o confirmación positiva.
- El naranja/ámbar (`--orange`) se usa solo para pendiente, aviso o revisión requerida.
- El azul (`--blue`) se usa para navegación, información o la acción principal de la interfaz.
- El morado (`--purple`, nueva variable) queda reservado para "Parcial" (recibos) y cualquier estado intermedio que no sea ni pendiente ni completado — no se reutiliza para otra cosa.
- El gris es el color por defecto de cualquier acción o estado secundario/cerrado (anulado, rectificado, no enviado) — nunca para algo que requiere acción.
- Ninguna fila de tabla debe depender solo del color: `Anulado` ya combina gris + tachado (mantener); se recomienda que `Pendiente`/`Parcial`/`Devuelto` dejen de teñir el texto de la fila y confíen solo en el badge (que ya lleva texto legible, no solo color).
- Los colores de estado deben declararse siempre como variables (`var(--...)`), nunca como hex sueltos en CSS ni en `style="..."` de JavaScript — excepción única y documentada: el HTML de recibos/facturas para imprimir, que debe verse igual sin importar el tema.

## 7. Componentes afectados

| Componente | Estado actual | Acción propuesta |
|---|---|---|
| Botones | Correctos, ya usan variables + overrides `body.dark` completos | Sin cambios |
| Tablas | Cabecera/cuerpo correctos; filas de estado (`.tr-*`) hardcodeadas y semánticamente inconsistentes | Corregir según §3/§4 (C01) |
| Badges | Verde/rojo/azul correctos; naranja y gris no pasan AA en claro; morado sin variable propia | Corregir colores (§5) + añadir `--purple`/`--purple-light` |
| Formularios | Campos correctos en ambos temas; anillo de foco débil | Reforzar contraste del foco (§4 C07) |
| Modales (contenedor) | Correcto en ambos temas | Sin cambios |
| Modales (contenido inyectado por JS) | Cajas de estado con colores fijos, rompen en oscuro | Corregir según §3/§4 (C02/C16) |
| Menús ("Más ▾") | Correctos (revisión UX anterior) | Sin cambios |
| Dashboard cards | Correctas en ambos temas | Sin cambios |
| Gráficos (Chart.js) | Colores correctos al crear, no se actualizan al cambiar de tema en caliente | Redibujar en `toggleModoOscuro()` (§4 C04) |
| Alerts / callouts | Correctos, usan variables | Sin cambios |
| Toasts | Correctos (`toast-success/error/info` usan variables) | Sin cambios |
| Tooltips | Nativos del navegador, no restylables — no es un defecto de la app | Sin acción posible ni necesaria |
| Dropdowns nativos (`<select>`) | Correctos gracias a `color-scheme: dark` | Sin cambios |
| Paginación | Correcta en ambos temas | Sin cambios |
| Iconos SVG inline | La mayoría usa `stroke="currentColor"` (hereda el color del texto, se adapta solo) — correcto | Sin cambios, salvo los iconos con `fill` fijo dentro de los bloques ya señalados en §3 |
| Aviso ⚠IPC/IRAV (Contratos/Plantillas) | Sin versión oscura | Corregir (§4 C03/C12) |
| Menú lateral | Inaccesible por teclado | Añadir semántica accesible (§4 C06) |

## 8. Plan de implementación

**Fase 1** — Variables base y limpieza de duplicados
- Añadir `--purple`/`--purple-light` (y sus equivalentes oscuros) a `:root`/`body.dark`.
- Sustituir los hex duplicados de `.tr-*`, `.tr-selected`, `.aviso-revision-header`, `.revisiones-anuales-header`, `.rev-urgente` por las variables ya existentes.
- Oscurecer el texto de `badge-orange` y `badge-gray` en modo claro (§5).

**Fase 2** — Componentes de estado dentro de modales
- Sustituir las cajas de "cargando/éxito/error" de `email.js` y `facturas.js` por variables (`var(--color-info-light)`, `var(--green-light)`, `var(--red-light)`).
- Auditar `actividad.js` (19 hex) y `plantillas.js` (18 hex) con el mismo criterio.

**Fase 3** — Aviso ⚠IPC/IRAV
- Sustituir los 5 tonos ámbar hardcodeados de `contratos.js` (filtro, badge, modal) por `var(--orange)`/`var(--orange-light)`; comprobar que el resultado se ve bien en modo oscuro.

**Fase 4** — Gráficos del Dashboard
- Enganchar `toggleModoOscuro()` (en `ux.js`) a un redibujado real de los gráficos activos: o bien guardar las instancias `Chart` en variables de módulo y llamar a `.destroy()` + recrear con `initDashboardCharts()`, o actualizar `chart.options` y llamar a `chart.update()`.

**Fase 5** — Accesibilidad de teclado y foco
- Menú lateral: añadir `tabindex="0"` y `role="button"` (o convertir a `<button>`/`<a>`) + manejador de `keydown` para `Enter`/`Espacio` en cada `.nav-item`.
- Reforzar el anillo de foco de inputs/selects/textarea (subir opacidad del `box-shadow` o añadir `outline` visible).

**Fase 6** — QA visual completo
- Revisar cada pantalla en claro y oscuro tras los cambios.
- Repetir los cálculos de contraste de §5 sobre los valores finales.
- Verificar navegación completa por teclado (Tab) sin usar el ratón.

## 9. Riesgos

- **Volumen:** 246 apariciones de hex en JS: corregirlas todas exige tocar 20 ficheros. Se recomienda hacerlo por fases (§8) y no de golpe, empezando por lo más visible (modales de email, aviso IPC).
- **Chart.js:** guardar las instancias para poder destruirlas/redibujarlas es un cambio de estructura pequeño pero real en `dashboard.js` — hay que confirmar que no queden "gráficos fantasma" duplicados si se llama dos veces sin destruir el anterior.
- **Iconos con `fill` fijo:** algunos SVG puede que usen `fill="#xxxxxx"` en vez de `currentColor` — no se ha hecho un barrido exhaustivo de SVGs individuales, solo de `style="color:/background:"`; posible trabajo adicional no cuantificado aquí.
- **Menú lateral accesible:** añadir `tabindex`/`role`/`keydown` a los `.nav-item` es de bajo riesgo funcional, pero conviene probarlo con lector de pantalla si es posible, no solo con teclado.
- **Contraste "aprobado" es aproximado:** los cálculos de §5 usan la fórmula estándar WCAG sobre los valores hex declarados; no sustituyen una auditoría con herramienta certificada (axe, Lighthouse), pero son suficientes para decidir qué corregir primero.
- **No afecta a lógica de negocio ni backend:** todo lo propuesto es CSS/JS de presentación; ningún cambio toca `api.php`, la base de datos, ni las funciones de negocio.

## 10. Pruebas realizadas en navegador (antes de la propuesta)

Verificado en `http://localhost/AlquiGest_v2/AlquiGest.php` (aplicación real, datos de producción, sin modificar ningún registro):

- **Modo claro → modo oscuro (botón de la cabecera):** el cambio es instantáneo y persiste en `localStorage` tras recargar, tal como dice el README.
- **Dashboard, gráficos:** al cambiar a oscuro estando ya en el Dashboard, el fondo, las tarjetas y el resto de la pantalla cambian correctamente, pero el gráfico de barras y el de dona **mantienen el grid y los colores de fondo claros** hasta navegar a otra pantalla y volver — confirma el hallazgo de §2.5/§4 C04.
- **Contratos, aviso ⚠IPC:** en modo oscuro, el chip de filtro "⚠ REVISIÓN PENDIENTE" (fondo sólido `#fef3c7`) y el modal completo "Aplicar subida IPC" (fondo sólido `#fef3c7` cubriendo casi todo el modal) se quedan en tono crema claro sin ningún ajuste — confirmado con capturas reales. El botón "⚠ IPC" de cada fila, al ser un color sólido con texto blanco, sí se lee razonablemente bien en ambos temas — matiz añadido tras la comprobación visual, el problema real está en el chip de filtro y el modal, no en ese botón. Confirma §2.4/§4 C03.
- **Recibos, tabla:** confirmado con captura que un recibo `Pendiente` muestra el badge en naranja pero el texto completo de la fila (número, inquilino, inmueble, importe) en rojo — dos señales de color para el mismo estado. Confirma §2.2/§4 C01.
- **Modal de email:** verificado (inyectando el mismo HTML que genera `email.js`, sin disparar ningún envío real) que la caja "⏳ Enviando email…" aparece con fondo azul claro (`#dbeafe`) dentro del modal ya oscuro — el defecto más visible de toda la revisión, confirma §2.3/§4 C02.
- **Menú lateral:** confirmado en el código (`AlquiGest.php`) que ningún `.nav-item` tiene `tabindex` ni `role`; probado pulsando Tab repetidamente desde la búsqueda global — el foco salta directamente a los botones de cabecera, saltándose todo el menú lateral.

**Prototipo (`ux_modo_oscuro_colores.php`) probado en navegador tras construirlo:** las 9 pestañas cambian correctamente; el interruptor Claro/Oscuro actualiza al instante toda la página (paleta, tablas, badges, formulario, modal, dashboard, gráfico) sin recargar; la comparativa Antes/Después de la pestaña 1 reproduce fielmente ambos defectos reales (caja de modal y fila roja) y muestra cómo quedan corregidos. Se detectó y corrigió un bug propio del prototipo durante la prueba (la casilla del formulario se estiraba al 100% de ancho por una regla CSS demasiado genérica) — corregido y verificado de nuevo. Sin errores de consola en ninguna pestaña ni tema.

## 11. Criterios de aceptación

- Ningún color de estado (badge, fila, alerta) se declara como hex suelto en CSS o en `style=` de JavaScript, salvo el HTML de impresión de recibos/facturas (documentado como excepción).
- Los badges `Pendiente`, `Anulado`/`Rectificada`/`No enviado` alcanzan ≥4.5:1 de contraste en modo claro (recalculado tras el cambio).
- Ningún modal muestra una caja de color fijo que no se adapte al tema activo.
- El aviso ⚠IPC/IRAV se ve correctamente en modo oscuro en Contratos y Plantillas.
- Cambiar de tema con el gráfico del Dashboard visible actualiza sus colores sin necesidad de navegar a otra pantalla.
- Todo el menú lateral es alcanzable y operable solo con teclado (Tab + Enter/Espacio).
- No quedan errores de consola nuevos tras los cambios, en ningún módulo.

## 12. Detalle de la implementación real (09/07/2026)

**Fase 1 — Variables y limpieza (`assets/css/main.css`):**
- Nuevas variables `--purple`/`--purple-light` en `:root` y `body.dark`.
- `--orange` claro corregido de `#c27803` a `#92400e` (contraste 3.12:1 → 6.30:1).
- `badge-gray` claro corregido a `var(--gray-600)` (contraste 4.18:1 → 6.74:1).
- `.tr-pendiente/.tr-cobrado/.tr-parcial/.tr-devuelto/.tr-anulado/.tr-selected`, `.aviso-revision-header`, `.revisiones-anuales-header`, `.renov-plazo-agotado`, `.rev-urgente` tokenizados; overrides `body.dark` redundantes eliminados donde ya cascadeaban solos.
- `.tr-pendiente` conserva `color: var(--red)` por decisión explícita del usuario (ver §0).

**Fase 2 — Cajas de estado en modales:** `email.js` y `facturas.js` (mensajes cargando/éxito/error), más una pasada adicional de auditoría en `actividad.js` (mapas `_ACT_COLOR`/`_ACT_BG` reescritos a variables) y `plantillas.js` (badges Activa/Inactiva/Por defecto migrados a las clases `badge-*` reales, cajas de aviso e indicadores tokenizados).

**Fase 3 — Aviso ⚠IPC/IRAV:** `contratos.js` (filtro, badge de renta, modal "Aplicar subida IPC") y `recibos-cobro.js` (mismo aviso en el flujo de generación de recibo) tokenizados. Los botones sólidos "⚠ IPC" / "Aplicar subida" / "Aplicar al recibo" se dejaron **deliberadamente hardcodeados** (`#f59e0b` + texto blanco): tokenizarlos a `var(--orange)` habría introducido un contraste pobre en modo oscuro, donde `--orange` es un amarillo claro (`#fbbf24`) sobre texto blanco.

**Fase 4 — Gráficos del Dashboard:** `dashboard.js` refactorizado para guardar las instancias `Chart` (`_dashChartBar`, `_dashChartDona`) y una función `_redibujarGraficosDashboard()` que las destruye y recrea con la paleta del tema activo; enganchada desde `toggleModoOscuro()` en `ux.js`.

**Fase 5 — Accesibilidad de teclado:** los 15 `.nav-item` de `AlquiGest.php` reciben `tabindex="0" role="button"`; `ux.js` añade un `keydown` global que activa `click()` en Enter/Espacio sobre `.nav-item`. Anillos de foco reforzados (opacidad `.12-.2` → `.35-.4`) en inputs/selects/textarea y añadido `:focus-visible` propio para `.nav-item` (la barra lateral es siempre oscura independientemente del tema de la app).

**Fase 6 — QA:** verificado en copia aislada (`AlquiGest_v2_test_colors`, BD `alquigest_test_colors_20260709`, servidor PHP en `127.0.0.1:8899`) con Playwright: redibujado de gráficos en vivo, fila "Pendiente" en rojo en oscuro, modal IPC con tema oscuro correcto, badges de Plantillas con clases reales, navegación completa por teclado (Enter/Espacio en varios `.nav-item`, anillo de foco visible), badge "Cobro registrado" correcto en verde en ambos temas. Barrido final de las 14 pantallas de la aplicación en claro y oscuro: **0 errores/avisos de consola**. Verificado además que la aplicación real (BD de producción) carga sin errores tras el despliegue. Entorno de pruebas aislado eliminado por completo tras la verificación (proceso PHP, base de datos y directorio).

**Ficheros modificados:** `assets/css/main.css`, `assets/js/ux.js`, `assets/js/dashboard.js`, `assets/js/contratos.js`, `assets/js/recibos-cobro.js`, `assets/js/email.js`, `assets/js/facturas.js`, `assets/js/actividad.js`, `assets/js/plantillas.js`, `AlquiGest.php` (cache-busters `?v=20260709a` + `tabindex`/`role` en `.nav-item`).

---

*Documento actualizado tras la implementación real (09/07/2026). Prototipo histórico: `ux_modo_oscuro_colores.php` + `assets/css/ux-theme-proposal.css`.*
