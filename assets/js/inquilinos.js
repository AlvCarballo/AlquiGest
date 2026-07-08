// ===========================
// INQUILINOS
// CRUD de inquilinos. Desde aquí se puede acceder al historial de pagos
// de cada inquilino (modalPagosInquilino) y descargar o imprimir sus recibos.
// La eliminación está bloqueada si el inquilino tiene contratos asociados.
// ===========================
let _inquilinosPag = 1;
function renderInquilinos() {
  const items = [...DB.get('inquilinos')].sort((a, b) =>
    (a.nombre||'').localeCompare(b.nombre||'', 'es', {sensitivity:'base'})
  );
  const _INQ_PP = Math.max(5, parseInt(_cfgGet('filas_inquilinos', '20')) || 20);
  const _inqTotPag = Math.max(1, Math.ceil(items.length / _INQ_PP));
  _inquilinosPag = Math.max(1, Math.min(_inquilinosPag, _inqTotPag));
  const inquilinosPag = items.slice((_inquilinosPag - 1) * _INQ_PP, _inquilinosPag * _INQ_PP);
  document.getElementById('header-actions').innerHTML = `
    <button class="btn btn-primary" onclick="modalInquilino()">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nuevo inquilino
    </button>`;

  document.getElementById('content').innerHTML = `
    <div class="card">
      <div class="card-header">
        <div class="card-title">Inquilinos (${items.length})</div>
        <div class="search-bar" style="width:260px">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="search" placeholder="Buscar..." oninput="filterTable(this,'tbl-inquilinos',[0,1,2])">
        </div>
      </div>
      <div class="table-wrap">
        <table id="tbl-inquilinos">
          <thead><tr><th>Nombre</th><th>NIF</th><th>Teléfono</th><th>Email</th><th>Estado</th><th></th></tr></thead>
          <tbody>
            ${items.length ? inquilinosPag.map(inq => {
              const contrato = DB.get('contratos').find(c => c.inquilino_id === inq.id && c.estado === 'activo');
              return `<tr>
                <td><strong>${esc(inq.nombre)}</strong></td>
                <td>${esc(inq.nif||'-')}</td>
                <td>${esc(inq.telefono||'-')}</td>
                <td>${esc(inq.email||'-')}</td>
                <td>${contrato ? '<span class="badge badge-green">Activo</span>' : '<span class="badge badge-orange">Sin contrato</span>'}</td>
                <td class="td-actions">
                  ${_cfgVisi('VisiPagosInq') ? `<button class="btn btn-sm btn-primary" style="font-size:11px" onclick="modalPagosInquilino(${inq.id})" title="Historial de pagos">Pagos</button>` : ''}
                  ${_cfgVisi('VisiHistorialInq') ? `<button class="btn btn-sm btn-secondary" style="font-size:11px" onclick="modalHistorialInquilino(${inq.id})" title="Historial completo">Historial</button>` : ''}
                  <button class="btn btn-sm btn-secondary btn-icon" title="Editar" onclick="modalInquilino(${inq.id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  </button>
                  ${_cfgVisi('VisiBorrarInq') ? `<button class="btn btn-sm btn-danger btn-icon" title="Eliminar" onclick="deleteInquilino(${inq.id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                  </button>` : ''}
                </td>
              </tr>`;
            }).join('') : '<tr><td colspan="6"><div class="empty-state"><svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/></svg><p>Sin inquilinos</p><small>Pulsa "Nuevo inquilino" para comenzar</small></div></td></tr>'}
          </tbody>
        </table>
      </div>
      ${_inqTotPag > 1 ? `
        <div class="table-pagination">
          <button class="btn btn-sm btn-secondary" onclick="_inquilinosPag=${_inquilinosPag-1};renderInquilinos()" ${_inquilinosPag<=1?'disabled':''}>‹ Ant.</button>
          <span>Página ${_inquilinosPag} de ${_inqTotPag} · ${items.length} inquilinos</span>
          <button class="btn btn-sm btn-secondary" onclick="_inquilinosPag=${_inquilinosPag+1};renderInquilinos()" ${_inquilinosPag>=_inqTotPag?'disabled':''}>Sig. ›</button>
        </div>` : ''}
    </div>
  `;
  makeTableSortable('tbl-inquilinos', {col:0, dir:1});
}

// ===========================
// HISTORIAL COMPLETO POR INQUILINO (M-F08)
// Modal con pestañas: Contratos, Recibos y Facturas del inquilino.
// ===========================
function modalHistorialInquilino(id) {
  const inq = DB.getItem('inquilinos', id);
  if (!inq) return;

  const contratos = DB.get('contratos').filter(c => c.inquilino_id === id);
  const recibos   = DB.get('recibos').filter(r => r.inquilino_id === id)
    .sort((a, b) => (b.fecha_emision || '').localeCompare(a.fecha_emision || ''));
  const facturas  = DB.get('facturas').filter(f => f.inquilino_id === id)
    .sort((a, b) => (b.fecha_emision || '').localeCompare(a.fecha_emision || ''));
  const inmuebles = DB.get('inmuebles');

  const totalCobrado = recibos.filter(r => r.estado === ESTADO.COBRADO)
    .reduce((s, r) => s + (r.importe_total || 0), 0);
  const totalPend    = recibos.filter(r => r.estado === ESTADO.PENDIENTE || r.estado === ESTADO.PARCIAL)
    .reduce((s, r) => s + (r.importe_total || 0) - (r.importe_pagado || 0), 0);

  // Pestaña de contratos
  const tabContratos = `
    <table class="data-table" style="font-size:12px">
      <thead><tr><th>Inmueble</th><th>Inicio</th><th>Fin</th><th>Renta</th><th>Estado</th></tr></thead>
      <tbody>${contratos.length
        ? contratos.map(c => {
            const inm = inmuebles.find(i => i.id === c.inmueble_id);
            return `<tr>
              <td>${esc(getInmuebleNombre(inm))}</td>
              <td>${fmtDate(c.fecha_inicio)}</td>
              <td>${fmtDate(c.fecha_fin)||'Indefinido'}</td>
              <td>${fmtMoney(c.renta_base)}</td>
              <td>${badgeEstado(c.estado)}</td>
            </tr>`;
          }).join('')
        : '<tr><td colspan="5" style="text-align:center;color:var(--gray-400)">Sin contratos</td></tr>'}
      </tbody>
    </table>`;

  // Pestaña de recibos
  const tabRecibos = `
    <div style="display:flex;gap:20px;margin-bottom:12px">
      <div style="text-align:center;background:var(--gray-50);padding:10px 16px;border-radius:8px">
        <div style="font-size:18px;font-weight:700;color:var(--green)">${fmtMoney(totalCobrado)}</div>
        <div style="font-size:11px;color:var(--gray-500)">Total cobrado</div>
      </div>
      <div style="text-align:center;background:var(--gray-50);padding:10px 16px;border-radius:8px">
        <div style="font-size:18px;font-weight:700;color:var(--orange)">${fmtMoney(totalPend)}</div>
        <div style="font-size:11px;color:var(--gray-500)">Pendiente</div>
      </div>
      <div style="text-align:center;background:var(--gray-50);padding:10px 16px;border-radius:8px">
        <div style="font-size:18px;font-weight:700;color:var(--blue)">${recibos.length}</div>
        <div style="font-size:11px;color:var(--gray-500)">Recibos emitidos</div>
      </div>
    </div>
    <table class="data-table" style="font-size:12px">
      <thead><tr><th>Nº</th><th>Período</th><th>Fecha</th><th>Total</th><th>Estado</th></tr></thead>
      <tbody>${recibos.length
        ? recibos.slice(0, 20).map(r =>
            `<tr class="tr-${r.estado||'pendiente'}">
              <td><strong>${esc(r.numero_recibo)}</strong></td>
              <td style="font-size:11px">${esc(r.concepto_periodo||'-')}</td>
              <td>${fmtDate(r.fecha_emision)}</td>
              <td>${fmtMoney(r.importe_total)}</td>
              <td>${badgeEstadoRecibo(r.estado)}</td>
            </tr>`).join('')
        : '<tr><td colspan="5" style="text-align:center;color:var(--gray-400)">Sin recibos</td></tr>'}
      </tbody>
    </table>
    ${recibos.length > 20 ? `<div style="padding:8px;font-size:12px;color:var(--gray-400)">…y ${recibos.length-20} recibos más. <a href="#" onclick="closeModal();navigate('recibos',{inquilino_id:${id}})">Ver todos →</a></div>` : ''}`;

  // Pestaña de facturas
  const tabFacturas = `
    <table class="data-table" style="font-size:12px">
      <thead><tr><th>Nº Factura</th><th>Fecha</th><th>Base</th><th>IVA</th><th>Total</th><th>Estado</th></tr></thead>
      <tbody>${facturas.length
        ? facturas.map(f =>
            `<tr>
              <td><strong>${esc(f.numero_factura)}</strong></td>
              <td>${fmtDate(f.fecha_emision)}</td>
              <td>${fmtMoney(f.base_imponible)}</td>
              <td>${fmtMoney(f.cuota_iva)}</td>
              <td>${fmtMoney(f.total)}</td>
              <td>${badgeEstado(f.estado_vf||f.estado)}</td>
            </tr>`).join('')
        : '<tr><td colspan="6" style="text-align:center;color:var(--gray-400)">Sin facturas</td></tr>'}
      </tbody>
    </table>`;

  openModal(`Historial — ${esc(inq.nombre)}`,
    `<div class="tab-bar" style="margin-bottom:12px">
      <button class="tab-btn active" id="tab-btn-contratos" onclick="switchTab('contratos')">Contratos (${contratos.length})</button>
      <button class="tab-btn" id="tab-btn-recibos"   onclick="switchTab('recibos')">Recibos (${recibos.length})</button>
      <button class="tab-btn" id="tab-btn-facturas"  onclick="switchTab('facturas')">Facturas (${facturas.length})</button>
    </div>
    <div id="tab-panel-contratos" class="tab-panel active">${tabContratos}</div>
    <div id="tab-panel-recibos"   class="tab-panel">${tabRecibos}</div>
    <div id="tab-panel-facturas"  class="tab-panel">${tabFacturas}</div>`,
    `<button class="btn btn-secondary" onclick="closeModal()">Cerrar</button>
     <button class="btn btn-primary" onclick="closeModal();navigate('recibos',{inquilino_id:${id}})">Ver recibos</button>`,
    true // modal grande
  );
}

// Cambia la pestaña activa dentro del historial del inquilino
function switchTab(nombre) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.getElementById('tab-btn-' + nombre)?.classList.add('active');
  document.getElementById('tab-panel-' + nombre)?.classList.add('active');
}

// Helper para badge de estado genérico
function badgeEstado(estado) {
  const map = { activo:'badge-green', finalizado:'badge-orange', rescindido:'badge-red',
                emitida:'badge-blue', rectificada:'badge-orange', enviado:'badge-green', error:'badge-red' };
  return `<span class="badge ${map[estado]||'badge-blue'}">${esc(estado||'-')}</span>`;
}

function modalInquilino(id=null) {
  const inq = id ? DB.getItem('inquilinos', id) : {};
  openModal(id ? 'Editar inquilino' : 'Nuevo inquilino', `
    <form id="form-inquilino" class="form-grid form-grid-2">
      <div class="form-group" style="grid-column:1/-1">
        <div class="form-section-title">Datos personales</div>
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>Nombre completo *</label>
        <input name="nombre" value="${inq.nombre||''}" required placeholder="Apellidos, Nombre o Razón Social">
      </div>
      <div class="form-group">
        <label>NIF / CIF</label>
        <input name="nif" value="${inq.nif||''}" placeholder="12345678A">
      </div>
      <div class="form-group">
        <label>Teléfono</label>
        <input name="telefono" value="${inq.telefono||''}" placeholder="600 000 000">
      </div>
      <div class="form-group">
        <label>Móvil</label>
        <input name="movil" value="${inq.movil||''}" placeholder="600 000 000">
      </div>
      <div class="form-group">
        <label>Email</label>
        <input name="email" type="email" value="${inq.email||''}" placeholder="inquilino@email.com">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <div class="form-section-title" style="margin-top:4px">Dirección</div>
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>Dirección actual</label>
        <input name="direccion" value="${inq.direccion||''}" placeholder="Calle, número, piso...">
      </div>
      <div class="form-group">
        <label>C.P.</label>
        <input name="cp" value="${inq.cp||''}" placeholder="28001">
      </div>
      <div class="form-group">
        <label>Municipio</label>
        <input name="municipio" value="${inq.municipio||''}" placeholder="Madrid">
      </div>
      <div class="form-group">
        <label>Provincia</label>
        <input name="provincia" value="${inq.provincia||''}" placeholder="Madrid">
      </div>
      <div class="form-group">
        <label>País</label>
        <input name="pais" value="${inq.pais||'España'}">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <div class="form-section-title" style="margin-top:4px">Datos bancarios</div>
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>IBAN</label>
        <input name="iban" value="${inq.iban||''}" placeholder="ES00 0000 0000 0000 0000 0000">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>Observaciones</label>
        <textarea name="observaciones">${inq.observaciones||''}</textarea>
      </div>
    </form>
  `, `
    <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
    <button class="btn btn-primary" onclick="saveInquilino(${id||'null'})">Guardar</button>
  `);
}

async function saveInquilino(id) {
  const form = document.getElementById('form-inquilino');
  if (!form.checkValidity()) { form.reportValidity(); return; }
  const data = Object.fromEntries(new FormData(form));
  if (id) data.id = id;
  await DB.save('inquilinos', data);
  closeModalForce();
  toast(id ? 'Inquilino actualizado' : 'Inquilino creado');
  renderInquilinos();
}

// ── Panel de historial de pagos de un inquilino ───────────────
// Muestra todos los recibos del año seleccionado con su estado.
// Desde aquí el usuario puede:
//   · Imprimir todos los recibos del año (imprimirRecibosInquilino)
//   · Descargar todos los recibos del año en un PDF (pdfRecibosInquilino)
//   · Descargar un resumen PDF de la cuenta del inquilino (pdfResumenInquilino)
function modalPagosInquilino(id) {
  const inq = DB.getItem('inquilinos', id);
  if (!inq) return;
  const anyo = new Date().getFullYear();
  const anyos = Array.from({length: 6}, (_, i) => anyo - i);
  openModal(`Historial de pagos · ${inq.nombre}`, `
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap">
      <label style="margin:0;font-weight:600;white-space:nowrap">Año:</label>
      <select id="sel-anyo-inquilino" onchange="renderPagosInquilinoTabla(${id})"
              style="padding:6px 10px;border:1px solid var(--gray-300);border-radius:6px;font-size:14px">
        ${anyos.map(y => `<option value="${y}" ${y===anyo?'selected':''}>${y}</option>`).join('')}
      </select>
      <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn-sm btn-secondary" onclick="pdfResumenInquilino(${id})">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:4px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>Resumen PDF
        </button>
        <button class="btn btn-sm btn-secondary" onclick="imprimirRecibosInquilino(${id})">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:4px"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>Imprimir Recibos
        </button>
        <button class="btn btn-sm btn-primary" onclick="pdfRecibosInquilino(${id})">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:4px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>Descargar PDF
        </button>
      </div>
    </div>
    <div id="tabla-pagos-inquilino"></div>
  `);
  renderPagosInquilinoTabla(id);
}

function renderPagosInquilinoTabla(id) {
  const anyo = parseInt(document.getElementById('sel-anyo-inquilino')?.value) || new Date().getFullYear();
  const recibos = DB.get('recibos').filter(r => {
    if (r.inquilino_id !== id) return false;
    const fecha = r.fecha_emision || r.fecha_creacion || '';
    return fecha.startsWith(String(anyo));
  }).sort((a,b) => b.id - a.id);
  const inmuebles = DB.get('inmuebles');
  const totalFacturado = recibos.reduce((s,r) => s + (r.importe_total||0), 0);
  const totalCobrado = recibos.reduce((s,r) => s + (r.importe_pagado||0), 0);
  const rows = recibos.map(r => {
    const inm = inmuebles.find(i => i.id === r.inmueble_id);
    const pagado = (r.pagos||[]).reduce((s,p)=>s+p.importe,0);
    return `<tr class="tr-${r.estado||'pendiente'}">
      <td>${fmtDate(r.fecha_emision)}</td>
      <td>${r.numero_recibo}</td>
      <td style="font-size:11px">${inm ? getInmuebleNombre(inm) : '-'}</td>
      <td>${r.concepto_periodo||'-'}</td>
      <td style="text-align:right">${fmtMoney(r.importe_total)}</td>
      <td style="text-align:right">${pagado !== 0 ? fmtMoney(pagado) : '-'}</td>
      <td>${badgeEstadoRecibo(r.estado)}</td>
    </tr>`;
  }).join('');
  document.getElementById('tabla-pagos-inquilino').innerHTML = `
    <div style="display:flex;gap:16px;margin-bottom:16px">
      <div style="flex:1;background:var(--gray-50);border-radius:8px;padding:12px;text-align:center">
        <div style="font-size:11px;color:var(--gray-500)">TOTAL FACTURADO</div>
        <div style="font-size:20px;font-weight:700">${fmtMoney(totalFacturado)}</div>
      </div>
      <div style="flex:1;background:#f0fdf4;border-radius:8px;padding:12px;text-align:center">
        <div style="font-size:11px;color:var(--gray-500)">TOTAL COBRADO</div>
        <div style="font-size:20px;font-weight:700;color:#15803d">${fmtMoney(totalCobrado)}</div>
      </div>
      <div style="flex:1;background:#fef9c3;border-radius:8px;padding:12px;text-align:center">
        <div style="font-size:11px;color:var(--gray-500)">PENDIENTE</div>
        <div style="font-size:20px;font-weight:700;color:#a16207">${fmtMoney(totalFacturado - totalCobrado)}</div>
      </div>
    </div>
    <div style="overflow-x:auto;max-height:380px;overflow-y:auto">
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead style="position:sticky;top:0;background:#fff"><tr>
        <th style="text-align:left;padding:8px 6px;border-bottom:2px solid var(--gray-200)">Fecha</th>
        <th style="text-align:left;padding:8px 6px;border-bottom:2px solid var(--gray-200)">Nº Recibo</th>
        <th style="text-align:left;padding:8px 6px;border-bottom:2px solid var(--gray-200)">Inmueble</th>
        <th style="text-align:left;padding:8px 6px;border-bottom:2px solid var(--gray-200)">Período</th>
        <th style="text-align:right;padding:8px 6px;border-bottom:2px solid var(--gray-200)">Total</th>
        <th style="text-align:right;padding:8px 6px;border-bottom:2px solid var(--gray-200)">Pagado</th>
        <th style="text-align:left;padding:8px 6px;border-bottom:2px solid var(--gray-200)">Estado</th>
      </tr></thead>
      <tbody>${rows || '<tr><td colspan="7" style="padding:20px;text-align:center;color:var(--gray-400)">Sin recibos en ' + anyo + '</td></tr>'}</tbody>
    </table>
    </div>`;
}

function pdfResumenInquilino(id) {
  if (!window.jspdf) { toast('Librería PDF cargando, inténtalo en unos segundos', 'error'); return; }
  const anyo = parseInt(document.getElementById('sel-anyo-inquilino')?.value) || new Date().getFullYear();
  const inq  = DB.getItem('inquilinos', id);
  const empresa = DB.getEmpresa() || {};
  const recibos = DB.get('recibos').filter(r => {
    if (r.inquilino_id !== id) return false;
    const f = r.fecha_emision || r.fecha_creacion || '';
    return f.startsWith(String(anyo));
  }).sort((a,b) => (a.fecha_emision||'').localeCompare(b.fecha_emision||''));
  const inmuebles = DB.get('inmuebles');
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF({ unit:'mm', format:'a4' });
  // Cabecera
  doc.setFontSize(14); doc.setFont(undefined,'bold');
  doc.text(`Historial de pagos · ${inq.nombre}`, 14, 18);
  doc.setFontSize(10); doc.setFont(undefined,'normal');
  doc.text(`Año: ${anyo}`, 14, 25);
  if (empresa.nombre) doc.text(empresa.nombre, 196, 18, {align:'right'});
  doc.setDrawColor(200,200,200); doc.line(14, 28, 196, 28);
  // Cabecera tabla
  let y = 35;
  const C = [14, 38, 68, 108, 140, 163, 184];
  doc.setFontSize(9); doc.setFont(undefined,'bold');
  ['Fecha','Nº Recibo','Inmueble','Período','Total','Pagado','Estado'].forEach((h,i) => {
    doc.text(h, C[i], y, i>=4?{align:'right'}:undefined);
  });
  doc.setDrawColor(150,150,150); doc.line(14, y+2, 196, y+2); y += 7;
  doc.setFont(undefined,'normal');
  let totFact = 0, totCob = 0;
  recibos.forEach(r => {
    const inm = inmuebles.find(i => i.id === r.inmueble_id);
    const pag = (r.pagos||[]).reduce((s,p)=>s+(p.importe||0),0);
    totFact += r.importe_total||0; totCob += pag;
    if (y > 272) { doc.addPage(); y = 20; }
    doc.text(fmtDate(r.fecha_emision)||'-', C[0], y);
    doc.text((r.numero_recibo||'-').substring(0,15), C[1], y);
    doc.text((inm?getInmuebleNombre(inm):'-').substring(0,20), C[2], y);
    doc.text((r.concepto_periodo||'-').substring(0,18), C[3], y);
    doc.text(fmtMoney(r.importe_total), C[4], y, {align:'right'});
    doc.text(pag>0?fmtMoney(pag):'-', C[5], y, {align:'right'});
    doc.text(r.estado||'-', C[6], y);
    y += 6;
  });
  // Totales
  y += 2; doc.setDrawColor(100,100,100); doc.line(14, y, 196, y); y += 5;
  doc.setFont(undefined,'bold');
  doc.text('TOTALES', C[0], y);
  doc.text(fmtMoney(totFact), C[4], y, {align:'right'});
  doc.text(fmtMoney(totCob), C[5], y, {align:'right'});
  y += 5; doc.setFont(undefined,'normal');
  doc.text(`Pendiente: ${fmtMoney(totFact - totCob)}`, C[0], y);
  downloadPDF(doc, `resumen-${inq.nombre}-${anyo}.pdf`);
  toast('PDF generado');
}

// Genera un PDF con todos los recibos del año seleccionado del inquilino.
// Cada recibo ocupa una página; usa html2canvas para capturar el HTML del
// recibo y jsPDF para ensamblar el PDF multipágina.
async function pdfRecibosInquilino(id) {
  if (!window.jspdf || !window.html2canvas) { toast('Librería PDF cargando, inténtalo en unos segundos', 'error'); return; }
  const anyo = parseInt(document.getElementById('sel-anyo-inquilino')?.value) || new Date().getFullYear();
  const inq  = DB.getItem('inquilinos', id);
  const recibos = DB.get('recibos').filter(r => {
    if (r.inquilino_id !== id) return false;
    const f = r.fecha_emision || r.fecha_creacion || '';
    return f.startsWith(String(anyo));
  }).sort((a,b) => (a.fecha_emision||'').localeCompare(b.fecha_emision||''));
  if (!recibos.length) { toast('No hay recibos para este año', 'error'); return; }
  toast(`Generando PDF con ${recibos.length} recibo(s)…`);
  try {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation:'portrait', unit:'mm', format:'a4' });
    const pW = doc.internal.pageSize.getWidth();
    const pH = doc.internal.pageSize.getHeight();
    for (let i = 0; i < recibos.length; i++) {
      if (i > 0) doc.addPage();
      const container = document.createElement('div');
      container.style.cssText = 'position:fixed;left:-9999px;top:0;width:794px;background:#fff';
      container.innerHTML = buildReciboHTML(recibos[i].id, 'a4');
      document.body.appendChild(container);
      const el = container.querySelector('.recibo-a4, .recibo-a5') || container;
      try {
        const canvas = await html2canvas(el, {scale:2, useCORS:true, backgroundColor:'#ffffff', logging:false});
        const imgH = (canvas.height / canvas.width) * pW;
        doc.addImage(canvas.toDataURL('image/jpeg', 0.92), 'JPEG', 0, 0, pW, Math.min(imgH, pH));
      } finally {
        document.body.removeChild(container);
      }
    }
    downloadPDF(doc, `recibos-${inq.nombre}-${anyo}.pdf`);
    toast('PDF generado correctamente');
  } catch(e) {
    toast('Error generando PDF: ' + e.message, 'error');
  }
}

// Imprime todos los recibos del año seleccionado del inquilino en una ventana
// nueva (diálogo de impresión del navegador). Alternativa a pdfRecibosInquilino
// cuando se prefiere imprimir en papel en vez de descargar el PDF.
function imprimirRecibosInquilino(id) {
  const anyo = parseInt(document.getElementById('sel-anyo-inquilino')?.value) || new Date().getFullYear();
  const recibos = DB.get('recibos').filter(r => {
    if (r.inquilino_id !== id) return false;
    const f = r.fecha_emision || r.fecha_creacion || '';
    return f.startsWith(String(anyo));
  }).sort((a,b) => (a.fecha_emision||'').localeCompare(b.fecha_emision||''));
  if (!recibos.length) { toast('No hay recibos para este año', 'error'); return; }
  const htmls = recibos.map(r => buildReciboHTML(r.id, 'a4'));
  _abrirVentanaImpresion(htmls, 'a4');
}

async function deleteInquilino(id) {
  const contratos = DB.get('contratos').filter(c => c.inquilino_id === id);
  if (contratos.length) { toast('No se puede eliminar: tiene contratos asociados', 'error'); return; }
  if (!confirm('¿Eliminar este inquilino?')) return;
  await DB.delete('inquilinos', id);
  toast('Inquilino eliminado', 'info');
  renderInquilinos();
}
