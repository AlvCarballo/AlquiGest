# Modo oscuro — sistema de colores y correcciones

Este documento registra las decisiones y correcciones de contraste/color del modo oscuro de AlquiGest. Se referencia desde comentarios en `assets/css/main.css` y `assets/js/*.js`.

## Arquitectura del modo oscuro

El tema se activa añadiendo la clase `dark` al `<body>` (`assets/js/ux.js`, `toggleModoOscuro()`), persistida en `localStorage`. Todo el sistema de color vive en **tokens CSS** (custom properties) definidos dos veces en `assets/css/main.css`:

- `:root` — valores de modo claro.
- `body.dark` — valores de modo oscuro (mismos nombres de variable, valores distintos).

El resto de la hoja de estilos usa siempre `var(--token)`, nunca colores fijos, por lo que en teoría cualquier componente se adapta automáticamente al cambiar de tema. En la práctica se han encontrado varias excepciones a esta regla, documentadas abajo.

## §2 / §4 — Accesibilidad de teclado y foco (08/07/2026)

- **C06**: el anillo de foco por defecto del navegador se pierde sobre el menú lateral, que es siempre oscuro (fondo `#0f172a`) tanto en modo claro como oscuro de la app. Se sustituyó por un anillo claro con separación, visible en ambos casos.
- **C07**: la opacidad del anillo de foco de `--blue` se subió de `.15` a `.35` — con brillo de pantalla alto el anillo era demasiado tenue para verse bien.
- Los `.nav-item` son `<div onclick>` con `tabindex="0"`/`role="button"` para entrar en el orden de tabulación; como un `<div>` no activa su `onclick` con Enter/Espacio de forma nativa, se añadió un listener de `keydown` en `assets/js/ux.js` que simula el click.

## §3 — Unificación de colores de renovación (08/07/2026)

Los indicadores de "renovación próxima"/"renovación urgente" del Dashboard usaban un tercer tono ámbar sin relación con el resto de la paleta. Se unificaron a `var(--orange)` / `var(--orange-light)`, ya usados en el resto de avisos del sistema (revisión IPC, etc.), sin necesitar overrides adicionales en `body.dark` porque heredan directamente el token.

## §5 — Correcciones de contraste WCAG AA (08/07/2026)

- `--orange` se oscureció de `#c27803` a `#92400e`: el texto sobre `--orange-light` daba 3.12:1 (por debajo del 4.5:1 exigido para texto normal); con el nuevo valor sube a 6.30:1.
- El gris de ciertos textos secundarios se subió de `--gray-500` a `--gray-600`: sobre `--gray-100` el primero daba 4.18:1 (insuficiente), el segundo da 6.74:1.

---

## Corrección de previsualización de Plantillas en modo oscuro

**Fecha:** 2026-07-11

### Problema detectado

Al pulsar "Vista previa" en el módulo **Plantillas** con el modo oscuro activado, el contenido de la previsualización no se adaptaba al tema: aparecían bloques con fondos claros fijos y las marcas de variables (resueltas / no resueltas / bloques dinámicos) mantenían siempre los mismos colores independientemente del tema activo, degradando el contraste.

### Causa raíz

Dos causas independientes, ambas confirmadas antes de tocar nada:

1. **Colores hardcodeados en PHP.** `assets/php/plantillas.php`, función `generarPreviewHtml()`, generaba el HTML de la previsualización con estilos **inline** y colores fijos en hexadecimal, totalmente ajenos al sistema de variables CSS:
   - `<mark style="background:#d1fae5;...">` (variable resuelta)
   - `<mark style="background:#dbeafe;...color:#1e40af;...">` (placeholder `FotosContrato`)
   - `<mark style="background:#ecfdf5;...color:#166534;...">` (placeholder `ListaMuebles`)
   - `<mark style="background:#fee2e2;...color:#991b1b">` (variable no reconocida)
   - El contenedor exterior: `<div style="...background:#fafafa;border:1px solid var(--gray-200)...">` — fondo fijo claro combinado con un borde que sí era una variable (mezcla inconsistente).
   - Ninguno de estos estilos inline podía reaccionar a `body.dark` porque no eran `var(...)`: eran literales.

2. **Bug latente en el propio sistema de tokens** (descubierto durante la implementación, no visible hasta ahora porque nadie usaba estos tokens todavía): `--color-success`, `--color-success-light`, `--color-error`, `--color-error-light`, `--color-brand*` y `--color-warn` (sin sufijo `-muted`) se definen en `:root` como alias, p. ej. `--color-success: var(--green)`. Como `:root` corresponde al elemento `<html>`, ese `var(--green)` se resuelve **una sola vez para `<html>`**, usando el `--green` de modo claro (`body.dark` no llega a `<html>`, solo a `<body>` y sus descendientes). El valor resultante (verde claro) se hereda tal cual hacia abajo; como `body.dark` nunca redeclaraba `--color-success` directamente (solo `--green`), cualquier componente que usara `--color-success`/`--color-error` habría mostrado el color de modo claro aunque el body estuviera en modo oscuro. `--color-info`, en cambio, sí funcionaba correctamente porque está declarado con su propio valor literal dentro de `body.dark` (sin depender de un alias). Se confirmó con `getComputedStyle(document.body).getPropertyValue('--color-success')` devolviendo el valor claro estando `body.dark` activo.

### Archivos revisados

- `assets/php/plantillas.php` — generación de la previsualización (`generarPreviewHtml()`, `accionPreview()`).
- `assets/js/plantillas.js` — montaje del modal (`_plantillaPreview()`, `_plantillaPreviewConEntidad()`) y selector de fotos (`_fotosRender()`).
- `assets/css/main.css` — tokens de tema (`:root`, `body.dark`) y estilos de modal (`.ag-modal*`).
- `AlquiGest.php` — carga de `main.css`/`plantillas.js` (versión de caché).
- `assets/js/ux.js`, `assets/js/configuracion.js` — revisados, sin relación con el bug.

### Colores hardcodeados encontrados

| Ubicación | Antes | Problema |
|---|---|---|
| `plantillas.php` — variable resuelta | `background:#d1fae5` | Fijo, no reacciona a `body.dark` |
| `plantillas.php` — placeholder `FotosContrato` | `background:#dbeafe;color:#1e40af` | Fijo |
| `plantillas.php` — placeholder `ListaMuebles` | `background:#ecfdf5;color:#166534` | Fijo |
| `plantillas.php` — variable no reconocida | `background:#fee2e2;color:#991b1b` | Fijo |
| `plantillas.php` — contenedor de preview | `background:#fafafa` | Fijo (el borde sí usaba variable) |
| `plantillas.js` — leyenda de colores | `style="background:var(--green-light)..."` | Ya usaba variables, pero duplicado como estilo inline en dos funciones |
| `plantillas.js` — pie de miniatura de foto | `background:#fff` | Fijo, caja blanca en modal oscuro |

### Cambios realizados

**Backend (`assets/php/plantillas.php`):**
- Los 4 `<mark style="...">` se sustituyeron por `<mark class="tpl-preview-mark-ok|placeholder|error">`.
- El contenedor pasó de un `<div style="...">` a `<div class="tpl-preview">`.
- `FotosContrato` y `ListaMuebles` comparten ahora una única clase (`tpl-preview-mark-placeholder`): ambos son bloques informativos "esto se generará al descargar", no un error ni un valor resuelto; unificarlos evita duplicar casi la misma variante de color con matices sin significado adicional.
- El escapado de valores (`htmlspecialchars(...,ENT_QUOTES,'UTF-8')`) ya existía y se mantiene íntegro — verificado que la respuesta sigue sin poder inyectar HTML/JS (ver «Pruebas técnicas»).
- **No se ha tocado la generación real del DOCX** (función distinta, `accionGenerar`/`accionGenerarConFotos`): verificado que sigue produciendo un `.docx` válido.

**Frontend (`assets/js/plantillas.js`):**
- La leyenda de colores y el aviso de "variables no resueltas" (duplicados en `_plantillaPreview()` y `_plantillaPreviewConEntidad()`) pasan de estilos inline a clases (`tpl-preview-legend`, `tpl-preview-legend-ok`, `tpl-preview-legend-error`, `tpl-preview-warning`, `tpl-preview-error-msg`).
- El pie de miniatura del selector de fotos (`_fotosRender()`) pasa de `background:#fff` inline a la clase `tpl-foto-caption`.

**CSS (`assets/css/main.css`):**
- Nueva sección "Previsualización de plantillas" con las clases `.tpl-preview`, `.tpl-preview-mark-ok`, `.tpl-preview-mark-placeholder`, `.tpl-preview-mark-error`, `.tpl-preview-legend*`, `.tpl-preview-warning`, `.tpl-preview-error-msg`, `.tpl-foto-caption` — todas construidas sobre tokens existentes (`--bg-subtle`, `--text-primary`, `--border-default`, `--color-success*`, `--color-error*`, `--color-info*`, `--color-warn*`).
- **Corrección de raíz del bug de tokens**: `body.dark` ahora redeclara explícitamente `--color-success`, `--color-success-light`, `--color-error`, `--color-error-light`, `--color-brand`, `--color-brand-dark`, `--color-brand-light`, `--color-warn`, `--color-warn-light` (antes solo existían como alias en `:root`, nunca redeclarados en `body.dark`). Esto no solo arregla la previsualización de Plantillas, sino cualquier uso futuro de estos alias en el resto de la aplicación.
- **Ajuste de contraste WCAG AA**: `--color-success` (#22c55e) sobre `--color-success-light` (#14532d) en modo oscuro medía 4.00:1, por debajo del 4.5:1 exigido para texto normal. Se añadió un override específico (`body.dark .tpl-preview-mark-ok, body.dark .tpl-preview-legend-ok`) que oscurece el fondo a `#0d3319` (6.12:1), sin tocar el token global `--green-light` (que se usa en otros componentes como badges) para no producir efectos colaterales fuera de Plantillas.

### Clases CSS nuevas

```
.tpl-preview                 Contenedor del texto de la plantilla (fondo/color/borde con variables)
.tpl-preview-mark-ok         Variable resuelta (verde)
.tpl-preview-mark-placeholder Bloque dinámico: FotosContrato / ListaMuebles (azul/info)
.tpl-preview-mark-error      Variable no reconocida (rojo + borde, además del texto <<Nombre>>)
.tpl-preview-legend          Texto explicativo sobre el significado de los colores
.tpl-preview-legend-ok       Muestra de color "verde" dentro de la leyenda
.tpl-preview-legend-error    Muestra de color "rojo" dentro de la leyenda
.tpl-preview-warning         Aviso de variables no resueltas (antes del contenido)
.tpl-preview-error-msg       Mensaje de error al cargar la previsualización
.tpl-foto-caption            Pie de miniatura en el selector de fotos de FotosContrato
```

### Pruebas realizadas

**Navegador (Playwright):**
1. Modo claro → Plantillas → Vista previa de "Contrato de Vivienda 2026": marcas verdes/rojas legibles, estructura correcta, scroll funcional. Sin cambios visuales respecto al comportamiento anterior.
2. Modo oscuro → misma plantilla: modal y contenedor de preview oscuros, texto principal legible, marca verde (fecha resuelta) y marcas rojas (variables no disponibles) con buen contraste, aviso amarillo de "variables no resueltas" legible, scrollbar del navegador en variante oscura (`color-scheme: dark` ya aplicado a `body.dark`).
3. Plantilla con placeholders `FotosContrato` y `ListaMuebles` (Inventario del Contrato, multi-inquilino): marcas azules/info visibles y con buen contraste en ambos temas.
4. Verificado mediante `getComputedStyle` que `--color-success`/`--color-error` resuelven a los valores de modo oscuro tras la corrección (antes devolvían el valor de modo claro con `body.dark` activo).
5. Generación real de DOCX (`action=generar`) probada tras el cambio: el fichero resultante sigue siendo un `.docx` válido (Microsoft Word 2007+), sin relación con el HTML de la previsualización.

**Pruebas técnicas:**
6. `curl` directo a `action=preview`: la respuesta ya no contiene ningún `style=` ni colores hexadecimales; contiene las clases `tpl-preview-mark-ok`/`tpl-preview-mark-error` esperadas.
7. Confirmado que los valores de variables siguen pasando por `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` antes de insertarse en el HTML — no se ha introducido ninguna vía de XSS nueva ni se ha debilitado el escapado existente.
8. Contraste WCAG calculado para los 6 pares color-sobre-fondo relevantes (claro y oscuro, success/error/info): todos ≥ 4.5:1 tras la corrección, salvo `--color-info` en modo claro (3.57:1, par preexistente no tocado en este cambio — ver «Pendientes»).

### Resultado en modo claro

Sin cambios de apariencia: mismos colores, mismo contraste que antes de la corrección (verificado visualmente, sin regresión).

### Resultado en modo oscuro

Correcto: fondo del modal y de la previsualización oscuros, texto legible, las tres categorías de marca (resuelta/placeholder/error) con contraste ≥ 4.5:1, aviso de variables no resueltas legible, sin bloques blancos ni texto invisible, scroll con estilo nativo oscuro.

### Pendientes

- `--color-info` / `--color-info-light` en modo claro da 3.57:1 (por debajo de AA para texto normal). Es un par de tokens preexistente, usado en `.alert-info`, `.backup-alert`, `.revisiones-anuales-card`, entre otros — no se ha tocado en esta corrección porque no forma parte del bug de Plantillas y su ajuste afectaría a componentes fuera de este alcance; queda anotado para una revisión de accesibilidad más amplia.
- No se han añadido controles adicionales de bajo nivel (p. ej. `prefers-contrast`) — fuera del alcance solicitado.
