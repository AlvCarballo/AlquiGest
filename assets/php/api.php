<?php
// ============================================================
//  AlquiGest – API REST (backend MySQL)
//
//  Este archivo es el único punto de acceso entre el JavaScript
//  del navegador y la base de datos MySQL. Acepta peticiones GET
//  y POST desde localhost y las traduce a consultas SQL.
//
//  Acciones disponibles (parámetro GET ?action=...):
//    getAll  → devuelve todas las tablas en un solo JSON (carga inicial)
//    save    → INSERT o UPDATE de un registro (?table=nombre)
//    delete  → DELETE de un registro (?table=nombre&id=N)
//    check   → comprueba la conexión y devuelve la versión de MySQL
//
//  No modificar salvo para añadir tablas nuevas al array $TABLES y $SCHEMA.
// ============================================================

// ── Funciones compartidas (seguridad, CORS, helpers de BD) ───
require __DIR__ . '/helpers.php';
requireLocalhost();
setCorsHeaders();

// ── Configuración de base de datos ───────────────────────────
$cfg    = require __DIR__ . '/config.php';

// ── Tablas de la aplicación ───────────────────────────────────
// Solo se permite operar sobre estas tablas (lista blanca de seguridad).
// 'configuracion' almacena parámetros ajustables por el usuario (ej: filas_dashboard).
// 'facturas' almacena las facturas legales generadas desde recibos.
$TABLES = ['empresa', 'propietarios', 'fincas', 'inmuebles', 'inquilinos', 'contratos', 'recibos', 'configuracion', 'facturas', 'historial_rentas', 'contratos_inq_sec'];

// ── Esquema de columnas por tabla ─────────────────────────────
// Define qué columnas se incluyen en INSERT y UPDATE.
// 'id' y 'updated_at' se gestionan por separado y no aparecen aquí.
$SCHEMA = [
    'empresa'        => ['nombre','cif','direccion','cp','municipio','provincia','telefono','email','iban','pie_recibo','prefijo_recibos','gmail_user','gmail_pass','web','email_asunto_recibo','email_cuerpo_recibo','email_asunto_factura'],
    'propietarios'   => ['nombre','nif','telefono','email','irpf','direccion','cp','municipio','provincia','pais','iban','observaciones'],
    'fincas'         => ['nombre','sigla','calle','numero','cp','municipio','provincia','propietario_id','observaciones'],
    'inmuebles'      => ['finca_id','planta','puerta','tipo','metros','referencia_catastral','cedula','observaciones'],
    'inquilinos'     => ['nombre','nif','telefono','movil','email','direccion','cp','municipio','provincia','pais','iban','observaciones'],
    'contratos'      => ['inmueble_id','inquilino_id','fecha_inicio','fecha_fin','duracion_anos','duracion_unidad','aviso_recibo','aviso_factura','renta_base','iva_pct','irpf_pct','fianza','dia_pago','estado','revision','fecha_baja','motivo_baja','obs_baja','observaciones','ipc_anio_aplicado','motivo_temporada','nombre_fiador','nif_fiador','direccion_fiador'],
    // factura_id vincula el recibo con su factura emitida (NULL si no tiene factura todavía)
    // aviso_recibo (TINYINT 1): si es 1 el pie del recibo muestra el aviso de justificante de pago
    // recibo_rectificado_id: id del recibo que este recibo rectifica (solo en recibos rectificativos RER-AAAAMM-NNNNN)
    'recibos'        => ['contrato_id','inquilino_id','inmueble_id','numero_recibo','numero_seq','fecha_emision','periodo_desde','periodo_hasta','concepto_periodo','fecha_limite','renta_base','importe_iva','importe_irpf','importe_total','importe_pagado','conceptos_extra','notas','pagos','estado','fecha_creacion','aviso_recibo','factura_id','recibo_rectificado_id'],
    // variable: clave única (ej: 'filas_dashboard')
    // valor:    valor como texto (el JS lo convierte al tipo que necesite)
    // descripcion: texto libre para saber qué hace cada parámetro
    'configuracion'  => ['variable','valor','descripcion'],
    // Historial de revisiones de renta: cambios aplicados a cada contrato
    'historial_rentas' => ['contrato_id','fecha','tipo_revision','porcentaje','renta_anterior','renta_nueva','observaciones'],
    // Inquilinos secundarios del contrato: solo para documentos, sin impacto en negocio
    'contratos_inq_sec' => ['contrato_id','nombre','nif','direccion','telefono','email','orden'],
    // ── Facturas ──────────────────────────────────────────────────
    // Una factura se genera desde un recibo y congela los datos fiscales
    // del momento de emisión. No se puede crear sin recibo origen.
    // Los campos emisor_* y cliente_* se copian en el momento de generar
    // para garantizar la inmutabilidad histórica (si el inquilino cambia
    // de dirección, la factura antigua conserva la dirección original).
    'facturas' => [
        'recibo_id','contrato_id','inquilino_id','inmueble_id',
        'numero_factura','numero_seq','serie','tipo_factura',
        'fecha_emision','fecha_operacion','periodo_desde','periodo_hasta',
        // Datos del emisor (empresa/propietario) copiados en el momento de emisión
        'emisor_nombre','emisor_nif','emisor_direccion','emisor_cp',
        'emisor_municipio','emisor_provincia','emisor_email','emisor_telefono','emisor_iban',
        // Datos del cliente (inquilino) copiados en el momento de emisión
        'cliente_nombre','cliente_nif','cliente_direccion','cliente_cp',
        'cliente_municipio','cliente_provincia','cliente_email',
        // Descripción del servicio e importes fiscales
        'inmueble_direccion','concepto','conceptos_extra','notas',
        'base_imponible','iva_pct','importe_iva','irpf_pct','importe_irpf','importe_total',
        'estado',
        // Campos preparados para VERI*FACTU / SIF (no se envían todavía a AEAT)
        'hash_factura','hash_anterior','qr_url',
        'verifactu_estado','verifactu_respuesta',
        'factura_rectificada_id',
        'fecha_creacion',
    ],
];

// Columnas que se almacenan como JSON (arrays de objetos desde JavaScript).
// Al leer se decodifican a array PHP; al escribir se codifican con json_encode.
$JSON_COLS = ['pagos'];

// ── Singleton de conexión PDO ─────────────────────────────────
// Se crea una única instancia de PDO para toda la petición.
// PDO::ERRMODE_EXCEPTION hace que los errores SQL se conviertan en excepciones
// capturables en el bloque try/catch del router.
function db(array $cfg): PDO {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO(
            "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset={$cfg['charset']}",
            $cfg['user'], $cfg['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

// ── Validación de nombre de tabla ─────────────────────────────
// Comprueba que la tabla solicitada esté en la lista blanca $TABLES.
// Evita inyección SQL en los nombres de tabla dinámicos.
function validTable(string $t): bool {
    global $TABLES;
    return in_array($t, $TABLES, true);
}

// ── Alias local de json_respond para compatibilidad con el resto del archivo ─
// api.php usaba el nombre json_out internamente; se mantiene como alias.
function json_out($data, int $code = 200): void { json_respond($data, $code); }

// ── IneIndexService: obtiene la variación anual del IPC o IRAV ───────────
//
//  Fuente: API JSON oficial del INE — wstempus (servicios.ine.es/wstempus)
//
//  Códigos de serie verificados mediante la cadena oficial de la API:
//    OPERACIONES_DISPONIBLES → Id=25 (IPC) / Id=481 (IRAV)
//    TABLAS_OPERACION/25     → Tabla 76134 "Tasa de variación índice general"
//    SERIES_TABLA/76134      → Serie IPC290750 "Variación anual"
//    SERIE/IRAV1             → "Total Nacional. Variación anual" (Op 481)
//
//  Endpoint: https://servicios.ine.es/wstempus/js/ES/DATOS_SERIE/{serie}?nult=1
//  Respuesta: [{"COD":"...","Nombre":"...","Data":[{"Valor":X,...}]}]
//
//  Si la API no responde o devuelve un valor no utilizable → 0%
//  El fallback no rompe la aplicación y deja el campo editable manualmente.

/**
 * Constantes del servicio INE.
 * Si el INE actualiza sus códigos de serie, solo hay que tocar aquí.
 */
const INE_WSTEMPUS_BASE = 'https://servicios.ine.es/wstempus/js/ES';

// Series verificadas con DATOS_SERIE (último valor anual disponible):
//   IPC290750 → Nacional. Índice general. Variación anual.  (Op 25, Tabla 76134)
//   IRAV1     → Total Nacional. Índice general. Variación anual. (Op 481)
const INE_SERIES = [
    'IPC'  => ['IPC290750'],
    'IRAV' => ['IRAV1'],
];

/** Realiza una petición HTTP con cURL; devuelve el cuerpo o false en error. */
function ine_curl_fetch(string $url, int $timeout = 8) {
    if (!function_exists('curl_init')) {
        error_log('[INE] cURL no disponible — no se puede consultar la API del INE');
        return false;
    }
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 2,
        CURLOPT_USERAGENT      => 'AlquiGest/2.2 (PHP; INE API client)',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING       => '',
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $resp  = curl_exec($ch);
    $errno = curl_errno($ch);
    $err   = curl_error($ch);
    $http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($errno) {
        error_log("[INE] cURL error ({$errno}): {$err}");
        return false;
    }
    if ($http !== 200) {
        error_log("[INE] HTTP {$http} al consultar {$url}");
        return false;
    }
    return ($resp !== '' && $resp !== false) ? $resp : false;
}

/**
 * Parsea la respuesta JSON de DATOS_SERIE y devuelve el último valor anual.
 * Formato esperado: [{"COD":"...","Data":[{"Valor":X},...]}]
 * Devuelve null si el JSON no tiene la forma esperada o el valor no es numérico.
 */
function ine_parse_datos_serie(string $json): ?float {
    $data = json_decode($json, true);
    if (!is_array($data) || empty($data)) {
        error_log('[INE] Respuesta JSON vacía o inválida');
        return null;
    }
    // El endpoint devuelve un array con una entrada por serie solicitada
    $serie   = isset($data[0]) ? $data[0] : $data;
    $entries = $serie['Data'] ?? [];
    if (empty($entries)) {
        error_log('[INE] La serie no contiene datos (Data vacío)');
        return null;
    }
    $last = end($entries);
    $raw  = $last['Valor'] ?? null;
    if ($raw === null || $raw === '') {
        error_log('[INE] Campo Valor ausente o nulo en el último dato');
        return null;
    }
    // El INE puede devolver tanto float nativo como string con coma decimal
    $val = round((float) str_replace(',', '.', (string) $raw), 2);
    if (!is_finite($val) || $val < -10 || $val > 25) {
        error_log("[INE] Valor fuera de rango esperado: {$val}");
        return null;
    }
    return $val;
}

/**
 * IneIndexService::getAnnualRateByType()
 *
 * Consulta la API JSON oficial del INE para obtener la variación anual del
 * índice indicado (IPC o IRAV). Devuelve el resultado como array asociativo:
 *   ['valor' => float, 'fuente' => 'INE'|'fallback', 'tipo' => 'IPC'|'IRAV', ...]
 *
 * En cualquier escenario de fallo devuelve valor=0 sin lanzar excepciones,
 * para que el usuario pueda introducir el porcentaje manualmente.
 */
function fetchIneAnnualRate(string $tipo): array {
    $series = INE_SERIES[$tipo] ?? [];
    if (empty($series)) {
        error_log("[INE] Tipo de índice no reconocido: {$tipo}");
        return [
            'valor'  => 0.0,
            'fuente' => 'fallback',
            'tipo'   => $tipo,
            'aviso'  => "Tipo de índice desconocido: {$tipo}. Introduzca el porcentaje manualmente.",
        ];
    }

    foreach ($series as $codigoSerie) {
        // Endpoint oficial: DATOS_SERIE/{codigo}?nult=1 → devuelve el último dato
        $url  = INE_WSTEMPUS_BASE . "/DATOS_SERIE/{$codigoSerie}?nult=1";
        $resp = ine_curl_fetch($url);
        if ($resp === false) {
            error_log("[INE] Sin respuesta de la API para serie {$codigoSerie}");
            continue;
        }
        $val = ine_parse_datos_serie($resp);
        if ($val !== null) {
            error_log("[INE] {$tipo} obtenido via API oficial (serie {$codigoSerie}): {$val}%");
            return [
                'valor'  => $val,
                'fuente' => 'INE',
                'tipo'   => $tipo,
                'serie'  => $codigoSerie,
                'endpoint' => $url,
            ];
        }
        error_log("[INE] La serie {$codigoSerie} no devolvió un valor utilizable");
    }

    // Fallback final: 0% editable — no rompe la aplicación
    error_log("[INE] No se pudo obtener la tasa anual {$tipo} desde la API del INE. Devolviendo 0%.");
    return [
        'valor'  => 0.0,
        'fuente' => 'fallback',
        'tipo'   => $tipo,
        'aviso'  => 'No se pudo obtener el dato del INE. Introduzca el porcentaje manualmente.',
    ];
}

// ── Router principal ──────────────────────────────────────────
// Lee la acción del parámetro GET y el cuerpo JSON del POST.
// Todas las excepciones se capturan y devuelven como JSON de error.
$action = $_GET['action'] ?? '';
$input  = json_decode(file_get_contents('php://input'), true) ?: [];

// ── ine_rate: consulta al INE (sin BD) ───────────────────────
// Devuelve {"valor":X.X,"fuente":"INE|fallback","tipo":"IPC|IRAV"}
// Si el INE no responde o cambia su formato devuelve valor:0.
if ($action === 'ine_rate') {
    $tipo = strtoupper(trim($_GET['tipo'] ?? ''));
    if (!in_array($tipo, ['IPC', 'IRAV'], true)) {
        json_out(['valor' => 0.0, 'fuente' => 'fallback',
                  'aviso' => 'Tipo no válido. Use IPC o IRAV.'], 400);
    }
    // -1 = representación más corta posible (evita el ruido binario en floats)
    ini_set('serialize_precision', -1);
    json_out(fetchIneAnnualRate($tipo));
}

try {
    $pdo = db($cfg);

    // ── Auto-migraciones: añade columnas nuevas sin perder datos ─
    $existingCols = array_column($pdo->query("SHOW COLUMNS FROM `contratos`")->fetchAll(), 'Field');
    if (!in_array('aviso_factura', $existingCols)) {
        $pdo->exec("ALTER TABLE `contratos` ADD COLUMN `aviso_factura` TINYINT(1) DEFAULT 0");
    }
    if (!in_array('ipc_anio_aplicado', $existingCols)) {
        $pdo->exec("ALTER TABLE `contratos` ADD COLUMN `ipc_anio_aplicado` INT NULL DEFAULT NULL");
    }
    if (!in_array('motivo_temporada', $existingCols)) {
        $pdo->exec("ALTER TABLE `contratos` ADD COLUMN `motivo_temporada` TEXT DEFAULT NULL");
    }
    if (!in_array('nombre_fiador', $existingCols)) {
        $pdo->exec("ALTER TABLE `contratos` ADD COLUMN `nombre_fiador` VARCHAR(150) DEFAULT NULL");
    }
    if (!in_array('nif_fiador', $existingCols)) {
        $pdo->exec("ALTER TABLE `contratos` ADD COLUMN `nif_fiador` VARCHAR(20) DEFAULT NULL");
    }
    if (!in_array('direccion_fiador', $existingCols)) {
        $pdo->exec("ALTER TABLE `contratos` ADD COLUMN `direccion_fiador` TEXT DEFAULT NULL");
    }

    // ── Migración: columna recibo_rectificado_id en recibos (recibos rectificativos RER) ──
    $existingColsRecibos = array_column($pdo->query("SHOW COLUMNS FROM `recibos`")->fetchAll(), 'Field');
    if (!in_array('recibo_rectificado_id', $existingColsRecibos)) {
        $pdo->exec("ALTER TABLE `recibos` ADD COLUMN `recibo_rectificado_id` INT DEFAULT NULL");
    }

    // ── Migración: tabla log_actividad (si log_actividad=true en config.php) ──
    if (!empty($cfg['log_actividad'])) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `log_actividad` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `tipo_accion` VARCHAR(100) NOT NULL DEFAULT '',
                `entidad` VARCHAR(50) DEFAULT '',
                `entidad_id` INT DEFAULT NULL,
                `descripcion` TEXT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_log_fecha` (`fecha`),
                INDEX `idx_log_tipo` (`tipo_accion`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\PDOException $e) { /* tabla ya existe */ }
    }

    // ── Migración automática: cifrar contraseñas en texto plano ──
    // Si existe encrypt_key en config.php y hay contraseñas sin cifrar, se cifran ahora.
    // Solo se ejecuta si openssl está disponible y la clave está configurada.
    $encKey = $cfg['encrypt_key'] ?? '';
    if ($encKey && function_exists('openssl_encrypt')) {
        try {
            // Cifrar gmail_pass si existe y está en texto plano
            $empRow = $pdo->query("SELECT id, gmail_pass FROM empresa ORDER BY id LIMIT 1")->fetch();
            if ($empRow && !empty($empRow['gmail_pass']) && !isEncrypted($empRow['gmail_pass'])) {
                $cifrado = encryptValue($empRow['gmail_pass'], $encKey);
                $pdo->prepare("UPDATE empresa SET gmail_pass = ? WHERE id = ?")->execute([$cifrado, $empRow['id']]);
            }
            // Cifrar verifactu_cert_pass si existe y está en texto plano
            $cfgPassRow = $pdo->query("SELECT id, valor FROM configuracion WHERE variable = 'verifactu_cert_pass' LIMIT 1")->fetch();
            if ($cfgPassRow && !empty($cfgPassRow['valor']) && !isEncrypted($cfgPassRow['valor'])) {
                $cifrado = encryptValue($cfgPassRow['valor'], $encKey);
                $pdo->prepare("UPDATE configuracion SET valor = ? WHERE id = ?")->execute([$cifrado, $cfgPassRow['id']]);
            }
        } catch (\PDOException $e) {
            error_log('[AlquiGest] Error en migración de cifrado: ' . $e->getMessage());
        }
    }

    // ── Migración: tabla contratos_inq_sec (inquilinos secundarios) ──────
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `contratos_inq_sec` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `contrato_id` INT NOT NULL,
            `nombre` VARCHAR(150) NOT NULL DEFAULT '',
            `nif` VARCHAR(20) DEFAULT '',
            `direccion` TEXT DEFAULT NULL,
            `telefono` VARCHAR(30) DEFAULT '',
            `email` VARCHAR(100) DEFAULT '',
            `orden` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_inq_sec_contrato` (`contrato_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\PDOException $e) { /* tabla ya existe */ }

    // ── Migración: tabla historial_rentas (si no existe) ─────────
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `historial_rentas` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `contrato_id` INT NOT NULL DEFAULT 0,
            `fecha` DATE NOT NULL,
            `tipo_revision` VARCHAR(50) DEFAULT '',
            `porcentaje` DECIMAL(6,3) DEFAULT 0,
            `renta_anterior` DECIMAL(10,2) DEFAULT 0,
            `renta_nueva` DECIMAL(10,2) DEFAULT 0,
            `observaciones` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\PDOException $e) { /* tabla ya existe */ }

    // ── Migración: índices de rendimiento (try/catch por compatibilidad MySQL 5.7) ─
    // MySQL 5.7 no soporta CREATE INDEX IF NOT EXISTS; se ignora el error si ya existe.
    $indicesMigracion = [
        "CREATE INDEX `idx_contratos_estado`    ON `contratos`(`estado`)",
        "CREATE INDEX `idx_contratos_inmueble`  ON `contratos`(`inmueble_id`)",
        "CREATE INDEX `idx_contratos_inquilino` ON `contratos`(`inquilino_id`)",
        "CREATE INDEX `idx_recibos_estado`    ON `recibos`(`estado`)",
        "CREATE INDEX `idx_recibos_contrato`  ON `recibos`(`contrato_id`)",
        "CREATE INDEX `idx_recibos_inquilino` ON `recibos`(`inquilino_id`)",
        "CREATE INDEX `idx_recibos_inmueble`  ON `recibos`(`inmueble_id`)",
        "CREATE INDEX `idx_historial_contrato` ON `historial_rentas`(`contrato_id`)",
    ];
    foreach ($indicesMigracion as $sqlIdx) {
        try { $pdo->exec($sqlIdx); } catch (\PDOException $e) { /* índice ya existe, se ignora */ }
    }

    // ── Migración: UNIQUE KEY en recibos.numero_recibo (evita duplicados) ──
    // Solo se añade si no existe ya y no hay duplicados actuales (instalaciones
    // antiguas podrían tenerlos); si los hay se deja para revisión manual.
    try {
        $tieneIndice = $pdo->query(
            "SHOW INDEX FROM `recibos` WHERE Key_name = 'uq_recibos_numero_recibo'"
        )->fetch();
        if (!$tieneIndice) {
            $dup = $pdo->query(
                "SELECT numero_recibo FROM `recibos` WHERE numero_recibo != ''
                 GROUP BY numero_recibo HAVING COUNT(*) > 1 LIMIT 1"
            )->fetch();
            if (!$dup) {
                $pdo->exec("ALTER TABLE `recibos` ADD UNIQUE KEY `uq_recibos_numero_recibo` (`numero_recibo`)");
            }
        }
    } catch (\PDOException $e) { /* se ignora: se revisará en el próximo arranque */ }

    // ── getAll: carga inicial completa de la BD ──────────────
    // El JavaScript llama a esto una sola vez al arrancar para cachear
    // todos los datos en memoria (objeto DB._cache).
    // Los campos cifrados (gmail_pass, verifactu_cert_pass) se descifran
    // aquí antes de devolver al cliente JS para que el frontend sea transparente.
    if ($action === 'getAll') {
        $result = [];
        foreach ($TABLES as $t) {
            $rows    = $pdo->query("SELECT * FROM `$t` ORDER BY id")->fetchAll();
            $jCols   = $JSON_COLS;
            $result[$t] = array_map(fn($r) => rowToObj($r, $jCols), $rows);
        }
        // Descifrar campos sensibles antes de enviar al navegador
        $encKey = $cfg['encrypt_key'] ?? '';
        if ($encKey) {
            // gmail_pass en tabla empresa
            if (!empty($result['empresa'])) {
                foreach ($result['empresa'] as &$emp) {
                    if (!empty($emp['gmail_pass']) && isEncrypted((string)$emp['gmail_pass'])) {
                        $emp['gmail_pass'] = decryptValue((string)$emp['gmail_pass'], $encKey);
                    }
                }
                unset($emp);
            }
            // verifactu_cert_pass en tabla configuracion
            if (!empty($result['configuracion'])) {
                foreach ($result['configuracion'] as &$cfgRow) {
                    if (($cfgRow['variable'] ?? '') === 'verifactu_cert_pass'
                        && !empty($cfgRow['valor']) && isEncrypted((string)$cfgRow['valor'])) {
                        $cfgRow['valor'] = decryptValue((string)$cfgRow['valor'], $encKey);
                    }
                }
                unset($cfgRow);
            }
        }
        json_out($result);
    }

    // ── log: registrar una acción en log_actividad ───────────
    // Si log_actividad está desactivado en config.php, responde ok sin insertar.
    if ($action === 'log') {
        if (empty($cfg['log_actividad'])) {
            json_out(['ok' => true, 'skip' => true]);
        }
        $tipo    = trim($input['tipo_accion']  ?? '');
        $entidad = trim($input['entidad']      ?? '');
        $entId   = isset($input['entidad_id']) ? (int)$input['entidad_id'] : null;
        $desc    = trim($input['descripcion']  ?? '');
        if (!$tipo) json_out(['ok' => false, 'error' => 'tipo_accion requerido'], 400);
        $pdo->prepare(
            "INSERT INTO `log_actividad` (fecha, tipo_accion, entidad, entidad_id, descripcion) VALUES (NOW(), ?, ?, ?, ?)"
        )->execute([$tipo, $entidad, $entId, $desc]);
        json_out(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    }

    // ── getLog: obtener registros de actividad con filtros ────
    // Devuelve los últimos N registros (defecto 100, máx 200).
    // Filtros opcionales: tipo (GET), desde (GET fecha), hasta (GET fecha).
    if ($action === 'getLog') {
        if (empty($cfg['log_actividad'])) json_out([]);
        $tipo   = trim($_GET['tipo']  ?? '');
        $desde  = trim($_GET['desde'] ?? '');
        $hasta  = trim($_GET['hasta'] ?? '');
        $limite = min(200, max(1, (int)($_GET['limite'] ?? 100)));
        $where  = ['1=1'];
        $params = [];
        if ($tipo)  { $where[] = 'tipo_accion = ?'; $params[] = $tipo; }
        if ($desde) { $where[] = 'fecha >= ?';      $params[] = $desde . ' 00:00:00'; }
        if ($hasta) { $where[] = 'fecha <= ?';      $params[] = $hasta . ' 23:59:59'; }
        $sql = "SELECT * FROM `log_actividad` WHERE " . implode(' AND ', $where) .
               " ORDER BY id DESC LIMIT " . $limite;
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $result = array_map(function ($r) {
            return [
                'id'          => (int)$r['id'],
                'fecha'       => $r['fecha'],
                'tipo_accion' => $r['tipo_accion'],
                'entidad'     => $r['entidad'],
                'entidad_id'  => $r['entidad_id'] !== null ? (int)$r['entidad_id'] : null,
                'descripcion' => $r['descripcion'],
                'created_at'  => $r['created_at'],
            ];
        }, $rows);
        json_out($result);
    }

    // ── backup: exporta toda la BD en JSON ───────────────────
    // Descarga un fichero JSON con todos los datos actuales de la BD.
    // Sirve como copia de seguridad manual desde Mi Empresa.
    if ($action === 'backup') {
        $result = [];
        foreach ($TABLES as $t) {
            $rows    = $pdo->query("SELECT * FROM `$t` ORDER BY id")->fetchAll();
            $jCols   = $JSON_COLS;
            $result[$t] = array_map(fn($r) => rowToObj($r, $jCols), $rows);
        }
        $result['_meta'] = [
            'version'     => '2.0.0',
            'fecha_backup' => date('Y-m-d H:i:s'),
            'tablas'      => count($TABLES),
        ];
        header('Content-Disposition: attachment; filename="alquigest_backup_' . date('Ymd_His') . '.json"');
        json_out($result);
    }

    // ── validarDatos: validación server-side de campos críticos ──
    // Comprueba los campos mínimos obligatorios antes de cada guardado.
    // Devuelve null si todo está bien, o un string con el mensaje de error.
    function validarDatos(string $tabla, array $datos): ?string {
        switch ($tabla) {
            case 'contratos':
                if (empty($datos['inmueble_id']) || (int)$datos['inmueble_id'] <= 0) {
                    return 'El contrato debe tener un inmueble asignado.';
                }
                if (empty($datos['inquilino_id']) || (int)$datos['inquilino_id'] <= 0) {
                    return 'El contrato debe tener un inquilino asignado.';
                }
                if (!isset($datos['renta_base']) || (float)$datos['renta_base'] < 0) {
                    return 'La renta base no puede ser negativa.';
                }
                if (empty($datos['fecha_inicio'])) {
                    return 'La fecha de inicio del contrato es obligatoria.';
                }
                break;

            case 'recibos':
                // Los recibos rectificativos (estado='rectificativo', numeración RER-AAAAMM-NNNNN)
                // llevan importes negados a propósito: cancelan en los totales al recibo original.
                $esRectificativoRec = ($datos['estado'] ?? '') === 'rectificativo';
                if (!isset($datos['importe_total']) || (!$esRectificativoRec && (float)$datos['importe_total'] < 0)) {
                    return 'El importe total del recibo no puede ser negativo.';
                }
                if (!isset($datos['renta_base']) || (!$esRectificativoRec && (float)$datos['renta_base'] < 0)) {
                    return 'La renta base del recibo no puede ser negativa.';
                }
                break;

            case 'inquilinos':
                if (empty(trim($datos['nombre'] ?? ''))) {
                    return 'El nombre del inquilino es obligatorio.';
                }
                break;

            case 'propietarios':
                if (empty(trim($datos['nombre'] ?? ''))) {
                    return 'El nombre del propietario es obligatorio.';
                }
                break;

            case 'fincas':
                if (empty(trim($datos['nombre'] ?? ''))) {
                    return 'El nombre de la finca es obligatorio.';
                }
                break;

            case 'inmuebles':
                if (empty(trim($datos['nombre'] ?? ''))) {
                    return 'La referencia/nombre del inmueble es obligatoria.';
                }
                break;

            case 'facturas':
                // Las facturas rectificativas (serie RET, tipo_factura R1) llevan importes
                // negados a propósito: cancelan fiscalmente los efectos de la factura original
                // (RD 1619/2012, art. 15). Solo se rechazan negativos en facturas normales.
                $esRectificativaFac = ($datos['serie'] ?? '') === 'RET' || (($datos['tipo_factura'] ?? '') === 'R1');
                if (!isset($datos['importe_total']) || (!$esRectificativaFac && (float)$datos['importe_total'] < 0)) {
                    return 'El importe total de la factura no puede ser negativo.';
                }
                break;
        }
        return null;
    }

    // ── nextNumeroDoc: reserva el siguiente número de secuencia mensual ──
    // Devuelve { seq, numero, tipo, periodo } de forma atómica (SELECT FOR UPDATE
    // dentro de una transacción InnoDB). Es imposible generar duplicados aunque
    // varios procesos llamen simultáneamente para el mismo tipo y periodo.
    // Uso: ?action=nextNumeroDoc&tipo=REC&periodo=202606&prefijo=REC
    if ($action === 'nextNumeroDoc') {
        $tipo    = preg_replace('/[^A-Z]/',      '', strtoupper($_GET['tipo']    ?? ''));
        $periodo = preg_replace('/[^0-9]/',      '',              $_GET['periodo'] ?? date('Ym'));
        $prefijo = preg_replace('/[^A-Z0-9\-]/', '', strtoupper($_GET['prefijo'] ?? $tipo));

        if (!$tipo || strlen($periodo) !== 6) {
            json_out(['error' => 'Parámetros inválidos: tipo y periodo (YYYYMM) son obligatorios'], 400);
        }

        // Crear la tabla si aún no existe (instalaciones antiguas sin migración)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `doc_secuencias` (
            `tipo`    VARCHAR(20) NOT NULL,
            `periodo` CHAR(6)     NOT NULL,
            `ultimo`  INT         NOT NULL DEFAULT 0,
            PRIMARY KEY (`tipo`, `periodo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->beginTransaction();
        try {
            // Bloqueo exclusivo de la fila: ninguna otra conexión puede leer
            // ni modificar esta fila hasta que la transacción haga COMMIT.
            $stmt = $pdo->prepare(
                "SELECT ultimo FROM doc_secuencias WHERE tipo = ? AND periodo = ? FOR UPDATE"
            );
            $stmt->execute([$tipo, $periodo]);
            $fila = $stmt->fetch();

            if ($fila === false) {
                // Primera secuencia para este tipo+periodo: insertar con seq=1
                $pdo->prepare(
                    "INSERT INTO doc_secuencias (tipo, periodo, ultimo) VALUES (?, ?, 1)"
                )->execute([$tipo, $periodo]);
                $seq = 1;
            } else {
                $seq = (int)$fila['ultimo'] + 1;
                $pdo->prepare(
                    "UPDATE doc_secuencias SET ultimo = ? WHERE tipo = ? AND periodo = ?"
                )->execute([$seq, $tipo, $periodo]);
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            json_out(['error' => 'Error al generar secuencia: ' . $e->getMessage()], 500);
        }

        $numero = $prefijo . '-' . $periodo . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);
        json_out(['seq' => $seq, 'numero' => $numero, 'tipo' => $tipo, 'periodo' => $periodo]);
    }

    // ── save: INSERT si no hay id, UPDATE si hay id ──────────
    // Construye la consulta dinámicamente con los campos del $SCHEMA
    // para la tabla indicada. Las columnas JSON se re-codifican a string.
    if ($action === 'save') {
        global $SCHEMA, $JSON_COLS;
        $table = $_GET['table'] ?? '';
        if (!validTable($table)) json_out(['error' => 'Tabla no válida'], 400);

        // Validar los datos antes de persistirlos
        $errorValidacion = validarDatos($table, $input);
        if ($errorValidacion !== null) {
            json_out(['error' => $errorValidacion], 422);
        }

        // Cifrar campos sensibles antes de escribir en BD
        $encKey = $cfg['encrypt_key'] ?? '';
        if ($encKey && function_exists('openssl_encrypt')) {
            // gmail_pass en tabla empresa: cifrar si no está ya cifrado y no está vacío
            if ($table === 'empresa' && isset($input['gmail_pass'])
                && $input['gmail_pass'] !== '' && !isEncrypted($input['gmail_pass'])) {
                $input['gmail_pass'] = encryptValue($input['gmail_pass'], $encKey);
            }
            // verifactu_cert_pass en configuracion: cifrar si la variable es esa
            if ($table === 'configuracion'
                && ($input['variable'] ?? '') === 'verifactu_cert_pass'
                && isset($input['valor']) && $input['valor'] !== ''
                && !isEncrypted($input['valor'])) {
                $input['valor'] = encryptValue($input['valor'], $encKey);
            }
        }

        $id   = isset($input['id']) ? (int)$input['id'] : 0;
        $cols = $SCHEMA[$table] ?? [];

        // ── VERI*FACTU: hash encadenado al insertar una factura nueva ──────
        // Solo se ejecuta si es un INSERT (id=0) en la tabla facturas Y la
        // variable verifactu_activo está a '1'. Si está a '0', no se calcula
        // ningún hash ni se modifica nada — comportamiento totalmente inerte.
        if ($table === 'facturas' && $id === 0) {
            $cfgVf = $pdo->query(
                "SELECT valor FROM configuracion WHERE variable = 'verifactu_activo' LIMIT 1"
            )->fetch();
            if ($cfgVf && $cfgVf['valor'] === '1') {
                $serie = $input['serie'] ?? 'FAC';
                // Obtener el hash de la última factura no anulada de la misma serie
                $stmtUlt = $pdo->prepare(
                    "SELECT hash_factura, numero_factura, fecha_emision FROM facturas
                     WHERE serie = ? AND estado != 'anulada'
                     ORDER BY id DESC LIMIT 1"
                );
                $stmtUlt->execute([$serie]);
                $uf = $stmtUlt->fetch();
                // Primera factura de la serie → hash_anterior = '0' (literal según RD 1007/2023)
                $hashAnterior = ($uf && $uf['hash_factura']) ? $uf['hash_factura'] : '0';

                // Cadena de hash según Anexo I del RD 1007/2023 (campos separados por '|')
                $fechaEmision  = $input['fecha_emision'] ?? date('Y-m-d');
                $fechaDDMMYYYY = implode('-', array_reverse(explode('-', $fechaEmision)));
                $tsHuella      = date('d-m-Y\TH:i:s');
                $cadenaHash    = implode('|', [
                    'IDEmisorFactura='        . ($input['emisor_nif']     ?? ''),
                    'NumSerieFactura='        . ($input['numero_factura'] ?? ''),
                    'FechaExpedicionFactura=' . $fechaDDMMYYYY,
                    'TipoFactura='            . ($input['tipo_factura']   ?? 'F1'),
                    'CuotaTotal='             . number_format((float)($input['importe_iva']   ?? 0), 2, '.', ''),
                    'ImporteTotal='           . number_format((float)($input['importe_total'] ?? 0), 2, '.', ''),
                    'Huella='                 . $hashAnterior,
                    'FechaHoraHuella='        . $tsHuella,
                ]);
                // Inyectar en $input — el bucle de $values los tomará de aquí
                $input['hash_factura']     = strtoupper(hash('sha256', $cadenaHash));
                $input['hash_anterior']    = $hashAnterior;
                $input['verifactu_estado'] = 'pendiente_envio';
            }
        }

        // Construir array de valores en el mismo orden que $cols
        $values = [];
        foreach ($cols as $col) {
            $val = $input[$col] ?? null;
            // Columnas JSON: si llega como array lo codificamos, si no ponemos []
            if (in_array($col, $JSON_COLS, true)) {
                $val = is_array($val) ? json_encode($val, JSON_UNESCAPED_UNICODE) : ($val ?? '[]');
            }
            $values[] = $val;
        }

        if ($id > 0) {
            // UPDATE: actualizar registro existente
            $sets = implode(', ', array_map(function($c) { return "`$c` = ?"; }, $cols));
            $pdo->prepare("UPDATE `$table` SET $sets WHERE id = ?")->execute(array_merge($values, [$id]));
        } else {
            // INSERT: nuevo registro; recuperar el id generado automáticamente
            $colList = '`' . implode('`,`', $cols) . '`';
            $marks   = implode(',', array_fill(0, count($cols), '?'));
            $pdo->prepare("INSERT INTO `$table` ($colList) VALUES ($marks)")->execute($values);
            $id = (int)$pdo->lastInsertId();
        }

        // Devolver el objeto original con el id real (útil si era nuevo)
        $input['id'] = $id;
        json_out($input);
    }

    // ── delete: eliminar registro por id ─────────────────────
    // Valida tabla e id antes de ejecutar el DELETE.
    if ($action === 'delete') {
        $table = $_GET['table'] ?? '';
        $id    = (int)($_GET['id'] ?? 0);
        if (!validTable($table) || $id <= 0) json_out(['error' => 'Parámetros inválidos'], 400);

        $pdo->prepare("DELETE FROM `$table` WHERE id = ?")->execute([$id]);
        json_out(['ok' => true]);
    }

    // ── check: prueba de conexión ─────────────────────────────
    // La página de instalación (install.php) usa este endpoint para
    // verificar que la BD está activa antes de continuar.
    if ($action === 'check') {
        $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
        json_out(['ok' => true, 'mysql_version' => $ver]);
    }

    // Acción desconocida
    json_out(['error' => 'Acción no reconocida'], 400);

} catch (PDOException $e) {
    // Registrar en el log de Apache/MAMP para facilitar el diagnóstico
    error_log('[AlquiGest] DB error: ' . $e->getMessage());
    // Caso especial: la BD no existe todavía (primera ejecución sin install.php)
    if (strpos($e->getMessage(), 'Unknown database') !== false) {
        json_out(['error' => 'Base de datos no creada. Accede primero a install.php', 'code' => 'NO_DB'], 503);
    }
    json_out(['error' => 'Error de base de datos'], 500);
} catch (Exception $e) {
    error_log('[AlquiGest] Error: ' . $e->getMessage());
    json_out(['error' => 'Error interno del servidor'], 500);
}
