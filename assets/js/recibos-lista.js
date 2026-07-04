// ============================================================
//  AlquiGest – recibos-lista.js
//  Renderiza la tabla de recibos con filtros, paginación y el
//  modal de impresión / PDF en lote.
//
//  Nota: _recibosPag se declara en dashboard.js (variable global
//  compartida entre la lista de recibos y el dashboard).
// ============================================================

// ===========================
// RECIBOS DE ALQUILER
// Sección principal de gestión de recibos. Muestra todos los recibos
// filtrados opcionalmente por estado y con búsqueda por texto.
// Los params opcionales permiten mostrar recibos de una finca, inmueble
// o inquilino concreto cuando se navega desde esas secciones.
// ===========================
function renderRecibos(params) {
  params = params || {};
  const allItems = DB.get('recibos');
  const inquilinos = DB.get('inquilinos');
  const inmuebles = DB.get('inmuebles');
  const fincas = DB.get('fincas');
  // Cargar facturas para saber qué recibos ya tienen factura emitida
  const todasFacturas = DB.get('facturas');

  // Apply context filters from params
  let items = allItems;
  let ctxLabel = '';
  if (params.finca_id) {
    const inmsIds = inmuebles.filter(i => i.finca_id === params.finca_id).map(i => i.id);
    items = items.filter(r => inmsIds.includes(r.inmueble_id));
    const f = fincas.find(f => f.id === params.finca_id);
    ctxLabel = f ? ` · ${f.nombre}` : '';
  }
  if (params.inmueble_id) {
    items = items.filter(r => r.inmueble_id === params.inmueble_id);
    const inm = inmuebles.find(i => i.id === params.inmueble_id);
    ctxLabel = inm ? ` · ${getInmuebleNombre(inm)}` : '';
  }
  if (params.inquilino_id) {
    items = items.filter(r => r.inquilino_id === params.inquilino_id);
    const inq = inquilinos.find(i => i.id === params.inquilino_id);
    ctxLabel = inq ? ` · ${inq.nombre}` : '';
  }

  const propietarios = DB.get('propietarios');
  // Leer los valores actuales de todos los filtros desde el DOM
  const estadoSel   = document.getElementById('filtro-estado')?.value    || '';
  const fechaDesde  = document.getElementById('filtro-fecha-desde')?.value || '';
  const fechaHasta  = document.getElementById('filtro-fecha-hasta')?.value || '';
  const inqFiltroId  = parseInt(document.getElementById('filtro-inquilino')?.value  || '0') || 0;
  const propFiltroId = parseInt(document.getElementById('filtro-propietario')?.value || '0') || 0;

  const _inmMapR = new Map(inmuebles.map(i => [i.id, i]));
  const _inqMapR = new Map(inquilinos.map(i => [i.id, i]));
  let sorted = [...items].sort((a, b) => {
    const da = a.fecha_emision ? new Date(a.fecha_emision) : new Date(0);
    const db = b.fecha_emision ? new Date(b.fecha_emision) : new Date(0);
    if (db - da !== 0) return db - da;
    const inma = _inmMapR.get(a.inmueble_id);
    const inmb = _inmMapR.get(b.inmueble_id);
    return (inma ? getInmuebleNombre(inma) : '').localeCompare(inmb ? getInmuebleNombre(inmb) : '', 'es', {sensitivity:'base'});
  });
  // Aplicar filtros activos
  if (estadoSel)   sorted = sorted.filter(r => r.estado === estadoSel);
  if (fechaDesde)  sorted = sorted.filter(r => (r.fecha_emision || '') >= fechaDesde);
  if (fechaHasta)  sorted = sorted.filter(r => (r.fecha_emision || '') <= fechaHasta);
  if (inqFiltroId) sorted = sorted.filter(r => r.inquilino_id === inqFiltroId);
  if (propFiltroId) {
    const _fincaMap = new Map(fincas.map(f => [f.id, f]));
    sorted = sorted.filter(r => {
      const inm = _inmMapR.get(r.inmueble_id);
      if (!inm) return false;
      const finca = _fincaMap.get(inm.finca_id);
      return finca && finca.propietario_id === propFiltroId;
    });
  }

  // Paginación: configurable desde Configuración > filas_recibos (defecto 30)
  const RECIBOS_PP   = Math.max(5, parseInt(_cfgGet('filas_recibos', '30')) || 30);
  const totalRec     = sorted.length;
  const totalPagRec  = Math.max(1, Math.ceil(totalRec / RECIBOS_PP));
  _recibosPag        = Math.max(1, Math.min(_recibosPag, totalPagRec));
  const sortedPage   = sorted.slice((_recibosPag - 1) * RECIBOS_PP, _recibosPag * RECIBOS_PP);

  document.getElementById('header-actions').innerHTML = `
    <button class="btn btn-secondary" onclick="modalImprimirLote()" title="Imprimir todos los recibos de un período">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
      Imprimir lote / PDF
    </button>
    <button class="btn btn-primary" onclick="modalNuevoReciboLibre()">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nuevo recibo
    </button>`;

  document.getElementById('content').innerHTML = `
    <div class="filtros-bar">
      ${params.finca_id||params.inmueble_id||params.inquilino_id
        ? `<button class="btn btn-secondary" onclick="navigate('recibos')">← Quitar filtro</button>` : ''}
      <select id="filtro-estado" onchange="_recibosPag=1;renderRecibos(navParams)">
        <option value="" ${!estadoSel?'selected':''}>Todos los estados</option>
        <option value="pendiente" ${estadoSel==='pendiente'?'selected':''}>Pendientes</option>
        <option value="parcial"   ${estadoSel==='parcial'?'selected':''}>Parciales</option>
        <option value="cobrado"   ${estadoSel==='cobrado'?'selected':''}>Cobrados</option>
        <option value="anulado"   ${estadoSel==='anulado'?'selected':''}>Anulados</option>
        <option value="rectificativo" ${estadoSel==='rectificativo'?'selected':''}>Rectificativos</option>
      </select>
      <select id="filtro-propietario" onchange="_recibosPag=1;renderRecibos(navParams)">
        <option value="0">Todos los propietarios</option>
        ${propietarios.map(p => `<option value="${p.id}" ${propFiltroId===p.id?'selected':''}>${esc(p.nombre)}</option>`).join('')}
      </select>
      <select id="filtro-inquilino" onchange="_recibosPag=1;renderRecibos(navParams)">
        <option value="0">Todos los inquilinos</option>
        ${inquilinos.map(i => `<option value="${i.id}" ${inqFiltroId===i.id?'selected':''}>${esc(i.nombre)}</option>`).join('')}
      </select>
      <div style="display:flex;align-items:center;gap:6px">
        <label style="font-size:12px;color:var(--gray-500);margin:0">Desde</label>
        <input type="date" id="filtro-fecha-desde" value="${esc(fechaDesde)}" onchange="_recibosPag=1;renderRecibos(navParams)" style="width:140px">
      </div>
      <div style="display:flex;align-items:center;gap:6px">
        <label style="font-size:12px;color:var(--gray-500);margin:0">Hasta</label>
        <input type="date" id="filtro-fecha-hasta" value="${esc(fechaHasta)}" onchange="_recibosPag=1;renderRecibos(navParams)" style="width:140px">
      </div>
      ${(estadoSel||fechaDesde||fechaHasta||inqFiltroId||propFiltroId)
        ? `<button class="btn btn-secondary" onclick="document.getElementById('filtro-estado').value='';document.getElementById('filtro-fecha-desde').value='';document.getElementById('filtro-fecha-hasta').value='';document.getElementById('filtro-inquilino').value='0';document.getElementById('filtro-propietario').value='0';_recibosPag=1;renderRecibos(navParams)">✕ Limpiar</button>` : ''}
      <div style="margin-left:auto;font-size:13px;color:var(--gray-500)">${totalRec} recibos</div>
      <div class="search-bar" style="width:200px">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="search" placeholder="Buscar en tabla..." oninput="filterTable(this,'tbl-recibos',[0,1,2])">
      </div>
    </div>
    <div class="card">
      <div class="card-header">
        <div class="card-title">Recibos de alquiler${ctxLabel}</div>
      </div>
      <div class="table-wrap">
        <table id="tbl-recibos">
          <thead><tr><th>Nº Recibo</th><th>Inquilino</th><th>Inmueble</th><th>Período</th><th>Total</th><th>Pagado</th><th>Estado</th><th></th></tr></thead>
          <tbody>
            ${sortedPage.length ? sortedPage.map(r => {
              const inq = _inqMapR.get(r.inquilino_id);
              const inm = _inmMapR.get(r.inmueble_id);
              const pagado = (r.pagos||[]).reduce((s,p)=>s+p.importe,0);
              const puedeCobrarse = ['pendiente','parcial'].includes(r.estado);
              // Buscar si ya existe factura para este recibo
              const facturaDeEsteRecibo = todasFacturas.find(f => f.recibo_id === r.id);
              // Construir la URL de WhatsApp (protocolo según whatsappNativo en configuración)
              let _waHref = '';
              const _telRaw = String(inq?.movil || inq?.telefono || '');
              if (_telRaw && _cfgVisi('whatsappVis')) {
                let _tel = _telRaw.replace(/[\s\-()+.]/g, '');
                if (_tel.startsWith('00')) _tel = _tel.slice(2);
                if (/^[67]\d{8}$/.test(_tel)) _tel = '34' + _tel;
                const _emp = DB.getEmpresa() || {};
                const _msg = `*Recibo de alquiler*\n\nN\xba recibo: ${r.numero_recibo}\nPer\xedodo: ${r.concepto_periodo||''}\nFecha emisi\xf3n: ${fmtDateShort(r.fecha_emision)}\nImporte: ${fmtMoney(r.importe_total)}\nEstado: ${r.estado}` +
                  (_emp.nombre ? `\n\n${_emp.nombre}` : '') + (_emp.telefono ? `\nTel: ${_emp.telefono}` : '');
                _waHref = _buildWAUrl(_tel, _msg);
              }
              const _waSVG = `<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>`;
              return `<tr class="tr-${r.estado||'pendiente'}">
                <td><strong>${esc(r.numero_recibo)}</strong><br><small>${fmtDate(r.fecha_emision)}</small></td>
                <td>${esc(inq ? inq.nombre : '-')}</td>
                <td>${esc(inm ? getInmuebleNombre(inm) : '-')}</td>
                <td style="font-size:12px">${esc(r.concepto_periodo||'-')}</td>
                <td><strong>${fmtMoney(r.importe_total)}</strong></td>
                <td>${pagado > 0 ? fmtMoney(pagado) : '-'}</td>
                <td>${badgeEstadoRecibo(r.estado)}</td>
                <td class="td-actions">
                  ${_cfgVisi('VisiCobrarReci') ? (puedeCobrarse ? `<button class="btn btn-sm btn-success" style="font-size:11px" onclick="modalDarCobro(${r.id})">Cobrar</button>` : (r.estado === 'cobrado' ? `<button class="btn btn-sm btn-secondary" style="font-size:11px" onclick="modalDarCobro(${r.id})">Ver cobros</button>` : '')) : ''}
                  ${_cfgVisi('VisiEmailReci') ? (inq?.email
                    ? `<button class="btn btn-sm btn-info" style="font-size:11px" title="Enviar por email" onclick="enviarReciboEmail(${r.id})">✉</button>`
                    : `<button class="btn btn-sm btn-icon" style="font-size:11px;background:var(--gray-200);color:var(--gray-500);border-color:var(--gray-300);cursor:not-allowed" title="Sin email registrado" disabled>✉</button>`) : ''}
                  ${_cfgVisi('whatsappVis') ? (_waHref
                    ? (_cfgVisi('whatsappNativo')
                      ? `<button class="btn btn-sm btn-icon" style="background:#25d366;border-color:#1da851;color:#fff"
                                 title="Enviar por WhatsApp"
                                 onclick="enviarReciboWhatsapp(${r.id})">${_waSVG}</button>`
                      : `<a href="${esc(_waHref)}" target="_blank" rel="noopener noreferrer"
                                class="btn btn-sm btn-icon" style="background:#25d366;border-color:#1da851;color:#fff"
                                title="Enviar por WhatsApp"
                                onclick="if(_cfgVisi('whatsappPDF'))setTimeout(function(){_descargarPDFReciboWA(${r.id})},400)">${_waSVG}</a>`)
                    : `<button class="btn btn-sm btn-icon" style="background:var(--gray-200);border-color:var(--gray-300);color:var(--gray-500);opacity:.4;cursor:not-allowed" title="Sin teléfono registrado" disabled>${_waSVG}</button>`) : ''}
                  ${_cfgVisi('VisiImprimirReci') ? `<button class="btn btn-sm btn-primary btn-icon" title="Imprimir" onclick="imprimirReciboModal(${r.id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                  </button>` : ''}
                  <button class="btn btn-sm btn-secondary btn-icon" title="Editar" onclick="modalEditRecibo(${r.id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  </button>
                  ${_cfgVisi('VisiAnularReci') && r.estado !== 'anulado' && r.estado !== 'rectificativo' ? `<button class="btn btn-sm btn-danger btn-icon" title="Anular" onclick="anularRecibo(${r.id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                  </button>` : ''}
                  ${_cfgVisi('VisiFacturaReci') && r.estado !== 'anulado' && r.estado !== 'rectificativo'
                    ? (!facturaDeEsteRecibo
                      ? `<button class="btn btn-sm btn-violet btn-icon" style="font-size:10px" title="Generar factura legal" onclick="generarFacturaDesdeRecibo(${r.id})">
                           <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                           FAC
                         </button>`
                      : `<button class="btn btn-sm btn-navy btn-icon" style="font-size:10px" title="Ver/imprimir factura ${esc(facturaDeEsteRecibo.numero_factura)}" onclick="imprimirFacturaModal(${facturaDeEsteRecibo.id})">
                           <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                           FAC
                         </button>`)
                    : ''}
                </td>
              </tr>`;
            }).join('') : '<tr><td colspan="7"><div class="empty-state"><svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg><p>Sin recibos</p><small>Pulsa "Nuevo recibo" o genera desde Contratos</small></div></td></tr>'}
          </tbody>
        </table>
      </div>
      ${totalPagRec > 1 ? `
        <div class="table-pagination">
          <button class="btn btn-sm btn-secondary" onclick="_recibosPag=${_recibosPag-1};renderRecibos(navParams)" ${_recibosPag<=1?'disabled':''}>‹ Ant.</button>
          <span>Página ${_recibosPag} de ${totalPagRec} · ${totalRec} recibos</span>
          <button class="btn btn-sm btn-secondary" onclick="_recibosPag=${_recibosPag+1};renderRecibos(navParams)" ${_recibosPag>=totalPagRec?'disabled':''}>Sig. ›</button>
        </div>` : ''}
    </div>
  `;
  makeTableSortable('tbl-recibos', {col:3, dir:-1});
}

// Lote: constantes de meses y trimestres para el modal de impresión/PDF masiva
// Nombres de mes en español para detectar el trimestre de un recibo
const _MESES_LOTE = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                     'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
// Índices de mes (0-based) que pertenecen a cada trimestre
const _TRIM_MESES = { '1':[0,1,2], '2':[3,4,5], '3':[6,7,8], '4':[9,10,11] };

// Aplica todos los filtros del modal y devuelve los recibos coincidentes
function _filtrarRecibosLote() {
  const tipo    = document.getElementById('lote-tipo')?.value    || 'mes';
  const estado  = document.getElementById('lote-estado')?.value  || '';
  const inqId   = parseInt(document.getElementById('lote-inquilino')?.value) || 0;

  let recibos = DB.get('recibos').filter(r => r.estado !== 'anulado' && r.estado !== 'rectificativo');

  if (tipo === 'mes') {
    // Filtrar por el concepto_periodo exacto (ej: "Enero 2026")
    const periodo = document.getElementById('lote-periodo')?.value || '';
    recibos = recibos.filter(r => r.concepto_periodo === periodo);
  } else {
    // Para trimestre y año se usa la fecha_emision (los 4 primeros caracteres = año)
    const anyo = parseInt(document.getElementById('lote-anyo')?.value) || new Date().getFullYear();
    recibos = recibos.filter(r => (r.fecha_emision || '').startsWith(String(anyo)));
    if (tipo === 'trimestre') {
      // Detectar el trimestre del recibo por el nombre del mes en concepto_periodo
      const mesesTrim = _TRIM_MESES[document.getElementById('lote-trimestre')?.value || '1'];
      recibos = recibos.filter(r => {
        const mesIdx = _MESES_LOTE.findIndex(m => (r.concepto_periodo || '').startsWith(m));
        return mesIdx >= 0 && mesesTrim.includes(mesIdx);
      });
    }
    // Si tipo === 'anyo' ya filtramos solo por año, sin restricción de mes
  }

  if (estado) recibos = recibos.filter(r => r.estado === estado);
  if (inqId)  recibos = recibos.filter(r => r.inquilino_id === inqId);

  return recibos;
}

// Genera el nombre del fichero PDF según el tipo de período seleccionado
function _nombreLote() {
  const tipo = document.getElementById('lote-tipo')?.value || 'mes';
  if (tipo === 'mes') {
    return (document.getElementById('lote-periodo')?.value || 'lote').replace(' ', '-');
  }
  const anyo = document.getElementById('lote-anyo')?.value || new Date().getFullYear();
  if (tipo === 'trimestre') {
    const t = document.getElementById('lote-trimestre')?.value || '1';
    return `T${t}-${anyo}`;
  }
  return String(anyo);
}

// Muestra u oculta los campos del modal según el tipo de período elegido
// y actualiza el contador de recibos en tiempo real
function loteActualizarUI() {
  const tipo = document.getElementById('lote-tipo')?.value;
  document.getElementById('lote-wrap-mes').style.display       = tipo === 'mes'       ? '' : 'none';
  document.getElementById('lote-wrap-anyo').style.display      = tipo !== 'mes'       ? '' : 'none';
  document.getElementById('lote-wrap-trimestre').style.display = tipo === 'trimestre' ? '' : 'none';
  loteActualizarContador();
}

// Cuenta cuántos recibos coinciden y muestra el resultado en el modal.
// También actualiza los botones de envío (Email/WhatsApp) si hay inquilino elegido.
function loteActualizarContador() {
  const el = document.getElementById('lote-info');
  if (!el) return;
  try {
    const n = _filtrarRecibosLote().length;
    el.innerHTML = n
      ? `<span style="color:var(--green);font-weight:600">✓ ${n} recibo${n !== 1 ? 's' : ''} seleccionado${n !== 1 ? 's' : ''}</span>`
      : `<span style="color:var(--red)">No hay recibos para los filtros seleccionados</span>`;
  } catch(e) {
    el.innerHTML = '';
  }
  loteActualizarBotonesExtra();
}

// Muestra los botones de Email y WhatsApp solo cuando:
//   · Se ha seleccionado un inquilino concreto (no "Todos")
//   · Hay al menos un recibo coincidente con los filtros actuales
// El botón de WhatsApp es un <a href target="_blank"> real (nunca bloqueado
// por el navegador). Se oculta si whatsappVis=0 en configuración.
function loteActualizarBotonesExtra() {
  const wrap = document.getElementById('lote-btns-extra');
  if (!wrap) return;
  const inqId   = parseInt(document.getElementById('lote-inquilino')?.value) || 0;
  const recibos = _filtrarRecibosLote();
  // Solo activo cuando hay inquilino específico (no "Todos") y hay recibos
  if (!inqId || !recibos.length) { wrap.innerHTML = ''; return; }
  const inq = DB.getItem('inquilinos', inqId);
  if (!inq) { wrap.innerHTML = ''; return; }

  const tieneEmail = !!inq.email;
  const telRaw     = String(inq.movil || inq.telefono || '');
  const tieneTel   = !!telRaw;                              // tiene teléfono físicamente
  const tieneWA    = tieneTel && _cfgVisi('whatsappVis');   // botón WA habilitado en config

  // Ocultar sólo cuando no hay ningún método de contacto disponible
  if (!tieneEmail && !tieneTel) { wrap.innerHTML = ''; return; }

  const WA_SVG = `<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:4px"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>`;

  let btns = `<div style="border-top:1px solid var(--gray-200);margin-top:12px;padding-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <span style="font-size:12px;color:var(--gray-500)">Enviar a <strong>${esc(inq.nombre)}</strong>:</span>`;

  if (tieneEmail) {
    btns += `<button class="btn btn-sm btn-info" style="color:#fff" onclick="ejecutarEmailLote()">✉ Enviar por email</button>`;
  }

  if (tieneWA) {
    let tel = telRaw.replace(/[\s\-()+.]/g, '');
    if (tel.startsWith('00')) tel = tel.slice(2);
    if (/^[67]\d{8}$/.test(tel)) tel = '34' + tel;
    const empresa = DB.getEmpresa() || {};
    const nRec    = recibos.length;
    const nombre  = _nombreLote();
    const waMsg   = `*Recibos de alquiler – ${nombre.replace(/-/g, ' ')}*\n\n${nRec} recibo${nRec !== 1 ? 's' : ''} adjunto${nRec !== 1 ? 's' : ''}` +
                    (empresa.nombre   ? `\n\n${empresa.nombre}`    : '') +
                    (empresa.telefono ? `\nTel: ${empresa.telefono}` : '');
    const waUrl   = _buildWAUrl(tel, waMsg);
    if (_cfgVisi('whatsappNativo')) {
      btns += `<button class="btn btn-sm" style="background:#25d366;border-color:#1da851;color:#fff"
         onclick="window.open('${esc(waUrl)}','_blank');if(_cfgVisi('whatsappPDF'))setTimeout(_descargarPDFLoteWA,400)">${WA_SVG}Enviar por WhatsApp</button>`;
    } else {
      btns += `<a href="${esc(waUrl)}" target="_blank" rel="noopener noreferrer"
         class="btn btn-sm" style="background:#25d366;border-color:#1da851;color:#fff;text-decoration:none"
         onclick="if(_cfgVisi('whatsappPDF'))setTimeout(_descargarPDFLoteWA,400)">${WA_SVG}Enviar por WhatsApp</a>`;
    }
  }

  btns += `</div>`;
  wrap.innerHTML = btns;
}

function modalImprimirLote() {
  if (!DB.get('recibos').length) { toast('No hay recibos generados', 'error'); return; }

  const anyo = new Date().getFullYear();

  // Períodos disponibles (meses con recibos), ordenados de más reciente a más antiguo
  const periodos = [...new Set(DB.get('recibos').map(r => r.concepto_periodo).filter(Boolean))];
  periodos.sort((a, b) => {
    const iA = _MESES_LOTE.indexOf(a.split(' ')[0]), yA = parseInt(a.split(' ')[1]) || 0;
    const iB = _MESES_LOTE.indexOf(b.split(' ')[0]), yB = parseInt(b.split(' ')[1]) || 0;
    return (yB - yA) || (iB - iA);
  });

  // Inquilinos que tienen al menos un recibo, ordenados alfabéticamente
  const idsConRecibo = new Set(DB.get('recibos').map(r => r.inquilino_id));
  const inquilinos = DB.get('inquilinos')
    .filter(i => idsConRecibo.has(i.id))
    .sort((a, b) => (a.nombre || '').localeCompare(b.nombre || '', 'es', { sensitivity: 'base' }));

  openModal('Imprimir / PDF recibos en lote', `
    <div class="form-grid form-grid-3" style="gap:16px">

      <!-- Tipo de agrupación temporal -->
      <div class="form-group">
        <label>Agrupar por</label>
        <select id="lote-tipo" onchange="loteActualizarUI()" style="padding:8px">
          <option value="mes">Mes concreto</option>
          <option value="trimestre">Trimestre</option>
          <option value="anyo">Año completo</option>
        </select>
      </div>

      <!-- Selector de mes (solo visible si tipo = mes) -->
      <div class="form-group" id="lote-wrap-mes">
        <label>Período</label>
        <select id="lote-periodo" onchange="loteActualizarContador()" style="padding:8px">
          ${periodos.length ? periodos.map(p => `<option value="${p}">${p}</option>`).join('') : '<option value="">Sin períodos</option>'}
        </select>
      </div>

      <!-- Año (visible para trimestre y año) -->
      <div class="form-group" id="lote-wrap-anyo" style="display:none">
        <label>Año</label>
        <input type="number" id="lote-anyo" value="${anyo}" min="2000" max="2099"
               onchange="loteActualizarContador()" style="padding:8px">
      </div>

      <!-- Trimestre (solo visible si tipo = trimestre) -->
      <div class="form-group" id="lote-wrap-trimestre" style="display:none">
        <label>Trimestre</label>
        <select id="lote-trimestre" onchange="loteActualizarContador()" style="padding:8px">
          <option value="1">1T (Ene – Mar)</option>
          <option value="2">2T (Abr – Jun)</option>
          <option value="3">3T (Jul – Sep)</option>
          <option value="4">4T (Oct – Dic)</option>
        </select>
      </div>

      <!-- Filtro de inquilino -->
      <div class="form-group" style="grid-column:1/-1">
        <label>Inquilino <span style="font-size:11px;color:var(--gray-400);font-weight:400">(selecciona uno concreto para ver opciones de envío)</span></label>
        <select id="lote-inquilino" onchange="loteActualizarContador()" style="padding:8px">
          <option value="">Todos los inquilinos</option>
          ${inquilinos.map(i => `<option value="${i.id}">${esc(i.nombre)}</option>`).join('')}
        </select>
      </div>

      <!-- Formato de papel y filtro de estado -->
      <div class="form-group">
        <label>Formato</label>
        <select id="lote-formato" style="padding:8px">
          <option value="a4">A4 (vertical)</option>
          <option value="a5">A5 (vertical)</option>
        </select>
      </div>
      <div class="form-group">
        <label>Estado</label>
        <select id="lote-estado" onchange="loteActualizarContador()" style="padding:8px">
          <option value="">Todos (excepto anulados)</option>
          <option value="pendiente">Solo pendientes</option>
          <option value="cobrado">Solo cobrados</option>
        </select>
      </div>

    </div>
    <!-- Contador en tiempo real de recibos que coinciden con el filtro -->
    <div id="lote-info" style="margin-top:14px;font-size:13px;min-height:20px"></div>
    <!-- Botones de envío (Email / WhatsApp): solo cuando se selecciona un inquilino concreto -->
    <div id="lote-btns-extra"></div>
  `, `
    <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
    <button class="btn btn-secondary" onclick="ejecutarImpresionLote()">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
      Imprimir
    </button>
    <button class="btn btn-primary" onclick="ejecutarPDFLote()">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
      Descargar PDF
    </button>
  `, true);

  // Inicializar el contador al abrir el modal
  setTimeout(loteActualizarContador, 0);
}
