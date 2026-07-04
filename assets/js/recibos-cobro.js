// ============================================================
//  AlquiGest – recibos-cobro.js
//  Módulo de generación y cobro de recibos:
//    modalGenerarRecibo() → crear recibo desde contrato
//    modalNuevoReciboLibre() → crear recibo manual
//    modalDarCobro() → panel de pagos
//    guardarPago() / anularPago() → gestión de cobros
//    anularRecibo() → anulación de recibo
//    modalEditRecibo() / updateRecibo() → edición de recibo
// ============================================================

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
  data.renta_base   = parseFloat(data.renta_base)||0;
  data.importe_iva  = parseFloat(data.importe_iva)||0;
  data.importe_irpf = parseFloat(data.importe_irpf)||0;
  data.importe_total = parseFloat(data.importe_total)||0;
  data.estado = 'pendiente';
  data.fecha_creacion = new Date().toISOString();

  // Obtener número atómico del servidor (reinicio mensual, sin duplicados)
  const empresa  = DB.getEmpresa();
  const prefix   = empresa?.prefijo_recibos || 'REC';
  const fechaEm  = data.fecha_emision || new Date().toISOString().slice(0, 10);
  const periodo  = fechaEm.replace(/-/g, '').slice(0, 6);
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
        <input id="num-recibo-precalc" value="Se asignará automáticamente" readonly
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
  // Registrar en log de auditoría (fire-and-forget)
  const _inqN = (DB.getItem('inquilinos', r.inquilino_id) || {}).nombre || '';
  registrarActividad('cobro', 'recibos', r.id,
    r.numero_recibo + ' — ' + _inqN + ' — ' + fmtMoney(totalPagado));
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
  // Registrar en log de auditoría (fire-and-forget)
  const _inqAnulP = (DB.getItem('inquilinos', r.inquilino_id) || {}).nombre || '';
  registrarActividad('anulacion_pago', 'recibos', reciboId,
    r.numero_recibo + ' — ' + _inqAnulP);
  toast('Cobro anulado');
  closeModalForce();
  renderRecibos(navParams);
}

function marcarCobrado(id) { modalDarCobro(id); }

// ===========================
// ANULAR RECIBO — anulación lógica (RD 1619/2012 aplicado por analogía a facturas)
//   · Nunca se borra físicamente: el recibo original queda con estado 'anulado'.
//   · Si el recibo TODAVÍA NO tiene factura: se genera un recibo rectificativo
//     RER-AAAAMM-NNNNN (importes negados) que compensa al original en los totales.
//       — Si además el recibo ya estaba COBRADO (total o parcialmente), no se anula
//         "sin más": se pregunta explícitamente si se quiere devolver el cobro, y el
//         rectificativo generado incluye ese cobro en negativo (campo `pagos` e
//         `importe_pagado`), dejando trazabilidad de la devolución. Si el usuario
//         cancela esa pregunta, no se modifica nada (ni estado ni RER ni cobros).
//   · Si el recibo tiene una factura EMITIDA asociada: ya no basta con anular el
//     recibo dejando la factura tal cual — hay que rectificar antes la factura
//     (genera RET-AAAAMM-NNNNN reutilizando anularFacturaConRectificativa() de
//     facturas.js) y solo si eso sale bien se anula el recibo. Nunca se genera un
//     RER en este caso: la corrección fiscal ya queda hecha con el RET de la
//     factura, y generar además un RER duplicaría la compensación.
//   · Si la factura asociada YA está rectificada (o anulada): la corrección fiscal
//     ya existe de antes, así que es seguro anular el recibo sin tocar de nuevo la
//     factura ni generar ningún documento adicional (evita un RET duplicado).
// ===========================
async function anularRecibo(id) {
  const r = DB.getItem('recibos', id);
  if (!r) return;
  if (r.estado === 'anulado') { toast('Este recibo ya está anulado.', 'info'); return; }
  if (r.estado === 'rectificativo') { toast('Los recibos rectificativos no se pueden anular.', 'info'); return; }

  // Se busca por `factura_id` (el puntero que mantiene actualizado
  // generarFacturaDesdeRecibo()) y no con .find() sobre todas las facturas: un
  // mismo recibo puede tener más de una factura a lo largo del tiempo (p. ej. una
  // sustitutiva tras rectificar la anterior), y factura_id es la única forma
  // fiable de saber cuál es la vigente.
  const facturaAsoc = r.factura_id ? DB.getItem('facturas', r.factura_id) : null;

  if (facturaAsoc && facturaAsoc.estado === 'emitida') {
    // Regla nueva: un recibo con factura EMITIDA no puede anularse sin rectificar
    // antes la factura. Se pregunta explícitamente porque implica anular también
    // un documento fiscal (la factura), no solo el recibo.
    if (!confirm(
      'Este recibo tiene una factura emitida asociada. Para anular el recibo es necesario ' +
      'anular también la factura y generar su factura rectificativa. ¿Desea anular la factura y continuar?'
    )) {
      toast('No se ha anulado el recibo. Para anular un recibo con factura emitida es necesario anular también la factura y generar su factura rectificativa.', 'info');
      return;
    }

    // Se rectifica primero la factura (genera RET). Solo si esto termina bien se
    // anula después el recibo: así nunca queda un recibo anulado con la factura
    // todavía "emitida" a medias, ni una anulación parcial.
    const resultadoFactura = await anularFacturaConRectificativa(facturaAsoc.id, {
      reciboId: r.id, reciboNumero: r.numero_recibo
    });
    if (!resultadoFactura.ok) {
      toast('No se ha anulado el recibo porque no se ha podido anular correctamente la factura asociada.', 'error');
      return;
    }

    // No se genera RER: la corrección fiscal ya la aporta el RET recién creado
    // sobre la factura: un RER adicional duplicaría la compensación.
    r.estado = 'anulado';
    r.notas = (r.notas ? r.notas + '\n' : '') +
      'Recibo anulado. Factura ' + facturaAsoc.numero_factura + ' rectificada por ' + resultadoFactura.numeroRectificativa + '.';
    await DB.save('recibos', r);

    const _inqAnul = (DB.getItem('inquilinos', r.inquilino_id) || {}).nombre || '';
    registrarActividad('anulacion_recibo', 'recibos', id, r.numero_recibo + ' — ' + _inqAnul + ' — factura rectificada ' + resultadoFactura.numeroRectificativa);

    toast('Factura anulada con rectificativa y recibo anulado correctamente.', 'success');
    renderRecibos();
    return;
  }

  if (facturaAsoc) {
    // La factura asociada ya está rectificada (o anulada): la corrección fiscal
    // ya existe de antes, así que es seguro anular el recibo sin generar ningún
    // documento adicional (ni RER ni un segundo RET sobre la misma factura).
    if (!confirm(
      '¿Anular este recibo?\n\n' +
      'La factura asociada (' + facturaAsoc.numero_factura + ') ya está ' +
      (facturaAsoc.estado === 'rectificada' ? 'rectificada' : 'anulada') + '. ' +
      'El recibo se anulará sin generar ningún documento adicional.'
    )) return;

    r.estado = 'anulado';
    await DB.save('recibos', r);
    const _inqAnul2 = (DB.getItem('inquilinos', r.inquilino_id) || {}).nombre || '';
    registrarActividad('anulacion_recibo', 'recibos', id, r.numero_recibo + ' — ' + _inqAnul2);
    toast('Recibo anulado — la factura ya estaba ' + facturaAsoc.estado + '.', 'info');
    renderRecibos();
    return;
  }

  // El recibo tiene cobros (totales o parciales) si su estado es 'cobrado'/'parcial'
  // o si el array de pagos suma un importe mayor que cero. En ese caso NO se puede
  // anular "sin más": hay que preguntar explícitamente si se quiere devolver el cobro,
  // porque el rectificativo que se genera a continuación va a reflejar esa devolución
  // en negativo. Si el recibo está pendiente (sin cobros), se mantiene el comportamiento
  // anterior: un único aviso genérico y no se pregunta nada sobre devoluciones.
  const totalPagadoActual = (r.pagos || []).reduce((s, p) => s + (parseFloat(p.importe) || 0), 0);
  const estaCobrado = r.estado === 'cobrado' || r.estado === 'parcial' || totalPagadoActual > 0;

  if (estaCobrado) {
    if (!confirm(
      'El recibo ya está cobrado. Para anularlo es necesario devolver el cobro en el recibo ' +
      'rectificativo. ¿Desea continuar y generar la devolución?'
    )) {
      // Cancelación explícita: no se toca el recibo ni se genera nada. Se sale
      // aquí mismo, antes de reservar número ni de tocar la BD.
      toast('Anulación cancelada. No se ha modificado el recibo.', 'info');
      return;
    }
  } else {
    if (!confirm(
      '¿Anular este recibo?\n\n' +
      'Al no tener factura emitida, se generará automáticamente un recibo rectificativo ' +
      '(serie RER) que anula sus efectos.'
    )) return;
  }

  // Reservar número del recibo rectificativo (atómico, reinicio mensual, mismo servicio que REC/FAC)
  const hoy     = new Date().toISOString().split('T')[0];
  const periodo = hoy.replace(/-/g, '').slice(0, 6);
  let rerInfo;
  try {
    rerInfo = await nextNumeroDoc('RER', periodo, 'RER');
  } catch (e) {
    toast('No se pudo generar el número de recibo rectificativo. Inténtalo de nuevo.', 'error');
    return;
  }

  const rectificativo = {
    contrato_id  : r.contrato_id,
    inquilino_id : r.inquilino_id,
    inmueble_id  : r.inmueble_id,
    numero_recibo: rerInfo.numero,
    numero_seq   : rerInfo.seq,
    fecha_emision: hoy,
    fecha_limite : hoy,
    concepto_periodo: 'Rectificación de: ' + (r.concepto_periodo || ''),
    renta_base   : -parseFloat(r.renta_base    || 0),
    importe_iva  : -parseFloat(r.importe_iva   || 0),
    importe_irpf : -parseFloat(r.importe_irpf  || 0),
    importe_total: -parseFloat(r.importe_total || 0),
    // Si el recibo tenía cobros, el rectificativo refleja la devolución con el
    // mismo importe en negativo (mismo patrón que las facturas rectificativas RET),
    // dejando un "pago" de devolución trazable dentro del propio array de pagos.
    importe_pagado: estaCobrado ? -totalPagadoActual : 0,
    pagos        : estaCobrado
      ? [{ fecha: hoy, importe: -totalPagadoActual, metodo: 'Devolución', cuenta: '' }]
      : [],
    estado       : 'rectificativo',
    recibo_rectificado_id: r.id,
    notas        : estaCobrado
      ? 'Recibo rectificativo de ' + r.numero_recibo + '. Anulación total con devolución de cobro de ' + fmtMoney(totalPagadoActual) + '.'
      : 'Recibo rectificativo de ' + r.numero_recibo + '. Anulación total.',
    fecha_creacion: new Date().toISOString(),
  };
  const saved = await DB.save('recibos', rectificativo);
  if (!saved || saved.error) { toast('Error al crear el recibo rectificativo.', 'error'); return; }

  // Marcar el original como anulado con referencia al rectificativo.
  // confirmar_devolucion viaja al backend (aunque no sea una columna de la tabla)
  // para que la validación server-side sepa que el usuario ya confirmó la
  // devolución del cobro y no rechace la operación (ver validarDatos() en api.php).
  r.estado = 'anulado';
  r.confirmar_devolucion = estaCobrado;
  r.notas = (r.notas ? r.notas + '\n' : '') +
    'Rectificado por: ' + rerInfo.numero + ' · emitido el ' + hoy + '.' +
    (estaCobrado ? ' Devolución de ' + fmtMoney(totalPagadoActual) + ' registrada.' : '');
  await DB.save('recibos', r);

  const _inqAnul = (DB.getItem('inquilinos', r.inquilino_id) || {}).nombre || '';
  registrarActividad('anulacion_recibo', 'recibos', id, r.numero_recibo + ' — ' + _inqAnul);

  toast(estaCobrado
    ? 'Recibo anulado correctamente y devolución registrada en ' + rerInfo.numero + '.'
    : 'Creado ' + rerInfo.numero + ' · ' + r.numero_recibo + ' queda anulado.', 'success');
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
