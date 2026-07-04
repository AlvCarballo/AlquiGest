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
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
                `created_at`     DATETIME    DEFAULT CURRENT_TIMESTAMP,
                `updated_at`     DATETIME    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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

        // Añadir tabla log_actividad solo si está activada en config.php
        if (!empty($cfg['log_actividad'])) {
            $createSqls['log_actividad'] = "CREATE TABLE `log_actividad` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `tipo_accion` VARCHAR(100) NOT NULL DEFAULT '',
                `entidad` VARCHAR(50) DEFAULT '',
                `entidad_id` INT DEFAULT NULL,
                `descripcion` TEXT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_log_fecha` (`fecha`),
                INDEX `idx_log_tipo` (`tipo_accion`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        }

        foreach ($createSqls as $t => $sql) {
            $pdo->exec("DROP TABLE IF EXISTS `$t`");
            $pdo->exec($sql);
            $log[] = "✅ Tabla <code>$t</code> creada";
        }

        // ── Valores por defecto de configuración ──────────────
        // Se insertan siempre (instalación limpia y con datos de ejemplo)
        // para que la aplicación funcione sin configuración manual.
        //
        // Convención de visibilidad de botones destructivos:
        //   '1' → el botón se muestra (comportamiento por defecto)
        //   '0' → el botón queda oculto (útil en entornos de solo consulta)
        $configDefaults = [
            ['filas_dashboard',  '6',  'Número de filas por página en las tarjetas del dashboard (renovaciones y revisiones de renta). Mínimo: 1.'],
            ['filas_recibos',     '30', 'Número de filas por página en la tabla de Recibos. Mínimo: 5.'],
            ['filas_propietarios','20', 'Número de filas por página en la tabla de Propietarios. Mínimo: 5.'],
            ['filas_fincas',      '20', 'Número de filas por página en la tabla de Fincas. Mínimo: 5.'],
            ['filas_inmuebles',   '20', 'Número de filas por página en la tabla de Pisos / Locales. Mínimo: 5.'],
            ['filas_inquilinos',  '20', 'Número de filas por página en la tabla de Inquilinos. Mínimo: 5.'],
            ['filas_contratos',   '20', 'Número de filas por página en la tabla de Contratos. Mínimo: 5.'],
            ['filas_facturas',    '20', 'Número de filas por página en la tabla de Facturas. Mínimo: 5.'],
            ['VisiBorrarProp',   '1', 'Muestra el botón Eliminar en la tabla de Propietarios (1=visible, 0=oculto).'],
            ['VisiBorrarFinc',   '1', 'Muestra el botón Eliminar en la tabla de Fincas (1=visible, 0=oculto).'],
            ['VisiBorrarInm',    '1', 'Muestra el botón Eliminar en la tabla de Inmuebles (1=visible, 0=oculto).'],
            ['VisiBorrarInq',    '1', 'Muestra el botón Eliminar en la tabla de Inquilinos (1=visible, 0=oculto).'],
            ['VisiBorrarCont',   '1', 'Muestra el botón Eliminar en la tabla de Contratos (1=visible, 0=oculto).'],
            ['VisiAnularReci',   '1', 'Muestra el botón Anular en la tabla de Recibos (1=visible, 0=oculto).'],
            ['VisiAnularPago',   '1', 'Muestra el botón Anular en el panel de cobros de un recibo (1=visible, 0=oculto).'],
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
            // ── Dashboard — log de actividad ─────────────────────
            ['dash_log_actividad',   '1', 'Muestra el widget de últimas actividades en el Dashboard (1=visible, 0=oculto).'],
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

    <!-- ZipArchive / Excel fix -->
    <?php
    $zipOk = ($zipStatus === 'ok');
    $zipClass = $zipOk ? 'zip-ok' : 'zip-bad';
    ?>
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
          <button type="submit" name="mode" value="backup" class="btn-backup">
            ⬇ Completa (estructura + datos)
          </button>
        </form>
        <form method="POST">
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
      <p>Cualquiera de los dos botones de instalación eliminará por completo la base de datos actual: propietarios, fincas, inmuebles, inquilinos, contratos y recibos. <strong>Esta acción no puede deshacerse.</strong> Descarga una copia de seguridad antes de continuar.</p>
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

    <div class="mt-3 text-center">
      <a href="../../index.php" class="text-muted" style="font-size:13px">← Volver al inicio</a>
    </div>

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

  </div>
</div>
</body>
</html>
