<?php
// ============================================================
//  AlquiGest – Configuración general
//  Edita este fichero para cambiar la conexión a la BD o la
//  versión/año que aparece en el pie de la aplicación.
//  No es necesario tocar ningún otro fichero PHP.
// ============================================================

return [

    // ── Base de datos ─────────────────────────────────────────
    'host'    => 'localhost',   // Servidor MySQL (MAMP: localhost)
    'port'    => 3306,          // Puerto MySQL  (MAMP: 3306)
    'user'    => 'root',        // Usuario MySQL
    'pass'    => 'root',        // Contraseña MySQL
    'name'    => 'alquigest',   // Nombre de la base de datos
    'charset' => 'utf8mb4',

    // ── Aplicación ────────────────────────────────────────────
    'version' => '3.0.1',       // Versión que aparece en el pie del sidebar
    'year'    => '2026',        // Año que aparece junto a la versión

    // ── Log de actividad / auditoría ──────────────────────────
    // true  → se registran las acciones importantes (cobros, facturas, bajas…)
    // false → el log queda desactivado y la tabla log_actividad no se usa
    'log_actividad' => true,

    // ── Clave de cifrado AES-256 para credenciales sensibles ──
    // Se usa para cifrar gmail_pass y verifactu_cert_pass en la BD.
    // IMPORTANTE: Cambia esta cadena antes de poner en producción.
    // Si se pierde esta clave las credenciales cifradas son irrecuperables.
    // Mínimo 32 caracteres recomendado (AES-256 usa los primeros 32 bytes).
    'encrypt_key' => 'AG_ENC_KEY_ALQ26_xK7mP9nQ2vR8sT5uAbCdEfGhIjKlMnOpQr',

];
