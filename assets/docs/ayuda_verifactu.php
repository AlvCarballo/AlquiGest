<?php $cfg = require __DIR__ . '/../php/config.php'; ?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AlquiGest – Guía VERI*FACTU / AEAT</title>
<link rel="stylesheet" href="../css/main.css">
</head>
<body style="background:var(--gray-50)">
<div class="doc-page">

<nav class="doc-nav">
  <div class="doc-nav-logo">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#6d28d9" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
      <div class="doc-nav-logo-title">AlquiGest</div>
    </div>
    <div class="doc-nav-logo-sub">Guía VERI*FACTU / AEAT</div>
  </div>
  <div style="padding:10px 0">
    <a class="doc-nav-item" href="ayuda.php">← Manual general</a>
    <a class="doc-nav-item" href="../../AlquiGest.php">← Abrir AlquiGest</a>
    <div style="height:1px;background:#374151;margin:6px 16px 10px"></div>
    <div class="doc-nav-section-title">Contenido</div>
    <a class="doc-nav-item" href="#que-es">¿Qué es VERI*FACTU?</a>
    <a class="doc-nav-item" href="#estado-actual">Estado en AlquiGest</a>
    <a class="doc-nav-item" href="#configurar">Configurar AlquiGest</a>
    <a class="doc-nav-item" href="#activar">Activar / desactivar</a>
    <a class="doc-nav-item" href="#flujo">Flujo de una factura</a>
    <a class="doc-nav-item" href="#estructura-bd">Estructura en BD</a>
    <a class="doc-nav-item" href="#hash">Hash encadenado</a>
    <a class="doc-nav-item" href="#soap">Envío SOAP a AEAT</a>
    <a class="doc-nav-item" href="#qr">QR de verificación</a>
    <a class="doc-nav-item" href="#certificado">Certificado digital</a>
    <a class="doc-nav-item" href="#donde-modificar">Archivos clave</a>
    <a class="doc-nav-item" href="#referencias">Referencias oficiales</a>
  </div>
</nav>

<main class="doc-main">

<div class="doc-hero doc-hero-vf">
  <div class="doc-hero-label">AlquiGest v2.0.0 · Integración fiscal avanzada</div>
  <h1>Guía de integración VERI*FACTU y AEAT</h1>
  <p>Cómo funciona la integración de AlquiGest con el sistema de verificación de facturas de la Agencia Tributaria española.</p>
</div>

<div class="callout callout-ok">
  <div class="callout-icon">✅</div>
  <div class="callout-body">
    <strong>Implementación completa disponible en AlquiGest v2.0.0</strong>
    <p>AlquiGest tiene implementado el hash SHA-256 encadenado, el envío SOAP a la AEAT, la firma con certificado digital (.p12/.pfx), el QR de verificación en el PDF y la pantalla completa de configuración. Solo tienes que subir tu certificado y activarlo.</p>
  </div>
</div>

<!-- QUÉ ES -->
<h2 id="que-es">¿Qué es VERI*FACTU?</h2>

<p><strong>VERI*FACTU</strong> (también llamado SIF – Sistema de Información de Facturación) es un sistema de la AEAT española que permite a los contribuyentes registrar facturas electrónicas directamente en la Agencia Tributaria en tiempo real.</p>

<p>Está regulado por el <strong>Real Decreto 1007/2023</strong> y afecta a empresarios y profesionales que emitan facturas según el Reglamento de Facturación (RD 1619/2012).</p>

<h3>¿A quién afecta?</h3>
<ul>
  <li>Empresas y autónomos que emitan facturas completas.</li>
  <li>Arrendadores de locales y oficinas (IVA 21%) dados de alta en actividades económicas.</li>
  <li><strong>Arrendadores de vivienda habitual (sin IVA) no empresarios</strong>: en principio no están obligados (consulta con tu asesor fiscal).</li>
</ul>

<div class="callout callout-tip">
  <div class="callout-icon">💡</div>
  <div class="callout-body">
    <strong>¿Necesito esto para alquiler de vivienda?</strong>
    <p>Para alquiler de vivienda habitual sin IVA y sin actividad empresarial, VERI*FACTU probablemente no es obligatorio. Para locales comerciales con IVA o gestión como actividad económica, consulta con un asesor fiscal.</p>
  </div>
</div>

<h3>¿Qué hace VERI*FACTU técnicamente?</h3>
<ol>
  <li>Cada factura se firma con un <strong>hash SHA-256</strong> encadenado con la factura anterior (inmutabilidad).</li>
  <li>Se envía un registro XML a la API de la AEAT usando <strong>HTTPS/SOAP con certificado digital</strong>.</li>
  <li>La AEAT devuelve una confirmación.</li>
  <li>Se genera un <strong>código QR</strong> de verificación que se imprime en la factura.</li>
  <li>El receptor puede escanear el QR para verificar la factura en la sede electrónica de la AEAT.</li>
</ol>

<!-- ESTADO ACTUAL -->
<h2 id="estado-actual">Estado en AlquiGest</h2>

<table class="field-table">
  <thead><tr><th>Componente</th><th>Estado</th><th>Descripción</th></tr></thead>
  <tbody>
    <tr><td>Tabla <code>facturas</code> en BD con campos VERI*FACTU</td><td><span class="badge-ok">✅ Listo</span></td><td>Campos <code>hash_factura</code>, <code>hash_anterior</code>, <code>qr_url</code>, <code>verifactu_estado</code>, <code>verifactu_respuesta</code></td></tr>
    <tr><td>Variable <code>verifactu_activo</code> en configuracion</td><td><span class="badge-ok">✅ Listo</span></td><td>Valor <code>0</code> = desactivado por defecto. Activar desde la pantalla VERI*FACTU</td></tr>
    <tr><td>Hash SHA-256 encadenado al crear factura</td><td><span class="badge-ok">✅ Implementado</span></td><td>En <code>api.php</code> (INSERT) y <code>verifactu.php</code> (recálculo en envío)</td></tr>
    <tr><td>Envío XML SOAP a AEAT con certificado</td><td><span class="badge-ok">✅ Implementado</span></td><td>En <code>verifactu.php</code> — acciones <code>enviar</code> y <code>reenviar</code></td></tr>
    <tr><td>Generación QR de verificación</td><td><span class="badge-ok">✅ Implementado</span></td><td>URL construida y QR generado con qrious.js, impreso en el PDF de la factura</td></tr>
    <tr><td>Firma con certificado digital PKCS12</td><td><span class="badge-ok">✅ Implementado</span></td><td>curl con <code>CURLOPT_SSLCERTTYPE=P12</code> — sube tu .p12/.pfx desde la pantalla de configuración</td></tr>
    <tr><td>Pantalla de configuración en AlquiGest</td><td><span class="badge-ok">✅ Implementado</span></td><td>Menú lateral → Configuración → VERI*FACTU</td></tr>
    <tr><td>Test de conexión con AEAT</td><td><span class="badge-ok">✅ Implementado</span></td><td>Botón "Test conexión" en la pantalla de configuración</td></tr>
  </tbody>
</table>

<!-- CONFIGURAR -->
<h2 id="configurar">Configurar AlquiGest para VERI*FACTU</h2>

<p>Todo se gestiona desde <strong>Menú lateral → Configuración → 🛡️ VERI*FACTU</strong>. No hace falta editar código ni la base de datos manualmente.</p>

<div class="doc-phase">
  <div class="doc-phase-card">
    <div class="doc-phase-num">1</div>
    <div class="doc-phase-title">Datos del emisor</div>
    <div class="doc-phase-desc">Rellena el NIF del obligado de emisión y el nombre del sistema. Guarda la configuración.</div>
  </div>
  <div class="doc-phase-card">
    <div class="doc-phase-num">2</div>
    <div class="doc-phase-title">Certificado digital</div>
    <div class="doc-phase-desc">Sube tu archivo .p12 o .pfx y guarda la contraseña. AlquiGest verifica la fecha de caducidad.</div>
  </div>
  <div class="doc-phase-card">
    <div class="doc-phase-num">3</div>
    <div class="doc-phase-title">Test en pruebas</div>
    <div class="doc-phase-desc">Selecciona entorno Pruebas, pulsa "Test conexión" y envía una factura de prueba a la AEAT.</div>
  </div>
  <div class="doc-phase-card">
    <div class="doc-phase-num">4</div>
    <div class="doc-phase-title">Activar en producción</div>
    <div class="doc-phase-desc">Cuando todo funcione, cambia a entorno Producción y pulsa Activar. Desde ese momento es automático.</div>
  </div>
</div>

<!-- ACTIVAR / DESACTIVAR -->
<h2 id="activar">Activar / desactivar VERI*FACTU</h2>

<p>La variable <code>verifactu_activo</code> en la tabla <code>configuracion</code> controla si el sistema envía facturas a AEAT:</p>
<ul>
  <li><code>0</code> — <strong>Desactivado</strong> (valor por defecto). AlquiGest guarda las facturas localmente. No se genera hash ni se envía nada a la AEAT.</li>
  <li><code>1</code> — <strong>Activado</strong>. Cada factura nueva se firma automáticamente y se registra en el SIF de la AEAT.</li>
</ul>

<p>Puedes cambiar este valor desde la pantalla <strong>VERI*FACTU</strong> pulsando el botón <strong>Activar / Desactivar</strong>. AlquiGest comprueba que tengas NIF y certificado configurados antes de permitir la activación.</p>

<div class="callout callout-err">
  <div class="callout-icon">🚨</div>
  <div class="callout-body">
    <strong>Comprobación de seguridad</strong>
    <p>Aunque actives la variable, si el certificado o el NIF no están configurados, el envío a la AEAT devolverá error. La factura se guardará igualmente en BD con estado <code>error</code> y podrás reintentarlo desde la pantalla VERI*FACTU.</p>
  </div>
</div>

<!-- FLUJO DE UNA FACTURA -->
<h2 id="flujo">Flujo de una factura con VERI*FACTU activo</h2>

<div class="workflow">
  <div class="wf-step"><div class="wf-step-icon">🧾</div><div class="wf-step-label">1. Generar<br>factura</div></div>
  <div class="wf-arrow">→</div>
  <div class="wf-step"><div class="wf-step-icon">#️⃣</div><div class="wf-step-label">2. Calcular<br>hash SHA-256</div></div>
  <div class="wf-arrow">→</div>
  <div class="wf-step"><div class="wf-step-icon">📤</div><div class="wf-step-label">3. Enviar<br>XML a AEAT</div></div>
  <div class="wf-arrow">→</div>
  <div class="wf-step"><div class="wf-step-icon">✅</div><div class="wf-step-label">4. AEAT<br>confirma</div></div>
  <div class="wf-arrow">→</div>
  <div class="wf-step"><div class="wf-step-icon">📱</div><div class="wf-step-label">5. QR en<br>el PDF</div></div>
</div>

<ol>
  <li>El usuario genera una factura desde un recibo (botón FAC en la tabla de Recibos).</li>
  <li>En el momento del INSERT, <code>api.php</code> calcula el hash SHA-256 encadenado con la factura anterior.</li>
  <li>AlquiGest llama automáticamente a <code>verifactu.php?action=enviar</code> en segundo plano.</li>
  <li>Se construye el XML SOAP, se firma con el certificado .p12/.pfx y se envía a la AEAT.</li>
  <li>Si la AEAT acepta la factura, se guarda el hash y la URL del QR en BD.</li>
  <li>Al imprimir o generar el PDF de la factura, el QR aparece automáticamente en el documento.</li>
</ol>

<p>Si hay un error en el envío, la factura queda guardada con estado <code>error</code>. Puedes reintentarla desde la tabla de facturas (botón <strong>🛡 AEAT</strong>) o desde la pantalla de configuración VERI*FACTU.</p>

<!-- ESTRUCTURA BD -->
<h2 id="estructura-bd">Estructura de la tabla facturas — campos VERI*FACTU</h2>

<table class="field-table">
  <thead><tr><th>Campo</th><th>Tipo</th><th>Uso en VERI*FACTU</th></tr></thead>
  <tbody>
    <tr><td><code>hash_factura</code></td><td>VARCHAR(255)</td><td>Hash SHA-256 de esta factura (campos obligatorios + hash_anterior). Se calcula al insertar si verifactu_activo=1, y siempre al enviar</td></tr>
    <tr><td><code>hash_anterior</code></td><td>VARCHAR(255)</td><td>Hash de la factura anterior de la misma serie. Primera factura: <code>0</code> (literal, según RD 1007/2023)</td></tr>
    <tr><td><code>qr_url</code></td><td>TEXT</td><td>URL de verificación en la sede electrónica de la AEAT. Se genera y guarda tras el envío exitoso</td></tr>
    <tr><td><code>verifactu_estado</code></td><td>VARCHAR(50)</td><td><code>no_enviado</code> / <code>pendiente_envio</code> / <code>enviado</code> / <code>error</code></td></tr>
    <tr><td><code>verifactu_respuesta</code></td><td>TEXT</td><td>XML completo de la respuesta de la AEAT (para auditoría y diagnóstico de errores)</td></tr>
    <tr><td><code>tipo_factura</code></td><td>VARCHAR(20)</td><td><code>F1</code> = factura completa (por defecto), <code>R1</code>–<code>R5</code> = rectificativa</td></tr>
    <tr><td><code>serie</code></td><td>VARCHAR(20)</td><td>Serie de numeración (por defecto: <code>FAC</code>)</td></tr>
    <tr><td><code>factura_rectificada_id</code></td><td>INT</td><td>ID de la factura que rectifica esta (para facturas rectificativas futuras)</td></tr>
  </tbody>
</table>

<!-- HASH -->
<h2 id="hash">Hash SHA-256 encadenado</h2>

<p>El SIF exige que cada factura tenga un hash SHA-256 calculado sobre sus campos obligatorios, encadenado con el hash de la factura anterior de la misma serie. Esto hace imposible insertar o modificar facturas retroactivamente sin invalidar toda la cadena.</p>

<h3>Cadena de hash (según Anexo I del RD 1007/2023)</h3>
<p>Los campos se concatenan separados por <code>|</code> en este orden exacto:</p>
<pre><code>IDEmisorFactura=B12345678|NumSerieFactura=FAC-202606-00001|FechaExpedicionFactura=17-06-2026|TipoFactura=F1|CuotaTotal=252.00|ImporteTotal=1452.00|Huella=&lt;HASH_ANTERIOR&gt;|FechaHoraHuella=17-06-2026T14:30:00</code></pre>

<p>El resultado se pasa por SHA-256 y se convierte a hexadecimal en mayúsculas.</p>

<div class="callout callout-tip">
  <div class="callout-icon">💡</div>
  <div class="callout-body">
    <strong>Primera factura de la serie</strong>
    <p>Para la primera factura de cada serie, el campo <code>Huella</code> de la cadena de hash debe ser literalmente <code>0</code> (no NULL, no cadena vacía). Esto marca el inicio de la cadena.</p>
  </div>
</div>

<h3>Dónde se calcula en el código</h3>
<ul>
  <li><strong><code>api.php</code></strong> — bloque especial en el action <code>save</code> para <code>$table === 'facturas'</code>: calcula el hash en el INSERT si <code>verifactu_activo = '1'</code>.</li>
  <li><strong><code>verifactu.php</code></strong> — función <code>calcularHashFactura()</code>: recalcula el hash en el momento del envío para garantizar integridad. Si hay discrepancia, se usa el recalculado.</li>
</ul>

<!-- SOAP -->
<h2 id="soap">Envío SOAP a la AEAT</h2>

<p>La comunicación con la AEAT usa <strong>HTTPS/SOAP</strong> con autenticación de cliente TLS mediante certificado digital PKCS12.</p>

<h3>Endpoints</h3>
<table class="field-table">
  <tr><th>Entorno</th><th>URL</th></tr>
  <tr><td><strong>Pruebas</strong></td><td><code>https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSistemaFacturacion</code></td></tr>
  <tr><td><strong>Producción</strong></td><td><code>https://www1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSistemaFacturacion</code></td></tr>
</table>

<h3>Estructura del XML de envío (simplificada)</h3>
<pre><code class="language-xml">&lt;?xml version="1.0" encoding="UTF-8"?&gt;
&lt;soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"&gt;
  &lt;soapenv:Body&gt;
    &lt;sum:RegFactuSistemaFacturacion&gt;
      &lt;Cabecera&gt;
        &lt;ObligadoEmision&gt;
          &lt;NombreRazon&gt;Tu Empresa S.L.&lt;/NombreRazon&gt;
          &lt;NIF&gt;B12345678&lt;/NIF&gt;
        &lt;/ObligadoEmision&gt;
      &lt;/Cabecera&gt;
      &lt;RegistroFacturacion&gt;
        &lt;RegistroAlta&gt;
          &lt;IDVersion&gt;1.0&lt;/IDVersion&gt;
          &lt;IDFactura&gt;
            &lt;IDEmisorFactura&gt;B12345678&lt;/IDEmisorFactura&gt;
            &lt;NumSerieFactura&gt;FAC-202606-00001&lt;/NumSerieFactura&gt;
            &lt;FechaExpedicionFactura&gt;17-06-2026&lt;/FechaExpedicionFactura&gt;
          &lt;/IDFactura&gt;
          &lt;TipoFactura&gt;F1&lt;/TipoFactura&gt;
          &lt;Desglose&gt;
            &lt;DetalleIVA&gt;
              &lt;TipoImpositivo&gt;21.00&lt;/TipoImpositivo&gt;
              &lt;BaseImponibleOImporteNoSujeto&gt;1200.00&lt;/BaseImponibleOImporteNoSujeto&gt;
              &lt;CuotaRepercutida&gt;252.00&lt;/CuotaRepercutida&gt;
            &lt;/DetalleIVA&gt;
          &lt;/Desglose&gt;
          &lt;ImporteTotal&gt;1452.00&lt;/ImporteTotal&gt;
          &lt;Encadenamiento&gt;
            &lt;PrimerRegistro&gt;N&lt;/PrimerRegistro&gt;
            &lt;RegistroAnterior&gt;
              &lt;Huella&gt;ABCDEF1234...&lt;/Huella&gt;
            &lt;/RegistroAnterior&gt;
          &lt;/Encadenamiento&gt;
          &lt;HuellaSQL&gt;3F7A...&lt;/HuellaSQL&gt;
        &lt;/RegistroAlta&gt;
      &lt;/RegistroFacturacion&gt;
    &lt;/sum:RegFactuSistemaFacturacion&gt;
  &lt;/soapenv:Body&gt;
&lt;/soapenv:Envelope&gt;</code></pre>

<div class="callout callout-tip">
  <div class="callout-icon">💡</div>
  <div class="callout-body">
    <strong>Facturas exentas de IVA (vivienda habitual)</strong>
    <p>Para arrendamientos sin IVA, el bloque <code>DetalleIVA</code> usa <code>&lt;CausaExencion&gt;E2&lt;/CausaExencion&gt;</code> (exención art. 20.Uno.23 LIVA) en lugar de <code>TipoImpositivo</code>. AlquiGest lo construye automáticamente según el porcentaje de IVA del contrato.</p>
  </div>
</div>

<h3>Vista previa del XML</h3>
<p>Antes de enviar, puedes ver el XML que se construiría para cualquier factura pulsando el botón <strong>&lt;/&gt;</strong> (ver XML) en la tabla de facturas enviadas. Esto ayuda a diagnosticar problemas sin enviar nada a la AEAT.</p>

<!-- QR -->
<h2 id="qr">QR de verificación</h2>

<p>Cuando la AEAT confirma el registro de una factura, AlquiGest construye la URL de verificación y la codifica como código QR. Esta URL permite a cualquier receptor verificar la factura en la sede electrónica de la AEAT.</p>

<h3>Formato de la URL de verificación</h3>
<pre><code>https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR?nif=B12345678&amp;numserie=FAC-202606-00001&amp;fecha=17-06-2026&amp;importe=1452.00</code></pre>

<p>(En el entorno de pruebas usa <code>prewww2.agenciatributaria.gob.es</code>.)</p>

<h3>Dónde aparece el QR</h3>
<ul>
  <li><strong>En el PDF de la factura:</strong> se imprime automáticamente si la factura tiene <code>qr_url</code> guardada. El QR se genera con la librería JavaScript <strong>qrious</strong> (cargada vía CDN) como imagen embebida, compatible con la generación de PDF mediante html2canvas.</li>
  <li><strong>En la pantalla de Facturas:</strong> aparece el enlace "Verificar AEAT →" en la columna QR de la tabla de facturas enviadas.</li>
  <li><strong>En la pantalla VERI*FACTU:</strong> tabla de historial de envíos con enlace directo.</li>
</ul>

<!-- CERTIFICADO -->
<h2 id="certificado">Certificado digital</h2>

<p>La autenticación con la AEAT requiere el certificado digital del emisor en formato <strong>PKCS12</strong> (.p12 o .pfx). Es el mismo tipo de certificado que se usa para la firma digital de documentos y la sede electrónica.</p>

<h3>Cómo subir el certificado</h3>
<ol>
  <li>Desde la pantalla <strong>VERI*FACTU</strong> en AlquiGest, sección "Certificado digital".</li>
  <li>Selecciona tu archivo .p12 o .pfx e introduce la contraseña.</li>
  <li>Pulsa <strong>Subir certificado</strong>. AlquiGest lo guarda en <code>certs/</code> y verifica que la contraseña es correcta.</li>
  <li>Guarda la contraseña pulsando <strong>Guardar contraseña</strong>.</li>
</ol>

<div class="callout callout-warn">
  <div class="callout-icon">⚠️</div>
  <div class="callout-body">
    <strong>Seguridad del certificado</strong>
    <p>El directorio <code>certs/</code> está protegido con un archivo <code>.htaccess</code> que bloquea el acceso HTTP directo. Solo <code>verifactu.php</code> puede leer el certificado desde el servidor. La contraseña se guarda en la base de datos (tabla <code>configuracion</code>) — considera cifrar la columna si el servidor es compartido.</p>
  </div>
</div>

<h3>Extensiones PHP necesarias</h3>
<p>Asegúrate de que MAMP o XAMPP tienen activas: <code>openssl</code>, <code>curl</code>, <code>dom</code>. Puedes comprobarlo en <code>phpinfo()</code>.</p>

<!-- ARCHIVOS CLAVE -->
<h2 id="donde-modificar">Archivos clave de la implementación</h2>

<table class="field-table">
  <thead><tr><th>Archivo</th><th>Rol en VERI*FACTU</th></tr></thead>
  <tbody>
    <tr>
      <td><code>api.php</code></td>
      <td>Calcula el hash encadenado en el INSERT de facturas si <code>verifactu_activo = '1'</code></td>
    </tr>
    <tr>
      <td><code>verifactu.php</code></td>
      <td>Backend completo: acciones <code>enviar</code>, <code>reenviar</code>, <code>test_conexion</code>, <code>upload_cert</code>, <code>estado</code>, <code>xml_preview</code></td>
    </tr>
    <tr>
      <td><code>AlquiGest.html</code></td>
      <td>Frontend: pantalla <code>renderVerifactu()</code>, botón <strong>🛡 AEAT</strong> en tabla de facturas, QR en <code>buildFacturaHTML()</code>, llamada automática a <code>verifactu.php</code> al generar una factura</td>
    </tr>
    <tr>
      <td><code>install.php</code></td>
      <td>Crea las 8 variables de configuración VERI*FACTU en la tabla <code>configuracion</code> en instalaciones nuevas</td>
    </tr>
    <tr>
      <td><code>migration_facturas.sql</code></td>
      <td>Script idempotente para añadir las variables VERI*FACTU en instalaciones existentes (sin borrar datos)</td>
    </tr>
    <tr>
      <td><code>certs/</code></td>
      <td>Directorio protegido donde se guarda el certificado .p12/.pfx. Acceso HTTP bloqueado por <code>.htaccess</code></td>
    </tr>
  </tbody>
</table>

<h3>Variables de configuración en BD</h3>
<table class="field-table">
  <thead><tr><th>Variable</th><th>Valor por defecto</th><th>Descripción</th></tr></thead>
  <tbody>
    <tr><td><code>verifactu_activo</code></td><td><code>0</code></td><td>Interruptor principal: 0=inactivo, 1=activo</td></tr>
    <tr><td><code>verifactu_entorno</code></td><td><code>pruebas</code></td><td>Entorno AEAT: <code>pruebas</code> o <code>produccion</code></td></tr>
    <tr><td><code>verifactu_cert_path</code></td><td>(vacío)</td><td>Ruta relativa al certificado .p12/.pfx</td></tr>
    <tr><td><code>verifactu_cert_pass</code></td><td>(vacío)</td><td>Contraseña del certificado</td></tr>
    <tr><td><code>verifactu_nif_sif</code></td><td>(vacío)</td><td>NIF del obligado de emisión ante el SIF</td></tr>
    <tr><td><code>verifactu_sistema_nombre</code></td><td><code>AlquiGest</code></td><td>Nombre del sistema informático</td></tr>
    <tr><td><code>verifactu_sistema_version</code></td><td><code>3.3</code></td><td>Versión del sistema</td></tr>
    <tr><td><code>verifactu_num_instalacion</code></td><td><code>1</code></td><td>Número de instalación</td></tr>
  </tbody>
</table>

<!-- REFERENCIAS -->
<h2 id="referencias">Referencias oficiales</h2>

<ul>
  <li><a href="https://sede.agenciatributaria.gob.es/Sede/iva/facturacion-registro/verifactu.html" target="_blank" rel="noopener">AEAT — Información general sobre VERI*FACTU</a></li>
  <li><a href="https://www.boe.es/buscar/doc.php?id=BOE-A-2023-22710" target="_blank" rel="noopener">Real Decreto 1007/2023 — texto completo en BOE</a></li>
  <li>Documentación técnica del WS SOAP de la AEAT (disponible en la sede electrónica, sección "Desarrolladores")</li>
  <li>Esquemas XSD del SIF — publicados por la AEAT junto con el WSDL del servicio</li>
</ul>

<div class="callout callout-purple">
  <div class="callout-icon">📌</div>
  <div class="callout-body">
    <strong>Prueba siempre en el entorno de preproducción primero</strong>
    <p>El entorno de pruebas de la AEAT (<code>prewww1.aeat.es</code>) está disponible para validar la integración sin consecuencias fiscales. Los errores en producción pueden generar facturas mal registradas que son difíciles de corregir. Usa el botón "Test conexión" de AlquiGest para verificar el acceso antes de activar.</p>
  </div>
</div>

<p style="margin-top:48px;padding-top:20px;border-top:1px solid var(--gray-200);font-size:12px;color:var(--gray-400)">
  AlquiGest v<?= htmlspecialchars($cfg['version']) ?> · Guía VERI*FACTU / AEAT · Junio 2026 ·
  <a href="ayuda.php">← Volver al manual general</a>
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
