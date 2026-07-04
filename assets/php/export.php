<?php
// ============================================================
//  AlquiGest – Exportación de informes en formato Excel (XLSX)
//
//  Uso: GET export.php?tipo=<nombre>&anyo=<año>
//
//  Tipos de informe disponibles:
//    · ingresos-finca         → ingresos mensuales agrupados por finca
//    · ingresos-piso          → ingresos mensuales por cada inmueble/unidad
//    · pendientes             → recibos sin cobrar o con pago parcial
//    · historico-cobros       → todos los pagos registrados del año
//    · resumen-propietario    → facturado y cobrado por propietario y finca
//    · recibos-anyo           → listado completo de recibos del ejercicio
//    · fiscal-trimestral      → rendimientos por trimestre con reducción 60 %
//    · irpf-anual             → hoja para la Declaración de la Renta (Mod. 100)
//    · modelo-115             → retenciones trimestrales y resumen anual (Mod. 180)
//    · irpf-propietario       → recibos cobrados por propietario para IRPF (anyo + propietario_id)
//    · iva-trimestral         → IVA repercutido trimestral para el Modelo 303
//
//  El XLSX se genera íntegramente en PHP usando ZipArchive (extensión nativa),
//  sin dependencias de PHPExcel, PhpSpreadsheet ni ninguna librería externa.
//  El OOXML producido es compatible con Excel, LibreOffice Calc y Google Sheets.
//
//  Normativa fiscal implementada: LIRPF arts. 22-24, reducción 60 % (art. 23.2)
//  Solo aplicable a contratos de vivienda con duración ≥ 1 año.
// ============================================================

// Capturar cualquier warning/notice de PHP para que no corrompa el binario XLSX
ob_start();

$cfg = require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';

// Restringir acceso a localhost únicamente
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    ob_end_clean();
    http_response_code(403);
    die('Acceso solo permitido desde localhost.');
}

// ── Generador de XLSX en PHP puro (solo ZipArchive nativa) ───
// Un archivo XLSX es en realidad un ZIP que contiene ficheros XML siguiendo
// el estándar OOXML (Office Open XML, ISO/IEC 29500).
// Esta función construye todos los XML necesarios y los empaqueta en un ZIP:
//   · [Content_Types].xml  → declara los tipos MIME de cada parte
//   · _rels/.rels          → relación raíz con el libro Excel
//   · xl/workbook.xml      → libro: lista de hojas
//   · xl/_rels/workbook.xml.rels → relaciones del libro con la hoja y estilos
//   · xl/worksheets/sheet1.xml  → datos de la hoja (filas y celdas)
//   · xl/sharedStrings.xml → tabla de cadenas compartidas (SST): todas las
//     celdas de texto referencian por índice a esta tabla en lugar de repetir
//     el texto en cada celda, lo que reduce el tamaño del fichero.
//   · xl/styles.xml        → dos estilos: normal y negrita (para la cabecera)
//
// Parámetro $data: array de arrays (primera fila = cabecera, se pone en negrita)
// Devuelve: string binario del ZIP/XLSX listo para enviar con el header adecuado.
function buildXLSX(array $data, string $sheetName = 'Informe'): string {
    $strings = []; $sIdx = [];
    $totalStrUsage = 0; // total de usos de cadena en celdas (puede ser > uniqueCount por repeticiones)

    // Eliminar caracteres de control XML ilegales (<, >, control chars) del valor de celda
    $cleanXml = function($s): string {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string)$s);
    };

    // Añade una cadena a la SST si no existe y devuelve su índice
    $addStr = function($s) use (&$strings, &$sIdx, &$totalStrUsage, $cleanXml): int {
        $totalStrUsage++;
        $s = $cleanXml($s);
        if (!isset($sIdx[$s])) { $sIdx[$s] = count($strings); $strings[] = $s; }
        return $sIdx[$s];
    };

    // Secuencia de nombres de columna Excel: A-Z, luego AA-AZ, BA-BZ, ..., ZA-ZZ (702 columnas)
    $alpha = range('A', 'Z');
    foreach (range('A', 'Z') as $c1) {
        foreach (range('A', 'Z') as $c2) {
            $alpha[] = $c1 . $c2;
        }
    }

    $rowsXml = '';
    foreach ($data as $ri => $row) {
        $cells = '';
        $style = ($ri === 0) ? ' s="1"' : ''; // fila 0 = cabecera en negrita (estilo 1)
        foreach (array_values($row) as $ci => $val) {
            $col = $alpha[$ci];
            $ref = $col . ($ri + 1); // referencia de celda, ej: "B3"
            if (is_array($val) || is_null($val)) { $val = ''; }
            if (is_bool($val)) { $val = $val ? '1' : '0'; }
            if (is_numeric($val) && $val !== '') {
                // Números: sprintf '%.10F' garantiza notación decimal (nunca exponencial como 1E+6)
                $numStr = rtrim(rtrim(sprintf('%.10F', (float)$val), '0'), '.');
                if ($numStr === '' || $numStr === '-') $numStr = '0';
                $cells .= "<c r=\"{$ref}\"{$style}><v>{$numStr}</v></c>";
            } elseif ($val !== '') {
                // Texto no vacío: referencia al índice de la SST (t="s")
                // El atributo 's' (style) debe ir antes que 't' (type) según el esquema OOXML
                $si = $addStr($val);
                $cells .= "<c r=\"{$ref}\"{$style} t=\"s\"><v>{$si}</v></c>";
            }
            // Celda vacía: se omite; Excel acepta filas dispersas (sparse rows)
        }
        $rowsXml .= "<row r=\"" . ($ri + 1) . "\">{$cells}</row>";
    }

    // count = usos totales de cadena; uniqueCount = cadenas únicas en SST
    $ssXml  = '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $totalStrUsage . '" uniqueCount="' . count($strings) . '">';
    foreach ($strings as $s) {
        $ssXml .= '<si><t xml:space="preserve">' . htmlspecialchars($s, ENT_XML1, 'UTF-8') . '</t></si>';
    }
    $ssXml .= '</sst>';

    $sheetXml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $rowsXml . '</sheetData></worksheet>';
    $wbXml    = '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . htmlspecialchars($sheetName, ENT_XML1) . '" sheetId="1" r:id="rId1"/></sheets></workbook>';
    $wbRels   = '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    $rels     = '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    $ct       = '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';
    $styles   = '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles><dxfs count="0"/><tableStyles count="0" defaultTableStyle="TableStyleMedium9" defaultPivotStyle="PivotStyleLight16"/></styleSheet>';

    // Crear el ZIP en un fichero temporal, llenarlo con todos los XML y leerlo
    $tmp = tempnam(sys_get_temp_dir(), 'ag_xlsx_');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('No se pudo crear el archivo XLSX temporal');
    }
    $zip->addFromString('[Content_Types].xml',          $ct);
    $zip->addFromString('_rels/.rels',                  $rels);
    $zip->addFromString('xl/workbook.xml',              $wbXml);
    $zip->addFromString('xl/_rels/workbook.xml.rels',   $wbRels);
    $zip->addFromString('xl/worksheets/sheet1.xml',     $sheetXml);
    $zip->addFromString('xl/sharedStrings.xml',         $ssXml);
    $zip->addFromString('xl/styles.xml',                $styles);
    $zip->close();
    $content = file_get_contents($tmp);
    unlink($tmp);
    return $content;
}

// ── Conexión a MySQL y carga de datos ────────────────────────
// Se cargan todas las tablas en memoria de una vez para evitar
// múltiples consultas dentro de los bucles de generación del informe.
try {
    $pdo = new PDO(
        "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset={$cfg['charset']}",
        $cfg['user'], $cfg['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(503); die('Error BD: ' . $e->getMessage());
}

$tipo  = $_GET['tipo']  ?? '';
$anyo  = (int)($_GET['anyo'] ?? date('Y'));
$meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$mAbr  = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

$recibos      = getTable($pdo, 'recibos');
$inmuebles    = getTable($pdo, 'inmuebles');
$fincas       = getTable($pdo, 'fincas');
$inquilinos   = getTable($pdo, 'inquilinos');
$propietarios = getTable($pdo, 'propietarios');
$contratos    = getTable($pdo, 'contratos');

// ── Funciones auxiliares (closures) ──────────────────────────
// Se usan como funciones locales al no necesitar ser accesibles globalmente.

// Devuelve el nombre legible de un inmueble combinando finca + planta + puerta
$inmNombre = function(array $inm) use ($fincas): string {
    $f = current(array_filter($fincas, fn($x) => $x['id'] === ($inm['finca_id'] ?? 0)));
    $base = $f ? trim(($f['sigla'] ?? '') . ' ' . ($f['calle'] ?? '') . ' ' . ($f['numero'] ?? '')) : '';
    $ud   = trim(($inm['planta'] ?? '') . ' ' . ($inm['puerta'] ?? ''));
    return trim("$base $ud");
};

// Suma el importe_total de todos los recibos no anulados de los inmuebles $inmIds
// cuyo concepto_periodo contiene el nombre del mes $m del año $anyo.
// Se usa para rellenar las columnas mensuales de los informes de ingresos.
$porMes = function(array $recs, array $inmIds, int $m) use ($anyo, $meses): float {
    return (float)array_sum(array_map(fn($r) =>
        in_array($r['inmueble_id'] ?? 0, $inmIds) &&
        ($r['estado'] ?? '') !== 'anulado' &&
        strpos($r['concepto_periodo'] ?? '', $meses[$m - 1]) !== false &&
        strpos($r['fecha_emision'] ?? '', (string)$anyo) === 0
        ? (float)($r['importe_total'] ?? 0) : 0,
    $recs));
};

// Determina si un contrato tiene duración >= 1 año.
// Los contratos indefinidos (sin fecha_fin) se consideran >= 1 año.
// Necesario para decidir si aplica la reducción del 60 % (art. 23.2 LIRPF):
// solo viviendas con contrato de al menos un año tienen derecho a la reducción.
$conMin1Anyo = function($con): bool {
    if (!$con) return false;
    $inicio = $con['fecha_inicio'] ?? null;
    $fin    = $con['fecha_fin']    ?? null;
    if (!$inicio) return false;
    if (!$fin)    return true;  // indefinido → se considera >= 1 año
    return (strtotime($fin) - strtotime($inicio)) >= 365 * 24 * 3600;
};

// Suma la base imponible (renta_base) y la retención IRPF real (importe_irpf > 0)
// de los recibos de un inmueble en los meses indicados ($ml = [1,2,3] para el 1T).
// Solo se incluyen recibos que realmente aplicaron retención (importe_irpf > 0).
// Se usa para el Modelo 115 (arrendatario empresa/autónomo que retiene al propietario).
$porTrimIRPF = function(array $recs, int $inmId, array $ml) use ($anyo, $meses): array {
    $base = 0.0; $irpf = 0.0;
    foreach ($recs as $r) {
        if (($r['inmueble_id'] ?? 0) !== $inmId) continue;
        if (($r['estado'] ?? '') === 'anulado')   continue;
        if ((float)($r['importe_irpf'] ?? 0) <= 0) continue;
        if (strpos($r['fecha_emision'] ?? '', (string)$anyo) !== 0) continue;
        $enTrim = false;
        foreach ($ml as $m) {
            if (stripos($r['concepto_periodo'] ?? '', $meses[$m - 1]) !== false) {
                $enTrim = true; break;
            }
        }
        if (!$enTrim) continue;
        $base += (float)($r['renta_base']    ?? 0);
        $irpf += (float)($r['importe_irpf']  ?? 0);
    }
    return ['base' => $base, 'irpf' => $irpf];
};

$data = []; $nombre = 'Informe'; // $data = filas del informe; $nombre = nombre del fichero XLSX

// ── Selector de informe ───────────────────────────────────────
// Cada case construye el array $data con cabecera + filas + totales.
// La primera fila de $data siempre es la cabecera (aparece en negrita en el XLSX).
switch ($tipo) {

    // ── Ingresos mensuales agrupados por finca/edificio ───────
    case 'ingresos-finca':
        $nombre = "Ingresos_Finca_{$anyo}";
        $data[] = array_merge(['Finca'], $mAbr, ['TOTAL']);
        $totMes = array_fill(0, 12, 0.0);
        $totAnyo = 0.0;
        foreach ($fincas as $f) {
            $ids  = array_column(array_filter($inmuebles, fn($i) => ($i['finca_id'] ?? 0) === $f['id']), 'id');
            $fila = [$f['nombre'] ?? '-'];
            $tot  = 0.0;
            for ($m = 1; $m <= 12; $m++) {
                $v = $porMes($recibos, $ids, $m);
                $fila[] = $v;
                $tot += $v;
                $totMes[$m - 1] += $v;
            }
            $fila[] = $tot;
            $totAnyo += $tot;
            $data[] = $fila;
        }
        $totFila = ['TOTAL'];
        foreach ($totMes as $v) $totFila[] = $v;
        $totFila[] = $totAnyo;
        $data[] = $totFila;
        break;

    // ── Ingresos mensuales por cada piso/unidad, con inquilino ─
    case 'ingresos-piso':
        $nombre = "Ingresos_Piso_{$anyo}";
        $data[] = array_merge(['Finca', 'Inmueble', 'Inquilino'], $mAbr, ['TOTAL']);
        $totMes = array_fill(0, 12, 0.0);
        $totAnyo = 0.0;
        foreach ($inmuebles as $inm) {
            $f   = current(array_filter($fincas, fn($x) => $x['id'] === ($inm['finca_id'] ?? 0)));
            $con = current(array_filter($contratos, fn($c) => ($c['inmueble_id'] ?? 0) === $inm['id'] && ($c['estado'] ?? '') === 'activo'));
            $inq = $con ? current(array_filter($inquilinos, fn($i) => $i['id'] === ($con['inquilino_id'] ?? 0))) : null;
            $fila = [
                $f   ? ($f['nombre'] ?? '-')   : '-',
                $inmNombre($inm),
                $inq ? ($inq['nombre'] ?? '-') : '-',
            ];
            $tot = 0.0;
            for ($m = 1; $m <= 12; $m++) {
                $v = $porMes($recibos, [$inm['id']], $m);
                $fila[] = $v;
                $tot += $v;
                $totMes[$m - 1] += $v;
            }
            $fila[] = $tot;
            $totAnyo += $tot;
            $data[] = $fila;
        }
        $totFila = ['TOTAL', '', ''];
        foreach ($totMes as $v) $totFila[] = $v;
        $totFila[] = $totAnyo;
        $data[] = $totFila;
        break;

    // ── Recibos sin cobrar o con pago parcial ─────────────────
    case 'pendientes':
        $nombre = 'Recibos_Pendientes';
        $data[] = ['Nº Recibo', 'Fecha Emisión', 'Inquilino', 'Inmueble', 'Período', 'Total', 'Pagado', 'Pendiente', 'Estado'];
        $sTot = 0.0; $sPag = 0.0; $sPend = 0.0;
        foreach ($recibos as $r) {
            if (!in_array($r['estado'] ?? '', ['pendiente', 'parcial'])) continue;
            $inq = current(array_filter($inquilinos, fn($i) => $i['id'] === ($r['inquilino_id'] ?? 0)));
            $inm = current(array_filter($inmuebles,  fn($i) => $i['id'] === ($r['inmueble_id']  ?? 0)));
            $pag = (float)array_sum(array_column($r['pagos'] ?? [], 'importe'));
            $tot = (float)($r['importe_total'] ?? 0);
            $sTot += $tot; $sPag += $pag; $sPend += ($tot - $pag);
            $data[] = [
                $r['numero_recibo'] ?? '-',
                $r['fecha_emision'] ?? '-',
                $inq ? ($inq['nombre'] ?? '-') : '-',
                $inm ? $inmNombre($inm) : '-',
                $r['concepto_periodo'] ?? '-',
                $tot, $pag, $tot - $pag,
                $r['estado'] ?? '-',
            ];
        }
        $data[] = ['TOTAL', '', '', '', '', $sTot, $sPag, $sPend, ''];
        break;

    // ── Histórico de cobros: un apunte por cada pago registrado ─
    // Los pagos están en el array JSON 'pagos' de cada recibo.
    case 'historico-cobros':
        $nombre = "Historico_Cobros_{$anyo}";
        $data[] = ['Fecha Pago', 'Nº Recibo', 'Inquilino', 'Inmueble', 'Período', 'Importe', 'Método', 'Cuenta'];
        $sImporte = 0.0;
        foreach ($recibos as $r) {
            $fe = substr($r['fecha_emision'] ?? '', 0, 4);
            if ($fe !== (string)$anyo) continue;
            $inq = current(array_filter($inquilinos, fn($i) => $i['id'] === ($r['inquilino_id'] ?? 0)));
            $inm = current(array_filter($inmuebles,  fn($i) => $i['id'] === ($r['inmueble_id']  ?? 0)));
            foreach ($r['pagos'] ?? [] as $p) {
                $imp = (float)($p['importe'] ?? 0);
                $sImporte += $imp;
                $data[] = [
                    $p['fecha']   ?? '-',
                    $r['numero_recibo'] ?? '-',
                    $inq ? ($inq['nombre'] ?? '-') : '-',
                    $inm ? $inmNombre($inm) : '-',
                    $r['concepto_periodo'] ?? '-',
                    $imp,
                    $p['metodo'] ?? '-',
                    $p['cuenta'] ?? '-',
                ];
            }
        }
        $data[] = ['TOTAL', '', '', '', '', $sImporte, '', ''];
        break;

    // ── Resumen por propietario: facturado vs cobrado vs pendiente ─
    case 'resumen-propietario':
        $nombre = "Resumen_Propietario_{$anyo}";
        $data[] = ['Propietario', 'Finca', 'Inmuebles', 'Facturado', 'Cobrado', 'Pendiente'];
        $sFact = 0.0; $sCob = 0.0;
        foreach ($propietarios as $prop) {
            $fp = array_filter($fincas, fn($f) => ($f['propietario_id'] ?? 0) === $prop['id']);
            foreach ($fp as $f) {
                $ids  = array_column(array_filter($inmuebles, fn($i) => ($i['finca_id'] ?? 0) === $f['id']), 'id');
                $recs = array_filter($recibos, fn($r) =>
                    in_array($r['inmueble_id'] ?? 0, $ids) &&
                    ($r['estado'] ?? '') !== 'anulado' &&
                    strpos($r['fecha_emision'] ?? '', (string)$anyo) === 0
                );
                $fact = (float)array_sum(array_column($recs, 'importe_total'));
                $cob  = (float)array_sum(array_map(
                    fn($r) => array_sum(array_column($r['pagos'] ?? [], 'importe')),
                    $recs
                ));
                $sFact += $fact; $sCob += $cob;
                $data[] = [
                    $prop['nombre'] ?? '-',
                    $f['nombre']    ?? '-',
                    count($ids),
                    $fact, $cob, $fact - $cob,
                ];
            }
        }
        $data[] = ['TOTAL', '', '', $sFact, $sCob, $sFact - $sCob];
        break;

    // ── Listado completo de recibos del ejercicio ──────────────
    case 'recibos-anyo':
        $nombre = "Recibos_{$anyo}";
        $data[] = ['Nº Recibo', 'Fecha', 'Inquilino', 'Inmueble', 'Período', 'Base', 'IVA', 'IRPF', 'Total', 'Pagado', 'Pendiente', 'Estado'];
        $sFact = 0.0; $sCob = 0.0;
        foreach ($recibos as $r) {
            $fe = substr($r['fecha_emision'] ?? '', 0, 4);
            if ($fe !== (string)$anyo || ($r['estado'] ?? '') === 'anulado') continue;
            $inq = current(array_filter($inquilinos, fn($i) => $i['id'] === ($r['inquilino_id'] ?? 0)));
            $inm = current(array_filter($inmuebles,  fn($i) => $i['id'] === ($r['inmueble_id']  ?? 0)));
            $pag = (float)array_sum(array_column($r['pagos'] ?? [], 'importe'));
            $tot = (float)($r['importe_total'] ?? 0);
            $sFact += $tot; $sCob += $pag;
            $data[] = [
                $r['numero_recibo']    ?? '-',
                $r['fecha_emision']    ?? '-',
                $inq ? ($inq['nombre'] ?? '-') : '-',
                $inm ? $inmNombre($inm) : '-',
                $r['concepto_periodo'] ?? '-',
                (float)($r['renta_base']    ?? 0),
                (float)($r['importe_iva']   ?? 0),
                (float)($r['importe_irpf']  ?? 0),
                $tot, $pag, $tot - $pag,
                $r['estado'] ?? '-',
            ];
        }
        $data[] = ['TOTAL', '', '', '', '', '', '', '', $sFact, $sCob, $sFact - $sCob, ''];
        break;

    // ─────────────────────────────────────────────────────────────
    // INFORME FISCAL 1: Rendimientos trimestrales (IRPF tracker)
    // ─────────────────────────────────────────────────────────────
    case 'fiscal-trimestral':
        $nombre = "Rendimientos_Trimestrales_{$anyo}";

        $trimEtqs = [
            "1T {$anyo} (Ene-Mar)",
            "2T {$anyo} (Abr-Jun)",
            "3T {$anyo} (Jul-Sep)",
            "4T {$anyo} (Oct-Dic)",
        ];
        $trimMesesLst = [[1,2,3],[4,5,6],[7,8,9],[10,11,12]];

        // Cabecera informativa
        $data[] = ["INFORME TRIMESTRAL DE RENDIMIENTOS DEL CAPITAL INMOBILIARIO - {$anyo}"];
        $data[] = ["Art. 22-24 LIRPF | Reducción 60 % vivienda habitual en arrendamiento (art. 23.2)"];
        $data[] = ["NOTA: Los importes corresponden a las cuotas emitidas del ejercicio. Revise con su asesor fiscal."];
        $data[] = [];
        $data[] = array_merge(
            ['Propietario', 'NIF/DNI Propietario', 'Finca', 'Inmueble', 'Tipo arrendamiento', 'Inquilino'],
            $trimEtqs,
            ["Total anual {$anyo} (€)", 'Reducción 60 % vivienda (€)', 'Rend. neto computable (€)']
        );

        $totT = [0.0,0.0,0.0,0.0]; $gTot = 0.0; $gRed = 0.0; $gNeto = 0.0;

        foreach ($propietarios as $prop) {
            $nifProp = trim(($prop['nif'] ?? '') ?: ($prop['dni'] ?? ''));
            $fp = array_filter($fincas, fn($f) => ($f['propietario_id'] ?? 0) === $prop['id']);
            foreach ($fp as $f) {
                $finms = array_filter($inmuebles, fn($i) => ($i['finca_id'] ?? 0) === $f['id']);
                foreach ($finms as $inm) {
                    $con = current(array_filter($contratos, fn($c) =>
                        ($c['inmueble_id'] ?? 0) === $inm['id'] && ($c['estado'] ?? '') === 'activo'));
                    if (!$con) {
                        $con = current(array_filter($contratos, fn($c) =>
                            ($c['inmueble_id'] ?? 0) === $inm['id'] &&
                            substr($c['fecha_inicio'] ?? '9999', 0, 4) <= (string)$anyo &&
                            (empty($c['fecha_fin']) || substr($c['fecha_fin'], 0, 4) >= (string)$anyo)
                        ));
                    }
                    $inq = $con ? current(array_filter($inquilinos, fn($i) => $i['id'] === ($con['inquilino_id'] ?? 0))) : null;

                    $vals = []; $total = 0.0;
                    foreach ($trimMesesLst as $ti => $ml) {
                        $v = 0.0;
                        foreach ($ml as $m) { $v += $porMes($recibos, [$inm['id']], $m); }
                        $vals[] = $v;
                        $totT[$ti] += $v;
                        $total += $v;
                    }

                    // Determinar si aplica la reducción del 60 % del art. 23.2 LIRPF:
                    // · El inmueble debe ser VIVIENDA (no local, garaje, oficina, etc.)
                    // · El contrato debe tener duración >= 1 año (o ser indefinido)
                    $tipoRaw = strtolower($inm['tipo'] ?? '');
                    $esViv   = !in_array($tipoRaw, ['local','garaje','parking','trastero','oficina','comercial','nave']);
                    $min1A   = $conMin1Anyo($con);
                    $aplicaRed = $esViv && $min1A;

                    if ($aplicaRed) {
                        $tipoLabel = 'Vivienda — contrato ≥ 1 año (red. 60 %)';
                    } elseif ($esViv && !$min1A) {
                        $tipoLabel = 'Vivienda — contrato < 1 año (sin reducción)';
                    } else {
                        $tipoLabel = ucfirst($inm['tipo'] ?? 'Otro') . ' (sin reducción)';
                    }

                    $reduccion = $aplicaRed ? round($total * 0.60, 2) : 0.0;
                    $neto = $total - $reduccion;
                    $gTot += $total; $gRed += $reduccion; $gNeto += $neto;

                    $data[] = array_merge([
                        $prop['nombre'] ?? '-',
                        $nifProp ?: '-',
                        $f['nombre'] ?? ($f['calle'] ?? '-'),
                        $inmNombre($inm),
                        $tipoLabel,
                        $inq ? ($inq['nombre'] ?? '-') : '-',
                    ], $vals, [$total, $reduccion, $neto]);
                }
            }
        }

        $data[] = array_merge(['TOTALES', '', '', '', '', ''], $totT, [$gTot, $gRed, $gNeto]);
        $data[] = [];
        $data[] = ["(*) La reducción del 60 % (art. 23.2 LIRPF) requiere: inmueble destinado a vivienda Y contrato de duración >= 1 año."];
        $data[] = ["(*) Para locales, garajes, trasteros y otros no residenciales, o contratos < 1 año, NO se aplica reducción."];
        break;

    // ─────────────────────────────────────────────────────────────
    // INFORME FISCAL 2: Declaración de la Renta anual (IRPF)
    // ─────────────────────────────────────────────────────────────
    case 'irpf-anual':
        $nombre = "IRPF_Renta_Capital_Inmobiliario_{$anyo}";

        $data[] = ["RENDIMIENTOS DEL CAPITAL INMOBILIARIO - DECLARACIÓN DE LA RENTA {$anyo} (Modelo 100)"];
        $data[] = ["Arts. 22-24 LIRPF | Sección F del borrador de Renta"];
        $data[] = ["IMPORTANTE: Los gastos deducibles (columna K) deben ser cumplimentados por usted o su asesor fiscal."];
        $data[] = [];
        $data[] = [
            'A - Propietario (declarante)', 'B - NIF/DNI Propietario',
            'C - Finca / Edificio', 'D - Dirección del inmueble', 'E - Inmueble (planta/puerta)',
            'F - Tipo arrendamiento', 'G - Inquilino', 'H - NIF/DNI Inquilino',
            'I - Fecha inicio contrato', 'J - Fecha fin / vencimiento',
            "K - Ingresos íntegros {$anyo} (€)",
            'L - Gastos deducibles* (€) [rellenar]',
            'M - Rend. neto previo (K-L) (€)',
            'N - Reducción 60 % vivienda (€)',
            'O - Rend. neto computable (M-N) (€)',
            'P - Observaciones fiscales',
        ];

        $totIngr = 0.0; $totNeto = 0.0;

        foreach ($propietarios as $prop) {
            $nifProp = trim(($prop['nif'] ?? '') ?: ($prop['dni'] ?? ''));
            $fp = array_filter($fincas, fn($f) => ($f['propietario_id'] ?? 0) === $prop['id']);
            foreach ($fp as $f) {
                $dirFinca = trim(($f['calle'] ?? '') . ' ' . ($f['numero'] ?? ''));
                $finms = array_filter($inmuebles, fn($i) => ($i['finca_id'] ?? 0) === $f['id']);
                foreach ($finms as $inm) {
                    // Ingresos: suma de recibos emitidos en el ejercicio (no anulados)
                    $ingresos = 0.0;
                    for ($m = 1; $m <= 12; $m++) { $ingresos += $porMes($recibos, [$inm['id']], $m); }

                    $con = current(array_filter($contratos, fn($c) =>
                        ($c['inmueble_id'] ?? 0) === $inm['id'] && ($c['estado'] ?? '') === 'activo'));
                    if (!$con) {
                        $con = current(array_filter($contratos, fn($c) =>
                            ($c['inmueble_id'] ?? 0) === $inm['id'] &&
                            substr($c['fecha_inicio'] ?? '9999', 0, 4) <= (string)$anyo &&
                            (empty($c['fecha_fin']) || substr($c['fecha_fin'], 0, 4) >= (string)$anyo)
                        ));
                    }
                    $inq    = $con ? current(array_filter($inquilinos, fn($i) => $i['id'] === ($con['inquilino_id'] ?? 0))) : null;
                    $nifInq = $inq ? trim(($inq['nif'] ?? '') ?: ($inq['dni'] ?? '')) : '';

                    $tipoRaw   = strtolower($inm['tipo'] ?? '');
                    $esViv     = !in_array($tipoRaw, ['local','garaje','parking','trastero','oficina','comercial','nave']);
                    $min1A     = $conMin1Anyo($con);
                    $aplicaRed = $esViv && $min1A;

                    if ($aplicaRed) {
                        $tipoLabel = 'Arrendamiento de vivienda (contrato ≥ 1 año)';
                        $obs = 'Reducción 60 % aplicada (art. 23.2 LIRPF): vivienda y contrato >= 1 año. Gastos deducibles: IBI, seguros, comunidad, amortización 3 %, reparaciones, intereses préstamo.';
                    } elseif ($esViv && !$min1A) {
                        $tipoLabel = 'Arrendamiento de vivienda (contrato < 1 año — sin reducción)';
                        $obs = 'SIN reducción 60 %: el contrato tiene duración inferior a 1 año. Gastos deducibles: IBI, seguros, comunidad, amortización, reparaciones, intereses préstamo.';
                    } else {
                        $tipoLabel = 'Arrendamiento no residencial';
                        $obs = 'SIN reducción 60 %: inmueble no destinado a vivienda. Gastos deducibles: IBI, seguros, comunidad, amortización, reparaciones, intereses, publicidad.';
                    }

                    $reduccion = $aplicaRed ? round($ingresos * 0.60, 2) : 0.0;
                    $neto = $ingresos - $reduccion;
                    $totIngr += $ingresos; $totNeto += $neto;

                    $data[] = [
                        $prop['nombre'] ?? '-', $nifProp ?: '-',
                        $f['nombre'] ?? ($f['calle'] ?? '-'), $dirFinca ?: '-', $inmNombre($inm),
                        $tipoLabel,
                        $inq ? ($inq['nombre'] ?? '-') : '-', $nifInq ?: '-',
                        $con ? ($con['fecha_inicio'] ?? '-') : '-',
                        $con ? ($con['fecha_fin'] ?? 'Indefinido') : '-',
                        $ingresos,
                        0.0,        // columna L: gastos deducibles (rellenar manualmente)
                        $ingresos,  // columna M: rend. neto previo (= ingresos cuando gastos=0)
                        $reduccion, // columna N: reducción 60 %
                        $neto,      // columna O: rend. neto computable
                        $obs,
                    ];
                }
            }
        }

        $data[] = ['TOTALES', '', '', '', '', '', '', '', '', '',
                   $totIngr, 0.0, $totIngr, '', $totNeto, ''];
        $data[] = [];
        $data[] = ['RECORDATORIO DE GASTOS DEDUCIBLES (art. 23.1 LIRPF) - Cumplimente la columna L para cada inmueble:'];
        $data[] = ['  • Intereses y gastos de financiación del préstamo hipotecario (límite: ingresos íntegros)'];
        $data[] = ['  • Tributos: IBI, tasa de basuras, etc.'];
        $data[] = ['  • Gastos de comunidad de propietarios'];
        $data[] = ['  • Primas de seguros (incendio, responsabilidad civil, etc.)'];
        $data[] = ['  • Gastos de conservación y reparación (límite: ingresos íntegros)'];
        $data[] = ['  • Amortización: 3 % del mayor entre valor catastral de construcción y coste de adquisición de construcción'];
        $data[] = ['  • Honorarios de administración, gestoría, abogado (si se refieren al arrendamiento)'];
        $data[] = ['  • Saldos de dudoso cobro (si han transcurrido +6 meses desde el vencimiento)'];
        break;

    // ─────────────────────────────────────────────────────────────
    // INFORME FISCAL 3: Modelo 115 / 180 (retenciones trimestrales)
    // ─────────────────────────────────────────────────────────────
    case 'modelo-115':
        $nombre = "Modelo_115_180_{$anyo}";

        $plazos115 = [
            "1T {$anyo} — Presentar hasta el 20 abril {$anyo}",
            "2T {$anyo} — Presentar hasta el 20 julio {$anyo}",
            "3T {$anyo} — Presentar hasta el 20 octubre {$anyo}",
            "4T {$anyo} — Presentar hasta el 20 enero " . ($anyo + 1),
        ];
        $trimMesesLst = [[1,2,3],[4,5,6],[7,8,9],[10,11,12]];
        $resumenT = array_fill(0, 4, ['base' => 0.0, 'ret' => 0.0]);

        $data[] = ["MODELO 115 / 180 - RETENCIONES E INGRESOS A CUENTA SOBRE ARRENDAMIENTOS - {$anyo}"];
        $data[] = ["Retención aplicable: 19 % (art. 101.4 LIRPF) | Tipo reducido Ceuta/Melilla: 9,5 %"];
        $data[] = ["ÁMBITO: Solo aplica cuando el ARRENDATARIO es persona jurídica (empresa/sociedad) o empresario/profesional individual."];
        $data[] = ["Para arrendatarios particulares (personas físicas) que NO actúen como empresarios, NO procede retención ni Modelo 115."];
        $data[] = ["Modelo 180 = Resumen anual de retenciones (presentar hasta el 31 de enero del año siguiente)."];
        $data[] = [];

        // Detalle por trimestre
        $data[] = ['Trimestre / Período', 'Propietario (Perceptor)', 'NIF Propietario',
                   'Finca', 'Inmueble', 'Arrendatario (Retenedor)', 'NIF/CIF Arrendatario',
                   'Base imponible (€)', 'Tipo retención', 'Retención (€)', 'Importe neto (€)'];

        foreach ($trimMesesLst as $ti => $ml) {
            foreach ($propietarios as $prop) {
                $nifProp = trim(($prop['nif'] ?? '') ?: ($prop['dni'] ?? ''));
                $fp = array_filter($fincas, fn($f) => ($f['propietario_id'] ?? 0) === $prop['id']);
                foreach ($fp as $f) {
                    $finms = array_filter($inmuebles, fn($i) => ($i['finca_id'] ?? 0) === $f['id']);
                    foreach ($finms as $inm) {
                        // Solo recibos donde se aplicó retención IRPF real
                        $trimData = $porTrimIRPF($recibos, $inm['id'], $ml);
                        if ($trimData['base'] == 0.0 && $trimData['irpf'] == 0.0) continue;

                        $con = current(array_filter($contratos, fn($c) =>
                            ($c['inmueble_id'] ?? 0) === $inm['id'] && ($c['estado'] ?? '') === 'activo'));
                        $inq    = $con ? current(array_filter($inquilinos, fn($i) => $i['id'] === ($con['inquilino_id'] ?? 0))) : null;
                        $nifInq = $inq ? trim(($inq['nif'] ?? '') ?: ($inq['dni'] ?? '')) : '';

                        $base = $trimData['base'];
                        $ret  = $trimData['irpf'];
                        $pct  = $base > 0 ? round($ret / $base * 100, 2) : 0.0;
                        $resumenT[$ti]['base'] += $base;
                        $resumenT[$ti]['ret']  += $ret;

                        $data[] = [
                            $plazos115[$ti],
                            $prop['nombre'] ?? '-', $nifProp ?: '-',
                            $f['nombre'] ?? ($f['calle'] ?? '-'), $inmNombre($inm),
                            $inq ? ($inq['nombre'] ?? '-') : '-', $nifInq ?: '-',
                            $base, $pct . ' %', $ret, $base - $ret,
                        ];
                    }
                }
            }
        }

        // Resumen Modelo 115 por trimestres
        $data[] = [];
        $data[] = ['=== RESUMEN MODELO 115 — DATOS PARA CUMPLIMENTAR EL FORMULARIO ==='];
        $data[] = ['Período (Modelo 115)', 'Plazo de presentación',
                   'Casilla 01 — Base imponible (€)',
                   'Casilla 02 — Tipo de retención (%)',
                   'Casilla 03 — Cuota de retención (€)',
                   'Resultado a ingresar (€)'];
        $trimCortos = ["1T {$anyo} (Ene-Mar)", "2T {$anyo} (Abr-Jun)", "3T {$anyo} (Jul-Sep)", "4T {$anyo} (Oct-Dic)"];
        $plazosCortos = [
            "20/04/{$anyo}", "20/07/{$anyo}", "20/10/{$anyo}", "20/01/" . ($anyo + 1)
        ];
        $totBase = 0.0; $totRet = 0.0;
        foreach ($trimCortos as $ti => $tnom) {
            $base = $resumenT[$ti]['base']; $ret = $resumenT[$ti]['ret'];
            $totBase += $base; $totRet += $ret;
            $data[] = [$tnom, $plazosCortos[$ti], $base, 19.0, $ret, $ret];
        }
        $data[] = ["TOTAL {$anyo}", '', $totBase, '', $totRet, $totRet];

        // Desglose mensual por trimestre (M-L03): muestra subtotales mes a mes
        $data[] = [];
        $data[] = ['=== DESGLOSE MENSUAL DE RETENCIONES ==='];
        $data[] = ['Mes', 'Base imponible (€)', 'Retención (€)', 'Neto (€)'];
        $mesesNombres = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                         'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        $totBaseM = 0.0; $totRetM = 0.0;
        for ($m = 1; $m <= 12; $m++) {
            $baseM = 0.0; $retM = 0.0;
            foreach ($inmuebles as $inm) {
                $md = $porTrimIRPF($recibos, $inm['id'], [$m]);
                $baseM += $md['base'];
                $retM  += $md['irpf'];
            }
            $trimLabel = $m <= 3 ? '1T' : ($m <= 6 ? '2T' : ($m <= 9 ? '3T' : '4T'));
            $data[] = [$mesesNombres[$m - 1] . " ({$trimLabel})", $baseM, $retM, $baseM - $retM];
            $totBaseM += $baseM;
            $totRetM  += $retM;
        }
        $data[] = ["TOTAL {$anyo}", $totBaseM, $totRetM, $totBaseM - $totRetM];

        // Desglose por perceptor para el Modelo 180 (datos de cumplimentación)
        $data[] = [];
        $data[] = ["=== MODELO 180 — DATOS POR PERCEPTOR (presentar hasta 31/01/" . ($anyo + 1) . ") ==="];
        $data[] = ['NIF Perceptor', 'Nombre / Razón Social', 'Provincia', 'Clave percepción',
                   'Importe anual satisfecho (€)', 'Retenciones anuales (€)', 'Ejercicio'];
        $numPerceptores = 0;
        foreach ($propietarios as $prop) {
            $nifProp = trim(($prop['nif'] ?? '') ?: ($prop['dni'] ?? ''));
            $fp = array_filter($fincas, fn($f) => ($f['propietario_id'] ?? 0) === $prop['id']);
            $anuProp = ['base' => 0.0, 'irpf' => 0.0];
            foreach ($fp as $f) {
                $finms = array_filter($inmuebles, fn($i) => ($i['finca_id'] ?? 0) === $f['id']);
                foreach ($finms as $inm) {
                    $anual = $porTrimIRPF($recibos, $inm['id'], [1,2,3,4,5,6,7,8,9,10,11,12]);
                    $anuProp['base'] += $anual['base'];
                    $anuProp['irpf'] += $anual['irpf'];
                }
            }
            if ($anuProp['irpf'] <= 0.0) continue; // solo perceptores con retención efectiva
            $numPerceptores++;
            $data[] = [
                $nifProp ?: '-',
                $prop['nombre'] ?? '-',
                $prop['provincia'] ?? '-',
                '01 — Arrendamientos urbanos',
                round($anuProp['base'], 2),
                round($anuProp['irpf'], 2),
                (string)$anyo,
            ];
        }
        $data[] = [];
        $data[] = ["Total perceptores con retención: {$numPerceptores}"];
        $data[] = ["Total base imponible anual: {$totBase} €  |  Total retenciones: {$totRet} €"];
        $data[] = [];
        $data[] = ['RECORDATORIO: El Modelo 180 se presenta hasta el 31 de enero del año siguiente.'];
        $data[] = ['Clave 01 = arrendamientos urbanos de inmuebles. Subclave: 01 (arrendatario empresa/autónomo).'];
        break;

    // ── Informe IRPF anual por propietario [14] ───────────────
    // Lista todos los recibos cobrados o parcialmente cobrados del año
    // para los inmuebles del propietario indicado (propietario_id en GET).
    // Ayuda a preparar los rendimientos del capital inmobiliario del propietario.
    case 'irpf-propietario':
        $propietarioId = (int)($_GET['propietario_id'] ?? 0);
        if ($propietarioId <= 0) {
            ob_end_clean();
            http_response_code(400); die('propietario_id no válido');
        }
        $propObj = current(array_filter($propietarios, fn($p) => $p['id'] === $propietarioId));
        if (!$propObj) {
            ob_end_clean();
            http_response_code(404); die('Propietario no encontrado');
        }
        $nombre = "IRPF_{$anyo}_" . preg_replace('/[^A-Za-z0-9_]/', '_', ($propObj['nombre'] ?? 'propietario'));

        // Obtener IDs de fincas e inmuebles del propietario
        $fincasProp = array_filter($fincas, fn($f) => ($f['propietario_id'] ?? 0) === $propietarioId);
        $fincaIds   = array_column($fincasProp, 'id');
        $inmsProp   = array_filter($inmuebles, fn($i) => in_array($i['finca_id'] ?? 0, $fincaIds));
        $inmIds     = array_column($inmsProp, 'id');

        // Recibos cobrados (total o parcialmente) de ese propietario en el año indicado
        $recsProp = array_filter($recibos, function($r) use ($inmIds, $anyo) {
            if (!in_array($r['inmueble_id'] ?? 0, $inmIds)) return false;
            if (!in_array($r['estado'] ?? '', ['cobrado', 'parcial'])) return false;
            return strpos($r['fecha_emision'] ?? '', (string)$anyo) === 0;
        });

        // Cabecera
        $data[] = ["Informe IRPF {$anyo} — {$propObj['nombre']}"];
        $data[] = [];
        $data[] = ['Fecha emisión', 'Período', 'Inmueble', 'Inquilino', 'Renta base (€)', 'IVA (€)', 'IRPF ret. (€)', 'Total (€)', 'Pagado (€)', 'Estado'];

        $totBase = 0.0; $totIva = 0.0; $totIrpf = 0.0; $totTotal = 0.0; $totPagado = 0.0;
        foreach ($recsProp as $r) {
            $inm = current(array_filter($inmuebles, fn($i) => $i['id'] === ($r['inmueble_id'] ?? 0)));
            $inq = current(array_filter($inquilinos, fn($i) => $i['id'] === ($r['inquilino_id'] ?? 0)));
            $base   = (float)($r['renta_base']    ?? 0);
            $iva    = (float)($r['importe_iva']   ?? 0);
            $irpf   = (float)($r['importe_irpf']  ?? 0);
            $total  = (float)($r['importe_total'] ?? 0);
            $pagado = (float)($r['importe_pagado'] ?? 0);
            $totBase   += $base;   $totIva  += $iva;  $totIrpf += $irpf;
            $totTotal  += $total;  $totPagado += $pagado;
            $data[] = [
                $r['fecha_emision']   ?? '-',
                $r['concepto_periodo'] ?? '-',
                $inm ? $inmNombre($inm) : '-',
                $inq ? ($inq['nombre'] ?? '-') : '-',
                round($base,  2),
                round($iva,   2),
                round($irpf,  2),
                round($total, 2),
                round($pagado,2),
                $r['estado'] ?? '-',
            ];
        }
        $data[] = [];
        $data[] = ['TOTALES', '', '', '', round($totBase,2), round($totIva,2), round($totIrpf,2), round($totTotal,2), round($totPagado,2), ''];
        $data[] = [];
        $data[] = ["NIF propietario: " . ($propObj['nif'] ?? '-') . "  |  Año: {$anyo}  |  Inmuebles: " . count($inmsProp)];
        $data[] = ['NOTA: Los gastos deducibles no están registrados y deben añadirse manualmente.'];
        break;

    // ── Informe IVA trimestral — Modelo 303 [15] ─────────────
    // Desglose por trimestre del IVA repercutido en recibos de arrendamiento.
    // Solo incluye recibos con importe_iva > 0 (arrendamientos sujetos a IVA:
    // locales, oficinas, naves). Para uso como ayuda del Modelo 303.
    case 'iva-trimestral':
        $nombre = "IVA_Trimestral_{$anyo}";

        // Recibos del año con IVA repercutido (importe_iva > 0) y no anulados
        $recsIva = array_filter($recibos, function($r) use ($anyo) {
            if (($r['estado'] ?? '') === 'anulado') return false;
            if ((float)($r['importe_iva'] ?? 0) <= 0) return false;
            return strpos($r['fecha_emision'] ?? '', (string)$anyo) === 0;
        });

        // Función interna: devuelve los recibos del trimestre dado (1-4)
        $porTrim = function(array $recs, int $trim) use ($meses): array {
            $start = ($trim - 1) * 3 + 1; // mes inicio: 1,4,7,10
            $end   = $start + 2;           // mes fin:    3,6,9,12
            return array_filter($recs, function($r) use ($start, $end, $meses) {
                for ($m = $start; $m <= $end; $m++) {
                    if (stripos($r['concepto_periodo'] ?? '', $meses[$m - 1]) !== false) return true;
                }
                return false;
            });
        };

        // Detalle por línea
        $data[] = ["Informe IVA trimestral {$anyo} — Modelo 303"];
        $data[] = ['Solo se incluyen recibos con IVA repercutido (importe_iva > 0)'];
        $data[] = [];
        $data[] = ['Fecha emisión', 'Período', 'Trimestre', 'Inmueble', 'Inquilino', 'Base imponible (€)', 'IVA % aplicado', 'Cuota IVA (€)', 'Estado'];

        $totBaseG = 0.0; $totIvaG = 0.0;
        $trimNom = ['1T (Ene-Mar)', '2T (Abr-Jun)', '3T (Jul-Sep)', '4T (Oct-Dic)'];
        for ($t = 1; $t <= 4; $t++) {
            $recsT = $porTrim($recsIva, $t);
            if (!count($recsT)) continue;
            $totBaseT = 0.0; $totIvaT = 0.0;
            $data[] = [$trimNom[$t - 1], '', '', '', '', '', '', '', ''];
            foreach ($recsT as $r) {
                $inm  = current(array_filter($inmuebles, fn($i) => $i['id'] === ($r['inmueble_id'] ?? 0)));
                $inq  = current(array_filter($inquilinos, fn($i) => $i['id'] === ($r['inquilino_id'] ?? 0)));
                $base = (float)($r['renta_base']  ?? 0);
                $iva  = (float)($r['importe_iva'] ?? 0);
                $pct  = $base > 0 ? round($iva / $base * 100, 2) : 0;
                $totBaseT += $base; $totIvaT += $iva;
                $data[] = [
                    $r['fecha_emision']    ?? '-',
                    $r['concepto_periodo'] ?? '-',
                    $trimNom[$t - 1],
                    $inm ? $inmNombre($inm) : '-',
                    $inq ? ($inq['nombre'] ?? '-') : '-',
                    round($base, 2),
                    $pct . '%',
                    round($iva, 2),
                    $r['estado'] ?? '-',
                ];
            }
            $data[] = ['', 'Subtotal ' . $trimNom[$t - 1], '', '', '', round($totBaseT,2), '', round($totIvaT,2), ''];
            $totBaseG += $totBaseT; $totIvaG += $totIvaT;
            $data[] = [];
        }
        $data[] = ['TOTAL ANUAL', '', '', '', '', round($totBaseG,2), '', round($totIvaG,2), ''];
        $data[] = [];
        $data[] = ['NOTA: Este informe es una ayuda. Revise los datos con su asesor fiscal antes de presentar el Modelo 303.'];
        break;

    default:
        ob_end_clean();
        http_response_code(400); die('Tipo de informe no válido');
}

// ── Enviar el XLSX al navegador ───────────────────────────────
// Primero se verifica que ZipArchive esté disponible, se genera el XLSX,
// se descarta cualquier salida acumulada (warnings de PHP), y se envían
// las cabeceras HTTP correctas para que el navegador descargue el fichero.
if (!class_exists('ZipArchive')) {
    ob_end_clean();
    http_response_code(500);
    die('Error: la extensión ZipArchive de PHP no está habilitada. Abre php.ini de XAMPP y asegúrate de que la línea "extension=zip" no tenga punto y coma delante.');
}
try {
    $xlsx = buildXLSX($data, 'Informe');
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    die('Error al generar Excel: ' . $e->getMessage());
}
ob_end_clean(); // descarta cualquier warning/notice de PHP antes del binario
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nombre . '.xlsx"');
header('Content-Length: ' . strlen($xlsx));
header('Cache-Control: no-cache');
echo $xlsx;
