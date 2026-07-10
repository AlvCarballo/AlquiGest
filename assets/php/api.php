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
require __DIR__ . '/auth.php';
requireLocalhost();
setCorsHeaders();
session_bootstrap();

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
    // recibo_rectificado_id: id del recibo que este recibo rectifica (solo en recibos rectificativos RER-AAAA-NNNNN)
    'recibos'        => ['contrato_id','inquilino_id','inmueble_id','numero_recibo','numero_seq','fecha_emision','periodo_desde','periodo_hasta','concepto_periodo','fecha_limite','renta_base','importe_iva','importe_irpf','importe_total','importe_pagado','conceptos_extra','notas','pagos','estado','fecha_creacion','aviso_recibo','factura_id','recibo_rectificado_id','periodo_key'],
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

// ── Integridad referencial y borrado lógico ───────────────────
// Jerarquía real de la aplicación (ver README §10):
//   Propietario → Finca → Inmueble → Contrato → Recibo → Factura
//
// NINGUNA entidad de negocio se borra físicamente desde la aplicación:
//   · propietarios/fincas/inmuebles/inquilinos → borrado lógico (columna 'eliminado')
//     si no tienen dependencias; si las tienen, se bloquea el borrado.
//   · contratos/recibos/facturas → documentos con trazabilidad legal/contractual.
//     Ya tienen su propio campo 'estado' (baja/anulado/rectificada) que cumple la
//     misma función; un 'eliminado' adicional sería redundante. 'delete' se bloquea
//     siempre sin excepción: la baja/anulación/rectificación es la única vía.
$TABLAS_SIN_BORRADO_FISICO = [
    'contratos' => 'Los contratos no se pueden eliminar. Usa "Dar de baja" para finalizarlo conservando su histórico de recibos y facturas.',
    'recibos'   => 'Los recibos no se pueden eliminar físicamente. Usa la anulación (el registro se conserva para auditoría).',
    'facturas'  => 'Las facturas no se pueden eliminar físicamente. Usa la anulación/rectificación (RD 1619/2012).',
];

// Tablas con borrado lógico real: 'delete' hace UPDATE eliminado=1 en vez de DELETE,
// y quedan excluidas por defecto de getAll (ver acción 'getAll' más abajo), por lo
// que no aparecen en listados, selects, informes ni PDF.
$TABLAS_CON_BORRADO_LOGICO = ['propietarios', 'fincas', 'inmuebles', 'inquilinos'];

// Antes de marcar como eliminado, comprobamos si existen registros dependientes.
// Formato: tabla => [ [tabla_hija, columna_fk, etiqueta, respetaEliminado], ... ]
// 'respetaEliminado' = true cuando la tabla_hija también tiene columna 'eliminado':
// en ese caso solo cuentan como dependencia las filas hija que sigan activas
// (una finca ya eliminada, sin inmuebles, no debe bloquear borrar su propietario).
$DEPENDENCIAS_BORRADO = [
    'propietarios' => [
        ['fincas', 'propietario_id', 'finca(s) asociada(s)', true],
    ],
    'fincas' => [
        ['inmuebles', 'finca_id', 'inmueble(s) asociado(s)', true],
    ],
    'inmuebles' => [
        ['contratos', 'inmueble_id', 'contrato(s) asociado(s)', false],
        ['recibos',   'inmueble_id', 'recibo(s) asociado(s)', false],
        ['facturas',  'inmueble_id', 'factura(s) asociada(s)', false],
    ],
    'inquilinos' => [
        ['contratos', 'inquilino_id', 'contrato(s) asociado(s)', false],
        ['recibos',   'inquilino_id', 'recibo(s) asociado(s)', false],
        ['facturas',  'inquilino_id', 'factura(s) asociada(s)', false],
    ],
];

// Nombre legible de la entidad para el mensaje de error.
$NOMBRE_ENTIDAD = [
    'propietarios' => 'este propietario',
    'fincas'       => 'esta finca',
    'inmuebles'    => 'este inmueble',
    'inquilinos'   => 'este inquilino',
];

/**
 * Comprueba si la tabla/id tienen registros dependientes que impidan el borrado (lógico o físico).
 * Devuelve null si se puede borrar, o un array ['error'=>..,'details'=>[...]] si no.
 */
function comprobarDependencias(PDO $pdo, string $table, int $id): ?array {
    global $DEPENDENCIAS_BORRADO, $NOMBRE_ENTIDAD;
    if (!isset($DEPENDENCIAS_BORRADO[$table])) return null;

    $details = [];
    $partes  = [];
    foreach ($DEPENDENCIAS_BORRADO[$table] as [$tablaHija, $columnaFk, $etiqueta, $respetaEliminado]) {
        $sql = "SELECT COUNT(*) FROM `$tablaHija` WHERE `$columnaFk` = ?";
        if ($respetaEliminado) $sql .= " AND `eliminado` = 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $n = (int)$stmt->fetchColumn();
        if ($n > 0) {
            $details[$tablaHija] = $n;
            $partes[] = "$n $etiqueta";
        }
    }
    if (!$partes) return null;

    $nombre = $NOMBRE_ENTIDAD[$table] ?? 'este registro';
    return [
        'error'   => "No se puede eliminar $nombre porque tiene " . implode(' y ', $partes) . '.',
        'details' => $details,
    ];
}

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

    // ── Migración: borrado lógico (propietarios, fincas, inmuebles, inquilinos) ──
    // Estas tablas nunca se borran físicamente: 'delete' marca eliminado=1 en su lugar
    // (ver comprobarDependencias() y la acción 'delete' más abajo).
    foreach (['propietarios', 'fincas', 'inmuebles', 'inquilinos'] as $tablaBorradoLogico) {
        $colsTabla = array_column($pdo->query("SHOW COLUMNS FROM `$tablaBorradoLogico`")->fetchAll(), 'Field');
        if (!in_array('eliminado', $colsTabla, true)) {
            $pdo->exec("ALTER TABLE `$tablaBorradoLogico` ADD COLUMN `eliminado` TINYINT(1) NOT NULL DEFAULT 0");
        }
        if (!in_array('eliminado_en', $colsTabla, true)) {
            $pdo->exec("ALTER TABLE `$tablaBorradoLogico` ADD COLUMN `eliminado_en` DATETIME NULL DEFAULT NULL");
        }
    }

    // ── Migración: columna recibo_rectificado_id en recibos (recibos rectificativos RER) ──
    $existingColsRecibos = array_column($pdo->query("SHOW COLUMNS FROM `recibos`")->fetchAll(), 'Field');
    if (!in_array('recibo_rectificado_id', $existingColsRecibos)) {
        $pdo->exec("ALTER TABLE `recibos` ADD COLUMN `recibo_rectificado_id` INT DEFAULT NULL");
    }

    // ── Migración: columna periodo_key en recibos (protección real de duplicados) ──
    // periodo_key = "<contrato_id>-<AAAAMM del periodo_desde>", calculada SIEMPRE en el
    // servidor (ver acción 'save' más abajo), y solo para recibos "ordinarios"
    // (no anulados, no rectificativos). En cualquier otro caso queda NULL.
    // MySQL permite múltiples NULL en una UNIQUE KEY sin colisionar, así que:
    //   · como máximo un recibo ordinario por contrato+período (protección real,
    //     atómica, a prueba de condiciones de carrera — no un simple SELECT previo).
    //   · anular un recibo (o que sea RER) libera el período para reemitir.
    // Los recibos ya existentes (incl. los de datos de ejemplo) quedan con
    // periodo_key=NULL: no participan en la protección retroactivamente, mismo
    // criterio que el resto de migraciones de este bloque (no se pierden datos,
    // no se reescribe histórico). Revertir: DROP INDEX uq_recibos_periodo_key,
    // DROP COLUMN periodo_key.
    if (!in_array('periodo_key', $existingColsRecibos)) {
        $pdo->exec("ALTER TABLE `recibos` ADD COLUMN `periodo_key` VARCHAR(20) DEFAULT NULL");
    }
    try {
        $tienePeriodoKey = $pdo->query(
            "SHOW INDEX FROM `recibos` WHERE Key_name = 'uq_recibos_periodo_key'"
        )->fetch();
        if (!$tienePeriodoKey) {
            $pdo->exec("ALTER TABLE `recibos` ADD UNIQUE KEY `uq_recibos_periodo_key` (`periodo_key`)");
        }
    } catch (\PDOException $e) { /* índice ya existe o no se pudo crear: se revisará en el próximo arranque */ }

    // ── Migración: inicializar el contador ANUAL de doc_secuencias en instalaciones
    // existentes con documentos históricos en formato mensual antiguo (AAAAMM) ──
    // Los 4 tipos documentales (REC, RER, FAC, RET) usan numeración ANUAL (periodo
    // AAAA en doc_secuencias). NINGÚN documento histórico se renombra ni se
    // renumera: los numero_recibo/numero_factura ya emitidos (incluidos los de
    // formato mensual AAAAMM) se conservan tal cual, con sus enlaces, PDFs y
    // cobros intactos — esta migración solo decide con qué valor arranca el nuevo
    // contador anual, para que el primer número nuevo emitido en formato anual
    // nunca retroceda respecto a lo ya emitido ese año en formato antiguo.
    // Se ejecuta una sola vez por (tipo, año): si ya existe una fila anual para
    // ese tipo+año no se toca, para no sobrescribir un contador ya en uso.
    //
    // Valor de arranque = GREATEST(A, B):
    //   A = SUM(doc_secuencias.ultimo) de las filas MENSUALES (periodo AAAAMM) de
    //       ese año — el ledger de reservas atómicas real. Se usa SUM y no MAX
    //       porque numero_seq se reiniciaba cada mes: MAX solo daría el máximo de
    //       UN mes, no el total consumido en el año.
    //   B = recuento de documentos REALES de ese tipo y año, obtenido de
    //       recibos/facturas usando periodo_desde/fecha_emision (nunca parseando
    //       numero_recibo/numero_factura) — sirve de contraste por si el ledger
    //       de doc_secuencias estuviera incompleto respecto a los documentos
    //       realmente emitidos.
    // Se toma el MAYOR de los dos, nunca uno solo: un número ya reservado (aunque
    // el documento asociado ya no exista, p. ej. una reserva que no llegó a
    // guardarse) no debe poder reutilizarse jamás.
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `doc_secuencias` (
            `tipo`    VARCHAR(20) NOT NULL,
            `periodo` CHAR(6)     NOT NULL COMMENT 'AAAA (anual) para los 4 tipos REC/RER/FAC/RET; AAAAMM solo en filas historicas previas a la migracion',
            `ultimo`  INT         NOT NULL DEFAULT 0,
            PRIMARY KEY (`tipo`, `periodo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $hayMensuales = (int)$pdo->query(
            "SELECT COUNT(*) FROM doc_secuencias WHERE LENGTH(periodo) = 6"
        )->fetchColumn();

        if ($hayMensuales > 0) {
            $anualesExistentes = [];
            foreach ($pdo->query("SELECT tipo, periodo FROM doc_secuencias WHERE LENGTH(periodo) = 4") as $rowAnual) {
                $anualesExistentes[$rowAnual['tipo'] . '|' . $rowAnual['periodo']] = true;
            }

            $mensuales = $pdo->query(
                "SELECT tipo, SUBSTRING(periodo,1,4) AS anio, SUM(ultimo) AS suma
                 FROM doc_secuencias
                 WHERE LENGTH(periodo) = 6 AND tipo IN ('REC','RER','FAC','RET')
                 GROUP BY tipo, SUBSTRING(periodo,1,4)"
            )->fetchAll();

            foreach ($mensuales as $m) {
                $tipoMig = $m['tipo'];
                $anioMig = $m['anio'];
                if (isset($anualesExistentes[$tipoMig . '|' . $anioMig])) continue; // ya migrado o en uso: no tocar

                $sumaLedger = (int)$m['suma'];
                $countReal  = 0;
                if ($tipoMig === 'REC') {
                    $stmt = $pdo->prepare(
                        "SELECT COUNT(*) FROM recibos WHERE estado != 'rectificativo' AND YEAR(periodo_desde) = ?"
                    );
                    $stmt->execute([$anioMig]);
                    $countReal = (int)$stmt->fetchColumn();
                } elseif ($tipoMig === 'RER') {
                    $stmt = $pdo->prepare(
                        "SELECT COUNT(*) FROM recibos rer
                         INNER JOIN recibos orig ON orig.id = rer.recibo_rectificado_id
                         WHERE rer.estado = 'rectificativo' AND YEAR(orig.periodo_desde) = ?"
                    );
                    $stmt->execute([$anioMig]);
                    $countReal = (int)$stmt->fetchColumn();
                } elseif ($tipoMig === 'FAC') {
                    $stmt = $pdo->prepare(
                        "SELECT COUNT(*) FROM facturas WHERE serie != 'RET' AND YEAR(fecha_emision) = ?"
                    );
                    $stmt->execute([$anioMig]);
                    $countReal = (int)$stmt->fetchColumn();
                } elseif ($tipoMig === 'RET') {
                    $stmt = $pdo->prepare(
                        "SELECT COUNT(*) FROM facturas WHERE serie = 'RET' AND YEAR(fecha_emision) = ?"
                    );
                    $stmt->execute([$anioMig]);
                    $countReal = (int)$stmt->fetchColumn();
                }

                $arranque = max($sumaLedger, $countReal);
                if ($arranque > 0) {
                    $pdo->prepare(
                        "INSERT INTO doc_secuencias (tipo, periodo, ultimo) VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE ultimo = GREATEST(ultimo, VALUES(ultimo))"
                    )->execute([$tipoMig, $anioMig, $arranque]);
                }
            }
        }
    } catch (\PDOException $e) {
        error_log('[AlquiGest] Error en migración de numeración anual doc_secuencias: ' . $e->getMessage());
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
                `usuario_id` INT DEFAULT NULL,
                `usuario_nombre` VARCHAR(150) DEFAULT NULL,
                `usuario_username` VARCHAR(60) DEFAULT NULL,
                `usuario_rol` VARCHAR(20) DEFAULT NULL,
                `ip` VARCHAR(45) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_log_fecha` (`fecha`),
                INDEX `idx_log_tipo` (`tipo_accion`),
                INDEX `idx_log_usuario` (`usuario_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\PDOException $e) { /* tabla ya existe */ }

        // Instalaciones existentes con log_actividad anterior a la atribución de usuario:
        // añadir las columnas nuevas si faltan (misma tabla, no se recrea ni se pierden datos).
        $colsLog = array_column($pdo->query("SHOW COLUMNS FROM `log_actividad`")->fetchAll(), 'Field');
        foreach ([
            'usuario_id'       => "ALTER TABLE `log_actividad` ADD COLUMN `usuario_id` INT DEFAULT NULL",
            'usuario_nombre'   => "ALTER TABLE `log_actividad` ADD COLUMN `usuario_nombre` VARCHAR(150) DEFAULT NULL",
            'usuario_username' => "ALTER TABLE `log_actividad` ADD COLUMN `usuario_username` VARCHAR(60) DEFAULT NULL",
            'usuario_rol'      => "ALTER TABLE `log_actividad` ADD COLUMN `usuario_rol` VARCHAR(20) DEFAULT NULL",
            'ip'               => "ALTER TABLE `log_actividad` ADD COLUMN `ip` VARCHAR(45) DEFAULT NULL",
        ] as $col => $ddl) {
            if (!in_array($col, $colsLog, true)) {
                try { $pdo->exec($ddl); } catch (\PDOException $e) { /* ya existe */ }
            }
        }
        try { $pdo->exec("CREATE INDEX `idx_log_usuario` ON `log_actividad`(`usuario_id`)"); }
        catch (\PDOException $e) { /* índice ya existe */ }
    }

    // ── Migración: tabla usuarios (sistema de autenticación) ──────
    // Nunca se destruye en una reinstalación (ver install.php): solo se crea
    // aquí si todavía no existe, para instalaciones que arrancaron antes de
    // que existiera el sistema de usuarios.
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `usuarios` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `nombre` VARCHAR(150) NOT NULL,
            `email` VARCHAR(150) DEFAULT '',
            `username` VARCHAR(60) NOT NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `rol` VARCHAR(20) NOT NULL DEFAULT 'user',
            `activo` TINYINT(1) NOT NULL DEFAULT 1,
            `ultimo_login` DATETIME NULL DEFAULT NULL,
            `creado_en` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `actualizado_en` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `eliminado_en` DATETIME NULL DEFAULT NULL,
            UNIQUE KEY `uq_usuarios_username` (`username`),
            INDEX `idx_usuarios_rol` (`rol`),
            INDEX `idx_usuarios_activo` (`activo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (\PDOException $e) { /* tabla ya existe */ }

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
        "CREATE INDEX `idx_recibos_fecha_emision` ON `recibos`(`fecha_emision`)",
        "CREATE INDEX `idx_recibos_periodo_desde` ON `recibos`(`periodo_desde`)",
        "CREATE INDEX `idx_historial_contrato` ON `historial_rentas`(`contrato_id`)",
        "CREATE INDEX `idx_propietarios_eliminado` ON `propietarios`(`eliminado`)",
        "CREATE INDEX `idx_fincas_eliminado`       ON `fincas`(`eliminado`)",
        "CREATE INDEX `idx_inmuebles_eliminado`    ON `inmuebles`(`eliminado`)",
        "CREATE INDEX `idx_inquilinos_eliminado`   ON `inquilinos`(`eliminado`)",
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

    // ── Control de acceso: todas las acciones exigen sesión iniciada, ──
    // salvo 'login' (para poder autenticarse) y 'check' (ping de conexión,
    // sin datos de negocio, usado por install.php antes de tener sesión).
    // Las auto-migraciones de arriba SÍ deben ejecutarse siempre: son las que
    // crean la tabla `usuarios` en instalaciones que se actualizan a esta versión.
    $ACCIONES_PUBLICAS = ['login', 'check'];
    $usuarioActual = null;
    if (!in_array($action, $ACCIONES_PUBLICAS, true)) {
        $usuarioActual = requireLoginApi($pdo);
    }

    // ── login: autenticación de usuario ───────────────────────
    if ($action === 'login') {
        $u = trim($input['username'] ?? '');
        $p = (string)($input['password'] ?? '');
        $resultado = attemptLogin($pdo, $u, $p);
        if (!$resultado['ok']) json_out(['ok' => false, 'error' => $resultado['error']], 401);
        json_out(['ok' => true, 'user' => $resultado['user'], 'csrf' => csrfToken()]);
    }

    // ── logout: cierre de sesión ───────────────────────────────
    if ($action === 'logout') {
        doLogout($pdo, $usuarioActual);
        json_out(['ok' => true]);
    }

    // ── me: usuario autenticado actual (para refrescar la cabecera) ──
    if ($action === 'me') {
        json_out(['ok' => true, 'user' => $usuarioActual, 'csrf' => csrfToken()]);
    }

    // ── Gestión de usuarios (solo admin) ───────────────────────
    if ($action === 'listUsuarios') {
        requireRoleApi($usuarioActual, 'admin');
        $rows = $pdo->query(
            "SELECT id, nombre, email, username, rol, activo, ultimo_login, creado_en, actualizado_en
             FROM usuarios WHERE eliminado_en IS NULL ORDER BY id"
        )->fetchAll();
        // Castear tipos explícitamente: PDO devuelve todo como string y el frontend
        // compara u.id === AG_USER.id (número) para saber si es el propio usuario.
        foreach ($rows as &$r) {
            $r['id']     = (int)$r['id'];
            $r['activo'] = (int)$r['activo'];
        }
        unset($r);
        json_out(['ok' => true, 'usuarios' => $rows]);
    }

    if ($action === 'saveUsuario') {
        requireRoleApi($usuarioActual, 'admin');
        if (!csrfValid($input['_csrf'] ?? null)) {
            json_out(['ok' => false, 'error' => 'Token de seguridad inválido. Recarga la página e inténtalo de nuevo.', 'code' => 'CSRF'], 419);
        }

        $id       = (int)($input['id'] ?? 0);
        $nombre   = trim($input['nombre'] ?? '');
        $email    = trim($input['email'] ?? '');
        $username = trim($input['username'] ?? '');
        $rol      = ($input['rol'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $activo   = !empty($input['activo']) ? 1 : 0;
        $password = (string)($input['password'] ?? '');

        if ($nombre === '' || $username === '') {
            json_out(['ok' => false, 'error' => 'Nombre y usuario son obligatorios.'], 422);
        }
        if (!preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $username)) {
            json_out(['ok' => false, 'error' => 'El usuario solo puede contener letras, números, puntos, guiones y guiones bajos (mínimo 3 caracteres).'], 422);
        }

        // Username único (excluyendo el propio registro si es una edición)
        $dupSt = $pdo->prepare("SELECT id FROM usuarios WHERE username = ? AND id != ? AND eliminado_en IS NULL");
        $dupSt->execute([$username, $id]);
        if ($dupSt->fetch()) {
            json_out(['ok' => false, 'error' => 'Ya existe un usuario con ese nombre de usuario.'], 422);
        }

        if ($id > 0) {
            // Edición: no permitir quitarse a sí mismo el rol admin ni desactivarse
            // si es el único admin activo (evita quedarse sin ningún administrador).
            if (($id === (int)$usuarioActual['id']) && ($rol !== 'admin' || $activo !== 1)) {
                $otrosAdmins = (int)$pdo->query(
                    "SELECT COUNT(*) FROM usuarios WHERE rol='admin' AND activo=1 AND eliminado_en IS NULL AND id != " . (int)$id
                )->fetchColumn();
                if ($otrosAdmins === 0) {
                    json_out(['ok' => false, 'error' => 'No puedes quitarte el rol de administrador ni desactivarte: eres el único administrador activo.'], 422);
                }
            }
            $sets = "nombre=?, email=?, username=?, rol=?, activo=?";
            $vals = [$nombre, $email, $username, $rol, $activo];
            if ($password !== '') {
                if (strlen($password) < 8) json_out(['ok' => false, 'error' => 'La contraseña debe tener al menos 8 caracteres.'], 422);
                $sets .= ", password_hash=?";
                $vals[] = password_hash($password, PASSWORD_DEFAULT);
            }
            $vals[] = $id;
            $pdo->prepare("UPDATE usuarios SET $sets WHERE id = ?")->execute($vals);
            logActividad($pdo, 'usuario_editado', 'usuarios', $id, "Editado el usuario \"$username\"");
        } else {
            if (strlen($password) < 8) {
                json_out(['ok' => false, 'error' => 'La contraseña debe tener al menos 8 caracteres.'], 422);
            }
            $pdo->prepare(
                "INSERT INTO usuarios (nombre, email, username, password_hash, rol, activo) VALUES (?,?,?,?,?,?)"
            )->execute([$nombre, $email, $username, password_hash($password, PASSWORD_DEFAULT), $rol, $activo]);
            $id = (int)$pdo->lastInsertId();
            logActividad($pdo, 'usuario_creado', 'usuarios', $id, "Creado el usuario \"$username\" (rol: $rol)");
        }
        json_out(['ok' => true, 'id' => $id]);
    }

    if ($action === 'deleteUsuario') {
        requireRoleApi($usuarioActual, 'admin');
        if (!csrfValid($input['_csrf'] ?? $_GET['_csrf'] ?? null)) {
            json_out(['ok' => false, 'error' => 'Token de seguridad inválido. Recarga la página e inténtalo de nuevo.', 'code' => 'CSRF'], 419);
        }
        $id = (int)($_GET['id'] ?? $input['id'] ?? 0);
        if ($id <= 0) json_out(['ok' => false, 'error' => 'Parámetros inválidos'], 400);
        if ($id === (int)$usuarioActual['id']) {
            json_out(['ok' => false, 'error' => 'No puedes eliminar tu propio usuario mientras tienes la sesión iniciada.'], 422);
        }
        $st = $pdo->prepare("SELECT username, rol, activo FROM usuarios WHERE id = ? AND eliminado_en IS NULL");
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) json_out(['ok' => false, 'error' => 'Usuario no encontrado'], 404);
        if ($row['rol'] === 'admin' && (int)$row['activo'] === 1) {
            $otrosAdmins = (int)$pdo->query(
                "SELECT COUNT(*) FROM usuarios WHERE rol='admin' AND activo=1 AND eliminado_en IS NULL AND id != " . (int)$id
            )->fetchColumn();
            if ($otrosAdmins === 0) {
                json_out(['ok' => false, 'error' => 'No se puede eliminar: es el único administrador activo.'], 422);
            }
        }
        $pdo->prepare("UPDATE usuarios SET eliminado_en = NOW(), activo = 0 WHERE id = ?")->execute([$id]);
        logActividad($pdo, 'usuario_eliminado', 'usuarios', $id, "Eliminado el usuario \"{$row['username']}\"");
        json_out(['ok' => true]);
    }

    // ── getAll: carga inicial completa de la BD ──────────────
    // El JavaScript llama a esto una sola vez al arrancar para cachear
    // todos los datos en memoria (objeto DB._cache).
    // Los campos cifrados (gmail_pass, verifactu_cert_pass) se descifran
    // aquí antes de devolver al cliente JS para que el frontend sea transparente.
    if ($action === 'getAll') {
        global $TABLAS_CON_BORRADO_LOGICO;
        $result = [];
        foreach ($TABLES as $t) {
            // Las tablas con borrado lógico nunca envían al navegador los registros
            // marcados eliminado=1: quedan fuera de listados, selects, informes y PDF
            // sin necesidad de filtrar en cada pantalla (ver acción 'delete' más abajo).
            $where = in_array($t, $TABLAS_CON_BORRADO_LOGICO, true) ? ' WHERE `eliminado` = 0' : '';
            $rows    = $pdo->query("SELECT * FROM `$t`$where ORDER BY id")->fetchAll();
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
    // El usuario se toma siempre de la sesión del servidor (nunca del cliente).
    if ($action === 'log') {
        if (empty($cfg['log_actividad'])) {
            json_out(['ok' => true, 'skip' => true]);
        }
        $tipo    = trim($input['tipo_accion']  ?? '');
        $entidad = trim($input['entidad']      ?? '');
        $entId   = isset($input['entidad_id']) ? (int)$input['entidad_id'] : null;
        $desc    = trim($input['descripcion']  ?? '');
        if (!$tipo) json_out(['ok' => false, 'error' => 'tipo_accion requerido'], 400);
        logActividad($pdo, $tipo, $entidad, $entId, $desc);
        json_out(['ok' => true]);
    }

    // ── getLog: obtener registros de actividad con filtros ────
    // Devuelve los últimos N registros (defecto 100, máx 200).
    // Filtros opcionales: tipo, desde, hasta (GET) y usuario_id (GET).
    if ($action === 'getLog') {
        if (empty($cfg['log_actividad'])) json_out([]);
        $tipo    = trim($_GET['tipo']  ?? '');
        $desde   = trim($_GET['desde'] ?? '');
        $hasta   = trim($_GET['hasta'] ?? '');
        $usrId   = trim($_GET['usuario_id'] ?? '');
        $limite  = min(200, max(1, (int)($_GET['limite'] ?? 100)));
        $where   = ['1=1'];
        $params  = [];
        if ($tipo)  { $where[] = 'tipo_accion = ?'; $params[] = $tipo; }
        if ($desde) { $where[] = 'fecha >= ?';      $params[] = $desde . ' 00:00:00'; }
        if ($hasta) { $where[] = 'fecha <= ?';      $params[] = $hasta . ' 23:59:59'; }
        if ($usrId !== '') {
            if ($usrId === '0') { $where[] = 'usuario_id IS NULL'; }
            else                { $where[] = 'usuario_id = ?'; $params[] = (int)$usrId; }
        }
        $sql = "SELECT * FROM `log_actividad` WHERE " . implode(' AND ', $where) .
               " ORDER BY id DESC LIMIT " . $limite;
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $result = array_map(function ($r) {
            return [
                'id'               => (int)$r['id'],
                'fecha'            => $r['fecha'],
                'tipo_accion'      => $r['tipo_accion'],
                'entidad'          => $r['entidad'],
                'entidad_id'       => $r['entidad_id'] !== null ? (int)$r['entidad_id'] : null,
                'descripcion'      => $r['descripcion'],
                'usuario_id'       => isset($r['usuario_id']) && $r['usuario_id'] !== null ? (int)$r['usuario_id'] : null,
                'usuario_nombre'   => $r['usuario_nombre']   ?? null,
                'usuario_username' => $r['usuario_username'] ?? null,
                'usuario_rol'      => $r['usuario_rol']      ?? null,
                'ip'               => $r['ip'] ?? null,
                'created_at'       => $r['created_at'],
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
    function validarDatos(string $tabla, array $datos, ?PDO $pdo = null): ?string {
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
                // Los recibos rectificativos (estado='rectificativo', numeración RER-AAAA-NNNNN)
                // llevan importes negados a propósito: cancelan en los totales al recibo original.
                $esRectificativoRec = ($datos['estado'] ?? '') === 'rectificativo';
                if (!isset($datos['importe_total']) || (!$esRectificativoRec && (float)$datos['importe_total'] < 0)) {
                    return 'El importe total del recibo no puede ser negativo.';
                }
                if (!isset($datos['renta_base']) || (!$esRectificativoRec && (float)$datos['renta_base'] < 0)) {
                    return 'La renta base del recibo no puede ser negativa.';
                }
                // ── Anulación de un recibo: protecciones server-side ──────────────
                // No basta con la validación del navegador: si alguien llama directamente
                // al endpoint para poner estado='anulado' en un recibo, se comprueba el
                // estado previo real en BD (no lo que diga el payload) antes de permitirlo.
                //
                // 1) Recibo con factura EMITIDA asociada: no puede anularse hasta que esa
                //    factura deje de estar 'emitida' (es decir, hasta que ya se haya
                //    rectificado generando su RET). El flujo normal (anularRecibo() en
                //    recibos-cobro.js) siempre rectifica la factura ANTES de guardar el
                //    recibo como anulado, así que cuando esta comprobación se ejecuta la
                //    factura ya está 'rectificada' en BD. Si sigue 'emitida' es que no se
                //    ha completado ese paso (o que alguien ha llamado al endpoint
                //    directamente sin pasar por el flujo) — se rechaza sin excepción.
                // 2) Recibo cobrado (o con cobros parciales) SIN factura asociada: exige
                //    que el payload incluya `confirmar_devolucion` (lo envía anularRecibo()
                //    tras preguntar al usuario si quiere devolver el cobro). Solo aplica
                //    cuando no hay factura: es el único caso en que se genera
                //    automáticamente el recibo rectificativo (RER) con el cobro en negativo.
                if ($pdo && ($datos['estado'] ?? '') === 'anulado' && !empty($datos['id'])) {
                    $stmtPrevRec = $pdo->prepare('SELECT estado, importe_pagado, factura_id FROM recibos WHERE id = ?');
                    $stmtPrevRec->execute([(int)$datos['id']]);
                    $prevRec = $stmtPrevRec->fetch();
                    if ($prevRec) {
                        $facturaEstadoRec = null;
                        if (!empty($prevRec['factura_id'])) {
                            $stmtFacRec = $pdo->prepare('SELECT estado FROM facturas WHERE id = ?');
                            $stmtFacRec->execute([(int)$prevRec['factura_id']]);
                            $facRec = $stmtFacRec->fetch();
                            $facturaEstadoRec = $facRec ? $facRec['estado'] : null;
                        }

                        if ($facturaEstadoRec === 'emitida') {
                            return 'Este recibo tiene una factura emitida asociada. Anula primero la factura (se generará su rectificativa) antes de anular el recibo.';
                        }

                        if ($facturaEstadoRec === null) {
                            $estabaCobrado = in_array($prevRec['estado'], ['cobrado', 'parcial'], true)
                                || (float)($prevRec['importe_pagado'] ?? 0) > 0;
                            if ($estabaCobrado && empty($datos['confirmar_devolucion'])) {
                                return 'El recibo ya está cobrado. Debe confirmar la devolución del cobro para poder anularlo.';
                            }
                        }
                    }
                }

                // ── Alta de un recibo ordinario: el backend nunca confía ciegamente en
                // el cliente. Se exige un contrato REAL (no solo un id positivo) y un
                // período con fecha válida — igual da si la llamada viene de la UI o
                // directamente del endpoint. No aplica a los rectificativos (RER), que
                // no representan un período propio. La detección del recibo DUPLICADO
                // en sí (mismo contrato+período) la garantiza la UNIQUE KEY
                // uq_recibos_periodo_key (ver acción 'save'), no esta función.
                if ($pdo && empty($datos['id']) && !$esRectificativoRec) {
                    $contratoIdRec = (int)($datos['contrato_id'] ?? 0);
                    if ($contratoIdRec <= 0) {
                        return 'El recibo debe tener un contrato asignado.';
                    }
                    if (empty($datos['periodo_desde']) || strtotime((string)$datos['periodo_desde']) === false) {
                        return 'El recibo debe tener un período (periodo_desde) válido.';
                    }
                    $periodoHastaRec = (!empty($datos['periodo_hasta']) && strtotime((string)$datos['periodo_hasta']) !== false)
                        ? $datos['periodo_hasta'] : $datos['periodo_desde'];

                    $stmtContRec = $pdo->prepare('SELECT estado, fecha_inicio, fecha_baja FROM contratos WHERE id = ?');
                    $stmtContRec->execute([$contratoIdRec]);
                    $contRec = $stmtContRec->fetch();
                    if (!$contRec) {
                        return 'El contrato indicado no existe.';
                    }
                    // Un contrato iniciado A MITAD del período sí puede tener recibo de ese
                    // período (no se prorratea); uno iniciado DESPUÉS del período, no.
                    if (!empty($contRec['fecha_inicio']) && strtotime($periodoHastaRec) < strtotime($contRec['fecha_inicio'])) {
                        return 'El período del recibo es anterior a la fecha de inicio del contrato.';
                    }
                    // Un contrato no activo (finalizado/rescindido) no genera recibos nuevos,
                    // igual que ya impide el frontend (modalGenerarRecibo en recibos-cobro.js) —
                    // aquí se repite la comprobación porque el backend no puede confiar en que
                    // toda petición pase por esa pantalla. NOTA: `fecha_fin` (duración pactada)
                    // NO se usa aquí: numerosos contratos de la aplicación siguen 'activo' más
                    // allá de su fecha_fin nominal en espera de renovación formal (comportamiento
                    // real y esperado de la app), así que solo `estado` es una señal fiable.
                    if (($contRec['estado'] ?? '') !== 'activo') {
                        return 'El contrato no está activo: no se pueden generar recibos nuevos para él.';
                    }
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
                // La tabla `inmuebles` no tiene columna `nombre` (ver $SCHEMA['inmuebles']);
                // el campo real obligatorio del formulario (assets/js/inmuebles.js) es `planta`.
                if (empty(trim($datos['planta'] ?? ''))) {
                    return 'La planta del inmueble es obligatoria.';
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

    // ── nextNumeroDoc: reserva el siguiente número de secuencia documental ──
    // Devuelve { seq, numero, tipo, periodo } de forma atómica (SELECT FOR UPDATE
    // dentro de una transacción InnoDB). Es imposible generar duplicados aunque
    // varios procesos llamen simultáneamente para el mismo tipo y periodo.
    // Los 4 tipos (REC/RER/FAC/RET) usan numeración ANUAL (periodo AAAA). Fuente
    // del año según tipo: REC → periodo_desde del propio recibo; RER → periodo_desde
    // del recibo ORIGINAL rectificado; FAC/RET → fecha_emision del propio documento.
    // Nunca se sustituye por la fecha actual del servidor.
    // Uso: ?action=nextNumeroDoc&tipo=REC&periodo=2026&prefijo=REC
    //      ?action=nextNumeroDoc&tipo=FAC&periodo=2026&prefijo=FAC
    if ($action === 'nextNumeroDoc') {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            json_out(['error' => 'Método no permitido'], 405);
        }
        $tipo = preg_replace('/[^A-Z]/', '', strtoupper($_GET['tipo'] ?? ''));
        // 'periodo' es OBLIGATORIO y explícito: si no llega en la petición NO se
        // sustituye por date('Y') (la fecha actual del servidor). Ese fallback
        // silencioso era la causa raíz de que un documento de un período pasado
        // pudiera terminar numerado con el año de HOY si el llamante omitía el
        // parámetro.
        $periodoRaw = $_GET['periodo'] ?? '';
        $periodo    = preg_replace('/[^0-9]/', '', $periodoRaw);
        $prefijo    = preg_replace('/[^A-Z0-9\-]/', '', strtoupper($_GET['prefijo'] ?? $tipo));

        // Validación única para los 4 tipos: periodo debe ser AAAA (año razonable).
        // Un tipo no reconocido, o un formato incorrecto (p.ej. 6 dígitos AAAAMM,
        // el formato histórico ya retirado), se rechaza explícitamente — nunca se
        // recorta ni se ajusta el valor recibido para "hacerlo válido".
        $TIPOS_DOC = ['REC', 'RER', 'FAC', 'RET'];
        if (in_array($tipo, $TIPOS_DOC, true)) {
            $periodoValido   = (bool)preg_match('/^20\d{2}$/', $periodo);
            $formatoEsperado = 'AAAA (año 2000-2099)';
        } else {
            $periodoValido   = false;
            $formatoEsperado = null;
        }

        if (!$tipo || !$periodoValido) {
            $msg = $formatoEsperado
                ? "Parámetros inválidos: para el tipo $tipo, periodo debe tener formato $formatoEsperado"
                : 'Parámetros inválidos: tipo de documento no reconocido';
            json_out(['error' => $msg], 400);
        }

        // Crear la tabla si aún no existe (instalaciones antiguas sin migración).
        // `periodo` es CHAR(6): admite tanto el histórico AAAAMM (6 caracteres, ya
        // no se genera pero sigue existiendo en filas antiguas) como el AAAA actual
        // (4 caracteres) sin ningún problema — MySQL retira el relleno de espacios
        // de un CHAR al leerlo (salvo con el sql_mode no habitual
        // PAD_CHAR_TO_FULL_LENGTH), así que no hace falta VARCHAR ni migración de
        // esquema para admitir ambos formatos a la vez.
        $pdo->exec("CREATE TABLE IF NOT EXISTS `doc_secuencias` (
            `tipo`    VARCHAR(20) NOT NULL,
            `periodo` CHAR(6)     NOT NULL COMMENT 'AAAA (anual) para los 4 tipos REC/RER/FAC/RET; AAAAMM solo en filas historicas previas a la migracion',
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
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            json_out(['error' => 'Método no permitido'], 405);
        }
        global $SCHEMA, $JSON_COLS;
        $table = $_GET['table'] ?? '';
        if (!validTable($table)) json_out(['error' => 'Tabla no válida'], 400);

        // Validar los datos antes de persistirlos
        $errorValidacion = validarDatos($table, $input, $pdo);
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
        $esAlta = ($id <= 0);
        $cols = $SCHEMA[$table] ?? [];

        // ── periodo_key: clave real de duplicados de un recibo, calculada SIEMPRE
        // en el servidor (nunca se confía en lo que envíe el cliente). Un recibo
        // "ordinario" (no anulado, no rectificativo) solo puede existir una vez
        // por contrato+período — lo garantiza la UNIQUE KEY uq_recibos_periodo_key
        // (ver migración más arriba), no una simple comprobación previa: así queda
        // protegido también frente a dos peticiones concurrentes para el mismo
        // contrato y período (condición de carrera).
        if ($table === 'recibos') {
            $esOrdinario = !in_array($input['estado'] ?? '', ['anulado', 'rectificativo'], true)
                && empty($input['recibo_rectificado_id']);
            if ($esOrdinario && !empty($input['contrato_id']) && !empty($input['periodo_desde'])) {
                $ts = strtotime((string)$input['periodo_desde']);
                $input['periodo_key'] = $ts !== false
                    ? ((int)$input['contrato_id'] . '-' . date('Ym', $ts))
                    : null;
            } else {
                $input['periodo_key'] = null;
            }
        }

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

        try {
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
        } catch (\PDOException $e) {
            // Violación de uq_recibos_periodo_key: ya existe un recibo ordinario
            // para este contrato y este período. Traducido a un mensaje de negocio
            // claro en vez de dejar pasar el error crudo de MySQL al cliente.
            if ($table === 'recibos' && (int)$e->getCode() === 23000 && strpos($e->getMessage(), 'uq_recibos_periodo_key') !== false) {
                json_out(['error' => 'Ya existe un recibo para este contrato en este período.', 'code' => 'RECIBO_DUPLICADO'], 409);
            }
            throw $e;
        }

        // Registrar en el log de actividad las altas/modificaciones de las entidades
        // principales de negocio (propietarios, fincas, inmuebles, inquilinos, contratos).
        // Recibos y facturas no se registran aquí: sus eventos relevantes (cobro, anulación,
        // factura generada...) ya se registran de forma más descriptiva desde el frontend.
        if (in_array($table, ['propietarios', 'fincas', 'inmuebles', 'inquilinos', 'contratos'], true)) {
            logActividad($pdo, ($esAlta ? 'alta_' : 'modificacion_') . $table, $table, $id,
                ($esAlta ? 'Alta' : 'Modificación') . " en $table #$id");
        }

        // Devolver el objeto original con el id real (útil si era nuevo)
        $input['id'] = $id;
        json_out($input);
    }

    // ── delete: eliminar registro por id ──────────────────────
    // Ninguna entidad de negocio se borra físicamente:
    //   · contratos/recibos/facturas → bloqueado siempre (usar baja/anulación/rectificación).
    //   · propietarios/fincas/inmuebles/inquilinos → borrado LÓGICO (UPDATE eliminado=1)
    //     si no hay dependencias; si las hay, se bloquea con el detalle del motivo.
    if ($action === 'delete') {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            json_out(['error' => 'Método no permitido'], 405);
        }
        global $TABLAS_SIN_BORRADO_FISICO, $TABLAS_CON_BORRADO_LOGICO;
        $table = $_GET['table'] ?? '';
        $id    = (int)($_GET['id'] ?? 0);
        if (!validTable($table) || $id <= 0) json_out(['error' => 'Parámetros inválidos'], 400);

        // Documentos con trazabilidad legal/contractual: nunca se borran físicamente
        // y no tienen equivalente de borrado lógico adicional (ya usan 'estado').
        if (isset($TABLAS_SIN_BORRADO_FISICO[$table])) {
            json_out([
                'ok'    => false,
                'error' => $TABLAS_SIN_BORRADO_FISICO[$table],
                'code'  => 'DELETE_NOT_ALLOWED',
            ], 409);
        }

        // Resto de tablas de negocio: bloquear si tienen registros dependientes activos.
        $dep = comprobarDependencias($pdo, $table, $id);
        if ($dep !== null) {
            json_out([
                'ok'      => false,
                'error'   => $dep['error'],
                'code'    => 'ENTITY_HAS_DEPENDENCIES',
                'details' => $dep['details'],
            ], 409);
        }

        // Borrado lógico: el registro se conserva en BD (auditoría/histórico) pero
        // queda excluido de getAll, por lo que desaparece de listados, selects,
        // informes y PDF sin necesidad de tocar cada pantalla.
        if (in_array($table, $TABLAS_CON_BORRADO_LOGICO, true)) {
            $stmt = $pdo->prepare("UPDATE `$table` SET `eliminado` = 1, `eliminado_en` = NOW() WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() === 0) json_out(['ok' => false, 'error' => 'Registro no encontrado'], 404);
            logActividad($pdo, 'eliminacion_logica', $table, $id, "Eliminación lógica en $table #$id");
            json_out(['ok' => true, 'message' => 'Registro eliminado correctamente.', 'logical_delete' => true]);
        }

        // Cualquier otra tabla de la lista blanca (ej. configuracion, contratos_inq_sec,
        // empresa) sin trazabilidad legal ni jerarquía de negocio: sin botón "Eliminar"
        // en la interfaz, se mantiene el borrado físico simple existente.
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
