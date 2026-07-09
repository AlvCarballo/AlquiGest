// ===========================
// CONTRATOS DE ALQUILER
// Cada contrato vincula un inquilino con un inmueble y define las condiciones
// económicas (renta, IVA, IRPF, fianza) y temporales (inicio, fin, revisión).
// Solo puede haber un contrato ACTIVO por inmueble al mismo tiempo.
// Desde la lista se puede generar un recibo individual o dar de baja el contrato.
// ===========================
let _contratosPag = 1;
let _contFiltroIPC = false;

// ============================================================
// Acciones de fila de Contratos: acción principal + menú "Más" agrupado.
// Propuesta UX 08/07/2026, ver UX_UI_ANALISIS_PROPUESTA.md §3.
// El aviso ⚠IPC se mantiene siempre visible fuera de este componente
// (es una alerta temporal, no una acción rutinaria más).
// ============================================================
function _accionesContrato(c, esActivo) {
  const grupos = [
    { titulo:'Contrato', items:[
      { label:'Renovar', icon:'🔄', onclick:`modalRenovarContrato(${c.id})`, oculto: !_cfgVisi('VisiRenovarCont') || !esActivo },
      { label:'Historial de rentas', icon:'🕓', onclick:`modalHistorialRentas(${c.id})`, oculto: !_cfgVisi('VisiHistorialCont') || !esActivo },
      { label:'Dar de baja', icon:'⛔', danger:true, onclick:`modalBajaContrato(${c.id})`, oculto: !_cfgVisi('VisiBajaCont') || !esActivo },
    ]},
    { titulo:'Documentos', items:[
      { label:'PDF del contrato', icon:'🖶', onclick:`pdfContrato(${c.id})`, oculto: !_cfgVisi('VisiPDFCont') || !esActivo },
      { label:'Justificante de fianza', icon:'🖶', onclick:`pdfFianza(${c.id})`, oculto: !_cfgVisi('VisiFianzaCont') || !(parseFloat(c.fianza) > 0) },
      { label:'Contrato en DOCX', icon:'📄', onclick:`generarDocumentoDesdePlantilla('contrato',${c.id},0)`, oculto: !_cfgVisi('VisiDocxCont') },
    ]},
    { titulo:'Gestión', items:[
      { label:'Editar', icon:'✎', onclick:`modalContrato(${c.id})` },
    ]},
  ];
  const principal = esActivo
    ? (_cfgVisi('VisiGenerarReciboCont') ? { label:'Generar recibo', cls:'btn-success', onclick:`modalGenerarRecibo(${c.id})` } : null)
    : (_cfgVisi('VisiPDFCont') ? { label:'Ver PDF', cls:'btn-secondary', onclick:`pdfContrato(${c.id})` } : null);
  return accionesFila(principal, grupos);
}

function renderContratos() {
  // Si venimos del dashboard con filtroIPC, activar el filtro
  if (navParams && navParams.filtroIPC) {
    _contFiltroIPC = true;
    navParams.filtroIPC = false;
  }

  const inquilinos = DB.get('inquilinos');
  const inmuebles  = DB.get('inmuebles');
  const inqMap = new Map(inquilinos.map(i => [i.id, i]));
  const inmMap = new Map(inmuebles.map(i => [i.id, i]));
  const ESTADO_ORD = {activo:0, finalizado:1, rescindido:2};
  const estadoFiltro = document.getElementById('filtro-contrato-estado')?.value || '';

  // IDs de contratos con IPC pendiente (para botón y filtro)
  const ipcPendIds = new Set(contratosIPCPendientes().map(c => c.id));

  let items = [...DB.get('contratos')].sort((a, b) => {
    const ea = ESTADO_ORD[a.estado] ?? 3, eb = ESTADO_ORD[b.estado] ?? 3;
    if (ea !== eb) return ea - eb;
    const inma = inmMap.get(a.inmueble_id);
    const inmb = inmMap.get(b.inmueble_id);
    const cmp = (inma ? getInmuebleNombre(inma) : '').localeCompare(inmb ? getInmuebleNombre(inmb) : '', 'es', {sensitivity:'base'});
    if (cmp !== 0) return cmp;
    const inqa = inqMap.get(a.inquilino_id);
    const inqb = inqMap.get(b.inquilino_id);
    return (inqa?.nombre||'').localeCompare(inqb?.nombre||'', 'es', {sensitivity:'base'});
  });
  if (estadoFiltro) items = items.filter(c => c.estado === estadoFiltro);
  if (_contFiltroIPC) items = items.filter(c => ipcPendIds.has(c.id));
  const _CONT_PP = Math.max(5, parseInt(_cfgGet('filas_contratos', '20')) || 20);
  const _contTotPag = Math.max(1, Math.ceil(items.length / _CONT_PP));
  _contratosPag = Math.max(1, Math.min(_contratosPag, _contTotPag));
  const contratosPag = items.slice((_contratosPag - 1) * _CONT_PP, _contratosPag * _CONT_PP);

  document.getElementById('header-actions').innerHTML = `
    <button class="btn btn-primary" onclick="modalContrato()">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nuevo contrato
    </button>`;

  document.getElementById('content').innerHTML = `
    <div class="card">
      <div class="card-header">
        <div class="card-title">Contratos (${items.length})</div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <select id="filtro-contrato-estado" onchange="_contratosPag=1;renderContratos()" style="width:140px;padding:6px 8px">
            <option value="" ${!estadoFiltro?'selected':''}>Todos</option>
            <option value="activo" ${estadoFiltro==='activo'?'selected':''}>Activos</option>
            <option value="finalizado" ${estadoFiltro==='finalizado'?'selected':''}>Finalizados</option>
            <option value="rescindido" ${estadoFiltro==='rescindido'?'selected':''}>Rescindidos</option>
          </select>
          ${ipcPendIds.size > 0 ? `
          <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;margin:0;font-weight:400;
                        background:${_contFiltroIPC?'var(--orange-light)':'transparent'};
                        border:1px solid ${_contFiltroIPC?'var(--color-warn-muted)':'var(--gray-300)'};
                        padding:5px 10px;border-radius:6px;white-space:nowrap">
            <input type="checkbox" ${_contFiltroIPC?'checked':''}
                   onchange="_contFiltroIPC=this.checked;_contratosPag=1;renderContratos()"
                   style="width:auto;margin:0;accent-color:var(--orange)">
            <span style="color:var(--orange);font-weight:600">⚠ Revisión pendiente (${ipcPendIds.size})</span>
          </label>` : ''}
          <div class="search-bar" style="width:220px">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" placeholder="Buscar..." oninput="filterTable(this,'tbl-contratos',[0,1])">
          </div>
        </div>
      </div>
      <div class="table-wrap">
        <table id="tbl-contratos">
          <thead><tr><th>Inquilino</th><th>Inmueble</th><th>Inicio</th><th>Baja / Fin</th><th>Renta</th><th>Estado</th><th></th></tr></thead>
          <tbody>
            ${items.length ? contratosPag.map(c => {
              const inq = inqMap.get(c.inquilino_id);
              const inm = inmMap.get(c.inmueble_id);
              const esActivo = c.estado === 'activo';
              const _anioAct = new Date().getFullYear();
              const badgeRevision = TIPOS_REVISION_INE.includes(c.revision)
                ? (parseInt(c.ipc_anio_aplicado) === _anioAct
                    ? `<br><span style="font-size:10px;background:var(--green-light);color:var(--green);border:1px solid var(--green);border-radius:3px;padding:1px 4px">${c.revision} ✓ ${_anioAct}</span>`
                    : (ipcPendIds.has(c.id)
                        ? `<br><span style="font-size:10px;background:var(--orange-light);color:var(--orange);border:1px solid var(--color-warn-muted);border-radius:3px;padding:1px 4px">${c.revision} ⚠</span>`
                        : ''))
                : '';
              return `<tr>
                <td><strong>${esc(inq ? inq.nombre : '-')}</strong></td>
                <td>${esc(inm ? getInmuebleNombre(inm) : '-')}</td>
                <td>${fmtDate(c.fecha_inicio)}</td>
                <td>${c.fecha_baja ? `<span style="color:var(--red)">${fmtDate(c.fecha_baja)}<br><small>${esc(c.motivo_baja||'')}</small></span>` : (fmtDate(c.fecha_fin)||'Indefinido')}</td>
                <td><strong>${fmtMoney(c.renta_base)}</strong>${badgeRevision}</td>
                <td>${badgeEstadoContrato(c.estado)}</td>
                <td class="td-actions">
                  <!-- Botón sólido con ámbar fijo (no var(--orange)) a propósito: en modo
                       oscuro --orange es un amarillo muy claro (#fbbf24) que da muy poco
                       contraste con el texto blanco del botón. Al ser color sólido con texto
                       blanco ya se lee bien en ambos temas tal cual, sin necesitar variable. -->
                  ${ipcPendIds.has(c.id) ? `<button class="btn btn-sm" style="background:#f59e0b;color:#fff;border-color:#d97706;font-size:11px;font-weight:700"
                          title="Aplicar revisión ${c.revision} anual" onclick="modalAplicarIPC(${c.id})">⚠ ${c.revision}</button>` : ''}
                  ${_accionesContrato(c, esActivo)}
                </td>
              </tr>`;
            }).join('') : `<tr><td colspan="7"><div class="empty-state">
              <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
              <p>Sin contratos</p><small>Pulsa "Nuevo contrato" para comenzar</small></div></td></tr>`}
          </tbody>
        </table>
      </div>
      ${_contTotPag > 1 ? `
        <div class="table-pagination">
          <button class="btn btn-sm btn-secondary" onclick="_contratosPag=${_contratosPag-1};renderContratos()" ${_contratosPag<=1?'disabled':''}>‹ Ant.</button>
          <span>Página ${_contratosPag} de ${_contTotPag} · ${items.length} contratos</span>
          <button class="btn btn-sm btn-secondary" onclick="_contratosPag=${_contratosPag+1};renderContratos()" ${_contratosPag>=_contTotPag?'disabled':''}>Sig. ›</button>
        </div>` : ''}
    </div>`;
  makeTableSortable('tbl-contratos', {col:5, dir:1});
}

function badgeEstadoContrato(estado) {
  const map = { activo:'badge-green', finalizado:'badge-orange', rescindido:'badge-red' };
  const labels = { activo:'Activo', finalizado:'Finalizado', rescindido:'Rescindido' };
  return `<span class="badge ${map[estado]||'badge-blue'}">${labels[estado]||estado}</span>`;
}

function modalContrato(id=null) {
  const c = id ? DB.getItem('contratos', id) : {};
  const inquilinos = DB.get('inquilinos');
  const inmuebles = DB.get('inmuebles');

  // Determinar si mostrar el campo motivo_temporada al abrir el modal
  // Se muestra si hay fechas definidas y la duración es menor a 1 año,
  // o si ya tiene un valor guardado en BD.
  const mostrarTemporada = c.motivo_temporada
    || _esDuracionMenorUnAnio(c.fecha_inicio, c.fecha_fin);

  openModal(id ? 'Editar contrato' : 'Nuevo contrato de alquiler', `
    <form id="form-contrato" class="form-grid form-grid-2">
      <div class="form-group">
        <label>Inquilino *</label>
        <select name="inquilino_id" required>
          <option value="">-- Seleccionar --</option>
          ${inquilinos.map(i => `<option value="${i.id}" ${c.inquilino_id===i.id?'selected':''}>${i.nombre}</option>`).join('')}
        </select>
      </div>
      <div class="form-group">
        <label>Inmueble *</label>
        <select name="inmueble_id" required>
          <option value="">-- Seleccionar --</option>
          ${inmuebles.map(i => `<option value="${i.id}" ${c.inmueble_id===i.id?'selected':''}>${getInmuebleNombre(i)}</option>`).join('')}
        </select>
      </div>
      <div class="form-group">
        <label>Fecha inicio *</label>
        <input name="fecha_inicio" id="cont-fecha-inicio" type="date" value="${c.fecha_inicio||''}" required
               oninput="_actualizarCampoTemporada()">
      </div>
      <div class="form-group">
        <label>Fecha fin</label>
        <input name="fecha_fin" id="cont-fecha-fin" type="date" value="${c.fecha_fin||''}"
               oninput="_actualizarCampoTemporada()">
      </div>
      <div class="form-group">
        <label>Estado</label>
        <select name="estado">
          <option value="activo" ${c.estado==='activo'||!c.estado?'selected':''}>Activo</option>
          <option value="finalizado" ${c.estado==='finalizado'?'selected':''}>Finalizado</option>
          <option value="rescindido" ${c.estado==='rescindido'?'selected':''}>Rescindido</option>
        </select>
      </div>
      <div class="form-group">
        <label>Duración</label>
        <div style="display:flex;gap:8px">
          <input name="duracion_anos" type="number" min="1" value="${c.duracion_anos||''}" placeholder="1" style="flex:1">
          <select name="duracion_unidad" style="width:110px">
            <option value="anos" ${(c.duracion_unidad||'anos')==='anos'?'selected':''}>años</option>
            <option value="meses" ${c.duracion_unidad==='meses'?'selected':''}>meses</option>
            <option value="dias" ${c.duracion_unidad==='dias'?'selected':''}>días</option>
          </select>
        </div>
      </div>

      <!-- Motivo de temporada: visible solo cuando la duración es < 1 año -->
      <div class="form-group" id="bloque-motivo-temporada" style="grid-column:1/-1;${mostrarTemporada ? '' : 'display:none'}">
        <label>Motivo de temporada
          <span style="font-size:11px;color:var(--gray-400);font-weight:normal;margin-left:4px">(variable {{MotivoTemporada}} en plantillas)</span>
        </label>
        <input name="motivo_temporada" type="text"
               value="${esc(c.motivo_temporada||'')}"
               placeholder="Ej: Estudios universitarios 2024-2025">
        <div style="font-size:11px;color:var(--gray-400);margin-top:4px">
          Requerido en contratos de temporada (duración inferior a 1 año) según la Ley de Arrendamientos Urbanos.
        </div>
      </div>

      <div class="form-group" style="grid-column:1/-1">
        <div class="form-section-title" style="margin-top:4px">Condiciones económicas</div>
      </div>
      <div class="form-group">
        <label>Renta base mensual (€) *</label>
        <input name="renta_base" type="number" step="0.01" min="0" value="${c.renta_base||''}" required placeholder="800.00">
      </div>
      <div class="form-group">
        <label>IVA (%)</label>
        <input name="iva_pct" type="number" step="0.01" min="0" max="100" value="${c.iva_pct||0}" placeholder="0">
      </div>
      <div class="form-group">
        <label>IRPF (%)</label>
        <input name="irpf_pct" type="number" step="0.01" min="0" max="100" value="${c.irpf_pct||0}" placeholder="0">
      </div>
      <div class="form-group">
        <label>Fianza (€)</label>
        <input name="fianza" type="number" step="0.01" min="0" value="${c.fianza||''}" placeholder="800.00">
      </div>
      <div class="form-group">
        <label>Revisión anual</label>
        <select name="revision">
          <option value="IPC" ${c.revision==='IPC'?'selected':''}>IPC (INE)</option>
          <option value="IRAV" ${c.revision==='IRAV'?'selected':''}>IRAV (INE)</option>
          <option value="Fija" ${c.revision==='Fija'?'selected':''}>Subida fija %</option>
          <option value="Sin revision" ${c.revision==='Sin revision'?'selected':''}>Sin revisión</option>
        </select>
      </div>
      <div class="form-group">
        <label>Día de pago</label>
        <input name="dia_pago" type="number" min="1" max="31" value="${c.dia_pago||5}" placeholder="5">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>Observaciones / Cláusulas adicionales</label>
        <textarea name="observaciones">${c.observaciones||''}</textarea>
      </div>

      <!-- Inquilinos secundarios (firmantes adicionales, sin impacto en negocio) -->
      <div class="form-group" style="grid-column:1/-1">
        <div class="form-section-title" style="margin-top:4px">Inquilinos secundarios
          <span style="font-size:11px;color:var(--gray-400);font-weight:normal;margin-left:6px">
            (firmantes adicionales — bloque <code>{{#INQUILINOS_SECUNDARIOS}}</code> en plantillas)
          </span>
        </div>
      </div>
      <div class="form-group" style="grid-column:1/-1" id="bloque-inq-sec">
        ${id
          ? '<div style="color:var(--gray-400);font-size:12px">Cargando…</div>'
          : '<div style="color:var(--gray-400);font-size:12px;padding:8px 0">Guarda el contrato primero para añadir inquilinos secundarios.</div>'
        }
      </div>

      <!-- Fiador solidario (opcional) -->
      <div class="form-group" style="grid-column:1/-1">
        <div class="form-section-title" style="margin-top:4px">Fiador solidario
          <span style="font-size:11px;color:var(--gray-400);font-weight:normal;margin-left:6px">
            (opcional — variables {{NombreFiador}}, {{NIFFiador}}, {{DireccionFiador}} en plantillas)
          </span>
        </div>
      </div>
      <div class="form-group">
        <label>Nombre del fiador</label>
        <input name="nombre_fiador" type="text" value="${esc(c.nombre_fiador||'')}" placeholder="García Ruiz, José">
      </div>
      <div class="form-group">
        <label>NIF del fiador</label>
        <input name="nif_fiador" type="text" value="${esc(c.nif_fiador||'')}" placeholder="11223344C">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>Dirección del fiador</label>
        <input name="direccion_fiador" type="text" value="${esc(c.direccion_fiador||'')}" placeholder="Calle Ejemplo 3, 28001 Madrid">
      </div>

      <div class="form-group" style="grid-column:1/-1">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:normal">
          <input type="checkbox" name="aviso_recibo" value="1" ${(c.aviso_recibo===undefined||c.aviso_recibo===1||c.aviso_recibo===true)?'checked':''} style="width:auto;margin:0">
          Mostrar aviso en recibo: <em>"Recibo no válido sin el correspondiente justificante bancario"</em>
        </label>
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:normal">
          <input type="checkbox" name="aviso_factura" value="1" ${c.aviso_factura==1?'checked':''} style="width:auto;margin:0">
          Mostrar aviso en factura: <em>"Esta factura no constituye justificante de pago sin el correspondiente justificante bancario"</em>
        </label>
      </div>
    </form>
  `, `
    <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
    <button class="btn btn-primary" onclick="saveContrato(${id||'null'})">Guardar</button>
  `);

  // Cargar inquilinos secundarios una vez que el DOM del modal está listo
  if (id) setTimeout(function() { _inqSecRender(id); }, 80);
}

// Devuelve true si la duración entre dos fechas ISO es menor a 1 año (365 días).
// Usado para mostrar/ocultar el campo de motivo de temporada.
function _esDuracionMenorUnAnio(inicio, fin) {
  if (!inicio || !fin) return false;
  const d1 = new Date(inicio + 'T00:00:00');
  const d2 = new Date(fin   + 'T00:00:00');
  if (isNaN(d1) || isNaN(d2) || d2 <= d1) return false;
  const diasDiferencia = (d2 - d1) / 86400000;
  return diasDiferencia < 365;
}

// Muestra u oculta el bloque "Motivo de temporada" según las fechas del formulario.
// Se llama desde el oninput de los campos de fecha.
function _actualizarCampoTemporada() {
  const inicio = (document.getElementById('cont-fecha-inicio') || {}).value || '';
  const fin    = (document.getElementById('cont-fecha-fin')    || {}).value || '';
  const bloque = document.getElementById('bloque-motivo-temporada');
  if (!bloque) return;
  bloque.style.display = _esDuracionMenorUnAnio(inicio, fin) ? '' : 'none';
}

async function saveContrato(id) {
  const form = document.getElementById('form-contrato');
  if (!form.checkValidity()) { form.reportValidity(); return; }
  const data = Object.fromEntries(new FormData(form));
  data.inquilino_id = parseInt(data.inquilino_id);
  data.inmueble_id  = parseInt(data.inmueble_id);
  if (!(data.inmueble_id > 0))  return toast('Debes seleccionar un inmueble', 'error');
  if (!(data.inquilino_id > 0)) return toast('Debes seleccionar un inquilino', 'error');
  data.renta_base   = parseFloat(data.renta_base);
  data.iva_pct      = parseFloat(data.iva_pct)||0;
  data.irpf_pct     = parseFloat(data.irpf_pct)||0;
  data.fianza       = parseFloat(data.fianza)||0;
  data.dia_pago     = parseInt(data.dia_pago)||5;
  data.aviso_recibo  = data.aviso_recibo  === '1' ? 1 : 0;
  data.aviso_factura = data.aviso_factura === '1' ? 1 : 0;
  if (data.fecha_fin && data.fecha_inicio && data.fecha_fin <= data.fecha_inicio) {
    return toast('La fecha de fin debe ser posterior a la fecha de inicio', 'error');
  }
  // Sólo 1 activo por inmueble
  if (data.estado === 'activo') {
    const activo = DB.get('contratos').find(c =>
      c.inmueble_id === data.inmueble_id && c.estado === 'activo' && c.id !== id);
    if (activo) return toast('Este piso ya tiene un contrato activo. Da de baja el anterior primero.', 'error');
  }
  if (id) data.id = id;
  await DB.save('contratos', data);
  closeModalForce();
  toast(id ? 'Contrato actualizado' : 'Contrato creado');
  renderContratos();
}

// Los contratos no se pueden borrar físicamente (documento con trazabilidad
// legal/contractual): la única vía para cerrarlos es "Dar de baja" (modalBajaContrato),
// que conserva el registro y su histórico de recibos/facturas. El backend
// (assets/php/api.php, acción 'delete') rechaza cualquier intento de DELETE
// sobre la tabla contratos aunque se llame directamente a la API.

// --- Baja de contrato ---
function modalBajaContrato(id) {
  const c   = DB.getItem('contratos', id);
  const inq = DB.getItem('inquilinos', c.inquilino_id);
  const inm = DB.getItem('inmuebles',  c.inmueble_id);
  const hoy = new Date().toISOString().split('T')[0];
  openModal('Dar de baja el contrato', `
    <div class="alert alert-info" style="margin-bottom:16px">
      <strong>${inq?.nombre||'-'}</strong> — ${getInmuebleNombre(inm)}<br>
      Renta: ${fmtMoney(c.renta_base)} · Inicio: ${fmtDate(c.fecha_inicio)}
    </div>
    <form id="form-baja" class="form-grid form-grid-2">
      <div class="form-group">
        <label>Fecha de baja *</label>
        <input name="fecha_baja" type="date" value="${hoy}" required>
      </div>
      <div class="form-group">
        <label>Motivo</label>
        <select name="motivo_baja">
          <option>Fin de contrato</option>
          <option>Rescisión voluntaria</option>
          <option>Impago</option>
          <option>Desahucio</option>
          <option>Acuerdo mutuo</option>
          <option>Otro</option>
        </select>
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>Observaciones</label>
        <textarea name="obs_baja" placeholder="Detalles adicionales..."></textarea>
      </div>
    </form>`,
    `<button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
     <button class="btn btn-danger" onclick="confirmarBaja(${id})">Dar de baja</button>`);
}

async function confirmarBaja(id) {
  const form = document.getElementById('form-baja');
  if (!form.checkValidity()) { form.reportValidity(); return; }
  const d = Object.fromEntries(new FormData(form));
  const c = DB.getItem('contratos', id);
  c.estado      = 'finalizado';
  c.fecha_baja  = d.fecha_baja;
  c.motivo_baja = d.motivo_baja;
  c.obs_baja    = d.obs_baja;
  await DB.save('contratos', c);
  // Registrar baja en el log de auditoría (fire-and-forget)
  const _inqBaja = (DB.getItem('inquilinos', c.inquilino_id) || {}).nombre || '';
  registrarActividad('baja_contrato', 'contratos', id,
    _inqBaja + ' — motivo: ' + (d.motivo_baja || 'no indicado'));
  closeModalForce();
  toast('Contrato dado de baja');
  renderContratos();
}

// ===========================
// MODAL APLICAR REVISIÓN IPC / IRAV
// El porcentaje se obtiene en tiempo real desde el INE (api.php?action=ine_rate).
// Si el INE no responde se muestra 0% y un aviso para introducirlo manualmente.
// ===========================
async function modalAplicarIPC(id) {
  const c   = DB.getItem('contratos', id);
  if (!c) return;
  const inq  = DB.getItem('inquilinos', c.inquilino_id);
  const inm  = DB.getItem('inmuebles',  c.inmueble_id);
  const tipo = (c.revision === 'IRAV') ? 'IRAV' : 'IPC';
  const iniDate = new Date(c.fecha_inicio + 'T00:00:00');
  const anios   = new Date().getFullYear() - iniDate.getFullYear();

  // Abrir modal con spinner mientras se consulta el INE
  const _calcHtml = (pct) => {
    const nueva = Math.round(c.renta_base * (1 + pct / 100) * 100) / 100;
    const inc   = Math.round((nueva - c.renta_base) * 100) / 100;
    return {
      nueva: nueva.toLocaleString('es-ES', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' €',
      inc:   inc.toLocaleString('es-ES',   {minimumFractionDigits:2, maximumFractionDigits:2}) + ' €/mes',
    };
  };

  const _renderModal = (pct, fuente, aviso) => {
    const calc = _calcHtml(pct);
    const fuenteHtml = fuente === 'INE'
      ? `<span style="color:var(--green);font-size:11px">✓ Dato obtenido del INE</span>`
      : `<span style="color:var(--red);font-size:11px">⚠ ${aviso || 'No se pudo conectar con el INE — introduzca el porcentaje manualmente'}</span>`;
    openModal(`Revisión ${tipo} — ${inq?.nombre || ''}`, `
      <div class="alert alert-info" style="margin-bottom:16px">
        <strong>${inq?.nombre || '—'}</strong> · ${getInmuebleNombre(inm)}<br>
        Contrato iniciado el <strong>${fmtDate(c.fecha_inicio)}</strong> · ${anios}º aniversario
      </div>
      <div style="background:var(--orange-light);border:1px solid var(--color-warn-muted);border-radius:8px;padding:14px 16px;margin-bottom:20px">
        <div style="font-size:13px;color:var(--orange);margin-bottom:4px">
          Renta actual: <strong style="font-size:16px">${fmtMoney(c.renta_base)}</strong>
        </div>
        <div style="margin-bottom:12px">${fuenteHtml}</div>
        <div class="form-grid form-grid-2" style="gap:12px">
          <div class="form-group" style="margin:0">
            <label>% ${tipo} a aplicar</label>
            <input id="ipc-pct" type="number" step="0.01" min="-10" max="25"
                   value="${pct}"
                   oninput="
                     const pct = parseFloat(this.value)||0;
                     const nueva = Math.round(${c.renta_base} * (1 + pct/100) * 100) / 100;
                     document.getElementById('ipc-nueva-renta').textContent = nueva.toLocaleString('es-ES',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' €';
                     document.getElementById('ipc-diferencia').textContent = (nueva - ${c.renta_base}).toLocaleString('es-ES',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' €/mes';
                   ">
          </div>
          <div class="form-group" style="margin:0">
            <label>Nueva renta resultante</label>
            <div style="padding:8px 12px;background:var(--gray-50);border:1px solid var(--gray-300);border-radius:6px;font-size:16px;font-weight:700;color:var(--green)">
              <span id="ipc-nueva-renta">${calc.nueva}</span>
            </div>
          </div>
        </div>
        <div style="font-size:12px;color:var(--orange);margin-top:8px">
          Incremento: <strong><span id="ipc-diferencia">${calc.inc}</span></strong>
        </div>
      </div>
      <div style="font-size:12px;color:var(--gray-500)">
        Al aplicar la subida se actualizará la renta del contrato y no volverá a aparecer el aviso ${tipo} este año.
      </div>
    `, `
      <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
      <button class="btn btn-primary" style="background:#f59e0b;border-color:#d97706"
              onclick="aplicarSubidaIPC(${id})">Aplicar subida</button>
    `);
  };

  // Mostrar modal con 0% mientras se carga el dato del INE
  _renderModal(0, 'cargando', '');
  toast(`Consultando ${tipo} al INE…`, 'info');

  try {
    const resp = await fetch(`assets/php/api.php?action=ine_rate&tipo=${tipo}`);
    const data = await resp.json();
    const pct  = parseFloat(data.valor) || 0;
    closeModalForce();
    _renderModal(pct, data.fuente, data.aviso);
  } catch (e) {
    console.warn('[INE] Error al consultar api.php?action=ine_rate:', e);
    closeModalForce();
    _renderModal(0, 'fallback', 'No se pudo contactar con el INE — introduzca el porcentaje manualmente');
  }
}

async function aplicarSubidaIPC(id) {
  const c    = DB.getItem('contratos', id);
  const tipo = (c.revision === 'IRAV') ? 'IRAV' : 'IPC';
  const pct  = parseFloat(document.getElementById('ipc-pct')?.value) || 0;
  // Guardar renta anterior antes de modificar el contrato
  const rentaAnterior = c.renta_base || 0;
  const rentaNueva = Math.round(rentaAnterior * (1 + pct / 100) * 100) / 100;
  c.renta_base        = rentaNueva;
  c.ipc_anio_aplicado = new Date().getFullYear();
  await DB.save('contratos', c);
  // Registrar el cambio en el historial de revisiones
  await DB.save('historial_rentas', {
    contrato_id:    id,
    fecha:          fmtLocalISO(new Date()),
    tipo_revision:  tipo,
    porcentaje:     pct,
    renta_anterior: rentaAnterior,
    renta_nueva:    rentaNueva,
    observaciones:  'Revisión ' + tipo + ' aplicada manualmente'
  });
  // Registrar subida IPC en el log de auditoría (fire-and-forget)
  const _inqIPC = (DB.getItem('inquilinos', c.inquilino_id) || {}).nombre || '';
  registrarActividad('subida_ipc', 'contratos', id,
    _inqIPC + ' — ' + tipo + ' ' + pct + '% — ' + fmtMoney(rentaAnterior) + ' → ' + fmtMoney(rentaNueva));
  closeModalForce();
  toast(`${tipo} aplicado · Nueva renta: ${fmtMoney(rentaNueva)}`, 'success');
  renderContratos();
}

// Muestra el historial de revisiones de renta de un contrato en un modal.
function modalHistorialRentas(contratoId) {
  const historial = DB.get('historial_rentas')
    .filter(h => h.contrato_id === contratoId)
    .sort((a, b) => (b.fecha || '').localeCompare(a.fecha || ''));
  const c = DB.getItem('contratos', contratoId);
  const inq = c ? DB.getItem('inquilinos', c.inquilino_id) : null;
  const titulo = inq ? `Historial de rentas — ${esc(inq.nombre)}` : 'Historial de rentas';
  const filas = historial.length
    ? historial.map(h => `
        <tr>
          <td>${fmtDate(h.fecha)}</td>
          <td>${esc(h.tipo_revision || '-')}</td>
          <td>${h.porcentaje != null ? h.porcentaje + '%' : '-'}</td>
          <td>${fmtMoney(h.renta_anterior)}</td>
          <td>${fmtMoney(h.renta_nueva)}</td>
          <td style="font-size:12px;color:var(--gray-500)">${esc(h.observaciones || '')}</td>
        </tr>`).join('')
    : '<tr><td colspan="6" style="text-align:center;color:var(--gray-400)">Sin revisiones registradas</td></tr>';
  openModal(
    titulo,
    `<div class="table-wrap">
      <table>
        <thead><tr><th>Fecha</th><th>Tipo</th><th>%</th><th>Renta anterior</th><th>Renta nueva</th><th>Observaciones</th></tr></thead>
        <tbody>${filas}</tbody>
      </table>
    </div>`,
    `<button class="btn btn-secondary" onclick="closeModal()">Cerrar</button>`
  );
}

// ===========================
// RENOVACIÓN DE CONTRATO [13]
// Permite extender la fecha de fin del contrato y actualizar la renta,
// registrando el cambio en el historial de rentas.
// ===========================
function modalRenovarContrato(id) {
  const c = DB.getItem('contratos', id);
  if (!c) return;
  const inq = DB.getItem('inquilinos', c.inquilino_id);
  const inm = DB.getItem('inmuebles', c.inmueble_id);
  // Calcular fecha de fin propuesta: 1 año desde la fecha de fin actual (o desde hoy)
  const finActual = c.fecha_fin ? new Date(c.fecha_fin) : new Date();
  const finNuevo = new Date(finActual);
  finNuevo.setFullYear(finNuevo.getFullYear() + 1);
  openModal(
    'Renovar contrato',
    `<p style="margin-bottom:16px;color:var(--gray-600)">
      <strong>${esc(inq ? inq.nombre : '-')}</strong> &mdash; ${esc(inm ? getInmuebleNombre(inm) : '-')}
    </p>
    <div class="form-grid">
      <div class="form-group">
        <label>Nueva fecha de fin</label>
        <input type="date" id="renov-fecha-fin" value="${fmtLocalISO(finNuevo)}">
      </div>
      <div class="form-group">
        <label>Nueva renta base (€)</label>
        <input type="number" id="renov-renta" value="${c.renta_base || 0}" min="0" step="0.01">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>Observaciones</label>
        <textarea id="renov-obs" rows="2" placeholder="Condiciones de renovación, etc.">${esc(c.observaciones_renovacion || '')}</textarea>
      </div>
    </div>`,
    `<button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
    <button class="btn btn-primary" onclick="saveRenovarContrato(${id})">Guardar renovación</button>`
  );
}

async function saveRenovarContrato(id) {
  const c = DB.getItem('contratos', id);
  if (!c) return;
  const fechaFin  = document.getElementById('renov-fecha-fin')?.value;
  const rentaNueva = parseFloat(document.getElementById('renov-renta')?.value) || 0;
  const obs = document.getElementById('renov-obs')?.value || '';
  if (!fechaFin) return toast('La fecha de fin es obligatoria', 'error');
  // Registrar cambio de renta en historial si varió
  const rentaAnterior = c.renta_base || 0;
  if (rentaNueva !== rentaAnterior) {
    await DB.save('historial_rentas', {
      contrato_id:    id,
      fecha:          fmtLocalISO(new Date()),
      tipo_revision:  'Renovación',
      porcentaje:     rentaAnterior > 0 ? Math.round((rentaNueva / rentaAnterior - 1) * 10000) / 100 : 0,
      renta_anterior: rentaAnterior,
      renta_nueva:    rentaNueva,
      observaciones:  obs || 'Cambio de renta en renovación de contrato'
    });
  }
  c.fecha_fin   = fechaFin;
  c.renta_base  = rentaNueva;
  c.estado      = 'activo';
  if (obs) c.observaciones = obs;
  await DB.save('contratos', c);
  closeModalForce();
  toast('Contrato renovado correctamente', 'success');
  renderContratos();
}

// ===========================
// INQUILINOS SECUNDARIOS [inq_sec]
// Firmantes adicionales del contrato.
// Solo afectan a la generación de documentos Word (bloque {{#INQUILINOS_SECUNDARIOS}}).
// No intervienen en ningún proceso de negocio (recibos, facturas, cobros, etc.).
// ===========================

// Renderiza la lista de inquilinos secundarios dentro del bloque del modal de contrato.
function _inqSecRender(contratoId) {
  const el = document.getElementById('bloque-inq-sec');
  if (!el) return;

  const lista = DB.get('contratos_inq_sec').filter(function(s) { return s.contrato_id === contratoId; });
  lista.sort(function(a, b) { return (a.orden || 0) - (b.orden || 0) || a.id - b.id; });

  var html = '';
  if (lista.length) {
    html += '<table style="width:100%;font-size:12px;border-collapse:collapse;margin-bottom:8px">';
    html += '<thead><tr style="border-bottom:1px solid var(--gray-200)">' +
            '<th style="text-align:left;padding:4px 6px;font-weight:600">Nombre</th>' +
            '<th style="text-align:left;padding:4px 6px;font-weight:600">NIF</th>' +
            '<th style="text-align:left;padding:4px 6px;font-weight:600">Teléfono</th>' +
            '<th></th></tr></thead><tbody>';
    lista.forEach(function(s) {
      html += '<tr style="border-bottom:1px solid var(--gray-100)">' +
              '<td style="padding:4px 6px">' + esc(s.nombre) + '</td>' +
              '<td style="padding:4px 6px;color:var(--gray-500)">' + esc(s.nif || '—') + '</td>' +
              '<td style="padding:4px 6px;color:var(--gray-500)">' + esc(s.telefono || '—') + '</td>' +
              '<td style="padding:4px 6px;white-space:nowrap;text-align:right">' +
              '<button class="btn btn-sm btn-secondary" style="font-size:10px" ' +
              'onclick="_inqSecEditar(' + s.id + ',' + contratoId + ')">Editar</button> ' +
              '<button class="btn btn-sm btn-danger" style="font-size:10px" ' +
              'onclick="_inqSecEliminar(' + s.id + ',' + contratoId + ')">Eliminar</button>' +
              '</td></tr>';
    });
    html += '</tbody></table>';
  } else {
    html += '<div style="color:var(--gray-400);font-size:12px;padding:6px 0">Sin inquilinos secundarios.</div>';
  }

  html += '<button class="btn btn-sm btn-secondary" style="font-size:11px;margin-top:4px" ' +
          'onclick="_inqSecNuevo(' + contratoId + ')">+ Añadir inquilino secundario</button>';

  el.innerHTML = html;
}

// Abre el sub-modal para añadir un nuevo inquilino secundario.
function _inqSecNuevo(contratoId) {
  _inqSecModalForm(contratoId, null);
}

// Abre el sub-modal para editar un inquilino secundario existente.
function _inqSecEditar(id, contratoId) {
  _inqSecModalForm(contratoId, DB.getItem('contratos_inq_sec', id));
}

// Muestra el formulario de inquilino secundario en un modal de segundo nivel.
function _inqSecModalForm(contratoId, s) {
  s = s || {};
  openModal(s.id ? 'Editar inquilino secundario' : 'Añadir inquilino secundario',
    '<div class="form-grid form-grid-2" id="form-inq-sec">' +
    '<div class="form-group" style="grid-column:1/-1"><label>Nombre completo *</label>' +
    '<input id="is-nombre" type="text" value="' + esc(s.nombre || '') + '" placeholder="Ruiz Pérez, Ana" required></div>' +
    '<div class="form-group"><label>NIF / NIE</label>' +
    '<input id="is-nif" type="text" value="' + esc(s.nif || '') + '" placeholder="44556677E"></div>' +
    '<div class="form-group"><label>Teléfono</label>' +
    '<input id="is-telefono" type="text" value="' + esc(s.telefono || '') + '" placeholder="600 111 222"></div>' +
    '<div class="form-group"><label>Email</label>' +
    '<input id="is-email" type="email" value="' + esc(s.email || '') + '" placeholder="ana@email.com"></div>' +
    '<div class="form-group" style="grid-column:1/-1"><label>Dirección</label>' +
    '<input id="is-direccion" type="text" value="' + esc(s.direccion || '') + '" placeholder="Av. España 5, 28003 Madrid"></div>' +
    '</div>',
    '<button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>' +
    '<button class="btn btn-primary" onclick="_inqSecGuardar(' + contratoId + ',' + (s.id || 'null') + ')">Guardar</button>'
  );
}

// Guarda (crea o actualiza) un inquilino secundario.
async function _inqSecGuardar(contratoId, id) {
  const nombre = (document.getElementById('is-nombre') || {}).value.trim();
  if (!nombre) { toast('El nombre es obligatorio', 'error'); return; }
  const datos = {
    contrato_id: parseInt(contratoId),
    nombre:      nombre,
    nif:         (document.getElementById('is-nif')       || {}).value.trim() || '',
    telefono:    (document.getElementById('is-telefono')  || {}).value.trim() || '',
    email:       (document.getElementById('is-email')     || {}).value.trim() || '',
    direccion:   (document.getElementById('is-direccion') || {}).value.trim() || '',
    orden:       0,
  };
  if (id) datos.id = id;
  await DB.save('contratos_inq_sec', datos);
  closeModalForce();
  toast(id ? 'Inquilino secundario actualizado' : 'Inquilino secundario añadido', 'success');
  _inqSecRender(contratoId);
}

// Elimina un inquilino secundario tras confirmación.
async function _inqSecEliminar(id, contratoId) {
  if (!confirm('¿Eliminar este inquilino secundario?')) return;
  const r = await DB.delete('contratos_inq_sec', id);
  if (!r.ok) { toast(r.error || 'Error al eliminar', 'error'); return; }
  toast('Inquilino secundario eliminado', 'info');
  _inqSecRender(contratoId);
}
