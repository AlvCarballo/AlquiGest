# Sistema de usuarios, autenticación y permisos

**Fecha:** 2026-07-10
**Versión afectada:** AlquiGest v3.0.1
**Archivos principales:** `assets/php/auth.php` (nuevo), `login.php` (nuevo), `assets/js/usuarios.js` (nuevo), `AlquiGest.php`, `assets/php/api.php`, `assets/php/install.php`, `assets/php/plantillas.php`, `assets/php/verifactu.php`, `assets/php/email.php`, `assets/php/export.php`, `assets/php/import.php`, `assets/php/pdf_download.php`

---

## 1. Resumen del cambio

AlquiGest funcionaba sin ningún concepto de usuario: `requireLocalhost()` era la única barrera, y cualquier proceso que pudiera alcanzar `http://127.0.0.1/...` tenía acceso total, incluido `install.php` (instalación limpia, restauración, backups). Se ha añadido un sistema de autenticación real con dos roles (`admin` y `user`), sesiones PHP seguras, protección CSRF, y un control de acceso de cuatro niveles para `install.php` que permite seguir instalando la aplicación por primera vez sin bloquear a nadie, pero protege completamente el instalador en cuanto existe al menos un usuario.

## 2. Arquitectura de autenticación

- **`assets/php/auth.php`** — núcleo único de autenticación, incluido desde cualquier script que necesite sesión: `api.php`, `install.php`, `AlquiGest.php`, `login.php`, `plantillas.php`, `verifactu.php`, `email.php`, `export.php`, `import.php`, `pdf_download.php`.
- Sesiones PHP nativas (`session_bootstrap()`): cookies `HttpOnly` + `SameSite=Lax`, nombre de sesión propio (`ALQUIGEST_SESID`), cierre automático tras 60 minutos de inactividad (`AUTH_SESSION_IDLE_SECONDS`).
- **Sin fijación de sesión**: `session_regenerate_id(true)` se ejecuta en cada login correcto.
- **Revalidación en caliente**: cada función `requireLogin*()` vuelve a consultar la tabla `usuarios` (no se fía solo de lo que hay en `$_SESSION`) para comprobar que el usuario sigue `activo=1` y no ha cambiado de rol. Si un admin desactiva a alguien mientras tiene sesión abierta, su siguiente petición (aunque sea a mitad de sesión) es rechazada — **verificado en pruebas** (ver §13).
- **Contraseñas**: `password_hash()` / `password_verify()` (bcrypt), nunca texto plano ni hashes hechos a mano.
- **CSRF**: token de 32 bytes aleatorios por sesión (`csrfToken()` / `csrfValid()`), comprobado con `hash_equals()`. Se exige en todos los formularios POST de `install.php` y en las acciones `saveUsuario`/`deleteUsuario` de la API.
- **Ningún permiso se decide en el navegador.** El JavaScript (`window.AG_USER`) solo se usa para adaptar la interfaz (ocultar botones/menús); la validación real ocurre siempre en PHP, y así se ha verificado explícitamente (ver §13, intentos de bypass).

## 3. Tabla de usuarios

```sql
CREATE TABLE usuarios (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    nombre         VARCHAR(150) NOT NULL,
    email          VARCHAR(150) DEFAULT '',
    username       VARCHAR(60)  NOT NULL,
    password_hash  VARCHAR(255) NOT NULL,
    rol            VARCHAR(20)  NOT NULL DEFAULT 'user',   -- 'admin' | 'user'
    activo         TINYINT(1)   NOT NULL DEFAULT 1,
    ultimo_login   DATETIME     NULL DEFAULT NULL,
    creado_en      DATETIME     DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    eliminado_en   DATETIME     NULL DEFAULT NULL,
    UNIQUE KEY uq_usuarios_username (username),
    INDEX idx_usuarios_rol (rol),
    INDEX idx_usuarios_activo (activo)
);
```

No existía ninguna tabla parecida antes de este cambio. Sigue el mismo patrón de borrado lógico ya usado en el resto de la aplicación (ver `ANALISIS_BORRADO_LOGICO_E_INTEGRIDAD.md`): un usuario **nunca** se borra físicamente; "Eliminar" marca `eliminado_en = NOW()` y `activo = 0`. `activo` permite además una suspensión reversible (desactivar/reactivar) sin llegar a "eliminar".

**Salvaguarda anti-bloqueo:** tanto al editar como al eliminar, el backend impide que el único administrador activo se quite el rol, se desactive o se elimine a sí mismo si no queda ningún otro admin activo (`api.php`, acción `saveUsuario`/`deleteUsuario`).

## 4. Roles

| Rol | Descripción |
|---|---|
| `admin` | Acceso completo a la aplicación, a `install.php` completo (instalación limpia/con ejemplos, restauración, backups) y a la gestión de usuarios. |
| `user` | Acceso completo a la aplicación normal (propietarios, contratos, recibos, facturas, informes...). Dentro de `install.php` solo ve y puede usar la sección de copia de seguridad. No puede instalar, restaurar, ejecutar migraciones destructivas ni gestionar usuarios. |

## 5. Matriz de permisos

| Rol | App normal | Install completo | Backup en install | Gestión usuarios | Logs |
|---|---|---|---|---|---|
| admin | ✅ | ✅ | ✅ | ✅ (crear/editar/desactivar/eliminar) | ✅ ve todos los usuarios, puede filtrar |
| user | ✅ | ❌ (bloqueado en backend) | ✅ | ❌ (403 backend) | ✅ ve el historial, no gestiona usuarios |
| sin sesión | ❌ (redirige a login) | ❌ (redirige a login) | ❌ | ❌ | ❌ |

## 6. Comportamiento especial de primera instalación

`install.php` calcula `esPrimeraInstalacion()` (ver `auth.php`): true si no existe la base de datos, no existe la tabla `usuarios`, o existe pero tiene 0 filas (ni siquiera inactivas/eliminadas).

- **No existe BD / tabla `usuarios`** → se muestra el formulario de instalación de siempre (ZipArchive, backup, restaurar, instalación limpia/con ejemplos), sin pedir login. Al ejecutar una instalación se crea también la tabla `usuarios` (vacía).
- **Existe la tabla `usuarios` pero con 0 filas** → `install.php` muestra **solo** la pantalla "Crear el primer administrador" (nombre, usuario, email, contraseña ×2). Un `<details>` colapsable permite volver a la instalación completa si hace falta rehacer la base de datos antes de crear el admin. En cuanto se crea, la aplicación exige login a partir de ese momento.
- **Ya existen usuarios** → `install.php` exige sesión (redirige a `login.php?next=...`) y aplica la matriz de permisos de §5.

## 7. Protección de install.php

Todo el control de acceso vive en las primeras líneas de `install.php` (antes de cualquier salida HTML):

1. Calcula `$primeraInstalacion`, revalida la sesión contra la BD si no lo es, y redirige a `login.php` si no hay sesión válida.
2. `$esAdmin = $primeraInstalacion || rol === 'admin'`. `$soloBackup = !primera && !admin`.
3. **Bloqueo duro de modos**: si `$mode` no es `backup`/`backup_data` y el usuario no es admin, se anula `$mode` a `''` antes de que ningún bloque `if ($mode === ...)` pueda ejecutarse — aunque se manipule el POST directamente con curl/DevTools (verificado, ver §13).
4. **CSRF**: cualquier POST con `$mode` no vacío exige `_csrf` válido de la sesión actual.
5. El HTML de instalación/backup/restaurar/migraciones se extrajo a una única función (`renderBloqueInstalacionCompleta()`) para no duplicar el marcado entre el caso "primera instalación" y el caso "admin autenticado".

## 8. Modo "Backup limitado" (rol `user`)

Dentro de `install.php`, un usuario con rol `user` ve únicamente:
- Estado de ZipArchive/Excel (solo lectura, sin el botón que modifica `php.ini`).
- Sección "Copia de seguridad" con los dos botones (`Completa` y `Solo datos`).
- Un aviso explicando que el resto de acciones requieren un administrador.
- Enlace para volver a la aplicación y para cerrar sesión.

Todo lo demás (ZipArchive fix, restaurar SQL, instalación limpia, instalación con datos) está oculto **y** bloqueado en el backend (paso 3 de §7), no solo oculto visualmente.

## 9. Cambios en logs/actividad

- Tabla `log_actividad` ampliada con `usuario_id`, `usuario_nombre`, `usuario_username`, `usuario_rol`, `ip` (migración idempotente tanto en `install.php` como en el auto-arranque de `api.php`).
- `logActividad()` (en `auth.php`) centraliza la escritura: toma el usuario **siempre del lado servidor** (sesión), nunca de lo que envíe el cliente, e incluye la IP.
- Nuevas acciones registradas automáticamente: `login_correcto`, `login_fallido` (incluye usuario inactivo intentando entrar), `logout`, `usuario_creado`, `usuario_editado`, `usuario_eliminado`.
- **Alta/modificación genérica de negocio**: `api.php`, acción `save`, registra `alta_<tabla>` / `modificacion_<tabla>` para `propietarios`, `fincas`, `inmuebles`, `inquilinos`, `contratos` (antes no se registraba nada al crear/editar estas entidades). `recibos`/`facturas`/`contratos` de baja siguen usando sus eventos específicos ya existentes (`cobro`, `factura_generada`, `baja_contrato`...) para no duplicar registros.
- Pantalla **Actividad**: nueva columna "Usuario" y nuevo filtro desplegable "Usuario" (poblado con los usuarios distintos presentes en los resultados cargados, sin depender de un endpoint solo-admin). Los eventos sin usuario (login fallido con usuario inexistente, o el registro histórico previo a este cambio) muestran "Sistema".
- Widget "Últimas actividades" del Dashboard también actualizado con los nuevos tipos e icono/etiqueta.

## 10. Archivos modificados

| Archivo | Cambio realizado | Motivo | Estado |
|---|---|---|---|
| `assets/php/auth.php` | **Nuevo.** Sesión segura, login/logout, CSRF, revalidación, `logActividad()`, helpers `requireLogin*`/`requireRole*`/`canAccessInstall`/`canAccessBackupOnly` | Núcleo único de autenticación reutilizado por toda la app | ✅ |
| `login.php` | **Nuevo.** Página de login (self-contained, sin JS de la SPA) | Punto de entrada de autenticación | ✅ |
| `assets/js/usuarios.js` | **Nuevo.** Pantalla de gestión de usuarios (listar/crear/editar/eliminar) | UI admin-only para gestionar cuentas | ✅ |
| `AlquiGest.php` | Gate de acceso al inicio (`requireLoginWeb`), inyecta `window.AG_USER`/`AG_CSRF`, menú de usuario en cabecera, nav "Usuarios" (oculto por CSS, mostrado por JS solo si admin), bump de versión de scripts/CSS cacheados | Proteger la SPA y exponer sesión al JS | ✅ |
| `assets/php/api.php` | Gate de sesión global (excepto `login`/`check`), nuevas acciones `login`/`logout`/`me`/`listUsuarios`/`saveUsuario`/`deleteUsuario`, migración de `usuarios` y columnas de `log_actividad`, logging genérico en `save`/`delete`, `getLog` con filtro por usuario | Backend como única fuente de verdad de permisos | ✅ |
| `assets/php/install.php` | Control de acceso de 4 casos, modo `create_admin`, CSRF en todos los formularios, HTML extraído a `renderBloqueInstalacionCompleta()`, migración de `usuarios`/`log_actividad` (nunca destructiva) | Proteger instalación/backup/restauración | ✅ |
| `assets/php/plantillas.php` | `requireLoginApi()` tras conectar a BD | Exigir sesión en el motor de plantillas | ✅ |
| `assets/php/verifactu.php` | `requireLoginApi()` tras conectar a BD | Exigir sesión en la integración AEAT | ✅ |
| `assets/php/email.php` | `requireLoginApi()` tras conectar a BD | Exigir sesión en el envío de emails | ✅ |
| `assets/php/export.php` | `requireLoginApi()` tras conectar a BD | Exigir sesión en la exportación Excel | ✅ |
| `assets/php/import.php` | `requireLoginApi()` con conexión dedicada | Exigir sesión en la importación CSV/Excel | ✅ |
| `assets/php/pdf_download.php` | `requireLoginApi()` con conexión dedicada | Exigir sesión en el relay de PDF | ✅ |
| `assets/js/helpers.js` | Ruta `usuarios` añadida a `navigate()` | Enrutado de la nueva pantalla | ✅ |
| `assets/js/ux.js` | Menú de usuario (avatar, rol, cerrar sesión), visibilidad del nav "Usuarios" | UI de sesión en cabecera | ✅ |
| `assets/js/actividad.js` | Columna y filtro "Usuario", nuevas etiquetas de tipo de acción | Mostrar autoría en el historial | ✅ |
| `assets/js/dashboard.js` | Nuevos iconos/etiquetas de actividad, columna usuario en el widget | Coherencia con el historial completo | ✅ |
| `assets/css/main.css` | Estilos del menú de usuario (`#user-menu-*`) | Cabecera con soporte modo oscuro | ✅ |

## 11. Migraciones aplicadas

- **Tabla `usuarios`**: `CREATE TABLE IF NOT EXISTS` en `install.php` (fuera del bucle destructivo de reinstalación — nunca se borra) y en el auto-arranque de `api.php` (instalaciones existentes). **Verificado en caliente** contra la base de datos real de desarrollo: la tabla se creó sin tocar ningún dato de negocio.
- **Columnas de `log_actividad`**: `usuario_id`, `usuario_nombre`, `usuario_username`, `usuario_rol`, `ip`, añadidas con `ALTER TABLE ... ADD COLUMN` solo si no existen ya (comprobación `SHOW COLUMNS`), tanto en `install.php` como en `api.php`. **Verificado en caliente**: 0 filas perdidas, columnas creadas correctamente sobre la tabla ya poblada por la instalación anterior.
- Ninguna migración de este cambio es destructiva ni requiere intervención manual: basta con visitar cualquier página una vez para que se apliquen.

## 12. Riesgos

- **Sesión basada en cookies de servidor de archivos PHP por defecto** (`session.save_path`): en un hosting compartido mal configurado esto podría ser sensible; en el uso previsto (MAMP/XAMPP local) no es un riesgo real.
- **Sin límite de intentos de login (rate limiting)**: un ataque de fuerza bruta local podría probar contraseñas sin cooldown. Mitigado parcialmente porque la aplicación es de uso exclusivamente local (`requireLocalhost()`), pero queda como mejora pendiente si se llegara a exponer a una red mayor.
- **Recuperación de contraseña**: no existe flujo de "olvidé mi contraseña" (no hay envío de email de recuperación). Si el único admin pierde su contraseña, hay que restablecerla directamente en la base de datos (`UPDATE usuarios SET password_hash = ...` con un hash generado por `password_hash()`).
- **Compatibilidad con instalaciones muy antiguas**: si alguna instalación previa tuviera ya una tabla llamada `usuarios` con un esquema distinto (muy improbable, no es un nombre usado antes en este proyecto), la migración fallaría de forma segura (con excepción capturada) en vez de corromper datos.

## 13. Pruebas realizadas

**Backend (curl, sin navegador):**
1. `getAll`/`delete` sin sesión → `401 AUTH_REQUIRED`. `check` sin sesión → `200 OK` (público, sin datos de negocio).
2. Login con contraseña incorrecta → `401` con mensaje claro, registrado como `login_fallido`.
3. Login con usuario desactivado → rechazado con mensaje específico, registrado.
4. `install.php` con `mode=clean` vía POST directo, autenticado como `user` → bloqueado (`No tienes permisos...`), sin ejecutar nada; comprobado que `propietarios`/`usuarios` no cambiaron de tamaño.
5. `listUsuarios`/`saveUsuario` llamados directamente como `user` autenticado (con CSRF válido de su propia sesión) → `403 FORBIDDEN`.
6. Usuario desactivado en caliente mientras tenía sesión abierta → su siguiente petición (`getAll`) devuelve `401` inmediatamente, sin esperar a que expire la sesión.

**Navegador (Playwright), sobre la base de datos real de desarrollo:**
7. Primera instalación (tabla `usuarios` recién migrada, 0 filas): `install.php` mostró únicamente el formulario "Crear el primer administrador".
8. CSRF corrupto (probado accidentalmente durante la sesión de pruebas al manipular el DOM) → bloqueado con "Token de seguridad inválido o caducado", confirmando que la protección funciona incluso ante un fallo humano real, no solo en el caso ideal.
9. Alta del primer admin → redirección a `login.php`, login correcto → `AlquiGest.php`, cabecera muestra "Administrador Principal".
10. `install.php` como admin → acceso completo, con barra de sesión y enlace de cierre de sesión.
11. Pantalla **Usuarios**: creación de un segundo usuario con rol `user` ("Gestor de Prueba"). Se detectó y corrigió un bug real: `listUsuarios` no convertía `id`/`activo` a entero (PDO los devuelve como string), por lo que la comparación "es tu propio usuario" fallaba y el botón "Eliminar" aparecía sobre la propia cuenta del admin — corregido con un cast explícito en el backend.
12. Se detectó y corrigió un segundo bug real: el atributo `pattern` del campo "usuario" en el formulario de alta de usuario tenía un guion sin escapar dentro de la clase de caracteres (`[a-zA-Z0-9._-]`), lo que provoca `SyntaxError: Invalid character in character class` en navegadores con motores de regex en modo `v` — corregido escapando el guion (`._\\-`), igual que ya estaba en el formulario de `install.php`.
13. Se detectaron y corrigieron además varios ficheros JS servidos con una versión de caché (`?v=...`) desactualizada respecto a ediciones de esta sesión y de la sesión anterior (borrado lógico) — todas las versiones se han sincronizado.
14. Logout → redirección a `login.php`; intento de volver a `AlquiGest.php` sin sesión → redirigido de nuevo a login.
15. Login como "Gestor de Prueba" (rol `user`) → cabecera correcta, nav "Usuarios" oculto, `install.php` mostró el modo backup limitado (verificado: sin restaurar, sin instalación limpia, con backup funcional que descargó el `.sql`).
16. Pantalla Actividad: columna y filtro "Usuario" mostrando correctamente cada evento (login/logout/creación de usuario) atribuido al usuario real, con "Sistema" para el evento sin sesión (login fallido de un usuario inexistente).
17. Modo oscuro seguía funcionando correctamente con el nuevo menú de usuario tras todos los cambios (comprobado visualmente).
18. Datos de negocio (propietarios, fincas, inmuebles, contratos) verificados intactos (mismo recuento activo) antes y después de toda la batería de pruebas.

## 14. Pendientes

- No hay límite de intentos de login (rate limiting / bloqueo temporal tras varios fallos). Recomendado si la aplicación llegara a exponerse fuera de localhost.
- No hay flujo de recuperación de contraseña por email (no hay SMTP de sistema separado del de la empresa). Restablecimiento manual vía base de datos si hace falta.
- No hay pantalla de "editar mi propio perfil" separada de la gestión general de usuarios (un admin edita su propia cuenta desde la misma pantalla de Usuarios).
- El timeout de sesión por inactividad (60 minutos) está implementado pero no se ha probado en vivo esperando el tiempo real (validado por lectura de código, no por prueba cronometrada).
