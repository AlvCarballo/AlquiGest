// ===========================
// GENERAR RECIBOS EN LOTE
// Permite crear de golpe todos los recibos de un mes para uno o varios contratos.
// Flujo: elegir mes/año/ámbito → previsualizarLote() → generarLote().
// previsualizarLote() detecta duplicados (recibos del mismo contrato y período
// que ya existen) y los marca como "Ya generado" para omitirlos.
// ===========================
function renderGenerarRecibos() {
  const fincas = DB.get('fincas');
  const inmuebles = DB.get('inmuebles');
  document.getElementById('header-actions').innerHTML = '';
  document.getElementById('content').innerHTML = `
    <div class="card" style="max-width:700px;margin:0 auto">
      <div class="card-header"><div class="card-title">Generar Recibos en Lote</div></div>
      <div class="card-body">
        <div class="form-grid form-grid-2" style="gap:16px">
          <div class="form-group">
            <label>Mes</label>
            <select id="gen-mes" style="padding:8px">
              ${['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre']
                .map((m,i) => `<option value="${i+1}" ${i+1===new Date().getMonth()+1?'selected':''}>${m}</option>`).join('')}
            </select>
          </div>
          <div class="form-group">
            <label>Año</label>
            <input type="number" id="gen-anyo" value="${new Date().getFullYear()}" min="2000" max="2099" style="padding:8px">
          </div>
          <div class="form-group">
            <label>Ámbito</label>
            <select id="gen-ambito" onchange="cambiarAmbitoGen()" style="padding:8px">
              <option value="todo">Toda la cartera</option>
              <option value="finca">Por finca/edificio</option>
              <option value="piso">Por piso concreto</option>
            </select>
          </div>
          <div class="form-group" id="gen-finca-wrap" style="display:none">
            <label>Finca</label>
            <select id="gen-finca" onchange="cambiarFincaGen()" style="padding:8px">
              <option value="">-- Selecciona --</option>
              ${fincas.map(f=>`<option value="${f.id}">${f.nombre}</option>`).join('')}
            </select>
          </div>
          <div class="form-group" id="gen-piso-wrap" style="display:none">
            <label>Piso</label>
            <select id="gen-piso" style="padding:8px">
              <option value="">-- Selecciona finca primero --</option>
            </select>
          </div>
        </div>
        <div style="margin-top:16px;display:flex;gap:12px">
          <button class="btn btn-secondary" onclick="previsualizarLote()">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            Previsualizar
          </button>
          <button class="btn btn-primary" id="btn-generar-lote" style="display:none" onclick="generarLote()">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Generar recibos
          </button>
        </div>
        <div id="gen-preview" style="margin-top:20px"></div>
      </div>
    </div>
  `;
}

function cambiarAmbitoGen() {
  const v = document.getElementById('gen-ambito').value;
  document.getElementById('gen-finca-wrap').style.display = (v==='finca'||v==='piso') ? '' : 'none';
  document.getElementById('gen-piso-wrap').style.display = v==='piso' ? '' : 'none';
}

function cambiarFincaGen() {
  const fincaId = parseInt(document.getElementById('gen-finca').value);
  const inms = DB.get('inmuebles').filter(i => i.finca_id === fincaId);
  const sel = document.getElementById('gen-piso');
  sel.innerHTML = '<option value="">-- Selecciona --</option>' +
    inms.map(i => `<option value="${i.id}">${getInmuebleNombre(i)}</option>`).join('');
}

function _getContratosParaLote() {
  const ambito = document.getElementById('gen-ambito').value;
  const allContratos = DB.get('contratos').filter(c => c.estado === 'activo');
  if (ambito === 'todo') return allContratos;
  const fincaId = parseInt(document.getElementById('gen-finca').value);
  if (!fincaId) { toast('Selecciona una finca', 'error'); return null; }
  const inmsIds = DB.get('inmuebles').filter(i => i.finca_id === fincaId).map(i => i.id);
  if (ambito === 'finca') return allContratos.filter(c => inmsIds.includes(c.inmueble_id));
  const pisoId = parseInt(document.getElementById('gen-piso').value);
  if (!pisoId) { toast('Selecciona un piso', 'error'); return null; }
  return allContratos.filter(c => c.inmueble_id === pisoId);
}

function previsualizarLote() {
  const contratos = _getContratosParaLote();
  if (!contratos) return;
  const mes = parseInt(document.getElementById('gen-mes').value);
  const anyo = parseInt(document.getElementById('gen-anyo').value);
  const inquilinos = DB.get('inquilinos');
  const inmuebles = DB.get('inmuebles');
  const periodo = periodoLabel(mes, anyo);

  // Comprueba qué contratos ya tienen recibo para este período para no duplicar.
  // Un recibo anulado o rectificativo no cuenta como "ya generado": anular un
  // recibo debe permitir volver a emitir uno nuevo para el mismo período.
  const recibosExist = DB.get('recibos');
  const rows = contratos.map(c => {
    const inq = inquilinos.find(i => i.id === c.inquilino_id);
    const inm = inmuebles.find(i => i.id === c.inmueble_id);
    const yaExiste = recibosExist.some(r => r.contrato_id === c.id && r.concepto_periodo === periodo
      && r.estado !== 'anulado' && r.estado !== 'rectificativo');
    return { c, inq, inm, yaExiste };
  });

  const html = `
    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead><tr style="background:var(--gray-100)">
        <th style="text-align:left;padding:8px">Inquilino</th>
        <th style="text-align:left;padding:8px">Inmueble</th>
        <th style="text-align:right;padding:8px">Renta</th>
        <th style="text-align:center;padding:8px">Estado</th>
      </tr></thead>
      <tbody>
        ${rows.map(({c,inq,inm,yaExiste}) => `<tr style="border-bottom:1px solid var(--gray-200)${yaExiste?';opacity:.5':''}">
          <td style="padding:8px">${inq?.nombre||'?'}</td>
          <td style="padding:8px;font-size:12px">${inm?getInmuebleNombre(inm):'-'}</td>
          <td style="padding:8px;text-align:right">${fmtMoney(c.renta_base||0)}</td>
          <td style="padding:8px;text-align:center">${yaExiste?'<span style="color:#9ca3af;font-size:11px">Ya generado</span>':'<span style="color:#15803d;font-size:11px">✓ Pendiente</span>'}</td>
        </tr>`).join('')}
      </tbody>
    </table>
    <p style="margin-top:12px;font-size:13px;color:var(--gray-500)">
      ${rows.filter(r=>!r.yaExiste).length} recibos nuevos · ${rows.filter(r=>r.yaExiste).length} ya existentes (se omitirán)
    </p>`;

  document.getElementById('gen-preview').innerHTML = html;
  document.getElementById('btn-generar-lote').style.display = rows.some(r=>!r.yaExiste) ? '' : 'none';
}

async function generarLote() {
  const contratos = _getContratosParaLote();
  if (!contratos) return;
  const mes = parseInt(document.getElementById('gen-mes').value);
  const anyo = parseInt(document.getElementById('gen-anyo').value);
  const periodo = periodoLabel(mes, anyo);
  const recibosExist = DB.get('recibos');
  const empresa = DB.getEmpresa() || {};
  const inquilinos = DB.get('inquilinos');
  const inmuebles = DB.get('inmuebles');

  // Filtrar solo contratos que aún no tienen recibo para este período.
  // Un recibo anulado/rectificativo no bloquea la remisión de uno nuevo (ver previsualizarLote()).
  const contratosAGenerar = contratos.filter(c =>
    !recibosExist.some(r => r.contrato_id === c.id && r.concepto_periodo === periodo
      && r.estado !== 'anulado' && r.estado !== 'rectificativo')
  );
  const total = contratosAGenerar.length;

  if (total === 0) {
    toast('Todos los recibos de este período ya están generados', 'info');
    return;
  }

  // Deshabilitar el botón durante la generación para evitar doble clic
  const btnGenerar = document.getElementById('btn-generar-lote');
  if (btnGenerar) btnGenerar.disabled = true;

  const prevEl = document.getElementById('gen-preview');
  let creados = 0;
  const recibosGeneradosIds = [];

  for (const c of contratosAGenerar) {
    const inq = inquilinos.find(i => i.id === c.inquilino_id);
    const inm = inmuebles.find(i => i.id === c.inmueble_id);
    const n = creados + 1;
    const pct = Math.round((creados / total) * 100);

    // Actualizar barra de progreso antes de cada guardado
    if (prevEl) prevEl.innerHTML = `
      <div style="padding:16px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px">
        <div style="margin-bottom:8px;font-size:13px">
          Generando recibo <strong>${n}</strong> de <strong>${total}</strong>:
          <em>${inq ? esc(inq.nombre) : '?'}</em>
          ${inm ? '&mdash; ' + esc(getInmuebleNombre(inm)) : ''}
        </div>
        <div style="height:10px;background:var(--gray-200);border-radius:5px;overflow:hidden">
          <div style="height:100%;background:var(--blue);border-radius:5px;width:${pct}%;transition:width .3s"></div>
        </div>
        <div style="text-align:center;font-size:12px;color:var(--gray-500);margin-top:4px">${pct}%</div>
      </div>`;

    const prefix     = empresa.prefijo_recibos || 'REC';
    // Numeración anual: el año sale del período (periodo_desde) del propio recibo,
    // no del mes — coincide con `anyo`, el mismo valor usado para periodoPrimerDia().
    const periodoDoc = String(anyo);
    let seqInfo;
    try {
      seqInfo = await nextNumeroDoc('REC', periodoDoc, prefix);
    } catch (e) {
      toast(`Error al generar número para recibo ${n}/${total}. Proceso detenido.`, 'error');
      break;
    }
    const iva_imp  = (c.renta_base||0) * (c.iva_pct||0) / 100;
    const irpf_imp = (c.renta_base||0) * (c.irpf_pct||0) / 100;
    const totalImporte = (c.renta_base||0) + iva_imp - irpf_imp;
    const fechaEmision = periodoPrimerDia(mes, anyo);
    const diaPago = c.dia_pago || 5;
    const fechaLimite = fmtLocalISO(new Date(anyo, mes - 1, diaPago));
    const resultado = await DB.save('recibos', {
      contrato_id: c.id,
      inquilino_id: c.inquilino_id,
      inmueble_id: c.inmueble_id,
      numero_recibo: seqInfo.numero,
      numero_seq: seqInfo.seq,
      fecha_emision: fechaEmision,
      periodo_desde: periodoPrimerDia(mes, anyo),
      periodo_hasta: periodoUltimoDia(mes, anyo),
      fecha_limite: fechaLimite,
      concepto_periodo: periodo,
      renta_base: c.renta_base||0,
      importe_iva: iva_imp,
      importe_irpf: irpf_imp,
      importe_total: totalImporte,
      importe_pagado: 0,
      pagos: [],
      estado: 'pendiente',
      fecha_creacion: new Date().toISOString()
    });
    // Guardar el id del recibo generado para el envío masivo de emails
    if (resultado && resultado.id) recibosGeneradosIds.push(resultado.id);
    creados++;
  }

  // Rehabilitar el botón y ocultarlo
  if (btnGenerar) { btnGenerar.disabled = false; btnGenerar.style.display = 'none'; }

  toast(`${creados} recibo${creados !== 1 ? 's' : ''} generado${creados !== 1 ? 's' : ''} correctamente`, 'success');

  // Registrar generación del lote en el log de auditoría (fire-and-forget)
  registrarActividad('generacion_lote', 'recibos', null,
    creados + ' recibo' + (creados !== 1 ? 's' : '') + ' — período: ' + periodo);

  // Mostrar resumen con opción de envío por email en lugar de navegar automáticamente
  if (prevEl) prevEl.innerHTML = `
    <div style="padding:16px;background:var(--blue-light);border:1px solid #93c5fd;border-radius:8px">
      <strong>${creados} recibo${creados !== 1 ? 's' : ''} generado${creados !== 1 ? 's' : ''} para el período ${esc(periodo)}.</strong>
      <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn-secondary" onclick="navigate('recibos')">Ver recibos</button>
        <button class="btn btn-primary" onclick="enviarLotePorEmail(${JSON.stringify(recibosGeneradosIds)})">
          Enviar todos por email
        </button>
      </div>
    </div>`;
}

// ===========================
// ENVÍO MASIVO DE RECIBOS POR EMAIL
// Llama a email.php secuencialmente para cada recibo del lote generado.
// Solo envía a inquilinos con email configurado. Muestra progreso y resumen final.
// ===========================
async function enviarLotePorEmail(ids) {
  const empresa = DB.getEmpresa() || {};
  if (!empresa.gmail_user || !empresa.gmail_pass) {
    return toast('Configura el email SMTP en Mi Empresa antes de enviar', 'error');
  }

  // Obtener los recibos y filtrar los que tienen inquilino con email
  const recibos = ids.map(id => DB.getItem('recibos', id)).filter(Boolean);
  const conEmail = recibos.filter(r => {
    const inq = DB.getItem('inquilinos', r.inquilino_id);
    return inq && inq.email;
  });
  const sinEmail = recibos.length - conEmail.length;

  if (!conEmail.length) {
    return toast('Ningún inquilino tiene email configurado', 'error');
  }

  const msgSinEmail = sinEmail > 0
    ? `${sinEmail} recibo${sinEmail !== 1 ? 's' : ''} se omiten (inquilinos sin email).\n`
    : '';
  const confirmar = confirm(
    `Se enviarán ${conEmail.length} email${conEmail.length !== 1 ? 's' : ''}.\n${msgSinEmail}¿Continuar?`
  );
  if (!confirmar) return;

  const prevEl = document.getElementById('gen-preview');
  let enviados = 0;
  const errores = [];
  const total = conEmail.length;

  for (const recibo of conEmail) {
    const inq = DB.getItem('inquilinos', recibo.inquilino_id);
    const pct = Math.round((enviados / total) * 100);
    if (prevEl) prevEl.innerHTML = `
      <div style="padding:16px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px">
        <div>Enviando email ${enviados + 1} de ${total}: <strong>${esc(inq ? inq.nombre : '?')}</strong>...</div>
        <div style="height:8px;background:var(--gray-200);border-radius:4px;margin-top:8px;overflow:hidden">
          <div style="height:100%;background:var(--blue);border-radius:4px;width:${pct}%;transition:width .3s"></div>
        </div>
      </div>`;

    try {
      const resp = await fetch('assets/php/email.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ recibo_id: recibo.id })
      });
      const data = await resp.json();
      if (data.ok) {
        enviados++;
      } else {
        errores.push(`${inq ? inq.nombre : '?'}: ${data.error || 'Error desconocido'}`);
      }
    } catch (e) {
      errores.push(`${inq ? inq.nombre : '?'}: Error de conexión`);
    }
  }

  // Resumen final
  let resumenHtml = `<div style="padding:16px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px">
    <strong>${enviados} email${enviados !== 1 ? 's' : ''} enviado${enviados !== 1 ? 's' : ''} correctamente.</strong>`;
  if (sinEmail > 0) {
    resumenHtml += `<br><span style="color:var(--gray-500);font-size:13px">${sinEmail} omitido${sinEmail !== 1 ? 's' : ''} (sin email configurado).</span>`;
  }
  if (errores.length) {
    resumenHtml += `<br><span style="color:var(--red);font-size:13px">Errores (${errores.length}):</span>
      <ul style="margin-top:6px;font-size:12px;color:var(--red)">`;
    errores.forEach(function(e) { resumenHtml += `<li>${esc(e)}</li>`; });
    resumenHtml += '</ul>';
  }
  resumenHtml += `<br><button class="btn btn-secondary" style="margin-top:8px" onclick="navigate('recibos')">Ver recibos</button></div>`;
  if (prevEl) prevEl.innerHTML = resumenHtml;

  if (enviados > 0) toast(`${enviados} email${enviados !== 1 ? 's' : ''} enviado${enviados !== 1 ? 's' : ''}`, 'success');
}

// ===========================
// INFORMES EXCEL