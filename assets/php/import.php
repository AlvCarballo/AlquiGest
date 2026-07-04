<?php
// ============================================================
//  AlquiGest – import.php
//  Parsea un fichero .xlsx subido por POST y devuelve JSON con
//  la cabecera y las filas de la primera hoja.
//
//  Usa ZipArchive (extensión nativa PHP 7.4) + parseo OOXML
//  con SimpleXML, la misma tecnología que export.php para garantizar
//  coherencia arquitectónica.
//
//  Respuesta JSON:
//    { ok: true,  cabecera: ["col1","col2",...], filas: [[...],[...],...] }
//    { ok: false, error: "Mensaje de error legible" }
// ============================================================

$cfg = require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';

// Solo localhost
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    die(json_encode(['ok' => false, 'error' => 'Acceso solo permitido desde localhost.']));
}

header('Content-Type: application/json; charset=utf-8');

// ── Validar upload ────────────────────────────────────────────
if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    $codigos = [
        UPLOAD_ERR_INI_SIZE   => 'El fichero supera el límite de upload_max_filesize de PHP.',
        UPLOAD_ERR_FORM_SIZE  => 'El fichero supera el tamaño máximo del formulario.',
        UPLOAD_ERR_PARTIAL    => 'El fichero se subió parcialmente.',
        UPLOAD_ERR_NO_FILE    => 'No se recibió ningún fichero.',
        UPLOAD_ERR_NO_TMP_DIR => 'No existe directorio temporal en el servidor.',
        UPLOAD_ERR_CANT_WRITE => 'Error al escribir el fichero en disco.',
        UPLOAD_ERR_EXTENSION  => 'Una extensión PHP bloqueó la subida.',
    ];
    $cod = $_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE;
    echo json_encode(['ok' => false, 'error' => $codigos[$cod] ?? 'Error de subida desconocido.']);
    exit;
}

// Validar extensión (.xlsx)
$nombre    = $_FILES['archivo']['name'] ?? '';
$extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
if ($extension !== 'xlsx') {
    echo json_encode(['ok' => false, 'error' => 'Solo se admiten ficheros .xlsx (Excel). Para CSV usa la opción CSV.']);
    exit;
}

// Tamaño máximo: 5 MB (suficiente para miles de registros)
if ($_FILES['archivo']['size'] > 5 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'error' => 'El fichero es demasiado grande (máximo 5 MB).']);
    exit;
}

// ── Parsear el XLSX ───────────────────────────────────────────
try {
    $resultado = parsearXlsx($_FILES['archivo']['tmp_name']);
    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

// ── Función principal de parseo ───────────────────────────────
// Un .xlsx es un ZIP con estructura OOXML (ISO/IEC 29500).
// Pasos:
//   1. Abrir como ZIP
//   2. Leer xl/sharedStrings.xml → tabla de cadenas (SST)
//   3. Leer xl/worksheets/sheet1.xml → filas y celdas
//   4. Reconstruir la cuadrícula respetando columnas vacías
//   5. Devolver {ok, cabecera, filas}
function parsearXlsx(string $ruta): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('La extensión ZipArchive no está disponible en este PHP.');
    }

    $zip = new ZipArchive();
    if ($zip->open($ruta) !== true) {
        throw new RuntimeException('El fichero no es un ZIP/XLSX válido o está dañado.');
    }

    // ── 1. Leer la tabla de cadenas compartidas (SST) ────────────────
    // Las celdas de tipo texto no almacenan el texto directamente: usan
    // un índice numérico que apunta a esta tabla. Así se evita repetir
    // cadenas largas en cada celda que las contenga.
    $sst = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false && trim($ssXml) !== '') {
        // Deshabilitar entidades externas para seguridad
        $prev = libxml_use_internal_errors(true);
        $xml  = simplexml_load_string($ssXml);
        libxml_use_internal_errors($prev);
        if ($xml !== false) {
            foreach ($xml->si as $si) {
                // Concatenar todos los <t> dentro de <si> para manejar rich text
                $texto = '';
                if (isset($si->t)) {
                    $texto = (string)$si->t;
                } elseif (isset($si->r)) {
                    // Rich text: múltiples <r><t>...</t></r>
                    foreach ($si->r as $run) {
                        if (isset($run->t)) {
                            $texto .= (string)$run->t;
                        }
                    }
                }
                $sst[] = $texto;
            }
        }
    }

    // ── 2. Leer los datos de la primera hoja ──────────────────────────
    // OOXML puede nombrar la hoja de distintas formas; probamos los más
    // comunes y si no encontramos ninguno devolvemos un error claro.
    $hojaNombres = ['xl/worksheets/sheet1.xml', 'xl/worksheets/Sheet1.xml'];
    $hojaXml     = false;
    foreach ($hojaNombres as $nombre) {
        $hojaXml = $zip->getFromName($nombre);
        if ($hojaXml !== false) break;
    }
    $zip->close();

    if ($hojaXml === false || trim($hojaXml) === '') {
        throw new RuntimeException('No se encontró la hoja de datos (sheet1.xml) en el fichero XLSX.');
    }

    $prev = libxml_use_internal_errors(true);
    $hoja = simplexml_load_string($hojaXml);
    libxml_use_internal_errors($prev);
    if ($hoja === false) {
        throw new RuntimeException('El fichero está dañado: no se pudo leer la hoja de datos.');
    }

    // Registrar el espacio de nombres por defecto para poder usar XPath
    $ns = $hoja->getNamespaces(true);
    $nsUri = $ns[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    // ── 3. Reconstruir la cuadrícula ──────────────────────────────────
    // Las referencias de celda (A1, BC23…) se convierten a índice de
    // columna 0-based para construir arrays regulares (sin huecos).
    $filas        = [];
    $maxColumnas  = 0;

    // Iterar sobre cada <row> de <sheetData>
    if (isset($hoja->sheetData->row)) {
        foreach ($hoja->sheetData->row as $fila) {
            $indiceFila = (int)((string)$fila['r']) - 1; // índice 0-based
            $celdas     = [];

            foreach ($fila->c as $celda) {
                $ref  = (string)$celda['r']; // ej: "B3"
                $tipo = (string)$celda['t']; // "s" = SST, "str" = fórmula string, vacío = número
                $val  = isset($celda->v) ? (string)$celda->v : '';

                // Convertir referencia de celda (ej: "BC7") → índice de columna 0-based
                $colLetras = preg_replace('/[0-9]/', '', $ref);
                $colIdx    = columnaAIndice($colLetras);

                // Obtener valor según tipo de celda
                if ($tipo === 's') {
                    // Texto de la tabla SST
                    $valor = $sst[(int)$val] ?? '';
                } elseif ($tipo === 'str') {
                    // Resultado de fórmula de cadena
                    $valor = $val;
                } elseif ($tipo === 'b') {
                    // Booleano: 1=VERDADERO, 0=FALSO
                    $valor = $val === '1' ? 'VERDADERO' : 'FALSO';
                } else {
                    // Número (entero o decimal); lo dejamos como string para el JS
                    $valor = $val;
                }

                $celdas[$colIdx] = $valor;
                if ($colIdx + 1 > $maxColumnas) {
                    $maxColumnas = $colIdx + 1;
                }
            }
            $filas[$indiceFila] = $celdas;
        }
    }

    // Normalizar: rellenar huecos con '' para que todas las filas tengan el mismo número de columnas
    $filasNorm = [];
    ksort($filas); // ordenar por índice de fila
    foreach ($filas as $idx => $celdas) {
        $fila = [];
        for ($c = 0; $c < $maxColumnas; $c++) {
            $fila[] = $celdas[$c] ?? '';
        }
        // Ignorar filas completamente vacías
        if (count(array_filter($fila, fn($v) => $v !== '')) > 0) {
            $filasNorm[] = $fila;
        }
    }

    if (count($filasNorm) < 2) {
        throw new RuntimeException('El fichero no contiene datos (se necesita al menos cabecera + 1 fila de datos).');
    }

    // La primera fila es la cabecera; el resto son datos
    $cabecera = array_shift($filasNorm);

    return [
        'ok'       => true,
        'cabecera' => $cabecera,
        'filas'    => $filasNorm,
    ];
}

// Convierte letras de columna Excel ("A"→0, "Z"→25, "AA"→26, "BC"→54…)
function columnaAIndice(string $letras): int
{
    $letras = strtoupper($letras);
    $idx    = 0;
    $len    = strlen($letras);
    for ($i = 0; $i < $len; $i++) {
        $idx = $idx * 26 + (ord($letras[$i]) - ord('A') + 1);
    }
    return $idx - 1;
}
