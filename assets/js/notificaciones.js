// ===========================
// AVISOS DE REVISIÓN ANUAL DE RENTA
// Calcula qué contratos tienen su aniversario (fecha_inicio) en los próximos
// 30 días. El aviso se usa para recordar al gestor que debe revisar la renta
// según IPC u otro índice pactado en el contrato.
// Los avisos se muestran en la campana de la cabecera y en el Dashboard.
// ===========================
function getAvisosRevision() {
  const contratos  = DB.get('contratos');
  const inmuebles  = DB.get('inmuebles');
  const inquilinos = DB.get('inquilinos');

  const hoy = new Date();
  hoy.setHours(0, 0, 0, 0);

  const avisos = [];
  for (const c of contratos) {
    if (c.estado !== 'activo' || !c.fecha_inicio) continue;

    const inicio = new Date(c.fecha_inicio + 'T00:00:00');
    // Calcular el aniversario: mismo día y mes que el inicio, año actual
    let aniversario = new Date(hoy.getFullYear(), inicio.getMonth(), inicio.getDate());
    // Si ya pasó (o es hoy y ya ha cambiado el día), proyectar al año siguiente
    if (aniversario.getTime() < hoy.getTime()) {
      aniversario = new Date(hoy.getFullYear() + 1, inicio.getMonth(), inicio.getDate());
    }

    const diasHasta = Math.round((aniversario - hoy) / 86400000);
    if (diasHasta < 0 || diasHasta > 30) continue; // fuera de la ventana de 30 días

    const inm = inmuebles.find(i => i.id === c.inmueble_id) || null;
    const inq = inquilinos.find(i => i.id === c.inquilino_id) || null;
    const anosContrato = aniversario.getFullYear() - inicio.getFullYear();

    avisos.push({ contrato: c, inmueble: inm, inquilino: inq,
                  fechaAniversario: aniversario, diasHasta, anosContrato });
  }
  avisos.sort((a, b) => a.diasHasta - b.diasHasta);
  return avisos;
}

function updateNotificationBell() {
  const avisos = getAvisosRevision();
  const btn    = document.getElementById('notif-btn');
  const badge  = document.getElementById('notif-badge');
  if (!btn) return;
  if (avisos.length) {
    badge.textContent = avisos.length;
    badge.style.display = 'flex';
    btn.classList.add('has-alerts');
  } else {
    badge.style.display = 'none';
    btn.classList.remove('has-alerts');
  }
}

function toggleNotifPanel(e) {
  e.stopPropagation();
  const panel  = document.getElementById('notif-panel');
  const isOpen = panel.style.display === 'block';
  panel.style.display = isOpen ? 'none' : 'block';
  if (!isOpen) renderNotifPanel();
}

function renderNotifPanel() {
  const avisos = getAvisosRevision();
  const panel  = document.getElementById('notif-panel');
  const hoy    = new Date(); hoy.setHours(0,0,0,0);

  const items = avisos.length ? avisos.map(a => {
    const esCritico = a.diasHasta <= 7;
    const esHoy     = a.diasHasta === 0;
    const tagClass  = esHoy ? 'notif-item-tag hoy' : 'notif-item-tag';
    const tagText   = esHoy
      ? '⚠ HOY — ' + a.anosContrato + 'º aniversario'
      : (esCritico ? '⏰ ' : '🔔 ') + 'En ' + a.diasHasta + ' día' + (a.diasHasta !== 1 ? 's' : '')
        + ' · ' + a.anosContrato + 'º aniversario';
    const inmNombre = a.inmueble ? esc(getInmuebleNombre(a.inmueble)) : '—';
    const inqNombre = a.inquilino ? esc(a.inquilino.nombre) : '—';
    const fechaStr  = a.fechaAniversario.toLocaleDateString('es-ES', {day:'2-digit', month:'long', year:'numeric'});
    return `<div class="notif-item" onclick="navigate('contratos');toggleNotifPanel(event)">
      <div class="${tagClass}">${tagText}</div>
      <div class="notif-item-inmueble">${inmNombre}</div>
      <div class="notif-item-inq">${inqNombre}</div>
      <div class="notif-item-fecha">Fecha de revisión: <strong>${fechaStr}</strong></div>
    </div>`;
  }).join('') : `<div class="notif-empty">✓ Sin avisos de revisión en los próximos 30 días</div>`;

  panel.innerHTML = `
    <div class="notif-panel-header">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      Avisos de revisión anual
      <span class="notif-panel-sub">Próximos 30 días</span>
    </div>
    ${items}`;
}

document.addEventListener('click', e => {
  const wrap = document.getElementById('notif-wrap');
  if (wrap && !wrap.contains(e.target)) {
    const panel = document.getElementById('notif-panel');
    if (panel) panel.style.display = 'none';
  }
});

// ===========================
// MÓDULO DE FACTURAS
//
// Una factura se genera desde un recibo existente. En el momento
// de generarla se copian todos los datos fiscales (emisor, cliente,
// importes, periodo) para garantizar inmutabilidad histórica.
//
// Reglas principales:
//  - Un recibo → una sola factura (UNIQUE en BD sobre recibo_id)
//  - Numeración derivada del recibo: REC-YYYYMM-NNNNN → FAC-YYYYMM-NNNNN
//  - Una factura emitida no se puede editar ni borrar físicamente
//  - Los datos del emisor/cliente se congelan en el momento de emisión
//
// Estado de VERI*FACTU: los campos hash_factura, hash_anterior, qr_url
// y verifactu_estado están preparados pero no se envían a AEAT todavía.
// TODO: implementar envío real a AEAT cuando se requiera.
// ===========================

// Badge visual para el estado de una factura
function badgeEstadoFactura(estado) {
  const map    = { emitida:'badge-green', anulada:'badge-red', rectificada:'badge-blue' };
  const labels = { emitida:'Emitida', anulada:'Anulada', rectificada:'Rectificada' };
  return `<span class="badge ${map[estado]||'badge-blue'}">${labels[estado]||estado}</span>`;
}

// ===========================
// GENERACIÓN DE NÚMERO DE FACTURA
// El número de factura es autonumérico e independiente del número de recibo,
// igual que los recibos: FAC-YYYYMM-NNNNN donde YYYYMM es el mes de hoy
// y NNNNN es la siguiente secuencia de facturas emitidas en ese mes.
// Un recibo REC-202606-00058 podría generar FAC-202606-00001 si es la primera
// factura del mes, o FAC-202606-00043 si ya hay 42 facturas ese mes.
// Retorna { numeroFactura, numeroSeq } o null si hay error irrecuperable.
// ===========================