<?php
// ============================================================
//  AlquiGest – Envío de recibo por email vía Gmail SMTP
//
//  Recibe un POST JSON con { recibo_id: N, pdf_base64: "...", pdf_filename: "..." }
//  y envía un correo con el HTML del recibo como cuerpo y, opcionalmente,
//  el PDF del recibo como adjunto (base64 generado por jsPDF en el cliente).
//
//  Implementación SMTP manual con sockets PHP (fsockopen) sin librerías
//  externas. Protocolo: SMTP con STARTTLS en el puerto 587 de Gmail.
//  Requisito: PHP debe tener la extensión 'openssl' activa en MAMP.
//
//  Configuración necesaria en la tabla 'empresa':
//    gmail_user → dirección Gmail del remitente (ej: miadmin@gmail.com)
//    gmail_pass → Contraseña de Aplicación de Google (16 caracteres, sin espacios)
//                 (NO la contraseña normal de Gmail — hay que generarla en
//                  myaccount.google.com > Seguridad > Contraseñas de aplicación)
// ============================================================

// ── Funciones compartidas (seguridad, CORS, helpers de BD) ───
require __DIR__ . '/helpers.php';
require __DIR__ . '/auth.php';
requireLocalhost();
setCorsHeaders();
session_bootstrap();

// ── Cargar configuración de BD y leer el cuerpo de la petición ─
$cfg   = require __DIR__ . '/config.php';
$input = json_decode(file_get_contents('php://input'), true) ?: [];

// ── Alias local de json_respond para compatibilidad interna ──
// email.php usaba el nombre jsonOut; se mantiene como alias.
function jsonOut($data, int $code = 200): void { json_respond($data, $code); }

// ── Conectar a MySQL ──────────────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset={$cfg['charset']}",
        $cfg['user'], $cfg['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) { jsonOut(['error' => 'Error BD: '.$e->getMessage()], 503); }

// El envío de emails exige sesión iniciada (cualquier rol).
requireLoginApi($pdo);

// ── Leer configuración de email de la tabla 'empresa' ─────────
// gmail_user y gmail_pass son los datos del remitente.
// Se guardan en BD para que el usuario los configure desde Mi Empresa.
$empresaRow = $pdo->query("SELECT * FROM empresa ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$empresa    = $empresaRow ? rowToObj($empresaRow) : [];
$gmailUser  = $empresa['gmail_user'] ?? '';
$gmailPass  = $empresa['gmail_pass'] ?? '';  // Contraseña de Aplicación de Google

// Descifrar contraseña si está cifrada con AES-256-CBC (encryptValue en helpers.php)
$encKey = $cfg['encrypt_key'] ?? '';
if ($encKey && isEncrypted((string)$gmailPass)) {
    $gmailPass = decryptValue((string)$gmailPass, $encKey);
}

if (!$gmailUser || !$gmailPass) {
    jsonOut(['error' => 'Configura el email Gmail en Mi Empresa antes de enviar.', 'code' => 'NO_EMAIL_CFG'], 400);
}

// ── Determinar si se envía recibo o factura ───────────────────
// El cliente JS envía 'factura_id' para facturas y 'recibo_id' para recibos.
$facturaId = (int)($input['factura_id'] ?? 0);
$reciboId  = (int)($input['recibo_id']  ?? 0);

if ($facturaId > 0) {
    // ── Modo FACTURA ──────────────────────────────────────────────
    $factura = getRow($pdo, 'facturas', $facturaId);
    if (!$factura) jsonOut(['error' => 'Factura no encontrada'], 404);

    $emailDest = trim($factura['cliente_email'] ?? '');
    if (!$emailDest || !filter_var($emailDest, FILTER_VALIDATE_EMAIL)) {
        jsonOut(['error' => 'La factura no tiene email de cliente almacenado.'], 400);
    }

    // Preparar variables para el HTML del correo de factura
    $numFac   = htmlspecialchars($factura['numero_factura'] ?? '-');
    $empNom   = htmlspecialchars($empresa['nombre']    ?? 'Administración');
    $empDir   = htmlspecialchars(($empresa['direccion'] ?? '') . ' ' . ($empresa['municipio'] ?? ''));
    $empTel   = htmlspecialchars($empresa['telefono']  ?? '');
    $cliNom   = htmlspecialchars($factura['cliente_nombre']    ?? '-');
    $dirInm   = htmlspecialchars($factura['inmueble_direccion'] ?? '-');
    $total    = number_format((float)($factura['importe_total'] ?? 0), 2, ',', '.');
    $base     = number_format((float)($factura['base_imponible'] ?? 0), 2, ',', '.');
    $iva      = number_format((float)($factura['importe_iva']   ?? 0), 2, ',', '.');
    $irpf     = number_format((float)($factura['importe_irpf']  ?? 0), 2, ',', '.');
    $periodo  = htmlspecialchars($factura['concepto'] ?? '-');
    $fecha    = htmlspecialchars($factura['fecha_emision'] ?? '-');

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><style>
  body{font-family:Arial,sans-serif;color:#1f2937;background:#f9fafb;margin:0;padding:20px}
  .card{background:#fff;border-radius:10px;padding:32px;max-width:580px;margin:0 auto;border:1px solid #e5e7eb}
  h2{color:#1e40af;margin:0 0 4px} .sub{color:#6b7280;font-size:13px;margin-bottom:24px}
  table{width:100%;border-collapse:collapse;margin:16px 0}
  td{padding:10px 12px;border:1px solid #e5e7eb;font-size:14px}
  tr:first-child td{background:#f3f4f6;font-weight:600}
  .total{font-size:22px;font-weight:700;color:#1e40af;text-align:right;margin-top:16px}
  .footer{margin-top:24px;font-size:12px;color:#9ca3af;text-align:center}
  .legal{margin-top:16px;font-size:11px;color:#9ca3af;border-top:1px solid #e5e7eb;padding-top:12px}
</style></head>
<body><div class="card">
  <h2>🧾 Factura de alquiler</h2>
  <div class="sub">{$empNom} · {$empDir} · {$empTel}</div>
  <table>
    <tr><td>Nº Factura</td><td><strong>{$numFac}</strong></td></tr>
    <tr><td>Concepto</td><td>{$periodo}</td></tr>
    <tr><td>Fecha emisión</td><td>{$fecha}</td></tr>
    <tr><td>Cliente</td><td>{$cliNom}</td></tr>
    <tr><td>Inmueble</td><td>{$dirInm}</td></tr>
    <tr><td>Base imponible</td><td>{$base} €</td></tr>
    <tr><td>IVA</td><td>{$iva} €</td></tr>
    <tr><td>Retención IRPF</td><td>− {$irpf} €</td></tr>
  </table>
  <div class="total">TOTAL: {$total} €</div>
  <div class="legal">Factura emitida conforme al RD 1619/2012. Se adjunta el PDF para sus registros.</div>
  <div class="footer">{$empNom} · Generado con AlquiGest</div>
</div></body></html>
HTML;

    $asunto = !empty($input['asunto_personalizado'])
        ? trim($input['asunto_personalizado'])
        : "Factura {$numFac} — {$empNom}";

} elseif ($reciboId > 0) {
    // ── Modo RECIBO (comportamiento original) ─────────────────────
    $recibo   = getRow($pdo, 'recibos', $reciboId);
    if (!$recibo) jsonOut(['error' => 'Recibo no encontrado'], 404);

    $inquilino = getRow($pdo, 'inquilinos', (int)($recibo['inquilino_id'] ?? 0));
    if (!$inquilino) jsonOut(['error' => 'Inquilino no encontrado'], 404);

    $emailDest = trim($inquilino['email'] ?? '');
    if (!$emailDest || !filter_var($emailDest, FILTER_VALIDATE_EMAIL)) {
        jsonOut(['error' => 'El inquilino no tiene un email válido en su ficha.'], 400);
    }

    // Construir la dirección del inmueble para el cuerpo del email
    $inmueble = getRow($pdo, 'inmuebles', (int)($recibo['inmueble_id'] ?? 0));
    $fincas   = getTable($pdo, 'fincas');
    $finca    = $inmueble ? current(array_filter($fincas, fn($f) => $f['id'] === ($inmueble['finca_id']??0))) : null;
    $dirInm   = htmlspecialchars($inmueble ? trim(($finca['sigla']??'').' '.($finca['calle']??'').' '.($finca['numero']??'').' '.($inmueble['planta']??'').' '.($inmueble['puerta']??'')) : '-');

    // Preparar variables para el HTML del correo de recibo
    $total   = number_format((float)($recibo['importe_total'] ?? 0), 2, ',', '.');
    $periodo = htmlspecialchars($recibo['concepto_periodo'] ?? '-');
    $numRec  = htmlspecialchars($recibo['numero_recibo'] ?? '-');
    $fecha   = htmlspecialchars($recibo['fecha_emision'] ?? '-');
    $empNom  = htmlspecialchars($empresa['nombre'] ?? 'Administración');
    $empDir  = htmlspecialchars(($empresa['direccion'] ?? '') . ' ' . ($empresa['municipio'] ?? ''));
    $empTel  = htmlspecialchars($empresa['telefono'] ?? '');
    $inqNom  = htmlspecialchars($inquilino['nombre'] ?? '-');

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><style>
  body{font-family:Arial,sans-serif;color:#1f2937;background:#f9fafb;margin:0;padding:20px}
  .card{background:#fff;border-radius:10px;padding:32px;max-width:560px;margin:0 auto;border:1px solid #e5e7eb}
  h2{color:#1e40af;margin:0 0 4px} .sub{color:#6b7280;font-size:13px;margin-bottom:24px}
  table{width:100%;border-collapse:collapse;margin:16px 0}
  td{padding:10px 12px;border:1px solid #e5e7eb;font-size:14px}
  tr:first-child td{background:#f3f4f6;font-weight:600}
  .total{font-size:22px;font-weight:700;color:#1e40af;text-align:right;margin-top:16px}
  .footer{margin-top:24px;font-size:12px;color:#9ca3af;text-align:center}
</style></head>
<body><div class="card">
  <h2>🏠 Recibo de alquiler</h2>
  <div class="sub">{$empNom} · {$empDir} · {$empTel}</div>
  <table>
    <tr><td>Nº Recibo</td><td>{$numRec}</td></tr>
    <tr><td>Período</td><td>{$periodo}</td></tr>
    <tr><td>Fecha emisión</td><td>{$fecha}</td></tr>
    <tr><td>Inquilino</td><td>{$inqNom}</td></tr>
    <tr><td>Inmueble</td><td>{$dirInm}</td></tr>
    <tr><td>Renta base</td><td>{$total} €</td></tr>
  </table>
  <div class="total">TOTAL: {$total} €</div>
  <div class="footer">{$empNom} · Generado con AlquiGest</div>
</div></body></html>
HTML;

    $asunto = !empty($input['asunto_personalizado'])
        ? trim($input['asunto_personalizado'])
        : "Recibo de alquiler – {$periodo} – {$numRec}";

} else {
    jsonOut(['error' => 'Se requiere recibo_id o factura_id'], 400);
}

// ── Función de envío SMTP con sockets PHP ────────────────────
// Implementa el diálogo SMTP completo sin librerías:
//   1. Conecta a $host:$port con fsockopen (timeout 10 s)
//   2. Envía EHLO
//   3. Inicia STARTTLS y negocia TLS con stream_socket_enable_crypto
//   4. Autentica con AUTH LOGIN (usuario y contraseña en base64)
//   5. Envía MAIL FROM / RCPT TO / DATA con cabeceras MIME
//   6. Cierra con QUIT
//
// El cuerpo MIME es multipart/mixed con:
//   · Parte 1: HTML del recibo (text/html, base64)
//   · Parte 2 (opcional): PDF adjunto (application/pdf, base64)
//
// Devuelve ['ok' => true] si 250 OK, o ['error' => '...'] si falla.
function smtpSend(string $host, int $port, string $user, string $pass,
                  string $from, string $to, string $subject,
                  string $html, string $pdfBase64 = '', string $pdfFilename = 'recibo.pdf'): array {

    // Boundary único para separar las partes MIME
    $bnd = 'ALQUIG_' . md5(uniqid());

    // ── Construir el cuerpo MIME ──
    $mime  = "--$bnd\r\n";
    $mime .= "Content-Type: text/html; charset=UTF-8\r\n";
    $mime .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $mime .= chunk_split(base64_encode($html)) . "\r\n";

    if ($pdfBase64 !== '') {
        // Sanear el nombre del adjunto para evitar inyección de cabeceras
        $safeName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $pdfFilename);
        $mime .= "--$bnd\r\n";
        $mime .= "Content-Type: application/pdf; name=\"$safeName\"\r\n";
        $mime .= "Content-Transfer-Encoding: base64\r\n";
        $mime .= "Content-Disposition: attachment; filename=\"$safeName\"\r\n\r\n";
        $mime .= chunk_split($pdfBase64) . "\r\n";
    }
    $mime .= "--$bnd--\r\n";

    // ── Cabeceras del mensaje ──
    // El asunto y el nombre del remitente se codifican en Base64 UTF-8
    // para soportar caracteres especiales (tildes, ñ…).
    $headers = implode("\r\n", [
        "From: =?UTF-8?B?".base64_encode('AlquiGest')."?= <$from>",
        "To: $to",
        "Subject: =?UTF-8?B?".base64_encode($subject)."?=",
        "MIME-Version: 1.0",
        "Content-Type: multipart/mixed; boundary=\"$bnd\"",
    ]);

    // ── Diálogo SMTP vía socket ──
    $sock = @fsockopen($host, $port, $errno, $errstr, 10);
    if (!$sock) return ['error' => "No se puede conectar a $host:$port – $errstr"];

    // Función auxiliar para leer la respuesta del servidor (puede ser multilínea)
    $recv = function() use ($sock) {
        $r = '';
        while ($line = fgets($sock, 515)) {
            $r .= $line;
            if (substr($line, 3, 1) === ' ') break; // última línea de la respuesta
        }
        return $r;
    };
    // Función auxiliar para enviar un comando SMTP
    $send = function(string $cmd) use ($sock) { fwrite($sock, $cmd . "\r\n"); };

    $recv(); // 220 — saludo del servidor

    // Identificarse y pedir inicio de TLS
    $send("EHLO localhost");    $recv();
    $send("STARTTLS");          $recv();

    // Activar cifrado TLS sobre el socket existente (requiere openssl en PHP)
    if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        fclose($sock);
        return ['error' => 'No se pudo iniciar TLS. Comprueba que PHP tiene extensión openssl activada en MAMP.'];
    }

    // Volver a identificarse tras activar TLS
    $send("EHLO localhost");    $recv();

    // Autenticación LOGIN: usuario y contraseña en base64
    $send("AUTH LOGIN");        $recv();
    $send(base64_encode($user));$recv();
    $send(base64_encode($pass));$r2 = $recv();
    if (strpos($r2,'235') === false) {
        fclose($sock);
        return ['error' => 'Autenticación Gmail fallida. Usa una Contraseña de Aplicación (no tu contraseña normal).'];
    }

    // Enviar el mensaje
    $send("MAIL FROM:<$from>");  $recv();
    $send("RCPT TO:<$to>");      $recv();
    $send("DATA");               $recv();
    $send("$headers\r\n\r\n$mime\r\n.");  // punto solo en línea = fin del cuerpo
    $r3 = $recv();
    $send("QUIT");
    fclose($sock);

    // 250 = aceptado por el servidor
    if (strpos($r3,'250') !== false) return ['ok' => true];
    return ['error' => 'Error al enviar: '.$r3];
}

// ── Ejecutar el envío ─────────────────────────────────────────
// $asunto, $htmlBody y $emailDest ya están definidos en el bloque if/elseif anterior.
$pdfBase64   = trim($input['pdf_base64']  ?? '');
$pdfFilename = trim($input['pdf_filename'] ?? 'documento.pdf');

$result = smtpSend('smtp.gmail.com', 587, $gmailUser, $gmailPass,
                    $gmailUser, $emailDest, $asunto, $htmlBody, $pdfBase64, $pdfFilename);

// ── Responder al cliente JavaScript ───────────────────────────
$tipoDoc = $facturaId > 0 ? 'Factura' : 'Recibo';
$msg = $pdfBase64 ? "$tipoDoc enviado a $emailDest (con PDF adjunto)" : "$tipoDoc enviado a $emailDest";
if (isset($result['ok'])) {
    jsonOut(['ok' => true, 'mensaje' => $msg]);
} else {
    jsonOut(['error' => $result['error']], 500);
}
