<?php
// ============================================================
//  AlquiGest – Página de login
//  Autenticación de usuarios (ver assets/php/auth.php).
//  Si todavía no existe ningún usuario, redirige a install.php
//  para completar la primera instalación (crear el primer admin).
// ============================================================
require __DIR__ . '/assets/php/auth.php';
$cfg = require __DIR__ . '/assets/php/config.php';
session_bootstrap();

// Destino tras el login: solo se admite una ruta relativa dentro de la app
// (evita open-redirect si alguien manipula ?next=).
$next = $_GET['next'] ?? 'AlquiGest.php';
if (!preg_match('#^[a-zA-Z0-9/_.\-]+$#', $next) || strpos($next, '://') !== false) {
    $next = 'AlquiGest.php';
}

if (esPrimeraInstalacion($cfg)) {
    header('Location: assets/php/install.php');
    exit;
}

$error = null;

// Si ya hay una sesión válida, no mostrar el formulario de nuevo.
try {
    $pdo = authConnect($cfg);
    $u = currentUser();
    if ($u && revalidarUsuario($pdo, $u)) {
        header('Location: ' . $next);
        exit;
    }
} catch (\Throwable $e) {
    $error = 'No se puede conectar con la base de datos. Comprueba que MAMP/XAMPP está en marcha.';
}

if (!$error && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrfValid($_POST['_csrf'] ?? null)) {
        $error = 'Token de seguridad inválido. Recarga la página e inténtalo de nuevo.';
    } else {
        try {
            $pdo = authConnect($cfg);
            $r = attemptLogin($pdo, $_POST['username'] ?? '', $_POST['password'] ?? '');
            if ($r['ok']) {
                header('Location: ' . $next);
                exit;
            }
            $error = $r['error'];
        } catch (\Throwable $e) {
            $error = 'No se puede conectar con la base de datos. Comprueba que MAMP/XAMPP está en marcha.';
        }
    }
}

$token = csrfToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar sesión · AlquiGest</title>
<link rel="icon" href="data:,">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --blue: #1a56db; --blue-dark: #1242a8;
  --gray-50:#f9fafb; --gray-200:#e5e7eb; --gray-400:#9ca3af;
  --gray-500:#6b7280; --gray-600:#4b5563; --gray-900:#111827;
  --red:#c81e1e; --red-dark:#991b1b; --red-light:#fde8e8;
}
body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  background: var(--gray-900); color: white; min-height: 100vh;
  display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 24px;
}
.card {
  background: white; border-radius: 16px; padding: 40px 44px; text-align: center;
  max-width: 400px; width: 100%; box-shadow: 0 20px 60px rgba(0,0,0,.4);
}
.logo {
  display: inline-flex; align-items: center; justify-content: center;
  width: 64px; height: 64px; background: var(--blue); border-radius: 16px;
  margin-bottom: 18px; box-shadow: 0 8px 24px rgba(26,86,219,.35);
}
h1 { font-size: 24px; font-weight: 800; color: var(--gray-900); letter-spacing: -.5px; }
h1 span { color: var(--blue); }
.subtitle { font-size: 13px; color: var(--gray-500); margin-top: 4px; margin-bottom: 26px; }
.form-group { text-align: left; margin-bottom: 14px; }
.form-group label { display:block; font-size:12px; font-weight:600; color:var(--gray-600); margin-bottom:5px; }
.form-group input {
  width: 100%; padding: 10px 12px; border: 1px solid var(--gray-200); border-radius: 8px;
  font-size: 14px; color: var(--gray-900);
}
.form-group input:focus { outline: none; border-color: var(--blue); box-shadow: 0 0 0 3px rgba(26,86,219,.15); }
.btn {
  width: 100%; display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  background: var(--blue); color: white; font-size: 14px; font-weight: 600;
  padding: 11px 18px; border-radius: 10px; border: none; cursor: pointer; margin-top: 6px;
  transition: background .15s;
}
.btn:hover { background: var(--blue-dark); }
.error-box {
  background: var(--red-light); border: 1px solid #fca5a5; border-radius: 8px;
  padding: 10px 12px; margin-bottom: 16px; font-size: 13px; color: var(--red-dark); text-align: left;
}
footer { margin-top: 24px; font-size: 11px; color: var(--gray-600); }
footer a { color: var(--gray-400); }
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <svg width="32" height="32" fill="none" stroke="white" stroke-width="1.8" viewBox="0 0 24 24">
      <path d="M3 22V9l9-7 9 7v13"/><rect x="9" y="14" width="6" height="8"/><path d="M9 9h.01M15 9h.01"/>
    </svg>
  </div>
  <h1>Alqui<span>Gest</span></h1>
  <p class="subtitle">Inicia sesión para continuar</p>

  <?php if ($error): ?>
    <div class="error-box"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" autocomplete="on">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($token) ?>">
    <div class="form-group">
      <label for="f-user">Usuario</label>
      <input id="f-user" name="username" type="text" autocomplete="username" required autofocus>
    </div>
    <div class="form-group">
      <label for="f-pass">Contraseña</label>
      <input id="f-pass" name="password" type="password" autocomplete="current-password" required>
    </div>
    <button type="submit" class="btn">Entrar</button>
  </form>
</div>
<footer>AlquiGest &nbsp;·&nbsp; <a href="index.php">Volver al inicio</a></footer>
</body>
</html>
