// ===========================
// VERI*FACTU — FRONTEND
//
// Toda la lógica de interfaz para la integración con la AEAT.
// El envío real solo ocurre si la variable verifactu_activo = '1'
// en la tabla configuracion — la comprobación la hace verifactu.php,
// pero el JS también la respeta para no mostrar botones innecesarios.
// ===========================

// ── Helpers de configuracion ─────────────────────────────────

// Leer una variable de la tabla configuracion (caché de DB)
function getConfigVar(variable, defecto = '') {
  const c = DB.get('configuracion').find(c => c.variable === variable);
  return (c && c.valor !== null && c.valor !== undefined) ? String(c.valor) : defecto;
}

// Guardar/actualizar una variable de la tabla configuracion
async function saveConfigVar(variable, valor) {
  const all = DB.get('configuracion');
  const existing = all.find(c => c.variable === variable);
  if (existing) {
    existing.valor = String(valor);
    await DB.save('configuracion', existing);
  } else {
    await DB.save('configuracion', { variable, valor: String(valor), descripcion: '' });
  }
}

// ── QR helper ────────────────────────────────────────────────

// Genera un QR como data URL PNG usando la librería qrious (CDN).
// Si qrious no está cargado, devuelve cadena vacía sin error.
function qrDataURL(text, size) {
  if (!window.QRious || !text) return '';
  try {
    const qr = new QRious({ value: text, size: size || 120, backgroundAlpha: 0 });
    return qr.toDataURL('image/png');
  } catch(e) { return ''; }
}

// ── Badge VERI*FACTU ─────────────────────────────────────────
// Revisado 08/07/2026: 'no_enviado' pasa a gris y 'enviado' a azul, para no
// competir con el verde de badgeEstadoFactura('emitida') en la misma fila
// (ver UX_UI_ANALISIS_PROPUESTA.md §6).
function badgeVF(estado) {
  const map = {
    no_enviado     : ['badge-gray',   'No enviado'],
    pendiente_envio: ['badge-orange', 'Pendiente'],
    enviado        : ['badge-blue',   'Enviado ✓'],
    error          : ['badge-red',    'Error'],
  };
  const [cls, label] = map[estado] || ['badge-blue', estado || '—'];
  return `<span class="badge ${cls}" style="font-size:10px">${label}</span>`;
}

// ── Enviar una factura a AEAT ────────────────────────────────
// Solo actúa si verifactu_activo = '1'. La comprobación definitiva
// la hace verifactu.php; aquí solo evitamos la llamada innecesaria.
async function enviarFacturaAEAT(facturaId, btn) {
  if (getConfigVar('verifactu_activo') !== '1') {
    toast('VERI*FACTU no está activado. Actívalo en Configuración → VERI*FACTU.', 'error');
    return;
  }
  if (btn) { btn.disabled = true; btn.textContent = 'Enviando…'; }
  try {
    const resp = await fetch('assets/php/verifactu.php?action=enviar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ factura_id: facturaId })
    });
    const res = await resp.json();
    if (res.ok) {
      toast('Factura registrada en AEAT correctamente.', 'success');
      // Forzar recarga de cache para reflejar hash/qr_url actualizados
      DB._cache = null;
      renderFacturas(navParams);
    } else {
      toast('Error AEAT: ' + (res.error || 'error desconocido'), 'error');
      if (btn) { btn.disabled = false; btn.textContent = 'Enviar AEAT'; }
    }
  } catch(e) {
    toast('No se pudo contactar con verifactu.php: ' + e.message, 'error');
    if (btn) { btn.disabled = false; btn.textContent = 'Enviar AEAT'; }
  }
}

// ── Página de configuración VERI*FACTU ───────────────────────
async function renderVerifactu(params) {
  params = params || {};
  document.getElementById('content').innerHTML =
    `<div class="card"><div style="padding:20px;text-align:center;color:var(--gray-400)">Cargando estado VERI*FACTU…</div></div>`;
  document.getElementById('header-actions').innerHTML = '';

  // Cargar estado desde verifactu.php
  let estado = null;
  try {
    const resp = await fetch('assets/php/verifactu.php?action=estado');
    estado = await resp.json();
  } catch(e) { estado = { error: 'No se pudo conectar con verifactu.php: ' + e.message }; }

  if (estado && estado.error && !estado.ok) {
    document.getElementById('content').innerHTML =
      `<div class="card"><div style="padding:20px;color:var(--red)">${esc(estado.error)}</div></div>`;
    return;
  }

  // Leer config actual del cache local para los inputs
  const activo   = getConfigVar('verifactu_activo') === '1';
  const entorno  = getConfigVar('verifactu_entorno', 'pruebas');
  const certPath = getConfigVar('verifactu_cert_path');
  const certPass = getConfigVar('verifactu_cert_pass');
  const nifSif   = getConfigVar('verifactu_nif_sif');
  const sisNom   = getConfigVar('verifactu_sistema_nombre', 'AlquiGest');
  const sisVer   = getConfigVar('verifactu_sistema_version', '3.3');
  const numInst  = getConfigVar('verifactu_num_instalacion', '1');

  // Estadísticas de facturas por estado VERI*FACTU
  const stats = estado?.stats_facturas || {};
  const pendientes = (parseInt(stats.pendiente_envio || 0) + parseInt(stats.error || 0));
  const enviadas   = parseInt(stats.enviado || 0);
  const noEnviadas = parseInt(stats.no_enviado || 0);

  // Info del certificado
  const certInfo  = estado?.cert_info || {};
  const certExiste = estado?.cert_existe;
  const certBadge = !certPath
    ? `<span class="badge badge-blue" style="font-size:11px">Sin certificado</span>`
    : (!certExiste
      ? `<span class="badge badge-red" style="font-size:11px">Archivo no encontrado</span>`
      : (certInfo.caducado
        ? `<span class="badge badge-red" style="font-size:11px">Caducado ${certInfo.valido_hasta || ''}</span>`
        : (certInfo.error
          ? `<span class="badge badge-orange" style="font-size:11px">Error al leer</span>`
          : `<span class="badge badge-green" style="font-size:11px">OK · ${certInfo.sujeto || ''} · hasta ${certInfo.valido_hasta || ''}</span>`)));

  // Facturas pendientes/con error
  const facturasPend = DB.get('facturas').filter(f =>
    f.verifactu_estado === 'pendiente_envio' || f.verifactu_estado === 'error'
  );

  document.getElementById('content').innerHTML = `
  <div style="display:grid;gap:16px">

    <!-- Banner de estado -->
    <div style="background:${activo ? '#d1fae5' : '#f3f4f6'};border:1px solid ${activo ? '#6ee7b7' : '#e5e7eb'};border-radius:12px;padding:18px 24px;display:flex;align-items:center;gap:16px">
      <div style="font-size:32px">${activo ? '🟢' : '⚫'}</div>
      <div>
        <div style="font-weight:700;font-size:16px;color:${activo ? '#065f46' : 'var(--gray-700)'}">
          VERI*FACTU está ${activo ? 'ACTIVADO' : 'DESACTIVADO'}
        </div>
        <div style="font-size:13px;color:var(--gray-500);margin-top:2px">
          Entorno: <strong>${entorno === 'produccion' ? '🔴 Producción (AEAT real)' : '🟡 Pruebas (prewww)'}</strong> &nbsp;·&nbsp;
          Facturas enviadas: <strong>${enviadas}</strong> &nbsp;·&nbsp;
          Pendientes/error: <strong style="color:${pendientes > 0 ? 'var(--red)' : 'inherit'}">${pendientes}</strong>
        </div>
      </div>
      ${activo
        ? `<button class="btn btn-danger btn-sm" style="margin-left:auto" onclick="toggleVerifactu(false)">Desactivar</button>`
        : `<button class="btn btn-primary btn-sm" style="margin-left:auto" onclick="toggleVerifactu(true)">Activar</button>`}
    </div>

    <!-- Columnas: config + certificado -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

      <!-- Configuración general -->
      <div class="card">
        <div class="card-header"><div class="card-title">⚙️ Configuración general</div></div>
        <div style="padding:16px;display:grid;gap:12px">
          <div>
            <label class="form-label">Entorno AEAT</label>
            <select id="vf-entorno" class="form-control">
              <option value="pruebas"    ${entorno==='pruebas'   ?'selected':''}>🟡 Pruebas (prewww1.aeat.es)</option>
              <option value="produccion" ${entorno==='produccion'?'selected':''}>🔴 Producción (www1.aeat.es)</option>
            </select>
            <small class="text-muted">Usa Pruebas hasta tener todo validado con la AEAT.</small>
          </div>
          <div>
            <label class="form-label">NIF del obligado de emisión <span style="color:var(--red)">*</span></label>
            <input id="vf-nif" class="form-control" value="${esc(nifSif)}" placeholder="Igual al NIF de empresa">
            <small class="text-muted">NIF del emisor registrado en el SIF de la AEAT.</small>
          </div>
          <div>
            <label class="form-label">Nombre del sistema informático</label>
            <input id="vf-sis-nom" class="form-control" value="${esc(sisNom)}" placeholder="AlquiGest">
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
            <div>
              <label class="form-label">Versión</label>
              <input id="vf-sis-ver" class="form-control" value="${esc(sisVer)}" placeholder="3.3">
            </div>
            <div>
              <label class="form-label">Nº instalación</label>
              <input id="vf-num-inst" class="form-control" value="${esc(numInst)}" placeholder="1">
            </div>
          </div>
          <div style="display:flex;gap:8px;margin-top:4px">
            <button class="btn btn-primary" onclick="saveVerifactuConfig()">Guardar configuración</button>
            <button class="btn btn-secondary" onclick="testConexionAEAT(this)">🔌 Test conexión</button>
          </div>
          <div id="vf-test-result"></div>
        </div>
      </div>

      <!-- Certificado digital -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">🔐 Certificado digital</div>
          <div>${certBadge}</div>
        </div>
        <div style="padding:16px;display:grid;gap:12px">
          ${certPath
            ? `<div style="font-size:13px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:6px;padding:10px">
                 <div style="font-weight:600;margin-bottom:4px">Certificado actual:</div>
                 <code style="font-size:11px">${esc(certPath)}</code>
                 ${certInfo.sujeto ? `<div style="margin-top:6px;color:var(--gray-500)">${esc(certInfo.sujeto)}</div>` : ''}
               </div>`
            : `<div class="callout callout-warn" style="padding:10px 14px;font-size:13px">
                 Sin certificado. Sube el .p12/.pfx de tu firma digital para enviar facturas a la AEAT.
               </div>`}
          <div>
            <label class="form-label">Subir certificado (.p12 / .pfx)</label>
            <input type="file" id="vf-cert-file" class="form-control" accept=".p12,.pfx">
            <small class="text-muted">El archivo se guarda en la carpeta <code>certs/</code> protegida contra acceso HTTP.</small>
          </div>
          <div>
            <label class="form-label">Contraseña del certificado</label>
            <input type="password" id="vf-cert-pass" class="form-control" value="${esc(certPass)}" placeholder="Contraseña del .p12/.pfx">
          </div>
          <div style="display:flex;gap:8px">
            <button class="btn btn-primary" onclick="subirCertificadoVF()">Subir certificado</button>
            <button class="btn btn-secondary" onclick="guardarCertPassVF()">Guardar contraseña</button>
          </div>
          <div id="vf-cert-result"></div>
        </div>
      </div>
    </div>

    <!-- Facturas pendientes/con error -->
    ${facturasPend.length ? `
    <div class="card">
      <div class="card-header">
        <div class="card-title" style="color:var(--red)">⚠ Facturas pendientes de envío a AEAT (${facturasPend.length})</div>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr>
            <th>Nº Factura</th><th>Fecha</th><th>Cliente</th><th>Total</th><th>Estado VF</th><th>Último error</th><th></th>
          </tr></thead>
          <tbody>
            ${facturasPend.map(f => `<tr>
              <td><strong>${esc(f.numero_factura)}</strong></td>
              <td>${fmtDate(f.fecha_emision)}</td>
              <td>${esc(f.cliente_nombre||'—')}</td>
              <td>${fmtMoney(f.importe_total)}</td>
              <td>${badgeVF(f.verifactu_estado)}</td>
              <td style="font-size:11px;color:var(--red);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                ${f.verifactu_estado === 'error' ? esc((f.verifactu_respuesta||'').slice(0,120)) : '—'}
              </td>
              <td>
                ${activo
                  ? `<button class="btn btn-sm btn-primary" onclick="enviarFacturaAEAT(${f.id},this)">Enviar AEAT</button>`
                  : `<span style="font-size:11px;color:var(--gray-400)">VERI*FACTU inactivo</span>`}
              </td>
            </tr>`).join('')}
          </tbody>
        </table>
      </div>
    </div>` : ''}

    <!-- Historial de envíos -->
    ${enviadas > 0 ? `
    <div class="card">
      <div class="card-header">
        <div class="card-title" style="color:var(--green)">✅ Facturas registradas en AEAT (${enviadas})</div>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr>
            <th>Nº Factura</th><th>Fecha</th><th>Cliente</th><th>Total</th><th>Hash (primeros 16)</th><th>QR</th>
          </tr></thead>
          <tbody>
            ${DB.get('facturas').filter(f => f.verifactu_estado === 'enviado').slice(0,50).map(f => `<tr>
              <td><strong>${esc(f.numero_factura)}</strong></td>
              <td>${fmtDate(f.fecha_emision)}</td>
              <td>${esc(f.cliente_nombre||'—')}</td>
              <td>${fmtMoney(f.importe_total)}</td>
              <td style="font-family:monospace;font-size:11px">${esc((f.hash_factura||'').slice(0,16))}…</td>
              <td>
                ${f.qr_url
                  ? `<a href="${esc(f.qr_url)}" target="_blank" rel="noopener"
                       style="font-size:11px;color:var(--blue)">Verificar AEAT →</a>`
                  : '—'}
              </td>
            </tr>`).join('')}
          </tbody>
        </table>
      </div>
    </div>` : ''}

    <!-- Guía rápida -->
    <div class="card">
      <div class="card-header"><div class="card-title">📖 Pasos para activar VERI*FACTU</div></div>
      <div style="padding:16px">
        <ol style="padding-left:20px;display:grid;gap:10px;font-size:14px;color:var(--gray-700)">
          <li>Rellena el <strong>NIF del obligado de emisión</strong> y guarda la configuración.</li>
          <li>Sube tu <strong>certificado digital</strong> (.p12/.pfx) y guarda la contraseña.</li>
          <li>Selecciona entorno <strong>Pruebas</strong> y pulsa <strong>Test conexión</strong> para verificar que alcanzas los servidores de la AEAT.</li>
          <li>Genera una factura de prueba y pulsa <strong>Enviar AEAT</strong> en el listado de facturas pendientes.</li>
          <li>Cuando todo funcione en pruebas, cambia a entorno <strong>Producción</strong>, guarda y pulsa <strong>Activar</strong>.</li>
          <li>A partir de ese momento, cada nueva factura se firmará y registrará automáticamente al generarla.</li>
        </ol>
        <p style="margin-top:12px;font-size:12px;color:var(--gray-400)">
          Consulta <a href="assets/docs/ayuda_verifactu.php" target="_blank">Guía técnica VERI*FACTU / AEAT</a> para más detalles.
        </p>
      </div>
    </div>
  </div>`;
}

// ── Activar / desactivar VERI*FACTU ──────────────────────────
async function toggleVerifactu(activar) {
  if (activar) {
    // Verificar requisitos mínimos antes de activar
    const nif  = getConfigVar('verifactu_nif_sif');
    const cert = getConfigVar('verifactu_cert_path');
    const pass = getConfigVar('verifactu_cert_pass');
    const avisos = [];
    if (!nif)  avisos.push('Falta el NIF del obligado de emisión.');
    if (!cert) avisos.push('No hay certificado digital subido.');
    if (!pass) avisos.push('No hay contraseña de certificado guardada.');
    if (avisos.length) {
      if (!confirm('⚠ Hay configuración incompleta:\n\n' + avisos.join('\n') +
                   '\n\n¿Activar de todas formas? Los envíos a AEAT podrían fallar.')) return;
    } else {
      if (!confirm('¿Activar VERI*FACTU?\n\nA partir de ahora cada nueva factura calculará su hash y se enviará automáticamente a la AEAT en el entorno configurado.')) return;
    }
  } else {
    if (!confirm('¿Desactivar VERI*FACTU?\n\nLas facturas seguirán generándose pero NO se enviarán a la AEAT.')) return;
  }
  await saveConfigVar('verifactu_activo', activar ? '1' : '0');
  toast('VERI*FACTU ' + (activar ? 'activado' : 'desactivado') + '.', 'success');
  renderVerifactu(navParams);
}

// ── Guardar configuración general ────────────────────────────
async function saveVerifactuConfig() {
  await saveConfigVar('verifactu_entorno',         document.getElementById('vf-entorno').value);
  await saveConfigVar('verifactu_nif_sif',         document.getElementById('vf-nif').value.trim());
  await saveConfigVar('verifactu_sistema_nombre',  document.getElementById('vf-sis-nom').value.trim());
  await saveConfigVar('verifactu_sistema_version', document.getElementById('vf-sis-ver').value.trim());
  await saveConfigVar('verifactu_num_instalacion', document.getElementById('vf-num-inst').value.trim());
  toast('Configuración VERI*FACTU guardada.', 'success');
  renderVerifactu(navParams);
}

// ── Guardar contraseña del certificado ───────────────────────
async function guardarCertPassVF() {
  const pass = document.getElementById('vf-cert-pass').value;
  await saveConfigVar('verifactu_cert_pass', pass);
  toast('Contraseña del certificado guardada.', 'success');
}

// ── Subir certificado .p12/.pfx ──────────────────────────────
async function subirCertificadoVF() {
  const fileInput = document.getElementById('vf-cert-file');
  const div = document.getElementById('vf-cert-result');
  if (!fileInput?.files?.length) { toast('Selecciona un archivo .p12 o .pfx', 'error'); return; }

  // Guardar la contraseña primero (la necesita el PHP para verificar)
  const pass = document.getElementById('vf-cert-pass').value;
  if (pass) await saveConfigVar('verifactu_cert_pass', pass);

  div.innerHTML = `<div style="color:var(--blue);font-size:13px">⏳ Subiendo certificado…</div>`;
  const formData = new FormData();
  formData.append('cert', fileInput.files[0]);

  try {
    const resp = await fetch('assets/php/verifactu.php?action=upload_cert', { method: 'POST', body: formData });
    const res  = await resp.json();
    if (res.ok) {
      await saveConfigVar('verifactu_cert_path', res.path);
      div.innerHTML = `<div style="color:var(--green);font-size:13px">✅ Certificado subido en <code>${esc(res.path)}</code>${res.info ? '<br>' + esc(res.info) : ''}</div>`;
      toast('Certificado subido correctamente.', 'success');
      setTimeout(() => renderVerifactu(navParams), 1200);
    } else {
      div.innerHTML = `<div style="color:var(--red);font-size:13px">❌ ${esc(res.error)}</div>`;
    }
  } catch(e) {
    div.innerHTML = `<div style="color:var(--red);font-size:13px">❌ Error de conexión: ${esc(e.message)}</div>`;
  }
}

// ── Test de conexión con la AEAT ─────────────────────────────
async function testConexionAEAT(btn) {
  const div = document.getElementById('vf-test-result');
  if (btn) { btn.disabled = true; btn.textContent = 'Probando…'; }
  div.innerHTML = `<div style="color:var(--blue);font-size:13px">⏳ Conectando con la AEAT…</div>`;
  try {
    const resp = await fetch('assets/php/verifactu.php?action=test_conexion');
    const res  = await resp.json();
    if (res.ok) {
      div.innerHTML = `<div style="color:var(--green);font-size:13px">
        ✅ Conexión OK — HTTP ${res.http_code} · ${res.tiempo_ms}ms<br>
        <span style="font-size:11px;color:var(--gray-400)">${esc(res.url)}</span>
      </div>`;
    } else {
      div.innerHTML = `<div style="color:var(--red);font-size:13px">
        ❌ ${esc(res.error || 'HTTP ' + res.http_code)}<br>
        <span style="font-size:11px;color:var(--gray-400)">${esc(res.url||'')}</span>
      </div>`;
    }
  } catch(e) {
    div.innerHTML = `<div style="color:var(--red);font-size:13px">❌ Error: ${esc(e.message)}</div>`;
  }
  if (btn) { btn.disabled = false; btn.textContent = '🔌 Test conexión'; }
}

// ── Ver XML SOAP que se enviaría ─────────────────────────────
async function verXMLFactura(facturaId) {
  const resp = await fetch('assets/php/verifactu.php?action=xml_preview', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ factura_id: facturaId })
  });
  const res = await resp.json();
  if (!res.ok) { toast('Error: ' + res.error, 'error'); return; }
  openModal('XML VERI*FACTU — Factura ' + facturaId,
    `<div style="max-height:400px;overflow:auto">
       <pre style="font-size:11px;background:var(--gray-900);color:#e2e8f0;padding:14px;border-radius:6px;white-space:pre-wrap;word-break:break-all">${esc(res.xml)}</pre>
     </div>
     <div style="margin-top:10px;font-size:12px;color:var(--gray-500)">
       Hash: <code>${esc(res.hash)}</code><br>
       QR URL: <code>${esc(res.qr_url)}</code>
     </div>`);
}

// ===========================
// BÚSQUEDA GLOBAL EN CABECERA (M-F01)
// Filtra en tiempo real entre inquilinos, inmuebles y contratos.
// Muestra un panel desplegable con un máximo de 8 resultados.
// ===========================