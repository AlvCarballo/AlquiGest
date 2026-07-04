// ===========================
// BACKUP DE BASE DE DATOS (M-F06)
// Descarga un fichero JSON con todos los datos via api.php?action=backup.
// ===========================
function descargarBackup() {
  const enlace = document.createElement('a');
  enlace.href = 'assets/php/api.php?action=backup';
  enlace.download = 'alquigest_backup_' + new Date().toISOString().slice(0, 10) + '.json';
  document.body.appendChild(enlace);
  enlace.click();
  document.body.removeChild(enlace);
  localStorage.setItem('ag_ultimo_backup', new Date().toISOString().slice(0, 10));
  toast('Backup descargado', 'success');
}

// ===========================
// ALERTA DE REVISIÓN ANUAL IPC / IRAV (M-L02)
// Devuelve los contratos activos con revisión IPC o IRAV cuyo mes de inicio
// coincide con el mes actual → revisión anual pendiente.
// ===========================
const TIPOS_REVISION_INE = ['IPC', 'IRAV'];

function contratosIPCPendientes() {
  const mesActual  = new Date().getMonth() + 1;
  const anioActual = new Date().getFullYear();
  return DB.get('contratos').filter(c => {
    if (c.estado !== ESTADO.ACTIVO) return false;
    if (!TIPOS_REVISION_INE.includes(c.revision)) return false;
    const ini = new Date(c.fecha_inicio + 'T00:00:00');
    if (ini.getMonth() + 1 !== mesActual) return false;
    if (ini.getFullYear() >= anioActual) return false;
    return parseInt(c.ipc_anio_aplicado) !== anioActual;
  });
}

// ===========================
// CERTIFICADO DE RETENCIONES IRPF (M-L01)
// Genera un PDF del certificado de retenciones del año anterior
// para el propietario indicado.
// ===========================
function pdfCertificadoIRPF(propietarioId) {
  const propietario = DB.getItem('propietarios', propietarioId);
  if (!propietario) { toast('Propietario no encontrado', 'error'); return; }
  const empresa = DB.getEmpresa() || {};
  const anio    = new Date().getFullYear() - 1; // certificado del ejercicio anterior

  // Recopilar todos los recibos del año para los inmuebles de este propietario
  const fincaIds   = DB.get('fincas').filter(f => f.propietario_id === propietarioId).map(f => f.id);
  const inmuebleIds = DB.get('inmuebles').filter(i => fincaIds.includes(i.finca_id)).map(i => i.id);
  const recibos = DB.get('recibos').filter(r =>
    inmuebleIds.includes(r.inmueble_id) &&
    r.estado !== ESTADO.ANULADO &&
    new Date(r.fecha_emision).getFullYear() === anio
  );

  const totalBase = recibos.reduce((s, r) => s + (r.renta_base || 0), 0);
  const totalIRPF = recibos.reduce((s, r) => s + (r.importe_irpf || 0), 0);

  const htmlCert = `<div style="font-family:Arial,sans-serif;padding:40px;max-width:750px">
    <h2 style="color:#1a56db;border-bottom:2px solid #1a56db;padding-bottom:8px">
      Certificado de Retenciones e Ingresos a Cuenta — Ejercicio ${anio}
    </h2>
    <p style="color:#6b7280;margin-bottom:20px">Modelo 190 — Arrendamientos de inmuebles</p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:20px">
      <div style="background:#f9fafb;padding:14px;border-radius:8px">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#6b7280;margin-bottom:6px">Retenedor</div>
        <strong>${esc(empresa.nombre||'')}</strong><br>
        NIF: ${esc(empresa.cif||'—')}<br>
        ${esc(empresa.direccion||'')}
      </div>
      <div style="background:#f9fafb;padding:14px;border-radius:8px">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#6b7280;margin-bottom:6px">Perceptor</div>
        <strong>${esc(propietario.nombre)}</strong><br>
        NIF: ${esc(propietario.nif||'—')}<br>
        ${esc(propietario.direccion||'')}
      </div>
    </div>
    <table style="width:100%;border-collapse:collapse;margin-bottom:16px">
      <thead>
        <tr style="background:#f3f4f6">
          <th style="padding:10px;border:1px solid #e5e7eb;text-align:left">Concepto</th>
          <th style="padding:10px;border:1px solid #e5e7eb;text-align:right">Importe</th>
        </tr>
      </thead>
      <tbody>
        <tr><td style="padding:10px;border:1px solid #e5e7eb">Rendimientos íntegros de arrendamiento (${recibos.length} recibos)</td>
            <td style="padding:10px;border:1px solid #e5e7eb;text-align:right">${fmtMoney(totalBase)}</td></tr>
        <tr><td style="padding:10px;border:1px solid #e5e7eb;font-weight:700">Retenciones practicadas (IRPF)</td>
            <td style="padding:10px;border:1px solid #e5e7eb;text-align:right;font-weight:700;color:#c81e1e">${fmtMoney(totalIRPF)}</td></tr>
      </tbody>
    </table>
    <p style="font-size:11px;color:#9ca3af;margin-top:20px;border-top:1px solid #e5e7eb;padding-top:12px">
      Generado con AlquiGest v2.0.0 · ${new Date().toLocaleDateString('es-ES')} · Los datos anteriores corresponden al ejercicio ${anio}.
    </p>
  </div>`;

  const div = document.createElement('div');
  div.innerHTML = htmlCert;
  div.style.cssText = 'position:absolute;left:-9999px;top:0;background:white;width:800px';
  document.body.appendChild(div);
  toast('Generando certificado IRPF…', 'info');
  html2canvas(div, { scale: 2, backgroundColor: '#ffffff' }).then(canvas => {
    document.body.removeChild(div);
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF('p', 'mm', 'a4');
    const pw = pdf.internal.pageSize.getWidth();
    pdf.addImage(canvas.toDataURL('image/jpeg', 0.92), 'JPEG', 0, 0, pw, pw * (canvas.height / canvas.width));
    downloadPDF(pdf, 'certificado_irpf_' + esc(propietario.nif) + '_' + anio + '.pdf');
  });
}

// ===========================
// CALENDARIO DE COBROS (M-UX04)
// Vista mensual con los recibos pendientes por día de vencimiento.
// ===========================
let _calMes  = new Date().getMonth();
let _calAnio = new Date().getFullYear();

function renderCalendario() {
  const hoy        = new Date();
  const mes        = _calMes, anio = _calAnio;
  const primerDia  = new Date(anio, mes, 1);
  const ultimoDia  = new Date(anio, mes + 1, 0);
  const nombresMes = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                      'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

  // Agrupar recibos del mes por día de fecha_limite (excluye anulados).
  // Si fecha_limite es null se calcula desde fecha_emision + dia_pago del contrato.
  const _contratosMap = {};
  DB.get('contratos').forEach(c => { _contratosMap[c.id] = c; });
  function _getFechaLimite(r) {
    if (r.fecha_limite) return r.fecha_limite;
    const fe = r.fecha_emision ? new Date(r.fecha_emision + 'T00:00:00') : null;
    if (!fe) return null;
    const cont = _contratosMap[r.contrato_id];
    const dia = (cont && cont.dia_pago) ? cont.dia_pago : 5;
    return fmtLocalISO(new Date(fe.getFullYear(), fe.getMonth(), dia));
  }
  const recibosMes = DB.get('recibos').filter(r => {
    if (r.estado === ESTADO.ANULADO) return false;
    const fl = _getFechaLimite(r);
    if (!fl) return false;
    const d = new Date(fl);
    return d.getFullYear() === anio && d.getMonth() === mes;
  });
  const porDia = {};
  recibosMes.forEach(r => {
    const dia = new Date(_getFechaLimite(r)).getDate();
    if (!porDia[dia]) porDia[dia] = [];
    porDia[dia].push(r);
  });

  // Cabecera de días de la semana
  const cabecera = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom']
    .map(d => `<div class="cal-head">${d}</div>`).join('');

  // Celdas del calendario
  let celdas = '';
  let diaSemana = primerDia.getDay();
  diaSemana = diaSemana === 0 ? 6 : diaSemana - 1; // convertir Dom=0 a 6
  for (let i = 0; i < diaSemana; i++) celdas += '<div class="cal-day otro-mes"></div>';

  for (let d = 1; d <= ultimoDia.getDate(); d++) {
    const esHoy = hoy.getDate() === d && hoy.getMonth() === mes && hoy.getFullYear() === anio;
    const chips = (porDia[d] || []).map(r => {
      const inq   = DB.getItem('inquilinos', r.inquilino_id);
      const clase = r.estado === ESTADO.COBRADO ? 'cobrado' : '';
      const nom   = (inq?.nombre || '?').split(' ')[0];
      return `<div class="cal-recibo-chip ${clase}" title="${esc(inq?.nombre||'')} · ${esc(r.numero_recibo)}"
               onclick="navigate('recibos')">${esc(nom)} ${fmtMoney(r.importe_total)}</div>`;
    }).join('');
    celdas += `<div class="cal-day${esHoy ? ' hoy' : ''}">
      <div class="cal-day-num">${d}</div>${chips}
    </div>`;
  }

  const pendientes = recibosMes.filter(r => r.estado === ESTADO.PENDIENTE || r.estado === ESTADO.PARCIAL).length;

  document.getElementById('content').innerHTML = `
    <div class="content-header">
      <div>
        <h2 style="margin:0">${nombresMes[mes]} ${anio}</h2>
        <div style="font-size:13px;color:var(--gray-500);margin-top:4px">${pendientes} recibos pendientes este mes</div>
      </div>
      <div style="display:flex;gap:8px">
        <button class="btn btn-secondary" onclick="_calMes--;if(_calMes<0){_calMes=11;_calAnio--}renderCalendario()">‹ Anterior</button>
        <button class="btn btn-secondary" onclick="_calMes=new Date().getMonth();_calAnio=new Date().getFullYear();renderCalendario()">Hoy</button>
        <button class="btn btn-secondary" onclick="_calMes++;if(_calMes>11){_calMes=0;_calAnio++}renderCalendario()">Siguiente ›</button>
      </div>
    </div>
    <div class="cal-grid">${cabecera}${celdas}</div>
  `;
}

// ===========================
// INFORME DE MOROSIDAD (M-F10)
// ===========================
const _MOR_DIAS = 30;
let _morFiltroTexto     = '';
let _morIncluirActuales = false;
let _morFiltroInquilino = '';

function _getMorosidadDatos() {
  const hoy = new Date();
  const lista = [];
  DB.get('recibos')
    .filter(r => r.estado === ESTADO.PENDIENTE || r.estado === ESTADO.PARCIAL)
    .forEach(r => {
      const fechaRef = r.fecha_emision;
      if (!fechaRef) return;
      const retraso = Math.floor((hoy - new Date(fechaRef)) / 86400000);
      if (!_morIncluirActuales && retraso < _MOR_DIAS) return;
      lista.push({
        recibo:    r,
        inquilino: DB.getItem('inquilinos', r.inquilino_id),
        inmueble:  DB.getItem('inmuebles',  r.inmueble_id),
        retraso
      });
    });
  lista.sort((a, b) => b.retraso - a.retraso);
  let result = lista;
  if (_morFiltroInquilino) {
    result = result.filter(m => String(m.recibo.inquilino_id) === String(_morFiltroInquilino));
  }
  if (_morFiltroTexto) {
    const q = _morFiltroTexto.toLowerCase();
    result = result.filter(m =>
      (m.inquilino?.nombre || '').toLowerCase().includes(q) ||
      getInmuebleNombre(m.inmueble).toLowerCase().includes(q) ||
      (m.recibo.numero_recibo || '').toLowerCase().includes(q)
    );
  }
  return result;
}

function _morHtmlImprimible(morosos) {
  const total = morosos.reduce((s, m) =>
    s + Math.max(0, (m.recibo.importe_total || 0) - (m.recibo.importe_pagado || 0)), 0);

  const filas = morosos.map(m => {
    const pend = Math.max(0, (m.recibo.importe_total || 0) - (m.recibo.importe_pagado || 0));
    let retrasoTxt, col;
    if (m.retraso < 0)          { retrasoTxt = 'No vencido'; col = '#6b7280'; }
    else if (m.retraso < _MOR_DIAS) { retrasoTxt = m.retraso + ' días'; col = '#d97706'; }
    else                        { retrasoTxt = m.retraso + ' días'; col = '#dc2626'; }
    return `<tr>
      <td style="padding:7px 10px;border:1px solid #e5e7eb">${esc(m.inquilino?.nombre || '—')}</td>
      <td style="padding:7px 10px;border:1px solid #e5e7eb">${esc(getInmuebleNombre(m.inmueble))}</td>
      <td style="padding:7px 10px;border:1px solid #e5e7eb">${esc(m.recibo.numero_recibo)}</td>
      <td style="padding:7px 10px;border:1px solid #e5e7eb">${fmtDate(m.recibo.fecha_emision)}</td>
      <td style="padding:7px 10px;border:1px solid #e5e7eb;text-align:center;color:${col};font-weight:700">${retrasoTxt}</td>
      <td style="padding:7px 10px;border:1px solid #e5e7eb;text-align:right;font-weight:600">${fmtMoney(pend)}</td>
    </tr>`;
  }).join('');

  const tipo   = _morIncluirActuales ? 'Todos los recibos no cobrados' : `Recibos sin cobrar con más de ${_MOR_DIAS} días desde su emisión`;
  const filtro = _morFiltroTexto ? ` · Filtro: "${esc(_morFiltroTexto)}"` : '';

  return `<div style="font-family:Arial,sans-serif;padding:32px;background:#fff;color:#111;max-width:960px">
    <h2 style="margin:0 0 4px;color:#1a56db">Informe de Morosidad</h2>
    <div style="font-size:12px;color:#6b7280;margin-bottom:20px">${tipo}${filtro} · ${new Date().toLocaleDateString('es-ES')}</div>
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead>
        <tr style="background:#f3f4f6">
          <th style="padding:8px 10px;border:1px solid #e5e7eb;text-align:left">Inquilino</th>
          <th style="padding:8px 10px;border:1px solid #e5e7eb;text-align:left">Inmueble</th>
          <th style="padding:8px 10px;border:1px solid #e5e7eb;text-align:left">Recibo</th>
          <th style="padding:8px 10px;border:1px solid #e5e7eb;text-align:left">Fecha emisión</th>
          <th style="padding:8px 10px;border:1px solid #e5e7eb;text-align:center">Días sin cobrar</th>
          <th style="padding:8px 10px;border:1px solid #e5e7eb;text-align:right">Pendiente</th>
        </tr>
      </thead>
      <tbody>${filas}</tbody>
    </table>
    <div style="margin-top:14px;padding:12px 16px;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;text-align:right">
      <span style="font-size:13px;color:#6b7280">${morosos.length} recibo${morosos.length !== 1 ? 's' : ''} · </span>
      <strong style="font-size:15px;color:#dc2626">Total pendiente: ${fmtMoney(total)}</strong>
    </div>
  </div>`;
}

function renderMorosidad() {
  const morosos        = _getMorosidadDatos();
  const totalPendiente = morosos.reduce((s, m) =>
    s + Math.max(0, (m.recibo.importe_total || 0) - (m.recibo.importe_pagado || 0)), 0);
  const subtitulo = _morIncluirActuales
    ? 'Todos los recibos no cobrados'
    : `Recibos sin cobrar con más de ${_MOR_DIAS} días desde su emisión`;

  const filas = morosos.map(m => {
    const pend = Math.max(0, (m.recibo.importe_total || 0) - (m.recibo.importe_pagado || 0));
    let retrasoHtml;
    if (m.retraso < 0)             retrasoHtml = `<span style="color:var(--gray-500)">No vencido</span>`;
    else if (m.retraso < _MOR_DIAS) retrasoHtml = `<span style="color:#d97706;font-weight:700">${m.retraso} días</span>`;
    else                            retrasoHtml = `<span style="color:var(--red);font-weight:700">${m.retraso} días</span>`;
    return `<tr>
      <td>${esc(m.inquilino?.nombre || '—')}</td>
      <td>${esc(getInmuebleNombre(m.inmueble))}</td>
      <td>${esc(m.recibo.numero_recibo)}</td>
      <td>${fmtDate(m.recibo.fecha_emision)}</td>
      <td style="text-align:center">${retrasoHtml}</td>
      <td style="text-align:right;font-weight:600">${fmtMoney(pend)}</td>
      <td><button class="btn btn-sm btn-primary" onclick="modalDarCobro(${m.recibo.id})">Cobrar</button></td>
    </tr>`;
  }).join('');

  // Inquilinos con recibos pendientes/parciales (para el selector, sin filtro de días)
  const _iqMap = new Map();
  DB.get('recibos').filter(r => r.estado === ESTADO.PENDIENTE || r.estado === ESTADO.PARCIAL).forEach(r => {
    if (!_iqMap.has(r.inquilino_id)) {
      const iq = DB.getItem('inquilinos', r.inquilino_id);
      if (iq) _iqMap.set(r.inquilino_id, iq.nombre);
    }
  });
  const iqOpciones = [..._iqMap.entries()]
    .sort((a, b) => a[1].localeCompare(b[1]))
    .map(([id, nombre]) => `<option value="${id}" ${String(_morFiltroInquilino)===String(id)?'selected':''}>${esc(nombre)}</option>`)
    .join('');

  document.getElementById('content').innerHTML = `
    <div class="content-header">
      <div>
        <h2 style="margin:0">Informe de Morosidad</h2>
        <div style="font-size:13px;color:var(--gray-500);margin-top:4px">${subtitulo}</div>
      </div>
      <span style="font-size:13px;color:var(--gray-600)">
        ${morosos.length} recibo${morosos.length!==1?'s':''} · <strong style="color:var(--red)">${fmtMoney(totalPendiente)}</strong> pendiente
      </span>
    </div>
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;flex-wrap:wrap">
      <select style="max-width:240px" onchange="_morFiltroInquilino=this.value;renderMorosidad()">
        <option value="">— Todos los inquilinos —</option>
        ${iqOpciones}
      </select>
      <input type="text" placeholder="Buscar inmueble o recibo…"
             style="max-width:220px" value="${esc(_morFiltroTexto)}"
             oninput="_morFiltroTexto=this.value;renderMorosidad()">
      <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;margin-bottom:0;font-weight:400">
        <input type="checkbox" ${_morIncluirActuales ? 'checked' : ''}
               onchange="_morIncluirActuales=this.checked;renderMorosidad()">
        Incluir recibos con menos de ${_MOR_DIAS} días desde su emisión
      </label>
    </div>
    ${morosos.length === 0
      ? `<div style="text-align:center;padding:60px;color:var(--gray-400)">
           <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin-bottom:12px"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
           <div style="font-size:16px;font-weight:600;margin-bottom:4px">Sin resultados</div>
           <div>No hay recibos con los filtros actuales</div>
         </div>`
      : `<table class="data-table">
           <thead><tr>
             <th>Inquilino</th><th>Inmueble</th><th>Recibo</th>
             <th>Fecha emisión</th><th style="text-align:center">Días sin cobrar</th>
             <th style="text-align:right">Pendiente</th><th></th>
           </tr></thead>
           <tbody>${filas}</tbody>
         </table>`
    }
  `;
  document.getElementById('header-actions').innerHTML =
    `<button class="btn btn-secondary" onclick="imprimirMorosidad()">Imprimir</button>
     <button class="btn btn-secondary" onclick="pdfMorosidad()">PDF</button>`;
}

function pdfMorosidad() {
  const morosos = _getMorosidadDatos();
  if (!morosos.length) { toast('No hay datos para exportar', 'info'); return; }
  const div = document.createElement('div');
  div.innerHTML = _morHtmlImprimible(morosos);
  div.style.cssText = 'position:absolute;left:-9999px;top:0;background:white;width:1000px';
  document.body.appendChild(div);
  toast('Generando PDF…', 'info');
  html2canvas(div, { scale: 1.5, backgroundColor: '#ffffff' }).then(canvas => {
    document.body.removeChild(div);
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF('l', 'mm', 'a4');
    const pw  = pdf.internal.pageSize.getWidth();
    pdf.addImage(canvas.toDataURL('image/jpeg', 0.92), 'JPEG', 0, 0, pw, pw * (canvas.height / canvas.width));
    downloadPDF(pdf, 'morosidad_' + new Date().toISOString().slice(0, 10) + '.pdf');
  });
}

function imprimirMorosidad() {
  const morosos = _getMorosidadDatos();
  if (!morosos.length) { toast('No hay datos para imprimir', 'info'); return; }
  const iframe = document.createElement('iframe');
  iframe.style.cssText = 'position:absolute;left:-9999px;top:0;width:1px;height:1px';
  document.body.appendChild(iframe);
  const html = _morHtmlImprimible(morosos);
  iframe.contentDocument.write(`<!DOCTYPE html><html><head><meta charset="utf-8">
    <style>body{margin:0}@media print{@page{margin:1.5cm}}</style>
    </head><body>${html}</body></html>`);
  iframe.contentDocument.close();
  setTimeout(() => {
    iframe.contentWindow.focus();
    iframe.contentWindow.print();
    setTimeout(() => document.body.removeChild(iframe), 4000);
  }, 400);
}

// ===========================
// IMPORTACIÓN MASIVA DESDE CSV Y EXCEL (.xlsx) — [M-UX05 / Fase 4 #32]
// Soporta ambos formatos en el mismo dropzone.
// · CSV  → procesado en el navegador (FileReader)
// · XLSX → enviado a import.php (PHP/ZipArchive), que devuelve JSON
// La vista previa y la importación real son compartidas entre ambos formatos.
// ===========================

// Datos pre-procesados listos para importar (array de objetos {col: valor})
let _csvDatos = [];

function renderImportar() {
  document.getElementById('content').innerHTML = `
    <div class="content-header">
      <h2 style="margin:0">Importar datos</h2>
      <span style="font-size:13px;color:var(--gray-500)">CSV o Excel (.xlsx)</span>
    </div>
    <div style="max-width:660px">

      <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px;flex-wrap:wrap">
        <div style="display:flex;align-items:center;gap:8px">
          <label style="margin:0;font-weight:600">¿Qué importar?</label>
          <select id="csv-tipo" style="width:auto">
            <option value="inquilinos">Inquilinos</option>
            <option value="propietarios">Propietarios</option>
          </select>
        </div>
      </div>

      <!-- Zona de arrastrar y soltar -->
      <div class="csv-dropzone" id="csv-dropzone"
           onclick="document.getElementById('csv-file').click()"
           ondragover="event.preventDefault();this.classList.add('drag-over')"
           ondragleave="this.classList.remove('drag-over')"
           ondrop="event.preventDefault();this.classList.remove('drag-over');_importarArchivo(event.dataTransfer.files[0])">
        <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin-bottom:12px">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
          <polyline points="17 8 12 3 7 8"/>
          <line x1="12" y1="3" x2="12" y2="15"/>
        </svg>
        <div style="font-size:15px;font-weight:600">Arrastra tu fichero aquí</div>
        <div style="font-size:13px;margin-top:6px;color:var(--gray-500)">o haz clic para seleccionar</div>
        <div style="margin-top:8px;display:flex;gap:8px;justify-content:center">
          <span style="background:var(--gray-100);border:1px solid var(--gray-300);border-radius:4px;padding:2px 8px;font-size:11px;font-weight:600">.CSV</span>
          <span style="background:#e0f2fe;border:1px solid #93c5fd;border-radius:4px;padding:2px 8px;font-size:11px;font-weight:600;color:#1e40af">.XLSX</span>
        </div>
        <input type="file" id="csv-file" accept=".csv,.txt,.xlsx"
               style="display:none" onchange="_importarArchivo(this.files[0])">
      </div>

      <!-- Estado de procesado (spinner, error, etc.) -->
      <div id="csv-estado" style="margin-top:12px"></div>

      <!-- Vista previa de datos -->
      <div id="csv-preview" style="margin-top:12px"></div>

      <!-- Botones de acción (ocultos hasta que haya datos) -->
      <div id="csv-acciones" style="margin-top:12px;display:none">
        <button class="btn btn-primary" onclick="csvImportar()">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
          Importar registros
        </button>
        <button class="btn btn-secondary" style="margin-left:8px"
                onclick="_importarLimpiar()">Cancelar</button>
      </div>

      <!-- Ayuda de formato -->
      <details style="margin-top:20px;font-size:12px;color:var(--gray-500)">
        <summary style="cursor:pointer;font-weight:600">Ver formato esperado</summary>
        <div style="margin-top:8px;background:var(--gray-50);padding:12px;border-radius:6px">
          <p style="font-weight:600;margin-bottom:6px">Columnas requeridas por tabla:</p>
          <div style="font-family:monospace;font-size:11px;line-height:1.8">
            <strong>Inquilinos (CSV separado por ;):</strong><br>
            nombre;nif;telefono;movil;email;direccion;cp;municipio;provincia;pais;iban;observaciones<br><br>
            <strong>Propietarios (CSV separado por ;):</strong><br>
            nombre;nif;telefono;email;irpf;direccion;cp;municipio;provincia;pais;iban;observaciones<br><br>
            <strong>Excel (.xlsx):</strong> Primera fila = nombres de columna (mismos que CSV). Sin separador.
          </div>
        </div>
      </details>
    </div>
  `;
}

// Limpia el estado de la importación (botón Cancelar)
function _importarLimpiar() {
  _csvDatos = [];
  const prev  = document.getElementById('csv-preview');
  const acc   = document.getElementById('csv-acciones');
  const est   = document.getElementById('csv-estado');
  if (prev) prev.innerHTML = '';
  if (acc)  acc.style.display = 'none';
  if (est)  est.innerHTML = '';
}

// Punto de entrada común para CSV y XLSX: detecta el tipo por extensión
function _importarArchivo(file) {
  if (!file) return;
  _importarLimpiar();
  var ext = file.name.split('.').pop().toLowerCase();
  if (ext === 'xlsx') {
    _xlsxProcesar(file);
  } else if (ext === 'csv' || ext === 'txt') {
    csvProcesar(file);
  } else {
    var est = document.getElementById('csv-estado');
    if (est) est.innerHTML = '<div style="color:var(--red);padding:10px">Formato no soportado. Usa .csv, .txt o .xlsx</div>';
  }
}

// Procesa un archivo CSV en el navegador (sin servidor)
function csvProcesar(file) {
  if (!file) return;
  var reader = new FileReader();
  reader.onload = function(e) {
    var texto  = e.target.result;
    // Detectar y eliminar BOM UTF-8 si existe
    if (texto.charCodeAt(0) === 0xFEFF) texto = texto.slice(1);
    var lineas = texto.trim().split('\n').filter(function(l){ return l.trim(); });
    if (lineas.length < 2) {
      toast('El CSV necesita al menos cabecera y una fila de datos', 'error');
      return;
    }
    var cabecera = lineas[0].split(';').map(function(c){ return c.trim().replace(/^"|"$/g, ''); });
    _csvDatos = lineas.slice(1).map(function(l) {
      var vals = l.split(';').map(function(v){ return v.trim().replace(/^"|"$/g, ''); });
      var obj  = {};
      cabecera.forEach(function(col, i){ obj[col] = vals[i] || ''; });
      return obj;
    }).filter(function(o){ return Object.values(o).some(function(v){ return v; }); });
    _importarMostrarPreview(cabecera, _csvDatos, 'CSV');
  };
  reader.onerror = function() { toast('Error al leer el fichero', 'error'); };
  reader.readAsText(file, 'utf-8');
}

// Envía el .xlsx a import.php (server-side) y procesa la respuesta JSON
function _xlsxProcesar(file) {
  var est = document.getElementById('csv-estado');
  if (est) est.innerHTML = '<div style="padding:12px;color:var(--gray-500)">⏳ Leyendo fichero Excel…</div>';

  var fd = new FormData();
  fd.append('archivo', file);

  fetch('assets/php/import.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (est) est.innerHTML = '';
      if (!res.ok) {
        var elErr = document.getElementById('csv-estado');
        if (elErr) elErr.innerHTML =
          '<div style="padding:12px;background:#fee2e2;color:#991b1b;border-radius:8px;font-size:13px">' +
          '<strong>Error al leer el Excel:</strong> ' + esc(res.error) + '</div>';
        return;
      }
      // Convertir las filas (array de arrays) a array de objetos {col: valor}
      _csvDatos = res.filas.map(function(fila) {
        var obj = {};
        res.cabecera.forEach(function(col, i){ obj[col] = fila[i] || ''; });
        return obj;
      }).filter(function(o){ return Object.values(o).some(function(v){ return v; }); });
      _importarMostrarPreview(res.cabecera, _csvDatos, 'Excel');
    })
    .catch(function(e) {
      if (est) est.innerHTML =
        '<div style="padding:12px;background:#fee2e2;color:#991b1b;border-radius:8px;font-size:13px">' +
        'Error de conexión con el servidor: ' + esc(e.message) + '</div>';
    });
}

// Muestra la tabla de previsualización y activa el botón Importar
// Compartida entre CSV y XLSX para no duplicar código (DRY).
function _importarMostrarPreview(cabecera, datos, origen) {
  var prev = document.getElementById('csv-preview');
  var acc  = document.getElementById('csv-acciones');
  if (!prev) return;

  var badgeColor = origen === 'Excel'
    ? 'background:#e0f2fe;color:#1e40af;border:1px solid #93c5fd'
    : 'background:var(--gray-100);color:var(--gray-600);border:1px solid var(--gray-300)';

  prev.innerHTML =
    '<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">' +
    '  <span style="font-size:13px;font-weight:600;color:var(--gray-700)">' +
    datos.length + ' registros detectados</span>' +
    '  <span style="' + badgeColor + ';border-radius:4px;padding:2px 8px;font-size:11px;font-weight:700">' +
    origen + '</span>' +
    '</div>' +
    '<div style="overflow-x:auto">' +
    '<table class="data-table" style="font-size:12px">' +
    '<thead><tr>' + cabecera.map(function(c){ return '<th>' + esc(c) + '</th>'; }).join('') + '</tr></thead>' +
    '<tbody>' + datos.slice(0, 5).map(function(r) {
      return '<tr>' + cabecera.map(function(c){ return '<td>' + esc(r[c] || '') + '</td>'; }).join('') + '</tr>';
    }).join('') + '</tbody>' +
    '</table>' +
    (datos.length > 5 ? '<div style="padding:8px;color:var(--gray-400);font-size:12px">…y ' + (datos.length - 5) + ' filas más</div>' : '') +
    '</div>';

  if (acc) acc.style.display = '';
}

// Importa los datos pre-procesados a la BD (igual para CSV y XLSX)
async function csvImportar() {
  const tipo = document.getElementById('csv-tipo').value;
  if (!_csvDatos.length) { toast('No hay datos para importar', 'error'); return; }

  const btn = document.querySelector('#csv-acciones .btn-primary');
  if (btn) { btn.disabled = true; btn.textContent = 'Importando…'; }

  let ok = 0, err = 0, errores = [];
  for (const fila of _csvDatos) {
    const res = await DB.save(tipo, { ...fila });
    if (res && !res.error) {
      ok++;
    } else {
      err++;
      if (errores.length < 5) errores.push(fila.nombre || JSON.stringify(fila).slice(0,60));
    }
  }

  if (btn) { btn.disabled = false; btn.textContent = 'Importar registros'; }

  if (ok > 0) {
    const msg = ok + ' registros importados correctamente' + (err > 0 ? ' · ' + err + ' con error' : '');
    toast(msg, 'success');
    _csvDatos = [];
    navigate(tipo);
  } else {
    const detalle = errores.length ? ' · Primeros errores: ' + errores.join(', ') : '';
    toast('No se pudo importar ningún registro' + detalle, 'error');
  }
}
