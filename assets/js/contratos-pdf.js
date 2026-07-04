// ============================================================
//  AlquiGest – contratos-pdf.js
//  Generación de documentos PDF para contratos y fianzas.
//
//  [28] pdfContrato(id)  → abre modal con opciones Imprimir/PDF del contrato LAU
//  [30] pdfFianza(id)    → abre modal con fecha y genera justificante de fianza
//
//  Ambos documentos se generan como HTML y se imprimen usando
//  _abrirVentanaImpresion() de recibos-pdf.js, lo que permite al
//  navegador paginar automáticamente (contratos son multi-página).
// ============================================================

// ── Helpers internos de documento ────────────────────────────

// Reúne todos los datos relacionados con un contrato en un solo objeto
// para no repetir búsquedas en cada función de plantilla.
function _getDatosContrato(id) {
    var c      = DB.getItem('contratos', id);
    if (!c) return null;
    var inq    = DB.getItem('inquilinos', c.inquilino_id) || {};
    var inm    = DB.getItem('inmuebles', c.inmueble_id)   || {};
    var finca  = DB.getItem('fincas', inm.finca_id)       || {};
    var prop   = DB.getItem('propietarios', finca.propietario_id) || {};
    var emp    = DB.getEmpresa() || {};
    return { c: c, inq: inq, inm: inm, finca: finca, prop: prop, emp: emp };
}

// Construye la dirección legible del inmueble arrendado
function _dirInmueble(finca, inm) {
    var partes = [
        (finca.sigla || '') + ' ' + (finca.calle || '') + ' ' + (finca.numero || ''),
        inm.planta || '', inm.puerta || ''
    ].map(function(s){ return s.trim(); }).filter(Boolean);
    var dir = partes.join(', ');
    if (finca.cp)        dir += ' — CP ' + finca.cp;
    if (finca.municipio) dir += ', ' + finca.municipio;
    if (finca.provincia) dir += ' (' + finca.provincia + ')';
    return dir;
}

// Calcula la duración del contrato en años y meses (para la cláusula de duración)
function _duracionContrato(fechaInicio, fechaFin) {
    if (!fechaInicio) return 'indefinida';
    var inicio = new Date(fechaInicio);
    var fin    = fechaFin ? new Date(fechaFin) : null;
    if (!fin || isNaN(fin)) return 'indefinida';
    var anios  = fin.getFullYear() - inicio.getFullYear();
    var meses  = fin.getMonth() - inicio.getMonth();
    if (meses < 0) { anios--; meses += 12; }
    var partes = [];
    if (anios > 0)  partes.push(anios  + (anios  === 1 ? ' año'  : ' años'));
    if (meses > 0)  partes.push(meses  + (meses  === 1 ? ' mes'  : ' meses'));
    return partes.join(' y ') || 'menos de un mes';
}

// Texto de la cláusula de revisión de renta según el tipo configurado
function _clausulaRevision(c) {
    if (!c.revision || c.revision === 'Sin revision') {
        return 'La renta no estará sujeta a revisión periódica durante la vigencia del contrato.';
    }
    if (c.revision === 'Fija') {
        return 'La renta permanecerá fija durante toda la vigencia del contrato.';
    }
    if (c.revision === 'IPC') {
        return 'La renta se actualizará anualmente, en cada fecha de aniversario del contrato, ' +
               'aplicando la variación porcentual del Índice de Precios al Consumo (IPC) general ' +
               'publicado por el INE para el período de doce meses anteriores a la fecha de revisión, ' +
               'conforme a lo previsto en el art. 18 de la LAU.';
    }
    if (c.revision === 'IRAV') {
        return 'La renta se actualizará anualmente, en cada fecha de aniversario del contrato, ' +
               'conforme al Índice de Referencia de Arrendamientos de Vivienda (IRAV) publicado ' +
               'por el INE, como límite máximo de la actualización, conforme a la Ley 12/2023.';
    }
    return 'La renta se actualizará según lo pactado: ' + esc(c.revision) + '.';
}

// ── [28] Modal de opciones para el contrato ───────────────────
function pdfContrato(id) {
    var d = _getDatosContrato(id);
    if (!d) { toast('Contrato no encontrado', 'error'); return; }

    openModal('Contrato de Arrendamiento — Opciones', `
      <div style="background:#fef3c7;border:1px solid #fbbf24;border-radius:8px;padding:12px;margin-bottom:16px;font-size:13px">
        <strong>⚠ Aviso legal:</strong> Este documento es una plantilla de referencia generada automáticamente
        con los datos del contrato. <strong>No tiene valor jurídico por sí solo.</strong>
        Debe ser revisado y firmado por ambas partes. El usuario es responsable de adaptar el
        contenido a su situación y de cumplir con la normativa aplicable (LAU y legislación autonómica).
      </div>
      <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
        <button class="btn btn-primary" onclick="imprimirContrato(${id})">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
          Imprimir / Guardar PDF
        </button>
      </div>
      <p style="font-size:12px;color:var(--gray-500);text-align:center;margin-top:12px">
        En el diálogo de impresión del navegador, selecciona <strong>«Guardar como PDF»</strong>
        como destino para obtener el fichero PDF.
      </p>`,
      '<button class="btn btn-secondary" onclick="closeModal()">Cerrar</button>',
      false
    );
}

// Abre la ventana de impresión con el HTML del contrato generado
function imprimirContrato(id) {
    var html = _buildContratoHTML(id);
    if (!html) return;
    closeModalForce();
    _abrirVentanaImpresion([html], 'a4');
}

// ── [28] Plantilla HTML del contrato de arrendamiento ────────
// Genera un documento A4 con las cláusulas estándar de la LAU.
// Reutiliza _getReciboCss() de recibos-pdf.js para heredar los estilos.
function _buildContratoHTML(id) {
    var d = _getDatosContrato(id);
    if (!d) { toast('Contrato no encontrado', 'error'); return ''; }
    var c = d.c, inq = d.inq, inm = d.inm, finca = d.finca, prop = d.prop, emp = d.emp;

    var duracion    = _duracionContrato(c.fecha_inicio, c.fecha_fin);
    var dirInm      = _dirInmueble(finca, inm);
    var revisionTxt = _clausulaRevision(c);
    var hoy         = fmtDateShort(new Date().toISOString().slice(0,10));
    var rentaLetras = montoEnLetras(c.renta_base || 0);
    var ivaPct      = parseFloat(c.iva_pct)  || 0;
    var irpfPct     = parseFloat(c.irpf_pct) || 0;
    var fianza      = parseFloat(c.fianza)   || 0;
    var fianzaLetras = fianza > 0 ? montoEnLetras(fianza) : '';

    // Nombre del arrendador: propietario o empresa si el prop no tiene nombre
    var nombreArrendador = prop.nombre || emp.nombre || '—';
    var nifArrendador    = prop.nif    || emp.cif    || '';
    var dirArrendador    = prop.direccion || emp.direccion || '';

    return `<div class="contrato-a4">

  <!-- CABECERA -->
  <div class="cont-cabecera">
    <div class="cont-titulo">CONTRATO DE ARRENDAMIENTO DE FINCA URBANA</div>
    <div class="cont-subtitulo">Ley de Arrendamientos Urbanos — Ley 29/1994, de 24 de noviembre</div>
  </div>

  <!-- LUGAR Y FECHA -->
  <p class="cont-lugar">En ${esc(finca.municipio || '___________')}, a ${hoy}.</p>

  <!-- REUNIDOS -->
  <div class="cont-seccion">
    <div class="cont-h2">REUNIDOS</div>

    <p><strong>De una parte, como ARRENDADOR:</strong><br>
    D./Dña./Entidad <strong>${esc(nombreArrendador)}</strong>,
    ${nifArrendador ? 'con NIF/CIF <strong>' + esc(nifArrendador) + '</strong>,' : ''}
    ${dirArrendador ? 'con domicilio en ' + esc(dirArrendador) + '.' : ''}
    </p>

    <p><strong>De otra parte, como ARRENDATARIO:</strong><br>
    D./Dña. <strong>${esc(inq.nombre || '—')}</strong>,
    ${inq.nif ? 'con NIF/NIE <strong>' + esc(inq.nif) + '</strong>,' : ''}
    ${inq.direccion ? 'con domicilio en ' + esc(inq.direccion) + (inq.municipio ? ', ' + esc(inq.municipio) : '') + '.' : ''}
    ${inq.telefono || inq.movil ? 'Teléfono de contacto: ' + esc(inq.movil || inq.telefono) + '.' : ''}
    </p>

    <p>Ambas partes se reconocen mutuamente capacidad legal suficiente para otorgar el presente contrato, y acuerdan suscribir el presente <strong>CONTRATO DE ARRENDAMIENTO DE VIVIENDA / USO DISTINTO</strong> conforme a las siguientes:</p>
  </div>

  <!-- CLÁUSULAS -->
  <div class="cont-seccion">
    <div class="cont-h2">CLÁUSULAS</div>

    <!-- 1. OBJETO -->
    <div class="cont-clausula">
      <div class="cont-h3">PRIMERA. — Objeto del arrendamiento</div>
      <p>El ARRENDADOR cede en arrendamiento al ARRENDATARIO, quien acepta, el inmueble sito en:</p>
      <div class="cont-dato-destacado">${esc(dirInm || '—')}</div>
      ${inm.referencia_catastral ? '<p>Referencia catastral: <strong>' + esc(inm.referencia_catastral) + '</strong></p>' : ''}
      <p>El inmueble se destina a <strong>${c.uso_inmueble ? esc(c.uso_inmueble) : 'vivienda habitual del arrendatario'}</strong>.</p>
    </div>

    <!-- 2. DURACIÓN -->
    <div class="cont-clausula">
      <div class="cont-h3">SEGUNDA. — Duración</div>
      <p>El presente arrendamiento tendrá una duración de <strong>${duracion}</strong>,
      con fecha de inicio el día <strong>${fmtDateShort(c.fecha_inicio)}</strong>
      ${c.fecha_fin ? 'y fecha de vencimiento el día <strong>' + fmtDateShort(c.fecha_fin) + '</strong>' : '(duración indefinida)'}.</p>
      <p>Finalizado el período pactado, el contrato se extinguirá automáticamente salvo que se acuerde expresamente su renovación por escrito entre las partes, con las condiciones que en ese momento se estipulen.</p>
      <p>El arrendatario deberá comunicar su voluntad de no renovar con un preaviso mínimo de 30 días antes de la fecha de vencimiento.</p>
    </div>

    <!-- 3. RENTA -->
    <div class="cont-clausula">
      <div class="cont-h3">TERCERA. — Renta y forma de pago</div>
      <p>La renta mensual pactada es de <strong>${fmtMoney(c.renta_base || 0)}</strong>
      (${rentaLetras}${ivaPct > 0 ? ', más el ' + ivaPct + '% de IVA (' + fmtMoney((c.renta_base || 0) * ivaPct / 100) + ')' : ''}).</p>
      ${irpfPct > 0 ? '<p>Se aplicará una retención de IRPF del <strong>' + irpfPct + '%</strong> (' + fmtMoney((c.renta_base || 0) * irpfPct / 100) + ') sobre cada recibo.</p>' : ''}
      <p>La renta se abonará por mensualidades anticipadas, dentro de los primeros <strong>${c.dia_pago || 5} días</strong> de cada mes,
      mediante transferencia bancaria
      ${emp.iban ? 'a la cuenta <strong>' + esc(emp.iban) + '</strong>' : 'a la cuenta bancaria que designe el arrendador'}.</p>
      <p>El incumplimiento del pago en el plazo establecido devengará los intereses legales de demora previstos en la normativa aplicable.</p>
    </div>

    <!-- 4. ACTUALIZACIÓN DE RENTA -->
    <div class="cont-clausula">
      <div class="cont-h3">CUARTA. — Actualización de la renta</div>
      <p>${revisionTxt}</p>
      <p>La actualización se notificará al arrendatario con antelación suficiente, indicando el nuevo importe y el índice aplicado.</p>
    </div>

    <!-- 5. FIANZA -->
    <div class="cont-clausula">
      <div class="cont-h3">QUINTA. — Fianza legal</div>
      ${fianza > 0 ? `
        <p>En el acto de la firma del presente contrato, el arrendatario entrega al arrendador, en concepto de <strong>fianza legal obligatoria</strong> (art. 36 LAU), la cantidad de
        <strong>${fmtMoney(fianza)}</strong> (${esc(fianzaLetras)}).</p>
        <p>Dicha fianza será depositada por el arrendador en el organismo autonómico correspondiente, conforme a la normativa vigente.
        Será devuelta al arrendatario a la finalización del contrato, una vez comprobado el buen estado del inmueble y el cumplimiento de todas las obligaciones contractuales.</p>
        <p>La fianza no podrá imputarse al pago de ninguna mensualidad de renta durante la vigencia del contrato.</p>
      ` : `
        <p>Las partes acuerdan <strong>no constituir fianza legal</strong> en el presente contrato, renunciando expresamente a la misma de conformidad con lo permitido por la normativa aplicable.</p>
      `}
    </div>

    <!-- 6. ESTADO Y CONSERVACIÓN -->
    <div class="cont-clausula">
      <div class="cont-h3">SEXTA. — Estado del inmueble y obras</div>
      <p>El arrendatario declara recibir el inmueble en perfecto estado de uso y habitabilidad, comprometiéndose a devolverlo al finalizar el arrendamiento en el mismo estado, salvo el deterioro derivado del uso normal y del transcurso del tiempo.</p>
      <p>El arrendatario no podrá realizar obras de reforma o modificación sin consentimiento escrito previo del arrendador. Las pequeñas reparaciones derivadas del desgaste ordinario serán a cargo del arrendatario.</p>
      <p>El arrendador está obligado a realizar las reparaciones necesarias para conservar el inmueble en condiciones de habitabilidad durante la vigencia del contrato (art. 21 LAU).</p>
    </div>

    <!-- 7. SUMINISTROS -->
    <div class="cont-clausula">
      <div class="cont-h3">SÉPTIMA. — Suministros y gastos</div>
      <p>Serán de cuenta exclusiva del arrendatario los gastos de suministros individualizables de agua, gas, electricidad, telecomunicaciones y demás servicios que se contraten a su nombre o se contabilicen mediante contador individual.</p>
      <p>Los gastos de comunidad${c.gastos_comunidad ? ' (actualmente ' + fmtMoney(c.gastos_comunidad) + '/mes)' : ''} y el IBI correrán a cargo del ${c.gastos_propietario ? 'arrendador' : 'arrendatario'}, salvo pacto expreso en contrario.</p>
    </div>

    <!-- 8. PROHIBICIONES -->
    <div class="cont-clausula">
      <div class="cont-h3">OCTAVA. — Uso y prohibiciones</div>
      <p>El arrendatario se compromete a usar el inmueble exclusivamente para el uso pactado en la cláusula primera, quedando expresamente prohibido:</p>
      <ul class="cont-lista">
        <li>Subarrendar o ceder el contrato, total o parcialmente, sin consentimiento escrito del arrendador.</li>
        <li>Desarrollar actividades molestas, insalubres, nocivas, peligrosas o ilícitas.</li>
        <li>Tener animales domésticos sin consentimiento previo y expreso del arrendador.</li>
        <li>Usar el inmueble para fines turísticos o de alquiler temporal sin autorización.</li>
      </ul>
    </div>

    <!-- 9. RESOLUCIÓN -->
    <div class="cont-clausula">
      <div class="cont-h3">NOVENA. — Causas de resolución</div>
      <p>Serán causas de resolución del contrato, además de las previstas en el artículo 27 de la LAU:</p>
      <ul class="cont-lista">
        <li>La falta de pago de la renta o de cualquier cantidad cuyo pago hubiera asumido el arrendatario.</li>
        <li>La realización de obras no consentidas por el arrendador que causen daño al inmueble.</li>
        <li>El subarrendamiento o cesión inconsentida del contrato.</li>
        <li>La realización de actividades prohibidas en la cláusula octava.</li>
        <li>El incumplimiento de cualquier obligación esencial del presente contrato.</li>
      </ul>
    </div>

    <!-- 10. JURISDICCIÓN -->
    <div class="cont-clausula">
      <div class="cont-h3">DÉCIMA. — Legislación aplicable y jurisdicción</div>
      <p>El presente contrato se regirá por la Ley 29/1994, de 24 de noviembre, de Arrendamientos Urbanos, y sus modificaciones posteriores, y supletoriamente por el Código Civil y demás normativa aplicable.</p>
      <p>Para la resolución de cualquier controversia derivada del presente contrato, las partes se someten a los Juzgados y Tribunales del municipio donde esté sito el inmueble arrendado, con renuncia expresa a cualquier otro fuero que pudiera corresponderles.</p>
    </div>

    ${c.notas ? `
    <div class="cont-clausula">
      <div class="cont-h3">CLÁUSULAS ADICIONALES</div>
      <p style="white-space:pre-wrap">${esc(c.notas)}</p>
    </div>` : ''}
  </div>

  <!-- FIRMAS -->
  <div class="cont-firmas">
    <p style="text-align:center;margin-bottom:28px">
      Leído el presente documento, las partes lo firman en prueba de conformidad en el lugar y fecha indicados en el encabezamiento.
    </p>
    <div class="cont-firma-grid">
      <div class="cont-firma-col">
        <div class="cont-firma-titulo">EL ARRENDADOR</div>
        <div class="cont-firma-linea"></div>
        <div class="cont-firma-nombre">${esc(nombreArrendador)}</div>
        ${nifArrendador ? '<div class="cont-firma-nif">NIF/CIF: ' + esc(nifArrendador) + '</div>' : ''}
      </div>
      <div class="cont-firma-col">
        <div class="cont-firma-titulo">EL ARRENDATARIO</div>
        <div class="cont-firma-linea"></div>
        <div class="cont-firma-nombre">${esc(inq.nombre || '—')}</div>
        ${inq.nif ? '<div class="cont-firma-nif">NIF/NIE: ' + esc(inq.nif) + '</div>' : ''}
      </div>
    </div>
  </div>

  <!-- PIE DE AVISO LEGAL -->
  <div class="cont-pie-legal">
    Documento generado automáticamente por AlquiGest con fines informativos.
    No constituye asesoramiento jurídico. Revisa el contenido antes de firmar.
  </div>

</div>`;
}

// ── Estilos propios del contrato (inyectados en la ventana de impresión) ──
// Se añaden a los de la app vía _getReciboCss(); se definen aquí para
// tenerlos localizados y no contaminar main.css.
function _getContratoCss() {
    return `
      .contrato-a4 {
        font-family: 'Times New Roman', Times, serif;
        font-size: 11pt;
        line-height: 1.6;
        color: #111;
        max-width: 170mm;
        margin: 0 auto;
        padding: 10mm 0;
      }
      .cont-cabecera {
        text-align: center;
        border-bottom: 2px solid #1e3a5f;
        padding-bottom: 8px;
        margin-bottom: 16px;
      }
      .cont-titulo {
        font-size: 14pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: #1e3a5f;
      }
      .cont-subtitulo {
        font-size: 9pt;
        color: #555;
        margin-top: 4px;
      }
      .cont-lugar {
        text-align: right;
        font-style: italic;
        margin-bottom: 12px;
      }
      .cont-seccion {
        margin-bottom: 12px;
      }
      .cont-h2 {
        font-size: 11pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: .08em;
        border-bottom: 1px solid #1e3a5f;
        padding-bottom: 3px;
        margin: 16px 0 10px;
        color: #1e3a5f;
      }
      .cont-clausula {
        margin-bottom: 10px;
      }
      .cont-h3 {
        font-weight: bold;
        font-size: 10.5pt;
        margin-bottom: 4px;
        color: #222;
      }
      .cont-dato-destacado {
        background: #f0f4f8;
        border-left: 3px solid #1e3a5f;
        padding: 6px 10px;
        margin: 8px 0;
        font-weight: bold;
        font-size: 10.5pt;
      }
      .cont-lista {
        margin: 4px 0 4px 18px;
        padding: 0;
      }
      .cont-lista li {
        margin-bottom: 3px;
      }
      .cont-firmas {
        margin-top: 28px;
        padding-top: 16px;
        border-top: 1px solid #ccc;
      }
      .cont-firma-grid {
        display: flex;
        gap: 40px;
        justify-content: space-around;
        margin-top: 20px;
      }
      .cont-firma-col {
        flex: 1;
        text-align: center;
      }
      .cont-firma-titulo {
        font-weight: bold;
        font-size: 10pt;
        text-transform: uppercase;
        margin-bottom: 50px;
      }
      .cont-firma-linea {
        border-bottom: 1px solid #333;
        margin-bottom: 8px;
      }
      .cont-firma-nombre {
        font-size: 10pt;
        font-weight: 600;
      }
      .cont-firma-nif {
        font-size: 9pt;
        color: #555;
      }
      .cont-pie-legal {
        margin-top: 20px;
        padding: 8px 12px;
        background: #fff8dc;
        border: 1px solid #e6c94a;
        border-radius: 4px;
        font-size: 8pt;
        color: #7a6a00;
        text-align: center;
      }
      @media print {
        .contrato-a4 {
          max-width: 100%;
          padding: 0;
        }
        .cont-clausula {
          page-break-inside: avoid;
        }
        .cont-firmas {
          page-break-inside: avoid;
        }
      }
    `;
}

// Sobrescribe _abrirVentanaImpresion para inyectar también el CSS del contrato
// cuando el contenido es de tipo contrato. Usamos una función propia para no
// acoplar contratos-pdf.js con la implementación interna de recibos-pdf.js.
function _imprimirContenidoA4(htmlArray) {
    var win = window.open('', '_blank', 'width=960,height=720');
    if (!win) { alert('Activa las ventanas emergentes del navegador para imprimir.'); return; }
    var body = htmlArray.join('<div style="page-break-after:always;height:0"></div>');
    win.document.write('<!DOCTYPE html><html lang="es"><head>' +
        '<meta charset="UTF-8"><title>AlquiGest — Documento</title>' +
        '<style>' + _getContratoCss() + '</style>' +
        '</head><body>' + body +
        '<script>window.onload=function(){setTimeout(function(){window.print();window.onafterprint=function(){window.close();};},300);};<\/script>' +
        '</body></html>');
    win.document.close();
}

// ── [30] Modal de fianza ──────────────────────────────────────
function pdfFianza(id) {
    var d = _getDatosContrato(id);
    if (!d) { toast('Contrato no encontrado', 'error'); return; }
    if (!(parseFloat(d.c.fianza) > 0)) { toast('Este contrato no tiene fianza registrada', 'info'); return; }

    var hoy = new Date().toISOString().slice(0,10);
    openModal('Recibo de entrega de fianza', `
      <p style="font-size:13px;color:var(--gray-600);margin-bottom:16px">
        Genera un justificante de entrega de fianza para entregar al inquilino. No crea ningún recibo ni registro en la base de datos.
      </p>
      <div class="form-group">
        <label>Fecha de entrega</label>
        <input type="date" id="fianza-fecha" value="${hoy}">
      </div>`,
      `<button class="btn btn-primary" onclick="imprimirFianza(${id})">
         <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
         Imprimir / Guardar PDF
       </button>
       <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>`
    );
}

// Genera e imprime el justificante de fianza
function imprimirFianza(id) {
    var fecha = (document.getElementById('fianza-fecha') || {}).value || new Date().toISOString().slice(0,10);
    var html  = _buildFianzaHTML(id, fecha);
    if (!html) return;
    closeModalForce();
    _imprimirContenidoA4([html]);
}

// ── [30] Plantilla HTML del justificante de fianza ────────────
function _buildFianzaHTML(id, fecha) {
    var d = _getDatosContrato(id);
    if (!d) { toast('Contrato no encontrado', 'error'); return ''; }
    var c = d.c, inq = d.inq, inm = d.inm, finca = d.finca, prop = d.prop, emp = d.emp;

    var fianza       = parseFloat(c.fianza) || 0;
    var fianzaLetras = montoEnLetras(fianza);
    var dirInm       = _dirInmueble(finca, inm);
    var nombreArr    = prop.nombre || emp.nombre || '—';
    var nifArr       = prop.nif    || emp.cif    || '';
    var fechaLeg     = fmtDateShort(fecha);

    return `<div class="contrato-a4" style="max-width:160mm">

  <!-- CABECERA EMPRESA -->
  ${emp.nombre ? `<div style="font-size:13pt;font-weight:bold;color:#1e3a5f">${esc(emp.nombre)}</div>` : ''}
  ${emp.cif ? `<div style="font-size:9pt;color:#555">NIF/CIF: ${esc(emp.cif)}</div>` : ''}
  ${emp.direccion ? `<div style="font-size:9pt;color:#555">${esc(emp.direccion)}${emp.municipio ? ', ' + esc(emp.municipio) : ''}</div>` : ''}
  ${emp.telefono ? `<div style="font-size:9pt;color:#555">Tel: ${esc(emp.telefono)}</div>` : ''}

  <div style="border-top:2px solid #1e3a5f;margin:12px 0"></div>

  <!-- TÍTULO -->
  <div class="cont-cabecera" style="border-bottom:none;margin-bottom:20px">
    <div class="cont-titulo" style="font-size:16pt">RECIBO DE ENTREGA DE FIANZA</div>
  </div>

  <!-- CUERPO -->
  <div style="font-size:11pt;line-height:1.8">
    <p>
      D./Dña./Entidad <strong>${esc(nombreArr)}</strong>${nifArr ? ', con NIF/CIF <strong>' + esc(nifArr) + '</strong>' : ''},
      en su calidad de arrendador/a,
    </p>
    <p>
      <strong>DECLARA HABER RECIBIDO</strong> de D./Dña. <strong>${esc(inq.nombre || '—')}</strong>
      ${inq.nif ? ', con NIF/NIE <strong>' + esc(inq.nif) + '</strong>' : ''},
      en concepto de <strong>FIANZA LEGAL DE ARRENDAMIENTO</strong> (art. 36 Ley 29/1994),
      la cantidad de:
    </p>

    <div style="border:2px solid #1e3a5f;border-radius:6px;padding:14px 20px;margin:18px 0;text-align:center">
      <div style="font-size:22pt;font-weight:bold;color:#1e3a5f">${fmtMoney(fianza)}</div>
      <div style="font-size:10pt;color:#555;margin-top:4px">(${esc(fianzaLetras)})</div>
    </div>

    <p>
      correspondiente al contrato de arrendamiento del inmueble ubicado en:
    </p>
    <div class="cont-dato-destacado">${esc(dirInm || '—')}</div>

    <p>
      con fecha de inicio del arrendamiento el <strong>${fmtDateShort(c.fecha_inicio)}</strong>
      ${c.fecha_fin ? 'y fecha de vencimiento el <strong>' + fmtDateShort(c.fecha_fin) + '</strong>' : ''}.
    </p>

    <p>
      La citada cantidad queda en poder del arrendador/a en concepto de fianza y le será
      devuelta al arrendatario/a a la finalización del contrato, previo cumplimiento de
      todas las obligaciones contractuales y comprobación del estado del inmueble.
    </p>

    <p style="margin-top:8px">
      En ${esc(finca.municipio || emp.municipio || '___________')}, a ${fechaLeg}.
    </p>
  </div>

  <!-- FIRMAS -->
  <div class="cont-firmas" style="margin-top:30px">
    <div class="cont-firma-grid">
      <div class="cont-firma-col">
        <div class="cont-firma-titulo">El Arrendador / Entregado</div>
        <div class="cont-firma-linea"></div>
        <div class="cont-firma-nombre">${esc(nombreArr)}</div>
        ${nifArr ? '<div class="cont-firma-nif">NIF/CIF: ' + esc(nifArr) + '</div>' : ''}
      </div>
      <div class="cont-firma-col">
        <div class="cont-firma-titulo">El Arrendatario / Recibido</div>
        <div class="cont-firma-linea"></div>
        <div class="cont-firma-nombre">${esc(inq.nombre || '—')}</div>
        ${inq.nif ? '<div class="cont-firma-nif">NIF/NIE: ' + esc(inq.nif) + '</div>' : ''}
      </div>
    </div>
  </div>

  <div class="cont-pie-legal">
    Justificante generado por AlquiGest · No válido como factura · Solo para uso informativo
  </div>
</div>`;
}
