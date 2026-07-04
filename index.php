<?php $cfg = require __DIR__ . '/assets/php/config.php'; ?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AlquiGest – Gestión de Alquileres</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --blue:      #1a56db;
  --blue-dark: #1242a8;
  --gray-50:   #f9fafb;
  --gray-100:  #f3f4f6;
  --gray-200:  #e5e7eb;
  --gray-400:  #9ca3af;
  --gray-500:  #6b7280;
  --gray-600:  #4b5563;
  --gray-700:  #374151;
  --gray-800:  #1f2937;
  --gray-900:  #111827;
  --red:       #c81e1e;
  --red-dark:  #991b1b;
  --red-light: #fde8e8;
}
body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  background: var(--gray-900);
  color: white;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 24px;
}

.card {
  background: white;
  border-radius: 16px;
  padding: 44px 52px;
  text-align: center;
  max-width: 500px;
  width: 100%;
  box-shadow: 0 20px 60px rgba(0,0,0,.4);
}

.logo {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 72px;
  height: 72px;
  background: var(--blue);
  border-radius: 18px;
  margin-bottom: 22px;
  box-shadow: 0 8px 24px rgba(26,86,219,.35);
}

h1 {
  font-size: 28px;
  font-weight: 800;
  color: var(--gray-900);
  letter-spacing: -.5px;
}
h1 span { color: var(--blue); }

.subtitle {
  font-size: 14px;
  color: var(--gray-500);
  margin-top: 6px;
  margin-bottom: 32px;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  background: var(--blue);
  color: white;
  font-size: 15px;
  font-weight: 600;
  padding: 13px 28px;
  border-radius: 10px;
  border: none;
  cursor: pointer;
  text-decoration: none;
  transition: background .15s, transform .1s, box-shadow .15s;
  box-shadow: 0 4px 14px rgba(26,86,219,.3);
}
.btn:hover {
  background: var(--blue-dark);
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(26,86,219,.4);
}
.btn:active { transform: translateY(0); }

.countdown {
  margin-top: 18px;
  font-size: 12px;
  color: var(--gray-400);
  height: 18px;
}

.features {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
  margin-top: 28px;
  padding-top: 24px;
  border-top: 1px solid var(--gray-200);
  text-align: left;
}
.feature {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 12px;
  color: var(--gray-600);
}
.feature-dot {
  width: 6px;
  height: 6px;
  background: var(--blue);
  border-radius: 50%;
  flex-shrink: 0;
  margin-top: 4px;
}

/* ── Sección de manuales ── */
.manuals {
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid var(--gray-200);
}
.manuals-title {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .07em;
  color: var(--gray-400);
  margin-bottom: 10px;
}
.manual-links {
  display: flex;
  justify-content: center;
  gap: 8px;
  flex-wrap: wrap;
}
.manual-link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--gray-600);
  text-decoration: none;
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid var(--gray-200);
  background: var(--gray-50);
  transition: all .12s;
}
.manual-link:hover {
  background: var(--gray-100);
  color: var(--gray-800);
  border-color: var(--gray-300);
}

/* ── Botón de instalación (esquina) ── */
.install-corner {
  position: fixed;
  bottom: 20px;
  right: 20px;
  z-index: 100;
}
.install-corner-btn {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
  cursor: pointer;
}
.install-trigger {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--gray-800);
  color: var(--gray-400);
  font-size: 11px;
  font-weight: 600;
  padding: 8px 14px;
  border-radius: 8px;
  border: 1px solid var(--gray-700);
  cursor: pointer;
  transition: all .12s;
  text-decoration: none;
}
.install-trigger:hover {
  background: var(--gray-700);
  color: white;
}
.install-popup {
  display: none;
  position: absolute;
  bottom: 44px;
  right: 0;
  width: 280px;
  background: white;
  border-radius: 12px;
  border: 2px solid var(--red);
  box-shadow: 0 8px 30px rgba(0,0,0,.3);
  overflow: hidden;
}
.install-popup.open { display: block; }
.install-popup-header {
  background: var(--red);
  padding: 10px 14px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.install-popup-header span {
  font-size: 13px;
  font-weight: 700;
  color: white;
}
.install-popup-body { padding: 14px; }
.install-warning {
  background: var(--red-light);
  border: 1px solid #fca5a5;
  border-radius: 8px;
  padding: 10px 12px;
  margin-bottom: 12px;
}
.install-warning-title {
  font-size: 12px;
  font-weight: 700;
  color: var(--red);
  margin-bottom: 4px;
}
.install-warning p {
  font-size: 11px;
  color: var(--red-dark);
  margin: 0;
  line-height: 1.5;
}
.install-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  width: 100%;
  padding: 10px;
  background: var(--red);
  color: white;
  font-size: 13px;
  font-weight: 700;
  border-radius: 8px;
  text-decoration: none;
  transition: background .12s;
}
.install-btn:hover { background: var(--red-dark); }

footer {
  margin-top: 28px;
  font-size: 11px;
  color: var(--gray-600);
}
</style>
</head>
<body>

<div class="card">
  <div class="logo">
    <svg width="36" height="36" fill="none" stroke="white" stroke-width="1.8" viewBox="0 0 24 24">
      <path d="M3 22V9l9-7 9 7v13"/>
      <rect x="9" y="14" width="6" height="8"/>
      <path d="M9 9h.01M15 9h.01"/>
    </svg>
  </div>

  <h1>Alqui<span>Gest</span></h1>
  <p class="subtitle">Sistema de gestión de alquileres</p>

  <a href="AlquiGest.php" class="btn" id="btn-entrar">
    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
      <polyline points="10 17 15 12 10 7"/>
      <line x1="15" y1="12" x2="3" y2="12"/>
    </svg>
    Entrar a la aplicación
  </a>

  <p class="countdown" id="countdown">Redirigiendo en <strong>30</strong> segundos…</p>

  <div class="features">
    <div class="feature"><div class="feature-dot"></div>Fincas e inmuebles</div>
    <div class="feature"><div class="feature-dot"></div>Gestión de inquilinos</div>
    <div class="feature"><div class="feature-dot"></div>Contratos de alquiler</div>
    <div class="feature"><div class="feature-dot"></div>Recibos y cobros</div>
    <div class="feature"><div class="feature-dot"></div>Generación de PDFs</div>
    <div class="feature"><div class="feature-dot"></div>Envío por email</div>
  </div>

  <!-- Manuales -->
  <div class="manuals">
    <div class="manuals-title">Documentación</div>
    <div class="manual-links">
      <a href="assets/docs/ayuda.php" class="manual-link">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
        Manual completo
      </a>
      <a href="assets/docs/ayuda_verifactu.php" class="manual-link">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="14 2 14 8 20 8"/></svg>
        Guía VERI*FACTU / AEAT
      </a>
      <a href="assets/docs/fixexcel.html" class="manual-link">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
        Error Excel (XAMPP)
      </a>
      <a href="assets/docs/cors.html" class="manual-link">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Seguridad y servidor
      </a>
    </div>
  </div>
</div>

<footer>AlquiGest v<?= htmlspecialchars($cfg['version']) ?> &nbsp;·&nbsp; Gestión local de alquileres</footer>

<!-- ── Botón install en esquina ── -->
<div class="install-corner">
  <div class="install-corner-btn">
    <div class="install-popup" id="install-popup">
      <div class="install-popup-header">
        <svg width="16" height="16" fill="none" stroke="white" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <span>Zona de instalación</span>
      </div>
      <div class="install-popup-body">
        <div class="install-warning">
          <div class="install-warning-title">⚠ ADVERTENCIA: PÉRDIDA DE DATOS</div>
          <p>Ejecutar el instalador <strong>borrará todos los datos</strong> actuales de la base de datos: propietarios, fincas, inquilinos, contratos y recibos.</p>
        </div>
        <p style="font-size:11px;color:#6b7280;margin-bottom:10px">Usa esta opción solo para una instalación inicial o para reiniciar el programa. Haz una copia de seguridad antes.</p>
        <a href="assets/php/install.php" class="install-btn">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
          Ir al instalador
        </a>
      </div>
    </div>
    <button class="install-trigger" id="install-trigger" type="button">
      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 19.07a10 10 0 0 1 0-14.14"/></svg>
      Instalación / BD
    </button>
  </div>
</div>

<script>
  // Countdown
  let n = 30;
  const cd = document.getElementById('countdown');
  const timer = setInterval(() => {
    n--;
    if (n <= 0) {
      clearInterval(timer);
      cd.textContent = '';
      window.location.href = 'AlquiGest.php';
    } else {
      cd.innerHTML = `Redirigiendo en <strong>${n}</strong> segundo${n !== 1 ? 's' : ''}…`;
    }
  }, 1000);

  document.getElementById('btn-entrar').addEventListener('click', () => {
    clearInterval(timer);
    cd.textContent = '';
  });

  // Popup install
  const trigger = document.getElementById('install-trigger');
  const popup   = document.getElementById('install-popup');
  trigger.addEventListener('click', (e) => {
    e.stopPropagation();
    popup.classList.toggle('open');
  });
  document.addEventListener('click', () => popup.classList.remove('open'));
</script>
</body>
</html>
