<?php
// ============================================================
//  AlquiGest – Funciones PHP compartidas entre scripts del servidor
//
//  Incluir con: require __DIR__ . '/../../assets/php/helpers.php';
//  (o la ruta relativa correspondiente desde cada script)
//
//  Funciones disponibles:
//    requireLocalhost()   → aborta con 403 si la petición no viene de localhost
//    setCorsHeaders()     → cabeceras HTTP de seguridad + CORS restrictivo
//    json_respond($d,$c)  → serializa como JSON y termina la ejecución
//    rowToObj($row,$json) → convierte una fila MySQL en array PHP tipado
//    getRow($pdo,$t,$id)  → obtiene un único registro por ID
//    getTable($pdo,$t)    → obtiene todos los registros de una tabla ordenados por id
// ============================================================

// ── Solo se puede invocar desde localhost ─────────────────────
// Devuelve 403 si la IP del cliente no es 127.0.0.1 / ::1.
function requireLocalhost(): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($ip, ['127.0.0.1', '::1'], true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Acceso solo permitido desde localhost']);
        exit;
    }
}

// ── Cabeceras HTTP de seguridad y CORS restrictivo ────────────
// CORS solo permite orígenes localhost/127.0.0.1. Responde al
// preflight de OPTIONS con 204 y termina la ejecución.
function setCorsHeaders(): void {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin && (
        strpos($origin, '//localhost') !== false ||
        strpos($origin, '//127.0.0.1') !== false
    )) {
        header("Access-Control-Allow-Origin: $origin");
    }
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ── Respuesta JSON unificada ──────────────────────────────────
// Establece el código HTTP, serializa el array como JSON y termina.
function json_respond($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── Conversión de fila MySQL → array PHP tipado ───────────────
// Convierte cadenas numéricas a int/float y decodifica columnas JSON.
// Parámetros:
//   $row      → fila tal como la devuelve PDO::FETCH_ASSOC
//   $jsonCols → array de nombres de columna que contienen JSON (por defecto ['pagos'])
function rowToObj(array $row, array $jsonCols = ['pagos']): array {
    $obj = ['id' => (int)$row['id']];
    foreach ($row as $k => $v) {
        if ($k === 'id' || $k === 'updated_at') continue;
        if (in_array($k, $jsonCols, true)) {
            $obj[$k] = $v !== null ? (json_decode($v, true) ?: []) : [];
        } elseif ($v !== null && preg_match('/^-?\d+$/', (string)$v)) {
            $obj[$k] = (int)$v;
        } elseif ($v !== null && preg_match('/^-?\d+\.\d+$/', (string)$v)) {
            $obj[$k] = (float)$v;
        } else {
            $obj[$k] = $v;
        }
    }
    return $obj;
}

// ── Obtener un único registro por ID ─────────────────────────
// Devuelve el registro como array tipado o null si no existe.
function getRow(PDO $pdo, string $table, int $id, array $jsonCols = ['pagos']): ?array {
    $st = $pdo->prepare("SELECT * FROM `$table` WHERE id = ?");
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ? rowToObj($row, $jsonCols) : null;
}

// ── Obtener todos los registros de una tabla ─────────────────
// Devuelve array de arrays tipados, ordenados por id.
function getTable(PDO $pdo, string $table, array $jsonCols = ['pagos']): array {
    $rows = $pdo->query("SELECT * FROM `$table` ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    return array_map(fn($r) => rowToObj($r, $jsonCols), $rows);
}

// ── Cifrado AES-256-CBC para credenciales sensibles ──────────
// Los valores cifrados llevan el prefijo 'ENC:' para distinguirlos
// de texto plano. El IV (vector de inicialización) se almacena junto
// con el texto cifrado (primeros 16 bytes tras decodificar base64).

// Cifra $valor con AES-256-CBC usando $clave. Devuelve 'ENC:'+base64.
// Si la extensión openssl no está disponible devuelve el valor sin cifrar.
function encryptValue(string $valor, string $clave): string {
    if (!$clave || !function_exists('openssl_encrypt')) return $valor;
    $iv      = openssl_random_pseudo_bytes(16);
    $cifrado = openssl_encrypt($valor, 'AES-256-CBC', $clave, OPENSSL_RAW_DATA, $iv);
    if ($cifrado === false) return $valor;
    return 'ENC:' . base64_encode($iv . $cifrado);
}

// Descifra un valor generado por encryptValue().
// Si el valor no tiene el prefijo 'ENC:' lo devuelve tal cual (texto plano).
// Devuelve cadena vacía si el descifrado falla (clave incorrecta o datos corruptos).
function decryptValue(string $valor, string $clave): string {
    if (!$clave || !function_exists('openssl_decrypt')) return $valor;
    if (substr($valor, 0, 4) !== 'ENC:') return $valor;
    $datos = base64_decode(substr($valor, 4));
    if ($datos === false || strlen($datos) < 17) return '';
    $iv         = substr($datos, 0, 16);
    $cifrado    = substr($datos, 16);
    $descifrado = openssl_decrypt($cifrado, 'AES-256-CBC', $clave, OPENSSL_RAW_DATA, $iv);
    return ($descifrado !== false) ? $descifrado : '';
}

// Devuelve true si el valor fue cifrado con encryptValue() (tiene prefijo 'ENC:').
function isEncrypted(string $valor): bool {
    return substr($valor, 0, 4) === 'ENC:';
}
