# Base de datos de AlquiGest — estructura y funcionamiento

Documento de referencia del esquema actual de la base de datos `alquigest` (MySQL 5.7+/MariaDB, motor InnoDB, charset `utf8mb4`). Solo describe **estructura y mecánica**, sin datos. Generado el 2026-07-10 a partir del esquema real (`SHOW CREATE TABLE`) tras la corrección del bug de numeración de recibos — ver `README.md` para la documentación funcional completa de la aplicación.

No hay `FOREIGN KEY` reales en ninguna tabla: la integridad referencial (existencia, estado, cascadas) la gestiona el backend en PHP (`assets/php/api.php`, función `validarDatos()` y la lista blanca `$TABLES`/`$SCHEMA`), no el motor de base de datos.

---

## 1. Jerarquía de negocio

```
empresa (1 fila fija)
  └── propietarios (N)
        └── fincas (N)
              └── inmuebles (N)
                    └── contratos (0-1 activo a la vez)
                          ├── contratos_inq_sec (N — inquilinos secundarios)
                          ├── historial_rentas (N — revisiones de renta)
                          └── recibos (N)
                                ├── recibo_rectificado_id → recibos.id (RER)
                                └── facturas (0-1 vigente)
                                      └── factura_rectificada_id → facturas.id (RET)
```

`inquilinos` no cuelga de nadie directamente: se relaciona con todo lo demás siempre a través de `contratos`.

---

## 2. Tablas de configuración y sistema

### `empresa`
Fila única (normalmente `id=1`) con los datos del arrendador/administrador: identidad fiscal, IBAN, credenciales SMTP de Gmail (`gmail_user`/`gmail_pass`, esta última cifrada con AES-256 si `config.php` tiene `encrypt_key`), plantillas de asunto/cuerpo de email, y `prefijo_recibos` (prefijo usado al construir `numero_recibo`).

### `configuracion`
Tabla clave-valor (`variable` único, `valor` como texto). Controla paginación por módulo, visibilidad de botones (`VisiBorrarProp`, `VisiAnularReci`, etc.), ajustes de WhatsApp, y los parámetros de VERI*FACTU (`verifactu_activo`, `verifactu_entorno`, `verifactu_cert_path`, `verifactu_cert_pass` cifrado, `verifactu_nif_sif`...). El backend interpreta el texto según cada caso (el frontend decide el tipo real).

### `usuarios`
Cuentas de acceso a la aplicación. `rol` es `admin` o `user` (ver `README.md` §23 para la matriz de permisos completa). `password_hash` con `password_hash()`/bcrypt. Borrado lógico propio con `eliminado_en` (no usa la columna genérica `eliminado` del resto de tablas). Nunca se destruye en una reinstalación de `install.php` (`CREATE TABLE IF NOT EXISTS`, no `DROP TABLE`).

### `log_actividad`
Auditoría de acciones (alta/baja/modificación, login/logout, cobros, anulaciones...). Cada fila lleva una copia del usuario que la generó (`usuario_id/nombre/username/rol`) tomada siempre del lado servidor, más `ip`. Como `usuarios`, no se destruye en una reinstalación. Puede desactivarse por completo con `log_actividad => false` en `config.php` (entonces esta tabla ni se crea).

### `plantillas`
Catálogo de plantillas `.docx` que usa el motor de generación de documentos (`assets/php/plantillas.php`). `fichero` es el nombre del `.docx` real dentro de `uploads/plantillas/` (con UUID, no el nombre original subido). `tipo_documento` filtra qué plantillas se ofrecen desde cada pantalla (`contrato_arrendamiento`, `otro`, etc.). Borrado lógico propio (`eliminado`/`eliminado_en`), igual que propietarios/fincas/inmuebles/inquilinos.

### `doc_secuencias`
El contador atómico de numeración documental. Clave primaria compuesta `(tipo, periodo)`:
- `tipo`: `REC` (recibo), `RER` (recibo rectificativo), `FAC` (factura), `RET` (factura rectificativa) — es un valor libre, no un `ENUM`; cualquier prefijo de 3-4 letras en mayúsculas es válido.
- `periodo` (`CHAR(6)`): **anual `AAAA` (4 caracteres) para los 4 tipos** (`REC`/`RER`/`FAC`/`RET`) desde julio 2026 — antes `REC`/`RER` eran mensuales (`AAAAMM`, 6 caracteres); ese formato ya no se genera pero sigue existiendo en filas históricas (documentos emitidos antes de la migración, nunca renumerados). La columna se mantiene `CHAR(6)` sin cambio de esquema: un valor de 4 caracteres cabe sin problema y MySQL lo devuelve limpio al leerlo (retira el relleno de espacios salvo con el `sql_mode` no habitual `PAD_CHAR_TO_FULL_LENGTH`), verificado empíricamente contra MySQL 5.7. La fuente del año difiere por tipo: `REC` → `periodo_desde` del propio recibo; `RER` → `periodo_desde` del recibo **original** que se rectifica; `FAC`/`RET` → `fecha_emision` del propio documento. Nunca la fecha actual del servidor.
- `ultimo`: el último número de secuencia entregado para ese `tipo`+`periodo`. **Nunca se reutiliza un número ya entregado**, aunque el documento correspondiente termine anulado.

Se incrementa exclusivamente desde `assets/php/api.php`, acción `nextNumeroDoc`, dentro de una transacción con `SELECT ... FOR UPDATE` — es lo único de todo el esquema que tiene protección real contra condiciones de carrera a nivel de fila (verificado con hasta 5 peticiones concurrentes para el mismo `tipo`+`periodo`: cero duplicados, numeración correlativa). El número final que ve el usuario se construye como `{PREFIJO}-{periodo}-{ultimo con 5 dígitos}` (ej. `REC-2026-00059`, `FAC-2026-00003`), pero ese texto formateado no se guarda aquí: se guarda en la propia tabla del documento (`recibos.numero_recibo`, `facturas.numero_factura`). `nextNumeroDoc` exige `periodo` con formato `AAAA` para los 4 tipos y rechaza con `400` explícito cualquier otro formato (incluido el histórico `AAAAMM` de 6 dígitos) — nunca ajusta ni recorta el valor recibido para "hacerlo válido".

**No hay ninguna relación automática entre esta tabla y `recibos`/`facturas`**: si una llamada a `nextNumeroDoc` reserva un número y la posterior inserción del documento nunca llega a completarse, ese número queda perdido para siempre (huella real de un fallo a medio camino — es justo lo que corrigió la sesión de auditoría de 2026-07-10, ver `README.md`). Instalaciones existentes con documentos históricos en formato mensual reciben, en el primer arranque tras la actualización, una fila anual nueva por cada `(tipo, año)` sin renumerar nada: el valor de arranque es `GREATEST(SUM(ultimo) de las filas mensuales de ese año, COUNT(*) de documentos reales de ese tipo/año)`, para no reutilizar nunca un número que ya pudo haberse reservado o emitido (ver `README.md`, changelog).

---

## 3. Entidades de negocio con borrado lógico

`propietarios`, `fincas`, `inmuebles`, `inquilinos` comparten el mismo patrón:
- `eliminado` (`TINYINT`, 0/1) + `eliminado_en` (`DATETIME`, NULL si no está eliminado).
- "Eliminar" desde la aplicación nunca hace `DELETE`: hace `UPDATE ... SET eliminado=1, eliminado_en=NOW()`.
- El backend bloquea el borrado si existen dependientes activos (`comprobarDependencias()` en `api.php`): no se puede eliminar una finca con inmuebles, ni un inmueble con contratos, etc.
- Las lecturas normales de la aplicación (`action=getAll`) filtran `eliminado=0`, así que un registro eliminado desaparece de listados/selects/informes sin dejar de existir en la tabla (para no romper el histórico de documentos que lo referencian).

`propietarios.irpf` es un campo de texto de 1 carácter (no documentado como flag S/N o porcentaje en el propio esquema — revisar el uso real en `assets/js/propietarios.js` si se va a tocar).

---

## 4. `contratos`

Columnas económicas: `renta_base`, `iva_pct`, `irpf_pct`, `fianza`, `dia_pago`. Columnas de vigencia: `fecha_inicio` (obligatoria), `fecha_fin` (duración pactada — **no** se usa para bloquear la generación de recibos: un contrato puede seguir `estado='activo'` más allá de su `fecha_fin` nominal mientras espera renovación formal), `fecha_baja`/`motivo_baja`/`obs_baja` (solo si se ha dado de baja anticipadamente).

`estado`: `activo` | `finalizado` | `rescindido`. Es el único campo que el backend usa hoy para decidir si un contrato puede generar recibos nuevos (ver `validarDatos()`, caso `recibos`, en `api.php`).

`revision`: texto libre pero con valores con significado especial para el resto de la aplicación — `IPC`, `IRAV` (activan el aviso de revisión anual pendiente, `assets/js/extras.js`), `Fija` y `Sin revision` (nunca lo activan). `ipc_anio_aplicado` registra el último año en que se aplicó la subida, para no repetir el aviso el mismo año.

`nombre_fiador`/`nif_fiador`/`direccion_fiador` y `motivo_temporada` son opcionales, usados solo si aplica (fiador solidario / contrato de temporada &lt; 1 año).

No hay ninguna restricción en el esquema que impida dos contratos `activo` para el mismo `inmueble_id` a la vez — esa regla ("solo un contrato activo por inmueble") solo se valida hoy en el frontend (`assets/js/contratos.js`), no en `api.php`.

### `contratos_inq_sec`
Inquilinos secundarios (co-firmantes) de un contrato, sin impacto en la lógica de negocio (solo aparecen en documentos generados). `orden` controla el orden de aparición.

### `historial_rentas`
Registro cronológico de cada cambio de renta de un contrato (`renta_anterior` → `renta_nueva`, `tipo_revision`, `porcentaje`). Se alimenta desde `assets/js/contratos.js` al aplicar una subida IPC/IRAV/fija. **No se consulta nunca** al generar un recibo: la generación de recibos (individual o en lote) siempre usa la renta *actual* del contrato, no la vigente en el período histórico que se está generando — limitación conocida, ver `README.md` §21.

---

## 5. `recibos` — el documento mensual de alquiler

Columnas de identidad: `contrato_id`/`inquilino_id`/`inmueble_id` (copiados del contrato en el momento de crear el recibo, no se recalculan después), `numero_recibo` (texto formateado, único), `numero_seq` (el entero crudo devuelto por `doc_secuencias`).

Columnas de período — **tres representaciones del mismo período, que deben mantenerse coherentes entre sí** (ver `assets/js/helpers.js`: `periodoLabel()`/`periodoYYYYMM()`/`periodoPrimerDia()`/`periodoUltimoDia()`, usadas por los dos flujos de generación para garantizarlo):
- `periodo_desde` / `periodo_hasta` (`DATE`): primer y último día del mes que cubre el recibo. Es la representación **canónica**, la que usa el backend para calcular `periodo_key`.
- `concepto_periodo` (texto libre, ej. `"Junio 2026"`): la etiqueta legible que ve el usuario. Editable a mano, no se usa para ninguna lógica de negocio desde la corrección de julio 2026 (antes era la única clave de detección de duplicados, con los problemas ya documentados en `README.md`).
- `fecha_emision` / `fecha_limite` (`DATE`): cuándo se emite el documento y cuándo vence el pago — independientes del período que cubre (un recibo de junio puede emitirse en julio).

Columnas económicas: `renta_base`, `importe_iva`, `importe_irpf`, `importe_total` (= renta + iva − irpf), `importe_pagado`. `pagos` es una columna `TEXT` con un **array JSON** de cobros (`[{fecha, importe, metodo, cuenta}]`); no existe una tabla `cobros` separada — el estado se deriva comparando `importe_pagado` contra `importe_total`.

`estado`: `pendiente` | `parcial` | `cobrado` | `anulado` | `rectificativo`. Un recibo `rectificativo` es en sí mismo el documento generado al anular otro (serie `RER`); `recibo_rectificado_id` apunta al recibo original que rectifica (`NULL` en todos los demás casos).

`factura_id`: `NULL` hasta que se genera una factura legal desde este recibo; a partir de ahí apunta a la factura vigente (puede cambiar a lo largo del tiempo si esa factura se rectifica y se sustituye).

`periodo_key` (añadida 2026-07-10): `VARCHAR(20)` con formato `"<contrato_id>-<AAAAMM>"`, calculada **siempre en el servidor** (nunca confía en lo que envíe el cliente), y solo para recibos "ordinarios" (`estado` no es `anulado` ni `rectificativo`, y `recibo_rectificado_id IS NULL`) — en cualquier otro caso queda `NULL`. Tiene una `UNIQUE KEY` real (`uq_recibos_periodo_key`): es la protección efectiva contra dos recibos ordinarios del mismo contrato y mes, incluida frente a condiciones de carrera (dos peticiones simultáneas), porque MySQL la aplica de forma atómica en el propio `INSERT`/`UPDATE`. Como MySQL permite múltiples `NULL` en una `UNIQUE KEY`, los recibos rectificativos/anulados nunca compiten entre sí por esta clave, y anular un recibo (que pasa su `periodo_key` a `NULL`) libera el período para volver a emitir uno nuevo.

`aviso_recibo` es un flag de presentación (si el pie del recibo muestra el aviso de "justificante de pago"), sin relación con `contratos.aviso_recibo`.

---

## 6. `facturas` — documento legal (RD 1619/2012)

Cada factura nace **siempre** desde un recibo existente (`recibo_id`) y congela una copia de los datos fiscales del emisor y el cliente en el momento de la emisión (`emisor_*`, `cliente_*`) — así una factura antigua conserva los datos que tenía el inquilino/empresa cuando se emitió, aunque luego cambien en `inquilinos`/`empresa`. `recibo_id` puede ser `NULL` solo en una factura rectificativa (no tiene un recibo propio: rectifica otra factura).

`numero_factura` (único) y `serie` (`FAC` normal, `RET` rectificativa) usan el mismo mecanismo atómico de `doc_secuencias` que los recibos, con período **anual** (`FAC-AAAA-NNNNN`/`RET-AAAA-NNNNN`): el contador continúa durante todo el año y solo reinicia a `00001` en año nuevo, y el año sale siempre de `fecha_emision` de la propia factura — nunca de `periodo_desde`/`periodo_hasta` (copiados del recibo de origen) ni del `concepto_periodo` del recibo. `tipo_factura`: `F1` (normal) o `R1` (rectificativa).

`estado`: `emitida` | `rectificada` | `anulada`. `factura_rectificada_id` apunta a la factura original cuando esta fila es una rectificativa (`RET`).

Columnas `hash_factura`/`hash_anterior`/`qr_url`/`verifactu_estado`/`verifactu_respuesta`: soporte para VERI*FACTU (RD 1007/2023) — cadena de hashes SHA-256 encadenados por factura, encaminado hacia la AEAT. Inertes mientras `configuracion.verifactu_activo = '0'` (valor por defecto).

El esquema **no tiene ninguna restricción `UNIQUE`/`FK` sobre `recibo_id`** pese a lo que sugiere un comentario histórico en `install.php`: nada a nivel de base de datos impide dos facturas para el mismo recibo; hoy esa protección solo existe (de forma incompleta) en JavaScript.

---

## 7. Mecánica de numeración documental — resumen operativo

```
1. Cliente pide "el siguiente número" a assets/php/api.php?action=nextNumeroDoc
     (tipo=REC|RER|FAC|RET, periodo=?, prefijo)
     → REC: periodo = AAAA del periodo_desde DEL PROPIO RECIBO.
     → RER: periodo = AAAA del periodo_desde DEL RECIBO ORIGINAL que se rectifica
            (nunca de la fecha en que se ejecuta la anulación).
     → FAC/RET: periodo = AAAA del AÑO DE fecha_emision DEL DOCUMENTO.
   Los 4 tipos son anuales: nunca se sustituye 'periodo' por la fecha actual del
   servidor si falta el dato de origen necesario.
2. Servidor: valida que 'periodo' tenga formato AAAA (año 2000-2099) para
   cualquiera de los 4 tipos — cualquier otro formato (incluido el histórico
   AAAAMM de 6 dígitos) se rechaza con 400 explícito, nunca se ajusta en
   silencio. Tampoco hay fallback a la fecha actual del servidor si falta
   'periodo'.
   BEGIN; SELECT ultimo FROM doc_secuencias WHERE tipo=? AND periodo=? FOR UPDATE;
   ultimo+1; UPDATE (o INSERT si no existía la fila); COMMIT.
3. Cliente arma numero_recibo/numero_factura = "{PREFIJO}-{periodo}-{seq con 5 dígitos}"
   (ej. REC-2026-00059 o FAC-2026-00003) y lo envía en el INSERT de
   recibos/facturas junto con periodo_desde/periodo_hasta.
4. Servidor (acción 'save'): para 'recibos', calcula periodo_key a partir de
   contrato_id + periodo_desde (SIEMPRE mensual, AAAAMM — concepto distinto de
   la numeración del documento, no cambia con esta migración) y confía esa
   clave (no lo que mande el cliente) a la UNIQUE KEY para rechazar duplicados
   de forma atómica. Las facturas no tienen un equivalente a periodo_key:
   UNIQUE(numero_factura) es suficiente, porque una factura es 1:1 con su
   recibo de origen (o con la factura que rectifica), no "un documento por
   contrato+período" como un recibo.
```

El paso 2 y el paso 3 son **dos peticiones HTTP independientes, no una transacción conjunta**: la reserva del número está protegida en sí misma, pero nada obliga a que el número reservado termine usándose — de ahí que un número pueda quedar "huérfano" (reservado pero sin documento) si el segundo paso nunca llega a completarse.

Las 4 series (`REC`, `RER`, `FAC`, `RET`) son completamente independientes entre sí (claves primarias distintas en `doc_secuencias`): generar/anular un documento de un tipo no consume ni afecta al contador de los otros 3, aunque compartan año.

---

## 8. Índices y ausencia de `FOREIGN KEY`

Todas las columnas `*_id` (`contrato_id`, `inquilino_id`, `inmueble_id`, `propietario_id`, `finca_id`, `recibo_id`...) son `INT` sin restricción `REFERENCES`. La consistencia la garantiza exclusivamente el código PHP:
- `validarDatos()` en `api.php` comprueba existencia/estado del contrato al crear un recibo.
- `comprobarDependencias()` bloquea el borrado lógico si hay dependientes.
- No hay `ON DELETE CASCADE` en ningún sitio (coherente con que nada se borra físicamente salvo `configuracion`/`contratos_inq_sec`/`empresa`).

Índices existentes: claves foráneas de uso frecuente (`idx_*_contrato`, `idx_*_inquilino`, `idx_*_inmueble`, `idx_*_estado`, `idx_*_eliminado`) y, desde 2026-07-10, `idx_recibos_fecha_emision`/`idx_recibos_periodo_desde` para los filtros por fecha de informes y dashboard.

---

## 9. Compatibilidad e instalación

Todo el esquema es compatible con MySQL 5.7 y MariaDB (sin `CHECK` constraints, sin `JSON` nativo — los campos JSON como `recibos.pagos` son `TEXT` serializado a mano en PHP, sin `GENERATED COLUMN`). `assets/php/install.php` recrea todas las tablas de negocio en cada instalación limpia/con ejemplos (`DROP TABLE` + `CREATE TABLE`); `usuarios` y `log_actividad` usan `CREATE TABLE IF NOT EXISTS` y nunca se destruyen. `assets/php/api.php` aplica además auto-migraciones idempotentes (`ALTER TABLE ADD COLUMN`/`ADD INDEX` envueltas en comprobación previa o `try/catch`) para instalaciones existentes que se actualizan sin pasar por `install.php`.
