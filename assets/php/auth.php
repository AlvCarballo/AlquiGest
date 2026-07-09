<?php
// ============================================================
//  AlquiGest – Autenticación y control de acceso (usuarios/roles)
//
//  Incluir con: require __DIR__ . '/auth.php'; (o ruta relativa)
//  seguido de session_bootstrap() ANTES de cualquier salida HTML.
//
//  Roles: 'admin' (acceso total, incl. install.php completo) y
//         'user'  (aplicación normal + solo backup dentro de install.php).
//
//  Funciones principales:
//    session_bootstrap()            → sesión segura + timeout de inactividad
//    authConnect($cfg)               → PDO independiente (para páginas sin BD propia)
//    esPrimeraInstalacion($cfg)      → true si no hay BD, tabla usuarios o usuarios
//    currentUser()                   → usuario de la sesión (rápido, sin BD)
//    requireLoginWeb($pdo,$url)      → exige sesión válida en páginas HTML (redirige)
//    requireLoginApi($pdo)           → exige sesión válida en endpoints JSON (401)
//    requireRoleWeb($user,$rol)      → exige rol en páginas HTML (403)
//    requireRoleApi($user,$rol)      → exige rol en endpoints JSON (403)
//    canAccessInstall($user)         → true solo si admin
//    attemptLogin($pdo,$u,$p)        → intenta autenticar, gestiona intentos
//    doLogout($pdo,$user)            → cierra sesión de forma segura
//    csrfToken() / csrfValid($t)     → protección CSRF basada en sesión
//    logActividad(...)               → registra una acción con usuario/IP
// ============================================================

const AUTH_SESSION_IDLE_SECONDS = 3600; // 60 minutos de inactividad → cierre automático
const AUTH_ROLES = ['admin', 'user'];

// ── Arranque de sesión segura ─────────────────────────────────
// Cookies HttpOnly + SameSite=Lax, regeneración periódica y expiración
// por inactividad. Debe llamarse antes de cualquier echo/HTML.
function session_bootstrap(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_name('ALQUIGEST_SESID');
    session_start();

    $ahora = time();
    // Cierre por inactividad: si ha pasado demasiado tiempo desde la última
    // petición, se destruye la sesión y se empieza una nueva vacía.
    if (!empty($_SESSION['_last_activity']) && ($ahora - $_SESSION['_last_activity']) > AUTH_SESSION_IDLE_SECONDS) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_regenerate_id(true);
    }
    $_SESSION['_last_activity'] = $ahora;
}

// ── Conexión PDO independiente ────────────────────────────────
// Para páginas (AlquiGest.php, install.php) que no crean ya su propia
// conexión con el mismo patrón que api.php. Lanza PDOException si falla.
function authConnect(array $cfg): PDO {
    return new PDO(
        "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset={$cfg['charset']}",
        $cfg['user'], $cfg['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

// Conexión sin seleccionar base de datos (para comprobar si la BD existe siquiera).
function authConnectServer(array $cfg): PDO {
    return new PDO(
        "mysql:host={$cfg['host']};port={$cfg['port']};charset={$cfg['charset']}",
        $cfg['user'], $cfg['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

// ── Comprobar si una tabla existe ─────────────────────────────
function tablaExiste(PDO $pdo, string $tabla): bool {
    $st = $pdo->prepare("SHOW TABLES LIKE ?");
    $st->execute([$tabla]);
    return (bool)$st->fetch();
}

// ── Estado de "primera instalación" ───────────────────────────
// true si: no existe la BD, o no existe la tabla `usuarios`, o existe
// pero no tiene ningún registro (ni siquiera inactivo/eliminado).
// En ese estado install.php es accesible sin login (ver install.php).
function esPrimeraInstalacion(array $cfg): bool {
    try {
        $pdoServer = authConnectServer($cfg);
        $st = $pdoServer->prepare("SHOW DATABASES LIKE ?");
        $st->execute([$cfg['name']]);
        if (!$st->fetch()) return true; // ni siquiera existe la BD

        $pdo = authConnect($cfg);
        if (!tablaExiste($pdo, 'usuarios')) return true;

        $n = (int)$pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
        return $n === 0;
    } catch (Exception $e) {
        // Si no se puede ni conectar al servidor MySQL, se trata igual que
        // "primera instalación": install.php ya gestiona ese error como hasta ahora.
        return true;
    }
}

// ── Usuario de la sesión actual (sin tocar la BD) ─────────────
function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

// ── Revalidar el usuario de sesión contra la BD ───────────────
// Detecta en caliente si el usuario fue desactivado, eliminado o cambiado
// de rol desde que inició sesión. Devuelve el usuario actualizado o null.
function revalidarUsuario(PDO $pdo, array $sessionUser): ?array {
    $st = $pdo->prepare("SELECT id, nombre, email, username, rol, activo, eliminado_en
                          FROM usuarios WHERE id = ? LIMIT 1");
    $st->execute([$sessionUser['id'] ?? 0]);
    $row = $st->fetch();
    if (!$row || (int)$row['activo'] !== 1 || $row['eliminado_en'] !== null) return null;

    $fresco = [
        'id'       => (int)$row['id'],
        'nombre'   => $row['nombre'],
        'email'    => $row['email'],
        'username' => $row['username'],
        'rol'      => $row['rol'],
    ];
    $_SESSION['user'] = $fresco;
    return $fresco;
}

// ── requireLoginWeb: para páginas HTML completas ──────────────
// Si no hay sesión válida, redirige a login.php (con ?next= para volver).
// Devuelve el usuario ya revalidado contra la BD.
function requireLoginWeb(PDO $pdo, string $loginUrl = '/login.php'): array {
    $u = currentUser();
    $fresco = $u ? revalidarUsuario($pdo, $u) : null;
    if (!$fresco) {
        $next = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . $loginUrl . ($next ? '?next=' . urlencode($next) : ''));
        exit;
    }
    return $fresco;
}

// ── requireLoginApi: para endpoints JSON ──────────────────────
function requireLoginApi(PDO $pdo): array {
    $u = currentUser();
    $fresco = $u ? revalidarUsuario($pdo, $u) : null;
    if (!$fresco) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Sesión no válida. Inicia sesión de nuevo.', 'code' => 'AUTH_REQUIRED']);
        exit;
    }
    return $fresco;
}

// ── requireRoleWeb / requireRoleApi ───────────────────────────
function requireRoleWeb(array $user, string $rol): void {
    if (($user['rol'] ?? '') !== $rol) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Acceso denegado</title></head>'
           . '<body style="font-family:sans-serif;max-width:520px;margin:80px auto;text-align:center;color:#374151">'
           . '<h2 style="color:#991b1b">Acceso denegado</h2>'
           . '<p>No tienes permisos para acceder a esta sección.</p>'
           . '<a href="../../AlquiGest.php" style="color:#1a56db">← Volver a la aplicación</a>'
           . '</body></html>';
        exit;
    }
}

function requireRoleApi(array $user, string $rol): void {
    if (($user['rol'] ?? '') !== $rol) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'No tienes permisos para realizar esta acción.', 'code' => 'FORBIDDEN']);
        exit;
    }
}

// ── Helpers de permisos concretos usados en install.php ───────
function canAccessInstall(?array $user): bool {
    return $user !== null && ($user['rol'] ?? '') === 'admin';
}
function canAccessBackupOnly(?array $user): bool {
    return $user !== null && ($user['rol'] ?? '') === 'user';
}

// ── Login ──────────────────────────────────────────────────────
// Verifica credenciales, comprueba que el usuario esté activo, regenera
// el id de sesión (evita fijación de sesión) y registra el resultado en
// el log de actividad (éxito y fallo, para poder auditar intentos).
function attemptLogin(PDO $pdo, string $username, string $password): array {
    $username = trim($username);
    if ($username === '' || $password === '') {
        return ['ok' => false, 'error' => 'Usuario y contraseña son obligatorios.'];
    }

    $st = $pdo->prepare("SELECT * FROM usuarios WHERE username = ? AND eliminado_en IS NULL LIMIT 1");
    $st->execute([$username]);
    $row = $st->fetch();

    if (!$row || !password_verify($password, $row['password_hash'])) {
        logActividad($pdo, 'login_fallido', 'usuarios', $row['id'] ?? null, "Intento fallido para usuario \"$username\"", [
            'id' => 0, 'nombre' => $username, 'username' => $username, 'rol' => '',
        ]);
        return ['ok' => false, 'error' => 'Usuario o contraseña incorrectos.'];
    }
    if ((int)$row['activo'] !== 1) {
        logActividad($pdo, 'login_fallido', 'usuarios', $row['id'], "Usuario \"$username\" inactivo intentó acceder", [
            'id' => (int)$row['id'], 'nombre' => $row['nombre'], 'username' => $row['username'], 'rol' => $row['rol'],
        ]);
        return ['ok' => false, 'error' => 'Este usuario está desactivado. Contacta con un administrador.'];
    }

    // Regenerar el id de sesión al autenticar: evita fijación de sesión
    // (session fixation) — un id de sesión previo a login nunca queda válido.
    session_regenerate_id(true);

    $user = [
        'id'       => (int)$row['id'],
        'nombre'   => $row['nombre'],
        'email'    => $row['email'],
        'username' => $row['username'],
        'rol'      => $row['rol'],
    ];
    $_SESSION['user'] = $user;
    $_SESSION['_csrf'] = $_SESSION['_csrf'] ?? bin2hex(random_bytes(32));

    $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?")->execute([$user['id']]);
    logActividad($pdo, 'login_correcto', 'usuarios', $user['id'], "Inicio de sesión de \"{$user['username']}\"");

    return ['ok' => true, 'user' => $user];
}

// ── Logout ─────────────────────────────────────────────────────
function doLogout(?PDO $pdo = null, ?array $user = null): void {
    if ($pdo && $user) {
        logActividad($pdo, 'logout', 'usuarios', $user['id'] ?? null, "Cierre de sesión de \"{$user['username']}\"");
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ── CSRF ───────────────────────────────────────────────────────
// Token único por sesión (no por formulario, para simplicidad): suficiente
// para bloquear peticiones cross-site, ya que un atacante externo no puede
// leer la sesión de la víctima para obtener el valor.
function csrfToken(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}
function csrfValid(?string $token): bool {
    return !empty($_SESSION['_csrf']) && is_string($token) && hash_equals($_SESSION['_csrf'], $token);
}

// ── Log de actividad con atribución de usuario ────────────────
// $usuarioOverride permite registrar acciones de un usuario que todavía no
// está en $_SESSION (p.ej. login fallido, donde no hay sesión iniciada).
// Si log_actividad no existe (BD antigua sin migrar) falla en silencio.
function logActividad(PDO $pdo, string $tipo, string $entidad = '', ?int $entidadId = null, string $desc = '', ?array $usuarioOverride = null): void {
    try {
        $u = $usuarioOverride ?? currentUser();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $pdo->prepare(
            "INSERT INTO log_actividad
                (tipo_accion, entidad, entidad_id, descripcion, usuario_id, usuario_nombre, usuario_username, usuario_rol, ip)
             VALUES (?,?,?,?,?,?,?,?,?)"
        )->execute([
            $tipo, $entidad, $entidadId, $desc,
            $u['id']       ?? null,
            $u['nombre']   ?? ($u ? '' : 'Sistema'),
            $u['username'] ?? null,
            $u['rol']      ?? null,
            $ip,
        ]);
    } catch (\Throwable $e) {
        // No romper la acción principal si el log falla (tabla no migrada, etc.)
        error_log('[AlquiGest] logActividad error: ' . $e->getMessage());
    }
}
