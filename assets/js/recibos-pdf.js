// ============================================================
//  AlquiGest – recibos-pdf.js
//  Impresión y generación de PDF de recibos (individuales y en lote):
//    buildReciboHTML()     → genera el HTML del recibo (A4 o A5)
//    imprimirRecibo()      → abre ventana nueva con window.print()
//    guardarReciboPDF()    → descarga directa mediante jsPDF + html2canvas
//    ejecutarPDFLote()     → PDF multipágina del lote filtrado
//    ejecutarImpresionLote() → ventana de impresión del lote
//    ejecutarEmailLote()   → envío por email del PDF del lote
//    ejecutarWhatsappLote() → descarga PDF para adjuntar al WA del lote
// ============================================================

function imprimirReciboModal(id) {
  const r = DB.getItem('recibos', id);
  if (!r) return;
  openModal('Imprimir / PDF', `
    <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin-bottom:16px">
      <button class="btn btn-primary" onclick="imprimirRecibo(${id},'a4')">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Imprimir A4
      </button>
      <button class="btn btn-secondary" onclick="imprimirRecibo(${id},'a5')">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Imprimir A5
      </button>
      <button class="btn btn-secondary" style="background:#dc2626;color:white;border-color:#dc2626" onclick="guardarReciboPDF(${id},'a4')">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
        Descargar PDF
      </button>
    </div>
    <p style="font-size:12px;color:var(--gray-500);text-align:center">Al imprimir, selecciona "Guardar como PDF" en el diálogo del navegador para obtener el PDF.</p>
    <div style="margin-top:12px;border:1px solid var(--gray-200);border-radius:8px;overflow:auto;max-height:400px;padding:8px">
      ${buildReciboHTML(id, 'a4')}
    </div>
  `, `<button class="btn btn-secondary" onclick="closeModal()">Cerrar</button>`, true);
}

function buildReciboHTML(id, formato) {
  const r = DB.getItem('recibos', id);
  if (!r) return '';
  const empresa = DB.getEmpresa() || {};
  const inq = DB.getItem('inquilinos', r.inquilino_id) || {};
  const inm = DB.getItem('inmuebles', r.inmueble_id) || {};
  const finca = DB.getItem('fincas', inm.finca_id) || {};
  const prop = DB.getItem('propietarios', finca.propietario_id) || {};
  const contrato = DB.getItem('contratos', r.contrato_id) || {};
  const cls = formato === 'a5' ? 'recibo-a5' : 'recibo-a4';

  // Parsear los conceptos adicionales: se escriben uno por línea con formato
  // "Descripción: importe" (ej: "Comunidad: 45,00€"). El separador es ":"
  // y el importe acepta coma decimal y símbolo €.
  const extras = (r.conceptos_extra||'').split('\n').filter(l => l.trim()).map(l => {
    const parts = l.split(':');
    return { desc: parts[0]||l, importe: parseFloat((parts[1]||'0').replace(',','.').replace('€',''))||0 };
  });

  // Sección de pagos: solo se renderiza si el recibo tiene al menos un pago registrado.
  // Muestra cada cobro (fecha, método, cuenta/referencia e importe),
  // el total ya pagado y, si queda saldo pendiente, el importe restante en rojo.
  const _pagos = Array.isArray(r.pagos) ? r.pagos.filter(p => p && (p.importe > 0)) : [];
  const _metodoLabel = { transferencia:'Transferencia', efectivo:'Efectivo', bizum:'Bizum', cheque:'Cheque', domiciliacion:'Domiciliación', otro:'Otro' };
  const pagosHtml = _pagos.length === 0 ? '' : (() => {
    const filas = _pagos.map(p => `
      <tr>
        <td>${p.fecha ? fmtDateShort(p.fecha) : '—'}</td>
        <td>${_metodoLabel[p.metodo] || (p.metodo ? esc(String(p.metodo)) : '—')}</td>
        <td>${p.cuenta ? esc(String(p.cuenta)) : '—'}</td>
        <td>${fmtMoney(p.importe || 0)}</td>
      </tr>`).join('');
    const restante = Math.max(0, (r.importe_total || 0) - (r.importe_pagado || 0));
    const pendienteHtml = restante > 0.005 ? `
      <div class="recibo-total-line pendiente">
        <span>PENDIENTE DE PAGO</span><span>${fmtMoney(restante)}</span>
      </div>` : '';
    return `
    <div class="recibo-pagos">
      <div class="recibo-pagos-titulo">Pagos recibidos</div>
      <table>
        <thead>
          <tr><th>Fecha</th><th>Método</th><th>Cuenta / Referencia</th><th>Importe</th></tr>
        </thead>
        <tbody>${filas}</tbody>
      </table>
      <div class="recibo-totales" style="margin-top:3px">
        <div class="recibo-total-line subtotal" style="color:#065f46;font-weight:600">
          <span>TOTAL PAGADO</span><span>${fmtMoney(r.importe_pagado || 0)}</span>
        </div>
        ${pendienteHtml}
      </div>
    </div>`;
  })();

  return `
  <div class="${cls}" id="recibo-print-${formato}">
    <!-- TÍTULO -->
    <div class="recibo-titulo-box">
      <h1>RECIBO DE ALQUILER</h1>
      <p>${r.concepto_periodo||''}</p>
    </div>

    <!-- NÚMERO Y FECHA -->
    <div class="recibo-num-fecha">
      <div>
        <div style="font-size:9pt;color:#6b7280;text-transform:uppercase;letter-spacing:.05em">Nº Recibo</div>
        <div class="recibo-num">${r.numero_recibo}</div>
      </div>
      <div class="recibo-fecha">
        <div><strong>Fecha de emisión:</strong> ${fmtDateShort(r.fecha_emision)}</div>
        ${r.fecha_limite ? `<div><strong>Fecha límite pago:</strong> ${fmtDateShort(r.fecha_limite)}</div>` : ''}
        ${r.periodo_desde ? `<div><strong>Período:</strong> ${fmtDate(r.periodo_desde)} – ${fmtDate(r.periodo_hasta)}</div>` : ''}
      </div>
    </div>

    <!-- PARTES -->
    <div class="recibo-partes">
      <div class="recibo-parte">
        <div class="recibo-parte-titulo">&#x1F4CB; Propietario / Arrendador</div>
        <div class="recibo-parte-nombre">${prop.nombre||'—'}</div>
        <div class="recibo-parte-datos">
          ${prop.nif ? 'NIF: ' + prop.nif : ''}<br>
          ${prop.direccion||''}${prop.municipio?' · '+prop.municipio:''}${prop.provincia?' ('+prop.provincia+')':''}
        </div>
      </div>
      <div class="recibo-parte">
        <div class="recibo-parte-titulo">&#x1F3E0; Inquilino / Arrendatario</div>
        <div class="recibo-parte-nombre">${inq.nombre||'—'}</div>
        <div class="recibo-parte-datos">
          ${inq.nif ? 'NIF: ' + inq.nif : ''}<br>
          ${inq.direccion||''}${inq.municipio?' · '+inq.municipio:''}${inq.provincia?' ('+inq.provincia+')':''}
        </div>
      </div>
    </div>

    <!-- INMUEBLE -->
    <div class="recibo-inmueble">
      <div class="recibo-inmueble-titulo">Inmueble objeto del arrendamiento</div>
      <div class="recibo-inmueble-datos">
        ${[finca.calle, finca.numero, inm.planta, inm.puerta].filter(Boolean).join(' ')}${finca.cp ? ' · CP ' + finca.cp : ''}${finca.municipio ? ' · ' + finca.municipio : ''}${finca.provincia ? ' (' + finca.provincia + ')' : ''}
        ${inm.referencia_catastral ? ' · Ref. catastral: ' + inm.referencia_catastral : ''}
      </div>
    </div>

    <!-- CONCEPTOS Y TOTALES -->
    <div class="recibo-conceptos">
      <table>
        <thead>
          <tr><th style="width:70%">Concepto</th><th>Importe</th></tr>
        </thead>
        <tbody>
          <tr><td>Renta base de alquiler — ${r.concepto_periodo||''}</td><td>${fmtMoney(r.renta_base)}</td></tr>
          ${extras.map(e => `<tr><td>${e.desc}</td><td>${fmtMoney(e.importe)}</td></tr>`).join('')}
        </tbody>
      </table>
      <div class="recibo-totales">
        ${r.importe_iva > 0 ? `<div class="recibo-total-line subtotal"><span>IVA (${DB.getItem('contratos',r.contrato_id)?.iva_pct||0}%)</span><span>${fmtMoney(r.importe_iva)}</span></div>` : ''}
        ${r.importe_irpf > 0 ? `<div class="recibo-total-line subtotal"><span>Retención IRPF (${DB.getItem('contratos',r.contrato_id)?.irpf_pct||0}%)</span><span>– ${fmtMoney(r.importe_irpf)}</span></div>` : ''}
        <div class="recibo-total-line total-final">
          <span>TOTAL A PAGAR</span>
          <span>${fmtMoney(r.importe_total)}</span>
        </div>
      </div>
    </div>

    <!-- PAGOS RECIBIDOS (solo si hay cobros registrados) -->
    ${pagosHtml}

    <!-- IMPORTE EN LETRAS -->
    <div style="font-size:9pt;color:#555;margin-bottom:5mm;font-style:italic;text-align:center">
      Son: <strong>${montoEnLetras(r.importe_total)}</strong>
    </div>

    ${r.estado === 'cobrado' ? `
    <div style="text-align:center;margin-bottom:5mm">
      <div style="display:inline-block;border:2px solid var(--green,#057a55);color:var(--green,#057a55);padding:4px 16px;border-radius:4px;font-size:11pt;font-weight:700;transform:rotate(-5deg);">
        ✓ PAGADO${r.fecha_cobro ? ' – ' + fmtDate(r.fecha_cobro) : ''}
      </div>
    </div>` : ''}

    ${r.notas ? `<div class="recibo-notas"><strong>Notas:</strong> ${r.notas}</div>` : ''}
    ${contrato.aviso_recibo ? `<div style="margin-top:8px;padding:8px 12px;border:2px solid #dc2626;border-radius:4px;background:#fef2f2;color:#dc2626;font-weight:700;text-align:center;font-size:10pt">⚠ Recibo no válido sin el correspondiente justificante bancario</div>` : ''}

  </div>`;
}

// Extrae el CSS de la hoja assets/css/main.css (sin Bootstrap) para inyectarlo en la
// ventana de impresión y en los divs ocultos capturados con html2canvas.
// Se necesita porque las ventanas de impresión no heredan los estilos del padre.
function _getReciboCss() {
  let css = '';
  for (const sheet of document.styleSheets) {
    if (sheet.href && sheet.href.toLowerCase().includes('bootstrap')) continue;
    try { for (const rule of sheet.cssRules) css += rule.cssText + '\n'; } catch(e) {}
  }
  return css;
}

// Abre nueva ventana con los recibos y lanza impresión / guardar PDF
function _abrirVentanaImpresion(htmlArray, formato) {
  const size = formato === 'a5' ? 'A5' : 'A4';
  const css  = _getReciboCss();
  const win  = window.open('', '_blank', 'width=960,height=720');
  if (!win) { alert('Activa las ventanas emergentes del navegador para imprimir.'); return; }
  const body = htmlArray.join('<div style="page-break-after:always;height:0;"></div>');
  win.document.write(`<!DOCTYPE html><html lang="es"><head>
    <meta charset="UTF-8"><title>Recibos AlquiGest</title>
    <link rel="stylesheet" href="assets/css/main.css">
  </head><body>${body}
  <script>
    window.onload=function(){
      setTimeout(function(){
        window.print();
        window.onafterprint=function(){window.close();};
      },300);
    };
  <\/script>
  </body></html>`);
  win.document.close();
}

function imprimirRecibo(id, formato) {
  const html = buildReciboHTML(id, formato);
  _abrirVentanaImpresion([html], formato);
}

// Monta un recibo en un <div> fuera de pantalla (left:-9999px), inyecta el
// CSS con _getReciboCss() para que html2canvas lo vea, captura el canvas y
// elimina el contenedor del DOM. Devuelve { canvas, isA5 }.
async function _montarYCapturar(id, formato) {
  const isA5 = formato === 'a5';
  const container = document.createElement('div');
  container.style.cssText = 'position:fixed;left:-9999px;top:0;background:white;' +
    'width:' + (isA5 ? '148mm' : '210mm') + ';';
  const styleEl = document.createElement('style');
  styleEl.textContent = _getReciboCss();
  container.appendChild(styleEl);
  const inner = document.createElement('div');
  inner.innerHTML = buildReciboHTML(id, formato);
  container.appendChild(inner);
  document.body.appendChild(container);
  const el = inner.querySelector('.recibo-a4, .recibo-a5');
  try {
    const canvas = await html2canvas(el, { scale: 2, useCORS: true, backgroundColor: '#ffffff', logging: false });
    return { canvas, isA5 };
  } finally {
    document.body.removeChild(container);
  }
}

async function _generarPDFBase64(id, formato) {
  const { canvas, isA5 } = await _montarYCapturar(id, formato);
  const { jsPDF } = window.jspdf;
  const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: isA5 ? 'a5' : 'a4' });
  const pW = pdf.internal.pageSize.getWidth();
  const pH = pdf.internal.pageSize.getHeight();
  const imgH = (canvas.height / canvas.width) * pW;
  pdf.addImage(canvas.toDataURL('image/jpeg', 0.92), 'JPEG', 0, 0, pW, Math.min(imgH, pH));
  return pdf.output('datauristring').split(',')[1];
}

// Descarga directa sin diálogo de impresión
async function guardarReciboPDF(id, formato) {
  const r = DB.getItem('recibos', id);
  if (!r) return;
  if (!window.jspdf || !window.html2canvas) { toast('Librería PDF cargando, inténtalo en unos segundos', 'error'); return; }
  toast('Generando PDF…');
  try {
    const { canvas, isA5 } = await _montarYCapturar(id, formato || 'a4');
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: isA5 ? 'a5' : 'a4' });
    const pW = pdf.internal.pageSize.getWidth();
    const pH = pdf.internal.pageSize.getHeight();
    const imgH = (canvas.height / canvas.width) * pW;
    pdf.addImage(canvas.toDataURL('image/jpeg', 0.92), 'JPEG', 0, 0, pW, Math.min(imgH, pH));
    downloadPDF(pdf, (r.numero_recibo || 'recibo') + '.pdf');
  } catch(e) {
    toast('Error generando PDF: ' + e.message, 'error');
  }
}

// Alias para la descarga de PDF individual desde el botón de WhatsApp
function _descargarPDFReciboWA(id) {
  guardarReciboPDF(id, 'a4');
}

function ejecutarImpresionLote() {
  const recibos = _filtrarRecibosLote();
  if (!recibos.length) { toast('No hay recibos para los filtros seleccionados', 'error'); return; }
  const formato = document.getElementById('lote-formato').value;
  const htmls = recibos
    .sort((a, b) => (a.numero_recibo || '').localeCompare(b.numero_recibo || ''))
    .map(r => buildReciboHTML(r.id, formato));
  closeModalForce();
  _abrirVentanaImpresion(htmls, formato);
}

// Genera un documento jsPDF multi-página con el array de recibos dado.
// Compartido por ejecutarPDFLote, ejecutarEmailLote y ejecutarWhatsappLote
// para evitar duplicar el bucle de captura html2canvas.
async function _generarPDFLoteDoc(sorted, formato) {
  const { jsPDF } = window.jspdf;
  const isA5 = formato === 'a5';
  const doc  = new jsPDF({ orientation: 'portrait', unit: 'mm', format: isA5 ? 'a5' : 'a4' });
  const pW   = doc.internal.pageSize.getWidth();
  const pH   = doc.internal.pageSize.getHeight();
  for (let i = 0; i < sorted.length; i++) {
    if (i > 0) doc.addPage();
    const container = document.createElement('div');
    container.style.cssText = `position:fixed;left:-9999px;top:0;width:${isA5 ? '148mm' : '210mm'};background:#fff`;
    const styleEl = document.createElement('style');
    styleEl.textContent = _getReciboCss();
    container.appendChild(styleEl);
    const inner = document.createElement('div');
    inner.innerHTML = buildReciboHTML(sorted[i].id, formato);
    container.appendChild(inner);
    document.body.appendChild(container);
    const el = inner.querySelector('.recibo-a4, .recibo-a5') || inner;
    try {
      const canvas = await html2canvas(el, { scale: 2, useCORS: true, backgroundColor: '#ffffff', logging: false });
      const imgH   = (canvas.height / canvas.width) * pW;
      doc.addImage(canvas.toDataURL('image/jpeg', 0.92), 'JPEG', 0, 0, pW, Math.min(imgH, pH));
    } finally {
      document.body.removeChild(container);
    }
  }
  return doc;
}

async function ejecutarPDFLote() {
  const recibos = _filtrarRecibosLote();
  if (!recibos.length) { toast('No hay recibos para los filtros seleccionados', 'error'); return; }
  if (!window.jspdf || !window.html2canvas) { toast('Librería PDF cargando, inténtalo en unos segundos', 'error'); return; }
  const formato = document.getElementById('lote-formato').value;
  const nombre  = _nombreLote();
  const sorted  = recibos.sort((a, b) => (a.numero_recibo || '').localeCompare(b.numero_recibo || ''));
  closeModalForce();
  toast(`Generando PDF con ${sorted.length} recibo(s)…`);
  try {
    const doc = await _generarPDFLoteDoc(sorted, formato);
    downloadPDF(doc, `recibos-${nombre}.pdf`);
    toast('PDF generado correctamente');
  } catch (e) {
    toast('Error generando PDF: ' + e.message, 'error');
  }
}

// Genera el PDF del lote y lo envía por email al inquilino seleccionado.
// Solo disponible cuando se ha elegido un inquilino concreto en el modal.
async function ejecutarEmailLote() {
  const recibos = _filtrarRecibosLote();
  if (!recibos.length) { toast('No hay recibos para los filtros seleccionados', 'error'); return; }
  if (!window.jspdf || !window.html2canvas) { toast('Librería PDF cargando, inténtalo en unos segundos', 'error'); return; }
  const inqId  = parseInt(document.getElementById('lote-inquilino')?.value) || 0;
  const inq    = DB.getItem('inquilinos', inqId);
  if (!inq?.email) { toast('El inquilino no tiene email registrado', 'error'); return; }
  const formato = document.getElementById('lote-formato').value;
  const nombre  = _nombreLote();
  const sorted  = recibos.sort((a, b) => (a.numero_recibo || '').localeCompare(b.numero_recibo || ''));
  const info    = document.getElementById('lote-info');
  if (info) info.innerHTML = `<span style="color:#1e40af">⏳ Generando PDF (${sorted.length} recibo${sorted.length !== 1 ? 's' : ''})…</span>`;
  try {
    const doc    = await _generarPDFLoteDoc(sorted, formato);
    const b64    = doc.output('datauristring').split(',')[1];
    if (info) info.innerHTML = `<span style="color:#1e40af">⏳ Enviando email a ${esc(inq.email)}…</span>`;
    const asunto = `Recibos de alquiler – ${nombre.replace(/-/g, ' ')} (${sorted.length} recibo${sorted.length !== 1 ? 's' : ''})`;
    const resp   = await fetch('assets/php/email.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ recibo_id: sorted[0].id, pdf_base64: b64,
                             pdf_filename: `recibos-${nombre}.pdf`, asunto_personalizado: asunto })
    });
    const res = await resp.json();
    if (info) info.innerHTML = res.ok
      ? `<span style="color:var(--green);font-weight:600">✅ ${res.mensaje}</span>`
      : `<span style="color:var(--red)">❌ ${esc(res.error)}</span>`;
  } catch(e) {
    if (info) info.innerHTML = `<span style="color:var(--red)">❌ Error: ${esc(e.message)}</span>`;
  }
}

// Descarga el PDF del lote cuando se envía por WhatsApp.
// Ya no abre WhatsApp (el <a href> en loteActualizarBotonesExtra lo hace).
// Se llama con setTimeout desde el onclick del enlace para no interferir con
// la apertura del enlace en la nueva pestaña.
function ejecutarWhatsappLote() {
  if (!_cfgVisi('whatsappPDF')) return;
  ejecutarPDFLote();
}

// Alias para que los botones inline del modal puedan llamar a ejecutarWhatsappLote
// mediante el nombre _descargarPDFLoteWA (referencia histórica del código original)
var _descargarPDFLoteWA = ejecutarWhatsappLote;
