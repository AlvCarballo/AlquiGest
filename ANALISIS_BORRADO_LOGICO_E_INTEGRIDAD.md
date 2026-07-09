# Análisis y corrección: borrado lógico e integridad referencial

**Fecha:** 2026-07-09
**Versión afectada:** AlquiGest v3.0.0
**Archivos principales modificados:** `assets/php/api.php`, `assets/php/plantillas.php`, `assets/php/install.php`, `assets/js/config.js`, `assets/js/propietarios.js`, `assets/js/fincas.js`, `assets/js/inmuebles.js`, `assets/js/inquilinos.js`, `assets/js/contratos.js`, `assets/js/configuracion.js`, `assets/js/plantillas.js`, `README.md`

---

## 1. Resumen del problema

La API (`assets/php/api.php`, acción `delete`) ejecutaba un `DELETE FROM <tabla> WHERE id = ?` genérico sobre cualquier tabla de la lista blanca (`propietarios`, `fincas`, `inmuebles`, `inquilinos`, `contratos`, `recibos`, `facturas`, etc.) **sin comprobar ninguna dependencia**. Las únicas protecciones existentes vivían en el frontend (JS), lo que significa:

- Cualquier llamada directa a la API (`curl`, DevTools, un bug futuro) podía borrar físicamente un propietario, finca, inmueble, inquilino, contrato, recibo o factura con relaciones activas, dejando registros huérfanos.
- Se encontró además un **bug real**: `deletePropietario()` en `assets/js/propietarios.js` comprobaba `DB.get('inmuebles').filter(i => i.propietario_id === id)`, pero la tabla `inmuebles` **no tiene columna `propietario_id`** (solo `finca_id`). La comprobación nunca bloqueaba nada: un propietario con fincas podía borrarse físicamente desde la interfaz, dejando sus fincas con un `propietario_id` apuntando a un registro inexistente.
- `assets/js/config.js` → `DB.delete()` no comprobaba la respuesta del servidor: aunque el backend hubiera rechazado el borrado, el registro se eliminaba igualmente de la caché local, mostrando al usuario que "se había borrado" cuando en realidad seguía en la base de datos (o, peor, dejando pensar que un borrado bloqueado había fallado sin más explicación).
- Contratos podían borrarse físicamente desde el menú "Más ▾ → Eliminar" si no tenían recibos asociados, contradiciendo el principio de trazabilidad contractual que ya se aplicaba a recibos y facturas.

## 2. Riesgo funcional

- Pérdida de la jerarquía Propietario → Finca → Inmueble → Contrato descrita en el README, con relaciones rotas (fincas sin propietario, inmuebles sin finca, etc.).
- Informes IRPF, Excel y el Dashboard podrían fallar o mostrar datos incompletos al toparse con claves foráneas (`propietario_id`, `finca_id`, `inmueble_id`, `inquilino_id`) que ya no existen.
- Pérdida de trazabilidad legal si se llegara a borrar un contrato con histórico de rentas o (por la vía directa a la API) un recibo o factura.

## 3. Riesgo de pérdida de datos

Antes de esta corrección, un borrado físico era irreversible: no había papelera, backup automático ni confirmación por parte del servidor. Un clic accidental (o un script) podía destruir de forma permanente la ficha de un propietario, finca, inmueble o inquilino con años de historial.

## 4. Relaciones entre entidades

```
Propietario (1) → Finca (N)         fincas.propietario_id
Finca (1)       → Inmueble (N)      inmuebles.finca_id
Inmueble (1)    → Contrato (N)      contratos.inmueble_id   (histórico completo, no solo el activo)
Inquilino (1)   → Contrato (N)      contratos.inquilino_id
Contrato (1)    → Recibo (N)        recibos.contrato_id
Recibo (1)      → Factura (0..1)    facturas.recibo_id
Inmueble/Inquilino → Recibo/Factura recibos.inmueble_id / inquilino_id, facturas.inmueble_id / inquilino_id (denormalizado)
```

No existen `FOREIGN KEY` reales en MySQL (todas las relaciones son lógicas, gestionadas en PHP/JS); por eso la integridad debe garantizarse explícitamente en el backend.

## 5. Matriz de eliminación

| Entidad | ¿Borrado físico? | ¿Borrado lógico? | Condición | Acción correcta |
|---|---|---|---|---|
| Propietario | No, nunca | Sí (`eliminado`) | Sin fincas activas (no eliminadas) | "Eliminar" → marca `eliminado=1` |
| Finca | No, nunca | Sí (`eliminado`) | Sin inmuebles activos (no eliminados) | "Eliminar" → marca `eliminado=1` |
| Inmueble | No, nunca | Sí (`eliminado`) | Sin contratos, recibos ni facturas asociadas | "Eliminar" → marca `eliminado=1` |
| Inquilino | No, nunca | Sí (`eliminado`) | Sin contratos, recibos ni facturas asociadas | "Eliminar" → marca `eliminado=1` |
| Contrato | No, nunca | No (ya tiene `estado`/`fecha_baja`) | — | "Dar de baja" (ya existente); `delete` bloqueado sin excepción |
| Recibo | No, nunca | No (ya tiene `estado='anulado'`) | — | "Anular" (ya existente); `delete` bloqueado sin excepción |
| Factura | No, nunca | No (ya tiene `estado='rectificada'/'anulada'`) | — | "Anular/Rectificar" (ya existente); `delete` bloqueado sin excepción |
| Plantilla DOCX | No, nunca | Sí (`eliminado`) | Ninguna (no hay FK hacia plantillas) | "Eliminar" → marca `eliminado=1`, conserva el fichero DOCX |

**Por qué contratos/recibos/facturas no reciben una columna `eliminado` nueva:** ya tienen un campo de estado con la misma función (`contratos.estado` + `fecha_baja`/`motivo_baja`; `recibos.estado='anulado'`; `facturas.estado='rectificada'/'anulada'`). Añadir un segundo campo `eliminado` sería redundante y crearía dos fuentes de verdad para el mismo concepto ("¿este documento sigue vigente?").

## 6. Endpoints revisados

- `assets/php/api.php` → acción `delete` (reescrita por completo).
- `assets/php/api.php` → acción `getAll` (ahora excluye `eliminado=1` de las tablas con borrado lógico).
- `assets/php/api.php` → acción `save` (sin cambios; ya validaba y no permitía tocar `eliminado` porque no está en `$SCHEMA`).
- `assets/php/plantillas.php` → acciones `delete` y `list`.
- `assets/php/install.php` → sin endpoint HTTP nuevo, pero se amplió el DDL de instalación limpia/con ejemplos.

## 7. Funciones JS revisadas

| Función | Archivo | Cambio |
|---|---|---|
| `DB.delete()` | `config.js` | Ahora interpreta la respuesta del backend y devuelve `{ok, error, code, details}` en vez de vaciar la caché a ciegas |
| `deletePropietario()` | `propietarios.js` | Corregido el bug (`inmuebles.propietario_id` → `fincas.propietario_id`); usa el resultado real de `DB.delete()` |
| `deleteFinca()` | `fincas.js` | Usa el resultado real de `DB.delete()` |
| `deleteInmueble()` | `inmuebles.js` | Usa el resultado real de `DB.delete()` |
| `deleteInquilino()` | `inquilinos.js` | Usa el resultado real de `DB.delete()` |
| `deleteContrato()` | `contratos.js` | **Eliminada.** Ya no existe ninguna vía de borrado de contratos en el frontend |
| `_inqSecEliminar()` | `contratos.js` | Sin cambio de lógica; adaptado al nuevo contrato de retorno de `DB.delete()` |
| `_plantillaEliminar()` / `_plantillaEliminarConfirmar()` | `plantillas.js` | Texto de confirmación actualizado (ya no anuncia que se borra el fichero) |

## 8. Cambios backend realizados

En `assets/php/api.php`:

1. Nuevo bloque de constantes: `$TABLAS_SIN_BORRADO_FISICO` (contratos/recibos/facturas, bloqueadas sin excepción) y `$TABLAS_CON_BORRADO_LOGICO` (propietarios/fincas/inmuebles/inquilinos).
2. Nueva función `comprobarDependencias()`: cuenta registros hijos antes de permitir el borrado lógico. Para fincas/inmuebles (que también tienen `eliminado`) solo cuentan las filas hija **activas** — una finca ya eliminada, sin inmuebles, no bloquea borrar su propietario.
3. Acción `delete` reescrita: bloquea contratos/recibos/facturas siempre; para el resto, bloquea si hay dependencias, y si no las hay ejecuta `UPDATE ... SET eliminado=1, eliminado_en=NOW()` en lugar de `DELETE`.
4. Acción `getAll` reescrita: añade `WHERE eliminado = 0` a las tablas con borrado lógico, de forma que ningún registro eliminado llega nunca al navegador (listados, selects, informes Excel, PDF y Dashboard quedan protegidos automáticamente, porque todos leen de la misma caché `DB._cache` poblada por `getAll`).
5. Migración automática (mismo patrón que las migraciones existentes de `aviso_factura`, `recibo_rectificado_id`, etc.): añade `eliminado`/`eliminado_en` a las 4 tablas si no existen, y sus índices.

En `assets/php/plantillas.php`:

1. `CREATE TABLE IF NOT EXISTS` ampliado con `eliminado`/`eliminado_en`.
2. Migración automática para instalaciones existentes (mismo patrón `SHOW COLUMNS` + `ALTER TABLE ADD COLUMN`).
3. `accionList()` filtra `WHERE eliminado = 0`.
4. `accionDelete()` ya no borra el registro ni el fichero DOCX: marca `eliminado=1, eliminado_en=NOW()`.

## 9. Cambios frontend realizados

- Textos de confirmación unificados: *"Este registro se marcará como eliminado y dejará de aparecer en los listados normales, pero se conservará para mantener el histórico. ¿Desea continuar?"* en propietarios, fincas, inmuebles, inquilinos y plantillas.
- Mensajes de error del backend mostrados literalmente vía `toast()` (p. ej. *"No se puede eliminar este propietario porque tiene 1 finca(s) asociada(s)."*).
- Botón "Eliminar" retirado del menú "Más ▾" de Contratos (y de la pestaña Botones de Parámetros: `VisiBorrarCont`).
- El resto de botones "Eliminar" (Propietarios, Fincas, Inmuebles, Inquilinos, Plantillas) se mantienen, porque ahora sí ejecutan un borrado lógico seguro.

## 10. Cambios en base de datos

Columnas nuevas (`TINYINT(1) NOT NULL DEFAULT 0` + `DATETIME NULL`):

| Tabla | Columnas añadidas |
|---|---|
| `propietarios` | `eliminado`, `eliminado_en` + índice `idx_propietarios_eliminado` |
| `fincas` | `eliminado`, `eliminado_en` + índice `idx_fincas_eliminado` |
| `inmuebles` | `eliminado`, `eliminado_en` + índice `idx_inmuebles_eliminado` |
| `inquilinos` | `eliminado`, `eliminado_en` + índice `idx_inquilinos_eliminado` |
| `plantillas` | `eliminado`, `eliminado_en` + índice `idx_plantillas_eliminado` |

`contratos`, `recibos` y `facturas` no reciben columnas nuevas (ver §5).

Efecto colateral menor: al retirar el toggle `VisiBorrarCont`, el nº de parámetros sembrados por `install.php` pasa de 77 a 76 (actualizado en el README). Las instalaciones existentes conservan la fila `VisiBorrarCont` en `configuracion` sin usarla — no se ha borrado para no tocar datos existentes sin necesidad.

## 11. Migraciones aplicadas

- `assets/php/api.php`: auto-migración en el arranque de cada request (mismo patrón ya usado para `aviso_factura`, `recibo_rectificado_id`, etc.). **Verificado en caliente contra la base de datos real del entorno de desarrollo (MySQL 5.7.24)**: las columnas se crearon correctamente sin perder ningún dato existente.
- `assets/php/plantillas.php`: auto-migración equivalente en su propio `CREATE TABLE IF NOT EXISTS` + `SHOW COLUMNS`.
- `install.php`: DDL de instalación limpia/con ejemplos actualizado para que las instalaciones nuevas nazcan ya con las columnas.

No ha hecho falta ningún script `.sql` manual adicional: el propio arranque de la aplicación migra instalaciones existentes de forma idempotente, seedejo el mismo patrón que ya usa el proyecto (ver `assets/sql/migrations/005_recibos_rectificativos.sql`, que documenta el mismo enfoque "api.php aplica automáticamente estos cambios al arrancar").

## 12. Casos de prueba

**Backend (llamadas directas a la API, sin pasar por el frontend):**

1. `DELETE propietarios/1` (con fincas) → bloqueado, `ENTITY_HAS_DEPENDENCIES`.
2. `DELETE fincas/1` (con inmuebles) → bloqueado.
3. `DELETE inmuebles/1` (con contratos/recibos) → bloqueado.
4. `DELETE inquilinos/1` (con contratos/recibos) → bloqueado.
5. `DELETE contratos/1` → bloqueado siempre, `DELETE_NOT_ALLOWED`.
6. `DELETE recibos/1` → bloqueado siempre.
7. `DELETE facturas/1` → bloqueado siempre.
8. Creación de propietario/finca/inmueble/inquilino sin dependencias → `delete` devuelve `{ok:true, logical_delete:true}`; fila verificada en MySQL con `eliminado=1` y `eliminado_en` con timestamp; `getAll` ya no la incluye.
9. Plantilla duplicada (sin uso) → `delete` marca `eliminado=1`; el fichero DOCX permanece en `uploads/plantillas/`; `list` ya no la incluye.
10. Encadenado finca → inmueble: al eliminar lógicamente el único inmueble de una finca de prueba, la finca (antes bloqueada) pasa a poder eliminarse (la dependencia respeta `eliminado` en la tabla hija).

**Navegador (Playwright):**

11. Alta de propietario sin fincas desde la UI → "Eliminar" → diálogo de confirmación con el texto claro → aceptar → desaparece de la lista, contador baja de 6 a 5.
12. Intento de eliminar un propietario con fincas (García Martínez, 4 fincas/inmuebles) desde la UI → bloqueado por la comprobación cliente antes incluso de mostrar el diálogo; la fila permanece intacta.
13. Pantalla Contratos: verificado que ninguna fila ni ningún contrato del menú "Más ▾" ofrece "Eliminar" (solo "Renovar", "Historial de rentas", "Dar de baja", documentos y "Editar").
14. Pantalla Recibos y Facturas: sin botón "Eliminar" en ningún estado (solo "Anular" donde aplica).
15. Pantalla Parámetros → Botones: el toggle "Eliminar contrato" ya no existe.
16. Pantalla Plantillas: listado tras un borrado lógico de prueba muestra el nº correcto de plantillas.
17. Recarga completa de la aplicación (`DB.init()` desde cero) sin errores de consola en Dashboard, Propietarios, Fincas, Pisos/Locales, Contratos, Recibos, Facturas, Parámetros y Plantillas.

## 13. Resultado de pruebas

Todas las pruebas anteriores (17) se ejecutaron sobre la base de datos real de desarrollo y **pasaron**. No se detectaron errores de consola JS en ninguna pantalla visitada. Los contadores de Propietarios (5), Fincas (5), Inmuebles (16), Inquilinos (16), Contratos (16) y Plantillas (6) se mantuvieron exactamente igual antes y después de las pruebas (todos los registros de prueba creados se limpiaron).

## 14. Pendientes

- No se ha construido una pantalla de "papelera" para consultar o restaurar registros con `eliminado=1`. Los datos se conservan en la base de datos (auditoría garantizada), pero hoy solo son recuperables editando `eliminado=0` directamente en la base de datos. Si se necesita restaurar desde la interfaz en el futuro, es una mejora aislada y de bajo riesgo (los datos ya están ahí).
- `assets/php/plantillas.php`: las acciones `generar`, `generarConFotos`, `download`, `preview`, `duplicate`, `setDefault` y `setActiva` no comprueban explícitamente `eliminado` al buscar la plantilla por `id` (solo `list` filtra). Como una plantilla eliminada ya no aparece en ningún selector de la interfaz, el riesgo práctico es mínimo (requeriría conocer manualmente el id), pero queda documentado como posible refuerzo futuro de defensa en profundidad.
- No se han añadido índices `FOREIGN KEY` reales en MySQL (el proyecto nunca los ha usado; la integridad se gestiona en PHP). Añadirlos sería un cambio de arquitectura mayor, fuera del alcance de esta corrección.

## 15. Recomendación final

La corrección elimina el borrado físico de toda entidad de negocio con relaciones, sustituyéndolo por bloqueo (contratos/recibos/facturas, que ya tenían su propio ciclo de vida basado en `estado`) o por borrado lógico real (propietarios/fincas/inmuebles/inquilinos/plantillas, que no tenían ningún mecanismo previo). La validación vive en el backend (`api.php`, `plantillas.php`) como fuente de verdad única; el frontend replica las mismas comprobaciones solo como mejora de UX (evitar una llamada de red innecesaria), nunca como única barrera. Se recomienda, como mejora futura de bajo riesgo, añadir una pantalla de "papelera / registros eliminados" si el usuario llega a necesitar restaurar alguno.

---

## Tabla de seguimiento

| ID | Entidad | Relación detectada | Riesgo | Cambio aplicado | Estado |
|----|---------|--------------------|--------|-----------------|--------|
| 1 | Propietarios | `fincas.propietario_id` | Alto (bug: el check de frontend no funcionaba, permitía borrar propietarios con fincas) | Backend bloquea + borrado lógico (`eliminado`) | ✅ Corregido |
| 2 | Fincas | `inmuebles.finca_id` | Alto (borrado físico sin control en backend) | Backend bloquea + borrado lógico (`eliminado`) | ✅ Corregido |
| 3 | Inmuebles | `contratos/recibos/facturas.inmueble_id` | Alto | Backend bloquea + borrado lógico (`eliminado`) | ✅ Corregido |
| 4 | Inquilinos | `contratos/recibos/facturas.inquilino_id` | Alto | Backend bloquea + borrado lógico (`eliminado`) | ✅ Corregido |
| 5 | Contratos | `recibos.contrato_id`, `historial_rentas.contrato_id`, `contratos_inq_sec.contrato_id` | Alto (borrado físico permitido si no había recibos) | Botón "Eliminar" retirado; `delete` bloqueado sin excepción en backend | ✅ Corregido |
| 6 | Recibos | `facturas.recibo_id` | Medio (ya sin botón en UI, pero sin bloqueo en backend) | `delete` bloqueado sin excepción en backend (defensa en profundidad) | ✅ Corregido |
| 7 | Facturas | Documento legal (RD 1619/2012) | Medio (ya sin botón en UI, pero sin bloqueo en backend) | `delete` bloqueado sin excepción en backend (defensa en profundidad) | ✅ Corregido |
| 8 | Plantillas DOCX | Ninguna (no referenciadas por FK) | Bajo | Borrado lógico (`eliminado`); ya no se borra el fichero DOCX físico | ✅ Corregido |
| 9 | `DB.delete()` (frontend) | — | Alto (ignoraba la respuesta del backend, vaciaba caché igualmente) | Ahora interpreta `{ok,error,code,details}` | ✅ Corregido |
