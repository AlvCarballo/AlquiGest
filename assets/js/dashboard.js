// ===========================
// DASHBOARD — PANTALLA PRINCIPAL
// Muestra fichas de resumen (propietarios, inmuebles, contratos activos,
// importe pendiente de cobro), tarjetas de avisos urgentes y las tablas
// de próximas renovaciones y revisiones anuales.
// Las tarjetas con más de _getDashPageSize() filas se paginan localmente.
// El número de filas por página se lee de la tabla 'configuracion'
// (variable 'filas_dashboard'). Si la entrada no existe, se usa 6 por defecto.
// ===========================
let _dashRenovaciones = [], _dashRenovPag = 1;
let _dashRevisiones   = [], _dashRevisionesPag = 1;
// Variable de paginación para la tabla de Recibos (M-F07)
let _recibosPag = 1;

// Devuelve el tamaño de página configurado en BD (variable 'filas_dashboard').
// Siempre devuelve al menos 1 para evitar divisiones por cero.
function _getDashPageSize() {
  const cfg = (DB.get('configuracion') || []).find(c => c.variable === 'filas_dashboard');
  return Math.max(1, parseInt(cfg?.valor) || 6);
}

// Devuelve true si el botón identificado por 'variable' debe mostrarse.
// Lee la tabla 'configuracion': valor '1' (o entrada inexistente) → visible;
// valor '0' → oculto. Así se pueden activar/desactivar botones destructivos
// (borrar, anular) desde la BD sin tocar el código.
function _cfgVisi(variable) {
  const cfg = (DB.get('configuracion') || []).find(c => c.variable === variable);
  // api.php convierte valores numéricos de VARCHAR a int, por eso 'valor'
  // puede llegar como número 0 en vez de cadena '0'. Se fuerza a string.
  return !cfg || String(cfg.valor) !== '0';
}

// Construye la URL de WhatsApp Web estándar (wa.me).
// El modo de apertura (modal/directo) lo controla whatsappNativo, no la URL.
function _buildWAUrl(tel, msg) {
  return 'https://wa.me/' + tel + '?text=' + encodeURIComponent(msg);
}

function _dashPagControls(page, totalPages, fnName) {
  if (totalPages <= 1) return '';
  const prev = `<button class="btn btn-sm btn-secondary" onclick="${fnName}(${page - 1})" ${page <= 1 ? 'disabled' : ''}>‹ Ant.</button>`;
  const next = `<button class="btn btn-sm btn-secondary" onclick="${fnName}(${page + 1})" ${page >= totalPages ? 'disabled' : ''}>Sig. ›</button>`;
  return `<div class="dash-pagination">${prev}<span>Página ${page} de ${totalPages}</span>${next}</div>`;
}

function _buildRenovCard(page) {
  const total = _dashRenovaciones.length;
  const ps = _getDashPageSize();
  const totalPages = Math.max(1, Math.ceil(total / ps));
  page = Math.max(1, Math.min(page, totalPages));
  const slice = _dashRenovaciones.slice((page - 1) * ps, page * ps);
  const filas = slice.map(r => {
    const diasParaAviso = r.dias - 120;
    let tiempoHtml, plazoHtml, rowStyle = '';
    if (r.dias < 0) {
      tiempoHtml = `<span class="renov-vencido">Vencido hace ${Math.abs(r.dias)} día${Math.abs(r.dias) !== 1 ? 's' : ''}</span>`;
      plazoHtml  = '<span style="color:var(--gray-400)">—</span>';
      rowStyle   = 'background:var(--row-vencido)';
    } else if (r.dias === 0) {
      tiempoHtml = `<span class="renov-vencido">Vence HOY</span>`;
      plazoHtml  = `<span class="renov-vencido">Plazo agotado</span>`;
      rowStyle   = 'background:var(--row-vencido)';
    } else if (r.dias < 120) {
      tiempoHtml = `<span class="renov-urgente">${r.dias} día${r.dias !== 1 ? 's' : ''}</span>`;
      plazoHtml  = `<span class="renov-plazo-agotado">Plazo agotado</span>`;
      rowStyle   = 'background:var(--row-alerta)';
    } else if (diasParaAviso === 0) {
      tiempoHtml = `<span class="renov-aviso">${r.dias} días</span>`;
      plazoHtml  = `<span class="renov-urgente">Último día para avisar</span>`;
      rowStyle   = 'background:var(--row-aviso)';
    } else {
      tiempoHtml = `<span class="renov-ok">${r.dias} días</span>`;
      plazoHtml  = `<span class="renov-aviso">${diasParaAviso} día${diasParaAviso !== 1 ? 's' : ''} para avisar</span>`;
    }
    const fechaStr = r.fechaFin.toLocaleDateString('es-ES', {day:'2-digit', month:'long', year:'numeric'});
    return `<tr style="${rowStyle}">
      <td><strong>${r.inmueble ? esc(getInmuebleNombre(r.inmueble)) : '—'}</strong></td>
      <td>${r.inquilino ? esc(r.inquilino.nombre) : '—'}</td>
      <td>${fechaStr}</td>
      <td style="text-align:center">${tiempoHtml}</td>
      <td style="text-align:center">${plazoHtml}</td>
      <td style="text-align:right;display:flex;gap:4px;justify-content:flex-end">
        <button class="btn btn-sm btn-secondary" onclick="modalRenovarContrato(${r.contrato.id})">Renovar</button>
        <button class="btn btn-sm btn-secondary" onclick="modalContrato(${r.contrato.id})">Gestionar</button>
      </td>
    </tr>`;
  }).join('');
  return `<div class="renovaciones-card">
    <div class="renovaciones-header">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      Próximas renovaciones de contrato
      <span style="margin-left:auto;font-size:11px;font-weight:400;color:var(--blue)">
        ${total} contrato${total !== 1 ? 's' : ''} · vencidos o próximos 6 meses
      </span>
      <button class="btn btn-sm btn-secondary" style="margin-left:8px" onclick="navigate('contratos')">Ver contratos</button>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Inmueble</th><th>Inquilino</th><th>Vencimiento</th>
            <th style="text-align:center">Tiempo restante</th>
            <th style="text-align:center">Plazo para avisar</th>
            <th></th>
          </tr>
        </thead>
        <tbody>${filas}</tbody>
      </table>
    </div>
    ${_dashPagControls(page, totalPages, 'dashRenovPage')}
  </div>`;
}

function dashRenovPage(n) {
  _dashRenovPag = n;
  const el = document.getElementById('renov-card-wrap');
  if (el) el.innerHTML = _buildRenovCard(n);
}

function _buildRevisionesCard(page) {
  const total = _dashRevisiones.length;
  const ps = _getDashPageSize();
  const totalPages = Math.max(1, Math.ceil(total / ps));
  page = Math.max(1, Math.min(page, totalPages));
  const slice = _dashRevisiones.slice((page - 1) * ps, page * ps);
  const filas = slice.map(r => {
    const inm  = r.inmueble  ? esc(getInmuebleNombre(r.inmueble)) : '—';
    const inq  = r.inquilino ? esc(r.inquilino.nombre) : '—';
    const fecha = r.fechaRevision.toLocaleDateString('es-ES', {day:'2-digit', month:'short', year:'numeric'});
    let cls = '', badge = '';
    if (r.dias <= 30) {
      cls   = 'rev-urgente';
      badge = '<span style="margin-left:6px;font-size:10px;background:rgba(239,68,68,.15);color:#ef4444;border:1px solid rgba(239,68,68,.3);border-radius:4px;padding:1px 5px">AVISO ACTIVO</span>';
    } else if (r.dias <= 90) {
      cls = 'rev-proximo';
    }
    const diasTxt = r.dias === 0
      ? '<strong style="color:#ef4444">Hoy</strong>'
      : `${r.dias} día${r.dias !== 1 ? 's' : ''}`;
    return `<tr class="${cls}">
      <td>${inm}</td>
      <td>${inq}</td>
      <td>${fecha}${badge}</td>
      <td style="text-align:center">${r.anosContrato}º año</td>
      <td style="text-align:center">${diasTxt}</td>
    </tr>`;
  }).join('');
  return `<div class="card revisiones-anuales-card">
    <div class="revisiones-anuales-header">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      Próximas revisiones anuales de renta
      <span style="margin-left:auto;font-size:12px;font-weight:400;opacity:.8">${total} contrato${total !== 1 ? 's' : ''} activo${total !== 1 ? 's' : ''}</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Inmueble</th><th>Inquilino</th><th>Fecha revisión</th>
            <th style="text-align:center">Año</th>
            <th style="text-align:center">Días restantes</th>
          </tr>
        </thead>
        <tbody>${filas}</tbody>
      </table>
    </div>
    ${_dashPagControls(page, totalPages, 'dashRevisionesPage')}
  </div>`;
}

function dashRevisionesPage(n) {
  _dashRevisionesPag = n;
  const el = document.getElementById('revisiones-card-wrap');
  if (el) el.innerHTML = _buildRevisionesCard(n);
}

function renderDashboard() {
  const propietarios = DB.get('propietarios');
  const inmuebles = DB.get('inmuebles');
  const inquilinos = DB.get('inquilinos');
  const contratos = DB.get('contratos');
  const recibos = DB.get('recibos');

  const activos = contratos.filter(c => c.estado === 'activo');
  const pendientes = recibos.filter(r => r.estado === 'pendiente');
  const totalPendiente = pendientes.reduce((s, r) => s + (r.importe_total||0), 0);
  const cobradoMes = recibos.filter(r => {
    if (r.estado !== 'cobrado') return false;
    const d = new Date(r.fecha_cobro||r.fecha_emision);
    const now = new Date();
    return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
  }).reduce((s, r) => s + (r.importe_total||0), 0);

  const recentRecibos = [...recibos].sort((a,b) => b.id - a.id).slice(0,5);
  _dashRenovPag = 1;
  _dashRevisionesPag = 1;

  const hoyD = new Date(); hoyD.setHours(0,0,0,0);

  // Calcular la próxima revisión anual de cada contrato activo.
  // La revisión cae el mismo día y mes que el inicio del contrato.
  // Si ya pasó este año, se proyecta al año siguiente.
  const todasRevisiones = activos
    .filter(c => c.fecha_inicio)
    .map(c => {
      const inicio = new Date(c.fecha_inicio + 'T00:00:00');
      let aniversario = new Date(hoyD.getFullYear(), inicio.getMonth(), inicio.getDate());
      if (aniversario.getTime() < hoyD.getTime()) {
        aniversario = new Date(hoyD.getFullYear() + 1, inicio.getMonth(), inicio.getDate());
      }
      const dias = Math.round((aniversario - hoyD) / 86400000);
      return {
        contrato   : c,
        inmueble   : inmuebles.find(i => i.id === c.inmueble_id) || null,
        inquilino  : inquilinos.find(i => i.id === c.inquilino_id) || null,
        fechaRevision: aniversario,
        anosContrato: aniversario.getFullYear() - inicio.getFullYear(),
        dias
      };
    })
    .sort((a, b) => a.dias - b.dias);

  // Contratos cuya fecha_fin cae en los próximos 6 meses (o ya vencidos).
  // Dias negativos = contrato ya vencido. Se ordenan de más urgente a más lejano.
  const proximasRenovaciones = activos
    .filter(c => c.fecha_fin)
    .map(c => {
      const fin  = new Date(c.fecha_fin + 'T00:00:00');
      const dias = Math.round((fin - hoyD) / 86400000);
      return {
        contrato : c,
        inmueble : inmuebles.find(i => i.id === c.inmueble_id) || null,
        inquilino: inquilinos.find(i => i.id === c.inquilino_id) || null,
        fechaFin : fin,
        dias
      };
    })
    .filter(r => r.dias <= 180)          // vencidos o próximos 6 meses
    .sort((a, b) => a.dias - b.dias);   // más urgentes primero
  _dashRenovaciones = proximasRenovaciones;
  _dashRevisiones   = todasRevisiones;

  // Visibilidad de widgets del Dashboard (configurables desde Parámetros → Dashboard)
  const _dv = {
    kpis            : _cfgGet('dash_kpis',              '1') !== '0',
    alertaIpc       : _cfgGet('dash_alerta_ipc',         '1') !== '0',
    alertaBkp       : _cfgGet('dash_alerta_backup',      '1') !== '0',
    avisos          : _cfgGet('dash_avisos_revision',    '1') !== '0',
    renov           : _cfgGet('dash_renovaciones',       '1') !== '0',
    revisiones      : _cfgGet('dash_revisiones',         '1') !== '0',
    recibos         : _cfgGet('dash_ultimos_recibos',    '1') !== '0',
    cobrado         : _cfgGet('dash_cobrado_mes',        '1') !== '0',
    graficos        : _cfgGet('dash_graficos',           '1') !== '0',
    cobrosEsperados : _cfgGet('dash_cobros_esperados',   '1') !== '0',
    logActividad    : _cfgGet('dash_log_actividad',      '1') !== '0',
  };

  // Alerta de backup desactualizado (item 06)
  const _ultimoBackup = localStorage.getItem('ag_ultimo_backup');
  const _diasLimiteBackup = Math.max(1, parseInt(_cfgGet('dash_backup_dias', '7')) || 7);
  let alertaBackup = '';
  if (!_ultimoBackup) {
    alertaBackup = `<div style="background:#eff6ff;border:1px solid #93c5fd;border-radius:var(--radius);padding:10px 16px;margin-bottom:12px;display:flex;align-items:center;gap:12px;font-size:13px">
      <svg width="18" height="18" fill="none" stroke="#1a56db" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      <div><strong>No hay ningún backup registrado.</strong> Se recomienda descargar una copia de seguridad periódicamente.
        <button class="btn btn-sm btn-secondary" style="margin-left:10px" onclick="descargarBackup()">Descargar backup</button>
      </div>
    </div>`;
  } else {
    const _diasBackup = Math.floor((new Date() - new Date(_ultimoBackup + 'T00:00:00')) / 86400000);
    if (_diasBackup > _diasLimiteBackup) {
      alertaBackup = `<div style="background:#eff6ff;border:1px solid #93c5fd;border-radius:var(--radius);padding:10px 16px;margin-bottom:12px;display:flex;align-items:center;gap:12px;font-size:13px">
        <svg width="18" height="18" fill="none" stroke="#1a56db" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        <div>Último backup: hace <strong>${_diasBackup} días</strong>. Se recomienda hacer backup al menos semanalmente.
          <button class="btn btn-sm btn-secondary" style="margin-left:10px" onclick="descargarBackup()">Descargar ahora</button>
        </div>
      </div>`;
    }
  }

  // Alerta de revisión anual pendiente IPC/IRAV (M-L02)
  const ipcPend = contratosIPCPendientes();
  const _tiposRev = [...new Set(ipcPend.map(c => c.revision))].join('/');
  const alertaIPC = ipcPend.length > 0
    ? `<div style="background:var(--orange-light);border:1px solid var(--orange);border-radius:var(--radius);padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:12px">
        <svg width="20" height="20" fill="none" stroke="var(--orange)" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <div>
          <strong style="color:var(--orange)">Revisión ${_tiposRev} pendiente</strong>
          <span style="color:var(--orange);margin-left:6px;font-size:13px">${ipcPend.length} contrato${ipcPend.length > 1 ? 's' : ''} con revisión ${_tiposRev} correspondiente a este mes</span>
          <button class="btn btn-sm" style="margin-left:12px;background:var(--orange);color:white;border:none" onclick="navigate('contratos',{filtroIPC:true})">Ver contratos</button>
        </div>
      </div>`
    : '';

  // Datos para gráfico de ingresos mensuales (últimos 6 meses)
  const mesesLabels = [];
  const mesesIngresos = [];
  for (let i = 5; i >= 0; i--) {
    const d = new Date();
    d.setMonth(d.getMonth() - i);
    const mes = d.getMonth(), anio = d.getFullYear();
    mesesLabels.push(d.toLocaleDateString('es-ES', { month: 'short', year: '2-digit' }));
    const ingreso = recibos.filter(r =>
      r.estado === ESTADO.COBRADO &&
      new Date(r.fecha_emision).getMonth() === mes &&
      new Date(r.fecha_emision).getFullYear() === anio
    ).reduce((s, r) => s + (r.importe_total || 0), 0);
    mesesIngresos.push(Math.round(ingreso * 100) / 100);
  }

  // Datos para gráfico de ocupación
  const totalInm     = inmuebles.length;
  const ocupados     = activos.length;
  const desocupados  = Math.max(0, totalInm - ocupados);

  // ── Previsión de cobros del mes actual (widget dash_cobros_esperados) ──
  const _mesActual  = new Date().getMonth() + 1;
  const _anyoActual = new Date().getFullYear();
  const _mesesNom   = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
  const _periodoAct = _mesesNom[_mesActual - 1] + ' ' + _anyoActual;
  // Recibos del período actual (emitidos este mes)
  const _recibosEsteMs = recibos.filter(r => r.concepto_periodo === _periodoAct);
  const _totalEsperado  = _recibosEsteMs.reduce((s, r) => s + (r.importe_total || 0), 0);
  const _totalCobrado   = _recibosEsteMs.filter(r => r.estado === ESTADO.COBRADO || r.estado === 'parcial')
                            .reduce((s, r) => s + (r.importe_pagado || 0), 0);
  const _totalPendMs    = _recibosEsteMs.filter(r => r.estado !== ESTADO.COBRADO)
                            .reduce((s, r) => s + ((r.importe_total || 0) - (r.importe_pagado || 0)), 0);
  const _pctCobrado     = _totalEsperado > 0 ? Math.round((_totalCobrado / _totalEsperado) * 100) : 0;
  const _cobrosEsperadosHtml = !_dv.cobrosEsperados ? '' : `
    <div class="card" style="margin-bottom:16px">
      <div class="card-header">
        <div class="card-title">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:6px;vertical-align:-2px"><rect x="2" y="7" width="20" height="14" rx="2"/><polyline points="16 3 12 7 8 3"/></svg>
          Previsión de cobros — ${_periodoAct}
        </div>
      </div>
      <div class="card-body" style="display:flex;flex-wrap:wrap;gap:24px;align-items:center">
        <div style="flex:1;min-width:180px">
          <div style="font-size:12px;color:var(--gray-500);margin-bottom:4px">Total esperado</div>
          <div style="font-size:22px;font-weight:700;color:var(--gray-800)">${fmtMoney(_totalEsperado)}</div>
          <div style="font-size:12px;color:var(--gray-400)">${_recibosEsteMs.length} recibo${_recibosEsteMs.length !== 1 ? 's' : ''}</div>
        </div>
        <div style="flex:1;min-width:140px">
          <div style="font-size:12px;color:var(--gray-500);margin-bottom:4px">Cobrado</div>
          <div style="font-size:20px;font-weight:600;color:var(--green)">${fmtMoney(_totalCobrado)}</div>
        </div>
        <div style="flex:1;min-width:140px">
          <div style="font-size:12px;color:var(--gray-500);margin-bottom:4px">Pendiente</div>
          <div style="font-size:20px;font-weight:600;color:var(--orange)">${fmtMoney(_totalPendMs)}</div>
        </div>
        <div style="flex:2;min-width:200px">
          <div style="font-size:12px;color:var(--gray-500);margin-bottom:6px">Progreso de cobros</div>
          <div style="height:12px;background:var(--gray-200);border-radius:6px;overflow:hidden">
            <div style="height:100%;background:var(--green);border-radius:6px;width:${_pctCobrado}%;transition:width .4s"></div>
          </div>
          <div style="text-align:right;font-size:12px;color:var(--gray-500);margin-top:4px">${_pctCobrado}% cobrado</div>
        </div>
      </div>
    </div>`;

  document.getElementById('content').innerHTML = `
    ${_dv.alertaBkp ? alertaBackup : ''}
    ${_dv.alertaIpc ? alertaIPC : ''}
    ${_dv.kpis ? `<div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--blue-light)">
          <svg width="24" height="24" fill="none" stroke-width="2" style="stroke:var(--blue)" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
        </div>
        <div><div class="stat-value">${propietarios.length}</div><div class="stat-label">Propietarios</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--green-light)">
          <svg width="24" height="24" fill="none" stroke-width="2" style="stroke:var(--green)" viewBox="0 0 24 24"><path d="M3 22V9l9-7 9 7v13"/><rect x="9" y="14" width="6" height="8"/></svg>
        </div>
        <div><div class="stat-value">${inmuebles.length}</div><div class="stat-label">Inmuebles</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--orange-light)">
          <svg width="24" height="24" fill="none" stroke-width="2" style="stroke:var(--orange)" viewBox="0 0 24 24"><circle cx="9" cy="7" r="4"/><path d="M3 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/></svg>
        </div>
        <div><div class="stat-value">${activos.length}</div><div class="stat-label">Contratos activos</div></div>
      </div>
      <div class="stat-card">
        <div class="stat-icon" style="background:var(--red-light)">
          <svg width="24" height="24" fill="none" stroke-width="2" style="stroke:var(--red)" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div><div class="stat-value">${fmtMoney(totalPendiente)}</div><div class="stat-label">Pendiente de cobro</div></div>
      </div>
    </div>` : ''}
    ${_cobrosEsperadosHtml}
    ${_dv.avisos ? (() => {
      const av = getAvisosRevision();
      if (!av.length) return '';
      const filas = av.map(a => {
        const dias = a.diasHasta === 0
          ? `<span style="color:var(--red);font-weight:700">HOY</span>`
          : `<span style="color:var(--orange);font-weight:600">${a.diasHasta} día${a.diasHasta !== 1 ? 's' : ''}</span>`;
        const fecha = a.fechaAniversario.toLocaleDateString('es-ES', {day:'2-digit', month:'long', year:'numeric'});
        return `<tr>
          <td><strong>${a.inmueble ? esc(getInmuebleNombre(a.inmueble)) : '—'}</strong></td>
          <td>${a.inquilino ? esc(a.inquilino.nombre) : '—'}</td>
          <td>${fecha}</td>
          <td style="text-align:center">${a.anosContrato}º</td>
          <td style="text-align:center">${dias}</td>
        </tr>`;
      }).join('');
      return `<div class="aviso-revision-card">
        <div class="aviso-revision-header">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          Revisiones anuales próximas — ${av.length} aviso${av.length !== 1 ? 's' : ''}
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Inmueble</th><th>Inquilino</th><th>Fecha revisión</th><th style="text-align:center">Año</th><th style="text-align:center">Días</th></tr></thead>
            <tbody>${filas}</tbody>
          </table>
        </div>
      </div>`;
    })() : ''}
    ${_dv.renov && proximasRenovaciones.length ? `<div id="renov-card-wrap">${_buildRenovCard(1)}</div>` : ''}
    ${_dv.revisiones && todasRevisiones.length ? `<div id="revisiones-card-wrap" style="margin-top:20px">${_buildRevisionesCard(1)}</div>` : ''}
    ${_dv.recibos || _dv.cobrado ? `<div class="dashboard-recent">
      ${_dv.recibos ? `<div class="card">
        <div class="card-header">
          <div class="card-title">Últimos recibos</div>
          <button class="btn btn-sm btn-secondary" onclick="navigate('recibos')">Ver todos</button>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Nº Recibo</th><th>Inquilino</th><th>Importe</th><th>Estado</th></tr></thead>
            <tbody>
              ${recentRecibos.length ? recentRecibos.map(r => {
                const inq = DB.getItem('inquilinos', r.inquilino_id);
                return `<tr class="tr-${r.estado||'pendiente'}">
                  <td><strong>${r.numero_recibo}</strong></td>
                  <td>${inq ? inq.nombre : '-'}</td>
                  <td>${fmtMoney(r.importe_total)}</td>
                  <td>${badgeEstadoRecibo(r.estado)}</td>
                </tr>`;
              }).join('') : '<tr><td colspan="4" style="text-align:center;color:var(--gray-400);padding:20px">Sin recibos</td></tr>'}
            </tbody>
          </table>
        </div>
      </div>` : ''}
      ${_dv.cobrado ? `<div class="card">
        <div class="card-header">
          <div class="card-title">Cobrado este mes</div>
        </div>
        <div class="card-body" style="text-align:center;padding:40px 20px">
          <div style="font-size:36px;font-weight:700;color:var(--green)">${fmtMoney(cobradoMes)}</div>
          <div style="color:var(--gray-500);margin-top:6px">${new Date().toLocaleDateString('es-ES',{month:'long',year:'numeric'})}</div>
          <div style="margin-top:24px;display:flex;justify-content:center;gap:24px">
            <div style="text-align:center">
              <div style="font-size:20px;font-weight:700;color:var(--orange)">${pendientes.length}</div>
              <div style="font-size:12px;color:var(--gray-500)">Recibos pendientes</div>
            </div>
            <div style="text-align:center">
              <div style="font-size:20px;font-weight:700;color:var(--blue)">${recibos.filter(r=>r.estado==='cobrado').length}</div>
              <div style="font-size:12px;color:var(--gray-500)">Total cobrados</div>
            </div>
          </div>
        </div>
      </div>` : ''}
    </div>` : ''}
    ${_dv.graficos ? `<!-- Gráficos de ingresos y ocupación -->
    <div class="charts-grid" style="margin-top:20px">
      <div class="card chart-wrap">
        <div class="card-header"><div class="card-title">Ingresos últimos 6 meses</div></div>
        <canvas id="chart-ingresos" height="160"></canvas>
      </div>
      <div class="card chart-wrap">
        <div class="card-header"><div class="card-title">Ocupación de inmuebles</div></div>
        <canvas id="chart-ocupacion" height="160"></canvas>
      </div>
    </div>` : ''}
    ${_dv.logActividad ? `<div id="dash-log-wrap" style="margin-top:20px">
      <div class="card">
        <div class="card-header">
          <div class="card-title">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:6px;vertical-align:-2px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            Últimas actividades
          </div>
          <button class="btn btn-sm btn-secondary" onclick="navigate('actividad')">Ver todo</button>
        </div>
        <div id="dash-log-body" style="padding:12px 16px;color:var(--gray-400);font-size:13px">Cargando...</div>
      </div>
    </div>` : ''}
  `;

  // Inicializar los gráficos solo si el widget está habilitado y el DOM existe
  if (_dv.graficos) {
    initDashboardCharts(mesesLabels, mesesIngresos, ocupados, desocupados);
  }

  // Widget de log de actividad: fetch async para no bloquear el render inicial
  if (_dv.logActividad) {
    fetch('assets/php/api.php?action=getLog&limite=5')
      .then(function (r) { return r.json(); })
      .then(function (filas) { _dashRenderLog(filas); })
      .catch(function () {
        const el = document.getElementById('dash-log-body');
        if (el) el.innerHTML = '<span style="color:var(--gray-400)">No disponible (comprueba que el log está activado en config.php).</span>';
      });
  }
}

// Actualiza el contenido del widget de log en el Dashboard con las últimas filas
function _dashRenderLog(filas) {
  const el = document.getElementById('dash-log-body');
  if (!el) return;
  if (!filas || !filas.length) {
    el.innerHTML = '<span style="color:var(--gray-400)">Sin actividad registrada todavía.</span>';
    return;
  }
  const iconos = {
    'cobro': 'bi-cash-coin text-success',
    'anulacion_pago': 'bi-x-circle text-warning',
    'anulacion_recibo': 'bi-slash-circle text-danger',
    'generacion_lote': 'bi-collection text-primary',
    'factura_generada': 'bi-receipt text-info',
    'email_enviado': 'bi-envelope text-primary',
    'baja_contrato': 'bi-file-earmark-x text-danger',
    'subida_ipc': 'bi-graph-up-arrow text-warning',
  };
  const labels = {
    'cobro': 'Cobro', 'anulacion_pago': 'Pago anulado', 'anulacion_recibo': 'Recibo anulado',
    'generacion_lote': 'Lote generado', 'factura_generada': 'Factura', 'email_enviado': 'Email',
    'baja_contrato': 'Baja contrato', 'subida_ipc': 'Subida IPC',
  };
  const html = filas.map(function (f) {
    const ico   = iconos[f.tipo_accion] || 'bi-info-circle';
    const lbl   = labels[f.tipo_accion] || f.tipo_accion;
    const fecha = (f.fecha || '').replace('T', ' ').slice(0, 16);
    return '<div style="display:flex;align-items:baseline;gap:10px;padding:5px 0;border-bottom:1px solid var(--gray-100)">' +
      '<i class="bi ' + ico + '" style="width:16px;flex-shrink:0"></i>' +
      '<span style="font-weight:600;font-size:13px;min-width:110px">' + lbl + '</span>' +
      '<span style="color:var(--gray-700);font-size:13px;flex:1">' + esc(f.descripcion || '') + '</span>' +
      '<span style="color:var(--gray-400);font-size:11px;white-space:nowrap">' + fecha + '</span>' +
      '</div>';
  }).join('');
  el.innerHTML = '<div style="padding:4px 0">' + html + '</div>';
}

// Crea los dos gráficos del Dashboard con Chart.js.
// Se llama después de renderDashboard() para que los canvas existan en el DOM.
function initDashboardCharts(mesesLabels, mesesIngresos, ocupados, desocupados) {
  if (!window.Chart) return;
  const dark = document.body.classList.contains('dark');
  const colAzul       = dark ? '#4d8ffa' : '#1a56db';
  const colAzulFondo  = dark ? 'rgba(77,143,250,0.15)' : 'rgba(26,86,219,0.15)';
  const colGrilla     = dark ? '#334155' : '#e5e7eb';
  const colTexto      = dark ? '#94a3b8' : '#6b7280';
  const colVerde      = dark ? '#22c55e' : '#057a55';
  const colVerdeFondo = dark ? 'rgba(34,197,94,0.5)' : 'rgba(5,122,85,0.7)';
  const colGrisFondo  = dark ? 'rgba(51,65,85,0.9)' : 'rgba(229,231,235,0.9)';
  const colGrisBorde  = dark ? '#475569' : '#d1d5db';

  const baseTickOpts  = { color: colTexto, font: { size: 11 } };

  // Gráfico de barras: ingresos cobrados por mes
  const ctxBar = document.getElementById('chart-ingresos');
  if (ctxBar) {
    new Chart(ctxBar, {
      type: 'bar',
      data: {
        labels: mesesLabels,
        datasets: [{
          label: 'Cobrado (€)',
          data: mesesIngresos,
          backgroundColor: colAzulFondo,
          borderColor: colAzul,
          borderWidth: 2,
          borderRadius: 6,
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          y: {
            beginAtZero: true,
            grid: { color: colGrilla },
            ticks: Object.assign({ callback: function(v) { return v.toLocaleString('es-ES') + ' €'; } }, baseTickOpts)
          },
          x: { grid: { display: false }, ticks: baseTickOpts }
        }
      }
    });
  }

  // Gráfico de dona: porcentaje de inmuebles ocupados
  const ctxDona = document.getElementById('chart-ocupacion');
  if (ctxDona) {
    new Chart(ctxDona, {
      type: 'doughnut',
      data: {
        labels: ['Ocupados', 'Desocupados'],
        datasets: [{
          data: [ocupados, desocupados],
          backgroundColor: [colVerdeFondo, colGrisFondo],
          borderColor: [colVerde, colGrisBorde],
          borderWidth: 2,
        }]
      },
      options: {
        responsive: true,
        cutout: '65%',
        plugins: {
          legend: {
            position: 'bottom',
            labels: { font: { size: 12 }, color: colTexto }
          }
        }
      }
    });
  }
}

function badgeEstadoRecibo(estado) {
  const map    = { pendiente:'badge-orange', cobrado:'badge-green', anulado:'badge-red', parcial:'badge-blue', devuelto:'badge-orange', rectificativo:'badge-blue' };
  const labels = { pendiente:'Pendiente', cobrado:'Cobrado', anulado:'Anulado', parcial:'Parcial', devuelto:'Devuelto', rectificativo:'Rectificativo' };
  return `<span class="badge ${map[estado]||'badge-blue'}">${labels[estado]||estado}</span>`;
}
