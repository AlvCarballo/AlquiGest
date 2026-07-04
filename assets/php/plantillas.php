<?php
// ============================================================
//  AlquiGest – Motor de plantillas DOCX
//
//  Acciones disponibles (POST JSON o GET con parámetro action=):
//    list            → listado de todas las plantillas
//    upload          → subir nueva plantilla DOCX (multipart/form-data)
//    delete          → eliminar plantilla y su fichero
//    rename          → renombrar (campo 'nombre' visible, no el fichero en disco)
//    duplicate       → duplicar plantilla (copia fichero + nuevo registro BD)
//    setDefault      → marcar como plantilla por defecto para su tipo
//    setActiva       → activar o desactivar una plantilla
//    download        → descargar fichero DOCX original (stream binario)
//    generar         → generar DOCX con variables sustituidas (stream binario)
//    generarConFotos → generar DOCX con fotos embebidas (multipart/form-data)
//    preview         → previsualizar como HTML las variables resueltas
//    variables       → devolver el catálogo completo de variables disponibles
//    analizar        → detectar si la plantilla usa {{FotosContrato}}
//
//  Seguridad:
//    · Solo localhost
//    · Solo ficheros .docx (extensión + MIME)
//    · Tamaño máximo 10 MB
//    · Nombre de fichero en disco = UUID + timestamp (no expone nombres originales)
//    · No se admiten rutas fuera de PLANTILLAS_DIR
// ============================================================

$cfg = require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
requireLocalhost();

// ── Constantes ────────────────────────────────────────────────
define('PLANTILLAS_DIR', realpath(__DIR__ . '/../../uploads/plantillas') . DIRECTORY_SEPARATOR);
define('MAX_DOCX_BYTES', 10 * 1024 * 1024); // 10 MB

// ── Conexión a BD ─────────────────────────────────────────────
$pdo = new PDO(
    "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset={$cfg['charset']}",
    $cfg['user'], $cfg['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

// Auto-crear la tabla si no existe (migración transparente)
$pdo->exec("CREATE TABLE IF NOT EXISTS `plantillas` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `nombre`         VARCHAR(150) NOT NULL,
    `tipo_documento` VARCHAR(50)  NOT NULL DEFAULT 'otro',
    `descripcion`    TEXT         DEFAULT NULL,
    `fichero`        VARCHAR(255) NOT NULL,
    `activa`         TINYINT(1)  DEFAULT 1,
    `por_defecto`    TINYINT(1)  DEFAULT 0,
    `created_at`     DATETIME    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Dispatcher ────────────────────────────────────────────────
$action = $_GET['action'] ?? $_POST['action'] ?? '';
if (!$action) {
    // Leer del body JSON si no viene como GET/POST directo
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = $body['action'] ?? '';
} else {
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
}

// Para acciones que devuelven JSON, establecer cabecera antes de cada función
switch ($action) {
    case 'list':       accionList($pdo);       break;
    case 'upload':     accionUpload($pdo);     break;
    case 'delete':     accionDelete($pdo, $body); break;
    case 'rename':     accionRename($pdo, $body); break;
    case 'duplicate':  accionDuplicate($pdo, $body); break;
    case 'setDefault': accionSetDefault($pdo, $body); break;
    case 'setActiva':  accionSetActiva($pdo, $body);  break;
    case 'download':   accionDownload($pdo);   break;
    case 'generar':         accionGenerar($pdo, $body);        break;
    case 'generarConFotos': accionGenerarConFotos($pdo);       break;
    case 'preview':         accionPreview($pdo, $body);        break;
    case 'variables':       accionVariables();                 break;
    case 'analizar':        accionAnalizarPlantilla($pdo);     break;
    default:
        setCorsHeaders();
        json_respond(['ok' => false, 'error' => "Acción desconocida: $action"], 400);
}

// ============================================================
//  ACCIONES CRUD
// ============================================================

// ── Listar plantillas ─────────────────────────────────────────
function accionList(PDO $pdo): void {
    setCorsHeaders();
    $rows = $pdo->query("SELECT * FROM plantillas ORDER BY tipo_documento, nombre")->fetchAll();
    json_respond(['ok' => true, 'plantillas' => array_map('normalizarPlantilla', $rows)]);
}

// ── Subir nueva plantilla ─────────────────────────────────────
function accionUpload(PDO $pdo): void {
    setCorsHeaders();

    // Validar fichero subido
    if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        $codigosError = [
            UPLOAD_ERR_INI_SIZE   => 'El fichero supera upload_max_filesize en php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'El fichero supera el tamaño máximo del formulario.',
            UPLOAD_ERR_PARTIAL    => 'El fichero se subió parcialmente.',
            UPLOAD_ERR_NO_FILE    => 'No se recibió ningún fichero.',
            UPLOAD_ERR_NO_TMP_DIR => 'No existe directorio temporal en el servidor.',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir en disco.',
            UPLOAD_ERR_EXTENSION  => 'Una extensión PHP bloqueó la subida.',
        ];
        $cod = $_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE;
        json_respond(['ok' => false, 'error' => $codigosError[$cod] ?? 'Error de subida desconocido.'], 400);
        return;
    }

    // Validar extensión
    $nombreOriginal = $_FILES['archivo']['name'] ?? '';
    $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
    if ($ext !== 'docx') {
        json_respond(['ok' => false, 'error' => 'Solo se admiten ficheros .docx'], 400);
        return;
    }

    // Validar tamaño
    if ($_FILES['archivo']['size'] > MAX_DOCX_BYTES) {
        json_respond(['ok' => false, 'error' => 'El fichero supera el límite de 10 MB.'], 400);
        return;
    }

    // Validar que es un ZIP/DOCX real (los DOCX son ZIPs)
    if (!validarDocx($_FILES['archivo']['tmp_name'])) {
        json_respond(['ok' => false, 'error' => 'El fichero no es un DOCX válido o está dañado.'], 400);
        return;
    }

    // Generar nombre seguro en disco (sin exponer el nombre original)
    $nombreFichero = generarNombreFichero();
    $rutaDestino   = PLANTILLAS_DIR . $nombreFichero;

    if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $rutaDestino)) {
        json_respond(['ok' => false, 'error' => 'No se pudo guardar el fichero en el servidor.'], 500);
        return;
    }

    // Obtener datos del formulario
    $nombre        = trim($_POST['nombre']        ?? '') ?: pathinfo($nombreOriginal, PATHINFO_FILENAME);
    $tipoDoc       = sanitizarTipoDoc($_POST['tipo_documento'] ?? 'otro');
    $descripcion   = trim($_POST['descripcion']   ?? '');
    $esPorDefecto  = (int)($_POST['por_defecto']  ?? 0);

    // Si se marca como por defecto, desmarcar el anterior de ese tipo
    if ($esPorDefecto) {
        $pdo->prepare("UPDATE plantillas SET por_defecto = 0 WHERE tipo_documento = ?")->execute([$tipoDoc]);
    }

    $stmt = $pdo->prepare("INSERT INTO plantillas (nombre, tipo_documento, descripcion, fichero, activa, por_defecto)
                           VALUES (?, ?, ?, ?, 1, ?)");
    $stmt->execute([$nombre, $tipoDoc, $descripcion ?: null, $nombreFichero, $esPorDefecto]);
    $id = (int)$pdo->lastInsertId();

    $plantilla = $pdo->prepare("SELECT * FROM plantillas WHERE id = ?");
    $plantilla->execute([$id]);
    json_respond(['ok' => true, 'plantilla' => normalizarPlantilla($plantilla->fetch())]);
}

// ── Eliminar plantilla ────────────────────────────────────────
function accionDelete(PDO $pdo, array $body): void {
    setCorsHeaders();
    $id = (int)($body['id'] ?? 0);
    if (!$id) { json_respond(['ok' => false, 'error' => 'ID requerido'], 400); return; }

    $stmt = $pdo->prepare("SELECT fichero FROM plantillas WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { json_respond(['ok' => false, 'error' => 'Plantilla no encontrada'], 404); return; }

    // Eliminar fichero de disco (solo si existe y está dentro de PLANTILLAS_DIR)
    $rutaFichero = PLANTILLAS_DIR . basename($row['fichero']);
    if (file_exists($rutaFichero) && strpos(realpath($rutaFichero), PLANTILLAS_DIR) === 0) {
        unlink($rutaFichero);
    }

    $pdo->prepare("DELETE FROM plantillas WHERE id = ?")->execute([$id]);
    json_respond(['ok' => true]);
}

// ── Renombrar plantilla ───────────────────────────────────────
function accionRename(PDO $pdo, array $body): void {
    setCorsHeaders();
    $id     = (int)($body['id']     ?? 0);
    $nombre = trim($body['nombre']  ?? '');
    if (!$id || !$nombre) {
        json_respond(['ok' => false, 'error' => 'ID y nombre requeridos'], 400);
        return;
    }
    $pdo->prepare("UPDATE plantillas SET nombre = ? WHERE id = ?")->execute([$nombre, $id]);
    json_respond(['ok' => true]);
}

// ── Duplicar plantilla ────────────────────────────────────────
function accionDuplicate(PDO $pdo, array $body): void {
    setCorsHeaders();
    $id = (int)($body['id'] ?? 0);
    if (!$id) { json_respond(['ok' => false, 'error' => 'ID requerido'], 400); return; }

    $stmt = $pdo->prepare("SELECT * FROM plantillas WHERE id = ?");
    $stmt->execute([$id]);
    $orig = $stmt->fetch();
    if (!$orig) { json_respond(['ok' => false, 'error' => 'Plantilla no encontrada'], 404); return; }

    // Copiar fichero físico
    $nuevoFichero = generarNombreFichero();
    $rutaOrig     = PLANTILLAS_DIR . basename($orig['fichero']);
    $rutaDest     = PLANTILLAS_DIR . $nuevoFichero;
    if (!file_exists($rutaOrig) || !copy($rutaOrig, $rutaDest)) {
        json_respond(['ok' => false, 'error' => 'No se pudo copiar el fichero de la plantilla.'], 500);
        return;
    }

    // Nuevo nombre con sufijo
    $nuevoNombre = $orig['nombre'] . ' (copia)';

    $ins = $pdo->prepare("INSERT INTO plantillas (nombre, tipo_documento, descripcion, fichero, activa, por_defecto)
                          VALUES (?, ?, ?, ?, ?, 0)");
    $ins->execute([$nuevoNombre, $orig['tipo_documento'], $orig['descripcion'], $nuevoFichero, $orig['activa']]);
    $nuevoId = (int)$pdo->lastInsertId();

    $nuevo = $pdo->prepare("SELECT * FROM plantillas WHERE id = ?");
    $nuevo->execute([$nuevoId]);
    json_respond(['ok' => true, 'plantilla' => normalizarPlantilla($nuevo->fetch())]);
}

// ── Marcar / quitar como por defecto ─────────────────────────
function accionSetDefault(PDO $pdo, array $body): void {
    setCorsHeaders();
    $id      = (int)($body['id']            ?? 0);
    $tipoDoc = sanitizarTipoDoc($body['tipo_documento'] ?? '');
    $valor   = isset($body['valor']) ? (int)$body['valor'] : 1;
    if (!$id) { json_respond(['ok' => false, 'error' => 'ID requerido'], 400); return; }

    if ($valor) {
        // Desmarcar todas las del mismo tipo y marcar la seleccionada
        $pdo->prepare("UPDATE plantillas SET por_defecto = 0 WHERE tipo_documento = ?")->execute([$tipoDoc]);
        $pdo->prepare("UPDATE plantillas SET por_defecto = 1 WHERE id = ?")->execute([$id]);
    } else {
        // Solo quitar el por_defecto de esta plantilla
        $pdo->prepare("UPDATE plantillas SET por_defecto = 0 WHERE id = ?")->execute([$id]);
    }
    json_respond(['ok' => true]);
}

// ── Activar / desactivar ──────────────────────────────────────
function accionSetActiva(PDO $pdo, array $body): void {
    setCorsHeaders();
    $id     = (int)($body['id']    ?? 0);
    $activa = (int)($body['activa'] ?? 0);
    if (!$id) { json_respond(['ok' => false, 'error' => 'ID requerido'], 400); return; }
    $pdo->prepare("UPDATE plantillas SET activa = ? WHERE id = ?")->execute([$activa, $id]);
    json_respond(['ok' => true]);
}

// ── Descargar fichero original ────────────────────────────────
function accionDownload(PDO $pdo): void {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { http_response_code(400); die('ID requerido'); }

    $stmt = $pdo->prepare("SELECT nombre, fichero FROM plantillas WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { http_response_code(404); die('Plantilla no encontrada'); }

    // Validar ruta (anti-path-traversal)
    $rutaFichero = PLANTILLAS_DIR . basename($row['fichero']);
    if (!file_exists($rutaFichero) || strpos(realpath($rutaFichero), PLANTILLAS_DIR) !== 0) {
        http_response_code(404);
        die('Fichero no encontrado en el servidor');
    }

    // Nombre de descarga limpio (nombre visible al usuario + .docx)
    $nombreDescarga = preg_replace('/[^a-zA-Z0-9_\-áéíóúÁÉÍÓÚñÑüÜ ]/', '_', $row['nombre']) . '.docx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $nombreDescarga . '"');
    header('Content-Length: ' . filesize($rutaFichero));
    header('Cache-Control: no-cache, no-store');
    readfile($rutaFichero);
    exit;
}

// ── Generar DOCX con variables sustituidas ────────────────────
function accionGenerar(PDO $pdo, array $body): void {
    $plantillaId = (int)($body['plantilla_id'] ?? $_GET['plantilla_id'] ?? 0);
    $tipoEnt     = $body['tipo']       ?? $_GET['tipo']       ?? '';
    $entidadId   = (int)($body['entidad_id'] ?? $_GET['entidad_id'] ?? 0);

    if (!$plantillaId) {
        setCorsHeaders();
        json_respond(['ok' => false, 'error' => 'plantilla_id requerido'], 400);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM plantillas WHERE id = ?");
    $stmt->execute([$plantillaId]);
    $plantilla = $stmt->fetch();
    if (!$plantilla) {
        setCorsHeaders();
        json_respond(['ok' => false, 'error' => 'Plantilla no encontrada'], 404);
        return;
    }

    $rutaOrig = PLANTILLAS_DIR . basename($plantilla['fichero']);
    if (!file_exists($rutaOrig)) {
        setCorsHeaders();
        json_respond(['ok' => false, 'error' => 'El fichero de la plantilla no existe en el servidor'], 404);
        return;
    }

    // Resolver las variables según el tipo y entidad
    $vars = resolverVariables($pdo, $tipoEnt, $entidadId);

    // Extraer listas especiales (no son variables de plantilla normales)
    $inqSec       = $vars['_inq_sec_list']   ?? [];
    $multiinqList = $vars['_multiinq_list']  ?? [];
    unset($vars['_inq_sec_list'], $vars['_multiinq_list']);

    // Generar el DOCX modificado en un fichero temporal
    $tmpFichero = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'alquigest_doc_' . uniqid('', true) . '.docx';
    $resultado  = generarDocx($rutaOrig, $tmpFichero, $vars, $inqSec, $multiinqList);

    if (!$resultado['ok']) {
        setCorsHeaders();
        json_respond(['ok' => false, 'error' => $resultado['error']], 500);
        return;
    }

    // Nombre de descarga legible
    $nombreDescarga = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $plantilla['nombre']) . '_generado.docx';

    // Avisar en cabecera si hubo variables desconocidas
    if (!empty($resultado['desconocidas'])) {
        header('X-AlquiGest-VarsDesconocidas: ' . implode(',', $resultado['desconocidas']));
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $nombreDescarga . '"');
    header('Content-Length: ' . filesize($tmpFichero));
    header('Cache-Control: no-cache, no-store');
    readfile($tmpFichero);
    unlink($tmpFichero); // limpieza del temporal
    exit;
}

// ── Previsualizar variables resueltas como HTML ───────────────
function accionPreview(PDO $pdo, array $body): void {
    setCorsHeaders();

    $plantillaId = (int)($body['plantilla_id'] ?? 0);
    $tipoEnt     = $body['tipo']       ?? '';
    $entidadId   = (int)($body['entidad_id'] ?? 0);

    if (!$plantillaId) {
        json_respond(['ok' => false, 'error' => 'plantilla_id requerido'], 400);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM plantillas WHERE id = ?");
    $stmt->execute([$plantillaId]);
    $plantilla = $stmt->fetch();
    if (!$plantilla) {
        json_respond(['ok' => false, 'error' => 'Plantilla no encontrada'], 404);
        return;
    }

    $rutaOrig = PLANTILLAS_DIR . basename($plantilla['fichero']);
    if (!file_exists($rutaOrig)) {
        json_respond(['ok' => false, 'error' => 'El fichero de la plantilla no existe'], 404);
        return;
    }

    $vars = resolverVariables($pdo, $tipoEnt, $entidadId);

    // Extraer listas especiales antes de pasar vars al motor HTML
    $inqSec       = $vars['_inq_sec_list']  ?? [];
    $multiinqList = $vars['_multiinq_list'] ?? [];
    unset($vars['_inq_sec_list'], $vars['_multiinq_list']);

    // Extraer texto del DOCX y generar preview HTML
    $preview = generarPreviewHtml($rutaOrig, $vars, $inqSec, $multiinqList);

    json_respond([
        'ok'                   => true,
        'html'                 => $preview['html'],
        'variables_resueltas'  => $vars,
        'variables_desconocidas' => $preview['desconocidas'],
        'nombre_plantilla'     => $plantilla['nombre'],
    ]);
}

// ── Catálogo de variables disponibles ────────────────────────
function accionVariables(): void {
    setCorsHeaders();
    json_respond(['ok' => true, 'variables' => catalogoVariables()]);
}

// ============================================================
//  MOTOR DE GENERACIÓN DOCX
// ============================================================

// Genera un DOCX sustituyendo variables en el original.
// Trabaja sobre los XMLs internos del ZIP OOXML.
// $inqSec: lista de inquilinos secundarios para expandir bloques {{#INQUILINOS_SECUNDARIOS}}.
// Devuelve ['ok' => bool, 'desconocidas' => [], 'error' => '']
function generarDocx(string $rutaOrig, string $rutaDest, array $vars, array $inqSec = [], array $multiinqList = []): array {
    // Copiar el original al destino (no modificamos el original)
    if (!copy($rutaOrig, $rutaDest)) {
        return ['ok' => false, 'error' => 'No se pudo crear el fichero temporal', 'desconocidas' => []];
    }

    if (!class_exists('ZipArchive')) {
        unlink($rutaDest);
        return ['ok' => false, 'error' => 'La extensión ZipArchive no está disponible.', 'desconocidas' => []];
    }

    $zip = new ZipArchive();
    if ($zip->open($rutaDest, ZipArchive::CREATE) !== true) {
        unlink($rutaDest);
        return ['ok' => false, 'error' => 'No se pudo abrir el DOCX como archivo ZIP.', 'desconocidas' => []];
    }

    $todasDesconocidas = [];

    // Procesamos los XMLs que pueden contener texto con variables
    $xmlsAProcesar = [
        'word/document.xml',
        'word/header1.xml', 'word/header2.xml', 'word/header3.xml',
        'word/footer1.xml', 'word/footer2.xml', 'word/footer3.xml',
    ];

    foreach ($xmlsAProcesar as $entrada) {
        $contenido = $zip->getFromName($entrada);
        if ($contenido === false) continue; // el fichero no existe en este DOCX, ignorar

        // Expandir bloques repetitivos antes de la sustitución normal de variables
        $contenido = expandirBloquesInquilinosSecundarios($contenido, $inqSec);
        $contenido = expandirBloqueMultiinquilino($contenido, $multiinqList);

        $resultado = procesarXmlWord($contenido, $vars);
        $zip->addFromString($entrada, $resultado['xml']);
        foreach ($resultado['desconocidas'] as $v) {
            $todasDesconocidas[] = $v;
        }
    }

    $zip->close();

    return [
        'ok'           => true,
        'desconocidas' => array_values(array_unique($todasDesconocidas)),
        'error'        => '',
    ];
}

// Procesa el XML de Word sustituyendo variables en cada párrafo
function procesarXmlWord(string $xml, array $vars): array {
    $desconocidas = [];

    // Procesamos cada párrafo <w:p>...</w:p>
    // Nota: /s (DOTALL) + .*? (lazy) en lugar de /U + [\s\S]*? porque
    // /U invierte la codicia de los cuantificadores — *? con /U sería codicioso.
    $resultado = preg_replace_callback(
        '/<w:p[ >].*?<\/w:p>/s',
        function($match) use ($vars, &$desconocidas) {
            return procesarParrafoWord($match[0], $vars, $desconocidas);
        },
        $xml
    );

    // Si preg_replace_callback falla (regexp muy largo), devolver xml original
    if ($resultado === null) {
        return ['xml' => $xml, 'desconocidas' => []];
    }

    return ['xml' => $resultado, 'desconocidas' => array_unique($desconocidas)];
}

// Procesa un párrafo individual: extrae texto, sustituye variables, reconstruye
function procesarParrafoWord(string $para, array $vars, array &$desconocidas): string {
    // Extraer texto completo del párrafo concatenando todos los <w:t>
    $textoCompleto = '';
    if (preg_match_all('/<w:t(?:\s[^>]*)?>([^<]*)<\/w:t>/s', $para, $matches)) {
        foreach ($matches[1] as $fragmento) {
            $textoCompleto .= html_entity_decode($fragmento, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        }
    }

    // Sin variables: devolver párrafo intacto (preserva todo el formato original)
    if (strpos($textoCompleto, '{{') === false) {
        return $para;
    }
    if (!preg_match('/\{\{\w+\}\}/', $textoCompleto)) {
        return $para;
    }

    // Sustituir variables conocidas; marcar desconocidas con <<NombreVar>>
    // Excepción: variables Inquilinos_Secundarios_*_N sin dato → cadena vacía (sin marcar)
    $textoFinal = preg_replace_callback('/\{\{(\w+)\}\}/', function($m) use ($vars, &$desconocidas) {
        $nombre = $m[1];
        if (isset($vars[$nombre])) {
            return $vars[$nombre];
        }
        if (preg_match('/^Inquilinos_Secundarios_\w+_\d+$/', $nombre)) {
            return '';
        }
        // Variables especiales: se inyectan por otros mecanismos o vienen del modal.
        // Si llegan aquí sin valor asignado, se sustituyen por cadena vacía silenciosamente.
        if ($nombre === 'FotosContrato' || $nombre === 'ListaMuebles') {
            return '';
        }
        $desconocidas[] = $nombre;
        return '<<' . $nombre . '>>';
    }, $textoCompleto);

    // Extraer propiedades del párrafo (w:pPr) y primer run (w:rPr) para conservar formato
    $pPr  = '';
    if (preg_match('/<w:pPr>[\s\S]*?<\/w:pPr>/U', $para, $pPrM)) {
        $pPr = $pPrM[0];
    }
    // Buscar el <w:rPr> del primer run de texto (tras </w:pPr>), no el de las
    // propiedades de párrafo. Sin flag /U para que *? sea lazy (primer match).
    $rPr  = '';
    $afterPPr = '';
    if (preg_match('/<\/w:pPr>([\s\S]*)/s', $para, $afterM)) {
        $afterPPr = $afterM[1];
    } else {
        $afterPPr = $para;
    }
    if (preg_match('/<w:rPr>[\s\S]*?<\/w:rPr>/', $afterPPr, $rPrM)) {
        $rPr = $rPrM[0];
    }

    // Capturar atributos del elemento <w:p> (p.ej: rsidR, w14:paraId…)
    $pAttr = '';
    if (preg_match('/<w:p(\s[^>]*)?>/', $para, $pAttrM)) {
        $pAttr = $pAttrM[1] ?? '';
    }

    // Construir el contenido del <w:r>. Si el texto contiene saltos de línea
    // (p.ej. de {{ListaMuebles}}), se divide en fragmentos unidos con <w:br/>.
    if (strpos($textoFinal, "\n") !== false) {
        $lineas = explode("\n", $textoFinal);
        $trozos = array_map(function($l) {
            return htmlspecialchars($l, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        }, $lineas);
        $runContent = '<w:t xml:space="preserve">' .
                      implode('</w:t><w:br/><w:t xml:space="preserve">', $trozos) .
                      '</w:t>';
    } else {
        $textoXml  = htmlspecialchars($textoFinal, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $xmlSpace  = (strlen($textoFinal) > 0 &&
                     ($textoFinal[0] === ' ' || substr($textoFinal, -1) === ' '))
            ? ' xml:space="preserve"' : '';
        $runContent = '<w:t' . $xmlSpace . '>' . $textoXml . '</w:t>';
    }

    // Reconstruir con un único run (merge de los runs originales)
    return '<w:p' . $pAttr . '>' .
           $pPr .
           '<w:r>' . $rPr . $runContent . '</w:r>' .
           '</w:p>';
}

// Extrae texto del DOCX y genera preview HTML con variables sustituidas.
// $inqSec: lista de inquilinos secundarios para expandir bloques repetitivos.
function generarPreviewHtml(string $rutaDocx, array $vars, array $inqSec = [], array $multiinqList = []): array {
    $desconocidas = [];

    $zip = new ZipArchive();
    if ($zip->open($rutaDocx) !== true) {
        return ['html' => '<p><em>No se pudo leer la plantilla.</em></p>', 'desconocidas' => []];
    }

    $docXml = $zip->getFromName('word/document.xml');
    $zip->close();

    if ($docXml === false) {
        return ['html' => '<p><em>No se encontró word/document.xml en la plantilla.</em></p>', 'desconocidas' => []];
    }

    // Expandir bloques repetitivos antes de generar el HTML de preview
    $docXml = expandirBloquesInquilinosSecundarios($docXml, $inqSec);
    $docXml = expandirBloqueMultiinquilino($docXml, $multiinqList);

    // Extraer párrafos y generar HTML
    $parrafos = [];
    preg_match_all('/<w:p[ >].*?<\/w:p>/s', $docXml, $pMatches);

    foreach ($pMatches[0] as $para) {
        // Obtener texto completo del párrafo
        $texto = '';
        if (preg_match_all('/<w:t(?:\s[^>]*)?>([^<]*)<\/w:t>/s', $para, $tM)) {
            foreach ($tM[1] as $frag) {
                $texto .= html_entity_decode($frag, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            }
        }
        if (trim($texto) === '') continue;

        // Sustituir variables en preview (verdes=ok, rojas=desconocidas)
        // Excepción: Inquilinos_Secundarios_*_N sin dato → vacío (no se marca en rojo)
        $textoSust = preg_replace_callback('/\{\{(\w+)\}\}/', function($m) use ($vars, &$desconocidas) {
            $nombre = $m[1];
            if (isset($vars[$nombre])) {
                return '<mark style="background:#d1fae5;padding:0 3px;border-radius:3px">' .
                       htmlspecialchars($vars[$nombre], ENT_QUOTES, 'UTF-8') . '</mark>';
            }
            if (preg_match('/^Inquilinos_Secundarios_\w+_\d+$/', $nombre)) {
                return '';
            }
            if ($nombre === 'FotosContrato') {
                return '<mark style="background:#dbeafe;padding:2px 8px;border-radius:3px;color:#1e40af;font-size:11px">' .
                       '[📷 Aquí se insertará la tabla de fotos]</mark>';
            }
            if ($nombre === 'ListaMuebles') {
                return '<mark style="background:#ecfdf5;padding:2px 8px;border-radius:3px;color:#166534;font-size:11px">' .
                       '[📋 Aquí se insertará el listado de mobiliario]</mark>';
            }
            $desconocidas[] = $nombre;
            return '<mark style="background:#fee2e2;padding:0 3px;border-radius:3px;color:#991b1b">&lt;&lt;' .
                   htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . '&gt;&gt;</mark>';
        }, htmlspecialchars($texto, ENT_QUOTES, 'UTF-8'));

        $parrafos[] = '<p style="margin:6px 0;line-height:1.6">' . $textoSust . '</p>';
    }

    $html = $parrafos
        ? '<div style="font-family:Georgia,serif;font-size:13px;max-height:400px;overflow-y:auto;' .
          'padding:16px;background:#fafafa;border:1px solid var(--gray-200);border-radius:8px">' .
          implode('', $parrafos) . '</div>'
        : '<p><em>No se pudo extraer texto de la plantilla.</em></p>';

    return ['html' => $html, 'desconocidas' => array_unique($desconocidas)];
}

// ============================================================
//  RESOLUCIÓN DE VARIABLES
// ============================================================

// Construye el mapa variable → valor según el tipo de entidad.
// Es el registro centralizado de variables: un único lugar donde añadir nuevas.
function resolverVariables(PDO $pdo, string $tipo, int $entidadId): array {
    $vars = [];

    // ── Datos de empresa (siempre disponibles) ────────────────
    $emp = $pdo->query("SELECT * FROM empresa LIMIT 1")->fetch() ?: [];
    $vars['NombreEmpresa']   = $emp['nombre']   ?? '';
    $vars['CIFEmpresa']      = $emp['cif']       ?? '';
    $vars['DireccionEmpresa'] = trim(
        ($emp['direccion'] ?? '') .
        ($emp['cp']        ? ', ' . $emp['cp']        : '') .
        ($emp['municipio'] ? ' ' . $emp['municipio']  : '') .
        ($emp['provincia'] ? ' (' . $emp['provincia'] . ')' : ''),
        ' ,'
    );
    $vars['TelefonoEmpresa'] = $emp['telefono'] ?? '';
    $vars['EmailEmpresa']    = $emp['email']    ?? '';
    $vars['IBANEmpresa']     = $emp['iban']     ?? '';

    // ── Variables de sistema ──────────────────────────────────
    $vars['FechaActual'] = date('d/m/Y');
    $vars['AnioActual']  = date('Y');
    $vars['MesActual']   = strftime('%B', mktime(0, 0, 0, (int)date('m'), 1)) ?: date('F');
    // Fecha larga en español: "29 de Junio del 2026" (día sin cero, mes con mayúscula)
    $mesesEs = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    $vars['FechaHoy'] = (int)date('j') . ' de ' . $mesesEs[(int)date('n') - 1] . ' del ' . date('Y');

    // ── Variables específicas de contrato ─────────────────────
    if ($tipo === 'contrato' && $entidadId > 0) {
        $stC = $pdo->prepare("SELECT * FROM contratos WHERE id = ?");
        $stC->execute([$entidadId]);
        $c = $stC->fetch() ?: [];

        // Inquilino
        $inq = [];
        if (!empty($c['inquilino_id'])) {
            $stI = $pdo->prepare("SELECT * FROM inquilinos WHERE id = ?");
            $stI->execute([$c['inquilino_id']]);
            $inq = $stI->fetch() ?: [];
        }

        // Inmueble, finca, propietario
        $inm = []; $finca = []; $prop = [];
        if (!empty($c['inmueble_id'])) {
            $stInm = $pdo->prepare("SELECT * FROM inmuebles WHERE id = ?");
            $stInm->execute([$c['inmueble_id']]);
            $inm = $stInm->fetch() ?: [];

            if (!empty($inm['finca_id'])) {
                $stF = $pdo->prepare("SELECT * FROM fincas WHERE id = ?");
                $stF->execute([$inm['finca_id']]);
                $finca = $stF->fetch() ?: [];

                if (!empty($finca['propietario_id'])) {
                    $stP = $pdo->prepare("SELECT * FROM propietarios WHERE id = ?");
                    $stP->execute([$finca['propietario_id']]);
                    $prop = $stP->fetch() ?: [];
                }
            }
        }

        // Propietario (cae a empresa si no hay propietario configurado)
        $vars['NombrePropietario'] = $prop['nombre'] ?? ($emp['nombre'] ?? '');
        $vars['NIFPropietario']    = $prop['nif']    ?? ($emp['cif']    ?? '');
        $vars['DireccionPropietario'] = $prop['direccion'] ?? ($emp['direccion'] ?? '');

        // Inquilino
        $vars['NombreInquilino']   = $inq['nombre']  ?? '';
        $vars['NIFInquilino']      = $inq['nif']     ?? '';
        $vars['TelefonoInquilino'] = $inq['movil']   ?: ($inq['telefono'] ?? '');
        $vars['EmailInquilino']    = $inq['email']   ?? '';
        $vars['IBANInquilino']     = $inq['iban']    ?? '';
        $vars['DireccionInquilino'] = trim(
            ($inq['direccion'] ?? '') .
            ($inq['cp']        ? ', ' . $inq['cp']        : '') .
            ($inq['municipio'] ? ' ' . $inq['municipio']  : ''),
            ' ,'
        );

        // Inmueble
        $dirInm = '';
        if ($finca) {
            $dirInm = trim(
                ($finca['sigla']     ? $finca['sigla'] . ' '    : '') .
                ($finca['calle']     ? $finca['calle'] . ' '    : '') .
                ($finca['numero']    ?? '')
            );
            if ($inm['planta'] ?? '') $dirInm .= ', ' . $inm['planta'];
            if ($inm['puerta']  ?? '') $dirInm .= ' ' . $inm['puerta'];
            $dirInm = trim($dirInm);
            if ($finca['cp']        ?? '') $dirInm .= ' — CP ' . $finca['cp'];
            if ($finca['municipio'] ?? '') $dirInm .= ', ' . $finca['municipio'];
            if ($finca['provincia'] ?? '') $dirInm .= ' (' . $finca['provincia'] . ')';
        }
        $vars['DireccionInmueble']  = $dirInm;
        $vars['RefCatastral']       = $inm['referencia_catastral'] ?? '';
        $vars['TipoInmueble']       = $inm['tipo'] ?? '';
        $vars['MunicipioInmueble']  = $finca['municipio'] ?? '';
        $vars['ProvinciaInmueble']  = $finca['provincia'] ?? '';

        // Datos económicos del contrato
        $renta  = (float)($c['renta_base'] ?? 0);
        $fianza = (float)($c['fianza']     ?? 0);
        $vars['Renta']          = number_format($renta,  2, ',', '.') . ' €';
        $vars['RentaLetras']    = montoEnLetrasPHP($renta)  . ' euros';
        $vars['Fianza']         = number_format($fianza, 2, ',', '.') . ' €';
        $vars['FianzaLetras']   = montoEnLetrasPHP($fianza) . ' euros';
        $vars['IVA']            = number_format((float)($c['iva_pct']  ?? 0), 2, ',', '.') . '%';
        $vars['IRPF']           = number_format((float)($c['irpf_pct'] ?? 0), 2, ',', '.') . '%';
        $vars['MetodoRevision'] = $c['revision'] ?? '';
        $vars['DiaPago']        = (string)($c['dia_pago'] ?? 5);

        // Fechas del contrato
        $vars['FechaInicio'] = !empty($c['fecha_inicio'])
            ? date('d/m/Y', strtotime($c['fecha_inicio'])) : '';
        $vars['FechaFin']    = !empty($c['fecha_fin'])
            ? date('d/m/Y', strtotime($c['fecha_fin']))    : '';
        $vars['Duracion']    = calcularDuracionTexto($c['fecha_inicio'] ?? '', $c['fecha_fin'] ?? '');

        // Fiador (campos opcionales — vacíos si el contrato no los tiene aún)
        $vars['NombreFiador']    = $c['nombre_fiador']    ?? '';
        $vars['NIFFiador']       = $c['nif_fiador']       ?? '';
        $vars['DireccionFiador'] = $c['direccion_fiador'] ?? '';

        // Motivo de temporada (vacío si no aplica)
        $vars['MotivoTemporada'] = $c['motivo_temporada'] ?? '';

        // Inquilinos secundarios: lista para bloques repetitivos + variables numeradas por posición
        try {
            $stSec = $pdo->prepare("SELECT * FROM contratos_inq_sec WHERE contrato_id = ? ORDER BY orden, id");
            $stSec->execute([$entidadId]);
            $vars['_inq_sec_list'] = $stSec->fetchAll() ?: [];
        } catch (\PDOException $e) {
            $vars['_inq_sec_list'] = [];
        }

        // Variables indexadas por posición (1-based): {{Inquilinos_Secundarios_Nombre_1}}, etc.
        // Las posiciones sin dato NO se pre-rellenan; el motor devuelve '' en lugar de <<...>>
        foreach ($vars['_inq_sec_list'] as $idx => $inqS) {
            $n = $idx + 1;
            $vars['Inquilinos_Secundarios_Nombre_'    . $n] = $inqS['nombre']    ?? '';
            $vars['Inquilinos_Secundarios_NIF_'       . $n] = $inqS['nif']       ?? '';
            $vars['Inquilinos_Secundarios_Direccion_' . $n] = $inqS['direccion'] ?? '';
            $vars['Inquilinos_Secundarios_Telefono_'  . $n] = $inqS['telefono']  ?? '';
            $vars['Inquilinos_Secundarios_Email_'     . $n] = $inqS['email']     ?? '';
        }

        // Lista combinada para {{InicioMultiinquilino}}: inquilino principal + todos los secundarios
        $listaMulti = [];
        if ($vars['NombreInquilino'] !== '') {
            $listaMulti[] = [
                'nombre'    => $vars['NombreInquilino'],
                'nif'       => $vars['NIFInquilino']       ?? '',
                'direccion' => $vars['DireccionInquilino'] ?? '',
            ];
        }
        foreach ($vars['_inq_sec_list'] as $s) {
            $listaMulti[] = [
                'nombre'    => $s['nombre']    ?? '',
                'nif'       => $s['nif']       ?? '',
                'direccion' => $s['direccion'] ?? '',
            ];
        }
        $vars['_multiinq_list'] = $listaMulti;
    }

    return $vars;
}

// ============================================================
//  CATÁLOGO DE VARIABLES (para el modal "Ver variables")
// ============================================================
function catalogoVariables(): array {
    return [
        // ── Sistema: fechas y valores del momento actual ───────────
        ['var' => 'FechaActual',        'grupo' => 'Sistema',      'desc' => 'Fecha de hoy en formato dd/mm/aaaa',                                'ej' => date('d/m/Y')],
        ['var' => 'FechaHoy',           'grupo' => 'Sistema',      'desc' => 'Fecha larga en español: «29 de Junio del 2026»',                    'ej' => '29 de Junio del 2026'],
        ['var' => 'AnioActual',         'grupo' => 'Sistema',      'desc' => 'Año actual en cuatro dígitos',                                      'ej' => date('Y')],
        ['var' => 'MesActual',          'grupo' => 'Sistema',      'desc' => 'Nombre del mes actual en español (minúsculas)',                      'ej' => 'junio'],
        // ── Empresa ───────────────────────────────────────────────
        ['var' => 'NombreEmpresa',      'grupo' => 'Empresa',      'desc' => 'Nombre de la empresa gestora',                                      'ej' => 'Gestiones García S.L.'],
        ['var' => 'CIFEmpresa',         'grupo' => 'Empresa',      'desc' => 'CIF/NIF de la empresa',                                             'ej' => 'B12345678'],
        ['var' => 'DireccionEmpresa',   'grupo' => 'Empresa',      'desc' => 'Dirección completa de la empresa',                                  'ej' => 'Av. Principal 1, 28001 Madrid'],
        ['var' => 'TelefonoEmpresa',    'grupo' => 'Empresa',      'desc' => 'Teléfono de la empresa',                                            'ej' => '910 000 001'],
        ['var' => 'EmailEmpresa',       'grupo' => 'Empresa',      'desc' => 'Email de la empresa',                                               'ej' => 'info@empresa.com'],
        ['var' => 'IBANEmpresa',        'grupo' => 'Empresa',      'desc' => 'IBAN de la cuenta bancaria de la empresa',                          'ej' => 'ES12 3456 7890 1234 5678 9012'],
        // ── Propietario ───────────────────────────────────────────
        ['var' => 'NombrePropietario',  'grupo' => 'Propietario',  'desc' => 'Nombre del propietario del inmueble',                               'ej' => 'García López, Ana'],
        ['var' => 'NIFPropietario',     'grupo' => 'Propietario',  'desc' => 'NIF/CIF del propietario',                                           'ej' => '12345678A'],
        ['var' => 'DireccionPropietario','grupo'=> 'Propietario',  'desc' => 'Dirección del propietario',                                         'ej' => 'Calle Mayor 1, Madrid'],
        // ── Inquilino principal ───────────────────────────────────
        ['var' => 'NombreInquilino',    'grupo' => 'Inquilino',    'desc' => 'Nombre completo del arrendatario principal',                        'ej' => 'Martínez Ruiz, Pedro'],
        ['var' => 'NIFInquilino',       'grupo' => 'Inquilino',    'desc' => 'NIF/NIE del arrendatario principal',                                'ej' => '87654321B'],
        ['var' => 'TelefonoInquilino',  'grupo' => 'Inquilino',    'desc' => 'Teléfono o móvil del arrendatario',                                 'ej' => '600 000 001'],
        ['var' => 'EmailInquilino',     'grupo' => 'Inquilino',    'desc' => 'Email del arrendatario',                                            'ej' => 'pedro@gmail.com'],
        ['var' => 'IBANInquilino',      'grupo' => 'Inquilino',    'desc' => 'IBAN bancario del arrendatario (vacío si no está informado)',        'ej' => 'ES98 2100 0418 4502 0005 1332'],
        ['var' => 'DireccionInquilino', 'grupo' => 'Inquilino',    'desc' => 'Dirección del arrendatario',                                        'ej' => 'C/ Ejemplo 5, 28001 Madrid'],
        // ── Bloque multiinquilino: principal + todos los secundarios ──
        // Los marcadores deben estar solos en su propio párrafo en la plantilla Word.
        // El bloque se repite una vez por cada inquilino: primero el principal y luego los secundarios en orden.
        ['var' => 'InicioMultiinquilino',        'grupo' => 'Bloque multiinquilino', 'desc' => 'Inicio del bloque (párrafo solo con este texto)',                    'ej' => '{{InicioMultiinquilino}}'],
        ['var' => 'NombreInquilinomultiple',     'grupo' => 'Bloque multiinquilino', 'desc' => 'Nombre de cada inquilino dentro del bloque',                        'ej' => 'Martínez Ruiz, Pedro'],
        ['var' => 'NIFInquilinomultiple',        'grupo' => 'Bloque multiinquilino', 'desc' => 'NIF/NIE de cada inquilino dentro del bloque',                       'ej' => '87654321B'],
        ['var' => 'DireccionInquilinomultiple',  'grupo' => 'Bloque multiinquilino', 'desc' => 'Dirección de cada inquilino dentro del bloque',                     'ej' => 'C/ Ejemplo 5, 28001 Madrid'],
        ['var' => '/InicioMultiinquilino',       'grupo' => 'Bloque multiinquilino', 'desc' => 'Fin del bloque (párrafo solo con este texto)',                       'ej' => '{{/InicioMultiinquilino}}'],
        // ── Bloque repetitivo: solo inquilinos secundarios ─────────
        // Los marcadores deben estar solos en su propio párrafo en la plantilla Word.
        ['var' => '#INQUILINOS_SECUNDARIOS',  'grupo' => 'Bloque inq. secundarios', 'desc' => 'Inicio del bloque solo-secundarios (párrafo con este texto)',        'ej' => '{{#INQUILINOS_SECUNDARIOS}}'],
        ['var' => 'InqNombre',               'grupo' => 'Bloque inq. secundarios', 'desc' => 'Nombre del inquilino secundario (dentro del bloque)',                'ej' => 'Ruiz Pérez, Ana'],
        ['var' => 'InqNIF',                  'grupo' => 'Bloque inq. secundarios', 'desc' => 'NIF/NIE del inquilino secundario (dentro del bloque)',               'ej' => '44556677E'],
        ['var' => 'InqDireccion',            'grupo' => 'Bloque inq. secundarios', 'desc' => 'Dirección del inquilino secundario (dentro del bloque)',             'ej' => 'Av. España 5, 28003 Madrid'],
        ['var' => 'InqTelefono',             'grupo' => 'Bloque inq. secundarios', 'desc' => 'Teléfono del inquilino secundario (dentro del bloque)',              'ej' => '600 111 222'],
        ['var' => 'InqEmail',                'grupo' => 'Bloque inq. secundarios', 'desc' => 'Email del inquilino secundario (dentro del bloque)',                 'ej' => 'ana@email.com'],
        ['var' => '/INQUILINOS_SECUNDARIOS', 'grupo' => 'Bloque inq. secundarios', 'desc' => 'Fin del bloque solo-secundarios (párrafo con este texto)',            'ej' => '{{/INQUILINOS_SECUNDARIOS}}'],
        // ── Inquilinos secundarios por posición ────────────────────
        // El índice empieza en 1. Si la posición no existe, se sustituye por cadena vacía.
        ['var' => 'Inquilinos_Secundarios_Nombre_1',    'grupo' => 'Inq. secundarios (pos.)', 'desc' => 'Nombre del 1er inquilino secundario',    'ej' => 'Martínez Gómez, Carlos'],
        ['var' => 'Inquilinos_Secundarios_NIF_1',       'grupo' => 'Inq. secundarios (pos.)', 'desc' => 'NIF del 1er inquilino secundario',       'ej' => '33445566F'],
        ['var' => 'Inquilinos_Secundarios_Direccion_1', 'grupo' => 'Inq. secundarios (pos.)', 'desc' => 'Dirección del 1er inquilino secundario', 'ej' => 'Calle Luna 8, 28010 Madrid'],
        ['var' => 'Inquilinos_Secundarios_Telefono_1',  'grupo' => 'Inq. secundarios (pos.)', 'desc' => 'Teléfono del 1er inquilino secundario',  'ej' => '611 222 333'],
        ['var' => 'Inquilinos_Secundarios_Email_1',     'grupo' => 'Inq. secundarios (pos.)', 'desc' => 'Email del 1er inquilino secundario',     'ej' => 'carlos@email.com'],
        ['var' => 'Inquilinos_Secundarios_Nombre_2',    'grupo' => 'Inq. secundarios (pos.)', 'desc' => 'Nombre del 2º inquilino secundario',     'ej' => 'Ruiz Pérez, Ana'],
        ['var' => 'Inquilinos_Secundarios_NIF_2',       'grupo' => 'Inq. secundarios (pos.)', 'desc' => 'NIF del 2º inquilino secundario',        'ej' => '44556677E'],
        ['var' => 'Inquilinos_Secundarios_Direccion_2', 'grupo' => 'Inq. secundarios (pos.)', 'desc' => 'Dirección del 2º inquilino secundario',  'ej' => 'Av. España 5, 28003 Madrid'],
        ['var' => 'Inquilinos_Secundarios_Telefono_2',  'grupo' => 'Inq. secundarios (pos.)', 'desc' => 'Teléfono del 2º inquilino secundario',   'ej' => '600 111 222'],
        ['var' => 'Inquilinos_Secundarios_Email_2',     'grupo' => 'Inq. secundarios (pos.)', 'desc' => 'Email del 2º inquilino secundario',      'ej' => 'ana@email.com'],
        ['var' => 'Inquilinos_Secundarios_Nombre_3',    'grupo' => 'Inq. secundarios (pos.)', 'desc' => 'Nombre del 3er inquilino secundario (el índice puede continuar: _4, _5…)', 'ej' => 'López Vega, Luis'],
        ['var' => 'Inquilinos_Secundarios_NIF_3',       'grupo' => 'Inq. secundarios (pos.)', 'desc' => 'NIF del 3er inquilino secundario',       'ej' => '55667788G'],
        ['var' => 'Inquilinos_Secundarios_Direccion_3', 'grupo' => 'Inq. secundarios (pos.)', 'desc' => 'Dirección del 3er inquilino secundario', 'ej' => 'C/ Sol 12, Madrid'],
        ['var' => 'Inquilinos_Secundarios_Telefono_3',  'grupo' => 'Inq. secundarios (pos.)', 'desc' => 'Teléfono del 3er inquilino secundario',  'ej' => '622 333 444'],
        ['var' => 'Inquilinos_Secundarios_Email_3',     'grupo' => 'Inq. secundarios (pos.)', 'desc' => 'Email del 3er inquilino secundario',     'ej' => 'luis@email.com'],
        // ── Inmueble ──────────────────────────────────────────────
        ['var' => 'DireccionInmueble',  'grupo' => 'Inmueble',     'desc' => 'Dirección completa del inmueble arrendado',                         'ej' => 'Av. Constitución 8 1º A, Madrid'],
        ['var' => 'RefCatastral',       'grupo' => 'Inmueble',     'desc' => 'Referencia catastral del inmueble',                                 'ej' => '1234567AB1234'],
        ['var' => 'TipoInmueble',       'grupo' => 'Inmueble',     'desc' => 'Tipo de inmueble (vivienda, local…)',                               'ej' => 'Vivienda'],
        ['var' => 'MunicipioInmueble',  'grupo' => 'Inmueble',     'desc' => 'Municipio donde está el inmueble',                                  'ej' => 'Madrid'],
        ['var' => 'ProvinciaInmueble',  'grupo' => 'Inmueble',     'desc' => 'Provincia del inmueble',                                            'ej' => 'Madrid'],
        // ── Contrato ──────────────────────────────────────────────
        ['var' => 'FechaInicio',        'grupo' => 'Contrato',     'desc' => 'Fecha de inicio del contrato',                                      'ej' => '01/01/2024'],
        ['var' => 'FechaFin',           'grupo' => 'Contrato',     'desc' => 'Fecha de vencimiento del contrato',                                 'ej' => '31/12/2025'],
        ['var' => 'Duracion',           'grupo' => 'Contrato',     'desc' => 'Duración del contrato en texto',                                    'ej' => '1 año y 6 meses'],
        ['var' => 'MetodoRevision',     'grupo' => 'Contrato',     'desc' => 'Tipo de revisión anual de la renta',                                'ej' => 'IPC'],
        ['var' => 'DiaPago',            'grupo' => 'Contrato',     'desc' => 'Día del mes en que se paga la renta',                               'ej' => '5'],
        ['var' => 'MotivoTemporada',    'grupo' => 'Contrato',     'desc' => 'Motivo del arrendamiento de temporada',                             'ej' => 'Estudios universitarios 2024-2025'],
        // ── Facturación ───────────────────────────────────────────
        ['var' => 'Renta',              'grupo' => 'Facturación',  'desc' => 'Importe mensual de la renta en euros',                              'ej' => '850,00 €'],
        ['var' => 'RentaLetras',        'grupo' => 'Facturación',  'desc' => 'Renta mensual escrita en letras',                                   'ej' => 'ochocientos cincuenta euros'],
        ['var' => 'IVA',                'grupo' => 'Facturación',  'desc' => 'Porcentaje de IVA del contrato',                                    'ej' => '21,00%'],
        ['var' => 'IRPF',               'grupo' => 'Facturación',  'desc' => 'Porcentaje de retención IRPF del contrato',                         'ej' => '15,00%'],
        // ── Fianza ────────────────────────────────────────────────
        ['var' => 'Fianza',             'grupo' => 'Fianza',       'desc' => 'Importe de la fianza en euros',                                     'ej' => '1.700,00 €'],
        ['var' => 'FianzaLetras',       'grupo' => 'Fianza',       'desc' => 'Importe de la fianza escrito en letras',                            'ej' => 'mil setecientos euros'],
        // ── Fiador ────────────────────────────────────────────────
        ['var' => 'NombreFiador',       'grupo' => 'Fiador',       'desc' => 'Nombre completo del fiador solidario',                              'ej' => 'García Ruiz, José'],
        ['var' => 'NIFFiador',          'grupo' => 'Fiador',       'desc' => 'NIF del fiador solidario',                                          'ej' => '11223344C'],
        ['var' => 'DireccionFiador',    'grupo' => 'Fiador',       'desc' => 'Dirección completa del fiador',                                     'ej' => 'Calle Ejemplo 3, Madrid'],
        // ── Fotos y anexos del contrato ───────────────────────────
        // FotosContrato: coloca la variable sola en su párrafo; abre diálogo de carga de fotos.
        // ListaMuebles: coloca la variable en cualquier párrafo; abre cuadro de texto multilínea.
        // Ambas variables comparten el mismo modal al generar.
        ['var' => 'FotosContrato',  'grupo' => 'Fotos', 'desc' => 'Tabla de fotos embebidas (JPG/PNG). El párrafo debe contener solo esta variable.',                      'ej' => '{{FotosContrato}}'],
        ['var' => 'ListaMuebles',   'grupo' => 'Fotos', 'desc' => 'Descripción del mobiliario del inmueble. Permite texto largo con saltos de línea.',                     'ej' => 'Sofá 3 plazas, mesa de comedor...'],
    ];
}

// ============================================================
//  BLOQUES REPETITIVOS
// ============================================================

// Motor genérico de expansión de bloques repetitivos en XML OOXML.
// Busca el párrafo cuyo texto visible sea exactamente $markerStart y el párrafo
// cuyo texto visible sea exactamente $markerEnd; expande los párrafos entre ellos
// una vez por cada elemento de $itemList, sustituyendo las variables que devuelve
// $varResolver($item). Si la plantilla no contiene el bloque, devuelve el XML intacto.
// Este diseño permite añadir en el futuro nuevos bloques (avalistas, garajes, etc.)
// con una sola llamada a esta función, sin duplicar lógica.
function _expandirBloqueRepetitivo(string $xml, string $markerStart, string $markerEnd, array $itemList, callable $varResolver): string {
    if (!preg_match_all('/(<w:p[ >].*?<\/w:p>)/s', $xml, $matches, PREG_OFFSET_CAPTURE)) {
        return $xml;
    }

    $paras = $matches[1]; // [0] => texto del párrafo, [1] => offset byte en el XML

    // Extrae el texto visible de un párrafo OOXML concatenando todos sus <w:t>
    $textoParrafo = function(string $para): string {
        $t = '';
        if (preg_match_all('/<w:t(?:\s[^>]*)?>([^<]*)<\/w:t>/s', $para, $m)) {
            foreach ($m[1] as $f) $t .= html_entity_decode($f, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        }
        return trim($t);
    };

    // Localizar los párrafos marcador (texto == marcador exacto)
    $startIdx = -1;
    $endIdx   = -1;
    foreach ($paras as $i => $p) {
        $txt = $textoParrafo($p[0]);
        if ($txt === $markerStart) $startIdx = $i;
        if ($txt === $markerEnd)   { $endIdx = $i; break; }
    }

    // Si no se encontró el bloque completo, devolver sin cambios
    if ($startIdx < 0 || $endIdx < 0 || $endIdx <= $startIdx) return $xml;

    // Párrafos plantilla: los que están entre los marcadores (los marcadores no se incluyen)
    $templateParas = array_slice($paras, $startIdx + 1, $endIdx - $startIdx - 1);

    // Expandir: una copia de los párrafos plantilla por cada elemento de la lista
    $expanded = '';
    foreach ($itemList as $item) {
        $localVars = $varResolver($item);
        foreach ($templateParas as $tp) {
            $desc = [];
            $expanded .= procesarParrafoWord($tp[0], $localVars, $desc);
        }
    }

    // Reemplazar en el XML el rango completo (marcador inicio → fin del marcador cierre)
    $startByte = $paras[$startIdx][1];
    $endByte   = $paras[$endIdx][1] + strlen($paras[$endIdx][0]);

    return substr($xml, 0, $startByte) . $expanded . substr($xml, $endByte);
}

// Expande el bloque {{#INQUILINOS_SECUNDARIOS}}...{{/INQUILINOS_SECUNDARIOS}}.
// Variables internas: {{InqNombre}}, {{InqNIF}}, {{InqDireccion}}, {{InqTelefono}}, {{InqEmail}}.
// Solo incluye los inquilinos secundarios (no el principal).
function expandirBloquesInquilinosSecundarios(string $xml, array $inqSec): string {
    return _expandirBloqueRepetitivo(
        $xml,
        '{{#INQUILINOS_SECUNDARIOS}}',
        '{{/INQUILINOS_SECUNDARIOS}}',
        $inqSec,
        function(array $inq): array {
            return [
                'InqNombre'    => $inq['nombre']    ?? '',
                'InqNIF'       => $inq['nif']       ?? '',
                'InqDireccion' => $inq['direccion'] ?? '',
                'InqTelefono'  => $inq['telefono']  ?? '',
                'InqEmail'     => $inq['email']     ?? '',
            ];
        }
    );
}

// Expande el bloque {{InicioMultiinquilino}}...{{/InicioMultiinquilino}}.
// Variables internas: {{NombreInquilinomultiple}}, {{NIFInquilinomultiple}}, {{DireccionInquilinomultiple}}.
// Incluye primero el inquilino principal y luego todos los secundarios en orden.
function expandirBloqueMultiinquilino(string $xml, array $multiinqList): string {
    return _expandirBloqueRepetitivo(
        $xml,
        '{{InicioMultiinquilino}}',
        '{{/InicioMultiinquilino}}',
        $multiinqList,
        function(array $inq): array {
            return [
                'NombreInquilinomultiple'    => $inq['nombre']    ?? '',
                'NIFInquilinomultiple'       => $inq['nif']       ?? '',
                'DireccionInquilinomultiple' => $inq['direccion'] ?? '',
            ];
        }
    );
}

// ============================================================
//  FUNCIONES AUXILIARES
// ============================================================

// Concatena el texto visible de todos los párrafos del XML de Word,
// reconstruyendo el contenido de cada <w:p> a partir de sus <w:t> hijos.
// Esto permite detectar variables aunque estén divididas entre múltiples runs.
function _extraerTextoParrafos(string $docXml): string {
    if (!preg_match_all('/(<w:p[ >].*?<\/w:p>)/s', $docXml, $matches)) return '';
    $texto = '';
    foreach ($matches[1] as $para) {
        if (preg_match_all('/<w:t(?:\s[^>]*)?>([^<]*)<\/w:t>/s', $para, $tm)) {
            foreach ($tm[1] as $t) {
                $texto .= html_entity_decode($t, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            }
        }
        $texto .= "\n";
    }
    return $texto;
}

// Genera un nombre de fichero único para almacenar en disco
function generarNombreFichero(): string {
    return date('YmdHis') . '_' . substr(bin2hex(random_bytes(8)), 0, 12) . '.docx';
}

// Valida que un fichero subido es realmente un DOCX (ZIP válido con word/document.xml)
function validarDocx(string $tmpRuta): bool {
    if (!class_exists('ZipArchive')) return true; // si no hay ZipArchive, confiamos en la extensión
    $zip = new ZipArchive();
    if ($zip->open($tmpRuta) !== true) return false;
    $tieneDoc = ($zip->locateName('word/document.xml') !== false);
    $zip->close();
    return $tieneDoc;
}

// Sanitiza el tipo de documento a valores permitidos
function sanitizarTipoDoc(string $tipo): string {
    $permitidos = ['contrato_arrendamiento', 'fianza', 'renovacion', 'comunicacion', 'otro'];
    return in_array($tipo, $permitidos, true) ? $tipo : 'otro';
}

// Normaliza un registro de BD a array tipado
function normalizarPlantilla(array $row): array {
    return [
        'id'             => (int)$row['id'],
        'nombre'         => $row['nombre'],
        'tipo_documento' => $row['tipo_documento'],
        'descripcion'    => $row['descripcion'] ?? '',
        'fichero'        => $row['fichero'],
        'activa'         => (int)$row['activa'],
        'por_defecto'    => (int)$row['por_defecto'],
        'created_at'     => $row['created_at'],
        'updated_at'     => $row['updated_at'],
    ];
}

// Convierte un importe numérico a texto en español (sin "euros")
function montoEnLetrasPHP(float $monto): string {
    $entero  = (int)floor(abs($monto));
    $result  = $entero === 0 ? 'cero' : _numALetras($entero);
    $cents   = (int)round((abs($monto) - $entero) * 100);
    if ($cents > 0) {
        $result .= ' con ' . _numALetras($cents) . ' céntimos';
    }
    return $monto < 0 ? 'menos ' . $result : $result;
}

function _numALetras(int $n): string {
    $u = ['','un','dos','tres','cuatro','cinco','seis','siete','ocho','nueve',
          'diez','once','doce','trece','catorce','quince','dieciséis',
          'diecisiete','dieciocho','diecinueve'];
    $d = ['','','veinte','treinta','cuarenta','cincuenta','sesenta','setenta','ochenta','noventa'];
    $c = ['','ciento','doscientos','trescientos','cuatrocientos','quinientos',
          'seiscientos','setecientos','ochocientos','novecientos'];

    if ($n < 0)  return 'menos ' . _numALetras(-$n);
    if ($n < 20) return $u[$n];
    if ($n < 30) return $n === 20 ? 'veinte' : 'veinti' . $u[$n - 20];
    if ($n < 100) {
        $dec = intdiv($n, 10); $uni = $n % 10;
        return $uni === 0 ? $d[$dec] : $d[$dec] . ' y ' . $u[$uni];
    }
    if ($n === 100) return 'cien';
    if ($n < 1000) {
        $cent = intdiv($n, 100); $resto = $n % 100;
        return $resto === 0 ? $c[$cent] : $c[$cent] . ' ' . _numALetras($resto);
    }
    if ($n < 2000) {
        $resto = $n % 1000;
        return 'mil' . ($resto > 0 ? ' ' . _numALetras($resto) : '');
    }
    if ($n < 1000000) {
        $miles = intdiv($n, 1000); $resto = $n % 1000;
        return _numALetras($miles) . ' mil' . ($resto > 0 ? ' ' . _numALetras($resto) : '');
    }
    $mills = intdiv($n, 1000000); $resto = $n % 1000000;
    $texMill = $mills === 1 ? 'un millón' : _numALetras($mills) . ' millones';
    return $texMill . ($resto > 0 ? ' ' . _numALetras($resto) : '');
}

// Calcula la duración en texto legible entre dos fechas ISO
function calcularDuracionTexto(string $inicio, string $fin): string {
    if (!$inicio || !$fin) return 'indefinida';
    try {
        $d1 = new DateTime($inicio);
        $d2 = new DateTime($fin);
        $diff = $d1->diff($d2);
        $partes = [];
        if ($diff->y > 0) $partes[] = $diff->y . ($diff->y === 1 ? ' año' : ' años');
        if ($diff->m > 0) $partes[] = $diff->m . ($diff->m === 1 ? ' mes' : ' meses');
        return $partes ? implode(' y ', $partes) : 'menos de un mes';
    } catch (Exception $e) {
        return 'indefinida';
    }
}

// ============================================================
//  FOTOS EN PLANTILLAS ({{FotosContrato}})
// ============================================================

// Detecta si una plantilla contiene la variable {{FotosContrato}}
function accionAnalizarPlantilla(PDO $pdo): void {
    setCorsHeaders();
    $plantillaId = (int)($_GET['plantilla_id'] ?? $_POST['plantilla_id'] ?? 0);
    if (!$plantillaId) {
        json_respond(['ok' => false, 'error' => 'plantilla_id requerido'], 400);
        return;
    }
    $stmt = $pdo->prepare("SELECT * FROM plantillas WHERE id = ?");
    $stmt->execute([$plantillaId]);
    $plantilla = $stmt->fetch();
    if (!$plantilla) {
        json_respond(['ok' => false, 'error' => 'Plantilla no encontrada'], 404);
        return;
    }
    $rutaOrig = PLANTILLAS_DIR . basename($plantilla['fichero']);
    if (!file_exists($rutaOrig)) {
        json_respond(['ok' => false, 'error' => 'Fichero de plantilla no encontrado en disco'], 404);
        return;
    }

    $tienefotos   = false;
    $tieneMuebles = false;
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($rutaOrig) === true) {
            $docXml = $zip->getFromName('word/document.xml');
            $zip->close();
            if ($docXml !== false) {
                // Reconstruir texto párrafo a párrafo para detectar variables
                // aunque estén divididas entre múltiples <w:t> (split-run de Word)
                $textoTotal   = _extraerTextoParrafos($docXml);
                $tienefotos   = (strpos($textoTotal, '{{FotosContrato}}') !== false);
                $tieneMuebles = (strpos($textoTotal, '{{ListaMuebles}}')  !== false);
            }
        }
    }
    json_respond(['ok' => true, 'tiene_fotos' => $tienefotos, 'tiene_muebles' => $tieneMuebles]);
}

// Genera DOCX con fotos embebidas (recibe multipart/form-data)
function accionGenerarConFotos(PDO $pdo): void {
    $plantillaId = (int)($_POST['plantilla_id'] ?? 0);
    $tipoEnt     = $_POST['tipo']       ?? '';
    $entidadId   = (int)($_POST['entidad_id'] ?? 0);
    $columnas    = max(1, min(3, (int)($_POST['columnas'] ?? 2)));

    if (!$plantillaId) {
        setCorsHeaders();
        json_respond(['ok' => false, 'error' => 'plantilla_id requerido'], 400);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM plantillas WHERE id = ?");
    $stmt->execute([$plantillaId]);
    $plantilla = $stmt->fetch();
    if (!$plantilla) {
        setCorsHeaders();
        json_respond(['ok' => false, 'error' => 'Plantilla no encontrada'], 404);
        return;
    }

    $rutaOrig = PLANTILLAS_DIR . basename($plantilla['fichero']);
    if (!file_exists($rutaOrig)) {
        setCorsHeaders();
        json_respond(['ok' => false, 'error' => 'El fichero de la plantilla no existe en el servidor'], 404);
        return;
    }

    // Procesar fotos subidas
    $fotosValidas = [];
    $fotosConvertidas = []; // paths a limpiar al finalizar

    if (!empty($_FILES['fotos']) && is_array($_FILES['fotos']['name'])) {
        $count = count($_FILES['fotos']['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['fotos']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $nombre  = $_FILES['fotos']['name'][$i];
            $tmpPath = $_FILES['fotos']['tmp_name'][$i];
            $ext     = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));

            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) continue;

            // Validar MIME real
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $tmpPath);
                finfo_close($finfo);
                if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) continue;
            }

            // Convertir WebP si es necesario
            if ($ext === 'webp') {
                $conv = _convertirWebpAJpeg($tmpPath);
                if (!$conv['ok']) {
                    setCorsHeaders();
                    json_respond(['ok' => false, 'error' => 'Foto ' . ($i + 1) . ': ' . $conv['error']], 400);
                    return;
                }
                $tmpPath = $conv['path'];
                $ext     = 'jpg';
                $fotosConvertidas[] = $tmpPath;
            }

            $fotosValidas[] = ['name' => $nombre, 'tmp_path' => $tmpPath, 'ext' => $ext];
        }
    }

    // Resolver variables
    $vars = resolverVariables($pdo, $tipoEnt, $entidadId);
    $inqSec       = $vars['_inq_sec_list']  ?? [];
    $multiinqList = $vars['_multiinq_list'] ?? [];
    unset($vars['_inq_sec_list'], $vars['_multiinq_list']);

    // Texto del mobiliario enviado desde el modal (vacío si no se informó)
    $vars['ListaMuebles'] = trim($_POST['lista_muebles'] ?? '');

    // Generar DOCX
    $tmpFichero = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'alquigest_doc_' . uniqid('', true) . '.docx';
    $resultado  = generarDocxConFotos($rutaOrig, $tmpFichero, $vars, $inqSec, $multiinqList, $fotosValidas, $columnas);

    // Limpiar WebP convertidos
    foreach ($fotosConvertidas as $p) {
        if (file_exists($p)) @unlink($p);
    }

    if (!$resultado['ok']) {
        setCorsHeaders();
        json_respond(['ok' => false, 'error' => $resultado['error']], 500);
        return;
    }

    $nombreDescarga = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $plantilla['nombre']) . '_generado.docx';

    if (!empty($resultado['desconocidas'])) {
        header('X-AlquiGest-VarsDesconocidas: ' . implode(',', $resultado['desconocidas']));
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $nombreDescarga . '"');
    header('Content-Length: ' . filesize($tmpFichero));
    header('Cache-Control: no-cache, no-store');
    readfile($tmpFichero);
    unlink($tmpFichero);
    exit;
}

// Genera DOCX con soporte para fotos embebidas en tabla OOXML
function generarDocxConFotos(string $rutaOrig, string $rutaDest, array $vars, array $inqSec, array $multiinqList, array $fotos, int $columnas): array {
    if (!copy($rutaOrig, $rutaDest)) {
        return ['ok' => false, 'error' => 'No se pudo crear el fichero temporal', 'desconocidas' => []];
    }

    if (!class_exists('ZipArchive')) {
        unlink($rutaDest);
        return ['ok' => false, 'error' => 'La extensión ZipArchive no está disponible.', 'desconocidas' => []];
    }

    $zip = new ZipArchive();
    if ($zip->open($rutaDest, ZipArchive::CREATE) !== true) {
        unlink($rutaDest);
        return ['ok' => false, 'error' => 'No se pudo abrir el DOCX como archivo ZIP.', 'desconocidas' => []];
    }

    $todasDesconocidas = [];

    // Leer rels del documento principal
    $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
    if ($relsXml === false) {
        $relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                   '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>';
    }

    // Procesar document.xml con inyección de fotos
    $docXml = $zip->getFromName('word/document.xml');
    if ($docXml !== false) {
        $docXml = expandirBloquesInquilinosSecundarios($docXml, $inqSec);
        $docXml = expandirBloqueMultiinquilino($docXml, $multiinqList);

        if (!empty($fotos) && strpos($docXml, '{{FotosContrato}}') !== false) {
            $injectResult = _inyectarTablaFotos($docXml, $fotos, $columnas, $zip, $relsXml);
            $docXml  = $injectResult['xml'];
            $relsXml = $injectResult['relsXml'];
        }

        $resultado = procesarXmlWord($docXml, $vars);
        $zip->addFromString('word/document.xml', $resultado['xml']);
        foreach ($resultado['desconocidas'] as $v) $todasDesconocidas[] = $v;

        $zip->addFromString('word/_rels/document.xml.rels', $relsXml);
    }

    // Cabeceras y pies (solo sustitución de texto, sin fotos)
    $xmlsExtra = [
        'word/header1.xml', 'word/header2.xml', 'word/header3.xml',
        'word/footer1.xml', 'word/footer2.xml', 'word/footer3.xml',
    ];
    foreach ($xmlsExtra as $entrada) {
        $contenido = $zip->getFromName($entrada);
        if ($contenido === false) continue;
        $contenido = expandirBloquesInquilinosSecundarios($contenido, $inqSec);
        $contenido = expandirBloqueMultiinquilino($contenido, $multiinqList);
        $resultado = procesarXmlWord($contenido, $vars);
        $zip->addFromString($entrada, $resultado['xml']);
        foreach ($resultado['desconocidas'] as $v) $todasDesconocidas[] = $v;
    }

    $zip->close();

    return [
        'ok'           => true,
        'desconocidas' => array_values(array_unique($todasDesconocidas)),
        'error'        => '',
    ];
}

// Localiza el párrafo {{FotosContrato}} en el XML y lo reemplaza por la tabla de fotos
function _inyectarTablaFotos(string $xml, array $fotos, int $columnas, ZipArchive $zip, string $relsXml): array {
    if (!preg_match_all('/(<w:p[ >].*?<\/w:p>)/s', $xml, $matches, PREG_OFFSET_CAPTURE)) {
        return ['xml' => $xml, 'relsXml' => $relsXml];
    }

    $paras = $matches[1];
    $targetIdx = -1;
    foreach ($paras as $i => $p) {
        $txt = '';
        if (preg_match_all('/<w:t(?:\s[^>]*)?>([^<]*)<\/w:t>/s', $p[0], $m)) {
            foreach ($m[1] as $f) $txt .= html_entity_decode($f, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        }
        if (trim($txt) === '{{FotosContrato}}') {
            $targetIdx = $i;
            break;
        }
    }

    if ($targetIdx < 0) return ['xml' => $xml, 'relsXml' => $relsXml];

    $tblXml = _construirTablaFotos($fotos, $columnas, $zip, $relsXml);

    $startByte = $paras[$targetIdx][1];
    $endByte   = $paras[$targetIdx][1] + strlen($paras[$targetIdx][0]);
    $xml = substr($xml, 0, $startByte) . $tblXml . substr($xml, $endByte);

    return ['xml' => $xml, 'relsXml' => $relsXml];
}

// Construye el XML OOXML <w:tbl> con las fotos y actualiza el ZIP y los rels
function _construirTablaFotos(array $fotos, int $columnas, ZipArchive $zip, string &$relsXml): string {
    // Ancho usable A4 con márgenes de 2.5 cm: 9026 twips
    $usableWidthDxa = 9026;
    $colWidthDxa    = intdiv($usableWidthDxa, $columnas);
    $cellMarginDxa  = 56;  // pequeño margen interior de celda (≈ 1mm)
    $imgWidthDxa    = $colWidthDxa - 2 * $cellMarginDxa;
    $imgWidthEmu    = $imgWidthDxa * 635; // 1 twip = 635 EMU

    $imgsData = [];
    foreach ($fotos as $idx => $foto) {
        $n   = $idx + 1;
        $ext = $foto['ext'];
        $zipMediaPath = 'word/media/foto_' . $n . '.' . $ext;

        // Añadir imagen al ZIP
        $zip->addFile($foto['tmp_path'], $zipMediaPath);

        // Añadir relación al rels XML
        $rIdStr  = 'rIdFoto' . $n;
        $relType = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';
        $newRel  = '<Relationship Id="' . $rIdStr . '" Type="' . $relType . '" Target="media/foto_' . $n . '.' . $ext . '"/>';
        $relsXml = str_replace('</Relationships>', $newRel . '</Relationships>', $relsXml);

        // Dimensiones reales para mantener proporción
        $imgSize = @getimagesize($foto['tmp_path']);
        $imgW    = ($imgSize && $imgSize[0] > 0) ? (int)$imgSize[0] : 800;
        $imgH    = ($imgSize && $imgSize[1] > 0) ? (int)$imgSize[1] : 600;
        $heightEmu = ($imgW > 0) ? (int)round($imgWidthEmu * $imgH / $imgW) : (int)round($imgWidthEmu * 3 / 4);

        $imgsData[] = [
            'rId'       => $rIdStr,
            'name'      => 'foto_' . $n,
            'widthEmu'  => $imgWidthEmu,
            'heightEmu' => $heightEmu,
            'picId'     => 5000 + $n, // IDs altos para no colisionar con los del documento original
        ];
    }

    // Columnas de la tabla
    $gridCols = '';
    for ($c = 0; $c < $columnas; $c++) {
        $gridCols .= '<w:gridCol w:w="' . $colWidthDxa . '"/>';
    }

    // Filas con las fotos
    $rows   = '';
    $chunks = array_chunk($imgsData, $columnas);
    foreach ($chunks as $chunk) {
        $rows .= '<w:tr>';
        for ($c = 0; $c < $columnas; $c++) {
            if (isset($chunk[$c])) {
                $rows .= _celdaFotoXml($chunk[$c], $colWidthDxa);
            } else {
                $rows .= '<w:tc><w:tcPr><w:tcW w:w="' . $colWidthDxa . '" w:type="dxa"/></w:tcPr>' .
                         '<w:p><w:pPr><w:jc w:val="center"/></w:pPr></w:p></w:tc>';
            }
        }
        $rows .= '</w:tr>';
    }

    return '<w:tbl>' .
           '<w:tblPr>' .
           '<w:tblW w:w="' . $usableWidthDxa . '" w:type="dxa"/>' .
           '<w:jc w:val="center"/>' .
           '<w:tblBorders>' .
           '<w:top w:val="none" w:sz="0" w:space="0" w:color="auto"/>' .
           '<w:left w:val="none" w:sz="0" w:space="0" w:color="auto"/>' .
           '<w:bottom w:val="none" w:sz="0" w:space="0" w:color="auto"/>' .
           '<w:right w:val="none" w:sz="0" w:space="0" w:color="auto"/>' .
           '<w:insideH w:val="none" w:sz="0" w:space="0" w:color="auto"/>' .
           '<w:insideV w:val="none" w:sz="0" w:space="0" w:color="auto"/>' .
           '</w:tblBorders>' .
           '<w:tblCellMar>' .
           '<w:top w:w="' . $cellMarginDxa . '" w:type="dxa"/>' .
           '<w:left w:w="' . $cellMarginDxa . '" w:type="dxa"/>' .
           '<w:bottom w:w="' . $cellMarginDxa . '" w:type="dxa"/>' .
           '<w:right w:w="' . $cellMarginDxa . '" w:type="dxa"/>' .
           '</w:tblCellMar>' .
           '</w:tblPr>' .
           '<w:tblGrid>' . $gridCols . '</w:tblGrid>' .
           $rows .
           '</w:tbl>';
}

// Genera el XML OOXML de una celda de tabla con una imagen embebida
function _celdaFotoXml(array $img, int $colWidthDxa): string {
    $nsWp  = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';
    $nsA   = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    $nsPic = 'http://schemas.openxmlformats.org/drawingml/2006/picture';
    $nsR   = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    return '<w:tc>' .
           '<w:tcPr><w:tcW w:w="' . $colWidthDxa . '" w:type="dxa"/></w:tcPr>' .
           '<w:p>' .
           '<w:pPr><w:jc w:val="center"/></w:pPr>' .
           '<w:r>' .
           '<w:drawing>' .
           '<wp:inline distT="0" distB="0" distL="0" distR="0" xmlns:wp="' . $nsWp . '">' .
           '<wp:extent cx="' . $img['widthEmu'] . '" cy="' . $img['heightEmu'] . '"/>' .
           '<wp:effectExtent l="0" t="0" r="0" b="0"/>' .
           '<wp:docPr id="' . $img['picId'] . '" name="' . $img['name'] . '"/>' .
           '<wp:cNvGraphicFramePr>' .
           '<a:graphicFrameLocks xmlns:a="' . $nsA . '" noChangeAspect="1"/>' .
           '</wp:cNvGraphicFramePr>' .
           '<a:graphic xmlns:a="' . $nsA . '">' .
           '<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">' .
           '<pic:pic xmlns:pic="' . $nsPic . '">' .
           '<pic:nvPicPr>' .
           '<pic:cNvPr id="' . $img['picId'] . '" name="' . $img['name'] . '"/>' .
           '<pic:cNvPicPr><a:picLocks noChangeAspect="1" noChangeArrowheads="1"/></pic:cNvPicPr>' .
           '</pic:nvPicPr>' .
           '<pic:blipFill>' .
           '<a:blip r:embed="' . $img['rId'] . '" xmlns:r="' . $nsR . '"/>' .
           '<a:stretch><a:fillRect/></a:stretch>' .
           '</pic:blipFill>' .
           '<pic:spPr bwMode="auto">' .
           '<a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $img['widthEmu'] . '" cy="' . $img['heightEmu'] . '"/></a:xfrm>' .
           '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom>' .
           '</pic:spPr>' .
           '</pic:pic>' .
           '</a:graphicData>' .
           '</a:graphic>' .
           '</wp:inline>' .
           '</w:drawing>' .
           '</w:r>' .
           '</w:p>' .
           '</w:tc>';
}

// Convierte una imagen WebP a JPEG usando PHP GD
function _convertirWebpAJpeg(string $tmpPath): array {
    if (!function_exists('imagecreatefromwebp')) {
        return ['ok' => false, 'error' => 'Tu servidor no soporta WebP (extensión GD sin soporte WebP). Convierte la imagen a JPG o PNG antes de subir.'];
    }
    $img = @imagecreatefromwebp($tmpPath);
    if (!$img) {
        return ['ok' => false, 'error' => 'No se pudo leer el fichero WebP.'];
    }
    $newPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'webp_conv_' . uniqid('', true) . '.jpg';
    if (!imagejpeg($img, $newPath, 90)) {
        imagedestroy($img);
        return ['ok' => false, 'error' => 'Error al convertir WebP a JPEG.'];
    }
    imagedestroy($img);
    return ['ok' => true, 'path' => $newPath];
}
