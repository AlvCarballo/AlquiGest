// ===========================
// GENERAR RECIBO DESDE CONTRATO
// ===========================
function modalGenerarRecibo(contratoId) {
  const contrato = DB.getItem('contratos', contratoId);
  if (!contrato) return;
  if (contrato.estado !== 'activo') {
    toast('No se pueden generar recibos para contratos que no están activos', 'error');
    return;
  }
  const inq = DB.getItem('inquilinos', contrato.inquilino_id);
  const inm = DB.getItem('inmuebles', contrato.inmueble_id);
  const prop = inm ? DB.getItem('propietarios', inm.propietario_id) : null;

  const now = new Date();
  const mesActual = now.toLocaleDateString('es-ES', { month:'long', year:'numeric' });
  const primerDia = fmtLocalISO(new Date(now.getFullYear(), now.getMonth(), 1));
  const ultimoDia = fmtLocalISO(new Date(now.getFullYear(), now.getMonth()+1, 0));

  const iva = (contrato.renta_base * contrato.iva_pct / 100);
  const irpf = (contrato.renta_base * contrato.irpf_pct / 100);
  const total = contrato.renta_base + iva - irpf;

  // Comprobar si hay revisión IPC/IRAV anual pendiente para este contrato
  const anioActual = now.getFullYear();
  let alertaIPCHtml = '';
  let _ipcTipoFetch = null;
  if (TIPOS_REVISION_INE.includes(contrato.revision) && contrato.fecha_inicio) {
    const iniContrato  = new Date(contrato.fecha_inicio + 'T00:00:00');
    const mesInicio    = iniContrato.getMonth() + 1;
    const mesActualN   = now.getMonth() + 1;
    const anosVigencia = anioActual - iniContrato.getFullYear();
    const yaAplicado   = parseInt(contrato.ipc_anio_aplicado) === anioActual;
    if (mesInicio === mesActualN && anosVigencia > 0 && !yaAplicado) {
      const tipo = contrato.revision;
      const rentaActual = contrato.renta_base;
      _ipcTipoFetch = tipo;
      alertaIPCHtml = `
        <div id="alerta-ipc-recibo" style="background:#fef3c7;border:1px solid #fbbf24;border-radius:8px;padding:12px;margin-bottom:16px">
          <div style="font-weight:700;color:#92400e;margin-bottom:8px">⚠ Revisión ${tipo} anual — ${anosVigencia}º aniversario del contrato</div>
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px">
            <label style="font-size:13px;color:#78350f;white-space:nowrap">Porcentaje ${tipo} (%):</label>
            <input id="inp-ipc-pct-recibo" type="number" step="0.01" min="-10" max="25" value="0"
                   style="width:90px;padding:4px 8px;border:1px solid #fbbf24;border-radius:4px;background:#fff"
                   oninput="(function(){const pct=parseFloat(document.getElementById('inp-ipc-pct-recibo').value)||0;const nueva=Math.round(${rentaActual}*(1+pct/100)*100)/100;const el=document.getElementById('ipc-nueva-renta-recibo');if(el)el.textContent=nueva.toLocaleString('es-ES',{minimumFractionDigits:2,maximumFractionDigits:2})+' €';})()">
            <span id="ipc-fuente-recibo" style="font-size:11px;color:#92400e;font-style:italic">⏳ Consultando INE…</span>
          </div>
          <div style="font-size:13px;color:#78350f;margin-bottom:8px">
            Renta actual: <strong>${fmtMoney(rentaActual)}</strong> →
            Nueva renta: <strong id="ipc-nueva-renta-recibo">${fmtMoney(rentaActual)}</strong>
          </div>
          <button type="button" class="btn btn-sm" style="background:#c27803;color:white;border:none"
                  onclick="aplicarIPCDesdeRecibo(${contratoId})">
            Aplicar al recibo
          </button>
        </div>`;
    }
  }

  openModal('Generar recibo de alquiler', `
    <form id="form-generar-recibo" class="form-grid form-grid-2">
      <div class="form-group" style="grid-column:1/-1">
        <div class="alert alert-info">
          Contrato: <strong>${inq?.nombre||'-'}</strong> → <strong>${inm?.nombre||'-'}</strong>
        </div>
        ${alertaIPCHtml}
      </div>
      <div class="form-group">
        <label>Número de recibo</label>
        <input name="numero_recibo" value="Se asignará automáticamente" readonly
               style="color:var(--text-muted);background:var(--bg-subtle);font-style:italic">
      </div>
      <div class="form-group">
        <label>Fecha emisión</label>
        <input name="fecha_emision" type="date" value="${fmtLocalISO(now)}">
      </div>
      <div class="form-group">
        <label>Período desde</label>
        <input name="periodo_desde" type="date" value="${primerDia}">
      </div>
      <div class="form-group">
        <label>Período hasta</label>
        <input name="periodo_hasta" type="date" value="${ultimoDia}">
      </div>
      <div class="form-group">
        <label>Concepto período</label>
        <input name="concepto_periodo" value="Alquiler ${mesActual}">
      </div>
      <div class="form-group">
        <label>Fecha límite pago</label>
        <input name="fecha_limite" type="date" value="${fmtLocalISO(new Date(now.getFullYear(), now.getMonth(), contrato.dia_pago||5))}">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <div class="form-section-title" style="margin-top:4px">Importes</div>
      </div>
      <div class="form-group">
        <label>Renta base (€)</label>
        <input name="renta_base" type="number" step="0.01" value="${contrato.renta_base}" id="inp-renta" oninput="recalcRecibo()">
      </div>
      <div class="form-group">
        <label>IVA (%)</label>
        <div style="display:flex;gap:8px;align-items:center">
          <input id="inp-iva-pct" type="number" step="0.01" min="0" max="100" value="${contrato.iva_pct||0}" oninput="recalcRecibo()" style="width:90px">
          <span style="color:var(--gray-500);font-size:12px">= <strong id="disp-iva">${iva.toFixed(2)}</strong> €</span>
        </div>
        <input type="hidden" name="importe_iva" id="inp-iva" value="${iva.toFixed(2)}">
      </div>
      <div class="form-group">
        <label>IRPF (%) a deducir</label>
        <div style="display:flex;gap:8px;align-items:center">
          <input id="inp-irpf-pct" type="number" step="0.01" min="0" max="100" value="${contrato.irpf_pct||0}" oninput="recalcRecibo()" style="width:90px">
          <span style="color:var(--gray-500);font-size:12px">= <strong id="disp-irpf">${irpf.toFixed(2)}</strong> €</span>
        </div>
        <input type="hidden" name="importe_irpf" id="inp-irpf" value="${irpf.toFixed(2)}">
      </div>
      <div class="form-group">
        <label style="color:var(--blue)">TOTAL A COBRAR (€)</label>
        <input name="importe_total" type="number" step="0.01" value="${total.toFixed(2)}" id="inp-total" style="font-weight:700;font-size:16px;color:var(--blue)">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>Conceptos adicionales (uno por línea)</label>
        <textarea name="conceptos_extra" placeholder="Comunidad: 45,00€&#10;Seguro: 12,00€"></textarea>
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>Notas internas</label>
        <textarea name="notas" placeholder="Notas internas del recibo..."></textarea>
      </div>
      <input type="hidden" name="contrato_id" value="${contratoId}">
      <input type="hidden" name="inquilino_id" value="${contrato.inquilino_id}">
      <input type="hidden" name="inmueble_id" value="${contrato.inmueble_id}">
    </form>
  `, `
    <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
    <button class="btn btn-primary" onclick="saveRecibo()">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      Crear recibo
    </button>
  `);

  // Fetch del porcentaje INE una vez el modal está en el DOM
  if (_ipcTipoFetch) {
    fetch('assets/php/api.php?action=ine_rate&tipo=' + _ipcTipoFetch)
      .then(r => r.json())
      .then(data => {
        const inp      = document.getElementById('inp-ipc-pct-recibo');
        const fuenteEl = document.getElementById('ipc-fuente-recibo');
        const rentaEl  = document.getElementById('ipc-nueva-renta-recibo');
        if (!inp) return;
        const pct  = typeof data.valor === 'number' ? data.valor : 0;
        inp.value  = pct;
        const nueva = Math.round(contrato.renta_base * (1 + pct / 100) * 100) / 100;
        if (rentaEl)  rentaEl.textContent  = nueva.toLocaleString('es-ES', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' €';
        if (fuenteEl) fuenteEl.textContent = (data.fuente === 'INE')
          ? ('✓ ' + _ipcTipoFetch + ' INE: ' + pct + '%')
          : ('⚠ Valor manual: ' + pct + '%');
      })
      .catch(() => {
        const fuenteEl = document.getElementById('ipc-fuente-recibo');
        if (fuenteEl) fuenteEl.textContent = '⚠ No se pudo contactar con el INE';
      });
  }
}

// Aplica el porcentaje IPC/IRAV al recibo en curso y lo guarda en el contrato
// para que no vuelva a aparecer el aviso este año.
async function aplicarIPCDesdeRecibo(contratoId) {
  const pct  = parseFloat(document.getElementById('inp-ipc-pct-recibo')?.value) || 0;
  const c    = DB.getItem('contratos', contratoId);
  if (!c) return;
  const nueva = Math.round(c.renta_base * (1 + pct / 100) * 100) / 100;

  // Actualizar campo renta del formulario del recibo
  const inpRenta = document.getElementById('inp-renta');
  if (inpRenta) { inpRenta.value = nueva; recalcRecibo(); }

  // Guardar contrato en BD con nueva renta e ipc_anio_aplicado
  c.renta_base        = nueva;
  c.ipc_anio_aplicado = new Date().getFullYear();
  await DB.save('contratos', c);

  // Feedback visual en el bloque de alerta
  const fuenteEl = document.getElementById('ipc-fuente-recibo');
  if (fuenteEl) fuenteEl.textContent = `✓ Aplicado ${pct}% · Nueva renta: ${fmtMoney(nueva)}`;
  const btn = document.querySelector('#alerta-ipc-recibo button');
  if (btn) { btn.disabled = true; btn.textContent = '✓ Aplicado'; btn.style.background = '#15803d'; }
}

// Recalcula los importes del formulario de generación de recibo en tiempo real.
// Se llama al cambiar renta base, % IVA o % IRPF.
// Fórmula: Total = Renta + IVA − IRPF (el IRPF es una retención que descuenta el inquilino)
function recalcRecibo() {
  const renta   = parseFloat(document.getElementById('inp-renta')?.value)    || 0;
  const ivaPct  = parseFloat(document.getElementById('inp-iva-pct')?.value)  || 0;
  const irpfPct = parseFloat(document.getElementById('inp-irpf-pct')?.value) || 0;
  const iva   = renta * ivaPct  / 100;
  const irpf  = renta * irpfPct / 100;
  const total = renta + iva - irpf;
  const elIva      = document.getElementById('inp-iva');
  const elIrpf     = document.getElementById('inp-irpf');
  const elTotal    = document.getElementById('inp-total');
  const elDispIva  = document.getElementById('disp-iva');
  const elDispIrpf = document.getElementById('disp-irpf');
  if (elIva)      elIva.value            = iva.toFixed(2);
  if (elIrpf)     elIrpf.value           = irpf.toFixed(2);
  if (elTotal)    elTotal.value          = total.toFixed(2);
  if (elDispIva)  elDispIva.textContent  = iva.toFixed(2);
  if (elDispIrpf) elDispIrpf.textContent = irpf.toFixed(2);
}

function recalcTotal() {
  const renta = parseFloat(document.getElementById('inp-renta')?.value) || 0;
  const iva   = parseFloat(document.getElementById('inp-iva')?.value)   || 0;
  const irpf  = parseFloat(document.getElementById('inp-irpf')?.value)  || 0;
  const el = document.getElementById('inp-total');
  if (el) el.value = (renta + iva - irpf).toFixed(2);
}

async function saveRecibo() {
  const form = document.getElementById('form-generar-recibo');
  if (!form) { toast('Selecciona un contrato primero', 'error'); return; }
  const data = Object.fromEntries(new FormData(form));
  data.contrato_id  = parseInt(data.contrato_id);
  data.inquilino_id = parseInt(data.inquilino_id);
  data.inmueble_id  = parseInt(data.inmueble_id);
  data.renta_base   = parseFloat(data.renta_base)  || 0;
  data.importe_iva  = parseFloat(data.importe_iva) || 0;
  data.importe_irpf = parseFloat(data.importe_irpf)|| 0;
  data.importe_total= parseFloat(data.importe_total)||0;
  data.estado       = 'pendiente';
  data.fecha_creacion = new Date().toISOString();

  // Obtener número de secuencia del servidor (atómico, sin duplicados)
  const empresa = DB.getEmpresa();
  const prefix  = empresa?.prefijo_recibos || 'REC';
  const fechaEm = data.fecha_emision || new Date().toISOString().slice(0, 10);
  const periodo = fechaEm.replace(/-/g, '').slice(0, 6);
  let seqInfo;
  try {
    seqInfo = await nextNumeroDoc('REC', periodo, prefix);
  } catch (e) {
    toast('No se pudo generar el número de recibo. Inténtalo de nuevo.', 'error');
    return;
  }
  data.numero_recibo = seqInfo.numero;
  data.numero_seq    = seqInfo.seq;

  await DB.save('recibos', data);
  closeModalForce();
  toast('Recibo ' + seqInfo.numero + ' creado correctamente');
  navigate('recibos');
}

// ── Nuevo recibo individual desde sección Recibos ─────────────
function modalNuevoReciboLibre() {
  const contratos = DB.get('contratos').filter(c => c.estado === 'activo');
  if (!contratos.length) { toast('No hay contratos activos', 'error'); return; }
  openModal('Nuevo recibo de alquiler', `
    <div class="form-grid form-grid-2" style="gap:16px;margin-bottom:20px">
      <div class="form-group">
        <label>Nº Recibo</label>
        <input value="Se asignará automáticamente" readonly
               style="color:var(--text-muted);background:var(--bg-subtle);font-style:italic">
      </div>
      <div class="form-group">
        <label>Contrato *</label>
        <select id="sel-contrato-nuevo" onchange="actualizarFormNuevoRecibo()" style="padding:8px">
          <option value="">-- Selecciona un contrato --</option>
          ${contratos.map(c => {
            const inq = DB.getItem('inquilinos', c.inquilino_id);
            const inm = DB.getItem('inmuebles', c.inmueble_id);
            return `<option value="${c.id}">${inq ? inq.nombre : '-'} – ${inm ? getInmuebleNombre(inm) : '-'}</option>`;
          }).join('')}
        </select>
      </div>
    </div>
    <div id="form-nuevo-recibo-body">
      <p style="text-align:center;color:var(--gray-400);padding:20px 0">Selecciona un contrato para continuar</p>
    </div>
  `, `
    <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
    <button class="btn btn-primary" onclick="saveRecibo()">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
      Crear recibo
    </button>
  `, true);
}

function actualizarFormNuevoRecibo() {
  const sel = document.getElementById('sel-contrato-nuevo');
  if (!sel) return;
  const contratoId = parseInt(sel.value);
  const body = document.getElementById('form-nuevo-recibo-body');
  if (!contratoId) {
    body.innerHTML = '<p style="text-align:center;color:var(--gray-400);padding:20px 0">Selecciona un contrato para continuar</p>';
    return;
  }
  const c = DB.getItem('contratos', contratoId);
  if (!c) return;
  const now = new Date();
  const mesActual = now.toLocaleDateString('es-ES', { month:'long', year:'numeric' });
  const primerDia = fmtLocalISO(new Date(now.getFullYear(), now.getMonth(), 1));
  const ultimoDia = fmtLocalISO(new Date(now.getFullYear(), now.getMonth()+1, 0));
  const iva  = (c.renta_base||0) * (c.iva_pct||0) / 100;
  const irpf = (c.renta_base||0) * (c.irpf_pct||0) / 100;
  const total = (c.renta_base||0) + iva - irpf;
  body.innerHTML = `
    <form id="form-generar-recibo" class="form-grid form-grid-2">
      <div class="form-group">
        <label>Fecha emisión</label>
        <input name="fecha_emision" type="date" value="${fmtLocalISO(now)}">
      </div>
      <div class="form-group">
        <label>Período desde</label>
        <input name="periodo_desde" type="date" value="${primerDia}">
      </div>
      <div class="form-group">
        <label>Período hasta</label>
        <input name="periodo_hasta" type="date" value="${ultimoDia}">
      </div>
      <div class="form-group">
        <label>Concepto período</label>
        <input name="concepto_periodo" value="Alquiler ${mesActual}">
      </div>
      <div class="form-group">
        <label>Fecha límite pago</label>
        <input name="fecha_limite" type="date" value="${fmtLocalISO(new Date(now.getFullYear(), now.getMonth(), c.dia_pago||5))}">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <div class="form-section-title" style="margin-top:4px">Importes</div>
      </div>
      <div class="form-group">
        <label>Renta base (€)</label>
        <input name="renta_base" id="inp-renta" type="number" step="0.01" value="${(c.renta_base||0).toFixed(2)}" oninput="recalcRecibo()">
      </div>
      <div class="form-group">
        <label>IVA (%)</label>
        <div style="display:flex;gap:8px;align-items:center">
          <input id="inp-iva-pct" type="number" step="0.01" min="0" max="100" value="${c.iva_pct||0}" oninput="recalcRecibo()" style="width:90px">
          <span style="color:var(--gray-500);font-size:12px">= <strong id="disp-iva">${iva.toFixed(2)}</strong> €</span>
        </div>
        <input type="hidden" name="importe_iva" id="inp-iva" value="${iva.toFixed(2)}">
      </div>
      <div class="form-group">
        <label>IRPF (%) a deducir</label>
        <div style="display:flex;gap:8px;align-items:center">
          <input id="inp-irpf-pct" type="number" step="0.01" min="0" max="100" value="${c.irpf_pct||0}" oninput="recalcRecibo()" style="width:90px">
          <span style="color:var(--gray-500);font-size:12px">= <strong id="disp-irpf">${irpf.toFixed(2)}</strong> €</span>
        </div>
        <input type="hidden" name="importe_irpf" id="inp-irpf" value="${irpf.toFixed(2)}">
      </div>
      <div class="form-group">
        <label style="color:var(--blue)">TOTAL A COBRAR (€)</label>
        <input name="importe_total" id="inp-total" type="number" step="0.01" value="${total.toFixed(2)}" style="font-weight:700;font-size:16px;color:var(--blue)">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>Conceptos adicionales (uno por línea)</label>
        <textarea name="conceptos_extra" placeholder="Comunidad: 45,00€&#10;Seguro: 12,00€"></textarea>
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>Notas internas</label>
        <textarea name="notas"></textarea>
      </div>
      <input type="hidden" name="contrato_id" value="${contratoId}">
      <input type="hidden" name="inquilino_id" value="${c.inquilino_id}">
      <input type="hidden" name="inmueble_id" value="${c.inmueble_id}">
    </form>`;
}

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
                  ${_cfgVisi('VisiAnularReci') ? `<button class="btn btn-sm btn-danger btn-icon" title="Anular" onclick="anularRecibo(${r.id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                  </button>` : ''}
                  ${_cfgVisi('VisiFacturaReci') && r.estado !== 'anulado'
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

// ===========================
// CONTROL DE PAGOS — DAR COBRO
// Un recibo puede recibir varios cobros parciales (campo `pagos` → array JSON).
// guardarPago() añade el nuevo cobro al array y recalcula el estado:
//   · Si el total pagado ≥ importe_total → estado 'cobrado'
//   · Si el total pagado < importe_total pero > 0 → estado 'parcial'
// anularPago() elimina un cobro del array y revierte el estado si es necesario.
// ===========================
function modalDarCobro(id) {
  const r   = DB.getItem('recibos', id);
  if (!r) return;
  const inq = DB.getItem('inquilinos', r.inquilino_id);
  const inm = DB.getItem('inmuebles',  r.inmueble_id);
  const empresa = DB.getEmpresa() || {};
  const pagos   = r.pagos || [];
  const totalPagado = pagos.reduce((s, p) => s + (p.importe||0), 0);
  const pendiente   = Math.max(0, (r.importe_total||0) - totalPagado);
  const hoy = new Date().toISOString().split('T')[0];

  openModal('Dar Cobro', `
    <div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;padding:12px;margin-bottom:16px">
      <div style="font-size:12px;color:var(--gray-500);text-transform:uppercase;letter-spacing:.05em">Recibo</div>
      <div style="font-weight:700;font-size:15px">${r.numero_recibo} — ${r.concepto_periodo||''}</div>
      <div style="color:var(--gray-600);font-size:13px">${inq?.nombre||'—'} · ${getInmuebleNombre(inm)}</div>
      <div style="display:flex;gap:24px;margin-top:8px">
        <div><div style="font-size:11px;color:var(--gray-400)">Total recibo</div><div style="font-weight:700">${fmtMoney(r.importe_total)}</div></div>
        <div><div style="font-size:11px;color:var(--gray-400)">Ya pagado</div><div style="font-weight:700;color:var(--green)">${fmtMoney(totalPagado)}</div></div>
        <div><div style="font-size:11px;color:var(--gray-400)">Pendiente</div><div style="font-weight:700;color:var(--red)">${fmtMoney(pendiente)}</div></div>
      </div>
    </div>
    ${pagos.length ? `
      <div style="margin-bottom:14px">
        <div style="font-size:11px;font-weight:700;color:var(--gray-500);text-transform:uppercase;margin-bottom:6px">Cobros anteriores</div>
        <table style="width:100%;font-size:12px;border-collapse:collapse">
          ${pagos.map((p,i)=>`<tr>
            <td style="padding:3px 6px;border-bottom:1px solid var(--gray-100)">${fmtDate(p.fecha)}</td>
            <td style="padding:3px 6px;border-bottom:1px solid var(--gray-100)">${p.metodo||''}</td>
            <td style="padding:3px 6px;border-bottom:1px solid var(--gray-100);text-align:right;font-weight:600">${fmtMoney(p.importe)}</td>
            <td style="padding:3px 6px;border-bottom:1px solid var(--gray-100)">${_cfgVisi('VisiAnularPago') ? `<button class="btn btn-sm btn-danger" style="padding:2px 8px;font-size:11px" onclick="anularPago(${id},${i})">Anular</button>` : ''}</td>
          </tr>`).join('')}
        </table>
      </div>` : ''}
    <form id="form-cobro" class="form-grid form-grid-2">
      <div class="form-group">
        <label>Importe a cobrar (€) *</label>
        <input id="inp-cobro-importe" name="importe" type="number" step="0.01" min="0.01"
               value="${pendiente.toFixed(2)}" required style="font-size:16px;font-weight:700;color:var(--blue)">
      </div>
      <div class="form-group">
        <label>Fecha de cobro *</label>
        <input name="fecha" type="date" value="${hoy}" required>
      </div>
      <div class="form-group">
        <label>Método</label>
        <select name="metodo">
          <option>Transferencia</option>
          <option>Domiciliación</option>
          <option>Efectivo</option>
          <option>Bizum</option>
          <option>Cheque</option>
        </select>
      </div>
      <div class="form-group">
        <label>Cuenta de ingreso</label>
        <input name="cuenta" value="${empresa.iban||''}">
      </div>
    </form>`,
    `<button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
     <button class="btn btn-success" onclick="guardarPago(${id})">
       <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
       Dar Cobro
     </button>`);
}

async function guardarPago(id) {
  const form = document.getElementById('form-cobro');
  if (!form.checkValidity()) { form.reportValidity(); return; }
  const d = Object.fromEntries(new FormData(form));
  const r = DB.getItem('recibos', id);
  if (!r.pagos) r.pagos = [];
  r.pagos.push({ fecha: d.fecha, importe: parseFloat(d.importe), metodo: d.metodo, cuenta: d.cuenta });
  const totalPagado = r.pagos.reduce((s, p) => s + p.importe, 0);
  r.importe_pagado = totalPagado;
  if (totalPagado >= (r.importe_total||0)) {
    r.estado      = 'cobrado';
    r.fecha_cobro = d.fecha;
  } else {
    r.estado = 'parcial';
  }
  await DB.save('recibos', r);
  closeModalForce();
  toast(r.estado === 'cobrado' ? 'Recibo cobrado completamente ✓' : `Cobro parcial registrado (${fmtMoney(totalPagado)} de ${fmtMoney(r.importe_total)})`);
  renderRecibos(navParams);
}

async function anularPago(reciboId, pagoIdx) {
  if (!confirm('¿Anular este cobro?')) return;
  const r = DB.getItem('recibos', reciboId);
  if (!r || !r.pagos) return;
  r.pagos.splice(pagoIdx, 1);
  const totalPagado = r.pagos.reduce((s, p) => s + (p.importe||0), 0);
  r.importe_pagado = totalPagado;
  if (totalPagado <= 0) {
    r.estado = 'pendiente';
    r.fecha_cobro = null;
  } else if (totalPagado < (r.importe_total||0)) {
    r.estado = 'parcial';
    r.fecha_cobro = null;
  } else {
    r.estado = 'cobrado';
  }
  await DB.save('recibos', r);
  toast('Cobro anulado');
  closeModalForce();
  renderRecibos(navParams);
}

function marcarCobrado(id) { modalDarCobro(id); }

async function anularRecibo(id) {
  // Comprobar si el recibo tiene factura emitida antes de confirmar
  const facturaAsoc = DB.get('facturas').find(f => f.recibo_id === id);
  const avisoFactura = facturaAsoc
    ? '\n\n⚠ ATENCIÓN: Este recibo tiene la factura ' + facturaAsoc.numero_factura + ' emitida. El recibo se anulará pero la factura quedará en estado "emitida". Si necesitas rectificarla, hazlo desde el módulo de Facturas.'
    : '';
  if (!confirm('¿Anular este recibo?' + avisoFactura)) return;
  const r = DB.getItem('recibos', id);
  r.estado = 'anulado';
  await DB.save('recibos', r);
  toast('Recibo anulado' + (facturaAsoc ? ' — la factura ' + facturaAsoc.numero_factura + ' sigue emitida' : ''), 'info');
  renderRecibos();
}

function modalEditRecibo(id) {
  const r = DB.getItem('recibos', id);
  if (!r) return;
  openModal('Editar recibo', `
    <form id="form-edit-recibo" class="form-grid form-grid-2">
      <div class="form-group">
        <label>Número de recibo</label>
        <input name="numero_recibo" value="${r.numero_recibo}">
      </div>
      <div class="form-group">
        <label>Fecha emisión</label>
        <input name="fecha_emision" type="date" value="${r.fecha_emision||''}">
      </div>
      <div class="form-group">
        <label>Estado</label>
        <select name="estado">
          <option value="pendiente" ${r.estado==='pendiente'?'selected':''}>Pendiente</option>
          <option value="cobrado" ${r.estado==='cobrado'?'selected':''}>Cobrado</option>
          <option value="anulado" ${r.estado==='anulado'?'selected':''}>Anulado</option>
        </select>
      </div>
      <div class="form-group">
        <label>Fecha cobro</label>
        <input name="fecha_cobro" type="date" value="${r.fecha_cobro||''}">
      </div>
      <div class="form-group">
        <label>Total (€)</label>
        <input name="importe_total" type="number" step="0.01" value="${r.importe_total}">
      </div>
      <div class="form-group">
        <label>Concepto período</label>
        <input name="concepto_periodo" value="${r.concepto_periodo||''}">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>Notas</label>
        <textarea name="notas">${r.notas||''}</textarea>
      </div>
    </form>
  `, `
    <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
    <button class="btn btn-primary" onclick="updateRecibo(${id})">Guardar</button>
  `);
}

async function updateRecibo(id) {
  // Advertir si el recibo ya tiene factura: los cambios NO se reflejarán en la factura
  const facturaAsoc = DB.get('facturas').find(f => f.recibo_id === id);
  if (facturaAsoc) {
    if (!confirm('Aviso: este recibo tiene la factura ' + facturaAsoc.numero_factura + ' emitida.\n\nLos cambios que hagas en el recibo NO modificarán la factura (que queda congelada históricamente).\n\n¿Continuar editando el recibo de todas formas?')) return;
  }
  const form = document.getElementById('form-edit-recibo');
  const data = Object.fromEntries(new FormData(form));
  const r = DB.getItem('recibos', id);
  Object.assign(r, data);
  r.importe_total = parseFloat(r.importe_total)||0;
  await DB.save('recibos', r);
  closeModalForce();
  toast('Recibo actualizado' + (facturaAsoc ? ' (la factura ' + facturaAsoc.numero_factura + ' no ha cambiado)' : ''));
  renderRecibos();
}

// ===========================
// IMPRESIÓN Y PDF DE RECIBOS
// buildReciboHTML(id, formato) → genera el HTML del recibo (A4 o A5)
//   con todos los datos de empresa, propietario, inquilino e inmueble.
// imprimirRecibo(id, formato) → abre ventana nueva y lanza window.print()
// guardarReciboPDF(id, formato) → usa html2canvas + jsPDF para descarga directa
// _montarYCapturar(id, formato) → función auxiliar: monta el recibo en un div
//   oculto fuera de pantalla y captura su imagen con html2canvas (escala×2).
// _abrirVentanaImpresion(htmlArray, formato) → abre ventana con uno o varios
//   recibos separados por saltos de página e invoca window.print().
// ===========================
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

// ── Lote: imprimir o descargar PDF de varios recibos a la vez ──
//
// modalImprimirLote() abre el modal con todos los filtros disponibles.
// Filtros:
//   · Tipo de período: Mes concreto | Trimestre | Año completo
//   · Inquilino: Todos o uno específico
//   · Estado: Todos (sin anulados) | Pendientes | Cobrados
//   · Formato: A4 | A5
//
// _filtrarRecibosLote() aplica todos los filtros del modal y devuelve
//   el array de recibos resultante (sin modificar la BD).
//
// _nombreLote() genera el nombre del fichero PDF según los filtros elegidos.
//
// loteActualizarUI() muestra u oculta los campos dependiendo del tipo
//   de período seleccionado y actualiza el contador de recibos.
//
// loteActualizarContador() cuenta los recibos que coinciden con los
//   filtros actuales y muestra el resultado en el modal.
//
// ejecutarImpresionLote() → abre ventana de impresión del navegador.
// ejecutarPDFLote()       → genera PDF multipágina y lo descarga.

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

  let recibos = DB.get('recibos').filter(r => r.estado !== 'anulado');

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
