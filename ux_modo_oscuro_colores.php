<?php
// ============================================================
//  AlquiGest — Propuesta modo oscuro / colores / accesibilidad
//  Página autocontenida (prototipo NO productivo).
//  CSS aislado: assets/css/ux-theme-proposal.css
//  No se enlaza desde el menú de la aplicación real y no toca
//  api.php ni la base de datos.
//  Ver UX_UI_MODO_OSCURO_COLORES.md para el análisis completo.
// ============================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AlquiGest — Propuesta modo oscuro y colores</title>
<link rel="stylesheet" href="assets/css/ux-theme-proposal.css">
</head>
<body>

<div class="uxc-banner">
  🎨 <strong>PROTOTIPO — no productivo.</strong> No está enlazado al menú de AlquiGest y no modifica datos reales.
  <a href="AlquiGest.php">← Volver a la aplicación</a>
  <a href="UX_UI_MODO_OSCURO_COLORES.md" target="_blank">Ver documento completo (MD)</a>
</div>

<div class="uxc-root" id="uxc-root" data-theme="light">

  <div class="uxc-topbar">
    <h1>Propuesta — Modo oscuro, colores y accesibilidad</h1>
    <p>Antes / Después de contraste, badges, formularios, modales y gráficos. Colores simulados, sin datos reales.</p>
    <nav class="uxc-nav" id="uxc-nav">
      <button data-tab="t1" class="active">1. Antes / Después</button>
      <button data-tab="t2">2. Tablas</button>
      <button data-tab="t3">3. Botones</button>
      <button data-tab="t4">4. Badges</button>
      <button data-tab="t5">5. Formulario</button>
      <button data-tab="t6">6. Modal</button>
      <button data-tab="t7">7. Dashboard</button>
      <button data-tab="t8">8. Gráfico</button>
      <button data-tab="t9">9. Paleta</button>
      <div class="uxc-theme-switch">
        <span>Tema:</span>
        <button onclick="uxcSetTheme('light')" id="uxc-btn-light">☀ Claro</button>
        <button onclick="uxcSetTheme('dark')" id="uxc-btn-dark">🌙 Oscuro</button>
      </div>
    </nav>
  </div>

  <div class="uxc-wrap">

    <!-- ============ TAB 1: ANTES / DESPUÉS ============ -->
    <section class="uxc-section active" id="t1">
      <div class="uxc-card">
        <h2>El defecto más visible: cajas de estado dentro de un modal</h2>
        <p class="uxc-desc">Los mensajes "Enviando…"/"Enviado ✓"/"Error" de los modales de email hoy usan colores fijos que no reaccionan al tema. Cambia a modo oscuro (arriba a la derecha) para ver la diferencia.</p>
        <div class="uxc-compare">
          <div class="uxc-compare-col">
            <span class="uxc-tag antes">Antes — color fijo</span>
            <div class="uxc-status-box hardcoded-info">⏳ Enviando email…</div>
            <div class="uxc-status-box hardcoded-success" style="margin-top:8px">✅ Correo enviado correctamente</div>
            <p class="uxc-note">Estos dos recuadros están escritos con <code>color:#1e40af;background:#dbeafe</code> y <code>color:#15803d;background:#dcfce7</code> directamente en JavaScript (email.js, facturas.js) — no usan ninguna variable, así que en modo oscuro se quedan igual de claros.</p>
          </div>
          <div class="uxc-compare-col">
            <span class="uxc-tag despues">Después — variables de tema</span>
            <div class="uxc-status-box info">⏳ Enviando email…</div>
            <div class="uxc-status-box success" style="margin-top:8px">✅ Correo enviado correctamente</div>
            <p class="uxc-note">Mismo componente, pero usando <code>var(--color-info-bg)</code>/<code>var(--color-success-bg)</code> — cambia de tema y estos sí se adaptan.</p>
          </div>
        </div>
      </div>

      <div class="uxc-card">
        <h2>Fila de recibo "Pendiente": dos señales de color contradictorias</h2>
        <p class="uxc-desc">Hoy el texto de la fila se pinta en rojo (peligro) mientras el badge de estado dice "Pendiente" en naranja (aviso) — dos colores para el mismo estado.</p>
        <div class="uxc-compare">
          <div class="uxc-compare-col">
            <span class="uxc-tag antes">Antes</span>
            <table class="uxc-table">
              <thead><tr><th>Nº Recibo</th><th>Total</th><th>Estado</th></tr></thead>
              <tbody>
                <tr><td style="color:#c81e1e"><strong>REC-202607-00001</strong></td><td style="color:#c81e1e">700,00 €</td><td><span class="uxc-badge uxc-b-warning">Pendiente</span></td></tr>
              </tbody>
            </table>
          </div>
          <div class="uxc-compare-col">
            <span class="uxc-tag despues">Después</span>
            <table class="uxc-table">
              <thead><tr><th>Nº Recibo</th><th>Total</th><th>Estado</th></tr></thead>
              <tbody>
                <tr><td><strong>REC-202607-00001</strong></td><td>700,00 €</td><td><span class="uxc-badge uxc-b-warning">Pendiente</span></td></tr>
              </tbody>
            </table>
            <p class="uxc-note">El texto de la fila vuelve al color normal; el badge es la única señal de estado — sin contradicción.</p>
          </div>
        </div>
      </div>

      <div class="uxc-card">
        <h2>Contraste de badges — cálculo WCAG (antes / después)</h2>
        <table class="uxc-table">
          <thead><tr><th>Badge</th><th>Antes</th><th>Contraste</th><th>Después</th><th>Contraste</th></tr></thead>
          <tbody>
            <tr>
              <td>Pendiente (naranja)</td>
              <td><span class="uxc-badge" style="background:#fdf6b2;color:#c27803">Pendiente</span></td>
              <td style="color:var(--color-danger);font-weight:700">3.12:1 ❌</td>
              <td><span class="uxc-badge uxc-b-warning">Pendiente</span></td>
              <td style="color:var(--color-success);font-weight:700">6.30:1 ✔</td>
            </tr>
            <tr>
              <td>Anulado (gris)</td>
              <td><span class="uxc-badge" style="background:#f3f4f6;color:#6b7280">Anulado</span></td>
              <td style="color:var(--color-danger);font-weight:700">4.18:1 ❌</td>
              <td><span class="uxc-badge uxc-b-gray">Anulado</span></td>
              <td style="color:var(--color-success);font-weight:700">6.74:1 ✔</td>
            </tr>
          </tbody>
        </table>
        <p class="uxc-note">Umbral WCAG AA para texto normal: 4.5:1. Cálculo con la fórmula estándar de luminancia relativa sobre los valores hex reales de <code>main.css</code>. Detalle completo en el MD, §5.</p>
      </div>
    </section>

    <!-- ============ TAB 2: TABLAS ============ -->
    <section class="uxc-section" id="t2">
      <div class="uxc-card">
        <h2>Tabla de recibos (después)</h2>
        <table class="uxc-table">
          <thead><tr><th>Nº Recibo</th><th>Inquilino</th><th>Total</th><th>Pagado</th><th>Estado</th></tr></thead>
          <tbody>
            <tr><td>REC-202607-00001</td><td>Rodríguez Pérez, Laura</td><td>700,00 €</td><td>—</td><td><span class="uxc-badge uxc-b-warning">Pendiente</span></td></tr>
            <tr><td>REC-202606-00003</td><td>Martín López, María</td><td>720,00 €</td><td>360,00 €</td><td><span class="uxc-badge uxc-b-purple">Parcial</span></td></tr>
            <tr><td>REC-202605-00005</td><td>Comercial Díaz S.L.</td><td>1.452,00 €</td><td>1.452,00 €</td><td><span class="uxc-badge uxc-b-success">Cobrado</span></td></tr>
            <tr style="opacity:.6;text-decoration:line-through"><td>REC-202607-00005</td><td>Comercial Díaz S.L.</td><td>1.452,00 €</td><td>1.452,00 €</td><td><span class="uxc-badge uxc-b-gray">Anulado</span></td></tr>
            <tr><td>RER-202607-00002</td><td>Comercial Díaz S.L.</td><td>-1.452,00 €</td><td>-1.452,00 €</td><td><span class="uxc-badge uxc-b-info">Rectificativo</span></td></tr>
          </tbody>
        </table>
      </div>
      <div class="uxc-card">
        <h2>Tabla de facturas (después)</h2>
        <table class="uxc-table">
          <thead><tr><th>Nº Factura</th><th>Cliente</th><th>Total</th><th>Estado</th><th>VERI·FACTU</th></tr></thead>
          <tbody>
            <tr><td>FAC-202607-00001</td><td>González Sánchez, Pedro</td><td>650,00 €</td><td><span class="uxc-badge uxc-b-success">Emitida</span></td><td><span class="uxc-badge uxc-b-warning">Pendiente</span></td></tr>
            <tr><td>FAC-202602-00001</td><td>Ortega Campos, Beatriz</td><td>750,00 €</td><td><span class="uxc-badge uxc-b-gray">Rectificada</span></td><td><span class="uxc-badge uxc-b-info">Enviado ✓</span></td></tr>
            <tr><td>RET-202607-00002</td><td>Ortega Campos, Beatriz</td><td>-750,00 €</td><td><span class="uxc-badge uxc-b-success">Emitida</span></td><td><span class="uxc-badge uxc-b-danger">Error</span></td></tr>
          </tbody>
        </table>
        <p class="uxc-note">"Emitida" (verde) y "Enviado ✓" de VERI·FACTU (azul) ya no comparten el mismo color en la misma fila — evita la doble señal verde/verde de la versión anterior.</p>
      </div>
    </section>

    <!-- ============ TAB 3: BOTONES ============ -->
    <section class="uxc-section" id="t3">
      <div class="uxc-card">
        <h2>Jerarquía de botones</h2>
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
          <button class="uxc-btn primary">Primario</button>
          <button class="uxc-btn secondary">Secundario</button>
          <button class="uxc-btn success">Éxito</button>
          <button class="uxc-btn danger">Peligro</button>
          <button class="uxc-btn ghost">Fantasma</button>
          <button class="uxc-btn secondary icon" title="Editar">✎</button>
          <button class="uxc-btn secondary" disabled>Deshabilitado</button>
        </div>
        <p class="uxc-note">Mismos estilos en claro y oscuro — solo cambian las variables subyacentes, no las clases.</p>
      </div>
      <div class="uxc-card">
        <h2>Foco de teclado (Tab hasta aquí)</h2>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <button class="uxc-btn primary">Prueba con Tab</button>
          <button class="uxc-btn secondary">Otro botón</button>
        </div>
        <p class="uxc-note">Anillo de foco propuesto con 35-40% de opacidad (<code>--focus-ring</code>), más visible que el 12-15% actual de la app real.</p>
      </div>
    </section>

    <!-- ============ TAB 4: BADGES ============ -->
    <section class="uxc-section" id="t4">
      <div class="uxc-card">
        <h2>Badges de Recibos</h2>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <span class="uxc-badge uxc-b-warning">Pendiente</span>
          <span class="uxc-badge uxc-b-purple">Parcial</span>
          <span class="uxc-badge uxc-b-success">Cobrado</span>
          <span class="uxc-badge uxc-b-gray">Anulado</span>
          <span class="uxc-badge uxc-b-info">Rectificativo</span>
        </div>
      </div>
      <div class="uxc-card">
        <h2>Badges de Facturas / VERI·FACTU</h2>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <span class="uxc-badge uxc-b-success">Emitida</span>
          <span class="uxc-badge uxc-b-gray">Rectificada</span>
          <span class="uxc-badge uxc-b-gray">No enviado</span>
          <span class="uxc-badge uxc-b-warning">Pendiente envío</span>
          <span class="uxc-badge uxc-b-info">Enviado ✓</span>
          <span class="uxc-badge uxc-b-danger">Error AEAT</span>
        </div>
      </div>
      <div class="uxc-card">
        <h2>Badges de Contratos</h2>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <span class="uxc-badge uxc-b-success">Activo</span>
          <span class="uxc-badge uxc-b-gray">Finalizado</span>
          <span class="uxc-badge uxc-b-warning">Próximo vencimiento</span>
          <span class="uxc-badge uxc-b-warning">⚠ Revisión IPC pendiente</span>
        </div>
      </div>
    </section>

    <!-- ============ TAB 5: FORMULARIO ============ -->
    <section class="uxc-section" id="t5">
      <div class="uxc-card">
        <h2>Ejemplo de formulario</h2>
        <div class="uxc-form-group">
          <label>Nombre del inquilino</label>
          <input type="text" placeholder="Ej: Laura Rodríguez Pérez">
        </div>
        <div class="uxc-form-group">
          <label>Tipo de inmueble</label>
          <select><option>Vivienda</option><option>Local comercial</option><option>Garaje</option></select>
        </div>
        <div class="uxc-form-group">
          <label>Observaciones</label>
          <textarea rows="3" placeholder="Notas internas…"></textarea>
        </div>
        <div class="uxc-form-group error">
          <label>Email</label>
          <input type="text" value="correo-no-valido">
          <div class="uxc-form-error">Introduce un email válido.</div>
        </div>
        <div class="uxc-form-group">
          <div class="uxc-checkbox-row"><input type="checkbox" id="uxc-chk"><label for="uxc-chk" style="margin:0;text-transform:none;font-weight:400">Aviso de justificante de pago en el recibo</label></div>
          <div class="uxc-form-help">Se incluirá un aviso legal al pie del recibo impreso.</div>
        </div>
      </div>
    </section>

    <!-- ============ TAB 6: MODAL ============ -->
    <section class="uxc-section" id="t6">
      <div class="uxc-card">
        <h2>Modal — envío de email (corregido)</h2>
        <div class="uxc-modal-demo">
          <div class="uxc-modal-demo-head"><span>Enviar recibo por email</span><span>✕</span></div>
          <div class="uxc-modal-demo-body">
            <p>Se enviará el recibo <strong>REC-202607-00005</strong> a:</p>
            <p style="text-align:center;margin:12px 0"><strong>Comercial Díaz S.L.</strong><br><span style="color:var(--color-muted)">admin@comercialdiaz.es</span></p>
            <div class="uxc-status-box success">✅ Correo enviado correctamente</div>
          </div>
          <div class="uxc-modal-demo-foot">
            <button class="uxc-btn secondary">Cerrar</button>
          </div>
        </div>
        <p class="uxc-note">Cambia el tema arriba a la derecha: este modal (y su caja de estado) se adapta correctamente en ambos casos.</p>
      </div>
    </section>

    <!-- ============ TAB 7: DASHBOARD ============ -->
    <section class="uxc-section" id="t7">
      <div class="uxc-card">
        <h2>Tarjetas de KPI</h2>
        <div class="uxc-stats-grid">
          <div class="uxc-stat-card"><div class="uxc-stat-value">5</div><div class="uxc-stat-label">Propietarios</div></div>
          <div class="uxc-stat-card"><div class="uxc-stat-value">16</div><div class="uxc-stat-label">Inmuebles</div></div>
          <div class="uxc-stat-card"><div class="uxc-stat-value">15</div><div class="uxc-stat-label">Contratos activos</div></div>
          <div class="uxc-stat-card"><div class="uxc-stat-value" style="color:var(--color-warning)">7.032,00 €</div><div class="uxc-stat-label">Pendiente de cobro</div></div>
        </div>
      </div>
      <div class="uxc-card">
        <h2>Aviso ⚠ IPC/IRAV (corregido para modo oscuro)</h2>
        <div class="uxc-status-box uxc-b-warning" style="background:var(--color-warning-bg);color:var(--color-warning);padding:14px 16px">
          <strong>⚠ Revisión IPC/IRAV pendiente</strong> — 7 contratos con revisión correspondiente a este mes
        </div>
        <p class="uxc-note">Mismo aviso que hoy en Contratos, pero usando <code>var(--color-warning-bg)</code>/<code>var(--color-warning)</code> en vez de <code>#fef3c7</code>/<code>#92400e</code> fijos — cambia de tema para comprobarlo.</p>
      </div>
    </section>

    <!-- ============ TAB 8: GRÁFICO ============ -->
    <section class="uxc-section" id="t8">
      <div class="uxc-card">
        <h2>Placeholder de gráfico (adaptado al tema activo)</h2>
        <div class="uxc-chart-placeholder">
          <div class="uxc-chart-grid-line">6.000 €</div>
          <div class="uxc-chart-bars">
            <div class="uxc-chart-bar" style="height:20%"></div>
            <div class="uxc-chart-bar" style="height:75%"></div>
            <div class="uxc-chart-bar" style="height:60%"></div>
            <div class="uxc-chart-bar" style="height:90%"></div>
            <div class="uxc-chart-bar" style="height:45%"></div>
          </div>
        </div>
        <p class="uxc-note">En la app real, Chart.js sí elige los colores correctos al crear el gráfico — el problema es que no se vuelve a dibujar si cambias de tema sin navegar fuera del Dashboard. Aquí, al ser CSS puro, el placeholder se actualiza al instante.</p>
      </div>
    </section>

    <!-- ============ TAB 9: PALETA ============ -->
    <section class="uxc-section" id="t9">
      <div class="uxc-card">
        <h2>Paleta activa (cambia de tema para ver ambas)</h2>
        <div class="uxc-swatch-grid" id="uxc-palette"></div>
      </div>
    </section>

  </div>
</div>

<script>
// ---- Tabs ----
document.getElementById('uxc-nav').addEventListener('click', function(e){
  var btn = e.target.closest('button[data-tab]');
  if(!btn) return;
  document.querySelectorAll('#uxc-nav button[data-tab]').forEach(function(b){ b.classList.remove('active'); });
  btn.classList.add('active');
  document.querySelectorAll('.uxc-section').forEach(function(s){ s.classList.remove('active'); });
  document.getElementById(btn.dataset.tab).classList.add('active');
  window.scrollTo({top:0, behavior:'smooth'});
});

// ---- Theme switch (aislado: data-theme en .uxc-root, no toca body.dark de la app real) ----
function uxcSetTheme(t){
  document.getElementById('uxc-root').setAttribute('data-theme', t);
  document.getElementById('uxc-btn-light').style.fontWeight = t === 'light' ? '800' : '400';
  document.getElementById('uxc-btn-dark').style.fontWeight  = t === 'dark'  ? '800' : '400';
  uxcRenderPalette(t);
}

// ---- Paleta de swatches ----
var UXC_TOKENS_LIGHT = [
  ['--color-bg','#f3f4f6','Fondo app'], ['--color-surface','#ffffff','Superficie / card'],
  ['--color-elevated','#f9fafb','Superficie elevada'], ['--color-text','#111827','Texto principal'],
  ['--color-muted','#4b5563','Texto secundario'], ['--color-border','#e5e7eb','Borde'],
  ['--color-primary','#1a56db','Primario'], ['--color-success','#057a55','Éxito'],
  ['--color-warning','#92400e','Advertencia (texto, corregido)'], ['--color-danger','#c81e1e','Peligro'],
  ['--color-info','#0284c7','Info'], ['--color-purple','#6d28d9','Morado (nuevo)'],
];
var UXC_TOKENS_DARK = [
  ['--color-bg','#0f172a','Fondo app'], ['--color-surface','#1e293b','Superficie / card'],
  ['--color-elevated','#263348','Superficie elevada'], ['--color-text','#f1f5f9','Texto principal'],
  ['--color-muted','#94a3b8','Texto secundario'], ['--color-border','#334155','Borde'],
  ['--color-primary','#4d8ffa','Primario'], ['--color-success','#22c55e','Éxito'],
  ['--color-warning','#fbbf24','Advertencia'], ['--color-danger','#f87171','Peligro'],
  ['--color-info','#38bdf8','Info'], ['--color-purple','#c4b5fd','Morado (nuevo)'],
];
function uxcRenderPalette(theme){
  var tokens = theme === 'dark' ? UXC_TOKENS_DARK : UXC_TOKENS_LIGHT;
  var el = document.getElementById('uxc-palette');
  el.innerHTML = tokens.map(function(t){
    return '<div class="uxc-swatch"><div class="uxc-swatch-color" style="background:'+t[1]+'"></div>' +
      '<div class="uxc-swatch-label">'+t[2]+'<code>'+t[0]+' · '+t[1]+'</code></div></div>';
  }).join('');
}
uxcRenderPalette('light');
</script>
</body>
</html>
