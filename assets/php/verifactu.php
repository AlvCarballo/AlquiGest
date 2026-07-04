<?php
// ============================================================
//  AlquiGest – verifactu.php
//
//  Integración VERI*FACTU / SIF con la AEAT (Agencia Tributaria española).
//  Regulación: Real Decreto 1007/2023 y Orden HAC/...
//
//  IMPORTANTE: Ninguna acción envía datos a la AEAT si la variable
//  verifactu_activo en la tabla 'configuracion' no está a '1'.
//  Esta comprobación se realiza en cada acción que implica envío.
//
//  Acciones disponibles (parámetro GET ?action=):
//    enviar           → POST {factura_id}  Calcular hash y enviar a AEAT
//    reenviar         → POST {factura_id}  Reintentar envío fallido
//    test_conexion    → GET               Comprobar conectividad con AEAT
//    upload_cert      → POST multipart    Subir certificado .p12/.pfx
//    estado           → GET               Estado de configuración
//    xml_preview      → POST {factura_id} Devolver el XML que se enviaría (sin enviar)
// ============================================================

// ── Funciones compartidas (seguridad, CORS, helpers de BD) ───
require __DIR__ . '/helpers.php';
requireLocalhost();
setCorsHeaders();

// ── Configuración de base de datos ───────────────────────────
$cfg = require __DIR__ . '/config.php';

// ── Alias local de json_respond para compatibilidad interna ──
function jsonOut(array $data, int $code = 200): void { json_respond($data, $code); }

// ── Helper: leer variable de configuracion ───────────────────
function getCfg(PDO $pdo, string $variable, string $default = ''): string {
    $s = $pdo->prepare("SELECT valor FROM configuracion WHERE variable = ? LIMIT 1");
    $s->execute([$variable]);
    $r = $s->fetch(PDO::FETCH_ASSOC);
    return ($r && $r['valor'] !== null) ? $r['valor'] : $default;
}

// ── Helper: actualizar variable de configuracion ─────────────
function setCfg(PDO $pdo, string $variable, string $valor): void {
    $pdo->prepare(
        "UPDATE configuracion SET valor = ? WHERE variable = ?"
    )->execute([$valor, $variable]);
}

// ── Conexión PDO ──────────────────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset={$cfg['charset']}",
        $cfg['user'], $cfg['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Exception $e) {
    jsonOut(['error' => 'Error de conexión con la base de datos'], 500);
}

// ── Leer toda la configuración VERI*FACTU ────────────────────
$vfActivo   = getCfg($pdo, 'verifactu_activo');
$vfEntorno  = getCfg($pdo, 'verifactu_entorno', 'pruebas');
$vfCertPath = getCfg($pdo, 'verifactu_cert_path');
$vfCertPass = getCfg($pdo, 'verifactu_cert_pass');
// Descifrar contraseña del certificado si está cifrada con AES-256-CBC
$encKey = $cfg['encrypt_key'] ?? '';
if ($encKey && isEncrypted($vfCertPass)) {
    $vfCertPass = decryptValue($vfCertPass, $encKey);
}
$vfNif      = getCfg($pdo, 'verifactu_nif_sif');
$vfSisNom   = getCfg($pdo, 'verifactu_sistema_nombre', 'AlquiGest');
$vfSisVer   = getCfg($pdo, 'verifactu_sistema_version', '2.0.0');
$vfNumInst  = getCfg($pdo, 'verifactu_num_instalacion', '1');

// URLs de los endpoints AEAT según entorno
$urlsAEAT = [
    'pruebas'    => 'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSistemaFacturacion',
    'produccion' => 'https://www1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSistemaFacturacion',
];
$urlAEAT = $urlsAEAT[$vfEntorno] ?? $urlsAEAT['pruebas'];

// URL base de validación QR (para construir el enlace de verificación ciudadana)
$urlsQR = [
    'pruebas'    => 'https://prewww2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR',
    'produccion' => 'https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR',
];
$urlQRBase = $urlsQR[$vfEntorno] ?? $urlsQR['pruebas'];

// ── Obtener datos de la factura anterior de la serie ─────────
function getFacturaAnterior(PDO $pdo, string $serie, int $idActual): ?array {
    $s = $pdo->prepare(
        "SELECT numero_factura, fecha_emision, hash_factura
         FROM facturas
         WHERE serie = ? AND estado != 'anulada' AND id < ?
         ORDER BY id DESC LIMIT 1"
    );
    $s->execute([$serie, $idActual]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}

// ── Calcular el hash SHA-256 encadenado de una factura ───────
// Según Anexo I del Real Decreto 1007/2023.
// El hash incluye los campos clave de la factura y el hash de la anterior.
// Devuelve el hash en hexadecimal MAYÚSCULAS.
function calcularHashFactura(array $f, string $hashAnterior): string {
    $fechaEmision  = $f['fecha_emision'] ?? date('Y-m-d');
    // La AEAT exige formato DD-MM-YYYY en la cadena de hash
    $fechaDDMMYYYY = implode('-', array_reverse(explode('-', $fechaEmision)));
    // Timestamp de la huella: momento de cálculo en formato DD-MM-YYYYTHH:MM:SS
    $tsHuella = date('d-m-Y\TH:i:s');

    $cadena = implode('|', [
        'IDEmisorFactura='        . ($f['emisor_nif']     ?? ''),
        'NumSerieFactura='        . ($f['numero_factura'] ?? ''),
        'FechaExpedicionFactura=' . $fechaDDMMYYYY,
        'TipoFactura='            . ($f['tipo_factura']   ?? 'F1'),
        'CuotaTotal='             . number_format((float)($f['importe_iva']   ?? 0), 2, '.', ''),
        'ImporteTotal='           . number_format((float)($f['importe_total'] ?? 0), 2, '.', ''),
        'Huella='                 . $hashAnterior,
        'FechaHoraHuella='        . $tsHuella,
    ]);
    return strtoupper(hash('sha256', $cadena));
}

// ── Construir la URL del código QR de verificación ───────────
// URL que el destinatario puede usar para verificar la factura en la AEAT.
function construirQrUrl(array $f, string $urlBase): string {
    $nif    = urlencode($f['emisor_nif']     ?? '');
    $num    = urlencode($f['numero_factura'] ?? '');
    $fecha  = urlencode(implode('-', array_reverse(explode('-', $f['fecha_emision'] ?? ''))));
    $total  = urlencode(number_format((float)($f['importe_total'] ?? 0), 2, '.', ''));
    return "{$urlBase}?nif={$nif}&numserie={$num}&fecha={$fecha}&importe={$total}";
}

// ── Construir el XML SOAP para el envío a AEAT ───────────────
// Implementa la estructura RegistroFacturacion según el esquema VERI*FACTU.
// Soporta facturas con y sin IVA, y el bloque de encadenamiento.
function buildVerifactuXML(array $f, string $hashAnterior, ?array $anterior,
                            string $nifSif, string $sisNom, string $sisVer,
                            string $numInst): string
{
    $nifEmisor  = htmlspecialchars($f['emisor_nif']     ?? '', ENT_XML1);
    $nomEmisor  = htmlspecialchars($f['emisor_nombre']  ?? '', ENT_XML1);
    $numSerie   = htmlspecialchars($f['numero_factura'] ?? '', ENT_XML1);
    $tipo       = htmlspecialchars($f['tipo_factura']   ?? 'F1', ENT_XML1);
    $fechaEmis  = $f['fecha_emision'] ?? date('Y-m-d');
    $fechaDDMM  = implode('-', array_reverse(explode('-', $fechaEmis)));
    $desc       = htmlspecialchars(($f['concepto'] ?? 'Arrendamiento de inmueble'), ENT_XML1);
    $cliNif     = htmlspecialchars($f['cliente_nif']    ?? '', ENT_XML1);
    $cliNom     = htmlspecialchars($f['cliente_nombre'] ?? '', ENT_XML1);
    $base       = number_format((float)($f['base_imponible'] ?? 0), 2, '.', '');
    $ivaPct     = number_format((float)($f['iva_pct']        ?? 0), 2, '.', '');
    $cuotaIva   = number_format((float)($f['importe_iva']    ?? 0), 2, '.', '');
    $total      = number_format((float)($f['importe_total']  ?? 0), 2, '.', '');
    $hash       = calcularHashFactura($f, $hashAnterior);
    $ts         = date('Y-m-d\TH:i:s');
    $tsDDMM     = date('d-m-Y\TH:i:s');
    $nifSifXml  = htmlspecialchars($nifSif ?: $nifEmisor, ENT_XML1);
    $sisNomXml  = htmlspecialchars($sisNom, ENT_XML1);
    $sisVerXml  = htmlspecialchars($sisVer, ENT_XML1);

    // Bloque de desglose fiscal: con IVA o exenta/no sujeta
    if ((float)($f['iva_pct'] ?? 0) > 0) {
        $desglose = "
        <Desglose>
          <DetalleIVA>
            <TipoImpositivo>{$ivaPct}</TipoImpositivo>
            <BaseImponibleOImporteNoSujeto>{$base}</BaseImponibleOImporteNoSujeto>
            <CuotaRepercutida>{$cuotaIva}</CuotaRepercutida>
          </DetalleIVA>
        </Desglose>";
    } else {
        // Arrendamiento de vivienda habitual: E2 = Exención art. 20.Uno.23 LIVA
        // Para otras situaciones (no sujeta, otros tipos de exención), ajustar CausaExencion.
        $desglose = "
        <Desglose>
          <DetalleIVA>
            <CausaExencion>E2</CausaExencion>
            <BaseImponibleExenta>{$base}</BaseImponibleExenta>
          </DetalleIVA>
        </Desglose>";
    }

    // Bloque de encadenamiento con la factura anterior
    if ($anterior && $anterior['hash_factura']) {
        $antNum   = htmlspecialchars($anterior['numero_factura'] ?? '', ENT_XML1);
        $antFecha = implode('-', array_reverse(explode('-', $anterior['fecha_emision'] ?? '')));
        $antHash  = htmlspecialchars($anterior['hash_factura'], ENT_XML1);
        $encadenamiento = "
          <PrimerRegistro>N</PrimerRegistro>
          <RegistroAnterior>
            <IDEmisorFactura>{$nifEmisor}</IDEmisorFactura>
            <NumSerieFactura>{$antNum}</NumSerieFactura>
            <FechaExpedicionFactura>{$antFecha}</FechaExpedicionFactura>
            <Huella>{$antHash}</Huella>
          </RegistroAnterior>";
    } else {
        // Primera factura de la serie
        $encadenamiento = "
          <PrimerRegistro>S</PrimerRegistro>";
    }

    return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
  xmlns:sum="https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SistemaFacturacion.wsdl">
  <soapenv:Header/>
  <soapenv:Body>
    <sum:RegFactuSistemaFacturacion>
      <Cabecera>
        <ObligadoEmision>
          <NombreRazon>{$nomEmisor}</NombreRazon>
          <NIF>{$nifSifXml}</NIF>
        </ObligadoEmision>
      </Cabecera>
      <RegistroFacturacion>
        <RegistroAlta>
          <IDVersion>1.0</IDVersion>
          <IDFactura>
            <IDEmisorFactura>{$nifEmisor}</IDEmisorFactura>
            <NumSerieFactura>{$numSerie}</NumSerieFactura>
            <FechaExpedicionFactura>{$fechaDDMM}</FechaExpedicionFactura>
          </IDFactura>
          <NombreRazonEmisor>{$nomEmisor}</NombreRazonEmisor>
          <Subsanacion>N</Subsanacion>
          <RechazoPrevio>N</RechazoPrevio>
          <TipoFactura>{$tipo}</TipoFactura>
          <DescripcionOperacion>{$desc}</DescripcionOperacion>
          <Destinatarios>
            <IDDestinatario>
              <NombreRazon>{$cliNom}</NombreRazon>
              <NIF>{$cliNif}</NIF>
            </IDDestinatario>
          </Destinatarios>
          {$desglose}
          <CuotaTotal>{$cuotaIva}</CuotaTotal>
          <ImporteTotal>{$total}</ImporteTotal>
          <Encadenamiento>{$encadenamiento}
          </Encadenamiento>
          <SistemaInformatico>
            <NombreRazon>{$nomEmisor}</NombreRazon>
            <NIF>{$nifSifXml}</NIF>
            <NombreSistemaInformatico>{$sisNomXml}</NombreSistemaInformatico>
            <IdSistemaInformatico>ALQUIGEST</IdSistemaInformatico>
            <Version>{$sisVerXml}</Version>
            <NumeroInstalacion>{$numInst}</NumeroInstalacion>
            <TipoUsoPosibleSoloVerifactu>S</TipoUsoPosibleSoloVerifactu>
            <TipoUsoPosibleMultiOT>N</TipoUsoPosibleMultiOT>
            <IndicadorMultiplesOT>N</IndicadorMultiplesOT>
          </SistemaInformatico>
          <FechaHoraHuella>{$tsDDMM}</FechaHoraHuella>
          <HuellaSQL>{$hash}</HuellaSQL>
        </RegistroAlta>
      </RegistroFacturacion>
    </sum:RegFactuSistemaFacturacion>
  </soapenv:Body>
</soapenv:Envelope>
XML;
}

// ── Enviar XML a la AEAT mediante HTTPS + certificado ────────
// Usa curl con el certificado PKCS12 para autenticación de cliente TLS.
// Devuelve ['ok'=>true, 'respuesta'=>'...'] o ['error'=>'...'].
function enviarSOAP(string $url, string $xml, string $certPath, string $certPass): array {
    if (!function_exists('curl_init')) {
        return ['error' => 'PHP cURL no está disponible. Instala la extensión php_curl.'];
    }
    if (!file_exists($certPath)) {
        return ['error' => "Certificado no encontrado en la ruta: {$certPath}"];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $xml,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: ""',
            'Content-Length: ' . strlen($xml),
        ],
        CURLOPT_SSLCERT        => $certPath,
        CURLOPT_SSLCERTTYPE    => 'P12',
        CURLOPT_SSLCERTPASSWD  => $certPass,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $respuesta  = curl_exec($ch);
    $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError  = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['error' => "Error de conexión cURL: {$curlError}"];
    }
    if ($httpCode >= 400) {
        return ['error' => "AEAT devolvió HTTP {$httpCode}", 'respuesta_raw' => $respuesta];
    }

    // Intentar parsear la respuesta SOAP para detectar errores de la AEAT
    $resultado = parsearRespuestaAEAT($respuesta);
    $resultado['respuesta_raw'] = $respuesta;
    return $resultado;
}

// ── Parsear la respuesta XML de la AEAT ──────────────────────
// Busca EstadoEnvio y EstadoRegistro para saber si fue aceptado.
function parsearRespuestaAEAT(string $xml): array {
    if (empty($xml)) return ['error' => 'Respuesta vacía de la AEAT'];

    // Suprimir warnings de XML malformado
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    $errors = libxml_get_errors();
    libxml_clear_errors();

    if ($errors && empty($dom->documentElement)) {
        return ['error' => 'Respuesta AEAT no es XML válido'];
    }

    // Buscar el estado del envío
    $estadoEnvio = '';
    $nodes = $dom->getElementsByTagName('EstadoEnvio');
    if ($nodes->length) $estadoEnvio = trim($nodes->item(0)->nodeValue);

    $estadoReg = '';
    $nodesReg  = $dom->getElementsByTagName('EstadoRegistro');
    if ($nodesReg->length) $estadoReg = trim($nodesReg->item(0)->nodeValue);

    $codError = '';
    $descError = '';
    $nodesErr  = $dom->getElementsByTagName('CodigoErrorRegistro');
    if ($nodesErr->length) $codError = trim($nodesErr->item(0)->nodeValue);
    $nodesDesc = $dom->getElementsByTagName('DescripcionErrorRegistroES');
    if ($nodesDesc->length) $descError = trim($nodesDesc->item(0)->nodeValue);

    if (strtolower($estadoEnvio) === 'correcto' || strtolower($estadoReg) === 'correcto') {
        return ['ok' => true, 'estado_envio' => $estadoEnvio, 'estado_registro' => $estadoReg];
    }

    $errorMsg = $descError ?: "Estado envío: {$estadoEnvio}, Estado registro: {$estadoReg}";
    if ($codError) $errorMsg = "[{$codError}] {$errorMsg}";
    return ['error' => $errorMsg ?: 'Error desconocido en la respuesta AEAT'];
}

// ── Actualizar campos VERI*FACTU de una factura en BD ────────
function actualizarFacturaVF(PDO $pdo, int $id, array $campos): void {
    $sets = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($campos)));
    $vals = array_values($campos);
    $vals[] = $id;
    $pdo->prepare("UPDATE facturas SET {$sets} WHERE id = ?")->execute($vals);
}

// ── Lógica central de envío ───────────────────────────────────
// Usada por 'enviar' y 'reenviar'. Calcula hash, construye XML, envía.
function procesarEnvio(PDO $pdo, int $facturaId, string $urlAEAT, string $urlQRBase,
                        string $vfCertPath, string $vfCertPass, string $vfNif,
                        string $vfSisNom, string $vfSisVer, string $vfNumInst): array
{
    $f = getRow($pdo, 'facturas', $facturaId);
    if (!$f) return ['error' => 'Factura no encontrada'];
    if ($f['estado'] === 'anulada') return ['error' => 'No se puede enviar una factura anulada'];

    // Obtener la factura anterior para el encadenamiento
    $anterior = getFacturaAnterior($pdo, $f['serie'] ?? 'FAC', $facturaId);
    $hashAnterior = ($anterior && $anterior['hash_factura']) ? $anterior['hash_factura'] : '0';

    // Calcular el hash (puede que ya esté calculado por api.php; se recalcula por seguridad)
    $hash = calcularHashFactura($f, $hashAnterior);

    // Construir el XML
    $xml = buildVerifactuXML($f, $hashAnterior, $anterior,
                             $vfNif, $vfSisNom, $vfSisVer, $vfNumInst);

    // Enviar a la AEAT
    $certPath = $vfCertPath ? (__DIR__ . '/' . ltrim($vfCertPath, '/\\')) : '';
    $resultado = enviarSOAP($urlAEAT, $xml, $certPath, $vfCertPass);

    // Construir la URL del QR de verificación
    $qrUrl = construirQrUrl($f, $urlQRBase);

    if (isset($resultado['ok'])) {
        // Éxito: guardar hash, QR y estado
        actualizarFacturaVF($pdo, $facturaId, [
            'hash_factura'        => $hash,
            'hash_anterior'       => $hashAnterior,
            'verifactu_estado'    => 'enviado',
            'verifactu_respuesta' => substr($resultado['respuesta_raw'] ?? '', 0, 65535),
            'qr_url'              => $qrUrl,
        ]);
        return ['ok' => true, 'hash' => $hash, 'qr_url' => $qrUrl,
                'estado_aeat' => $resultado['estado_envio'] ?? ''];
    } else {
        // Error: guardar el intento y el error
        actualizarFacturaVF($pdo, $facturaId, [
            'hash_factura'        => $hash,
            'hash_anterior'       => $hashAnterior,
            'verifactu_estado'    => 'error',
            'verifactu_respuesta' => substr(($resultado['error'] ?? '') . "\n\n" . ($resultado['respuesta_raw'] ?? ''), 0, 65535),
        ]);
        return ['error' => $resultado['error'] ?? 'Error desconocido'];
    }
}

// ── Router de acciones ────────────────────────────────────────
$action = $_GET['action'] ?? '';
$input  = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
}

try {

    // ──────────────────────────────────────────────────────────
    // estado: devuelve el estado de configuración de VERI*FACTU
    // No requiere verifactu_activo = 1
    // ──────────────────────────────────────────────────────────
    if ($action === 'estado') {
        $certAbsPath = $vfCertPath ? (__DIR__ . '/' . ltrim($vfCertPath, '/\\')) : '';
        $certExiste  = $certAbsPath && file_exists($certAbsPath);
        $certInfo    = [];
        if ($certExiste) {
            $p12data = file_get_contents($certAbsPath);
            $certsBag = [];
            if (openssl_pkcs12_read($p12data, $certsBag, $vfCertPass)) {
                $certData = openssl_x509_parse($certsBag['cert'] ?? '');
                if ($certData) {
                    $certInfo = [
                        'sujeto'     => $certData['subject']['CN'] ?? '',
                        'valido_hasta' => isset($certData['validTo_time_t'])
                            ? date('d/m/Y', $certData['validTo_time_t']) : '',
                        'caducado'   => isset($certData['validTo_time_t'])
                            ? ($certData['validTo_time_t'] < time()) : false,
                    ];
                }
            } else {
                $certInfo = ['error' => 'No se pudo leer el certificado (contraseña incorrecta o archivo dañado)'];
            }
        }

        // Contar facturas por estado
        $stats = $pdo->query(
            "SELECT verifactu_estado, COUNT(*) as total FROM facturas GROUP BY verifactu_estado"
        )->fetchAll();
        $statsMap = array_column($stats, 'total', 'verifactu_estado');

        jsonOut([
            'ok'          => true,
            'activo'      => $vfActivo === '1',
            'entorno'     => $vfEntorno,
            'cert_path'   => $vfCertPath,
            'cert_existe' => $certExiste,
            'cert_info'   => $certInfo,
            'nif_sif'     => $vfNif,
            'sistema'     => ['nombre' => $vfSisNom, 'version' => $vfSisVer, 'instalacion' => $vfNumInst],
            'url_aeat'    => $urlAEAT,
            'stats_facturas' => $statsMap,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // test_conexion: prueba conectividad con el endpoint AEAT
    // No requiere verifactu_activo = 1
    // ──────────────────────────────────────────────────────────
    if ($action === 'test_conexion') {
        if (!function_exists('curl_init')) {
            jsonOut(['ok' => false, 'error' => 'PHP cURL no está disponible. Instala php_curl.']);
        }
        $urlWSDL = $urlAEAT . '?wsdl';
        $ch = curl_init($urlWSDL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY         => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        curl_close($ch);

        if ($curlError) {
            jsonOut(['ok' => false, 'error' => "No se pudo conectar: {$curlError}", 'url' => $urlWSDL]);
        }
        jsonOut([
            'ok'        => $httpCode < 500,
            'http_code' => $httpCode,
            'tiempo_ms' => round($totalTime * 1000),
            'url'       => $urlWSDL,
            'entorno'   => $vfEntorno,
        ]);
    }

    // ──────────────────────────────────────────────────────────
    // upload_cert: sube el certificado .p12/.pfx al servidor
    // No requiere verifactu_activo = 1
    // ──────────────────────────────────────────────────────────
    if ($action === 'upload_cert') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['cert'])) {
            jsonOut(['error' => 'Se requiere un archivo .p12 o .pfx vía POST multipart'], 400);
        }
        $file = $_FILES['cert'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            jsonOut(['error' => 'Error al recibir el archivo (código ' . $file['error'] . ')'], 400);
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['p12', 'pfx'], true)) {
            jsonOut(['error' => 'Solo se admiten archivos .p12 o .pfx'], 400);
        }

        // Crear directorio protegido si no existe
        $targetDir = __DIR__ . '/certs/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0700, true);
            // Proteger el directorio contra acceso HTTP directo
            file_put_contents($targetDir . '.htaccess',
                "Options -Indexes\nRequire all denied\n");
        }

        $targetPath     = $targetDir . 'cert_verifactu.' . $ext;
        $relativePath   = 'certs/cert_verifactu.' . $ext;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            jsonOut(['error' => 'Error al guardar el certificado en el servidor'], 500);
        }

        // Verificar que el certificado es legible (con la contraseña si ya está guardada)
        $infoMsg = '';
        if ($vfCertPass) {
            $certsBag = [];
            if (openssl_pkcs12_read(file_get_contents($targetPath), $certsBag, $vfCertPass)) {
                $certData = openssl_x509_parse($certsBag['cert'] ?? '');
                $cn = $certData['subject']['CN'] ?? 'desconocido';
                $exp = isset($certData['validTo_time_t'])
                    ? date('d/m/Y', $certData['validTo_time_t']) : '?';
                $infoMsg = "Certificado de {$cn}, válido hasta {$exp}.";
            } else {
                $infoMsg = 'Advertencia: el certificado no se pudo leer con la contraseña actual. Verifica la contraseña.';
            }
        }

        // Guardar la ruta en configuracion
        setCfg($pdo, 'verifactu_cert_path', $relativePath);

        jsonOut(['ok' => true, 'path' => $relativePath, 'info' => $infoMsg]);
    }

    // ──────────────────────────────────────────────────────────
    // xml_preview: genera el XML que se enviaría sin enviarlo
    // No requiere verifactu_activo = 1 (útil para verificar la configuración)
    // ──────────────────────────────────────────────────────────
    if ($action === 'xml_preview') {
        $facturaId = (int)($input['factura_id'] ?? 0);
        if (!$facturaId) jsonOut(['error' => 'factura_id requerido'], 400);

        $f = getRow($pdo, 'facturas', $facturaId);
        if (!$f) jsonOut(['error' => 'Factura no encontrada'], 404);

        $anterior     = getFacturaAnterior($pdo, $f['serie'] ?? 'FAC', $facturaId);
        $hashAnterior = ($anterior && $anterior['hash_factura']) ? $anterior['hash_factura'] : '0';
        $xml          = buildVerifactuXML($f, $hashAnterior, $anterior,
                                          $vfNif, $vfSisNom, $vfSisVer, $vfNumInst);
        $hash         = calcularHashFactura($f, $hashAnterior);
        $qrUrl        = construirQrUrl($f, $urlQRBase);

        jsonOut(['ok' => true, 'xml' => $xml, 'hash' => $hash, 'qr_url' => $qrUrl]);
    }

    // ──────────────────────────────────────────────────────────
    // enviar / reenviar: enviar la factura a la AEAT
    // REQUIERE verifactu_activo = '1'
    // ──────────────────────────────────────────────────────────
    if ($action === 'enviar' || $action === 'reenviar') {
        // Comprobación de seguridad: NUNCA enviar si no está activo
        if ($vfActivo !== '1') {
            jsonOut(['error' => 'VERI*FACTU no está activado. Actívalo en la pantalla de configuración antes de enviar.'], 400);
        }
        if (!$vfNif) {
            jsonOut(['error' => 'Falta el NIF del obligado de emisión en la configuración VERI*FACTU.'], 400);
        }

        $facturaId = (int)($input['factura_id'] ?? 0);
        if (!$facturaId) jsonOut(['error' => 'factura_id requerido'], 400);

        $resultado = procesarEnvio($pdo, $facturaId, $urlAEAT, $urlQRBase,
                                   $vfCertPath, $vfCertPass, $vfNif,
                                   $vfSisNom, $vfSisVer, $vfNumInst);
        $code = isset($resultado['ok']) ? 200 : 500;
        jsonOut($resultado, $code);
    }

    jsonOut(['error' => 'Acción no reconocida'], 400);

} catch (PDOException $e) {
    error_log('[AlquiGest verifactu] DB error: ' . $e->getMessage());
    jsonOut(['error' => 'Error de base de datos'], 500);
} catch (Exception $e) {
    error_log('[AlquiGest verifactu] Error: ' . $e->getMessage());
    jsonOut(['error' => 'Error interno: ' . $e->getMessage()], 500);
}
