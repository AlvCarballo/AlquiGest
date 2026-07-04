<?php
require __DIR__ . '/helpers.php';
requireLocalhost();

$log = __DIR__ . '/pdf_download.log';
$ts  = date('Y-m-d H:i:s');
function logPDF($msg) { global $log, $ts; file_put_contents($log, "[$ts] $msg\n", FILE_APPEND); }

// ── GET: sirve el PDF almacenado y borra el temporal ─────────────
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $token = preg_replace('/[^a-f0-9]/', '', $_GET['token'] ?? '');
    logPDF("GET token=$token");
    if (!$token) { http_response_code(400); exit('Token vacío'); }
    $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ag_pdf_' . $token . '.bin';
    if (!file_exists($tmp)) {
        logPDF("ERROR: temporal no encontrado: $tmp");
        http_response_code(404); exit('PDF expirado o no encontrado');
    }
    $meta  = json_decode(file_get_contents($tmp . '.meta'), true) ?? [];
    $bytes = file_get_contents($tmp);
    @unlink($tmp); @unlink($tmp . '.meta');
    $nombre = $meta['nombre'] ?? 'documento.pdf';
    logPDF("GET OK: enviando $nombre (" . strlen($bytes) . " bytes)");
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    header('Content-Length: ' . strlen($bytes));
    header('Cache-Control: no-store, no-cache');
    header('Pragma: no-cache');
    echo $bytes;
    exit;
}

// ── POST: recibe el b64, guarda en temporal y devuelve el token ──
logPDF("POST data_len=" . strlen($_POST['data'] ?? '') . " nombre=" . ($_POST['nombre'] ?? '?'));

$nombre = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $_POST['nombre'] ?? 'documento.pdf');
if (substr(strtolower($nombre), -4) !== '.pdf') $nombre .= '.pdf';

$raw = $_POST['data'] ?? '';
if (!$raw) { logPDF("ERROR: sin datos"); http_response_code(400); exit('Sin datos'); }

$bytes = base64_decode($raw, true);
if ($bytes === false || strlen($bytes) < 5) {
    logPDF("ERROR: base64 inválido (" . strlen($bytes ?: '') . " bytes)");
    http_response_code(400); exit('Base64 inválido');
}

$token = bin2hex(random_bytes(16));
$tmp   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ag_pdf_' . $token . '.bin';
file_put_contents($tmp,          $bytes);
file_put_contents($tmp . '.meta', json_encode(['nombre' => $nombre]));
logPDF("POST OK: token=$token nombre=$nombre bytes=" . strlen($bytes));

header('Content-Type: application/json');
echo json_encode(['token' => $token]);
