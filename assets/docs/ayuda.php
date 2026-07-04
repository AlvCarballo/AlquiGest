<?php $cfg = require __DIR__ . '/../php/config.php'; ?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AlquiGest – Manual de usuario</title>
<link rel="stylesheet" href="../css/main.css">
</head>
<body style="background:var(--gray-50)">
<div class="doc-page">

<!-- NAV -->
<nav class="doc-nav">
  <div class="doc-nav-logo">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#1a56db" stroke-width="1.8"><path d="M3 22V9l9-7 9 7v13"/><rect x="9" y="14" width="6" height="8"/></svg>
      <div class="doc-nav-logo-title">AlquiGest</div>
    </div>
    <div class="doc-nav-logo-sub">Manual de usuario</div>
  </div>
  <div style="padding:10px 0">
    <a class="doc-nav-item" href="../../AlquiGest.php">🏠 Abrir la aplicación</a>
    <div style="height:1px;background:#374151;margin:4px 16px 8px"></div>
    <div class="doc-nav-section-title">Introducción</div>
    <a class="doc-nav-item" href="#inicio">🚀 Primeros pasos</a>
    <a class="doc-nav-item" href="#estructura">🗂️ Estructura del programa</a>
    <div class="doc-nav-section-title" style="margin-top:6px">Módulos</div>
    <a class="doc-nav-item" href="#empresa">🏢 Mi Empresa</a>
    <a class="doc-nav-item" href="#propietarios">👤 Propietarios</a>
    <a class="doc-nav-item" href="#fincas">🏘️ Fincas</a>
    <a class="doc-nav-item" href="#inmuebles">🚪 Inmuebles (Pisos)</a>
    <a class="doc-nav-item" href="#inquilinos">🧑‍🤝‍🧑 Inquilinos</a>
    <a class="doc-nav-item" href="#contratos">📄 Contratos</a>
    <a class="doc-nav-item" href="#recibos">🧾 Recibos y Cobros</a>
    <a class="doc-nav-item" href="#facturas">📋 Facturas legales</a>
    <a class="doc-nav-item" href="#generar">⚡ Generar Recibos en Lote</a>
    <a class="doc-nav-item" href="#plantillas">📝 Plantillas DOCX</a>
    <div class="doc-nav-section-title" style="margin-top:6px">Herramientas</div>
    <a class="doc-nav-item" href="#dashboard">📊 Dashboard</a>
    <a class="doc-nav-item" href="#excel">📈 Informes Excel</a>
    <a class="doc-nav-item" href="#excel-fiscal">🏛️ Informes fiscales</a>
    <a class="doc-nav-item" href="#email">📧 Envío por Email</a>
    <a class="doc-nav-item" href="#pdf">🖨️ Generación de PDFs</a>
    <a class="doc-nav-item" href="#calendario">📅 Calendario cobros</a>
    <a class="doc-nav-item" href="#morosidad">🔴 Morosidad</a>
    <a class="doc-nav-item" href="#importar">📥 Importar CSV</a>
    <a class="doc-nav-item" href="#backup">💾 Backup</a>
    <a class="doc-nav-item" href="#irpf">📄 Certif. IRPF</a>
    <div class="doc-nav-section-title" style="margin-top:6px">Productividad</div>
    <a class="doc-nav-item" href="#herramientas">⚡ Búsqueda y atajos</a>
    <div class="doc-nav-section-title" style="margin-top:6px">Avanzado</div>
    <a class="doc-nav-item" href="#verifactu-config">🛡️ VERI*FACTU</a>
    <a class="doc-nav-item" href="#configuracion">⚙️ Parámetros</a>
    <a class="doc-nav-item" href="#faq">❓ Preguntas frecuentes</a>
    <a class="doc-nav-item" href="#limitaciones">🚧 Limitaciones</a>
    <a class="doc-nav-item" href="ayuda_verifactu.php" style="color:#f59e0b">🏛️ Guía técnica AEAT →</a>
  </div>
</nav>

<!-- MAIN -->
<main class="doc-main">

<!-- HERO -->
<div class="doc-hero">
  <div class="doc-hero-label">AlquiGest v<?= htmlspecialchars($cfg['version']) ?> · Manual de usuario</div>
  <h1>Guía completa del programa</h1>
  <p>Todo lo que necesitas saber para gestionar fincas, inquilinos, contratos, recibos y facturas de alquiler.</p>
  <div class="doc-hero-grid" style="grid-template-columns:repeat(6,1fr)">
    <div class="doc-hero-stat"><div class="doc-hero-stat-num">👤</div><div class="doc-hero-stat-label">Propietarios</div></div>
    <div class="doc-hero-stat"><div class="doc-hero-stat-num">📄</div><div class="doc-hero-stat-label">Contratos</div></div>
    <div class="doc-hero-stat"><div class="doc-hero-stat-num">🧾</div><div class="doc-hero-stat-label">Recibos</div></div>
    <div class="doc-hero-stat"><div class="doc-hero-stat-num">📋</div><div class="doc-hero-stat-label">Facturas</div></div>
    <div class="doc-hero-stat"><div class="doc-hero-stat-num">📝</div><div class="doc-hero-stat-label">Plantillas</div></div>
    <div class="doc-hero-stat"><div class="doc-hero-stat-num">🛡️</div><div class="doc-hero-stat-label">VERI*FACTU</div></div>
  </div>
</div>

<!-- PRIMEROS PASOS -->
<h2 id="inicio">Primeros pasos</h2>

<p>AlquiGest es un programa de gestión de alquileres que funciona en tu navegador web. No necesita instalación adicional más allá del servidor local (MAMP o XAMPP) y se accede desde <code>http://localhost/AlquiGest_v2/</code>.</p>

<h3>Requisitos</h3>
<ul>
  <li>MAMP o XAMPP con <strong>Apache y MySQL activos</strong></li>
  <li>Haber ejecutado <code>install.php</code> al menos una vez para crear la base de datos</li>
  <li>Navegador moderno (Chrome, Firefox, Edge, Safari)</li>
</ul>

<div class="callout callout-tip">
  <div class="callout-icon">💡</div>
  <div class="callout-body">
    <strong>Primera vez</strong>
    <p>Ve a <code>http://localhost/AlquiGest_v2/install.php</code> y elige "Instalación limpia" o "Instalación con datos de ejemplo" para ver cómo funciona todo. Después accede normalmente a la aplicación.</p>
  </div>
</div>

<h3>Flujo de trabajo recomendado</h3>
<div class="workflow">
  <div class="wf-step"><div class="wf-step-icon">🏢</div><div class="wf-step-label">1. Configura<br>Mi Empresa</div></div>
  <div class="wf-arrow">→</div>
  <div class="wf-step"><div class="wf-step-icon">👤</div><div class="wf-step-label">2. Crea<br>Propietarios</div></div>
  <div class="wf-arrow">→</div>
  <div class="wf-step"><div class="wf-step-icon">🏘️</div><div class="wf-step-label">3. Añade<br>Fincas</div></div>
  <div class="wf-arrow">→</div>
  <div class="wf-step"><div class="wf-step-icon">🚪</div><div class="wf-step-label">4. Registra<br>Inmuebles</div></div>
  <div class="wf-arrow">→</div>
  <div class="wf-step"><div class="wf-step-icon">🧑‍🤝‍🧑</div><div class="wf-step-label">5. Añade<br>Inquilinos</div></div>
  <div class="wf-arrow">→</div>
  <div class="wf-step"><div class="wf-step-icon">📄</div><div class="wf-step-label">6. Crea<br>Contratos</div></div>
  <div class="wf-arrow">→</div>
  <div class="wf-step"><div class="wf-step-icon">🧾</div><div class="wf-step-label">7. Gestiona<br>Recibos</div></div>
  <div class="wf-arrow">→</div>
  <div class="wf-step"><div class="wf-step-icon">📋</div><div class="wf-step-label">8. Emite<br>Facturas</div></div>
</div>

<!-- ESTRUCTURA -->
<h2 id="estructura">Estructura del programa</h2>
<p>AlquiGest organiza la información en una jerarquía lógica. Comprender esta estructura es clave para usar el programa correctamente:</p>

<div class="doc-section-card">
  <svg width="100%" height="220" viewBox="0 0 700 220" font-family="-apple-system,sans-serif" style="max-width:700px">
    <defs>
      <marker id="aw" markerWidth="8" markerHeight="8" refX="6" refY="3" orient="auto"><path d="M0,0 L0,6 L8,3 z" fill="#9ca3af"/></marker>
    </defs>
    <rect x="280" y="10" width="140" height="36" rx="8" fill="#e8f0fe" stroke="#1a56db" stroke-width="1.5"/>
    <text x="350" y="32" font-size="13" font-weight="700" fill="#1a56db" text-anchor="middle">🏢 Mi Empresa</text>
    <rect x="90" y="80" width="130" height="36" rx="8" fill="#ede9fe" stroke="#7c3aed" stroke-width="1.5"/>
    <text x="155" y="102" font-size="13" font-weight="700" fill="#7c3aed" text-anchor="middle">👤 Propietario</text>
    <rect x="280" y="80" width="130" height="36" rx="8" fill="#def7ec" stroke="#057a55" stroke-width="1.5"/>
    <text x="345" y="102" font-size="13" font-weight="700" fill="#057a55" text-anchor="middle">🏘️ Finca</text>
    <rect x="480" y="80" width="130" height="36" rx="8" fill="#fdf6b2" stroke="#c27803" stroke-width="1.5"/>
    <text x="545" y="102" font-size="13" font-weight="700" fill="#c27803" text-anchor="middle">🧑 Inquilino</text>
    <rect x="280" y="155" width="130" height="36" rx="8" fill="#def7ec" stroke="#057a55" stroke-width="1.5"/>
    <text x="345" y="177" font-size="13" font-weight="700" fill="#057a55" text-anchor="middle">🚪 Inmueble</text>
    <rect x="470" y="155" width="130" height="36" rx="8" fill="#e8f0fe" stroke="#1a56db" stroke-width="1.5"/>
    <text x="535" y="177" font-size="13" font-weight="700" fill="#1a56db" text-anchor="middle">📄 Contrato</text>
    <line x1="220" y1="98" x2="280" y2="98" stroke="#9ca3af" stroke-width="1.5" marker-end="url(#aw)"/>
    <text x="250" y="91" font-size="10" fill="#9ca3af" text-anchor="middle">tiene</text>
    <line x1="345" y1="116" x2="345" y2="155" stroke="#9ca3af" stroke-width="1.5" marker-end="url(#aw)"/>
    <text x="360" y="138" font-size="10" fill="#9ca3af">tiene</text>
    <line x1="545" y1="116" x2="535" y2="155" stroke="#9ca3af" stroke-width="1.5" marker-end="url(#aw)"/>
    <text x="558" y="138" font-size="10" fill="#9ca3af">firma</text>
    <line x1="410" y1="173" x2="470" y2="173" stroke="#9ca3af" stroke-width="1.5" marker-end="url(#aw)"/>
    <text x="440" y="167" font-size="10" fill="#9ca3af" text-anchor="middle">en</text>
    <text x="350" y="52" font-size="10" fill="#6b7280" text-anchor="middle">─ contexto global ─</text>
  </svg>
  <p style="font-size:13px;color:var(--gray-500);margin:8px 0 0;text-align:center">Un <strong>Propietario</strong> tiene <strong>Fincas</strong> → cada Finca tiene <strong>Inmuebles</strong> → cada Inmueble puede tener un <strong>Contrato</strong> activo con un <strong>Inquilino</strong></p>
</div>

<!-- MI EMPRESA -->
<h2 id="empresa">Mi Empresa</h2>
<div class="doc-section-card">
  <div class="doc-section-header">
    <div class="doc-section-icon" style="background:#e8f0fe">🏢</div>
    <div><div class="doc-section-title">Configuración de la empresa administradora</div><div class="doc-section-desc">Datos que aparecen en recibos, facturas y correos enviados a inquilinos</div></div>
  </div>
</div>

<p>El módulo <strong>Mi Empresa</strong> guarda los datos del administrador o empresa gestora. Esta información aparece en la cabecera de todos los recibos y facturas generados y en los correos enviados.</p>

<table class="field-table">
  <tr><th>Campo</th><th>Descripción</th></tr>
  <tr><td><strong>Nombre</strong> <span class="badge-req">Obligatorio</span></td><td>Nombre comercial o razón social. Aparece en todos los documentos</td></tr>
  <tr><td><strong>CIF/NIF</strong> <span class="badge-req">Para facturas</span></td><td>Identificación fiscal. Imprescindible para generar facturas legales</td></tr>
  <tr><td><strong>Dirección, CP, Municipio</strong></td><td>Dirección completa que aparece en recibos y facturas</td></tr>
  <tr><td><strong>Teléfono / Email</strong></td><td>Datos de contacto que aparecen en los documentos</td></tr>
  <tr><td><strong>IBAN</strong></td><td>Cuenta bancaria para domiciliaciones</td></tr>
  <tr><td><strong>Pie de recibo</strong></td><td>Texto libre al pie de cada recibo (ej: "Gracias por su pago puntual")</td></tr>
  <tr><td><strong>Prefijo de recibos</strong></td><td>Siglas que preceden al número de recibo (ej: "REC" genera REC-202601-00001)</td></tr>
  <tr><td><strong>Gmail usuario / contraseña</strong></td><td>Cuenta Gmail y contraseña de aplicación para enviar correos. Ver sección <a href="#email">Envío por Email</a></td></tr>
  <tr><td><strong>Plantillas de email</strong></td><td>Asunto y cuerpo personalizables para correos de recibo y factura. Variables: <code>{{numero_recibo}}</code>, <code>{{periodo}}</code>, <code>{{inquilino}}</code>, <code>{{importe}}</code>, <code>{{empresa}}</code>, <code>{{numero_factura}}</code></td></tr>
  <tr><td><strong>Backup JSON</strong></td><td>Botón <em>Descargar backup JSON</em> que descarga todos los datos de la BD. Ver <a href="#backup">Copia de seguridad</a></td></tr>
</table>

<!-- PROPIETARIOS -->
<h2 id="propietarios">Propietarios</h2>
<p>Los propietarios son las personas o empresas dueñas de las fincas. Cada finca debe estar asignada a un propietario.</p>

<table class="field-table">
  <tr><th>Campo</th><th>Descripción</th></tr>
  <tr><td><strong>Nombre</strong> <span class="badge-req">Obligatorio</span></td><td>Nombre completo, idealmente "Apellido, Nombre" para mejor ordenación</td></tr>
  <tr><td><strong>NIF</strong></td><td>DNI, NIE o CIF del propietario</td></tr>
  <tr><td><strong>Teléfono / Email</strong></td><td>Datos de contacto</td></tr>
  <tr><td><strong>IRPF</strong></td><td>Marca "S" si el propietario está sujeto a retención de IRPF</td></tr>
  <tr><td><strong>IBAN</strong></td><td>Cuenta bancaria para liquidaciones de rentas</td></tr>
  <tr><td><strong>Observaciones</strong></td><td>Notas internas, preferencias de contacto, etc.</td></tr>
</table>

<div class="callout callout-tip">
  <div class="callout-icon">💡</div>
  <div class="callout-body">
    <strong>Contador de inmuebles</strong>
    <p>La tabla de propietarios muestra cuántos inmuebles tiene cada propietario (sumando los de todas sus fincas). Se actualiza automáticamente.</p>
  </div>
</div>

<!-- FINCAS -->
<h2 id="fincas">Fincas</h2>
<p>Una finca es un edificio o conjunto de inmuebles en una misma dirección. Cada finca pertenece a un propietario y agrupa uno o varios inmuebles.</p>

<table class="field-table">
  <tr><th>Campo</th><th>Descripción</th></tr>
  <tr><td><strong>Nombre</strong> <span class="badge-req">Obligatorio</span></td><td>Nombre identificativo (ej: "C/ Mayor 15")</td></tr>
  <tr><td><strong>Sigla / Calle / Número</strong></td><td>Dirección de la finca. La sigla es la abreviatura del tipo de vía (C, AV, PZ…)</td></tr>
  <tr><td><strong>CP, Municipio, Provincia</strong></td><td>Ubicación de la finca</td></tr>
  <tr><td><strong>Propietario</strong> <span class="badge-req">Obligatorio</span></td><td>Propietario al que pertenece esta finca. Debe crearse primero</td></tr>
</table>

<!-- INMUEBLES -->
<h2 id="inmuebles">Inmuebles (Pisos)</h2>
<p>Cada piso, local, garaje o trastero que se alquila es un inmueble. Pertenecen a una finca y pueden tener un contrato activo.</p>

<table class="field-table">
  <tr><th>Campo</th><th>Descripción</th></tr>
  <tr><td><strong>Finca</strong> <span class="badge-req">Obligatorio</span></td><td>La finca a la que pertenece este inmueble</td></tr>
  <tr><td><strong>Planta</strong></td><td>Número de planta: 1º, 2º, BAJO, ÁTICO, etc.</td></tr>
  <tr><td><strong>Puerta</strong></td><td>Letra o identificador: A, B, DCHA, IZQ, etc.</td></tr>
  <tr><td><strong>Tipo</strong></td><td>Clasificación: vivienda, local, garaje, trastero, oficina…</td></tr>
  <tr><td><strong>Metros</strong></td><td>Superficie en m²</td></tr>
  <tr><td><strong>Ref. Catastral</strong></td><td>Referencia catastral del inmueble (20 caracteres)</td></tr>
  <tr><td><strong>Cédula</strong></td><td>Número de cédula de habitabilidad</td></tr>
</table>

<p>La tabla muestra automáticamente si el piso está <strong>ocupado</strong> (contrato activo) o <strong>libre</strong>, con el nombre del inquilino actual.</p>

<!-- INQUILINOS -->
<h2 id="inquilinos">Inquilinos</h2>
<p>Los inquilinos son las personas o empresas que alquilan un inmueble. Un mismo inquilino puede tener varios contratos a lo largo del tiempo.</p>

<table class="field-table">
  <tr><th>Campo</th><th>Descripción</th></tr>
  <tr><td><strong>Nombre</strong> <span class="badge-req">Obligatorio</span></td><td>Nombre completo. Para personas usa "Apellido, Nombre"</td></tr>
  <tr><td><strong>NIF</strong> <span class="badge-req">Para facturas</span></td><td>DNI, NIE o CIF. Imprescindible para generar facturas legales</td></tr>
  <tr><td><strong>Teléfono / Móvil</strong></td><td>Teléfonos de contacto</td></tr>
  <tr><td><strong>Email</strong></td><td>Correo al que se enviarán recibos y facturas. Debe ser válido para el envío por email</td></tr>
  <tr><td><strong>Dirección</strong></td><td>Dirección particular del inquilino</td></tr>
  <tr><td><strong>IBAN</strong></td><td>Cuenta bancaria para domiciliaciones o devoluciones</td></tr>
</table>

<!-- CONTRATOS -->
<h2 id="contratos">Contratos</h2>
<p>Un contrato vincula un inquilino con un inmueble y define las condiciones del alquiler. Solo puede haber un contrato activo por inmueble.</p>

<table class="field-table">
  <tr><th>Campo</th><th>Descripción</th></tr>
  <tr><td><strong>Inmueble</strong> <span class="badge-req">Obligatorio</span></td><td>El piso o local objeto del contrato. Solo aparecen los que están libres</td></tr>
  <tr><td><strong>Inquilino</strong> <span class="badge-req">Obligatorio</span></td><td>El inquilino que firma el contrato</td></tr>
  <tr><td><strong>Fecha inicio / Fecha fin</strong></td><td>Período del contrato</td></tr>
  <tr><td><strong>Renta base</strong></td><td>Importe mensual del alquiler sin impuestos</td></tr>
  <tr><td><strong>IVA %</strong></td><td>Para locales comerciales: 21%. Para vivienda habitual: 0%</td></tr>
  <tr><td><strong>IRPF %</strong></td><td>Retención de IRPF si el propietario está sujeto (normalmente 19%)</td></tr>
  <tr><td><strong>Fianza</strong></td><td>Importe de la fianza depositada</td></tr>
  <tr><td><strong>Día de pago</strong></td><td>Día del mes en que vence el recibo (habitualmente 1 o 5)</td></tr>
  <tr><td><strong>Revisión</strong></td><td>Cláusula de revisión de renta (IPC, % fijo, etc.). Solo informativo</td></tr>
  <tr><td><strong>Motivo de temporada</strong></td><td>Texto libre que justifica la duración limitada del contrato. Aparece en plantillas como <code>{{MotivoTemporada}}</code>. Solo necesario para contratos de temporada (&lt; 1 año)</td></tr>
  <tr><td><strong>Mostrar aviso en recibo/factura</strong></td><td>Si está marcado, el recibo y la factura impresos incluyen al pie el aviso de justificante de pago (ver <a href="#facturas">sección Facturas</a>)</td></tr>
</table>

<h3>Fiador solidario (opcional)</h3>
<p>Cada contrato puede incluir un fiador solidario que queda vinculado al contrato. Sus datos aparecen en las plantillas DOCX a través de las variables <code>{{NombreFiador}}</code>, <code>{{NIFFiador}}</code> y <code>{{DireccionFiador}}</code>.</p>

<h3>Inquilinos secundarios (firmantes adicionales)</h3>
<p>Puedes añadir hasta N inquilinos secundarios por contrato (copropietarios, pareja, etc.). Sus datos se gestionan en la sección inferior del formulario de contrato y se pueden editar independientemente en cualquier momento. En plantillas, se usan con el bloque repetitivo <code>{{#INQUILINOS_SECUNDARIOS}}...{{/INQUILINOS_SECUNDARIOS}}</code> que se expande automáticamente una vez por cada inquilino secundario registrado.</p>

<h3>Estados del contrato</h3>
<ul>
  <li><strong>Activo</strong> — contrato en vigor, el inmueble está ocupado</li>
  <li><strong>Finalizado</strong> — contrato vencido por fin de plazo</li>
  <li><strong>Rescindido</strong> — contrato terminado antes de su vencimiento</li>
</ul>

<div class="callout callout-warn">
  <div class="callout-icon">⚠️</div>
  <div class="callout-body">
    <strong>Al dar de baja un contrato</strong>
    <p>El inmueble queda libre inmediatamente y puede asignarse a un nuevo inquilino. Los recibos y facturas del período se conservan en la base de datos.</p>
  </div>
</div>

<!-- RECIBOS -->
<h2 id="recibos">Recibos y Cobros</h2>
<p>Los recibos acreditan el cobro mensual del alquiler. AlquiGest los genera automáticamente a partir de los contratos activos.</p>

<h3>Generar recibos</h3>
<p>Desde la pestaña <strong>Recibos</strong>, pulsa <strong>Generar recibos del mes</strong>. AlquiGest creará un recibo por cada contrato activo calculando automáticamente IVA e IRPF.</p>

<div class="callout callout-tip">
  <div class="callout-icon">💡</div>
  <div class="callout-body">
    <strong>Numeración automática</strong>
    <p>El número de recibo usa el prefijo de Mi Empresa, año, mes y un secuencial único. Ejemplo: <code>REC-202601-00001</code>. Cada mes el secuencial comienza de nuevo.</p>
  </div>
</div>

<h3>Estados de un recibo</h3>
<ul>
  <li><strong>Pendiente</strong> — emitido pero no cobrado</li>
  <li><strong>Parcial</strong> — cobrado parcialmente, queda importe pendiente</li>
  <li><strong>Cobrado</strong> — pagado en su totalidad</li>
  <li><strong>Anulado</strong> — cancelado. No computa en informes pero se conserva por auditoría</li>
</ul>

<h3>Registrar un cobro</h3>
<p>Abre el detalle del recibo y pulsa <strong>Registrar pago</strong>. Puedes registrar fecha, importe (puede ser parcial), método de pago y cuenta bancaria. Un recibo puede tener múltiples pagos parciales.</p>

<!-- FACTURAS -->
<h2 id="facturas">Facturas legales</h2>

<div class="callout callout-ok">
  <div class="callout-icon">✅</div>
  <div class="callout-body">
    <strong>Cumplimiento del Reglamento de Facturación (RD 1619/2012)</strong>
    <p>Las facturas incluyen NIF/CIF del emisor y del cliente, base imponible, desglose de IVA y retención IRPF, numeración correlativa única e inmutable, y pie legal obligatorio.</p>
  </div>
</div>

<h3>Cómo generar una factura</h3>
<div class="workflow">
  <div class="wf-step"><div class="wf-step-icon">🧾</div><div class="wf-step-label">1. Abre<br>Recibos</div></div>
  <div class="wf-arrow">→</div>
  <div class="wf-step"><div class="wf-step-icon">🟣</div><div class="wf-step-label">2. Pulsa<br>botón FAC</div></div>
  <div class="wf-arrow">→</div>
  <div class="wf-step"><div class="wf-step-icon">🔍</div><div class="wf-step-label">3. Validación<br>automática</div></div>
  <div class="wf-arrow">→</div>
  <div class="wf-step"><div class="wf-step-icon">📋</div><div class="wf-step-label">4. Factura<br>emitida</div></div>
  <div class="wf-arrow">→</div>
  <div class="wf-step"><div class="wf-step-icon">📄</div><div class="wf-step-label">5. PDF / Email</div></div>
</div>

<p>En la tabla de Recibos, cada fila tiene un botón <strong style="color:#6d28d9">FAC</strong> (morado). Si el recibo aún no tiene factura, el botón genera una nueva. Si ya la tiene, abre directamente el diálogo de impresión de esa factura.</p>

<div class="callout callout-warn">
  <div class="callout-icon">⚠️</div>
  <div class="callout-body">
    <strong>Requisitos previos</strong>
    <p><strong>Mi Empresa</strong> debe tener rellenos: Nombre y NIF/CIF. El <strong>inquilino</strong> debe tener: Nombre y NIF/NIE/CIF. Si falta algún dato, el programa indica exactamente qué completar.</p>
  </div>
</div>

<h3>Numeración de facturas</h3>
<p>Las facturas tienen su propio contador independiente del número de recibo. El formato es <code>FAC-AAAAMM-NNNNN</code> con secuencial autónomo por mes. La primera factura del mes siempre es <code>FAC-AAAAMM-00001</code> aunque el recibo origen sea el número 58. Los números de factura no se derivan de los recibos.</p>
<ul>
  <li>La numeración es <strong>correlativa, única e inmutable</strong> por serie y mes.</li>
  <li>No se puede editar el número de factura desde la interfaz.</li>
  <li>No se puede generar dos facturas del mismo recibo (bloqueado en base de datos).</li>
</ul>

<h3>Inmutabilidad de las facturas</h3>
<p>Una factura emitida <strong>no se puede editar</strong>. Los datos del emisor y del cliente se copian en el momento de emisión y quedan congelados históricamente.</p>

<table class="field-table">
  <thead><tr><th>Acción</th><th>¿Permitida?</th><th>Motivo</th></tr></thead>
  <tbody>
    <tr><td>Ver / imprimir / PDF</td><td>✅ Sí</td><td>Siempre disponible</td></tr>
    <tr><td>Enviar por email</td><td>✅ Sí</td><td>Si el inquilino tiene email</td></tr>
    <tr><td>Anular factura</td><td>⚠️ Solo desde Facturas</td><td>Anulación lógica, el registro se conserva</td></tr>
    <tr><td>Modificar importes</td><td>❌ No</td><td>Inmutabilidad legal (RD 1619/2012)</td></tr>
    <tr><td>Borrar físicamente</td><td>❌ No</td><td>Inmutabilidad legal</td></tr>
    <tr><td>Generar 2ª factura del mismo recibo</td><td>❌ No</td><td>Un recibo = una factura</td></tr>
  </tbody>
</table>

<h3>IVA e IRPF en facturas</h3>
<ul>
  <li><strong>IVA = 0%:</strong> la factura indica que la operación está exenta o no sujeta a IVA.</li>
  <li><strong>IVA &gt; 0%:</strong> se muestra base imponible, tipo y cuota de IVA.</li>
  <li><strong>Con IRPF:</strong> se muestra la retención como línea separada que resta del total.</li>
</ul>

<h3>Aviso de justificante de pago</h3>
<p>Si el contrato tiene marcado el check <strong>"Mostrar aviso en recibo/factura"</strong>, la factura impresa o en PDF incluye este aviso en rojo al pie:</p>
<div style="border:1px solid #fca5a5;border-radius:6px;padding:10px 14px;background:#fff5f5;color:#c81e1e;font-weight:600;font-size:14px;margin:10px 0">
  Esta factura no constituye justificante de pago sin el correspondiente justificante bancario que acredite su abono.
</div>
<p>Útil para contratos con domiciliación bancaria donde el inquilino podría confundir la factura con un justificante de cobro. El mismo aviso también aparece en el recibo impreso si el check está activo.</p>

<h3>Módulo de Facturas</h3>
<p>La sección <strong>Facturas</strong> del menú lateral muestra todas las facturas emitidas con filtros por año, mes, inquilino y estado. Si VERI*FACTU está activo, también muestra el estado de envío a la AEAT y un botón para enviar o reintentar.</p>

<!-- GENERAR RECIBOS EN LOTE -->
<h2 id="generar">Generar Recibos en Lote</h2>
<p>La sección <strong>Generar Recibos</strong> permite emitir de una sola vez todos los recibos del mes para todos los contratos activos, sin tener que crearlos uno a uno.</p>

<div class="workflow">
  <div class="wf-step"><div class="wf-step-icon">📅</div><div class="wf-step-label">1. Selecciona<br>mes / año</div></div>
  <div class="wf-arrow">→</div>
  <div class="wf-step"><div class="wf-step-icon">👁️</div><div class="wf-step-label">2. Vista previa<br>de contratos</div></div>
  <div class="wf-arrow">→</div>
  <div class="wf-step"><div class="wf-step-icon">⚡</div><div class="wf-step-label">3. Pulsa<br>Generar</div></div>
  <div class="wf-arrow">→</div>
  <div class="wf-step"><div class="wf-step-icon">🧾</div><div class="wf-step-label">4. Recibos<br>creados</div></div>
</div>

<h3>Cómo funciona</h3>
<ol>
  <li>Elige el mes y el año para los que quieres generar recibos.</li>
  <li>Pulsa <strong>Vista previa</strong>: se muestra la lista de contratos activos que generarán recibo (con importe, inquilino e inmueble).</li>
  <li>Pulsa <strong>Generar recibos</strong>: el sistema crea automáticamente un recibo por cada contrato activo con la renta vigente.</li>
</ol>

<div class="callout callout-warn">
  <div class="callout-icon">⚠️</div>
  <div class="callout-body">
    <strong>Sin duplicados</strong>
    <p>Si ya existe un recibo para un contrato en el mismo mes, ese contrato se omite. No se crean duplicados. Puedes ejecutar el proceso varias veces de forma segura.</p>
  </div>
</div>

<!-- PLANTILLAS DOCX -->
<h2 id="plantillas">Plantillas DOCX para contratos</h2>
<p>AlquiGest puede generar contratos personalizados en formato Word (<code>.docx</code>) a partir de plantillas que tú diseñas. Crea el documento en Word con el formato y cláusulas que necesites, inserta variables donde deban aparecer los datos reales, y AlquiGest los rellena automáticamente al generar.</p>

<h3>Cómo crear una plantilla</h3>
<ol>
  <li>Diseña el contrato en Word con el formato, fuentes y cláusulas que necesites.</li>
  <li>Donde quieras que aparezca un dato (nombre del inquilino, renta, fecha…) escribe la variable entre dobles llaves: <code>{{NombreInquilino}}</code>.</li>
  <li>Guarda el archivo como <code>.docx</code>.</li>
  <li>En AlquiGest, ve a <strong>Plantillas</strong> → <strong>Nueva plantilla</strong> → sube el archivo.</li>
  <li>Desde <strong>Contratos</strong>, usa el botón de generación para crear el contrato con los datos reales.</li>
</ol>

<h3>Catálogo de variables</h3>
<p>Pulsa <strong>Ver catálogo</strong> en la sección Plantillas para ver la lista completa con descripción y ejemplo de cada variable. Las más habituales:</p>

<table class="field-table">
  <thead><tr><th>Variable</th><th>Dato que inserta</th></tr></thead>
  <tbody>
    <tr><td colspan="2" style="background:var(--gray-50);font-weight:600;font-size:12px">EMPRESA</td></tr>
    <tr><td><code>{{NombreEmpresa}}</code></td><td>Nombre de la empresa gestora</td></tr>
    <tr><td><code>{{CIFEmpresa}}</code></td><td>CIF/NIF de la empresa</td></tr>
    <tr><td><code>{{DireccionEmpresa}}</code></td><td>Dirección completa de la empresa</td></tr>
    <tr><td colspan="2" style="background:var(--gray-50);font-weight:600;font-size:12px">PROPIETARIO</td></tr>
    <tr><td><code>{{NombrePropietario}}</code></td><td>Nombre del propietario del inmueble</td></tr>
    <tr><td><code>{{NIFPropietario}}</code></td><td>NIF/CIF del propietario</td></tr>
    <tr><td colspan="2" style="background:var(--gray-50);font-weight:600;font-size:12px">INQUILINO PRINCIPAL</td></tr>
    <tr><td><code>{{NombreInquilino}}</code></td><td>Nombre completo del arrendatario</td></tr>
    <tr><td><code>{{NIFInquilino}}</code></td><td>NIF/NIE del arrendatario</td></tr>
    <tr><td><code>{{TelefonoInquilino}}</code></td><td>Teléfono o móvil del arrendatario</td></tr>
    <tr><td><code>{{EmailInquilino}}</code></td><td>Email del arrendatario</td></tr>
    <tr><td><code>{{IBANInquilino}}</code></td><td>IBAN bancario del arrendatario (vacío si no informado)</td></tr>
    <tr><td><code>{{DireccionInquilino}}</code></td><td>Dirección del arrendatario</td></tr>
    <tr><td colspan="2" style="background:var(--gray-50);font-weight:600;font-size:12px">INMUEBLE</td></tr>
    <tr><td><code>{{DireccionInmueble}}</code></td><td>Dirección completa del inmueble arrendado</td></tr>
    <tr><td><code>{{RefCatastral}}</code></td><td>Referencia catastral del inmueble</td></tr>
    <tr><td><code>{{TipoInmueble}}</code></td><td>Tipo de inmueble (Vivienda, Local…)</td></tr>
    <tr><td colspan="2" style="background:var(--gray-50);font-weight:600;font-size:12px">CONTRATO</td></tr>
    <tr><td><code>{{FechaInicio}}</code></td><td>Fecha de inicio del contrato (dd/mm/aaaa)</td></tr>
    <tr><td><code>{{FechaFin}}</code></td><td>Fecha de vencimiento del contrato</td></tr>
    <tr><td><code>{{Duracion}}</code></td><td>Duración en texto (ej: "1 año y 6 meses")</td></tr>
    <tr><td><code>{{DiaPago}}</code></td><td>Día del mes en que se paga la renta</td></tr>
    <tr><td colspan="2" style="background:var(--gray-50);font-weight:600;font-size:12px">FACTURACIÓN</td></tr>
    <tr><td><code>{{Renta}}</code></td><td>Importe mensual de la renta (con €)</td></tr>
    <tr><td><code>{{RentaLetras}}</code></td><td>Renta mensual escrita en letras</td></tr>
    <tr><td><code>{{IVA}}</code></td><td>Porcentaje de IVA del contrato</td></tr>
    <tr><td><code>{{IRPF}}</code></td><td>Porcentaje de retención IRPF</td></tr>
    <tr><td colspan="2" style="background:var(--gray-50);font-weight:600;font-size:12px">FIANZA</td></tr>
    <tr><td><code>{{Fianza}}</code></td><td>Importe de la fianza (con €)</td></tr>
    <tr><td><code>{{FianzaLetras}}</code></td><td>Fianza escrita en letras</td></tr>
    <tr><td colspan="2" style="background:var(--gray-50);font-weight:600;font-size:12px">FIADOR</td></tr>
    <tr><td><code>{{NombreFiador}}</code></td><td>Nombre del fiador solidario</td></tr>
    <tr><td><code>{{NIFFiador}}</code></td><td>NIF del fiador</td></tr>
    <tr><td colspan="2" style="background:var(--gray-50);font-weight:600;font-size:12px">SISTEMA</td></tr>
    <tr><td><code>{{FechaActual}}</code></td><td>Fecha de generación del documento (dd/mm/aaaa)</td></tr>
    <tr><td><code>{{FechaHoy}}</code></td><td>Fecha larga en español: «29 de Junio del 2026»</td></tr>
    <tr><td><code>{{AnioActual}}</code></td><td>Año actual en cuatro dígitos</td></tr>
    <tr><td><code>{{MesActual}}</code></td><td>Mes actual en texto (ej: "junio")</td></tr>
  </tbody>
</table>

<h3>Bloque multiinquilino (principal + secundarios)</h3>
<p>Usa este bloque cuando necesites listar <strong>todos los arrendatarios</strong> (principal y secundarios) con el mismo formato. Se repite una vez por cada persona, empezando por el inquilino principal:</p>
<div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;padding:12px 16px;font-family:monospace;font-size:13px;margin:8px 0;line-height:1.8">
  {{InicioMultiinquilino}}<br>
  &nbsp;&nbsp;{{NombreInquilinomultiple}}, con NIF {{NIFInquilinomultiple}}, domiciliado en {{DireccionInquilinomultiple}}.<br>
  {{/InicioMultiinquilino}}
</div>

<h3>Bloque para solo inquilinos secundarios</h3>
<p>Si solo necesitas los copropietarios (sin el titular principal), usa este bloque. Se expande una vez por cada inquilino secundario registrado:</p>
<div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;padding:12px 16px;font-family:monospace;font-size:13px;margin:8px 0;line-height:1.8">
  {{#INQUILINOS_SECUNDARIOS}}<br>
  &nbsp;&nbsp;{{InqNombre}}, con NIF {{InqNIF}}, domiciliado en {{InqDireccion}}.<br>
  {{/INQUILINOS_SECUNDARIOS}}
</div>

<h3>Fotos del contrato — <code>{{FotosContrato}}</code> y <code>{{ListaMuebles}}</code></h3>
<p>Estas dos variables abren el mismo modal al generar el documento. Pueden usarse juntas o por separado:</p>
<ul>
  <li><code>{{FotosContrato}}</code> — incluye fotografías del inmueble directamente en el Word. Colócala en un párrafo propio (sin más texto en ese párrafo).</li>
  <li><code>{{ListaMuebles}}</code> — inserta la descripción del mobiliario incluido en el inmueble. Admite texto largo con saltos de línea. Puede colocarse en cualquier párrafo.</li>
</ul>
<div class="callout callout-ok">
  <div class="callout-icon">📸</div>
  <div class="callout-body">
    <strong>Cómo funciona</strong>
    <p>Al generar, AlquiGest detecta la variable y abre un diálogo de carga de fotos. Puedes subir cuantas imágenes JPG, PNG o WebP necesites, elegir el número de columnas (1, 2 o 3) y reordenarlas antes de generar. Las fotos quedan embebidas en el <code>.docx</code> con sus proporciones originales.</p>
  </div>
</div>

<h3>Vista previa HTML</h3>
<p>El botón <strong>Vista previa</strong> muestra el contrato renderizado como HTML con todos los datos reales sustituidos, para comprobar el resultado antes de descargar el archivo.</p>

<!-- DASHBOARD -->
<h2 id="dashboard">Dashboard</h2>
<p>El <strong>Dashboard</strong> es la pantalla principal. Se muestra al abrir la aplicación y ofrece un resumen visual completo del estado de todas las fincas gestionadas.</p>

<h3>Estadísticas rápidas</h3>
<p>Cuatro tarjetas muestran de un vistazo: propietarios, inmuebles, contratos activos e importe pendiente de cobro.</p>

<h3>Gráficos de rendimiento (Chart.js)</h3>
<p>El Dashboard incluye dos gráficos automáticos:</p>
<ul>
  <li><strong>Ingresos mensuales (barras):</strong> importe cobrado mes a mes durante el año en curso.</li>
  <li><strong>Ocupación (dona):</strong> porcentaje de inmuebles ocupados vs. libres.</li>
</ul>

<h3>Alerta de revisión IPC</h3>
<p>Si algún contrato tiene la cláusula de revisión de renta y lleva más de 11 meses sin actualizarse, el Dashboard muestra un aviso amarillo con el listado de contratos afectados. Al generar el recibo de ese contrato, el asistente IPC calcula automáticamente la nueva renta sugerida según el índice aplicable.</p>

<h3>Campana de avisos</h3>
<p>El icono de campana se activa en naranja cuando algún contrato tiene su <strong>revisión anual de renta</strong> en los próximos 30 días. Al hacer clic despliega la lista de contratos afectados.</p>

<h3>Próximas renovaciones de contrato</h3>
<p>Muestra contratos activos cuya <strong>fecha de vencimiento</strong> sea en los próximos 6 meses o que ya hayan vencido.</p>
<table class="field-table">
  <tr><th>Color de fila</th><th>Situación</th></tr>
  <tr><td><span style="color:#c81e1e;font-weight:700">Rojo</span></td><td>Contrato vencido o que vence hoy</td></tr>
  <tr><td><span style="color:#c27803;font-weight:700">Naranja</span></td><td>Vence en menos de 4 meses — plazo de preaviso agotado</td></tr>
  <tr><td>Normal</td><td>Vence en 4-6 meses — todavía en plazo de preaviso</td></tr>
</table>

<h3>Últimos recibos y cobrado del mes</h3>
<p>La parte inferior muestra los 5 recibos más recientes y un resumen del importe cobrado en el mes actual, junto con los recibos pendientes y el total cobrado histórico.</p>

<!-- EXCEL -->
<h2 id="excel">Informes Excel</h2>
<p>AlquiGest exporta varios tipos de informes a formato Excel (.xlsx). Accede desde la pestaña <strong>Informes</strong> en la barra lateral.</p>

<table class="field-table">
  <tr><th>Informe</th><th>Contenido</th></tr>
  <tr><td><strong>Todos los recibos del año</strong></td><td>Listado completo con base, IVA, IRPF, total, cobrado y pendiente</td></tr>
  <tr><td><strong>Ingresos por finca</strong></td><td>Tabla con una fila por finca y columnas para cada mes del año</td></tr>
  <tr><td><strong>Ingresos por piso/unidad</strong></td><td>Desglose detallado por inmueble: finca, piso, inquilino y total mensual</td></tr>
  <tr><td><strong>Recibos pendientes</strong></td><td>Listado de todos los recibos Pendiente o Parcial con el importe que falta</td></tr>
  <tr><td><strong>Histórico de cobros</strong></td><td>Todos los pagos registrados en un año, con fecha, método y cuenta</td></tr>
  <tr><td><strong>Resumen por propietario</strong></td><td>Facturado, cobrado y pendiente agrupado por propietario y finca</td></tr>
</table>

<div class="callout callout-warn">
  <div class="callout-icon">⚠️</div>
  <div class="callout-body">
    <strong>¿No se descarga el Excel?</strong>
    <p>Si usas XAMPP es posible que la extensión ZipArchive de PHP no esté activada. Consulta el manual <a href="fixexcel.html">Solución: Error al exportar Excel</a>.</p>
  </div>
</div>

<!-- INFORMES FISCALES -->
<h2 id="excel-fiscal">Informes fiscales (IRPF / Hacienda)</h2>
<p>AlquiGest incluye tres informes para preparar la documentación fiscal de los arrendamientos según la normativa española.</p>

<div class="callout callout-warn">
  <div class="callout-icon">⚠️</div>
  <div class="callout-body">
    <strong>Estos informes son una ayuda, no asesoramiento fiscal</strong>
    <p>Revisa siempre los datos con tu asesor fiscal antes de presentar cualquier declaración. Los gastos deducibles no están en la aplicación y debes añadirlos manualmente.</p>
  </div>
</div>

<h3>1. Rendimientos trimestrales (IRPF)</h3>
<p>Ingresos por arrendamiento desglosados en cuatro trimestres (T1-T4) por inmueble y propietario. Incluye la <strong>reducción del 60 %</strong> (art. 23.2 LIRPF) cuando aplica: inmueble de vivienda habitual con contrato de duración ≥ 1 año.</p>

<h3>2. Declaración de la Renta — Capital Inmobiliario (Modelo 100)</h3>
<p>Informe anual para rellenar la sección F del Modelo 100. Una fila por inmueble con: ingresos íntegros (K), gastos deducibles (L, a rellenar manualmente), rendimiento neto (M-N) y observaciones (P). Al final del Excel se incluye un recordatorio de todos los gastos deducibles del art. 23.1 LIRPF.</p>

<h3>3. Modelo 115 / 180 — Retenciones sobre arrendamientos</h3>
<p>Para el Modelo 115 (declaración trimestral de retenciones) y el Modelo 180 (resumen anual). Solo incluye recibos con <code>importe_irpf &gt; 0</code>. Los plazos de presentación son el 20 de abril, julio, octubre y enero.</p>

<div class="callout callout-tip">
  <div class="callout-icon">💡</div>
  <div class="callout-body">
    <strong>¿Cuándo presentar el Modelo 115?</strong>
    <p>Solo cuando el inquilino es una persona jurídica (empresa, sociedad) o un empresario/profesional que alquila el inmueble para su actividad. Para inquilinos particulares sin actividad empresarial, no procede retención.</p>
  </div>
</div>

<!-- EMAIL -->
<h2 id="email">Envío por Email</h2>
<p>AlquiGest puede enviar recibos y facturas por correo electrónico al inquilino, con el documento en HTML y PDF adjunto.</p>

<h3>Configuración previa (Gmail)</h3>
<ol>
  <li>Activa la <strong>verificación en dos pasos</strong> en tu cuenta de Google</li>
  <li>Ve a <strong>Seguridad → Contraseñas de aplicaciones</strong></li>
  <li>Crea una nueva contraseña de aplicación (Google te dará 16 caracteres)</li>
  <li>Introduce tu Gmail y esa clave en <strong>Mi Empresa → Gmail usuario / contraseña</strong></li>
</ol>

<div class="callout callout-warn">
  <div class="callout-icon">⚠️</div>
  <div class="callout-body">
    <strong>No uses tu contraseña normal de Gmail</strong>
    <p>Google requiere una <em>Contraseña de Aplicación</em> específica. Tu contraseña normal no funcionará.</p>
  </div>
</div>

<!-- PDF -->
<h2 id="pdf">Generación de PDFs</h2>
<p>AlquiGest puede guardar recibos y facturas como PDF directamente desde el navegador, sin software adicional.</p>

<h3>Dos formas de generar PDF</h3>
<ol>
  <li><strong>Botón "Imprimir / PDF"</strong>: abre el diálogo de impresión del navegador. Selecciona "Guardar como PDF" como destino.</li>
  <li><strong>Botón "Guardar PDF"</strong>: genera el archivo directamente usando html2canvas + jsPDF, sin pasar por el diálogo de impresión. El archivo se descarga automáticamente.</li>
</ol>

<div class="callout callout-tip">
  <div class="callout-icon">💡</div>
  <div class="callout-body">
    <strong>Formatos A4 y A5</strong>
    <p>Puedes elegir entre formato A4 (estándar) o A5 (más compacto, ideal para imprimir dos por hoja) al imprimir tanto recibos como facturas.</p>
  </div>
</div>

<!-- VERIFACTU CONFIGURACIÓN -->
<h2 id="verifactu-config">VERI*FACTU — Configuración</h2>

<p>AlquiGest incluye integración completa con <strong>VERI*FACTU</strong> (Sistema de Información de Facturación de la AEAT, regulado por el RD 1007/2023). La integración está <strong>desactivada por defecto</strong> y solo funciona si la activas explícitamente.</p>

<div class="callout callout-ok">
  <div class="callout-icon">✅</div>
  <div class="callout-body">
    <strong>Implementación completa incluida</strong>
    <p>AlquiGest calcula el hash SHA-256 encadenado, construye el XML SOAP, lo envía a la AEAT con tu certificado digital (.p12/.pfx), y genera el código QR de verificación que aparece impreso en el PDF de la factura.</p>
  </div>
</div>

<h3>Acceder a la configuración</h3>
<p>Desde el menú lateral, grupo <strong>Configuración</strong>, haz clic en <strong>🛡️ VERI*FACTU</strong>.</p>

<h3>Pasos para activar VERI*FACTU</h3>
<ol>
  <li>Rellena el <strong>NIF del obligado de emisión</strong> y guarda la configuración.</li>
  <li>Sube tu <strong>certificado digital</strong> (.p12 o .pfx) e introduce la contraseña.</li>
  <li>Selecciona entorno <strong>Pruebas</strong> y pulsa <strong>Test conexión</strong> para verificar el acceso a la AEAT.</li>
  <li>Genera una factura y envíala desde la tabla de facturas pulsando <strong>🛡 AEAT</strong>.</li>
  <li>Cuando todo funcione en pruebas, cambia a entorno <strong>Producción</strong> y pulsa <strong>Activar</strong>.</li>
</ol>

<table class="field-table">
  <thead><tr><th>Campo de configuración</th><th>Descripción</th></tr></thead>
  <tbody>
    <tr><td><strong>Entorno AEAT</strong></td><td><code>pruebas</code> (prewww1.aeat.es) o <code>produccion</code> (www1.aeat.es). Usa pruebas hasta validar todo</td></tr>
    <tr><td><strong>NIF del obligado de emisión</strong></td><td>NIF del emisor registrado en el SIF. Normalmente igual al NIF de empresa</td></tr>
    <tr><td><strong>Certificado digital (.p12/.pfx)</strong></td><td>Se guarda en la carpeta <code>certs/</code> protegida contra acceso HTTP directo</td></tr>
    <tr><td><strong>Contraseña del certificado</strong></td><td>Contraseña del archivo .p12/.pfx</td></tr>
    <tr><td><strong>Nombre del sistema / Versión</strong></td><td>Identificación del sistema ante la AEAT. Por defecto: AlquiGest v2.0.0</td></tr>
  </tbody>
</table>

<div class="callout callout-warn">
  <div class="callout-icon">⚠️</div>
  <div class="callout-body">
    <strong>VERI*FACTU permanece inactivo hasta que lo actives</strong>
    <p>Si <code>verifactu_activo</code> no está a <code>1</code>, AlquiGest no calcula hashes ni envía nada a la AEAT. El interruptor de la pantalla VERI*FACTU controla esto. Puedes tener toda la configuración lista sin que entre en funcionamiento hasta que decidas activarlo.</p>
  </div>
</div>

<p><a href="ayuda_verifactu.php">📖 Ver guía técnica completa de integración VERI*FACTU / AEAT →</a></p>

<!-- HERRAMIENTAS DE PRODUCTIVIDAD -->
<h2 id="herramientas">Herramientas de productividad</h2>

<h3>Búsqueda global</h3>
<p>La barra de búsqueda del encabezado (icono lupa) permite localizar al instante cualquier inquilino, inmueble, finca o propietario escribiendo parte del nombre. Los resultados aparecen en un desplegable y al hacer clic navega directamente al elemento.</p>

<h3>Modo oscuro</h3>
<p>El botón de luna/sol en el encabezado activa o desactiva el modo oscuro. La preferencia se guarda automáticamente y se restaura en cada apertura.</p>

<h3>Atajos de teclado</h3>
<table class="field-table">
  <thead><tr><th>Tecla</th><th>Acción</th></tr></thead>
  <tbody>
    <tr><td><kbd>Escape</kbd></td><td>Cierra el modal abierto (con aviso si hay cambios sin guardar)</td></tr>
    <tr><td><kbd>Alt + D</kbd></td><td>Ir al Dashboard</td></tr>
    <tr><td><kbd>Alt + R</kbd></td><td>Ir a Recibos</td></tr>
    <tr><td><kbd>Alt + C</kbd></td><td>Ir a Contratos</td></tr>
    <tr><td><kbd>Alt + F</kbd></td><td>Ir a Facturas</td></tr>
    <tr><td><kbd>Alt + I</kbd></td><td>Ir a Inquilinos</td></tr>
    <tr><td><kbd>Alt + G</kbd></td><td>Ir a Generar Recibos</td></tr>
  </tbody>
</table>

<h3>Aviso de cambios sin guardar</h3>
<p>Si has modificado un formulario en un modal y pulsas Escape o el botón de cerrar sin guardar, el programa te preguntará si deseas descartar los cambios. Así se evitan pérdidas accidentales de datos.</p>

<h3>Filtros avanzados en Recibos</h3>
<p>La sección Recibos incluye una barra de filtros que permite combinar: estado del recibo, inquilino, y fechas desde/hasta. Los resultados se paginan de 30 en 30. El botón de paginación aparece en la parte inferior de la tabla.</p>

<h3>Historial del inquilino</h3>
<p>Desde la lista de Inquilinos, el botón <strong>Historial</strong> abre un panel con tres pestañas: todos los contratos que ha tenido, todos sus recibos y todas sus facturas. Incluye un resumen con el total facturado, cobrado y pendiente.</p>

<!-- CALENDARIO DE COBROS -->
<h2 id="calendario">Calendario de Cobros</h2>
<p>Accesible desde el menú lateral. Muestra un calendario mensual con todos los recibos vencidos en ese mes, agrupados por día. Los recibos pendientes aparecen en rojo, los cobrados en verde y los parciales en naranja. Los botones anterior/siguiente permiten navegar entre meses.</p>

<!-- MOROSIDAD -->
<h2 id="morosidad">Informe de Morosidad</h2>
<p>Accesible desde el menú lateral. Lista todos los recibos en estado <em>Pendiente</em> o <em>Parcial</em> con más de 30 días de antigüedad desde su fecha límite, ordenados por importe pendiente.</p>
<p>El botón <strong>Exportar PDF</strong> genera un informe formal de morosidad en formato A4 con los datos de la empresa, fecha de emisión y listado detallado por inquilino con importes acumulados.</p>
<p>El filtro <strong>Incluir recibos con menos de 30 días</strong> amplía el listado para mostrar también los recibos recientes pendientes, útil para anticipar situaciones de morosidad.</p>

<!-- GENERAR RECIBOS EN LOTE -->
<h2 id="generar">Generar Recibos en Lote</h2>
<p>La sección <strong>Generar Recibos</strong> del menú lateral permite crear en un solo clic todos los recibos de un mes para toda la cartera o un subconjunto de inmuebles.</p>

<h3>Parámetros de generación</h3>
<table class="field-table">
  <thead><tr><th>Opción</th><th>Descripción</th></tr></thead>
  <tbody>
    <tr><td><strong>Mes / Año</strong></td><td>El período para el que se generan los recibos</td></tr>
    <tr><td><strong>Ámbito — Toda la cartera</strong></td><td>Genera un recibo para cada contrato activo sin importar la finca</td></tr>
    <tr><td><strong>Ámbito — Por finca/edificio</strong></td><td>Solo los contratos activos de la finca seleccionada</td></tr>
    <tr><td><strong>Ámbito — Por piso concreto</strong></td><td>Genera el recibo de un único inmueble</td></tr>
  </tbody>
</table>

<h3>Comportamiento</h3>
<ul>
  <li>Si ya existe un recibo para un contrato en ese mes, no se genera un duplicado (protección automática).</li>
  <li>Muestra una previsualización de los recibos que se van a crear antes de confirmar.</li>
  <li>Los recibos generados quedan en estado <strong>Pendiente</strong> listos para cobrar.</li>
</ul>

<div class="callout callout-tip">
  <div class="callout-icon">💡</div>
  <div class="callout-body">
    <strong>También puedes generar recibos individualmente</strong>
    <p>Desde la sección <strong>Contratos</strong>, el botón <strong>Generar recibo</strong> de cada fila crea el recibo del mes actual para ese contrato específico.</p>
  </div>
</div>

<!-- PLANTILLAS DOCX -->
<h2 id="plantillas">Plantillas DOCX</h2>
<p>AlquiGest incluye un motor de plantillas Word (.docx) que permite generar contratos, comunicaciones y cualquier documento personalizado con los datos reales de cada contrato, inmueble, propietario e inquilino.</p>

<div class="callout callout-ok">
  <div class="callout-icon">✅</div>
  <div class="callout-body">
    <strong>Sin software adicional</strong>
    <p>Las plantillas se generan como archivos .docx descargables directamente desde el navegador. No necesitas LibreOffice ni ningún software instalado.</p>
  </div>
</div>

<h3>Cómo funciona</h3>
<div class="workflow">
  <div class="wf-step"><div class="wf-step-icon">📝</div><div class="wf-step-label">1. Crea una<br>plantilla Word</div></div>
  <div class="wf-arrow">→</div>
  <div class="wf-step"><div class="wf-step-icon">⬆</div><div class="wf-step-label">2. Súbela en<br>Plantillas</div></div>
  <div class="wf-arrow">→</div>
  <div class="wf-step"><div class="wf-step-icon">📄</div><div class="wf-step-label">3. Pulsa DOCX<br>en el contrato</div></div>
  <div class="wf-arrow">→</div>
  <div class="wf-step"><div class="wf-step-icon">⬇</div><div class="wf-step-label">4. Descarga el<br>documento</div></div>
</div>

<h3>Gestión de plantillas</h3>
<p>Desde el menú lateral → <strong>Plantillas</strong>. Cada plantilla tiene:</p>
<table class="field-table">
  <thead><tr><th>Acción</th><th>Descripción</th></tr></thead>
  <tbody>
    <tr><td><strong>Vista previa</strong></td><td>Muestra el documento con variables sustituidas (texto, sin imágenes) en pantalla</td></tr>
    <tr><td><strong>⬇ DOCX</strong></td><td>Descarga el archivo de plantilla original tal como se subió</td></tr>
    <tr><td><strong>Renombrar</strong></td><td>Cambia el nombre visible de la plantilla</td></tr>
    <tr><td><strong>Duplicar</strong></td><td>Crea una copia de la plantilla con nuevo nombre</td></tr>
    <tr><td><strong>Activar / Desactivar</strong></td><td>Las plantillas desactivadas no aparecen en el selector al generar documentos</td></tr>
    <tr><td><strong>⭐ Por defecto</strong></td><td>Esta plantilla se usará automáticamente cuando generes un documento sin elegir explícitamente</td></tr>
    <tr><td><strong>Eliminar</strong></td><td>Borra la plantilla permanentemente</td></tr>
  </tbody>
</table>

<h3>Variables disponibles</h3>
<p>Escribe las variables entre dobles llaves en tu documento Word. Ejemplo: <code>{{NombreInquilino}}</code>. Al generar el documento, cada variable se sustituye por el dato real del contrato.</p>

<table class="field-table">
  <thead><tr><th>Variable</th><th>Contenido</th></tr></thead>
  <tbody>
    <tr><td colspan="2" style="background:#f1f5f9;font-weight:700">Empresa</td></tr>
    <tr><td><code>{{NombreEmpresa}}</code></td><td>Nombre o razón social de la empresa administradora</td></tr>
    <tr><td><code>{{CIFEmpresa}}</code></td><td>CIF/NIF de la empresa</td></tr>
    <tr><td><code>{{DireccionEmpresa}}</code></td><td>Dirección completa de la empresa</td></tr>
    <tr><td><code>{{TelefonoEmpresa}}</code></td><td>Teléfono de la empresa</td></tr>
    <tr><td><code>{{EmailEmpresa}}</code></td><td>Email de la empresa</td></tr>
    <tr><td><code>{{IBANEmpresa}}</code></td><td>IBAN de la empresa</td></tr>
    <tr><td colspan="2" style="background:#f1f5f9;font-weight:700">Propietario</td></tr>
    <tr><td><code>{{NombrePropietario}}</code></td><td>Nombre del propietario de la finca</td></tr>
    <tr><td><code>{{NIFPropietario}}</code></td><td>NIF del propietario</td></tr>
    <tr><td><code>{{DireccionPropietario}}</code></td><td>Dirección del propietario</td></tr>
    <tr><td colspan="2" style="background:#f1f5f9;font-weight:700">Inquilino principal</td></tr>
    <tr><td><code>{{NombreInquilino}}</code></td><td>Nombre completo del inquilino</td></tr>
    <tr><td><code>{{NIFInquilino}}</code></td><td>NIF/NIE/CIF del inquilino</td></tr>
    <tr><td><code>{{TelefonoInquilino}}</code></td><td>Teléfono del inquilino</td></tr>
    <tr><td><code>{{EmailInquilino}}</code></td><td>Email del inquilino</td></tr>
    <tr><td><code>{{IBANInquilino}}</code></td><td>IBAN bancario del inquilino (vacío si no informado). Ejemplo: <code>ES98 2100 0418 4502 0005 1332</code></td></tr>
    <tr><td><code>{{DireccionInquilino}}</code></td><td>Dirección particular del inquilino</td></tr>
    <tr><td colspan="2" style="background:#f1f5f9;font-weight:700">Inmueble</td></tr>
    <tr><td><code>{{DireccionInmueble}}</code></td><td>Dirección completa del inmueble alquilado</td></tr>
    <tr><td><code>{{RefCatastral}}</code></td><td>Referencia catastral</td></tr>
    <tr><td><code>{{TipoInmueble}}</code></td><td>Tipo: vivienda, local, garaje, etc.</td></tr>
    <tr><td><code>{{MunicipioInmueble}}</code></td><td>Municipio del inmueble</td></tr>
    <tr><td><code>{{ProvinciaInmueble}}</code></td><td>Provincia del inmueble</td></tr>
    <tr><td colspan="2" style="background:#f1f5f9;font-weight:700">Contrato</td></tr>
    <tr><td><code>{{FechaInicio}}</code></td><td>Fecha de inicio del contrato (dd/mm/aaaa)</td></tr>
    <tr><td><code>{{FechaFin}}</code></td><td>Fecha de fin del contrato</td></tr>
    <tr><td><code>{{Duracion}}</code></td><td>Duración calculada en texto (ej: "1 año y 6 meses")</td></tr>
    <tr><td><code>{{MotivoTemporada}}</code></td><td>Causa de la temporalidad del contrato (solo contratos &lt; 1 año)</td></tr>
    <tr><td><code>{{MetodoRevision}}</code></td><td>Cláusula de revisión de renta (IPC, % fijo…)</td></tr>
    <tr><td><code>{{DiaPago}}</code></td><td>Día del mes en que vence el pago</td></tr>
    <tr><td colspan="2" style="background:#f1f5f9;font-weight:700">Facturación</td></tr>
    <tr><td><code>{{Renta}}</code></td><td>Renta base mensual en euros (ej: 750,00 €)</td></tr>
    <tr><td><code>{{RentaLetras}}</code></td><td>Renta en texto (ej: "Setecientos cincuenta euros")</td></tr>
    <tr><td><code>{{IVA}}</code></td><td>Porcentaje de IVA aplicable</td></tr>
    <tr><td><code>{{IRPF}}</code></td><td>Porcentaje de retención IRPF</td></tr>
    <tr><td colspan="2" style="background:#f1f5f9;font-weight:700">Fianza</td></tr>
    <tr><td><code>{{Fianza}}</code></td><td>Importe de la fianza</td></tr>
    <tr><td><code>{{FianzaLetras}}</code></td><td>Fianza en texto</td></tr>
    <tr><td colspan="2" style="background:#f1f5f9;font-weight:700">Fiador solidario</td></tr>
    <tr><td><code>{{NombreFiador}}</code></td><td>Nombre completo del fiador solidario</td></tr>
    <tr><td><code>{{NIFFiador}}</code></td><td>NIF del fiador</td></tr>
    <tr><td><code>{{DireccionFiador}}</code></td><td>Dirección del fiador</td></tr>
    <tr><td colspan="2" style="background:#f1f5f9;font-weight:700">Fotos</td></tr>
    <tr><td><code>{{FotosContrato}}</code></td><td>Tabla de fotografías embebidas. Ver sección <a href="#fotoscontrato">FotosContrato</a></td></tr>
    <tr><td><code>{{ListaMuebles}}</code></td><td>Descripción del mobiliario del inmueble (texto libre multilínea, abre modal al generar)</td></tr>
    <tr><td colspan="2" style="background:#f1f5f9;font-weight:700">Sistema</td></tr>
    <tr><td><code>{{FechaActual}}</code></td><td>Fecha de generación del documento (dd/mm/aaaa)</td></tr>
    <tr><td><code>{{FechaHoy}}</code></td><td>Fecha larga en español: «29 de Junio del 2026» (día sin cero, mes con mayúscula)</td></tr>
    <tr><td><code>{{AnioActual}}</code></td><td>Año en curso (4 dígitos)</td></tr>
    <tr><td><code>{{MesActual}}</code></td><td>Mes en curso en texto en minúsculas (ej: "junio")</td></tr>
  </tbody>
</table>

<div class="callout callout-warn">
  <div class="callout-icon">⚠️</div>
  <div class="callout-body">
    <strong>Las variables distinguen mayúsculas de minúsculas</strong>
    <p>Escribe exactamente <code>{{NombreInquilino}}</code>. Si escribes <code>{{NOMBREINQUILINO}}</code> o <code>{{nombreinquilino}}</code> no funcionará. Usa el botón <strong>{ } Variables</strong> en la sección Plantillas para copiar los nombres correctos.</p>
  </div>
</div>

<h3>Bloque multiinquilino — todos los arrendatarios</h3>
<p>Usa este bloque cuando necesites incluir <strong>todos los firmantes</strong> (inquilino principal + secundarios) con el mismo formato. El bloque se repite una vez por persona, comenzando siempre por el inquilino principal:</p>
<pre style="background:#1e293b;color:#e2e8f0;padding:14px;border-radius:8px;font-size:13px;overflow-x:auto">{{InicioMultiinquilino}}
{{NombreInquilinomultiple}}, con NIF {{NIFInquilinomultiple}}, domiciliado en {{DireccionInquilinomultiple}}.
{{/InicioMultiinquilino}}</pre>
<p>Si solo hay un inquilino, el bloque se expande una vez. Si no hay ninguno, el bloque desaparece por completo.</p>

<p>Variables disponibles dentro del bloque multiinquilino:</p>
<table class="field-table">
  <thead><tr><th>Variable</th><th>Contenido</th></tr></thead>
  <tbody>
    <tr><td><code>{{NombreInquilinomultiple}}</code></td><td>Nombre de cada arrendatario (principal o secundario)</td></tr>
    <tr><td><code>{{NIFInquilinomultiple}}</code></td><td>NIF/NIE de cada arrendatario</td></tr>
    <tr><td><code>{{DireccionInquilinomultiple}}</code></td><td>Dirección de cada arrendatario</td></tr>
  </tbody>
</table>

<h3>Bloque de solo inquilinos secundarios</h3>
<p>Si solo necesitas los copropietarios adicionales (sin el titular principal), usa el bloque <code>{{#INQUILINOS_SECUNDARIOS}}</code>. Todo lo que esté entre los marcadores se repite una vez por cada inquilino secundario registrado:</p>
<pre style="background:#1e293b;color:#e2e8f0;padding:14px;border-radius:8px;font-size:13px;overflow-x:auto">{{#INQUILINOS_SECUNDARIOS}}
{{InqNombre}}, con NIF {{InqNIF}}, domiciliado en {{InqDireccion}}.
{{/INQUILINOS_SECUNDARIOS}}</pre>
<p>Si el contrato no tiene inquilinos secundarios, el bloque desaparece por completo sin dejar espacios vacíos.</p>

<p>Variables disponibles dentro del bloque solo-secundarios:</p>
<table class="field-table">
  <thead><tr><th>Variable</th><th>Contenido</th></tr></thead>
  <tbody>
    <tr><td><code>{{InqNombre}}</code></td><td>Nombre del inquilino secundario</td></tr>
    <tr><td><code>{{InqNIF}}</code></td><td>NIF del inquilino secundario</td></tr>
    <tr><td><code>{{InqDireccion}}</code></td><td>Dirección del inquilino secundario</td></tr>
    <tr><td><code>{{InqTelefono}}</code></td><td>Teléfono del inquilino secundario</td></tr>
    <tr><td><code>{{InqEmail}}</code></td><td>Email del inquilino secundario</td></tr>
  </tbody>
</table>

<h3 id="fotoscontrato">FotosContrato — tabla de fotografías embebida</h3>
<p>La variable especial <code>{{FotosContrato}}</code> permite incrustar una galería de fotografías directamente en el documento Word generado. Es ideal para contratos de inmuebles donde se quiera acreditar el estado inicial de la vivienda.</p>

<h4>Cómo usarla</h4>
<ol>
  <li>En tu plantilla Word, escribe <code>{{FotosContrato}}</code> en un párrafo <strong>solo</strong>, sin ningún otro texto en esa línea.</li>
  <li>Al generar el documento desde un contrato, AlquiGest detecta automáticamente si la plantilla tiene esta variable.</li>
  <li>Si la detecta, muestra el <strong>diálogo de fotografías</strong> antes de generar el documento.</li>
  <li>Sube las imágenes (JPG, PNG o WebP), ajusta el número de columnas y pulsa <strong>Generar DOCX</strong>.</li>
</ol>

<h4>Opciones del diálogo de fotos</h4>
<table class="field-table">
  <thead><tr><th>Opción</th><th>Descripción</th></tr></thead>
  <tbody>
    <tr><td><strong>Número de columnas</strong></td><td>1, 2 o 3 columnas en la tabla de fotos. 2 columnas es el valor por defecto (aprox. 7,8 cm por foto en A4)</td></tr>
    <tr><td><strong>Área de subida</strong></td><td>Arrastra imágenes o haz clic para seleccionarlas. Puedes añadir varias a la vez</td></tr>
    <tr><td><strong>◄ ► (reordenar)</strong></td><td>Cambia el orden de las fotos en la tabla antes de generar el documento</td></tr>
    <tr><td><strong>× (eliminar)</strong></td><td>Quita una foto de la selección</td></tr>
  </tbody>
</table>

<div class="callout callout-tip">
  <div class="callout-icon">💡</div>
  <div class="callout-body">
    <strong>Formatos admitidos y tamaños</strong>
    <p>JPG, JPEG y PNG se procesan directamente. WebP se convierte automáticamente a JPEG si el servidor tiene soporte GD. No hay límite estricto de número de fotos; se han probado hasta 50 imágenes sin problemas.</p>
  </div>
</div>

<!-- PARÁMETROS / CONFIGURACIÓN -->
<h2 id="configuracion">Parámetros de configuración</h2>
<p>Accesible desde el menú lateral → <strong>Parámetros</strong>. Organizado en seis pestañas:</p>

<table class="field-table">
  <thead><tr><th>Pestaña</th><th>Contenido</th></tr></thead>
  <tbody>
    <tr><td><strong>Dashboard</strong></td><td>Activa o desactiva cada widget del panel principal: tarjetas KPI, alertas, gráficos, tablas de renovaciones y revisiones, últimos recibos, previsión de cobros y actividad reciente</td></tr>
    <tr><td><strong>Paginación</strong></td><td>Número de filas por página en cada sección (Propietarios, Fincas, Inmuebles, Inquilinos, Contratos, Recibos, Facturas)</td></tr>
    <tr><td><strong>Botones</strong></td><td>Muestra u oculta botones de acción en las tablas (Contratos, Recibos, Facturas, Inquilinos, Propietarios, Fincas, Inmuebles). Útil para simplificar la interfaz o evitar acciones accidentales</td></tr>
    <tr><td><strong>WhatsApp</strong></td><td>Activa el envío de recibos por WhatsApp y la generación automática de PDF al pulsar el botón</td></tr>
    <tr><td><strong>VERI*FACTU</strong></td><td>Configuración del sistema de facturación electrónica de la AEAT. Ver <a href="#verifactu-config">sección VERI*FACTU</a></td></tr>
    <tr><td><strong>Documentos</strong></td><td>Activa el módulo de plantillas DOCX, controla si se muestra el estado de plantillas en el Dashboard</td></tr>
  </tbody>
</table>

<p>Cada parámetro tiene un botón <strong>?</strong> que explica en detalle para qué sirve y cuándo conviene activarlo o desactivarlo.</p>

<!-- FAQ -->
<h2 id="faq">Preguntas frecuentes</h2>

<h3>¿Puedo instalar AlquiGest en un servidor real (no localhost)?</h3>
<p>Sí, pero el programa está configurado por defecto para funcionar solo en <code>localhost</code>. Para uso en servidor real necesitarás editar <code>assets/php/api.php</code> y <code>assets/php/plantillas.php</code> eliminando la restricción <code>requireLocalhost()</code>. No se recomienda exponer la aplicación a internet sin añadir autenticación.</p>

<h3>¿Cómo hago una copia de seguridad?</h3>
<p>Desde <strong>Mi Empresa</strong>, pulsa <strong>Descargar backup JSON</strong>. El archivo descargado contiene todos los datos en formato JSON. Guárdalo en un lugar seguro. Puedes restaurarlo importando el JSON desde <code>install.php</code>.</p>

<h3>¿Por qué mis plantillas Word pierden el formato tras generar el DOCX?</h3>
<p>Al sustituir variables, AlquiGest reconstruye el contenido de cada párrafo en un único bloque de texto. Esto puede eliminar formatos aplicados solo a una parte de la palabra que contiene la variable (negrita parcial, color de letra, etc.). Para evitarlo, aplica el formato a todo el párrafo, no solo a la variable.</p>

<h3>¿Puedo tener varios contratos activos en el mismo inmueble?</h3>
<p>No. Un inmueble solo puede tener un contrato activo a la vez. Para cambiar de inquilino, primero da de baja el contrato actual y luego crea uno nuevo.</p>

<h3>¿Cómo funciona la revisión IPC?</h3>
<p>Cuando generas el recibo de un contrato con revisión de renta activa, AlquiGest detecta si ha pasado un año desde la última actualización y muestra el asistente IPC. El asistente consulta el IPC publicado por el INE y calcula la nueva renta sugerida. Puedes aceptarla, modificarla o rechazarla.</p>

<h3>¿Puedo generar facturas sin IVA?</h3>
<p>Sí. Si el contrato tiene IVA = 0%, la factura se genera con base imponible y total iguales, sin desglose de IVA. Esto es lo habitual para arrendamientos de vivienda habitual.</p>

<h3>¿Qué pasa si el inquilino no tiene email?</h3>
<p>El botón de envío por email aparece en gris y no puede pulsarse. Para enviarlo, añade el email del inquilino en su ficha.</p>

<h3>¿Cómo convierto una plantilla DOCX a PDF?</h3>
<p>AlquiGest no incluye conversión DOCX → PDF porque requiere LibreOffice instalado en el servidor, que no está disponible en MAMP/Windows. Abre el DOCX descargado con Word o LibreOffice y exporta a PDF desde ahí.</p>

<!-- LIMITACIONES -->
<h2 id="limitaciones">Limitaciones conocidas</h2>

<table class="field-table">
  <thead><tr><th>Limitación</th><th>Alternativa</th></tr></thead>
  <tbody>
    <tr><td>Sin conversión automática DOCX → PDF</td><td>Abrir el DOCX descargado con Word/LibreOffice y exportar</td></tr>
    <tr><td>Solo un contrato activo por inmueble</td><td>Diseño por modelo de negocio. Dar de baja el anterior antes de crear otro</td></tr>
    <tr><td>Email solo vía Gmail con contraseña de aplicación</td><td>También se puede usar cualquier proveedor SMTP editando <code>api.php</code></td></tr>
    <tr><td>WebP en plantillas requiere GD con soporte WebP</td><td>Convertir las imágenes a JPG/PNG antes de subirlas</td></tr>
    <tr><td>Solo funciona en localhost por defecto</td><td>Editar la restricción en los PHP backends para uso en servidor</td></tr>
    <tr><td>Sin multi-usuario ni control de acceso</td><td>Acceso restringido por diseño a la red local</td></tr>
    <tr><td>Sin aplicación móvil</td><td>Funciona en navegador móvil pero está optimizado para escritorio</td></tr>
  </tbody>
</table>

<!-- IMPORTACIÓN CSV -->
<h2 id="importar">Importación masiva desde CSV</h2>
<p>Accesible desde el menú lateral → <strong>Importar datos</strong>. Permite añadir registros en bloque desde un archivo CSV (exportado de Excel u otro programa).</p>

<h3>Formato del CSV</h3>
<p>La primera fila debe ser la cabecera con los nombres de los campos. El separador puede ser coma o punto y coma. El sistema detecta automáticamente cuál usar.</p>

<h3>Tipos de datos importables</h3>
<ul>
  <li>Propietarios</li>
  <li>Inquilinos</li>
  <li>Fincas</li>
  <li>Inmuebles</li>
</ul>

<h3>Proceso de importación</h3>
<ol>
  <li>Selecciona o arrastra el archivo CSV sobre la zona de importación.</li>
  <li>El programa muestra una previsualización de los primeros registros y el número total.</li>
  <li>Elige el tipo de datos a importar (propietarios, inquilinos…).</li>
  <li>Pulsa <strong>Importar</strong>. El programa añade los registros uno a uno y muestra el progreso.</li>
</ol>

<div class="callout callout-warn">
  <div class="callout-icon">⚠️</div>
  <div class="callout-body">
    <strong>La importación no sobrescribe registros existentes</strong>
    <p>Todos los registros del CSV se añaden como nuevos. Si ya existe un registro con el mismo nombre, se creará un duplicado. Revisa la previsualización antes de confirmar.</p>
  </div>
</div>

<!-- COPIA DE SEGURIDAD -->
<h2 id="backup">Copia de seguridad</h2>
<p>AlquiGest ofrece dos formas de hacer copia de seguridad:</p>

<h3>1. Backup JSON desde Mi Empresa (recomendado)</h3>
<p>Desde <strong>Mi Empresa</strong>, pulsa el botón <strong>Descargar backup JSON</strong>. Se descarga un fichero <code>alquigest_backup_AAAA-MM-DD.json</code> con todos los datos de la base de datos. Para restaurar los datos en otra instalación, ve a <code>install.php</code> → <em>Restaurar desde JSON</em>.</p>

<h3>2. Backup SQL desde install.php</h3>
<p>Desde <code>http://localhost/AlquiGest_v2/install.php</code> puedes descargar un volcado SQL completo de la base de datos o restaurar desde un backup anterior.</p>

<div class="callout callout-tip">
  <div class="callout-icon">💡</div>
  <div class="callout-body">
    <strong>Periodicidad recomendada</strong>
    <p>Haz una copia de seguridad al menos una vez a la semana y guárdala en una ubicación diferente al servidor (USB, nube, etc.). Los datos de la base de datos local no se sincronizan con ningún servidor externo.</p>
  </div>
</div>

<!-- CERTIFICADO IRPF -->
<h2 id="irpf">Certificado de retenciones IRPF</h2>
<p>Desde la lista de <strong>Propietarios</strong>, los propietarios con IRPF marcado como "S" muestran un botón <strong>Cert. IRPF</strong>. Este botón genera un PDF con el certificado de retenciones del año, que acredita las cantidades retenidas e ingresadas a cuenta del IRPF del arrendador.</p>
<p>El certificado incluye: datos del retenedor (empresa), datos del perceptor (propietario), año fiscal, relación de inmuebles con rentas y retenciones, y total anual. Es apto para que el propietario lo adjunte a su declaración de la renta.</p>

<!-- FAQ (sección unificada) -->
<h2 id="faq">Preguntas frecuentes</h2>

<h3>¿Puedo instalar AlquiGest en un servidor real (no localhost)?</h3>
<p>Sí, pero el programa está configurado por defecto para funcionar solo en <code>localhost</code>. Para uso en servidor real necesitarás editar <code>assets/php/api.php</code> y <code>assets/php/plantillas.php</code> eliminando la restricción <code>requireLocalhost()</code>. No se recomienda exponer la aplicación a internet sin añadir autenticación.</p>

<h3>¿Puedo tener varios contratos activos para el mismo inquilino?</h3>
<p>Sí. Un inquilino puede alquilar varios inmuebles distintos al mismo tiempo. Lo que no puede haber es dos contratos activos para el <em>mismo</em> inmueble.</p>

<h3>¿Qué pasa si un contrato está activo pero la fecha de fin ya ha pasado?</h3>
<p>AlquiGest no finaliza los contratos automáticamente. Debes marcarlo manualmente como "Finalizado" cuando el inquilino abandone el inmueble.</p>

<h3>¿Cómo cambio la numeración de los recibos?</h3>
<p>El prefijo se configura en <strong>Mi Empresa → Prefijo de recibos</strong>. El número secuencial es automático y no puede modificarse para garantizar la trazabilidad contable.</p>

<h3>¿Puedo eliminar un recibo que generé por error?</h3>
<p>Sí. Desde el detalle del recibo puedes marcarlo como <strong>Anulado</strong>. Los recibos anulados no aparecen en los informes pero se conservan en la base de datos por auditoría.</p>

<h3>¿Cómo funciona la revisión IPC?</h3>
<p>Cuando generas el recibo de un contrato con revisión de renta activa, AlquiGest detecta si ha pasado un año desde la última actualización y muestra el asistente IPC/IRAV. El asistente consulta el índice publicado por el INE y calcula la nueva renta sugerida. Puedes aceptarla, modificarla o rechazarla.</p>

<h3>¿Por qué mis plantillas Word pierden el formato tras generar el DOCX?</h3>
<p>Al sustituir variables, AlquiGest reconstruye el contenido de cada párrafo en un único bloque de texto. Esto puede eliminar formatos aplicados solo a una parte de la palabra que contiene la variable (negrita parcial, color de letra, etc.). Para evitarlo, aplica el formato a todo el párrafo, no solo a la variable.</p>

<h3>¿Puedo generar facturas sin IVA?</h3>
<p>Sí. Si el contrato tiene IVA = 0%, la factura se genera con base imponible y total iguales, sin desglose de IVA. Esto es lo habitual para arrendamientos de vivienda habitual.</p>

<h3>¿Qué pasa si el inquilino no tiene email?</h3>
<p>El botón de envío por email aparece en gris y no puede pulsarse. Para enviarlo, añade el email del inquilino en su ficha.</p>

<h3>¿Cómo convierto una plantilla DOCX a PDF?</h3>
<p>AlquiGest no incluye conversión DOCX → PDF porque requiere LibreOffice instalado en el servidor, que no está disponible en MAMP/Windows. Abre el DOCX descargado con Word o LibreOffice y exporta a PDF desde ahí.</p>

<h3>¿Los datos se sincronizan con algún servidor externo?</h3>
<p>No. AlquiGest es <strong>100% local</strong>. Todos los datos se guardan en la base de datos MySQL de tu servidor local. No se envía ninguna información a internet, salvo los correos que envíes y, si lo activas, los registros VERI*FACTU a la AEAT.</p>

<h3>¿Cómo actualizo el programa a una versión nueva?</h3>
<p>Copia los archivos nuevos en la carpeta del proyecto sobreescribiendo los existentes. <strong>No ejecutes install.php</strong> porque borraría todos tus datos. Si hay cambios en la base de datos, encontrarás un archivo <code>migration_*.sql</code> con las instrucciones de actualización.</p>

<h3>¿Necesito activar VERI*FACTU?</h3>
<p>Depende de tu situación fiscal. Para arrendadores de vivienda habitual sin IVA y sin actividad empresarial, probablemente no. Para locales comerciales con IVA o si llevas la gestión como actividad económica, consulta con tu asesor fiscal. AlquiGest está listo para activarlo cuando lo necesites.</p>

<!-- LIMITACIONES -->
<h2 id="limitaciones">Limitaciones conocidas</h2>

<table class="field-table">
  <thead><tr><th>Limitación</th><th>Alternativa</th></tr></thead>
  <tbody>
    <tr><td>Sin conversión automática DOCX → PDF</td><td>Abrir el DOCX descargado con Word/LibreOffice y exportar</td></tr>
    <tr><td>Solo un contrato activo por inmueble</td><td>Diseño por modelo de negocio. Dar de baja el anterior antes de crear otro</td></tr>
    <tr><td>Email solo vía Gmail con contraseña de aplicación</td><td>También se puede usar cualquier proveedor SMTP editando <code>assets/php/email.php</code></td></tr>
    <tr><td>WebP en plantillas requiere GD con soporte WebP</td><td>Convertir las imágenes a JPG/PNG antes de subirlas</td></tr>
    <tr><td>Solo funciona en localhost por defecto</td><td>Editar la restricción <code>requireLocalhost()</code> en los PHP backends</td></tr>
    <tr><td>Sin multi-usuario ni control de acceso</td><td>Acceso restringido por diseño a la red local</td></tr>
    <tr><td>Sin aplicación móvil nativa</td><td>Funciona en navegador móvil pero está optimizado para escritorio</td></tr>
  </tbody>
</table>

<p style="margin-top:48px;padding-top:20px;border-top:1px solid var(--gray-200);font-size:12px;color:var(--gray-400)">
  AlquiGest v<?= htmlspecialchars($cfg['version']) ?> · Manual de usuario · Junio 2026
</p>
</main>
</div>

<script>
const sections = document.querySelectorAll('.doc-main h2[id]');
const links     = document.querySelectorAll('.doc-nav .doc-nav-item');
sections.forEach(s => {
  new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        links.forEach(l => l.classList.remove('active'));
        const l = document.querySelector(`.doc-nav a[href="#${e.target.id}"]`);
        if (l) l.classList.add('active');
      }
    });
  }, { rootMargin: '-20% 0px -70% 0px' }).observe(s);
});
</script>
</body>
</html>
