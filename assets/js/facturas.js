// ===========================

// Badge visual para el estado de una factura
function badgeEstadoFactura(estado) {
  const map    = { emitida:'badge-green', anulada:'badge-red', rectificada:'badge-blue' };
  const labels = { emitida:'Emitida', anulada:'Anulada', rectificada:'Rectificada' };
  return `<span class="badge ${map[estado]||'badge-blue'}">${labels[estado]||estado}</span>`;
}

// ===========================
// GENERACIÓN DE NÚMERO DE FACTURA
// FAC-YYYYMM-NNNNN donde YYYYMM es el mes de hoy.
// El contador se reinicia a 00001 el primer día de cada mes.
// Delega en nextNumeroDoc() (atómico, sin duplicados).
// ===========================
async function generarNumeroFacturaDesdeRecibo() {
  const hoy    = new Date().toISOString().split('T')[0];
  const periodo = hoy.replace(/-/g, '').slice(0, 6);
  const info    = await nextNumeroDoc('FAC', periodo, 'FAC');
  return { numeroFactura: info.numero, numeroSeq: info.seq };
}

// ===========================
// GENERAR FACTURA DESDE RECIBO
// Valida todos los requisitos legales antes de crear la factura.
// Copia los datos del recibo, empresa e inquilino en el momento de emisión.
// ===========================
async function generarFacturaDesdeRecibo(reciboId) {
  const r = DB.getItem('recibos', reciboId);
  if (!r) { toast('Recibo no encontrado.', 'error'); return; }

  // No se puede facturar un recibo anulado, ni un recibo rectificativo (documento de corrección interno)
  if (r.estado === 'anulado') {
    toast('No se puede generar factura de un recibo anulado.', 'error');
    return;
  }
  if (r.estado === 'rectificativo') {
    toast('No se puede generar factura de un recibo rectificativo.', 'error');
    return;
  }

  // Un recibo solo puede tener una factura vigente; si la anterior fue rectificada se permite emitir una nueva
  const facturaExistente = DB.get('facturas').find(f => f.recibo_id === r.id);
  if (facturaExistente) {
    if (facturaExistente.estado !== 'rectificada') {
      toast('Este recibo ya tiene la factura ' + facturaExistente.numero_factura + ' asociada.', 'error');
      return;
    }
    if (!confirm(
      'La factura anterior de este recibo (' + facturaExistente.numero_factura + ') fue rectificada.\n\n' +
      '¿Deseas emitir una nueva factura sustitutiva para el mismo recibo?'
    )) return;
  }

  // Cargar datos relacionados para validación y copia
  const empresa    = DB.getEmpresa() || {};
  const contrato   = DB.getItem('contratos', r.contrato_id) || {};
  const inquilino  = DB.getItem('inquilinos', r.inquilino_id) || {};
  const inmueble   = DB.getItem('inmuebles', r.inmueble_id) || {};
  const finca      = DB.getItem('fincas', inmueble.finca_id) || {};

  // Validaciones legales obligatorias antes de emitir
  const errores = [];
  if (!empresa.nombre)    errores.push('La empresa no tiene nombre configurado.');
  if (!empresa.cif)       errores.push('La empresa no tiene NIF/CIF configurado.');
  if (!empresa.direccion) errores.push('La empresa no tiene dirección configurada.');
  if (!inquilino.nombre)  errores.push('El inquilino no tiene nombre en su ficha.');
  if (!inquilino.nif)     errores.push('El inquilino no tiene NIF/NIE/CIF en su ficha.');
  if (!r.numero_recibo)   errores.push('El recibo no tiene número asignado.');
  if (!r.fecha_emision)   errores.push('El recibo no tiene fecha de emisión.');
  if (r.importe_total === undefined || r.importe_total === null) {
    errores.push('El recibo no tiene importe total definido.');
  }

  if (errores.length) {
    openModal('No se puede generar la factura',
      `<div style="color:#991b1b">
         <p style="margin-bottom:10px;font-weight:600">Faltan datos obligatorios para emitir la factura:</p>
         <ul style="padding-left:20px">${errores.map(e => `<li style="margin-bottom:4px">${esc(e)}</li>`).join('')}</ul>
         <p style="margin-top:12px;font-size:12px;color:#6b7280">Completa estos datos en Mi Empresa o en la ficha del inquilino y vuelve a intentarlo.</p>
       </div>`,
      '<button class="btn btn-secondary" onclick="closeModal()">Cerrar</button>');
    return;
  }

  // Generar el número de factura (atómico, reinicio mensual, sin duplicados)
  let numeroFactura, numeroSeq;
  try {
    ({ numeroFactura, numeroSeq } = await generarNumeroFacturaDesdeRecibo());
  } catch (e) {
    toast('No se pudo generar el número de factura. Inténtalo de nuevo.', 'error');
    return;
  }

  // Construir la dirección completa del inmueble
  const dirInmueble = [finca.calle, finca.numero, inmueble.planta, inmueble.puerta]
    .filter(Boolean).join(' ') +
    (finca.cp       ? ', CP ' + finca.cp       : '') +
    (finca.municipio ? ', ' + finca.municipio   : '') +
    (finca.provincia ? ' (' + finca.provincia + ')' : '');

  // Porcentajes de IVA e IRPF del contrato (0 si no tiene)
  const ivaPct  = parseFloat(contrato.iva_pct)  || 0;
  const irpfPct = parseFloat(contrato.irpf_pct) || 0;

  // Fecha de hoy para fecha_emision de la factura
  const hoy = new Date().toISOString().split('T')[0];

  // Construir el objeto factura copiando todos los datos en este momento exacto
  const factura = {
    recibo_id    : r.id,
    contrato_id  : r.contrato_id  || 0,
    inquilino_id : r.inquilino_id || 0,
    inmueble_id  : r.inmueble_id  || 0,

    numero_factura : numeroFactura,
    numero_seq     : numeroSeq,
    serie          : 'FAC',
    tipo_factura   : 'F1',

    // La fecha de emisión es hoy; la fecha de operación es la del recibo
    fecha_emision   : hoy,
    fecha_operacion : r.fecha_emision || hoy,
    periodo_desde   : r.periodo_desde || null,
    periodo_hasta   : r.periodo_hasta || null,

    // Datos del emisor (empresa) — copia congelada
    emisor_nombre    : empresa.nombre    || '',
    emisor_nif       : empresa.cif       || '',
    emisor_direccion : empresa.direccion || '',
    emisor_cp        : empresa.cp        || '',
    emisor_municipio : empresa.municipio || '',
    emisor_provincia : empresa.provincia || '',
    emisor_email     : empresa.email     || '',
    emisor_telefono  : empresa.telefono  || '',
    emisor_iban      : empresa.iban      || '',

    // Datos del cliente (inquilino) — copia congelada
    cliente_nombre    : inquilino.nombre    || '',
    cliente_nif       : inquilino.nif       || '',
    cliente_direccion : inquilino.direccion || '',
    cliente_cp        : inquilino.cp        || '',
    cliente_municipio : inquilino.municipio || '',
    cliente_provincia : inquilino.provincia || '',
    cliente_email     : inquilino.email     || '',

    inmueble_direccion : dirInmueble,
    concepto           : 'Alquiler del inmueble — ' + (r.concepto_periodo || ''),
    conceptos_extra    : r.conceptos_extra || '',
    notas              : r.notas || '',

    // Importes copiados exactamente del recibo — no se recalculan
    base_imponible : parseFloat(r.renta_base)    || 0,
    iva_pct        : ivaPct,
    importe_iva    : parseFloat(r.importe_iva)   || 0,
    irpf_pct       : irpfPct,
    importe_irpf   : parseFloat(r.importe_irpf)  || 0,
    importe_total  : parseFloat(r.importe_total) || 0,

    estado         : 'emitida',

    // Campos VERI*FACTU preparados para uso futuro (no se envían todavía a AEAT)
    hash_factura          : null,
    hash_anterior         : null,
    qr_url                : null,
    verifactu_estado      : 'no_enviado',
    verifactu_respuesta   : null,
    factura_rectificada_id: null,

    fecha_creacion : new Date().toISOString(),
  };

  // Guardar la factura en la base de datos
  const saved = await DB.save('facturas', factura);
  if (!saved || saved.error) {
    toast('Error al guardar la factura. Inténtalo de nuevo.', 'error');
    return;
  }

  // Actualizar el recibo con el id de la factura generada para enlace rápido
  r.factura_id = saved.id;
  await DB.save('recibos', r);

  // Registrar generación de factura en el log de auditoría (fire-and-forget)
  registrarActividad('factura_generada', 'facturas', saved.id,
    numeroFactura + ' — ' + (inquilino.nombre || '') + ' — ' + fmtMoney(factura.importe_total));

  toast('Factura ' + numeroFactura + ' generada correctamente.', 'success');

  // Si VERI*FACTU está activo, enviar a la AEAT en segundo plano (no bloqueante)
  if (getConfigVar('verifactu_activo') === '1') {
    toast('Enviando a AEAT…', 'info');
    fetch('assets/php/verifactu.php?action=enviar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ factura_id: saved.id })
    }).then(rr => rr.json()).then(res => {
      if (res.ok) {
        toast('Factura registrada en AEAT. QR generado.', 'success');
        DB._cache = null; // forzar recarga para obtener hash y qr_url
      } else {
        toast('Factura guardada. Error AEAT: ' + (res.error || ''), 'error');
      }
    }).catch(() => toast('Factura guardada. No se pudo contactar con AEAT.', 'error'));
  }

  navigate('facturas');
}

// ===========================
// ANULAR FACTURA CON RECTIFICATIVA (núcleo reutilizable) — RD 1619/2012, art. 15
// Genera la factura rectificativa RET-AAAAMM-NNNNN (importes negados, tipo_factura
// 'R1') y marca la original como 'rectificada'. No pide confirmación al usuario
// (la decide quien la llama) ni gestiona la interfaz: se reutiliza tanto desde el
// botón "Anular factura" del módulo Facturas (anularFactura(), más abajo) como
// desde la anulación de un recibo con factura emitida asociada
// (anularRecibo() en recibos-cobro.js), para no duplicar la lógica de generación
// de la rectificativa en dos sitios.
// opciones.reciboId / opciones.reciboNumero: si la llamada viene de anular un
// recibo, se anota en las notas de ambas facturas para dejar trazabilidad.
// Devuelve { ok:true, rectificativaId, numeroRectificativa } o { ok:false, error }.
// ===========================
async function anularFacturaConRectificativa(id, opciones = {}) {
  const f = DB.getItem('facturas', id);
  if (!f) return { ok: false, error: 'Factura no encontrada.' };
  if (f.serie === 'RET' || (f.tipo_factura || '').startsWith('R')) {
    return { ok: false, error: 'Las facturas rectificativas no se pueden anular. Si hay un error, consulta a tu asesor fiscal.' };
  }
  if (f.estado === 'anulada' || f.estado === 'rectificada') {
    return { ok: false, error: 'Esta factura ya está anulada o rectificada.' };
  }

  // Número de la rectificativa: serie RET-YYYYMM-NNNNN (atómico, reinicio mensual)
  const hoy    = new Date().toISOString().split('T')[0];
  const periodo = hoy.replace(/-/g, '').slice(0, 6);
  let numRect, sigSeq;
  try {
    const rectInfo = await nextNumeroDoc('RET', periodo, 'RET');
    numRect = rectInfo.numero;
    sigSeq  = rectInfo.seq;
  } catch (e) {
    return { ok: false, error: 'No se pudo generar el número de factura rectificativa. Inténtalo de nuevo.' };
  }

  const notaOrigen = opciones.reciboId
    ? ' (anulación solicitada desde el recibo ' + (opciones.reciboNumero || ('#' + opciones.reciboId)) + ')'
    : '';

  const rectificativa = {
    recibo_id             : null,
    contrato_id           : f.contrato_id,
    inquilino_id          : f.inquilino_id,
    inmueble_id           : f.inmueble_id,
    factura_rectificada_id: f.id,

    numero_factura : numRect,
    numero_seq     : sigSeq,
    serie          : 'RET',
    tipo_factura   : 'R1',

    fecha_emision   : hoy,
    fecha_operacion : f.fecha_emision,
    periodo_desde   : f.periodo_desde,
    periodo_hasta   : f.periodo_hasta,

    emisor_nombre    : f.emisor_nombre,    emisor_nif       : f.emisor_nif,
    emisor_direccion : f.emisor_direccion, emisor_cp        : f.emisor_cp,
    emisor_municipio : f.emisor_municipio, emisor_provincia : f.emisor_provincia,
    emisor_email     : f.emisor_email,     emisor_telefono  : f.emisor_telefono,
    emisor_iban      : f.emisor_iban,

    cliente_nombre    : f.cliente_nombre,    cliente_nif       : f.cliente_nif,
    cliente_direccion : f.cliente_direccion, cliente_cp        : f.cliente_cp,
    cliente_municipio : f.cliente_municipio, cliente_provincia : f.cliente_provincia,
    cliente_email     : f.cliente_email,

    inmueble_direccion : f.inmueble_direccion,
    concepto           : 'Rectificación de: ' + (f.concepto || ''),
    conceptos_extra    : f.conceptos_extra || '',
    notas              : 'Factura rectificativa de ' + f.numero_factura + '. Anulación total.' + notaOrigen,

    // Importes negados — cancelan los efectos fiscales de la original
    base_imponible : -parseFloat(f.base_imponible || 0),
    iva_pct        :  f.iva_pct,
    importe_iva    : -parseFloat(f.importe_iva    || 0),
    irpf_pct       :  f.irpf_pct,
    importe_irpf   : -parseFloat(f.importe_irpf   || 0),
    importe_total  : -parseFloat(f.importe_total  || 0),

    estado              : 'emitida',
    hash_factura        : null, hash_anterior       : null,
    qr_url              : null, verifactu_estado    : 'no_enviado',
    verifactu_respuesta : null, factura_rectificada_id: f.id,
    fecha_creacion      : new Date().toISOString(),
  };

  const saved = await DB.save('facturas', rectificativa);
  if (!saved || saved.error) return { ok: false, error: 'Error al crear la factura rectificativa.' };

  // Marcar la original como rectificada con referencia a la nueva rectificativa
  f.estado = 'rectificada';
  f.notas = (f.notas ? f.notas + '\n' : '') +
    'Rectificada por: ' + numRect + ' · emitida el ' + hoy + '.' + notaOrigen;
  const savedOrig = await DB.save('facturas', f);
  if (!savedOrig || savedOrig.error) {
    // La rectificativa ya se creó pero no se pudo marcar la original como
    // rectificada: se informa como fallo para que el llamador NO continúe
    // (p. ej. no debe anular el recibo si esto no ha terminado bien), aunque
    // la rectificativa quede creada — caso raro, queda documentado en notas.
    return { ok: false, error: 'La factura rectificativa se creó pero no se pudo actualizar la factura original.', rectificativaId: saved.id, numeroRectificativa: numRect };
  }

  return { ok: true, rectificativaId: saved.id, numeroRectificativa: numRect };
}

// ===========================
// ANULAR FACTURA (botón del módulo Facturas) — RD 1619/2012, art. 15
// Confirma con el usuario y delega la generación de la rectificativa en
// anularFacturaConRectificativa() (ver arriba).
// ===========================
async function anularFactura(id) {
  const f = DB.getItem('facturas', id);
  if (!f) return;
  if (f.serie === 'RET' || (f.tipo_factura || '').startsWith('R')) {
    toast('Las facturas rectificativas no se pueden anular. Si hay un error, consulta a tu asesor fiscal.', 'info'); return;
  }
  if (f.estado === 'anulada' || f.estado === 'rectificada') {
    toast('Esta factura ya está anulada o rectificada.', 'info'); return;
  }
  if (!confirm(
    '¿Anular la factura ' + f.numero_factura + '?\n\n' +
    'Se creará automáticamente una factura rectificativa (R1) con importes\n' +
    'negativos que cancela fiscalmente la original (RD 1619/2012, art. 15).\n\n' +
    'La factura original quedará marcada como RECTIFICADA.'
  )) return;

  const resultado = await anularFacturaConRectificativa(id);
  if (!resultado.ok) { toast(resultado.error || 'Error al anular la factura.', 'error'); return; }

  toast('Creada ' + resultado.numeroRectificativa + ' · ' + f.numero_factura + ' queda rectificada.', 'success');
  renderFacturas(navParams);
}

// ===========================
// LISTADO DE FACTURAS
// ===========================
let _facturasPag = 1;
let _facListaFiltrada = [];   // lista completa tras filtros, usada por imprimir/PDF

function renderFacturas(params) {
  params = params || {};
  const facturas      = DB.get('facturas');
  const inquilinos    = DB.get('inquilinos');
  const vfActivo      = getConfigVar('verifactu_activo') === '1';

  // Filtros desde UI
  const filtroAnio    = document.getElementById('fac-filtro-anio')?.value  || '';
  const filtroMes     = document.getElementById('fac-filtro-mes')?.value   || '';
  const filtroInq     = document.getElementById('fac-filtro-inq')?.value   || '';
  const filtroEstado  = document.getElementById('fac-filtro-estado')?.value|| '';

  // Ordenar por fecha de emisión descendente
  let lista = [...facturas].sort((a, b) => {
    if (b.fecha_emision < a.fecha_emision) return -1;
    if (b.fecha_emision > a.fecha_emision) return 1;
    return b.id - a.id;
  });

  // Aplicar filtros
  if (filtroAnio)   lista = lista.filter(f => (f.fecha_emision || '').startsWith(filtroAnio));
  if (filtroMes)    lista = lista.filter(f => (f.fecha_emision || '').slice(5, 7) === filtroMes.padStart(2,'0'));
  if (filtroInq)    lista = lista.filter(f => f.inquilino_id == filtroInq);
  if (filtroEstado) lista = lista.filter(f => f.estado === filtroEstado);
  const _FAC_PP = Math.max(5, parseInt(_cfgGet('filas_facturas', '20')) || 20);
  const _facTotPag = Math.max(1, Math.ceil(lista.length / _FAC_PP));
  _facturasPag = Math.max(1, Math.min(_facturasPag, _facTotPag));
  const listaPag = lista.slice((_facturasPag - 1) * _FAC_PP, _facturasPag * _FAC_PP);

  // Opciones de año dinámicas
  const anios = [...new Set(facturas.map(f => (f.fecha_emision||'').slice(0,4)).filter(Boolean))].sort().reverse();

  _facListaFiltrada = lista;
  document.getElementById('header-actions').innerHTML = lista.length
    ? `<button class="btn btn-secondary" onclick="imprimirListadoFacturas()">Imprimir</button>
       <button class="btn btn-secondary" onclick="pdfListadoFacturas()">PDF</button>`
    : '';
  document.getElementById('content').innerHTML = `
    <div class="card">
      <div class="card-header">
        <div class="card-title">Facturas emitidas (${lista.length})</div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <select id="fac-filtro-anio" onchange="_facturasPag=1;renderFacturas(navParams)" style="width:90px;padding:6px 8px">
            <option value="">Año</option>
            ${anios.map(a => `<option value="${a}" ${filtroAnio===a?'selected':''}>${a}</option>`).join('')}
          </select>
          <select id="fac-filtro-mes" onchange="_facturasPag=1;renderFacturas(navParams)" style="width:110px;padding:6px 8px">
            <option value="">Mes</option>
            ${['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre']
              .map((m,i) => `<option value="${String(i+1).padStart(2,'0')}" ${filtroMes===String(i+1).padStart(2,'0')?'selected':''}>${m}</option>`).join('')}
          </select>
          <select id="fac-filtro-inq" onchange="_facturasPag=1;renderFacturas(navParams)" style="width:160px;padding:6px 8px">
            <option value="">Todos inquilinos</option>
            ${inquilinos.map(q => `<option value="${q.id}" ${filtroInq==q.id?'selected':''}>${esc(q.nombre)}</option>`).join('')}
          </select>
          <select id="fac-filtro-estado" onchange="_facturasPag=1;renderFacturas(navParams)" style="width:120px;padding:6px 8px">
            <option value="" ${!filtroEstado?'selected':''}>Todos estados</option>
            <option value="emitida"     ${filtroEstado==='emitida'?'selected':''}>Emitidas</option>
            <option value="anulada"     ${filtroEstado==='anulada'?'selected':''}>Anuladas</option>
            <option value="rectificada" ${filtroEstado==='rectificada'?'selected':''}>Rectificadas</option>
          </select>
          <div class="search-bar" style="width:200px">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" placeholder="Nº factura..." oninput="filterTable(this,'tbl-facturas',[0,1,2])">
          </div>
        </div>
      </div>
      <div class="table-wrap">
        <table id="tbl-facturas">
          <thead>
            <tr>
              <th>Nº Factura</th>
              <th>Fecha emisión</th>
              <th>Cliente</th>
              <th>Inmueble</th>
              <th>Período</th>
              <th style="text-align:right">Base</th>
              <th style="text-align:right">IVA</th>
              <th style="text-align:right">IRPF</th>
              <th style="text-align:right">Total</th>
              <th>Estado</th>
              ${vfActivo ? '<th>AEAT</th>' : ''}
              <th></th>
            </tr>
          </thead>
          <tbody>
            ${lista.length ? listaPag.map(f => {
              const periodoStr = f.periodo_desde && f.periodo_hasta
                ? fmtDate(f.periodo_desde) + '–' + fmtDate(f.periodo_hasta)
                : (f.concepto || '').replace('Alquiler del inmueble — ','').slice(0,20);
              const reciboOrigen = DB.get('recibos').find(r => r.id === f.recibo_id);
              const facRectificada = f.factura_rectificada_id ? DB.getItem('facturas', f.factura_rectificada_id) : null;
              return `<tr class="tr-${(f.estado==='anulada'||f.estado==='rectificada')?'anulado':'cobrado'}">
                <td>
                  <strong>${esc(f.numero_factura)}</strong>
                  ${reciboOrigen ? `<br><small style="color:var(--gray-400)">Rec: ${esc(reciboOrigen.numero_recibo)}</small>` : ''}
                  ${facRectificada ? `<br><small style="color:#d97706">Rect: ${esc(facRectificada.numero_factura)}</small>` : ''}
                </td>
                <td>${fmtDate(f.fecha_emision)}</td>
                <td>${esc(f.cliente_nombre || '—')}</td>
                <td style="font-size:12px">${esc((f.inmueble_direccion||'—').slice(0,35))}${f.inmueble_direccion&&f.inmueble_direccion.length>35?'…':''}</td>
                <td style="font-size:12px">${esc(periodoStr)}</td>
                <td style="text-align:right">${fmtMoney(f.base_imponible)}</td>
                <td style="text-align:right">${Math.abs(f.importe_iva || 0) > 0.005 ? fmtMoney(f.importe_iva) : '<span style="color:var(--gray-400)">—</span>'}</td>
                <td style="text-align:right">${Math.abs(f.importe_irpf || 0) > 0.005 ? '<span style="color:var(--red)">' + ((f.importe_irpf||0) > 0 ? '−' : '+') + fmtMoney(Math.abs(f.importe_irpf)) + '</span>' : '<span style="color:var(--gray-400)">—</span>'}</td>
                <td style="text-align:right"><strong>${fmtMoney(f.importe_total)}</strong></td>
                <td>${badgeEstadoFactura(f.estado)}</td>
                ${vfActivo ? `<td>${badgeVF(f.verifactu_estado)}</td>` : ''}
                <td class="td-actions">
                  ${_cfgVisi('VisiImprimirFact') ? `<button class="btn btn-sm btn-primary btn-icon" title="Imprimir / PDF" onclick="imprimirFacturaModal(${f.id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                  </button>` : ''}
                  ${_cfgVisi('VisiEmailFact') ? (f.cliente_email
                    ? `<button class="btn btn-sm btn-info" style="font-size:11px" title="Enviar por email" onclick="enviarFacturaEmail(${f.id})">✉</button>`
                    : `<button class="btn btn-sm btn-icon" style="font-size:11px;background:var(--gray-200);color:var(--gray-500);border-color:var(--gray-300);cursor:not-allowed" title="Sin email registrado" disabled>✉</button>`) : ''}
                  ${_cfgVisi('VisiReciboOrigenFact') && reciboOrigen
                    ? `<button class="btn btn-sm btn-secondary" style="font-size:10px" title="Ver recibo origen" onclick="navigate('recibos');setTimeout(()=>{ document.querySelector('[title=\\'Imprimir\\'][onclick*=\\'imprimirReciboModal(${reciboOrigen.id})\\']')?.scrollIntoView({block:'center'}) },400)">
                         <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                         REC
                       </button>`
                    : ''}
                  ${_cfgVisi('VisiAEATFact') && vfActivo && f.estado === 'emitida' && f.verifactu_estado !== 'enviado'
                    ? `<button class="btn btn-sm" style="background:#7c3aed;color:#fff;font-size:10px" title="Enviar/reintentar envío a AEAT" onclick="enviarFacturaAEAT(${f.id},this)">
                         🛡 AEAT
                       </button>`
                    : ''}
              ${_cfgVisi('VisiXMLFact') && vfActivo && f.verifactu_estado === 'enviado'
                    ? `<button class="btn btn-sm btn-secondary btn-icon" title="Ver XML enviado a AEAT" onclick="verXMLFactura(${f.id})">
                         <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                       </button>`
                    : ''}
              ${_cfgVisi('VisiAnularFact') && f.estado === 'emitida'
                    ? `<button class="btn btn-sm btn-danger btn-icon" title="Anular factura" onclick="anularFactura(${f.id})">
                         <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                       </button>`
                    : ''}
                </td>
              </tr>`;
            }).join('') : `<tr><td colspan="${vfActivo ? 12 : 11}"><div class="empty-state">
              <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
              <p>Sin facturas</p>
              <small>Genera una factura desde un recibo en la sección Recibos</small>
            </div></td></tr>`}
          </tbody>
        </table>
      </div>
      ${_facTotPag > 1 ? `
        <div class="table-pagination">
          <button class="btn btn-sm btn-secondary" onclick="_facturasPag=${_facturasPag-1};renderFacturas(navParams)" ${_facturasPag<=1?'disabled':''}>‹ Ant.</button>
          <span>Página ${_facturasPag} de ${_facTotPag} · ${lista.length} facturas</span>
          <button class="btn btn-sm btn-secondary" onclick="_facturasPag=${_facturasPag+1};renderFacturas(navParams)" ${_facturasPag>=_facTotPag?'disabled':''}>Sig. ›</button>
        </div>` : ''}
    </div>`;
  makeTableSortable('tbl-facturas', {col:1, dir:-1});

  // Actualizar el indicador de alerta en el nav si hay facturas pendientes de AEAT
  if (getConfigVar('verifactu_activo') === '1') {
    const pendientes = DB.get('facturas').filter(f =>
      f.verifactu_estado === 'pendiente_envio' || f.verifactu_estado === 'error'
    ).length;
    const navItem = document.getElementById('nav-verifactu');
    if (navItem) {
      const badge = navItem.querySelector('.vf-badge');
      if (pendientes > 0) {
        if (!badge) {
          const b = document.createElement('span');
          b.className = 'vf-badge';
          b.style.cssText = 'background:#ef4444;color:#fff;border-radius:10px;padding:1px 6px;font-size:10px;font-weight:700;margin-left:auto';
          b.textContent = pendientes;
          navItem.appendChild(b);
        } else { badge.textContent = pendientes; }
      } else if (badge) { badge.remove(); }
    }
  }
}

// ===========================
// LISTADO IMPRIMIBLE DE FACTURAS
// ===========================
function _facListadoHtml() {
  const lista = _facListaFiltrada;

  // Descripción de filtros activos para el subtítulo
  const partes = [];
  const anio   = document.getElementById('fac-filtro-anio')?.value;
  const mes    = document.getElementById('fac-filtro-mes')?.value;
  const inqEl  = document.getElementById('fac-filtro-inq');
  const estEl  = document.getElementById('fac-filtro-estado');
  if (anio) partes.push('Año: ' + anio);
  if (mes)  partes.push('Mes: ' + ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'][parseInt(mes)-1]);
  if (inqEl?.value) partes.push('Cliente: ' + (inqEl.options[inqEl.selectedIndex]?.text || ''));
  if (estEl?.value) partes.push('Estado: ' + (estEl.options[estEl.selectedIndex]?.text || ''));
  const subtitulo = partes.length ? partes.join(' · ') : 'Todas las facturas';

  const totalBase  = lista.reduce((s, f) => s + (f.base_imponible  || 0), 0);
  const totalIva   = lista.reduce((s, f) => s + (f.importe_iva     || 0), 0);
  const totalIrpf  = lista.reduce((s, f) => s + (f.importe_irpf    || 0), 0);
  const totalTotal = lista.reduce((s, f) => s + (f.importe_total   || 0), 0);

  const filas = lista.map(f => {
    const periodoStr = f.periodo_desde && f.periodo_hasta
      ? fmtDate(f.periodo_desde) + '–' + fmtDate(f.periodo_hasta)
      : (f.concepto || '').replace('Alquiler del inmueble — ','').slice(0, 25);
    const estadoTxt = { emitida: 'Emitida', anulada: 'Anulada', rectificada: 'Rectificada' }[f.estado] || f.estado;
    const estadoCol = f.estado === 'emitida' ? '#057a55' : '#6b7280';
    return `<tr>
      <td style="padding:6px 8px;border:1px solid #e5e7eb">${esc(f.numero_factura)}</td>
      <td style="padding:6px 8px;border:1px solid #e5e7eb">${fmtDate(f.fecha_emision)}</td>
      <td style="padding:6px 8px;border:1px solid #e5e7eb">${esc(f.cliente_nombre || '—')}</td>
      <td style="padding:6px 8px;border:1px solid #e5e7eb;font-size:11px">${esc((f.inmueble_direccion || '—').slice(0, 35))}${(f.inmueble_direccion||'').length>35?'…':''}</td>
      <td style="padding:6px 8px;border:1px solid #e5e7eb;font-size:11px">${esc(periodoStr)}</td>
      <td style="padding:6px 8px;border:1px solid #e5e7eb;text-align:right">${fmtMoney(f.base_imponible)}</td>
      <td style="padding:6px 8px;border:1px solid #e5e7eb;text-align:right">${Math.abs(f.importe_iva||0)>0.005?fmtMoney(f.importe_iva):'—'}</td>
      <td style="padding:6px 8px;border:1px solid #e5e7eb;text-align:right">${Math.abs(f.importe_irpf||0)>0.005?fmtMoney(Math.abs(f.importe_irpf)):'—'}</td>
      <td style="padding:6px 8px;border:1px solid #e5e7eb;text-align:right;font-weight:700">${fmtMoney(f.importe_total)}</td>
      <td style="padding:6px 8px;border:1px solid #e5e7eb;color:${estadoCol};font-weight:600">${estadoTxt}</td>
    </tr>`;
  }).join('');

  return `<div style="font-family:Arial,sans-serif;padding:32px;background:#fff;color:#111;max-width:1100px">
    <h2 style="margin:0 0 2px;color:#1a56db">Listado de Facturas</h2>
    <div style="font-size:12px;color:#6b7280;margin-bottom:18px">${subtitulo} · ${new Date().toLocaleDateString('es-ES')} · ${lista.length} factura${lista.length!==1?'s':''}</div>
    <table style="width:100%;border-collapse:collapse;font-size:12px">
      <thead>
        <tr style="background:#f3f4f6">
          <th style="padding:7px 8px;border:1px solid #e5e7eb;text-align:left">Nº Factura</th>
          <th style="padding:7px 8px;border:1px solid #e5e7eb;text-align:left">Fecha</th>
          <th style="padding:7px 8px;border:1px solid #e5e7eb;text-align:left">Cliente</th>
          <th style="padding:7px 8px;border:1px solid #e5e7eb;text-align:left">Inmueble</th>
          <th style="padding:7px 8px;border:1px solid #e5e7eb;text-align:left">Período</th>
          <th style="padding:7px 8px;border:1px solid #e5e7eb;text-align:right">Base</th>
          <th style="padding:7px 8px;border:1px solid #e5e7eb;text-align:right">IVA</th>
          <th style="padding:7px 8px;border:1px solid #e5e7eb;text-align:right">IRPF</th>
          <th style="padding:7px 8px;border:1px solid #e5e7eb;text-align:right">Total</th>
          <th style="padding:7px 8px;border:1px solid #e5e7eb;text-align:left">Estado</th>
        </tr>
      </thead>
      <tbody>${filas}</tbody>
      <tfoot>
        <tr style="background:#f3f4f6;font-weight:700">
          <td colspan="5" style="padding:7px 8px;border:1px solid #e5e7eb">TOTAL (${lista.length} factura${lista.length!==1?'s':''})</td>
          <td style="padding:7px 8px;border:1px solid #e5e7eb;text-align:right">${fmtMoney(totalBase)}</td>
          <td style="padding:7px 8px;border:1px solid #e5e7eb;text-align:right">${Math.abs(totalIva)>0.005?fmtMoney(totalIva):'—'}</td>
          <td style="padding:7px 8px;border:1px solid #e5e7eb;text-align:right">${Math.abs(totalIrpf)>0.005?fmtMoney(Math.abs(totalIrpf)):'—'}</td>
          <td style="padding:7px 8px;border:1px solid #e5e7eb;text-align:right;color:#1a56db">${fmtMoney(totalTotal)}</td>
          <td style="padding:7px 8px;border:1px solid #e5e7eb"></td>
        </tr>
      </tfoot>
    </table>
  </div>`;
}

function imprimirListadoFacturas() {
  if (!_facListaFiltrada.length) { toast('No hay facturas para imprimir', 'info'); return; }
  const w = window.open('', '_blank', 'width=1200,height=800');
  const css = _getReciboCss ? _getReciboCss() : '';
  w.document.write(`<!DOCTYPE html><html><head><meta charset="UTF-8">
    <title>Listado de Facturas</title>
    <style>@media print{@page{margin:15mm}body{margin:0}} ${css}</style>
  </head><body>${_facListadoHtml()}</body></html>`);
  w.document.close();
  w.focus();
  setTimeout(() => w.print(), 400);
}

function pdfListadoFacturas() {
  if (!_facListaFiltrada.length) { toast('No hay facturas para exportar', 'info'); return; }
  const div = document.createElement('div');
  div.innerHTML = _facListadoHtml();
  div.style.cssText = 'position:absolute;left:-9999px;top:0;background:white;width:1100px';
  document.body.appendChild(div);
  toast('Generando PDF…', 'info');
  html2canvas(div, { scale: 1.5, backgroundColor: '#ffffff' }).then(canvas => {
    document.body.removeChild(div);
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
    const pw = pdf.internal.pageSize.getWidth();
    const ph = pdf.internal.pageSize.getHeight();
    const imgW = pw;
    const imgH = (canvas.height * imgW) / canvas.width;
    let y = 0;
    while (y < imgH) {
      if (y > 0) pdf.addPage();
      pdf.addImage(canvas.toDataURL('image/png'), 'PNG', 0, -y, imgW, imgH);
      y += ph;
    }
    const fecha = new Date().toISOString().slice(0, 10);
    pdf.save('listado_facturas_' + fecha + '.pdf');
  });
}

// ===========================
// HTML DE LA FACTURA (diseño similar al recibo)
// Usa los datos almacenados en la factura (campos congelados),
// no los datos vivos de las tablas relacionadas. Esto garantiza
// que la factura siempre muestra los datos del momento de emisión.
// ===========================
function buildFacturaHTML(id, formato) {
  const f = DB.getItem('facturas', id);
  if (!f) return '';
  const cls = formato === 'a5' ? 'recibo-a5' : 'recibo-a4';

  // Leer el flag aviso_factura del contrato vinculado
  const contrato    = DB.getItem('contratos', f.contrato_id);
  const mostrarAviso = contrato && contrato.aviso_factura == 1;

  // Parsear conceptos extra igual que en recibos
  const extras = (f.conceptos_extra || '').split('\n').filter(l => l.trim()).map(l => {
    const parts = l.split(':');
    return { desc: parts[0] || l, importe: parseFloat((parts[1] || '0').replace(',','.').replace('€','')) || 0 };
  });

  const tieneIva  = Math.abs(f.importe_iva  || 0) > 0.005;
  const tieneIrpf = Math.abs(f.importe_irpf || 0) > 0.005;

  // Período facturado
  const periodoStr = f.periodo_desde && f.periodo_hasta
    ? fmtDate(f.periodo_desde) + ' – ' + fmtDate(f.periodo_hasta)
    : '';

  // Referencia al recibo origen para el pie de la factura
  const reciboRef = DB.get('recibos').find(r => r.id === f.recibo_id);
  const numRecibo = reciboRef ? reciboRef.numero_recibo : ('ID: ' + f.recibo_id);

  return `
  <div class="${cls}" id="factura-print-${formato}">
    <!-- TÍTULO -->
    <div class="recibo-titulo-box">
      <h1>${f.serie === 'RET' ? 'FACTURA RECTIFICATIVA' : 'FACTURA'}</h1>
      <p>${esc(f.concepto || '')}</p>
    </div>

    <!-- NÚMERO Y FECHAS LEGALES -->
    <div class="recibo-num-fecha">
      <div>
        <div style="font-size:9pt;color:#6b7280;text-transform:uppercase;letter-spacing:.05em">Nº Factura</div>
        <div class="recibo-num">${esc(f.numero_factura)}</div>
        <div style="font-size:8.5pt;color:#9ca3af;margin-top:2pt">
          Serie: ${esc(f.serie || 'FAC')} &nbsp;·&nbsp; Tipo: ${esc(f.tipo_factura || 'F1')}
        </div>
      </div>
      <div class="recibo-fecha">
        <div><strong>Fecha de emisión:</strong> ${fmtDateShort(f.fecha_emision)}</div>
        ${f.fecha_operacion && f.fecha_operacion !== f.fecha_emision
          ? `<div><strong>Fecha de operación:</strong> ${fmtDateShort(f.fecha_operacion)}</div>`
          : ''}
        ${periodoStr
          ? `<div><strong>Período:</strong> ${periodoStr}</div>`
          : ''}
      </div>
    </div>

    <!-- REFERENCIA CRUZADA: rectificativa → original -->
    ${f.factura_rectificada_id ? (() => {
      const orig = DB.getItem('facturas', f.factura_rectificada_id);
      return orig ? `
      <div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:6px;padding:8px 12px;margin:3mm 0;font-size:9pt;line-height:1.6">
        <strong>Factura rectificativa</strong> — Rectifica la factura n.º <strong>${esc(orig.numero_factura)}</strong>
        de fecha ${fmtDateShort(orig.fecha_emision)} por importe total de <strong>${fmtMoney(orig.importe_total)}</strong>.
        Motivo: anulación total. Emitida conforme al art. 15 del RD 1619/2012.
      </div>` : '';
    })() : ''}

    <!-- REFERENCIA CRUZADA: original rectificada → rectificativa -->
    ${f.estado === 'rectificada' ? (() => {
      const rect = DB.get('facturas').find(r => r.factura_rectificada_id === f.id);
      return rect ? `
      <div style="background:#fff1f2;border:1px solid #f87171;border-radius:6px;padding:8px 12px;margin:3mm 0;font-size:9pt;line-height:1.6">
        <strong>Factura rectificada</strong> — Esta factura queda sin efecto. Anulada por la factura rectificativa
        n.º <strong>${esc(rect.numero_factura)}</strong> emitida el ${fmtDateShort(rect.fecha_emision)}.
      </div>` : '';
    })() : ''}

    <!-- EMISOR Y CLIENTE -->
    <div class="recibo-partes">
      <div class="recibo-parte">
        <div class="recibo-parte-titulo">&#x1F3E2; Emisor / Arrendador</div>
        <div class="recibo-parte-nombre">${esc(f.emisor_nombre || '—')}</div>
        <div class="recibo-parte-datos">
          ${f.emisor_nif ? 'NIF/CIF: <strong>' + esc(f.emisor_nif) + '</strong>' : ''}
          ${f.emisor_direccion ? '<br>' + esc(f.emisor_direccion) : ''}
          ${f.emisor_cp || f.emisor_municipio
            ? '<br>' + [f.emisor_cp, f.emisor_municipio, f.emisor_provincia ? '(' + f.emisor_provincia + ')' : ''].filter(Boolean).map(esc).join(' ')
            : ''}
          ${f.emisor_telefono ? '<br>Tel: ' + esc(f.emisor_telefono) : ''}
          ${f.emisor_email ? '<br>' + esc(f.emisor_email) : ''}
          ${f.emisor_iban ? '<br>IBAN: <strong>' + esc(f.emisor_iban) + '</strong>' : ''}
        </div>
      </div>
      <div class="recibo-parte">
        <div class="recibo-parte-titulo">&#x1F3E0; Cliente / Arrendatario</div>
        <div class="recibo-parte-nombre">${esc(f.cliente_nombre || '—')}</div>
        <div class="recibo-parte-datos">
          ${f.cliente_nif ? 'NIF/NIE/CIF: <strong>' + esc(f.cliente_nif) + '</strong>' : ''}
          ${f.cliente_direccion ? '<br>' + esc(f.cliente_direccion) : ''}
          ${f.cliente_cp || f.cliente_municipio
            ? '<br>' + [f.cliente_cp, f.cliente_municipio, f.cliente_provincia ? '(' + f.cliente_provincia + ')' : ''].filter(Boolean).map(esc).join(' ')
            : ''}
          ${f.cliente_email ? '<br>' + esc(f.cliente_email) : ''}
        </div>
      </div>
    </div>

    <!-- INMUEBLE ARRENDADO -->
    <div class="recibo-inmueble">
      <div class="recibo-inmueble-titulo">Inmueble objeto del arrendamiento</div>
      <div class="recibo-inmueble-datos">${esc(f.inmueble_direccion || '—')}</div>
    </div>

    <!-- DESGLOSE DE CONCEPTOS E IMPORTES -->
    <div class="recibo-conceptos">
      <table>
        <thead>
          <tr>
            <th style="width:55%">Concepto</th>
            <th style="text-align:right">Base imponible</th>
            <th style="text-align:right">Importe</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>${esc(f.concepto || 'Alquiler')}</td>
            <td style="text-align:right">${fmtMoney(f.base_imponible)}</td>
            <td style="text-align:right">${fmtMoney(f.base_imponible)}</td>
          </tr>
          ${extras.map(e => `<tr>
            <td>${esc(e.desc)}</td>
            <td style="text-align:right">${fmtMoney(e.importe)}</td>
            <td style="text-align:right">${fmtMoney(e.importe)}</td>
          </tr>`).join('')}
        </tbody>
      </table>

      <!-- TOTALES FISCALES LEGALES -->
      <div class="recibo-totales">
        <div class="recibo-total-line subtotal">
          <span>Base imponible</span>
          <span>${fmtMoney(f.base_imponible)}</span>
        </div>
        ${tieneIva
          ? `<div class="recibo-total-line subtotal">
               <span>IVA (${f.iva_pct}%)</span>
               <span>${(f.importe_iva || 0) >= 0 ? '+' : '−'} ${fmtMoney(Math.abs(f.importe_iva))}</span>
             </div>`
          : `<div class="recibo-total-line subtotal" style="color:#9ca3af;font-size:9pt">
               <span>IVA</span>
               <span>Operación exenta o no sujeta a IVA según configuración fiscal del contrato.</span>
             </div>`}
        ${tieneIrpf
          ? `<div class="recibo-total-line subtotal">
               <span>Retención IRPF (${f.irpf_pct}%)</span>
               <span>${(f.importe_irpf || 0) >= 0 ? '−' : '+'} ${fmtMoney(Math.abs(f.importe_irpf))}</span>
             </div>`
          : ''}
        <div class="recibo-total-line total-final">
          <span>TOTAL FACTURA</span>
          <span>${fmtMoney(f.importe_total)}</span>
        </div>
      </div>
    </div>

    <!-- IMPORTE EN LETRAS -->
    <div style="font-size:9pt;color:#555;margin-bottom:5mm;font-style:italic;text-align:center">
      Son: <strong>${montoEnLetras(f.importe_total)}</strong>
    </div>

    <!-- IBAN DE COBRO (si existe) -->
    ${f.emisor_iban
      ? `<div style="font-size:9pt;color:#374151;margin-bottom:5mm;text-align:center;background:#f3f4f6;padding:6px 10px;border-radius:4px">
           Transferencia/Domiciliación: <strong>${esc(f.emisor_iban)}</strong>
         </div>`
      : ''}

    <!-- SELLO ANULADA / RECTIFICADA (si aplica) -->
    ${(f.estado === 'anulada' || f.estado === 'rectificada')
      ? `<div style="text-align:center;margin-bottom:5mm">
           <div style="display:inline-block;border:3px solid #c81e1e;color:#c81e1e;padding:4px 20px;border-radius:4px;font-size:14pt;font-weight:900;transform:rotate(-5deg)">
             ${f.estado === 'rectificada' ? 'FACTURA RECTIFICADA' : 'FACTURA ANULADA'}
           </div>
         </div>`
      : ''}

    ${f.notas ? `<div class="recibo-notas"><strong>Notas:</strong> ${esc(f.notas)}</div>` : ''}

    <!-- QR DE VERIFICACIÓN AEAT (solo si la factura fue enviada a AEAT y tiene qr_url) -->
    ${f.qr_url && f.verifactu_estado === 'enviado' ? (() => {
      const qrSrc = qrDataURL(f.qr_url, 100);
      return `<div style="display:flex;align-items:center;gap:10mm;margin:4mm 0;border:1px solid #e5e7eb;border-radius:4px;padding:6px 10px;background:#f9fafb">
        ${qrSrc ? `<img src="${qrSrc}" style="width:70px;height:70px;flex-shrink:0">` : ''}
        <div style="font-size:8pt;color:#374151">
          <div style="font-weight:700;margin-bottom:2px">Factura verificable en AEAT</div>
          <div style="font-size:7pt;color:#6b7280;word-break:break-all">${esc(f.qr_url)}</div>
          <div style="font-size:7.5pt;margin-top:3px;color:#374151">Hash: <code style="font-size:6.5pt">${esc((f.hash_factura||'').slice(0,32))}…</code></div>
        </div>
      </div>`;
    })() : ''}

    <!-- AVISO DE VALIDEZ DE PAGO (solo si aviso_recibo está activado en el contrato) -->
    ${mostrarAviso ? `
    <div style="margin-top:6mm;font-size:8.5pt;color:#c81e1e;font-weight:600;text-align:center;border:1px solid #fca5a5;border-radius:4px;padding:6px 10px;background:#fff5f5;line-height:1.5">
      Esta factura no constituye justificante de pago sin el correspondiente justificante bancario que acredite su abono.
    </div>` : ''}

    <!-- PIE LEGAL OBLIGATORIO -->
    <div style="margin-top:5mm;font-size:8.5pt;color:#6b7280;border-top:1px solid #e5e7eb;padding-top:5pt;line-height:1.5">
      ${f.serie === 'RET'
        ? `Factura rectificativa emitida conforme al art. 15 del Reglamento de Facturación (RD 1619/2012).`
        : `Factura generada a partir del recibo Nº <strong>${esc(numRecibo)}</strong>.
           ${!tieneIva ? ' Esta operación está exenta o no sujeta a IVA según la configuración fiscal del contrato. Revise su situación fiscal concreta.' : ''}
           Factura emitida conforme al Reglamento de Facturación (RD 1619/2012).`}
      ${f.verifactu_estado === 'enviado' ? ' Registrada en el SIF de la AEAT (VERI*FACTU).' : ''}
    </div>
  </div>`;
}

// ===========================
// IMPRESIÓN Y PDF DE FACTURAS
// Equivalentes a las funciones de recibos pero para facturas.
// ===========================
function imprimirFacturaModal(id) {
  const f = DB.getItem('facturas', id);
  if (!f) return;
  openModal('Factura ' + f.numero_factura, `
    <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin-bottom:16px">
      <button class="btn btn-primary" onclick="imprimirFactura(${id},'a4')">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Imprimir A4
      </button>
      <button class="btn btn-secondary" onclick="imprimirFactura(${id},'a5')">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Imprimir A5
      </button>
      <button class="btn btn-secondary" style="background:#dc2626;color:white;border-color:#dc2626" onclick="guardarFacturaPDF(${id},'a4')">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
        Descargar PDF
      </button>
      ${f.cliente_email
        ? `<button class="btn btn-info" style="color:#fff" onclick="closeModal();enviarFacturaEmail(${id})">
             ✉ Enviar por email
           </button>`
        : ''}
    </div>
    <div style="margin-top:12px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;padding:12px;font-size:13px">
      <div style="font-weight:700;margin-bottom:6px">${esc(f.numero_factura)} — ${esc(f.cliente_nombre || '—')}</div>
      <div style="color:var(--gray-500)">
        Fecha emisión: ${fmtDate(f.fecha_emision)} &nbsp;·&nbsp;
        Total: <strong>${fmtMoney(f.importe_total)}</strong> &nbsp;·&nbsp;
        ${badgeEstadoFactura(f.estado)}
      </div>
    </div>
    <p style="font-size:12px;color:var(--gray-500);text-align:center;margin-top:12px">
      Al imprimir, selecciona "Guardar como PDF" en el diálogo del navegador.
    </p>`);
}

function imprimirFactura(id, formato) {
  const html = buildFacturaHTML(id, formato);
  const size = formato === 'a5' ? 'A5' : 'A4';
  const css  = _getReciboCss();
  const win  = window.open('', '_blank', 'width=960,height=720');
  if (!win) { alert('Activa las ventanas emergentes del navegador para imprimir.'); return; }
  win.document.write(`<!DOCTYPE html><html lang="es"><head>
    <meta charset="UTF-8"><title>Factura AlquiGest</title>
    <link rel="stylesheet" href="assets/css/main.css">
  </head><body>${html}
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

// Monta la factura en un div fuera de pantalla y la captura con html2canvas
async function _montarYCapturarFactura(id, formato) {
  const isA5 = formato === 'a5';
  const container = document.createElement('div');
  container.style.cssText = 'position:fixed;left:-9999px;top:0;background:white;' +
    'width:' + (isA5 ? '148mm' : '210mm') + ';';
  const styleEl = document.createElement('style');
  styleEl.textContent = _getReciboCss();
  container.appendChild(styleEl);
  const inner = document.createElement('div');
  inner.innerHTML = buildFacturaHTML(id, formato);
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

async function _generarPDFFacturaBase64(id, formato) {
  const { canvas, isA5 } = await _montarYCapturarFactura(id, formato);
  const { jsPDF } = window.jspdf;
  const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: isA5 ? 'a5' : 'a4' });
  const pW = pdf.internal.pageSize.getWidth();
  const pH = pdf.internal.pageSize.getHeight();
  const imgH = (canvas.height / canvas.width) * pW;
  pdf.addImage(canvas.toDataURL('image/jpeg', 0.92), 'JPEG', 0, 0, pW, Math.min(imgH, pH));
  return pdf.output('datauristring').split(',')[1];
}

async function guardarFacturaPDF(id, formato) {
  const f = DB.getItem('facturas', id);
  if (!f) return;
  if (!window.jspdf || !window.html2canvas) { toast('Librería PDF cargando, inténtalo en unos segundos', 'error'); return; }
  toast('Generando PDF de factura…');
  try {
    const { canvas, isA5 } = await _montarYCapturarFactura(id, formato || 'a4');
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: isA5 ? 'a5' : 'a4' });
    const pW = pdf.internal.pageSize.getWidth();
    const pH = pdf.internal.pageSize.getHeight();
    const imgH = (canvas.height / canvas.width) * pW;
    pdf.addImage(canvas.toDataURL('image/jpeg', 0.92), 'JPEG', 0, 0, pW, Math.min(imgH, pH));
    downloadPDF(pdf, (f.numero_factura || 'factura') + '.pdf');
  } catch(e) {
    toast('Error generando PDF: ' + e.message, 'error');
  }
}

// ===========================
// EMAIL DE FACTURA
// Reutiliza email.php pasando factura_id en lugar de recibo_id.
// El asunto y cuerpo del email se adaptan para ser de factura.
// ===========================
function enviarFacturaEmail(id) {
  const f   = DB.getItem('facturas', id);
  if (!f) return;
  const empresa = DB.getEmpresa() || {};
  if (!empresa.gmail_user || !empresa.gmail_pass) {
    toast('Configura el email Gmail en Mi Empresa primero.', 'error');
    navigate('empresa');
    return;
  }
  if (!f.cliente_email) {
    toast('Esta factura no tiene email de cliente almacenado.', 'error');
    return;
  }
  openModal('Enviar factura por email', `
    <p>Se enviará la factura <strong>${esc(f.numero_factura)}</strong> a:</p>
    <p style="font-size:18px;margin:16px 0;text-align:center">
      <strong>${esc(f.cliente_nombre || '—')}</strong><br>
      <span style="color:var(--gray-500)">${esc(f.cliente_email)}</span>
    </p>
    <p style="font-size:13px;color:var(--gray-500)">Remitente: ${esc(empresa.gmail_user)}</p>
    <div id="email-factura-result" style="margin-top:12px"></div>
  `, `<button class="btn btn-primary" onclick="confirmarEnvioFacturaEmail(${id})">✉ Enviar ahora</button>
      <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>`);
}

async function confirmarEnvioFacturaEmail(id) {
  const btn = event.target;
  const div = document.getElementById('email-factura-result');
  btn.disabled = true;
  const f = DB.getItem('facturas', id);

  let pdfBase64 = '';
  if (window.jspdf) {
    btn.textContent = 'Generando PDF…';
    div.innerHTML = `<div style="color:#1e40af;background:#dbeafe;border-radius:8px;padding:10px;font-size:13px">⏳ Generando PDF de la factura…</div>`;
    try { pdfBase64 = await _generarPDFFacturaBase64(id, 'a4'); } catch(e) { console.warn('PDF factura err:', e); }
  }

  btn.textContent = 'Enviando…';
  div.innerHTML = `<div style="color:#1e40af;background:#dbeafe;border-radius:8px;padding:10px;font-size:13px">⏳ Enviando email…</div>`;

  let res;
  try {
    const resp = await fetch('assets/php/email.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        factura_id   : id,
        pdf_base64   : pdfBase64,
        pdf_filename : (f?.numero_factura || 'factura') + '.pdf'
      })
    });
    res = await resp.json();
  } catch { res = { error: 'Error de conexión con el servidor' }; }

  if (res.ok) {
    div.innerHTML = `<div style="color:#15803d;background:#dcfce7;border-radius:8px;padding:12px">✅ ${res.mensaje}</div>`;
    btn.textContent = '✓ Enviado';
  } else {
    div.innerHTML = `<div style="color:#991b1b;background:#fee2e2;border-radius:8px;padding:12px">❌ ${res.error}</div>`;
    btn.disabled = false; btn.textContent = 'Reintentar';
  }
}
