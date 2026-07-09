<?php
// ============================================================
//  AlquiGest – Instalador
//  Accede UNA VEZ a: http://localhost/alquigest/install.php
// ============================================================
$cfg   = require __DIR__ . '/config.php';

// Restringir acceso al instalador: solo localhost
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    die('El instalador solo es accesible desde localhost.');
}

$mode  = $_POST['mode'] ?? '';
$log   = [];
$error = null;
$meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

// ── Control de acceso ──────────────────────────────────────────
// install.php tiene 4 comportamientos posibles (ver README / ANALISIS_USUARIOS_SEGURIDAD_PERMISOS.md):
//   A) Primera instalación (no hay BD, tabla `usuarios`, o usuarios) → accesible sin login.
//   B) Hay usuarios pero no hay sesión válida → redirige a login.php.
//   C) Usuario con rol 'user' → solo puede ver/usar la sección de copia de seguridad.
//   D) Usuario con rol 'admin' → acceso completo, como hasta ahora.
require __DIR__ . '/auth.php';
session_bootstrap();

$primeraInstalacion  = esPrimeraInstalacion($cfg);
$usuariosTablaExiste = false;
$usuarioActual       = null;
$accesoDenegado      = null;

if (!$primeraInstalacion) {
    try {
        $pdoAuthCheck = authConnect($cfg);
        $usuariosTablaExiste = true;
        $sesion = currentUser();
        $usuarioActual = $sesion ? revalidarUsuario($pdoAuthCheck, $sesion) : null;
    } catch (\Throwable $e) {
        $usuarioActual = null;
    }
    if (!$usuarioActual) {
        header('Location: ../../login.php?next=' . urlencode('assets/php/install.php'));
        exit;
    }
} else {
    // Puede que la BD y la tabla usuarios ya existan (con 0 filas) aunque
    // esPrimeraInstalacion() sea true; hace falta saberlo para decidir si se
    // muestra el formulario de instalación o el de "crear primer administrador".
    try {
        $pdoAuthCheck = authConnect($cfg);
        $usuariosTablaExiste = tablaExiste($pdoAuthCheck, 'usuarios');
    } catch (\Throwable $e) {
        $usuariosTablaExiste = false;
    }
}

// $esAdmin controla qué modos pueden ejecutarse. En primera instalación se
// confía por completo (no hay todavía ningún admin al que proteger).
$esAdmin    = $primeraInstalacion || (($usuarioActual['rol'] ?? '') === 'admin');
$soloBackup = !$primeraInstalacion && !$esAdmin;

// Bloqueo duro en backend: un usuario sin rol admin NUNCA puede disparar un
// modo distinto de backup/backup_data, aunque manipule el POST directamente.
$MODOS_BACKUP_PERMITIDOS = ['backup', 'backup_data'];
if ($mode !== '' && !$esAdmin && !in_array($mode, $MODOS_BACKUP_PERMITIDOS, true)) {
    $accesoDenegado = 'No tienes permisos para ejecutar esta acción. Solo un administrador puede hacerlo.';
    $mode = '';
}

// CSRF: toda acción por POST debe presentar el token de la sesión actual.
if ($mode !== '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrfValid($_POST['_csrf'] ?? null)) {
        $accesoDenegado = 'Token de seguridad inválido o caducado. Recarga la página e inténtalo de nuevo.';
        $mode = '';
    }
}

// ── create_admin: alta del primer administrador (solo primera instalación) ──
$adminCreado = null;
if ($mode === 'create_admin' && $primeraInstalacion) {
    try {
        $pdoAdmin = authConnect($cfg);
        if (!tablaExiste($pdoAdmin, 'usuarios')) {
            $error = 'Todavía no existe la tabla de usuarios. Ejecuta primero una instalación limpia o con datos de ejemplo.';
        } else {
            $nombreAdmin   = trim($_POST['nombre'] ?? '');
            $usernameAdmin = trim($_POST['username'] ?? '');
            $emailAdmin    = trim($_POST['email'] ?? '');
            $passAdmin     = (string)($_POST['password'] ?? '');
            $passAdmin2    = (string)($_POST['password2'] ?? '');
            if ($nombreAdmin === '' || $usernameAdmin === '') {
                $error = 'Nombre y usuario son obligatorios.';
            } elseif (!preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $usernameAdmin)) {
                $error = 'El usuario solo puede contener letras, números, puntos, guiones y guiones bajos (mínimo 3 caracteres).';
            } elseif (strlen($passAdmin) < 8) {
                $error = 'La contraseña debe tener al menos 8 caracteres.';
            } elseif ($passAdmin !== $passAdmin2) {
                $error = 'Las contraseñas no coinciden.';
            } else {
                $pdoAdmin->prepare(
                    "INSERT INTO usuarios (nombre, email, username, password_hash, rol, activo) VALUES (?,?,?,?,?,1)"
                )->execute([$nombreAdmin, $emailAdmin, $usernameAdmin, password_hash($passAdmin, PASSWORD_DEFAULT), 'admin']);
                $adminCreado = $usernameAdmin;
                logActividad($pdoAdmin, 'usuario_creado', 'usuarios', (int)$pdoAdmin->lastInsertId(),
                    "Primer administrador creado desde install.php: \"$usernameAdmin\"");
            }
        }
    } catch (\Throwable $e) {
        $error = 'Error al crear el administrador: ' . $e->getMessage();
    }
}

function insertRow(PDO $pdo, string $table, array $data): int {
    foreach ($data as $k => $v) {
        if (is_array($v)) $data[$k] = json_encode($v, JSON_UNESCAPED_UNICODE);
    }
    $cols = '`' . implode('`,`', array_keys($data)) . '`';
    $vals = implode(',', array_fill(0, count($data), '?'));
    $pdo->prepare("INSERT INTO `$table` ($cols) VALUES ($vals)")->execute(array_values($data));
    return (int)$pdo->lastInsertId();
}

// ── Estado de ZipArchive / arreglo automático ────────────────
$zipStatus = class_exists('ZipArchive') ? 'ok' : 'missing';
$zipMsg    = null;

if ($mode === 'fixzip') {
    if ($zipStatus === 'ok') {
        $zipMsg = ['type' => 'ok', 'text' => 'ZipArchive ya está activo. La exportación Excel funciona correctamente.'];
    } else {
        $iniFile = php_ini_loaded_file();
        if (!$iniFile) {
            $zipMsg = ['type' => 'err', 'text' => 'No se encontró php.ini automáticamente. Ábrelo desde el Panel de XAMPP → Config (Apache) → PHP (php.ini) y elimina el ";" de la línea ";extension=zip". Luego reinicia Apache.'];
        } else {
            $content = @file_get_contents($iniFile);
            if ($content === false) {
                $zipMsg = ['type' => 'err', 'text' => "Sin permisos para leer <code>$iniFile</code>. Edítalo manualmente como administrador."];
            } else {
                $already = (bool)preg_match('/^\s*extension\s*=\s*zip\b/mi', $content);
                if ($already) {
                    $zipMsg = ['type' => 'warn', 'text' => "La línea <code>extension=zip</code> ya está activa en <code>$iniFile</code> pero ZipArchive no carga. Reinicia Apache desde el Panel de XAMPP."];
                } else {
                    $new = preg_replace('/^(;+\s*)(extension\s*=\s*zip\b)/mi', '$2', $content);
                    if ($new === $content) {
                        $zipMsg = ['type' => 'err', 'text' => "No se encontró la línea <code>;extension=zip</code> en <code>$iniFile</code>. Añade manualmente la línea <code>extension=zip</code> y reinicia Apache."];
                    } elseif (@file_put_contents($iniFile, $new) !== false) {
                        $zipMsg = ['type' => 'restart', 'ini' => $iniFile,
                            'text' => "php.ini modificado correctamente (<code>$iniFile</code>). <strong>Ahora reinicia Apache</strong> en el Panel de XAMPP para activar ZipArchive."];
                    } else {
                        $zipMsg = ['type' => 'err', 'text' => "Sin permisos para escribir en <code>$iniFile</code>. Ejecútalo como administrador o edita el archivo manualmente y elimina el <code>;</code> de la línea <code>;extension=zip</code>."];
                    }
                }
            }
        }
    }
}

// ── Copia de seguridad ───────────────────────────────────────
if ($mode === 'backup') {
    try {
        $pdo = new PDO(
            "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset={$cfg['charset']}",
            $cfg['user'], $cfg['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $sql  = "-- ============================================================\n";
        $sql .= "-- AlquiGest – Copia de seguridad\n";
        $sql .= "-- Generado: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Base de datos: " . $cfg['name'] . "\n";
        $sql .= "-- ============================================================\n\n";
        $sql .= "SET NAMES utf8mb4;\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
            $sql .= "-- Tabla: $table\n";
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql .= $create[1] . ";\n\n";

            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';
                $sql .= "INSERT INTO `$table` ($cols) VALUES\n";
                $vals = [];
                foreach ($rows as $row) {
                    $escaped = array_map(
                        fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v),
                        $row
                    );
                    $vals[] = '  (' . implode(', ', $escaped) . ')';
                }
                $sql .= implode(",\n", $vals) . ";\n\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        $filename = 'alquigest_backup_' . date('Y-m-d_H-i-s') . '.sql';
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($sql));
        header('Cache-Control: no-cache, no-store');
        echo $sql;
        exit;

    } catch (Exception $e) {
        $error = 'Error al generar la copia de seguridad: ' . $e->getMessage();
    }
}

// ── Copia de seguridad parcial (solo datos, sin estructura) ──
if ($mode === 'backup_data') {
    try {
        $pdo = new PDO(
            "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset={$cfg['charset']}",
            $cfg['user'], $cfg['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $sql  = "-- ============================================================\n";
        $sql .= "-- AlquiGest – Copia de seguridad de datos (solo INSERT)\n";
        $sql .= "-- Generado: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Uso: restaurar datos en una instalación limpia existente\n";
        $sql .= "-- ============================================================\n\n";
        $sql .= "SET NAMES utf8mb4;\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) continue;
            $sql .= "-- Datos de tabla: $table\n";
            $sql .= "DELETE FROM `$table`;\n";
            $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';
            $sql .= "INSERT INTO `$table` ($cols) VALUES\n";
            $vals = [];
            foreach ($rows as $row) {
                $escaped = array_map(
                    fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v),
                    $row
                );
                $vals[] = '  (' . implode(', ', $escaped) . ')';
            }
            $sql .= implode(",\n", $vals) . ";\n\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        $filename = 'alquigest_datos_' . date('Y-m-d_H-i-s') . '.sql';
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($sql));
        header('Cache-Control: no-cache, no-store');
        echo $sql;
        exit;

    } catch (Exception $e) {
        $error = 'Error al generar la copia de datos: ' . $e->getMessage();
    }
}

// ── Restaurar desde archivo SQL ──────────────────────────────
$restoreMsg = null;
if ($mode === 'restore') {
    $f = $_FILES['sql_file'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
        $restoreMsg = ['type'=>'err', 'text'=>'No se recibió ningún archivo o hubo un error al subirlo (código: ' . ($f['error'] ?? '?') . ').'];
    } elseif (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'sql') {
        $restoreMsg = ['type'=>'err', 'text'=>'El archivo debe tener extensión <strong>.sql</strong>.'];
    } else {
        $sqlContent = file_get_contents($f['tmp_name']);
        if ($sqlContent === false || trim($sqlContent) === '') {
            $restoreMsg = ['type'=>'err', 'text'=>'El archivo SQL está vacío o no se pudo leer.'];
        } else {
            try {
                // Crear BD si no existe, luego conectarse a ella
                $dsn0 = "mysql:host={$cfg['host']};port={$cfg['port']};charset={$cfg['charset']}";
                $pdo0 = new PDO($dsn0, $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $pdo0->exec("CREATE DATABASE IF NOT EXISTS `{$cfg['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

                $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['name']};charset={$cfg['charset']}";
                $pdoR = new PDO($dsn, $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

                // Dividir el SQL en sentencias individuales separadas por ";\n"
                // Las líneas de comentario (--) se conservan para conteo pero se ignoran al ejecutar
                $parts = preg_split('/;\s*\n/', $sqlContent);
                $ok = 0; $skip = 0;
                foreach ($parts as $part) {
                    // Eliminar líneas de comentario
                    $stmt = trim(preg_replace('/^--[^\n]*\n?/m', '', $part));
                    if ($stmt === '') { $skip++; continue; }
                    $pdoR->exec($stmt);
                    $ok++;
                }
                $restoreMsg = ['type'=>'ok', 'text'=>"SQL restaurado correctamente. <strong>{$ok}</strong> sentencias ejecutadas en la base de datos <strong>{$cfg['name']}</strong>."];
            } catch (Exception $e) {
                $restoreMsg = ['type'=>'err', 'text'=>'Error al ejecutar el SQL: ' . htmlspecialchars($e->getMessage())];
            }
        }
    }
}

if ($mode === 'clean' || $mode === 'sample') {
    try {
        $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};charset={$cfg['charset']}";
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
        $log[] = "✅ Conexión MySQL correcta (v$ver)";

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$cfg['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$cfg['name']}`");
        $log[] = "✅ Base de datos <strong>{$cfg['name']}</strong> lista";

        $createSqls = [
            'empresa' => "CREATE TABLE `empresa` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `nombre` VARCHAR(200) DEFAULT '',
                `cif` VARCHAR(20) DEFAULT '',
                `direccion` VARCHAR(255) DEFAULT '',
                `cp` VARCHAR(10) DEFAULT '',
                `municipio` VARCHAR(100) DEFAULT '',
                `provincia` VARCHAR(100) DEFAULT '',
                `telefono` VARCHAR(30) DEFAULT '',
                `email` VARCHAR(150) DEFAULT '',
                `iban` VARCHAR(50) DEFAULT '',
                `pie_recibo` TEXT DEFAULT NULL,
                `prefijo_recibos` VARCHAR(10) DEFAULT 'R',
                `gmail_user` VARCHAR(150) DEFAULT '',
                `gmail_pass` VARCHAR(150) DEFAULT '',
                `web` VARCHAR(150) DEFAULT '',
                `email_asunto_recibo` TEXT DEFAULT NULL,
                `email_cuerpo_recibo` TEXT DEFAULT NULL,
                `email_asunto_factura` TEXT DEFAULT NULL,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'propietarios' => "CREATE TABLE `propietarios` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `nombre` VARCHAR(200) DEFAULT '',
                `nif` VARCHAR(20) DEFAULT '',
                `telefono` VARCHAR(30) DEFAULT '',
                `email` VARCHAR(150) DEFAULT '',
                `irpf` VARCHAR(1) DEFAULT '',
                `direccion` VARCHAR(255) DEFAULT '',
                `cp` VARCHAR(10) DEFAULT '',
                `municipio` VARCHAR(100) DEFAULT '',
                `provincia` VARCHAR(100) DEFAULT '',
                `pais` VARCHAR(100) DEFAULT 'España',
                `iban` VARCHAR(50) DEFAULT '',
                `observaciones` TEXT DEFAULT NULL,
                -- Borrado lógico: nunca se borra físicamente un propietario (ver assets/php/api.php, acción 'delete')
                `eliminado` TINYINT(1) NOT NULL DEFAULT 0,
                `eliminado_en` DATETIME NULL DEFAULT NULL,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_propietarios_eliminado` (`eliminado`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'fincas' => "CREATE TABLE `fincas` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `nombre` VARCHAR(200) DEFAULT '',
                `sigla` VARCHAR(10) DEFAULT '',
                `calle` VARCHAR(200) DEFAULT '',
                `numero` VARCHAR(10) DEFAULT '',
                `cp` VARCHAR(10) DEFAULT '',
                `municipio` VARCHAR(100) DEFAULT '',
                `provincia` VARCHAR(100) DEFAULT '',
                `propietario_id` INT DEFAULT NULL,
                `observaciones` TEXT DEFAULT NULL,
                -- Borrado lógico: nunca se borra físicamente una finca (ver assets/php/api.php, acción 'delete')
                `eliminado` TINYINT(1) NOT NULL DEFAULT 0,
                `eliminado_en` DATETIME NULL DEFAULT NULL,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_fincas_eliminado` (`eliminado`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'inmuebles' => "CREATE TABLE `inmuebles` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `finca_id` INT DEFAULT NULL,
                `planta` VARCHAR(20) DEFAULT '',
                `puerta` VARCHAR(10) DEFAULT '',
                `tipo` VARCHAR(50) DEFAULT '',
                `metros` DECIMAL(8,2) DEFAULT 0,
                `referencia_catastral` VARCHAR(50) DEFAULT '',
                `cedula` VARCHAR(50) DEFAULT '',
                `observaciones` TEXT DEFAULT NULL,
                -- Borrado lógico: nunca se borra físicamente un inmueble (ver assets/php/api.php, acción 'delete')
                `eliminado` TINYINT(1) NOT NULL DEFAULT 0,
                `eliminado_en` DATETIME NULL DEFAULT NULL,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_inmuebles_eliminado` (`eliminado`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'inquilinos' => "CREATE TABLE `inquilinos` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `nombre` VARCHAR(200) DEFAULT '',
                `nif` VARCHAR(20) DEFAULT '',
                `telefono` VARCHAR(30) DEFAULT '',
                `movil` VARCHAR(30) DEFAULT '',
                `email` VARCHAR(150) DEFAULT '',
                `direccion` VARCHAR(255) DEFAULT '',
                `cp` VARCHAR(10) DEFAULT '',
                `municipio` VARCHAR(100) DEFAULT '',
                `provincia` VARCHAR(100) DEFAULT '',
                `pais` VARCHAR(100) DEFAULT 'España',
                `iban` VARCHAR(50) DEFAULT '',
                `observaciones` TEXT DEFAULT NULL,
                -- Borrado lógico: nunca se borra físicamente un inquilino (ver assets/php/api.php, acción 'delete')
                `eliminado` TINYINT(1) NOT NULL DEFAULT 0,
                `eliminado_en` DATETIME NULL DEFAULT NULL,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_inquilinos_eliminado` (`eliminado`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'contratos' => "CREATE TABLE `contratos` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `inmueble_id` INT DEFAULT NULL,
                `inquilino_id` INT DEFAULT NULL,
                `fecha_inicio` DATE DEFAULT NULL,
                `fecha_fin` DATE DEFAULT NULL,
                `duracion_anos` INT DEFAULT 1,
                `duracion_unidad` VARCHAR(10) DEFAULT 'anos',
                `aviso_recibo` TINYINT(1) DEFAULT 1,
                `aviso_factura` TINYINT(1) DEFAULT 0,
                `ipc_anio_aplicado` INT NULL DEFAULT NULL,
                `renta_base` DECIMAL(10,2) DEFAULT 0,
                `iva_pct` DECIMAL(5,2) DEFAULT 0,
                `irpf_pct` DECIMAL(5,2) DEFAULT 0,
                `fianza` DECIMAL(10,2) DEFAULT 0,
                `dia_pago` INT DEFAULT 5,
                `estado` VARCHAR(20) DEFAULT 'activo',
                `revision` VARCHAR(50) DEFAULT '',
                `fecha_baja` DATE DEFAULT NULL,
                `motivo_baja` VARCHAR(100) DEFAULT '',
                `obs_baja` TEXT DEFAULT NULL,
                `observaciones` TEXT DEFAULT NULL,
                `motivo_temporada` TEXT DEFAULT NULL,
                `nombre_fiador` VARCHAR(150) DEFAULT NULL,
                `nif_fiador` VARCHAR(20) DEFAULT NULL,
                `direccion_fiador` TEXT DEFAULT NULL,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_contratos_estado` (`estado`),
                INDEX `idx_contratos_inmueble` (`inmueble_id`),
                INDEX `idx_contratos_inquilino` (`inquilino_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            'recibos' => "CREATE TABLE `recibos` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `contrato_id` INT DEFAULT NULL,
                `inquilino_id` INT DEFAULT NULL,
                `inmueble_id` INT DEFAULT NULL,
                `numero_recibo` VARCHAR(50) DEFAULT '',
                `numero_seq` INT DEFAULT 0,
                `fecha_emision` DATE DEFAULT NULL,
                `periodo_desde` DATE DEFAULT NULL,
                `periodo_hasta` DATE DEFAULT NULL,
                `concepto_periodo` VARCHAR(100) DEFAULT '',
                `fecha_limite` DATE DEFAULT NULL,
                `renta_base` DECIMAL(10,2) DEFAULT 0,
                `importe_iva` DECIMAL(10,2) DEFAULT 0,
                `importe_irpf` DECIMAL(10,2) DEFAULT 0,
                `importe_total` DECIMAL(10,2) DEFAULT 0,
                `importe_pagado` DECIMAL(10,2) DEFAULT 0,
                `conceptos_extra` TEXT DEFAULT NULL,
                `notas` TEXT DEFAULT NULL,
                `pagos` TEXT DEFAULT NULL,
                `estado` VARCHAR(20) DEFAULT 'pendiente',
                `aviso_recibo` TINYINT(1) NULL DEFAULT 0,
                `factura_id` INT DEFAULT NULL,
                -- id del recibo que este recibo rectifica (recibo rectificativo RER-AAAAMM-NNNNN).
                -- NULL en recibos normales; solo se rellena en el recibo rectificativo generado
                -- al anular un recibo que todavía no tenía factura emitida.
                `recibo_rectificado_id` INT DEFAULT NULL,
                `fecha_creacion` VARCHAR(50) DEFAULT '',
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_recibos_numero_recibo` (`numero_recibo`),
                INDEX `idx_recibos_estado` (`estado`),
                INDEX `idx_recibos_contrato` (`contrato_id`),
                INDEX `idx_recibos_inquilino` (`inquilino_id`),
                INDEX `idx_recibos_inmueble` (`inmueble_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // ── Tabla de facturas ──────────────────────────────────────────
            // Cada factura se genera desde un recibo existente y almacena una
            // copia congelada de los datos fiscales del momento de emisión.
            // La restricción UNIQUE sobre recibo_id impide duplicados en BD.
            // Los campos VERI*FACTU quedan preparados para integración futura con AEAT.
            'facturas' => "CREATE TABLE `facturas` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `recibo_id` INT DEFAULT NULL,
                `contrato_id` INT NOT NULL DEFAULT 0,
                `inquilino_id` INT NOT NULL DEFAULT 0,
                `inmueble_id` INT NOT NULL DEFAULT 0,
                -- Numeración legal: serie + número correlativo
                `numero_factura` VARCHAR(50) NOT NULL,
                `numero_seq` INT DEFAULT NULL,
                `serie` VARCHAR(20) DEFAULT 'FAC',
                `tipo_factura` VARCHAR(20) DEFAULT 'F1',
                -- Fechas legales
                `fecha_emision` DATE NOT NULL,
                `fecha_operacion` DATE DEFAULT NULL,
                `periodo_desde` DATE DEFAULT NULL,
                `periodo_hasta` DATE DEFAULT NULL,
                -- Emisor (empresa/propietario): copia congelada del momento de emisión
                `emisor_nombre` VARCHAR(255) DEFAULT NULL,
                `emisor_nif` VARCHAR(50) DEFAULT NULL,
                `emisor_direccion` VARCHAR(255) DEFAULT NULL,
                `emisor_cp` VARCHAR(20) DEFAULT NULL,
                `emisor_municipio` VARCHAR(100) DEFAULT NULL,
                `emisor_provincia` VARCHAR(100) DEFAULT NULL,
                `emisor_email` VARCHAR(150) DEFAULT NULL,
                `emisor_telefono` VARCHAR(50) DEFAULT NULL,
                `emisor_iban` VARCHAR(50) DEFAULT NULL,
                -- Cliente (inquilino): copia congelada del momento de emisión
                `cliente_nombre` VARCHAR(255) DEFAULT NULL,
                `cliente_nif` VARCHAR(50) DEFAULT NULL,
                `cliente_direccion` VARCHAR(255) DEFAULT NULL,
                `cliente_cp` VARCHAR(20) DEFAULT NULL,
                `cliente_municipio` VARCHAR(100) DEFAULT NULL,
                `cliente_provincia` VARCHAR(100) DEFAULT NULL,
                `cliente_email` VARCHAR(150) DEFAULT NULL,
                -- Descripción del servicio
                `inmueble_direccion` VARCHAR(255) DEFAULT NULL,
                `concepto` TEXT DEFAULT NULL,
                `conceptos_extra` TEXT DEFAULT NULL,
                `notas` TEXT DEFAULT NULL,
                -- Importes fiscales desglosados (copias del recibo)
                `base_imponible` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `iva_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                `importe_iva` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `irpf_pct` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                `importe_irpf` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `importe_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                -- Estado: emitida | anulada | rectificada
                `estado` VARCHAR(20) NOT NULL DEFAULT 'emitida',
                -- Campos preparados para integración futura con VERI*FACTU / SIF (AEAT)
                -- TODO: implementar envío real a AEAT cuando se requiera
                `hash_factura` VARCHAR(255) DEFAULT NULL,
                `hash_anterior` VARCHAR(255) DEFAULT NULL,
                `qr_url` TEXT DEFAULT NULL,
                `verifactu_estado` VARCHAR(50) DEFAULT 'no_enviado',
                `verifactu_respuesta` TEXT DEFAULT NULL,
                -- id de la factura que esta rectifica (para futuras facturas rectificativas)
                `factura_rectificada_id` INT DEFAULT NULL,
                `fecha_creacion` VARCHAR(50) DEFAULT '',
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                -- Unicidad por número de factura; recibo_id puede ser NULL (rectificativas no tienen recibo propio)
                UNIQUE KEY `uq_facturas_numero_factura` (`numero_factura`),
                INDEX `idx_facturas_fecha_emision` (`fecha_emision`),
                INDEX `idx_facturas_estado` (`estado`),
                INDEX `idx_facturas_inquilino_id` (`inquilino_id`),
                INDEX `idx_facturas_contrato_id` (`contrato_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // Historial de revisiones de renta: registra cada cambio de renta
            // (revisión IPC/IRAV, subida fija, renovación de contrato, etc.)
            // para tener un histórico cronológico de evolución de rentas por contrato.
            'historial_rentas' => "CREATE TABLE `historial_rentas` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `contrato_id` INT NOT NULL DEFAULT 0,
                `fecha` DATE NOT NULL,
                `tipo_revision` VARCHAR(50) DEFAULT '',
                `porcentaje` DECIMAL(6,3) DEFAULT 0,
                `renta_anterior` DECIMAL(10,2) DEFAULT 0,
                `renta_nueva` DECIMAL(10,2) DEFAULT 0,
                `observaciones` TEXT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_historial_contrato` (`contrato_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // Inquilinos secundarios: firmantes adicionales del contrato (sin impacto en lógica de negocio)
            'contratos_inq_sec' => "CREATE TABLE `contratos_inq_sec` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // Tabla de configuración: parámetros ajustables sin tocar el código.
            // variable: clave única del parámetro (ej: 'filas_dashboard')
            // valor:    valor almacenado como texto
            // descripcion: explicación del parámetro para el administrador
            'configuracion' => "CREATE TABLE `configuracion` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `variable` VARCHAR(100) NOT NULL,
                `valor` VARCHAR(255) DEFAULT '',
                `descripcion` VARCHAR(500) DEFAULT '',
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uq_variable` (`variable`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            // Plantillas DOCX (motor de plantillas). Misma definición que la
            // auto-creación perezosa de assets/php/plantillas.php, para que una
            // instalación limpia deje también esta tabla lista desde el principio.
            'plantillas' => "CREATE TABLE `plantillas` (
                `id`             INT AUTO_INCREMENT PRIMARY KEY,
                `nombre`         VARCHAR(150) NOT NULL,
                `tipo_documento` VARCHAR(50)  NOT NULL DEFAULT 'otro',
                `descripcion`    TEXT         DEFAULT NULL,
                `fichero`        VARCHAR(255) NOT NULL,
                `activa`         TINYINT(1)  DEFAULT 1,
                `por_defecto`    TINYINT(1)  DEFAULT 0,
                -- Borrado lógico: nunca se borra físicamente una plantilla (ver assets/php/plantillas.php, acción 'delete')
                `eliminado`      TINYINT(1)  NOT NULL DEFAULT 0,
                `eliminado_en`   DATETIME    NULL DEFAULT NULL,
                `created_at`     DATETIME    DEFAULT CURRENT_TIMESTAMP,
                `updated_at`     DATETIME    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_plantillas_eliminado` (`eliminado`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];

        // Tabla de secuencias de numeración mensual (REC, FAC, RECT, futuras series)
        $createSqls['doc_secuencias'] = "CREATE TABLE `doc_secuencias` (
            `tipo`    VARCHAR(20) NOT NULL COMMENT 'REC=Recibos, FAC=Facturas, RECT=Rectificativas',
            `periodo` CHAR(6)     NOT NULL COMMENT 'Periodo YYYYMM de emision',
            `ultimo`  INT         NOT NULL DEFAULT 0 COMMENT 'Ultimo numero de secuencia emitido en este periodo',
            PRIMARY KEY (`tipo`, `periodo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
          COMMENT='Secuencias de numeracion mensual. Un numero consumido nunca se reutiliza.'";

        foreach ($createSqls as $t => $sql) {
            $pdo->exec("DROP TABLE IF EXISTS `$t`");
            $pdo->exec($sql);
            $log[] = "✅ Tabla <code>$t</code> creada";
        }

        // ── Tablas que NUNCA se destruyen en una reinstalación ────────
        // `usuarios` y `log_actividad` son transversales a los datos de negocio:
        // una "instalación limpia" o "con datos de ejemplo" resetea propietarios,
        // fincas, contratos, etc., pero NO debe borrar las cuentas de usuario
        // (dejaría a todos fuera) ni el historial de auditoría/login. Por eso usan
        // CREATE TABLE IF NOT EXISTS en vez de DROP+CREATE, fuera del bucle anterior.
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
        $log[] = "✅ Tabla <code>usuarios</code> comprobada (no se destruye en reinstalaciones)";

        if (!empty($cfg['log_actividad'])) {
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
            $log[] = "✅ Tabla <code>log_actividad</code> comprobada (no se destruye en reinstalaciones)";
        }

        // ── Valores por defecto de configuración ──────────────
        // Se insertan siempre (instalación limpia y con datos de ejemplo)
        // para que la aplicación funcione sin configuración manual.
        //
        // Convención de visibilidad de botones destructivos:
        //   '1' → el botón se muestra (comportamiento por defecto)
        //   '0' → el botón queda oculto (útil en entornos de solo consulta)
        $configDefaults = [
            // ── Dashboard — visibilidad de widgets (pestaña "Dashboard" de Configuración) ──
            ['dash_kpis',             '1', 'Muestra las 4 tarjetas de resumen del Dashboard (propietarios, inmuebles, contratos activos, pendiente de cobro) (1=visible, 0=oculto).'],
            ['dash_alerta_ipc',       '1', 'Muestra el aviso naranja de revisión IPC/IRAV pendiente este mes en el Dashboard (1=visible, 0=oculto).'],
            ['dash_alerta_backup',    '1', 'Muestra el aviso azul de backup desactualizado en el Dashboard (1=visible, 0=oculto).'],
            ['dash_avisos_revision',  '1', 'Muestra la tarjeta de revisiones anuales urgentes (aniversario en los próximos 30 días) en el Dashboard (1=visible, 0=oculto).'],
            ['dash_renovaciones',     '1', 'Muestra la tabla de próximas renovaciones de contrato (vencidas o en los próximos 6 meses) en el Dashboard (1=visible, 0=oculto).'],
            ['dash_revisiones',       '1', 'Muestra la tabla completa de próximas revisiones anuales de todos los contratos activos en el Dashboard (1=visible, 0=oculto).'],
            ['dash_ultimos_recibos',  '1', 'Muestra la tabla de últimos 5 recibos en el Dashboard (1=visible, 0=oculto).'],
            ['dash_cobrado_mes',      '1', 'Muestra la tarjeta de cobrado este mes y recibos pendientes en el Dashboard (1=visible, 0=oculto).'],
            ['dash_graficos',         '1', 'Muestra los gráficos de ingresos y ocupación en el Dashboard (1=visible, 0=oculto).'],
            ['dash_cobros_esperados', '1', 'Muestra el widget de previsión de cobros del mes actual en el Dashboard (1=visible, 0=oculto).'],
            ['dash_backup_dias',      '7', 'Número de días sin backup a partir del cual aparece el aviso de backup en el Dashboard. Mínimo: 1.'],
            ['dash_log_actividad',    '1', 'Muestra el widget de últimas actividades en el Dashboard (1=visible, 0=oculto).'],
            // ── Paginación ──────────────────────────────────────
            ['filas_dashboard',  '6',  'Número de filas por página en las tarjetas del dashboard (renovaciones y revisiones de renta). Mínimo: 1.'],
            ['filas_recibos',     '30', 'Número de filas por página en la tabla de Recibos. Mínimo: 5.'],
            ['filas_propietarios','20', 'Número de filas por página en la tabla de Propietarios. Mínimo: 5.'],
            ['filas_fincas',      '20', 'Número de filas por página en la tabla de Fincas. Mínimo: 5.'],
            ['filas_inmuebles',   '20', 'Número de filas por página en la tabla de Pisos / Locales. Mínimo: 5.'],
            ['filas_inquilinos',  '20', 'Número de filas por página en la tabla de Inquilinos. Mínimo: 5.'],
            ['filas_contratos',   '20', 'Número de filas por página en la tabla de Contratos. Mínimo: 5.'],
            ['filas_facturas',    '20', 'Número de filas por página en la tabla de Facturas. Mínimo: 5.'],
            // ── Visibilidad de botones — Contratos ───────────────
            ['VisiGenerarReciboCont', '1', 'Muestra el botón "Generar recibo" en la tabla de contratos activos (1=visible, 0=oculto).'],
            ['VisiRenovarCont',       '1', 'Muestra el botón "Renovar" en la tabla de contratos activos (1=visible, 0=oculto).'],
            ['VisiHistorialCont',     '1', 'Muestra el botón "Historial" de revisiones de renta en la tabla de contratos activos (1=visible, 0=oculto).'],
            ['VisiBajaCont',          '1', 'Muestra el botón "Baja" en la tabla de contratos activos (1=visible, 0=oculto).'],
            ['VisiPDFCont',           '0', 'Muestra el botón "PDF" del contrato (1=visible, 0=oculto). Oculto por defecto.'],
            ['VisiFianzaCont',        '1', 'Muestra el botón "Fianza" (justificante) cuando el contrato tiene fianza registrada (1=visible, 0=oculto).'],
            ['VisiDocxCont',          '1', 'Muestra el botón "DOCX" para generar documentos desde plantillas Word (1=visible, 0=oculto).'],
            ['VisiBorrarProp',   '1', 'Muestra el botón Eliminar en la tabla de Propietarios (1=visible, 0=oculto).'],
            ['VisiBorrarFinc',   '1', 'Muestra el botón Eliminar en la tabla de Fincas (1=visible, 0=oculto).'],
            ['VisiBorrarInm',    '1', 'Muestra el botón Eliminar en la tabla de Inmuebles (1=visible, 0=oculto).'],
            ['VisiBorrarInq',    '1', 'Muestra el botón Eliminar en la tabla de Inquilinos (1=visible, 0=oculto).'],
            // Contratos: no existe botón "Eliminar" (los contratos no se borran físicamente,
            // solo se dan de baja). Ver assets/php/api.php, acción 'delete'.
            // ── Visibilidad de botones — Recibos ─────────────────
            ['VisiCobrarReci',   '1', 'Muestra los botones "Cobrar" / "Ver cobros" en la tabla de Recibos (1=visible, 0=oculto).'],
            ['VisiEmailReci',    '1', 'Muestra el botón de enviar por email en la tabla de Recibos (1=visible, 0=oculto).'],
            ['VisiImprimirReci', '1', 'Muestra el botón de imprimir/PDF en la tabla de Recibos (1=visible, 0=oculto).'],
            ['VisiFacturaReci',  '1', 'Muestra el botón de generar/ver factura en la tabla de Recibos (1=visible, 0=oculto).'],
            ['VisiAnularReci',   '1', 'Muestra el botón Anular en la tabla de Recibos (1=visible, 0=oculto).'],
            ['VisiAnularPago',   '1', 'Muestra el botón Anular en el panel de cobros de un recibo (1=visible, 0=oculto).'],
            // ── Visibilidad de botones — Facturas ────────────────
            ['VisiImprimirFact',    '1', 'Muestra el botón de imprimir/PDF en la tabla de Facturas (1=visible, 0=oculto).'],
            ['VisiEmailFact',       '1', 'Muestra el botón de enviar por email en la tabla de Facturas (1=visible, 0=oculto).'],
            ['VisiReciboOrigenFact','1', 'Muestra el botón para navegar al recibo origen de la factura (1=visible, 0=oculto).'],
            ['VisiAEATFact',        '1', 'Muestra el botón de envío a AEAT (VERI*FACTU) en la tabla de Facturas (1=visible, 0=oculto).'],
            ['VisiXMLFact',         '1', 'Muestra el botón "Ver XML AEAT" en facturas ya enviadas (1=visible, 0=oculto).'],
            ['VisiAnularFact',      '1', 'Muestra el botón Anular en facturas emitidas (1=visible, 0=oculto).'],
            // ── Visibilidad de botones — Inquilinos / Propietarios / Fincas / Inmuebles ──
            ['VisiPagosInq',      '1', 'Muestra el botón "Pagos" (historial de cobros) en la tabla de Inquilinos (1=visible, 0=oculto).'],
            ['VisiHistorialInq',  '1', 'Muestra el botón "Historial" completo en la tabla de Inquilinos (1=visible, 0=oculto).'],
            ['VisiIRPFProp',      '1', 'Muestra el botón "IRPF" (informe fiscal anual) en la tabla de Propietarios (1=visible, 0=oculto).'],
            // ── Sistema ───────────────────────────────────────────
            ['VisiBackupJSON',    '0', 'Muestra el botón de descarga de backup JSON en Mi Empresa (1=visible, 0=oculto). Oculto por defecto.'],
            ['whatsappVis',      '1', 'Muestra los botones de WhatsApp en la tabla de recibos y en el modal de lote (1=visible, 0=oculto).'],
            ['whatsappPDF',      '1', 'Genera y descarga el PDF del recibo al enviar por WhatsApp (1=activo, 0=solo texto sin PDF).'],
            ['whatsappNativo',   '1', 'Método de apertura de WhatsApp: 1=ventana emergente (window.open), 0=enlace directo href (nunca bloqueado por el navegador).'],
            // ── VERI*FACTU / SIF — integración con AEAT ──────────────────────
            // Ninguna acción se realiza hacia la AEAT mientras verifactu_activo = '0'.
            // Para activar: completar la configuración en la pantalla VERI*FACTU del menú
            // Configuración y cambiar verifactu_activo a '1' cuando todo esté listo.
            ['verifactu_activo',          '0',         'VERI*FACTU: 0=desactivado (por defecto), 1=activo. Solo activar cuando el certificado y la configuración estén completos.'],
            ['verifactu_entorno',         'pruebas',   'Entorno AEAT: pruebas (prewww1.aeat.es) o produccion (www1.aeat.es).'],
            ['verifactu_cert_path',       '',          'Ruta relativa al certificado .p12/.pfx del emisor (ej: certs/cert_verifactu.p12).'],
            ['verifactu_cert_pass',       '',          'Contraseña del certificado digital .p12/.pfx.'],
            ['verifactu_nif_sif',         '',          'NIF del obligado de emisión ante el SIF (normalmente igual al NIF de empresa).'],
            ['verifactu_sistema_nombre',  'AlquiGest', 'Nombre del sistema informático de facturación declarado ante AEAT.'],
            ['verifactu_sistema_version', '2.0.0',     'Versión del sistema informático de facturación.'],
            ['verifactu_num_instalacion', '1',         'Número de instalación del sistema (1 para instalación única).'],
            // ── Documentos — plantillas DOCX ─────────────────────
            ['docs_plantillas_activas', '1', 'Activa el módulo de plantillas DOCX en toda la aplicación (1=activo, 0=desactivado).'],
            ['docs_permitir_pdf',       '0', 'Conversión DOCX→PDF automática (requiere LibreOffice en el servidor; no disponible en MAMP/Windows). 0=desactivado.'],
            ['dash_plantillas_estado',  '1', 'Muestra en el Dashboard el número de plantillas DOCX activas (1=visible, 0=oculto).'],
            // ── Visibilidad del menú lateral ──────────────────────
            // '1' = visible (por defecto), '0' = oculto.
            // Dashboard y Parámetros son siempre visibles y no tienen clave aquí.
            ['menu_propietarios', '1', 'Muestra la opción Propietarios en el menú lateral (1=visible, 0=oculto).'],
            ['menu_fincas',       '1', 'Muestra la opción Fincas / Edificios en el menú lateral (1=visible, 0=oculto).'],
            ['menu_inmuebles',    '1', 'Muestra la opción Pisos / Locales en el menú lateral (1=visible, 0=oculto).'],
            ['menu_inquilinos',   '1', 'Muestra la opción Inquilinos en el menú lateral (1=visible, 0=oculto).'],
            ['menu_contratos',    '1', 'Muestra la opción Contratos en el menú lateral (1=visible, 0=oculto).'],
            ['menu_recibos',      '1', 'Muestra la opción Recibos en el menú lateral (1=visible, 0=oculto).'],
            ['menu_facturas',     '1', 'Muestra la opción Facturas en el menú lateral (1=visible, 0=oculto).'],
            ['menu_generar',      '1', 'Muestra la opción Generar Recibos en el menú lateral (1=visible, 0=oculto).'],
            ['menu_informes',     '1', 'Muestra la opción Informes Excel en el menú lateral (1=visible, 0=oculto).'],
            ['menu_calendario',   '1', 'Muestra la opción Calendario Cobros en el menú lateral (1=visible, 0=oculto).'],
            ['menu_morosidad',    '1', 'Muestra la opción Morosidad en el menú lateral (1=visible, 0=oculto).'],
            ['menu_actividad',    '1', 'Muestra la opción Actividad en el menú lateral (1=visible, 0=oculto).'],
            ['menu_empresa',      '1', 'Muestra la opción Mi Empresa en el menú lateral (1=visible, 0=oculto).'],
            ['menu_verifactu',    '1', 'Muestra la opción VERI*FACTU en el menú lateral (1=visible, 0=oculto).'],
            ['menu_plantillas',   '1', 'Muestra la opción Plantillas en el menú lateral (1=visible, 0=oculto).'],
        ];
        foreach ($configDefaults as [$var, $val, $desc]) {
            insertRow($pdo, 'configuracion', [
                'variable'    => $var,
                'valor'       => $val,
                'descripcion' => $desc,
            ]);
        }
        $log[] = "✅ Configuración por defecto insertada (" . count($configDefaults) . " parámetros)";

        // ── Plantillas DOCX obligatorias (funcionales, no datos de ejemplo) ──
        // Se insertan tanto en instalación limpia como con datos de ejemplo: son
        // necesarias para que el módulo de Plantillas / generación de documentos
        // funcione desde el primer arranque. Los ficheros DOCX ya deben existir en
        // uploads/plantillas/ (se comprueba su existencia antes de insertar la fila;
        // si falta algún fichero se registra un aviso y no se inserta esa plantilla,
        // para no dejar referencias rotas en la tabla).
        $plantillasDefault = [
            ['nombre'=>'Contrato de Vivienda 2026',                                   'tipo_documento'=>'contrato_arrendamiento', 'descripcion'=>'Contrato de arrendamiento de vivienda (inquilino único)',                        'fichero'=>'20260629202745_0f46bec435f5.docx'],
            ['nombre'=>'Contrato de Vivienda 2026 (Multi-inquilino)',                  'tipo_documento'=>'contrato_arrendamiento', 'descripcion'=>'Contrato de arrendamiento de vivienda con bloque multi-inquilino',               'fichero'=>'20260629202745_9cd13214a228.docx'],
            ['nombre'=>'Contrato de Vivienda Temporada 2026',                         'tipo_documento'=>'contrato_arrendamiento', 'descripcion'=>'Contrato de arrendamiento de vivienda de temporada (inquilino único)',           'fichero'=>'20260629202745_3646151941bb.docx'],
            ['nombre'=>'Contrato de Vivienda Temporada 2026 (Multi-inquilino)',        'tipo_documento'=>'contrato_arrendamiento', 'descripcion'=>'Contrato de arrendamiento de vivienda de temporada con bloque multi-inquilino',  'fichero'=>'20260629202745_0f945bd575bb.docx'],
            ['nombre'=>'Inventario del Contrato 2026',                                'tipo_documento'=>'otro',                   'descripcion'=>'Anexo de inventario y estado del inmueble (inquilino único)',                    'fichero'=>'20260629202745_696725afdf36.docx'],
            ['nombre'=>'Inventario del Contrato 2026 (Multi-inquilino)',              'tipo_documento'=>'otro',                   'descripcion'=>'Anexo de inventario y estado del inmueble con bloque multi-inquilino',           'fichero'=>'20260629202745_8e862f292091.docx'],
        ];
        $plantillasDir = __DIR__ . '/../../uploads/plantillas/';
        $plantillasOk = 0;
        foreach ($plantillasDefault as $pl) {
            if (!is_file($plantillasDir . $pl['fichero'])) {
                $log[] = "⚠️ Plantilla <code>{$pl['fichero']}</code> ({$pl['nombre']}) no encontrada en <code>uploads/plantillas/</code> — no se ha insertado su fila en la tabla <code>plantillas</code>.";
                continue;
            }
            insertRow($pdo, 'plantillas', [
                'nombre'         => $pl['nombre'],
                'tipo_documento' => $pl['tipo_documento'],
                'descripcion'    => $pl['descripcion'],
                'fichero'        => $pl['fichero'],
                'activa'         => 1,
                'por_defecto'    => 0,
            ]);
            $plantillasOk++;
        }
        $log[] = "✅ $plantillasOk/" . count($plantillasDefault) . " plantillas DOCX obligatorias insertadas";

        if ($mode === 'sample') {
            // ── Empresa ──────────────────────────────────────────
            insertRow($pdo, 'empresa', [
                'nombre'=>'Administración de Fincas López','cif'=>'B12345678',
                'direccion'=>'C/ Gran Vía 10, 2º B','cp'=>'28013','municipio'=>'Madrid','provincia'=>'Madrid',
                'telefono'=>'91 555 1234','email'=>'admin@fincaslopez.es',
                'iban'=>'ES91 2100 0418 4502 0005 1332',
                'pie_recibo'=>'Gracias por su pago puntual.','prefijo_recibos'=>'REC'
            ]);
            $log[] = "✅ Datos de empresa creados";

            // ── Propietarios ─────────────────────────────────────
            $p1 = insertRow($pdo,'propietarios',['nombre'=>'García Martínez, Manuel','nif'=>'12345678A','telefono'=>'650 111 222','email'=>'manuel@email.com','observaciones'=>'']);
            $p2 = insertRow($pdo,'propietarios',['nombre'=>'Fernández Torres, Ana','nif'=>'87654321B','telefono'=>'660 333 444','email'=>'ana.fernandez@email.com','observaciones'=>'']);
            $log[] = "✅ 2 propietarios creados";

            // ── Fincas ───────────────────────────────────────────
            $f1 = insertRow($pdo,'fincas',['nombre'=>'C/ Mayor 15','sigla'=>'C','calle'=>'Mayor','numero'=>'15','cp'=>'28001','municipio'=>'Madrid','provincia'=>'Madrid','propietario_id'=>$p1,'observaciones'=>'Edificio reformado en 2010']);
            $f2 = insertRow($pdo,'fincas',['nombre'=>'Av. Constitución 8','sigla'=>'AV','calle'=>'Constitución','numero'=>'8','cp'=>'28002','municipio'=>'Madrid','provincia'=>'Madrid','propietario_id'=>$p2,'observaciones'=>'']);
            $log[] = "✅ 2 fincas creadas";

            // ── Inmuebles ─────────────────────────────────────────
            $i1 = insertRow($pdo,'inmuebles',['finca_id'=>$f1,'planta'=>'1º','puerta'=>'A','tipo'=>'vivienda','metros'=>65,'referencia_catastral'=>'','cedula'=>'','observaciones'=>'Exterior, balcón']);
            $i2 = insertRow($pdo,'inmuebles',['finca_id'=>$f1,'planta'=>'1º','puerta'=>'B','tipo'=>'vivienda','metros'=>62,'referencia_catastral'=>'','cedula'=>'','observaciones'=>'Interior']);
            $i3 = insertRow($pdo,'inmuebles',['finca_id'=>$f1,'planta'=>'2º','puerta'=>'A','tipo'=>'vivienda','metros'=>70,'referencia_catastral'=>'','cedula'=>'','observaciones'=>'Soleado, orientación sur']);
            $i4 = insertRow($pdo,'inmuebles',['finca_id'=>$f1,'planta'=>'2º','puerta'=>'B','tipo'=>'vivienda','metros'=>68,'referencia_catastral'=>'','cedula'=>'','observaciones'=>'']);
            $i5 = insertRow($pdo,'inmuebles',['finca_id'=>$f2,'planta'=>'BAJO','puerta'=>'A','tipo'=>'local','metros'=>90,'referencia_catastral'=>'','cedula'=>'','observaciones'=>'Local comercial con escaparate']);
            $i6 = insertRow($pdo,'inmuebles',['finca_id'=>$f2,'planta'=>'1º','puerta'=>'A','tipo'=>'vivienda','metros'=>80,'referencia_catastral'=>'','cedula'=>'','observaciones'=>'']);
            $log[] = "✅ 6 inmuebles creados";

            // ── Inquilinos ────────────────────────────────────────
            $q1 = insertRow($pdo,'inquilinos',['nombre'=>'Rodríguez Pérez, Laura','nif'=>'11111111C','telefono'=>'611 100 200','email'=>'laura.rodriguez@gmail.com','direccion'=>'C/ Mayor 15 1º A','cp'=>'28001','municipio'=>'Madrid','provincia'=>'Madrid','iban'=>'ES00 1234 5678 9012 3456 7890','observaciones'=>'']);
            $q2 = insertRow($pdo,'inquilinos',['nombre'=>'González Sánchez, Pedro','nif'=>'22222222D','telefono'=>'622 200 300','email'=>'pedro.gonzalez@gmail.com','direccion'=>'C/ Mayor 15 1º B','cp'=>'28001','municipio'=>'Madrid','provincia'=>'Madrid','iban'=>'','observaciones'=>'']);
            $q3 = insertRow($pdo,'inquilinos',['nombre'=>'Martín López, María','nif'=>'33333333E','telefono'=>'633 300 400','email'=>'maria.martin@outlook.com','direccion'=>'C/ Mayor 15 2º A','cp'=>'28001','municipio'=>'Madrid','provincia'=>'Madrid','iban'=>'ES11 9876 5432 1098 7654 3210','observaciones'=>'']);
            $q4 = insertRow($pdo,'inquilinos',['nombre'=>'Torres Ruiz, Carlos','nif'=>'44444444F','telefono'=>'644 400 500','email'=>'carlos.torres@gmail.com','direccion'=>'C/ Mayor 15 2º B','cp'=>'28001','municipio'=>'Madrid','provincia'=>'Madrid','iban'=>'','observaciones'=>'Paga siempre a principios de mes']);
            $q5 = insertRow($pdo,'inquilinos',['nombre'=>'Comercial Díaz S.L.','nif'=>'B98765432','telefono'=>'91 700 1234','email'=>'admin@comercialdiaz.es','direccion'=>'Av. Constitución 8 Bajo A','cp'=>'28002','municipio'=>'Madrid','provincia'=>'Madrid','iban'=>'ES22 5555 6666 7777 8888 9999','observaciones'=>'Local comercial, pago trimestral']);
            $q6 = insertRow($pdo,'inquilinos',['nombre'=>'Jiménez Vega, Sofía','nif'=>'55555555G','telefono'=>'655 500 600','email'=>'sofia.jimenez@gmail.com','direccion'=>'Av. Constitución 8 1º A','cp'=>'28002','municipio'=>'Madrid','provincia'=>'Madrid','iban'=>'','observaciones'=>'']);
            $log[] = "✅ 6 inquilinos creados";

            // ── Contratos ─────────────────────────────────────────
            // revision='IPC' en contratos con inicio en junio → aparecerán en el alerta del dashboard cada junio
            $c1 = insertRow($pdo,'contratos',['inmueble_id'=>$i1,'inquilino_id'=>$q1,'fecha_inicio'=>'2024-01-01','fecha_fin'=>'2025-12-31','duracion_anos'=>2,'duracion_unidad'=>'anos','aviso_recibo'=>1,'renta_base'=>700,'iva_pct'=>0,'irpf_pct'=>0,'fianza'=>1400,'dia_pago'=>5,'estado'=>'activo','revision'=>'Fija','observaciones'=>'Contrato de 2 años']);
            $c2 = insertRow($pdo,'contratos',['inmueble_id'=>$i2,'inquilino_id'=>$q2,'fecha_inicio'=>'2024-03-01','fecha_fin'=>'2025-02-28','duracion_anos'=>1,'duracion_unidad'=>'anos','aviso_recibo'=>1,'renta_base'=>650,'iva_pct'=>0,'irpf_pct'=>0,'fianza'=>1300,'dia_pago'=>5,'estado'=>'activo','revision'=>'Sin revision','observaciones'=>'']);
            $c3 = insertRow($pdo,'contratos',['inmueble_id'=>$i3,'inquilino_id'=>$q3,'fecha_inicio'=>'2023-06-01','fecha_fin'=>'2024-05-31','duracion_anos'=>1,'duracion_unidad'=>'anos','aviso_recibo'=>1,'renta_base'=>720,'iva_pct'=>0,'irpf_pct'=>0,'fianza'=>1440,'dia_pago'=>5,'estado'=>'activo','revision'=>'IPC','observaciones'=>'Renovado automáticamente']);
            $c4 = insertRow($pdo,'contratos',['inmueble_id'=>$i4,'inquilino_id'=>$q4,'fecha_inicio'=>'2024-09-01','fecha_fin'=>'2025-08-31','duracion_anos'=>1,'duracion_unidad'=>'anos','aviso_recibo'=>1,'renta_base'=>680,'iva_pct'=>0,'irpf_pct'=>0,'fianza'=>1360,'dia_pago'=>5,'estado'=>'activo','revision'=>'Sin revision','observaciones'=>'']);
            $c5 = insertRow($pdo,'contratos',['inmueble_id'=>$i5,'inquilino_id'=>$q5,'fecha_inicio'=>'2022-01-01','fecha_fin'=>'2024-12-31','duracion_anos'=>3,'duracion_unidad'=>'anos','aviso_recibo'=>1,'renta_base'=>1200,'iva_pct'=>21,'irpf_pct'=>0,'fianza'=>2400,'dia_pago'=>5,'estado'=>'activo','revision'=>'Fija','observaciones'=>'Local comercial IVA 21%']);
            $c6 = insertRow($pdo,'contratos',['inmueble_id'=>$i6,'inquilino_id'=>$q6,'fecha_inicio'=>'2024-06-01','fecha_fin'=>'2025-05-31','duracion_anos'=>1,'duracion_unidad'=>'anos','aviso_recibo'=>1,'renta_base'=>800,'iva_pct'=>0,'irpf_pct'=>0,'fianza'=>1600,'dia_pago'=>5,'estado'=>'activo','revision'=>'IPC','observaciones'=>'']);
            $log[] = "✅ 6 contratos activos creados";

            // ── Recibos (últimos 5 meses + estado variado) ───────
            // La numeración es mensual: el contador se reinicia cada mes.
            // doc_secuencias se pobla automáticamente al final del bloque.
            $contrData = [
                [$c1,$q1,$i1,700,0,0],[$c2,$q2,$i2,650,0,0],[$c3,$q3,$i3,720,0,0],
                [$c4,$q4,$i4,680,0,0],[$c5,$q5,$i5,1200,21,0],[$c6,$q6,$i6,800,0,0]
            ];
            $now  = new DateTime();
            $recCount = 0;
            for ($back = 4; $back >= 0; $back--) {
                $d   = (clone $now)->modify("-$back month");
                $mes = (int)$d->format('n');
                $yr  = (int)$d->format('Y');
                $periodo = $meses[$mes-1] . ' ' . $yr;
                $seqMes  = 1; // reinicio mensual para datos de ejemplo
                foreach ($contrData as [$cid,$qid,$iid,$renta,$iva_pct,$irpf_pct]) {
                    $num       = 'REC-' . $yr . str_pad($mes,2,'0',STR_PAD_LEFT) . '-' . str_pad($seqMes,5,'0',STR_PAD_LEFT);
                    $imp_iva   = round($renta * $iva_pct / 100, 2);
                    $imp_irpf  = round($renta * $irpf_pct / 100, 2);
                    $total     = $renta + $imp_iva - $imp_irpf;
                    $estado = 'pendiente'; $pagos = []; $pagado = 0;
                    if ($back >= 2) {
                        $estado = 'cobrado';
                        $pagos  = [['fecha'=>$yr.'-'.str_pad($mes,2,'0',STR_PAD_LEFT).'-05','importe'=>$total,'metodo'=>'transferencia','cuenta'=>'ES91 2100 0418']];
                        $pagado = $total;
                    } elseif ($back === 1 && $iid === $i3) {
                        $estado = 'parcial';
                        $pagos  = [['fecha'=>$yr.'-'.str_pad($mes,2,'0',STR_PAD_LEFT).'-10','importe'=>round($total/2,2),'metodo'=>'transferencia','cuenta'=>'ES91 2100 0418']];
                        $pagado = round($total/2,2);
                    }
                    insertRow($pdo,'recibos',[
                        'contrato_id'=>$cid,'inquilino_id'=>$qid,'inmueble_id'=>$iid,
                        'numero_recibo'=>$num,'numero_seq'=>$seqMes,
                        'fecha_emision'=>$yr.'-'.str_pad($mes,2,'0',STR_PAD_LEFT).'-01',
                        'fecha_limite'=>$yr.'-'.str_pad($mes,2,'0',STR_PAD_LEFT).'-05',
                        'concepto_periodo'=>$periodo,'renta_base'=>$renta,
                        'importe_iva'=>$imp_iva,'importe_irpf'=>$imp_irpf,'importe_total'=>$total,
                        'importe_pagado'=>$pagado,'pagos'=>$pagos,
                        'estado'=>$estado,'fecha_creacion'=>date('c')
                    ]);
                    $seqMes++; $recCount++;
                }
            }
            $log[] = "✅ $recCount recibos de ejemplo creados";

            // ── Datos de ejemplo adicionales (inventados) ──────────────────────
            // Cubren casos que el bloque anterior no probaba: facturas normales,
            // facturas rectificativas (RET), recibos anulados con y sin factura,
            // recibos rectificativos (RER), un contrato finalizado y un salto de
            // año en la numeración mensual. Nombres y direcciones inventados;
            // no reutilizan los datos del SQL de referencia de la raíz del proyecto.
            $p3 = insertRow($pdo,'propietarios',['nombre'=>'Ruiz Delgado, Francisco','nif'=>'66778899Q','telefono'=>'699 222 333','email'=>'francisco.ruiz@email.com','observaciones'=>'Propietario de alta reciente']);
            $f3 = insertRow($pdo,'fincas',['nombre'=>'C/ Alcalá 120','sigla'=>'AL','calle'=>'Alcalá','numero'=>'120','cp'=>'28009','municipio'=>'Madrid','provincia'=>'Madrid','propietario_id'=>$p3,'observaciones'=>'Finca de nueva incorporación']);
            $i7 = insertRow($pdo,'inmuebles',['finca_id'=>$f3,'planta'=>'3º','puerta'=>'A','tipo'=>'vivienda','metros'=>75,'referencia_catastral'=>'','cedula'=>'','observaciones'=>'Reformado en 2025']);
            $i8 = insertRow($pdo,'inmuebles',['finca_id'=>$f3,'planta'=>'3º','puerta'=>'B','tipo'=>'vivienda','metros'=>58,'referencia_catastral'=>'','cedula'=>'','observaciones'=>'']);
            $i9 = insertRow($pdo,'inmuebles',['finca_id'=>$f3,'planta'=>'4º','puerta'=>'A','tipo'=>'vivienda','metros'=>82,'referencia_catastral'=>'','cedula'=>'','observaciones'=>'Ático con terraza']);
            $q7 = insertRow($pdo,'inquilinos',['nombre'=>'Ortega Campos, Beatriz','nif'=>'66112233H','telefono'=>'666 100 200','email'=>'beatriz.ortega@gmail.com','direccion'=>'C/ Alcalá 120 3º A','cp'=>'28009','municipio'=>'Madrid','provincia'=>'Madrid','iban'=>'ES44 3058 0090 1234 5678 9012','observaciones'=>'']);
            $q8 = insertRow($pdo,'inquilinos',['nombre'=>'Navarro Iglesias, Diego','nif'=>'77223344J','telefono'=>'677 200 300','email'=>'diego.navarro@outlook.com','direccion'=>'C/ Alcalá 120 3º B','cp'=>'28009','municipio'=>'Madrid','provincia'=>'Madrid','iban'=>'','observaciones'=>'']);
            $q9 = insertRow($pdo,'inquilinos',['nombre'=>'Castillo Herrera, Marta','nif'=>'88334455K','telefono'=>'688 300 400','email'=>'marta.castillo@gmail.com','direccion'=>'C/ Alcalá 120 4º A','cp'=>'28009','municipio'=>'Madrid','provincia'=>'Madrid','iban'=>'ES77 0049 1500 0512 3456 7890','observaciones'=>'Contrato finalizado, referencia histórica']);
            $log[] = "✅ 1 propietario, 1 finca, 3 inmuebles y 3 inquilinos adicionales creados";

            $c7 = insertRow($pdo,'contratos',['inmueble_id'=>$i7,'inquilino_id'=>$q7,'fecha_inicio'=>'2025-11-01','fecha_fin'=>'2026-10-31','duracion_anos'=>1,'duracion_unidad'=>'anos','aviso_recibo'=>1,'renta_base'=>750,'iva_pct'=>0,'irpf_pct'=>0,'fianza'=>1500,'dia_pago'=>1,'estado'=>'activo','revision'=>'IPC','observaciones'=>'Historial con salto de año (dic-2025 a ene-2026)']);
            $c8 = insertRow($pdo,'contratos',['inmueble_id'=>$i8,'inquilino_id'=>$q8,'fecha_inicio'=>'2025-12-01','fecha_fin'=>'2026-11-30','duracion_anos'=>1,'duracion_unidad'=>'anos','aviso_recibo'=>1,'renta_base'=>620,'iva_pct'=>0,'irpf_pct'=>0,'fianza'=>1240,'dia_pago'=>1,'estado'=>'activo','revision'=>'Sin revision','observaciones'=>'Incluye factura rectificativa (RET) y recibo rectificativo (RER) de ejemplo']);
            $c9 = insertRow($pdo,'contratos',['inmueble_id'=>$i9,'inquilino_id'=>$q9,'fecha_inicio'=>'2024-06-01','fecha_fin'=>'2025-12-31','duracion_anos'=>1,'duracion_unidad'=>'anos','aviso_recibo'=>1,'renta_base'=>900,'iva_pct'=>0,'irpf_pct'=>0,'fianza'=>1800,'dia_pago'=>1,'estado'=>'finalizado','revision'=>'Fija','fecha_baja'=>'2025-12-31','motivo_baja'=>'Fin de contrato','observaciones'=>'Contrato finalizado usado como ejemplo histórico']);
            $log[] = "✅ 3 contratos adicionales creados (incl. 1 finalizado/dado de baja)";

            // Recibos con fechas fijas (no relativas a "hoy") para que el salto de
            // año en la numeración mensual sea siempre reproducible al reinstalar.
            $r_c9_dic = insertRow($pdo,'recibos',['contrato_id'=>$c9,'inquilino_id'=>$q9,'inmueble_id'=>$i9,'numero_recibo'=>'REC-202512-00001','numero_seq'=>1,'fecha_emision'=>'2025-12-01','fecha_limite'=>'2025-12-01','concepto_periodo'=>'Diciembre 2025','renta_base'=>900,'importe_iva'=>0,'importe_irpf'=>0,'importe_total'=>900,'importe_pagado'=>900,'pagos'=>[['fecha'=>'2025-12-01','importe'=>900,'metodo'=>'transferencia','cuenta'=>'ES91 2100 0418']],'estado'=>'cobrado','fecha_creacion'=>date('c')]);
            $r_c7_dic = insertRow($pdo,'recibos',['contrato_id'=>$c7,'inquilino_id'=>$q7,'inmueble_id'=>$i7,'numero_recibo'=>'REC-202512-00002','numero_seq'=>2,'fecha_emision'=>'2025-12-01','fecha_limite'=>'2025-12-01','concepto_periodo'=>'Diciembre 2025','renta_base'=>750,'importe_iva'=>0,'importe_irpf'=>0,'importe_total'=>750,'importe_pagado'=>750,'pagos'=>[['fecha'=>'2025-12-01','importe'=>750,'metodo'=>'transferencia','cuenta'=>'ES91 2100 0418']],'estado'=>'cobrado','fecha_creacion'=>date('c')]);
            $r_c7_ene = insertRow($pdo,'recibos',['contrato_id'=>$c7,'inquilino_id'=>$q7,'inmueble_id'=>$i7,'numero_recibo'=>'REC-202601-00001','numero_seq'=>1,'fecha_emision'=>'2026-01-01','fecha_limite'=>'2026-01-01','concepto_periodo'=>'Enero 2026','renta_base'=>750,'importe_iva'=>0,'importe_irpf'=>0,'importe_total'=>750,'importe_pagado'=>750,'pagos'=>[['fecha'=>'2026-01-01','importe'=>750,'metodo'=>'transferencia','cuenta'=>'ES91 2100 0418']],'estado'=>'cobrado','fecha_creacion'=>date('c')]);
            $r_c8_ene = insertRow($pdo,'recibos',['contrato_id'=>$c8,'inquilino_id'=>$q8,'inmueble_id'=>$i8,'numero_recibo'=>'REC-202601-00002','numero_seq'=>2,'fecha_emision'=>'2026-01-01','fecha_limite'=>'2026-01-01','concepto_periodo'=>'Enero 2026','renta_base'=>620,'importe_iva'=>0,'importe_irpf'=>0,'importe_total'=>620,'importe_pagado'=>620,'pagos'=>[['fecha'=>'2026-01-01','importe'=>620,'metodo'=>'transferencia','cuenta'=>'ES91 2100 0418']],'estado'=>'cobrado','fecha_creacion'=>date('c')]);
            $r_c7_feb = insertRow($pdo,'recibos',['contrato_id'=>$c7,'inquilino_id'=>$q7,'inmueble_id'=>$i7,'numero_recibo'=>'REC-202602-00001','numero_seq'=>1,'fecha_emision'=>'2026-02-01','fecha_limite'=>'2026-02-01','concepto_periodo'=>'Febrero 2026','renta_base'=>750,'importe_iva'=>0,'importe_irpf'=>0,'importe_total'=>750,'importe_pagado'=>750,'pagos'=>[['fecha'=>'2026-02-01','importe'=>750,'metodo'=>'transferencia','cuenta'=>'ES91 2100 0418']],'estado'=>'cobrado','fecha_creacion'=>date('c')]);
            $r_c8_feb = insertRow($pdo,'recibos',['contrato_id'=>$c8,'inquilino_id'=>$q8,'inmueble_id'=>$i8,'numero_recibo'=>'REC-202602-00002','numero_seq'=>2,'fecha_emision'=>'2026-02-01','fecha_limite'=>'2026-02-01','concepto_periodo'=>'Febrero 2026','renta_base'=>620,'importe_iva'=>0,'importe_irpf'=>0,'importe_total'=>620,'importe_pagado'=>0,'pagos'=>[],'estado'=>'pendiente','fecha_creacion'=>date('c')]);
            $log[] = "✅ 6 recibos adicionales creados (incl. salto de año dic-2025 → ene-2026)";

            // Factura normal para el recibo de febrero de Ortega Campos → el recibo
            // se anula después. La factura permanece "emitida": la rectificación
            // fiscal es un acto explícito sobre la factura, no automático desde el recibo.
            $fac_c7 = insertRow($pdo,'facturas',[
                'recibo_id'=>$r_c7_feb,'contrato_id'=>$c7,'inquilino_id'=>$q7,'inmueble_id'=>$i7,
                'numero_factura'=>'FAC-202602-00001','numero_seq'=>1,'serie'=>'FAC','tipo_factura'=>'F1',
                'fecha_emision'=>'2026-02-05','fecha_operacion'=>'2026-02-01',
                'periodo_desde'=>'2026-02-01','periodo_hasta'=>'2026-02-28',
                'emisor_nombre'=>'Administración de Fincas López','emisor_nif'=>'B12345678',
                'emisor_direccion'=>'C/ Gran Vía 10, 2º B','emisor_cp'=>'28013','emisor_municipio'=>'Madrid','emisor_provincia'=>'Madrid',
                'emisor_email'=>'admin@fincaslopez.es','emisor_telefono'=>'91 555 1234','emisor_iban'=>'ES91 2100 0418 4502 0005 1332',
                'cliente_nombre'=>'Ortega Campos, Beatriz','cliente_nif'=>'66112233H','cliente_direccion'=>'C/ Alcalá 120 3º A',
                'cliente_cp'=>'28009','cliente_municipio'=>'Madrid','cliente_provincia'=>'Madrid','cliente_email'=>'beatriz.ortega@gmail.com',
                'inmueble_direccion'=>'Alcalá 120 3º A, CP 28009, Madrid',
                'concepto'=>'Alquiler del inmueble — Febrero 2026','conceptos_extra'=>'','notas'=>'',
                'base_imponible'=>750,'iva_pct'=>0,'importe_iva'=>0,'irpf_pct'=>0,'importe_irpf'=>0,'importe_total'=>750,
                'estado'=>'emitida','hash_factura'=>null,'hash_anterior'=>null,'qr_url'=>null,
                'verifactu_estado'=>'no_enviado','verifactu_respuesta'=>null,'factura_rectificada_id'=>null,
                'fecha_creacion'=>date('c'),
            ]);
            // Ejemplo "recibo anulado CON factura": el recibo queda anulado lógicamente,
            // la factura FAC-202602-00001 permanece emitida.
            $pdo->prepare("UPDATE recibos SET estado='anulado', factura_id=?, notas=? WHERE id=?")
                ->execute([$fac_c7, 'Recibo anulado. La factura FAC-202602-00001 sigue emitida; revisar en Facturas si procede rectificarla.', $r_c7_feb]);
            $log[] = "✅ Ejemplo: recibo anulado con factura emitida (FAC-202602-00001)";

            // Factura normal para el recibo de enero de Navarro Iglesias → se rectifica
            // para demostrar la nueva nomenclatura de facturas rectificativas (RET).
            $fac_c8 = insertRow($pdo,'facturas',[
                'recibo_id'=>$r_c8_ene,'contrato_id'=>$c8,'inquilino_id'=>$q8,'inmueble_id'=>$i8,
                'numero_factura'=>'FAC-202601-00001','numero_seq'=>1,'serie'=>'FAC','tipo_factura'=>'F1',
                'fecha_emision'=>'2026-01-05','fecha_operacion'=>'2026-01-01',
                'periodo_desde'=>'2026-01-01','periodo_hasta'=>'2026-01-31',
                'emisor_nombre'=>'Administración de Fincas López','emisor_nif'=>'B12345678',
                'emisor_direccion'=>'C/ Gran Vía 10, 2º B','emisor_cp'=>'28013','emisor_municipio'=>'Madrid','emisor_provincia'=>'Madrid',
                'emisor_email'=>'admin@fincaslopez.es','emisor_telefono'=>'91 555 1234','emisor_iban'=>'ES91 2100 0418 4502 0005 1332',
                'cliente_nombre'=>'Navarro Iglesias, Diego','cliente_nif'=>'77223344J','cliente_direccion'=>'C/ Alcalá 120 3º B',
                'cliente_cp'=>'28009','cliente_municipio'=>'Madrid','cliente_provincia'=>'Madrid','cliente_email'=>'diego.navarro@outlook.com',
                'inmueble_direccion'=>'Alcalá 120 3º B, CP 28009, Madrid',
                'concepto'=>'Alquiler del inmueble — Enero 2026','conceptos_extra'=>'','notas'=>'',
                'base_imponible'=>620,'iva_pct'=>0,'importe_iva'=>0,'irpf_pct'=>0,'importe_irpf'=>0,'importe_total'=>620,
                'estado'=>'rectificada','hash_factura'=>null,'hash_anterior'=>null,'qr_url'=>null,
                'verifactu_estado'=>'no_enviado','verifactu_respuesta'=>null,'factura_rectificada_id'=>null,
                'fecha_creacion'=>date('c'),
            ]);
            $hoy = date('Y-m-d'); $periodoHoy = date('Ym');
            $fac_ret = insertRow($pdo,'facturas',[
                'recibo_id'=>null,'contrato_id'=>$c8,'inquilino_id'=>$q8,'inmueble_id'=>$i8,
                'numero_factura'=>'RET-'.$periodoHoy.'-00001','numero_seq'=>1,'serie'=>'RET','tipo_factura'=>'R1',
                'fecha_emision'=>$hoy,'fecha_operacion'=>'2026-01-05',
                'periodo_desde'=>'2026-01-01','periodo_hasta'=>'2026-01-31',
                'emisor_nombre'=>'Administración de Fincas López','emisor_nif'=>'B12345678',
                'emisor_direccion'=>'C/ Gran Vía 10, 2º B','emisor_cp'=>'28013','emisor_municipio'=>'Madrid','emisor_provincia'=>'Madrid',
                'emisor_email'=>'admin@fincaslopez.es','emisor_telefono'=>'91 555 1234','emisor_iban'=>'ES91 2100 0418 4502 0005 1332',
                'cliente_nombre'=>'Navarro Iglesias, Diego','cliente_nif'=>'77223344J','cliente_direccion'=>'C/ Alcalá 120 3º B',
                'cliente_cp'=>'28009','cliente_municipio'=>'Madrid','cliente_provincia'=>'Madrid','cliente_email'=>'diego.navarro@outlook.com',
                'inmueble_direccion'=>'Alcalá 120 3º B, CP 28009, Madrid',
                'concepto'=>'Rectificación de: Alquiler del inmueble — Enero 2026','conceptos_extra'=>'',
                'notas'=>'Factura rectificativa de FAC-202601-00001. Anulación total.',
                'base_imponible'=>-620,'iva_pct'=>0,'importe_iva'=>0,'irpf_pct'=>0,'importe_irpf'=>0,'importe_total'=>-620,
                'estado'=>'emitida','hash_factura'=>null,'hash_anterior'=>null,'qr_url'=>null,
                'verifactu_estado'=>'no_enviado','verifactu_respuesta'=>null,'factura_rectificada_id'=>$fac_c8,
                'fecha_creacion'=>date('c'),
            ]);
            $pdo->prepare("UPDATE facturas SET notas=? WHERE id=?")
                ->execute(['Rectificada por: RET-'.$periodoHoy.'-00001 · emitida el '.$hoy.'.', $fac_c8]);
            $pdo->prepare("UPDATE recibos SET factura_id=? WHERE id=?")->execute([$fac_c8, $r_c8_ene]);
            $log[] = "✅ Ejemplo: factura rectificativa RET-$periodoHoy-00001 (rectifica FAC-202601-00001)";

            // Ejemplo "recibo anulado SIN factura": se anula el recibo de febrero de
            // Navarro Iglesias y se genera su recibo rectificativo (nueva nomenclatura RER).
            $rer_num = 'RER-'.$periodoHoy.'-00001';
            $r_rer = insertRow($pdo,'recibos',[
                'contrato_id'=>$c8,'inquilino_id'=>$q8,'inmueble_id'=>$i8,
                'numero_recibo'=>$rer_num,'numero_seq'=>1,
                'fecha_emision'=>$hoy,'fecha_limite'=>$hoy,
                'concepto_periodo'=>'Rectificación de: Febrero 2026','renta_base'=>-620,
                'importe_iva'=>0,'importe_irpf'=>0,'importe_total'=>-620,'importe_pagado'=>0,'pagos'=>[],
                'estado'=>'rectificativo','recibo_rectificado_id'=>$r_c8_feb,
                'notas'=>'Recibo rectificativo de REC-202602-00002. Anulación total.',
                'fecha_creacion'=>date('c'),
            ]);
            $pdo->prepare("UPDATE recibos SET estado='anulado', notas=? WHERE id=?")
                ->execute(['Rectificado por: '.$rer_num.' · emitido el '.$hoy.'.', $r_c8_feb]);
            $log[] = "✅ Ejemplo: recibo anulado sin factura + recibo rectificativo $rer_num";

            // ── Datos de ejemplo: revisión de renta (IPC/IRAV) ─────────────────
            // Fechas calculadas en el momento de instalar (no fijas) para que el aviso
            // de revisión pendiente sea comprobable justo después de instalar, sin
            // depender del mes en que se ejecute el instalador. La detección real la
            // hace contratosIPCPendientes() en extras.js: contrato activo, revision
            // IPC/IRAV, mes de fecha_inicio = mes actual, año de fecha_inicio < año
            // actual, e ipc_anio_aplicado distinto del año actual.
            $hoyRev        = new DateTime(); $hoyRev->setTime(0, 0, 0);
            $anioActualRev = (int)$hoyRev->format('Y');
            $mesActualRev  = (int)$hoyRev->format('n');
            $fechaFinRev   = (clone $hoyRev)->modify('+2 years')->format('Y-m-d');

            // Aniversario "próximo": dentro de los próximos 30 días naturales (ventana usada
            // por getAvisosRevision() en notificaciones.js) pero en un mes distinto al actual,
            // para no disparar también el aviso naranja de "pendiente" (que exige mismo mes).
            $anivProxima = (clone $hoyRev)->modify('+25 days');
            if ((int)$anivProxima->format('n') === $mesActualRev) {
                $anivProxima = (clone $hoyRev)->modify('first day of next month')->modify('+3 days');
            }
            $fiProxima = new DateTime();
            $fiProxima->setDate($anioActualRev - 4, (int)$anivProxima->format('n'), (int)$anivProxima->format('j'));
            $fiProxima = $fiProxima->format('Y-m-d');

            // Aniversario "lejano": a más de 30 días y en un mes distinto al actual → no debe
            // generar ningún aviso (ni "pendiente" ni "próxima"), solo aparece en la tabla
            // general de "Próximas revisiones anuales" del Dashboard con muchos días restantes.
            $anivLejana = (clone $hoyRev)->modify('+6 months');
            $fiLejana = new DateTime();
            $fiLejana->setDate($anioActualRev - 5, (int)$anivLejana->format('n'), (int)$anivLejana->format('j'));
            $fiLejana = $fiLejana->format('Y-m-d');

            $p4 = insertRow($pdo,'propietarios',['nombre'=>'Molina Vidal, Teresa','nif'=>'99001122X','telefono'=>'699 555 111','email'=>'teresa.molina@email.com','observaciones'=>'Propietaria — cartera con contratos indexados a IPC/IRAV']);
            $f4 = insertRow($pdo,'fincas',['nombre'=>'C/ Velázquez 22','sigla'=>'VZ','calle'=>'Velázquez','numero'=>'22','cp'=>'28006','municipio'=>'Madrid','provincia'=>'Madrid','propietario_id'=>$p4,'observaciones'=>'Finca con varios contratos sujetos a revisión anual de renta']);
            $i10 = insertRow($pdo,'inmuebles',['finca_id'=>$f4,'planta'=>'1º','puerta'=>'A','tipo'=>'vivienda','metros'=>72,'referencia_catastral'=>'','cedula'=>'','observaciones'=>'Revisión IPC pendiente de aplicar']);
            $i11 = insertRow($pdo,'inmuebles',['finca_id'=>$f4,'planta'=>'1º','puerta'=>'B','tipo'=>'vivienda','metros'=>66,'referencia_catastral'=>'','cedula'=>'','observaciones'=>'Revisión IRAV próxima (dentro de 30 días)']);
            $i12 = insertRow($pdo,'inmuebles',['finca_id'=>$f4,'planta'=>'2º','puerta'=>'A','tipo'=>'vivienda','metros'=>78,'referencia_catastral'=>'','cedula'=>'','observaciones'=>'Revisión IPC ya aplicada este año']);
            $i13 = insertRow($pdo,'inmuebles',['finca_id'=>$f4,'planta'=>'2º','puerta'=>'B','tipo'=>'vivienda','metros'=>64,'referencia_catastral'=>'','cedula'=>'','observaciones'=>'Revisión IRAV lejana, fuera de ventana de aviso']);
            $q10 = insertRow($pdo,'inquilinos',['nombre'=>'Delgado Núñez, Cristina','nif'=>'91112233L','telefono'=>'611 900 100','email'=>'cristina.delgado@gmail.com','direccion'=>'C/ Velázquez 22 1º A','cp'=>'28006','municipio'=>'Madrid','provincia'=>'Madrid','iban'=>'','observaciones'=>'']);
            $q11 = insertRow($pdo,'inquilinos',['nombre'=>'Vázquez Roldán, Ismael','nif'=>'92223344M','telefono'=>'622 900 200','email'=>'ismael.vazquez@gmail.com','direccion'=>'C/ Velázquez 22 1º B','cp'=>'28006','municipio'=>'Madrid','provincia'=>'Madrid','iban'=>'','observaciones'=>'']);
            $q12 = insertRow($pdo,'inquilinos',['nombre'=>'Bravo Cano, Nuria','nif'=>'93334455N','telefono'=>'633 900 300','email'=>'nuria.bravo@gmail.com','direccion'=>'C/ Velázquez 22 2º A','cp'=>'28006','municipio'=>'Madrid','provincia'=>'Madrid','iban'=>'','observaciones'=>'']);
            $q13 = insertRow($pdo,'inquilinos',['nombre'=>'Serra Montoya, Álex','nif'=>'94445566P','telefono'=>'644 900 400','email'=>'alex.serra@gmail.com','direccion'=>'C/ Velázquez 22 2º B','cp'=>'28006','municipio'=>'Madrid','provincia'=>'Madrid','iban'=>'','observaciones'=>'']);
            $log[] = "✅ 1 propietario, 1 finca, 4 inmuebles y 4 inquilinos de ejemplo creados (escenarios de revisión de renta)";

            // 1) PENDIENTE: mismo mes/día que hoy, contrato iniciado hace 3 años, sin aplicar aún
            $c10 = insertRow($pdo,'contratos',['inmueble_id'=>$i10,'inquilino_id'=>$q10,'fecha_inicio'=>(clone $hoyRev)->modify('-3 years')->format('Y-m-d'),'fecha_fin'=>$fechaFinRev,'duracion_anos'=>3,'duracion_unidad'=>'anos','aviso_recibo'=>1,'renta_base'=>710,'iva_pct'=>0,'irpf_pct'=>0,'fianza'=>1420,'dia_pago'=>5,'estado'=>'activo','revision'=>'IPC','ipc_anio_aplicado'=>null,'observaciones'=>'Ejemplo: revisión IPC pendiente — aparece en el aviso del Dashboard este mes']);
            // 2) PRÓXIMA: aniversario dentro de 30 días, en un mes distinto al actual (no dispara el aviso "pendiente")
            $c11 = insertRow($pdo,'contratos',['inmueble_id'=>$i11,'inquilino_id'=>$q11,'fecha_inicio'=>$fiProxima,'fecha_fin'=>$fechaFinRev,'duracion_anos'=>4,'duracion_unidad'=>'anos','aviso_recibo'=>1,'renta_base'=>640,'iva_pct'=>0,'irpf_pct'=>0,'fianza'=>1280,'dia_pago'=>5,'estado'=>'activo','revision'=>'IRAV','ipc_anio_aplicado'=>null,'observaciones'=>'Ejemplo: revisión IRAV próxima — aniversario dentro de 30 días, aún no corresponde este mes']);
            // 3) YA APLICADA: mismo mes/día que hoy, contrato iniciado hace 2 años, revisión ya aplicada este año → no debe reaparecer como pendiente
            $rentaAnteriorC12 = 750;
            $rentaNuevaC12    = 772.5;
            $c12 = insertRow($pdo,'contratos',['inmueble_id'=>$i12,'inquilino_id'=>$q12,'fecha_inicio'=>(clone $hoyRev)->modify('-2 years')->format('Y-m-d'),'fecha_fin'=>$fechaFinRev,'duracion_anos'=>2,'duracion_unidad'=>'anos','aviso_recibo'=>1,'renta_base'=>$rentaNuevaC12,'iva_pct'=>0,'irpf_pct'=>0,'fianza'=>1500,'dia_pago'=>5,'estado'=>'activo','revision'=>'IPC','ipc_anio_aplicado'=>$anioActualRev,'observaciones'=>'Ejemplo: revisión IPC ya aplicada este año — no debe aparecer como pendiente']);
            insertRow($pdo,'historial_rentas',['contrato_id'=>$c12,'fecha'=>(clone $hoyRev)->modify('-5 days')->format('Y-m-d'),'tipo_revision'=>'IPC','porcentaje'=>3.0,'renta_anterior'=>$rentaAnteriorC12,'renta_nueva'=>$rentaNuevaC12,'observaciones'=>'Revisión IPC aplicada (dato de ejemplo)']);
            // 4) LEJANA: aniversario a más de 30 días, mes distinto al actual → no debe generar ningún aviso
            $c13 = insertRow($pdo,'contratos',['inmueble_id'=>$i13,'inquilino_id'=>$q13,'fecha_inicio'=>$fiLejana,'fecha_fin'=>$fechaFinRev,'duracion_anos'=>5,'duracion_unidad'=>'anos','aviso_recibo'=>1,'renta_base'=>590,'iva_pct'=>0,'irpf_pct'=>0,'fianza'=>1180,'dia_pago'=>5,'estado'=>'activo','revision'=>'IRAV','ipc_anio_aplicado'=>null,'observaciones'=>'Ejemplo: revisión IRAV lejana — no debe aparecer en ningún aviso']);
            $log[] = "✅ 4 contratos de ejemplo creados (revisión pendiente, próxima, ya aplicada y lejana)";

            // ── Datos de ejemplo: inquilinos secundarios (contratos multi-inquilino) ──
            // contratos_inq_sec no está enlazada a la tabla inquilinos: son campos libres
            // (nombre, nif, dirección, teléfono, email) que se copian en la ficha del
            // contrato y en el bloque {{#INQUILINOS_SECUNDARIOS}} de las plantillas DOCX.
            // El inquilino principal sigue siendo inquilino_id del contrato; el secundario
            // nunca lo sustituye.
            $p5 = insertRow($pdo,'propietarios',['nombre'=>'Santos Prieto, Álvaro','nif'=>'95556677Q','telefono'=>'655 777 888','email'=>'alvaro.santos@email.com','observaciones'=>'Propietario — cartera con contratos multi-inquilino']);
            $f5 = insertRow($pdo,'fincas',['nombre'=>'C/ Goya 30','sigla'=>'GY','calle'=>'Goya','numero'=>'30','cp'=>'28001','municipio'=>'Madrid','provincia'=>'Madrid','propietario_id'=>$p5,'observaciones'=>'Finca con contratos de uno y varios inquilinos, para comparar']);
            $i14 = insertRow($pdo,'inmuebles',['finca_id'=>$f5,'planta'=>'BAJO','puerta'=>'A','tipo'=>'vivienda','metros'=>60,'referencia_catastral'=>'','cedula'=>'','observaciones'=>'Contrato de referencia con un solo inquilino']);
            $i15 = insertRow($pdo,'inmuebles',['finca_id'=>$f5,'planta'=>'1º','puerta'=>'A','tipo'=>'vivienda','metros'=>85,'referencia_catastral'=>'','cedula'=>'','observaciones'=>'Contrato con un inquilino principal y un inquilino secundario']);
            $i16 = insertRow($pdo,'inmuebles',['finca_id'=>$f5,'planta'=>'1º','puerta'=>'B','tipo'=>'vivienda','metros'=>95,'referencia_catastral'=>'','cedula'=>'','observaciones'=>'Contrato con un inquilino principal y dos inquilinos secundarios']);
            $q14 = insertRow($pdo,'inquilinos',['nombre'=>'Pardo Segura, Lucía','nif'=>'96667788R','telefono'=>'666 800 100','email'=>'lucia.pardo@gmail.com','direccion'=>'C/ Goya 30 Bajo A','cp'=>'28001','municipio'=>'Madrid','provincia'=>'Madrid','iban'=>'','observaciones'=>'Inquilina única — contrato de referencia sin inquilinos secundarios']);
            $q15 = insertRow($pdo,'inquilinos',['nombre'=>'Cabrera Morales, Rubén','nif'=>'97778899S','telefono'=>'677 800 200','email'=>'ruben.cabrera@gmail.com','direccion'=>'C/ Goya 30 1º A','cp'=>'28001','municipio'=>'Madrid','provincia'=>'Madrid','iban'=>'','observaciones'=>'Inquilino principal — contrato con 1 inquilino secundario']);
            $q16 = insertRow($pdo,'inquilinos',['nombre'=>'Iglesias Farto, Patricia','nif'=>'98889900T','telefono'=>'688 800 300','email'=>'patricia.iglesias@gmail.com','direccion'=>'C/ Goya 30 1º B','cp'=>'28001','municipio'=>'Madrid','provincia'=>'Madrid','iban'=>'','observaciones'=>'Inquilina principal — contrato con 2 inquilinos secundarios']);
            $log[] = "✅ 1 propietario, 1 finca, 3 inmuebles y 3 inquilinos principales de ejemplo creados (escenarios multi-inquilino)";

            $fiMulti = (clone $hoyRev)->modify('-6 months')->format('Y-m-d');
            $c14 = insertRow($pdo,'contratos',['inmueble_id'=>$i14,'inquilino_id'=>$q14,'fecha_inicio'=>$fiMulti,'fecha_fin'=>$fechaFinRev,'duracion_anos'=>2,'duracion_unidad'=>'anos','aviso_recibo'=>1,'renta_base'=>600,'iva_pct'=>0,'irpf_pct'=>0,'fianza'=>1200,'dia_pago'=>5,'estado'=>'activo','revision'=>'Sin revision','observaciones'=>'Ejemplo: contrato normal con un solo inquilino, sin secundarios (para comparar)']);
            $c15 = insertRow($pdo,'contratos',['inmueble_id'=>$i15,'inquilino_id'=>$q15,'fecha_inicio'=>$fiMulti,'fecha_fin'=>$fechaFinRev,'duracion_anos'=>2,'duracion_unidad'=>'anos','aviso_recibo'=>1,'renta_base'=>850,'iva_pct'=>0,'irpf_pct'=>0,'fianza'=>1700,'dia_pago'=>5,'estado'=>'activo','revision'=>'Sin revision','observaciones'=>'Ejemplo: contrato con 1 inquilino principal + 1 inquilino secundario']);
            $c16 = insertRow($pdo,'contratos',['inmueble_id'=>$i16,'inquilino_id'=>$q16,'fecha_inicio'=>$fiMulti,'fecha_fin'=>$fechaFinRev,'duracion_anos'=>2,'duracion_unidad'=>'anos','aviso_recibo'=>1,'renta_base'=>950,'iva_pct'=>0,'irpf_pct'=>0,'fianza'=>1900,'dia_pago'=>5,'estado'=>'activo','revision'=>'Sin revision','observaciones'=>'Ejemplo: contrato con 1 inquilino principal + 2 inquilinos secundarios']);
            $log[] = "✅ 3 contratos de ejemplo creados (1 solo inquilino, +1 secundario, +2 secundarios)";

            insertRow($pdo,'contratos_inq_sec',['contrato_id'=>$c15,'nombre'=>'Cabrera Morales, Sonia','nif'=>'97778800U','direccion'=>'C/ Goya 30 1º A','telefono'=>'677 800 250','email'=>'sonia.cabrera@gmail.com','orden'=>1]);
            insertRow($pdo,'contratos_inq_sec',['contrato_id'=>$c16,'nombre'=>'Iglesias Farto, Marcos','nif'=>'98889901V','direccion'=>'C/ Goya 30 1º B','telefono'=>'688 800 350','email'=>'marcos.iglesias@gmail.com','orden'=>1]);
            insertRow($pdo,'contratos_inq_sec',['contrato_id'=>$c16,'nombre'=>'Iglesias Farto, Elena','nif'=>'98889902W','direccion'=>'C/ Goya 30 1º B','telefono'=>'688 800 360','email'=>'elena.iglesias@gmail.com','orden'=>2]);
            $log[] = "✅ 3 inquilinos secundarios de ejemplo creados (1 en el contrato de 2, 2 en el contrato de 3)";

            // Poblar doc_secuencias con los máximos de los datos de ejemplo
            // para que el próximo recibo/factura continúe la secuencia correctamente.
            $pdo->exec("
                INSERT INTO doc_secuencias (tipo, periodo, ultimo)
                SELECT
                  SUBSTRING_INDEX(numero_recibo, '-', 1),
                  SUBSTRING(numero_recibo, LOCATE('-', numero_recibo)+1, 6),
                  MAX(numero_seq)
                FROM recibos
                WHERE numero_recibo REGEXP '^[A-Z]+-[0-9]{6}-'
                GROUP BY
                  SUBSTRING_INDEX(numero_recibo, '-', 1),
                  SUBSTRING(numero_recibo, LOCATE('-', numero_recibo)+1, 6)
                ON DUPLICATE KEY UPDATE ultimo = GREATEST(ultimo, VALUES(ultimo))
            ");
            $pdo->exec("
                INSERT INTO doc_secuencias (tipo, periodo, ultimo)
                SELECT
                  SUBSTRING_INDEX(numero_factura, '-', 1),
                  SUBSTRING(numero_factura, LOCATE('-', numero_factura)+1, 6),
                  MAX(numero_seq)
                FROM facturas
                WHERE numero_factura REGEXP '^[A-Z]+-[0-9]{6}-'
                GROUP BY
                  SUBSTRING_INDEX(numero_factura, '-', 1),
                  SUBSTRING(numero_factura, LOCATE('-', numero_factura)+1, 6)
                ON DUPLICATE KEY UPDATE ultimo = GREATEST(ultimo, VALUES(ultimo))
            ");
            $log[] = "✅ Secuencias de numeración inicializadas en <code>doc_secuencias</code> (recibos y facturas)";
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// ── Bloque HTML reutilizable: ZipArchive + backup + restaurar + instalación ──
// Es el único bloque que puede destruir datos (clean/sample) o sustituirlos
// (restore); solo se invoca para admins (o durante la primera instalación,
// antes de que exista ningún admin al que proteger). Se extrae a una función
// para no duplicar el HTML entre esos dos casos.
function renderBloqueInstalacionCompleta(string $zipStatus, ?array $zipMsg, ?array $restoreMsg): void {
    $zipOk    = ($zipStatus === 'ok');
    $zipClass = $zipOk ? 'zip-ok' : 'zip-bad';
    $csrf     = htmlspecialchars(csrfToken());
    ?>
    <!-- ZipArchive / Excel fix -->
    <div class="zip-section <?= $zipClass ?>">
      <div class="zip-section-title">
        <?= $zipOk ? '✅' : '❌' ?> Excel XLSX (ZipArchive)
        <span style="font-weight:400;font-size:11px;color:<?= $zipOk ? '#166534' : '#991b1b' ?>">
          — <?= $zipOk ? 'activo y funcionando' : 'extensión no disponible' ?>
        </span>
      </div>
      <?php if ($zipMsg): ?>
        <div class="msg-<?= $zipMsg['type'] === 'ok' ? 'ok' : ($zipMsg['type'] === 'restart' ? 'restart' : 'err') ?>">
          <?= $zipMsg['text'] ?>
        </div>
      <?php elseif (!$zipOk): ?>
        <p>La exportación Excel no funcionará hasta activar la extensión <strong>ZipArchive</strong> de PHP. Pulsa el botón para modificar php.ini automáticamente.</p>
        <form method="POST" style="display:inline">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
          <button type="submit" name="mode" value="fixzip" class="btn-fixzip">
            🔧 Activar ZipArchive en php.ini
          </button>
        </form>
        <span style="font-size:11px;color:#6b7280;margin-left:8px">Después reinicia Apache en el Panel de XAMPP</span>
      <?php endif; ?>
    </div>

    <!-- Copia de seguridad -->
    <div class="backup-section">
      <div class="backup-section-title">
        💾 Copia de seguridad
      </div>
      <p>Descarga los datos antes de realizar cualquier instalación. Podrás restaurarlos desde phpMyAdmin si algo va mal.</p>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <form method="POST">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
          <button type="submit" name="mode" value="backup" class="btn-backup">
            ⬇ Completa (estructura + datos)
          </button>
        </form>
        <form method="POST">
          <input type="hidden" name="_csrf" value="<?= $csrf ?>">
          <button type="submit" name="mode" value="backup_data" class="btn-backup-outline">
            ⬇ Solo datos (INSERT)
          </button>
        </form>
      </div>
      <p style="margin-top:8px;font-size:11px;color:#6b7280;margin-bottom:0">
        <strong>Completa</strong>: crea las tablas desde cero + datos. Útil para mover a otro servidor.<br>
        <strong>Solo datos</strong>: solo los INSERT, sin DROP/CREATE. Útil para restaurar datos en una instalación existente.
      </p>
    </div>

    <!-- Restaurar desde SQL -->
    <div class="restore-section">
      <div class="restore-section-title">
        📂 Restaurar base de datos desde archivo SQL
      </div>
      <p>Sube un archivo <strong>.sql</strong> generado desde esta misma pantalla (copia completa o solo datos) para restaurarlo directamente en la base de datos.</p>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="mode" value="restore">
        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
        <div class="restore-file-row">
          <input type="file" name="sql_file" accept=".sql" required>
          <button type="submit" class="btn-restore"
            onclick="return confirm('Se ejecutará el SQL sobre la base de datos actual.\n\nSi el archivo es una copia completa (con DROP TABLE) se borrarán los datos existentes.\n\n¿Continuar?')">
            ⬆ Subir y ejecutar
          </button>
        </div>
      </form>
      <?php if ($restoreMsg): ?>
        <div class="msg-restore-<?= $restoreMsg['type'] === 'ok' ? 'ok' : 'err' ?>">
          <?= $restoreMsg['text'] ?>
        </div>
      <?php endif; ?>
      <p style="margin-top:8px;font-size:11px;color:#3b82f6;margin-bottom:0">
        ⚠ Una copia <strong>completa</strong> (con DROP TABLE) sobreescribirá todos los datos actuales.<br>
        Una copia de <strong>solo datos</strong> (con DELETE + INSERT) también reemplaza los datos pero mantiene la estructura.
      </p>
    </div>

    <div class="install-divider">Instalación — borra todos los datos</div>

    <!-- Aviso de peligro -->
    <div class="danger-banner">
      <div class="danger-banner-title">
        ⚠ ADVERTENCIA: LAS SIGUIENTES OPCIONES BORRAN TODOS LOS DATOS
      </div>
      <p>Cualquiera de los dos botones de instalación eliminará por completo la base de datos actual: propietarios, fincas, inmuebles, inquilinos, contratos y recibos. <strong>Esta acción no puede deshacerse.</strong> Las cuentas de usuario y el historial de actividad NO se borran. Descarga una copia de seguridad antes de continuar.</p>
    </div>

    <div class="mb-3 mt-2 p-3" style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px">
      <label class="form-label fw-semibold">
        Por seguridad, escribe <strong>CONFIRMAR</strong> en el campo para activar los botones de instalación:
      </label>
      <input type="text" id="input-confirmar" class="form-control mt-1" autocomplete="off"
             oninput="var ok=this.value.trim()==='CONFIRMAR';document.getElementById('btn-instalar-clean').disabled=!ok;document.getElementById('btn-instalar-sample').disabled=!ok;"
             placeholder="Escribe CONFIRMAR...">
    </div>
    <form method="POST">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">
      <div class="d-grid gap-3">
        <button id="btn-instalar-clean" name="mode" value="clean" class="btn btn-outline-danger btn-lg" disabled
          onclick="return confirm('⚠ ATENCIÓN\n\nSe borrarán TODOS los datos actuales.\n\n¿Confirmas que quieres continuar con la instalación limpia?')">
          🗄️ Instalación limpia<br>
          <small class="fw-normal">Solo crea las tablas vacías — borra todos los datos existentes</small>
        </button>
        <button id="btn-instalar-sample" name="mode" value="sample" class="btn btn-danger btn-lg" disabled
          onclick="return confirm('⚠ ATENCIÓN\n\nSe borrarán TODOS los datos actuales y se sustituirán por datos de ejemplo.\n\n¿Confirmas que quieres continuar?')">
          📋 Instalación con datos de ejemplo<br>
          <small class="fw-normal">Borra todos los datos e inserta fincas, pisos, inquilinos y recibos de prueba</small>
        </button>
      </div>
    </form>
    <?php
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AlquiGest – Instalación</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.danger-banner {
  background: #fde8e8;
  border: 2px solid #c81e1e;
  border-radius: 10px;
  padding: 14px 18px;
  margin-bottom: 20px;
}
.danger-banner-title {
  font-size: 15px;
  font-weight: 700;
  color: #c81e1e;
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}
.danger-banner p {
  font-size: 13px;
  color: #7f1d1d;
  margin: 0;
}
.backup-section {
  background: #f0fdf4;
  border: 1px solid #86efac;
  border-radius: 10px;
  padding: 16px 18px;
  margin-bottom: 20px;
}
.backup-section-title {
  font-size: 14px;
  font-weight: 700;
  color: #14532d;
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}
.backup-section p {
  font-size: 13px;
  color: #166534;
  margin-bottom: 10px;
}
.btn-backup {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: #057a55;
  color: white;
  font-size: 13px;
  font-weight: 600;
  padding: 9px 18px;
  border-radius: 8px;
  border: none;
  cursor: pointer;
  text-decoration: none;
}
.btn-backup:hover { background: #065f46; color: white; }
.btn-backup-outline {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: white;
  color: #057a55;
  font-size: 13px;
  font-weight: 600;
  padding: 8px 16px;
  border-radius: 8px;
  border: 1px solid #057a55;
  cursor: pointer;
  text-decoration: none;
}
.btn-backup-outline:hover { background: #f0fdf4; color: #057a55; }
.zip-section {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 14px 18px;
  margin-bottom: 16px;
}
.zip-ok    { border-color: #86efac; background: #f0fdf4; }
.zip-bad   { border-color: #fca5a5; background: #fef2f2; }
.zip-section-title {
  font-size: 13px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}
.zip-ok   .zip-section-title { color: #14532d; }
.zip-bad  .zip-section-title { color: #991b1b; }
.zip-section p { font-size: 12px; margin-bottom: 8px; color: #374151; }
.btn-fixzip {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  background: #1a56db;
  color: white;
  font-size: 12px;
  font-weight: 600;
  padding: 7px 14px;
  border-radius: 7px;
  border: none;
  cursor: pointer;
}
.btn-fixzip:hover { background: #1242a8; }
.msg-restart { background: #fef3c7; border:1px solid #fcd34d; border-radius:7px; padding:10px 12px; font-size:12px; color:#92400e; margin-top:8px; }
.msg-ok      { background: #d1fae5; border:1px solid #6ee7b7; border-radius:7px; padding:10px 12px; font-size:12px; color:#065f46; margin-top:8px; }
.msg-err     { background: #fee2e2; border:1px solid #fca5a5; border-radius:7px; padding:10px 12px; font-size:12px; color:#991b1b; margin-top:8px; }
.restore-section {
  background: #eff6ff;
  border: 1px solid #93c5fd;
  border-radius: 10px;
  padding: 16px 18px;
  margin-bottom: 20px;
}
.restore-section-title {
  font-size: 14px;
  font-weight: 700;
  color: #1e3a8a;
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 6px;
}
.restore-section p { font-size: 13px; color: #1e40af; margin-bottom: 10px; }
.restore-file-row { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.restore-file-row input[type=file] {
  font-size:13px; border:1px solid #93c5fd; border-radius:7px;
  padding:6px 10px; background:white; color:#1e3a8a; flex:1; min-width:0;
}
.btn-restore {
  display: inline-flex; align-items: center; gap: 7px;
  background: #1a56db; color: white; font-size: 13px; font-weight: 600;
  padding: 9px 16px; border-radius: 8px; border: none; cursor: pointer; white-space:nowrap;
}
.btn-restore:hover { background: #1242a8; }
.msg-restore-ok  { background:#d1fae5; border:1px solid #6ee7b7; border-radius:7px; padding:10px 12px; font-size:12px; color:#065f46; margin-top:8px; }
.msg-restore-err { background:#fee2e2; border:1px solid #fca5a5; border-radius:7px; padding:10px 12px; font-size:12px; color:#991b1b; margin-top:8px; }
.install-divider {
  text-align: center;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: #9ca3af;
  margin: 16px 0;
  display: flex;
  align-items: center;
  gap: 10px;
}
.install-divider::before,
.install-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: #e5e7eb;
}
</style>
</head>
<body class="bg-light d-flex justify-content-center align-items-center" style="min-height:100vh;padding:24px">
<div class="card shadow" style="max-width:580px;width:100%">
  <div class="card-body p-4">

    <div class="d-flex align-items-center gap-2 mb-1">
      <span style="font-size:22px">🏠</span>
      <h4 class="mb-0 text-primary">AlquiGest – Instalación</h4>
    </div>
    <p class="text-muted mb-4" style="font-size:13px">
      Host: <strong><?= htmlspecialchars($cfg['host']) ?>:<?= $cfg['port'] ?></strong>
      &nbsp;·&nbsp; BD: <strong><?= htmlspecialchars($cfg['name']) ?></strong>
    </p>

    <?php if ($usuarioActual): ?>
    <p class="mb-3" style="font-size:12px;color:#6b7280">
      Sesión: <strong><?= htmlspecialchars($usuarioActual['nombre']) ?></strong>
      (<?= $usuarioActual['rol'] === 'admin' ? 'administrador' : 'usuario' ?>) ·
      <a href="#" onclick="fetch('../php/api.php?action=logout',{method:'POST'}).then(()=>location.href='../../login.php');return false;">cerrar sesión</a>
    </p>
    <?php endif; ?>

    <?php if ($adminCreado): ?>
    <div class="alert alert-success">
      ✅ Administrador <strong><?= htmlspecialchars($adminCreado) ?></strong> creado correctamente.
    </div>
    <div class="d-grid gap-2">
      <a href="../../login.php" class="btn btn-primary">Ir a iniciar sesión →</a>
    </div>

    <?php else: ?>
    <?php
    // Mostrar formulario principal si: sin modo, modo=fixzip (muestra resultado en el panel),
    // o backup/backup_data con error (el éxito ya hizo exit con el archivo)
    $showForm = !$mode || $mode === 'fixzip' || $mode === 'restore'
             || (in_array($mode, ['backup', 'backup_data']) && $error);
    ?>
    <?php if ($showForm): ?>

    <?php if ($error): ?>
    <div class="alert alert-danger mb-3">
      <strong>❌ Error:</strong> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($accesoDenegado): ?>
    <div class="alert alert-warning mb-3">
      <strong>🚫</strong> <?= htmlspecialchars($accesoDenegado) ?>
    </div>
    <?php endif; ?>

    <?php if ($primeraInstalacion && $usuariosTablaExiste): ?>
      <!-- Paso 2 de la primera instalación: crear el primer administrador.
           Las tablas ya existen (se acaba de ejecutar clean/sample o vienen de
           una instalación anterior a este sistema de usuarios), pero todavía
           no hay ninguna cuenta con la que iniciar sesión. -->
      <div class="restore-section">
        <div class="restore-section-title">👤 Crear el primer administrador</div>
        <p>La base de datos ya está lista. Crea la cuenta de administrador para poder entrar en la aplicación — a partir de este momento, install.php exigirá iniciar sesión.</p>
        <form method="POST">
          <input type="hidden" name="mode" value="create_admin">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
          <div class="mb-2"><label class="form-label">Nombre completo</label><input class="form-control" name="nombre" required></div>
          <div class="mb-2"><label class="form-label">Usuario (login)</label><input class="form-control" name="username" pattern="[a-zA-Z0-9._\-]{3,60}" required></div>
          <div class="mb-2"><label class="form-label">Email</label><input class="form-control" type="email" name="email"></div>
          <div class="mb-2"><label class="form-label">Contraseña</label><input class="form-control" type="password" name="password" minlength="8" required></div>
          <div class="mb-3"><label class="form-label">Repetir contraseña</label><input class="form-control" type="password" name="password2" minlength="8" required></div>
          <button type="submit" class="btn-restore" style="width:100%;justify-content:center">Crear administrador</button>
        </form>
      </div>
      <details class="mt-3">
        <summary style="cursor:pointer;font-size:12px;color:#6b7280">¿Necesitas reinstalar la base de datos desde cero antes de crear el administrador?</summary>
        <div class="mt-3">
          <?php renderBloqueInstalacionCompleta($zipStatus, $zipMsg, $restoreMsg); ?>
        </div>
      </details>

    <?php elseif ($primeraInstalacion): ?>
      <!-- Todavía no existe ni la base de datos ni la tabla usuarios: instalación normal -->
      <?php renderBloqueInstalacionCompleta($zipStatus, $zipMsg, $restoreMsg); ?>

    <?php elseif ($soloBackup): ?>
      <!-- Rol 'user': solo copia de seguridad, resto oculto (y bloqueado en backend) -->
      <?php $zipOk = ($zipStatus === 'ok'); ?>
      <div class="zip-section <?= $zipOk ? 'zip-ok' : 'zip-bad' ?>">
        <div class="zip-section-title">
          <?= $zipOk ? '✅' : '❌' ?> Excel XLSX (ZipArchive)
          <span style="font-weight:400;font-size:11px;color:<?= $zipOk ? '#166534' : '#991b1b' ?>">
            — <?= $zipOk ? 'activo y funcionando' : 'extensión no disponible (contacta con un administrador)' ?>
          </span>
        </div>
      </div>

      <div class="backup-section">
        <div class="backup-section-title">💾 Copia de seguridad</div>
        <p>Descarga una copia de los datos actuales.</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <form method="POST">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
            <button type="submit" name="mode" value="backup" class="btn-backup">⬇ Completa (estructura + datos)</button>
          </form>
          <form method="POST">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
            <button type="submit" name="mode" value="backup_data" class="btn-backup-outline">⬇ Solo datos (INSERT)</button>
          </form>
        </div>
      </div>

      <div class="alert alert-secondary" style="font-size:12px">
        No tienes permisos para instalar, restaurar ni ejecutar migraciones desde aquí. Contacta con un administrador si necesitas alguna de esas acciones.
      </div>
      <div class="mt-3 text-center">
        <a href="../../AlquiGest.php" class="text-muted" style="font-size:13px">← Volver a la aplicación</a>
      </div>

    <?php else: ?>
      <!-- Administrador autenticado: acceso completo -->
      <?php renderBloqueInstalacionCompleta($zipStatus, $zipMsg, $restoreMsg); ?>
      <div class="mt-3 text-center">
        <a href="../../index.php" class="text-muted" style="font-size:13px">← Volver al inicio</a>
      </div>
    <?php endif; ?>

    <?php elseif ($error): ?>
    <div class="alert alert-danger">
      <strong>❌ Error:</strong> <?= htmlspecialchars($error) ?><br>
      <small>Comprueba que MAMP/XAMPP está en marcha y que <code>config.php</code> tiene los datos correctos.</small>
    </div>
    <a href="install.php" class="btn btn-secondary mt-2">← Volver</a>

    <?php else: ?>
    <ul class="list-unstyled mb-4">
      <?php foreach ($log as $l): ?><li class="py-1"><?= $l ?></li><?php endforeach; ?>
    </ul>
    <div class="alert alert-success">✅ Instalación completada correctamente.</div>
    <div class="d-grid gap-2">
      <a href="../../AlquiGest.php" class="btn btn-primary">Abrir AlquiGest →</a>
      <a href="install.php" class="btn btn-outline-secondary">← Volver al instalador</a>
    </div>
    <?php endif; ?>
    <?php endif; // adminCreado ?>

  </div>
</div>
</body>
</html>
