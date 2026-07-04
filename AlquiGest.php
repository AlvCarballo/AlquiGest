<?php $cfg = require __DIR__ . '/assets/php/config.php'; ?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AlquiGest – Gestión de Alquileres</title>
<link rel="icon" href="data:,">
<link rel="stylesheet" href="assets/css/main.css?v=20260630a">
</head>
<body>

<div id="app">
  <!-- Sidebar -->
  <aside id="sidebar">
    <div class="sidebar-logo">
      <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
        <rect width="32" height="32" rx="8" fill="#1a56db"/>
        <path d="M6 24V14l10-8 10 8v10" stroke="white" stroke-width="2" stroke-linejoin="round"/>
        <rect x="12" y="18" width="8" height="6" rx="1" fill="white" opacity=".9"/>
        <path d="M10 12h12" stroke="white" stroke-width="1.5" opacity=".6"/>
      </svg>
      <div>
        <div class="sidebar-logo-text">AlquiGest</div>
        <div class="sidebar-logo-sub">Gestión de Alquileres</div>
      </div>
    </div>
    <nav class="sidebar-nav">
      <a href="index.html" class="nav-item" style="border-left-color:transparent;color:var(--gray-500);font-size:12px;padding:8px 16px;margin-bottom:2px">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
        Inicio
      </a>
      <div class="nav-group">
        <div class="nav-group-title">Principal</div>
        <div class="nav-item active" data-page="dashboard" onclick="navigate('dashboard')">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/></svg>
          Dashboard
        </div>
      </div>
      <div class="nav-group" id="nav-group-maestros">
        <div class="nav-group-title">Maestros</div>
        <div class="nav-item" id="nav-propietarios" data-page="propietarios" onclick="navigate('propietarios')">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
          Propietarios
        </div>
        <div class="nav-item" id="nav-fincas" data-page="fincas" onclick="navigate('fincas')">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="15" rx="1"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>
          Fincas / Edificios
        </div>
        <div class="nav-item" id="nav-inmuebles" data-page="inmuebles" onclick="navigate('inmuebles')">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 22V9l9-7 9 7v13"/><rect x="9" y="14" width="6" height="8"/><path d="M9 10h6"/></svg>
          Pisos / Locales
        </div>
        <div class="nav-item" id="nav-inquilinos" data-page="inquilinos" onclick="navigate('inquilinos')">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/><path d="M21 21v-2a4 4 0 0 0-3-3.85"/></svg>
          Inquilinos
        </div>
      </div>
      <div class="nav-group" id="nav-group-alquileres">
        <div class="nav-group-title">Alquileres</div>
        <div class="nav-item" id="nav-contratos" data-page="contratos" onclick="navigate('contratos')">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          Contratos
        </div>
        <div class="nav-item" id="nav-recibos" data-page="recibos" onclick="navigate('recibos')">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          Recibos
        </div>
        <div class="nav-item" id="nav-facturas" data-page="facturas" onclick="navigate('facturas')">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
          Facturas
        </div>
        <div class="nav-item" id="nav-generar" data-page="generar" onclick="navigate('generar')">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="12" y1="14" x2="12" y2="18"/><line x1="10" y1="16" x2="14" y2="16"/></svg>
          Generar Recibos
        </div>
      </div>
      <div class="nav-group" id="nav-group-informes">
        <div class="nav-group-title">Informes</div>
        <div class="nav-item" id="nav-informes" data-page="informes" onclick="navigate('informes')">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
          Informes Excel
        </div>
        <div class="nav-item" id="nav-calendario" data-page="calendario" onclick="navigate('calendario')">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          Calendario Cobros
        </div>
        <div class="nav-item" id="nav-morosidad" data-page="morosidad" onclick="navigate('morosidad')">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          Morosidad
        </div>
        <div class="nav-item" id="nav-actividad" data-page="actividad" onclick="navigate('actividad')">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
          Actividad
        </div>
      </div>
      <div class="nav-group" id="nav-group-config">
        <div class="nav-group-title">Configuración</div>
        <div class="nav-item" id="nav-empresa" data-page="empresa" onclick="navigate('empresa')">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.22 4.22l2.12 2.12M17.66 17.66l2.12 2.12M2 12h3M19 12h3M4.22 19.78l2.12-2.12M17.66 6.34l2.12-2.12"/></svg>
          Mi Empresa
        </div>
        <div class="nav-item" id="nav-configuracion" data-page="configuracion" onclick="navigate('configuracion')">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
          Parámetros
        </div>
        <div class="nav-item" id="nav-verifactu" data-page="verifactu" onclick="navigate('verifactu')">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          VERI*FACTU
        </div>
        <div class="nav-item" id="nav-plantillas" data-page="plantillas" onclick="navigate('plantillas')">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg>
          Plantillas
        </div>
      </div>
    </nav>
    <div class="sidebar-footer">AlquiGest v<?= htmlspecialchars($cfg['version']) ?> · <?= htmlspecialchars($cfg['year']) ?></div>
  </aside>

  <!-- Main -->
  <div id="main">
    <header id="header">
      <div id="header-title">Dashboard</div>

      <!-- Búsqueda global -->
      <div id="search-wrap">
        <svg id="search-icon" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input id="search-input" type="text" placeholder="Buscar inquilino, inmueble, contrato…" autocomplete="off"
               oninput="busquedaGlobal(this.value)" onblur="setTimeout(()=>document.getElementById('search-results').classList.remove('open'),200)">
        <div id="search-results"></div>
      </div>

      <div id="header-right">
        <!-- Botón modo oscuro -->
        <button id="dark-toggle" onclick="toggleModoOscuro()" title="Alternar modo oscuro">
          <svg id="dark-icon-sun" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
          <svg id="dark-icon-moon" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
        <div id="notif-wrap">
          <button id="notif-btn" onclick="toggleNotifPanel(event)" title="Avisos de revisión anual">
            <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
              <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            <span id="notif-badge"></span>
          </button>
          <div id="notif-panel"></div>
        </div>
        <div id="header-actions"></div>
      </div>
    </header>
    <div id="content"></div>
  </div>
</div>

<!-- Toast container -->
<div id="toast-container"></div>

<!-- Print area (hidden, shown on print) -->
<div id="print-area"></div>

<!-- ===========================
     MODALS
=========================== -->
<div class="ag-overlay" id="modal-overlay" onclick="closeModalOnOverlay(event)">
  <div class="ag-modal" id="modal">
    <div class="ag-modal-header">
      <div class="ag-modal-title" id="modal-title">Modal</div>
      <button class="ag-modal-close" onclick="closeModal()">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="ag-modal-body" id="modal-body"></div>
    <div class="ag-modal-footer" id="modal-footer"></div>
  </div>
</div>


<!-- Modulos JS separados (M-T06) â€" carga en orden de dependencias -->
<script src="assets/js/config.js?v=20260625o"></script>
<script src="assets/js/helpers.js?v=20260704a"></script>
<script src="assets/js/actividad.js?v=20260626v"></script>
<script src="assets/js/tabla.js?v=20260625o"></script>
<script src="assets/js/notificaciones.js?v=20260625o"></script>
<script src="assets/js/dashboard.js?v=20260704a"></script>
<script src="assets/js/empresa.js?v=20260626c"></script>
<script src="assets/js/propietarios.js?v=20260626z"></script>
<script src="assets/js/fincas.js?v=20260625o"></script>
<script src="assets/js/inmuebles.js?v=20260625o"></script>
<script src="assets/js/inquilinos.js?v=20260626z"></script>
<script src="assets/js/contratos.js?v=20260628a"></script>
<script src="assets/js/contratos-pdf.js?v=20260626w"></script>
<script src="assets/js/recibos-lista.js?v=20260704a"></script>
<script src="assets/js/recibos-cobro.js?v=20260705b"></script>
<script src="assets/js/recibos-pdf.js?v=20260630a"></script>
<script src="assets/js/generar.js?v=20260630a"></script>
<script src="assets/js/informes.js?v=20260626p"></script>
<script src="assets/js/email.js?v=20260630a"></script>
<script src="assets/js/facturas.js?v=20260705a"></script>
<script src="assets/js/verifactu.js?v=20260629a"></script>
<script src="assets/js/ux.js?v=20260626f"></script>
<script src="assets/js/extras.js?v=20260626w"></script>
<script src="assets/js/configuracion.js?v=20260630b"></script>
<script src="assets/js/plantillas.js?v=20260629d"></script>
<!-- Librerias externas locales (sin dependencia de internet) -->
<script src="assets/js/vendor/chart.umd.min.js"></script>
<script src="assets/js/vendor/html2canvas.min.js"></script>
<script src="assets/js/vendor/jspdf.umd.min.js"></script>
<script src="assets/js/vendor/qrious.min.js"></script>
<!-- Arranque: carga BD async y navega al dashboard (M-T07) -->
<script src="assets/js/init.js?v=20260630b"></script>

