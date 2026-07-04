// ===========================
// EMPRESA
// ===========================
function renderEmpresa() {
  const e = DB.getEmpresa() || {};
  const showBackup = _cfgGet('VisiBackupJSON', '0') !== '0';
  document.getElementById('header-actions').innerHTML = `
    <button class="btn btn-primary" onclick="saveEmpresa()">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
      Guardar
    </button>
    ${showBackup ? `<button class="btn btn-secondary" onclick="descargarBackup()">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Descargar backup JSON
    </button>` : ''}`;
  document.getElementById('content').innerHTML = `
    <div class="card">
      <div class="card-header"><div class="card-title">Datos de Mi Empresa / Administración</div></div>
      <div class="card-body">
        <div class="alert alert-info">Estos datos aparecerán en la cabecera de todos los recibos.</div>
        <form id="form-empresa" onsubmit="saveEmpresa(event)">
          <div class="form-grid form-grid-2" style="gap:16px">
            <div class="form-group" style="grid-column:1/-1">
              <div class="form-section-title">Identificación</div>
            </div>
            <div class="form-group">
              <label>Nombre / Razón Social *</label>
              <input name="nombre" value="${e.nombre||''}" required placeholder="Mi Administración de Fincas">
            </div>
            <div class="form-group">
              <label>CIF / NIF *</label>
              <input name="cif" value="${e.cif||''}" required placeholder="B12345678">
            </div>
            <div class="form-group">
              <label>Prefijo de recibos</label>
              <input name="prefijo_recibos" value="${e.prefijo_recibos||'R'}" placeholder="R" maxlength="5">
            </div>
            <div class="form-group">
              <label>Teléfono</label>
              <input name="telefono" value="${e.telefono||''}" placeholder="912 345 678">
            </div>
            <div class="form-group" style="grid-column:1/-1">
              <div class="form-section-title" style="margin-top:8px">Dirección</div>
            </div>
            <div class="form-group">
              <label>Dirección</label>
              <input name="direccion" value="${e.direccion||''}" placeholder="Calle Mayor, 1">
            </div>
            <div class="form-group">
              <label>Código Postal</label>
              <input name="cp" value="${e.cp||''}" placeholder="28001">
            </div>
            <div class="form-group">
              <label>Municipio</label>
              <input name="municipio" value="${e.municipio||''}" placeholder="Madrid">
            </div>
            <div class="form-group">
              <label>Provincia</label>
              <input name="provincia" value="${e.provincia||''}" placeholder="Madrid">
            </div>
            <div class="form-group">
              <label>Email</label>
              <input name="email" type="email" value="${e.email||''}" placeholder="admin@miadmin.es">
            </div>
            <div class="form-group">
              <label>Web</label>
              <input name="web" value="${e.web||''}" placeholder="www.miadmin.es">
            </div>
            <div class="form-group" style="grid-column:1/-1">
              <div class="form-section-title" style="margin-top:8px">Datos bancarios (para recibos)</div>
            </div>
            <div class="form-group" style="grid-column:1/-1">
              <label>IBAN</label>
              <input name="iban" value="${e.iban||''}" placeholder="ES00 0000 0000 0000 0000 0000">
            </div>
            <div class="form-group" style="grid-column:1/-1">
              <div class="form-section-title" style="margin-top:8px">📧 Configuración Email (Gmail)</div>
              <small style="color:var(--gray-500)">Necesitas una <strong>Contraseña de Aplicación</strong> de Google (no tu contraseña normal). <a href="https://myaccount.google.com/apppasswords" target="_blank">Crear contraseña de aplicación →</a></small>
            </div>
            <div class="form-group">
              <label>Email Gmail (remitente)</label>
              <input name="gmail_user" type="email" value="${e.gmail_user||''}" placeholder="tuemail@gmail.com">
            </div>
            <div class="form-group">
              <label>Contraseña de Aplicación Google</label>
              <div style="display:flex;gap:8px;align-items:center">
                <input name="gmail_pass" type="password" id="inp-gmail-pass" value="${e.gmail_pass||''}" placeholder="xxxx xxxx xxxx xxxx" style="flex:1">
                <button type="button" onclick="_togglePass('inp-gmail-pass',this)" title="Mostrar/ocultar contraseña" style="padding:6px 10px;border:1px solid var(--gray-300);border-radius:6px;background:var(--gray-50);cursor:pointer;flex-shrink:0">
                  <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </button>
              </div>
            </div>
          </div>
          <!-- Plantillas de email personalizables (M-F09) -->
          <div class="form-group" style="grid-column:1/-1;margin-top:16px">
            <div class="form-section-title">✉ Plantillas de Email</div>
            <small style="color:var(--gray-500)">
              Usa <code>{{numero_recibo}}</code>, <code>{{inquilino}}</code>, <code>{{importe}}</code>, <code>{{periodo}}</code>, <code>{{empresa}}</code> como variables dinámicas.
            </small>
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label>Asunto del email de recibo</label>
            <input name="email_asunto_recibo" value="${esc(e.email_asunto_recibo||'Recibo de alquiler {{numero_recibo}} — {{periodo}}')}"
                   placeholder="Recibo de alquiler {{numero_recibo}} — {{periodo}}">
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label>Cuerpo del email de recibo</label>
            <textarea name="email_cuerpo_recibo" rows="4" style="resize:vertical"
                      placeholder="Estimado/a {{inquilino}},\n\nAdjuntamos su recibo de alquiler...">${esc(e.email_cuerpo_recibo||'Estimado/a {{inquilino}},\n\nLe enviamos adjunto su recibo de alquiler correspondiente al período {{periodo}}.\n\nImporte: {{importe}}\n\nUn saludo,\n{{empresa}}')}</textarea>
          </div>
          <div class="form-group" style="grid-column:1/-1">
            <label>Asunto del email de factura</label>
            <input name="email_asunto_factura" value="${esc(e.email_asunto_factura||'Factura {{numero_factura}} — {{empresa}}')}"
                   placeholder="Factura {{numero_factura}} — {{empresa}}">
          </div>
          </div>
        </form>
      </div>
    </div>
  `;
}

async function saveEmpresa(evt) {
  if (evt) evt.preventDefault();
  const form = document.getElementById('form-empresa');
  if (!form) return;
  const data = Object.fromEntries(new FormData(form));
  await DB.setEmpresa(data);
  toast('Datos de empresa guardados');
}
