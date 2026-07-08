// ===========================
// EMAIL — ENVÍO DE RECIBO POR CORREO
// confirmarEnvioEmail() genera el PDF del recibo (si jsPDF está cargado),
// lo codifica en base64 y lo envía junto con los datos del recibo a email.php.
// email.php hace el diálogo SMTP con Gmail directamente por sockets PHP
// (sin librerías, sin php.ini especial) usando STARTTLS en el puerto 587.
// Requiere configurar Gmail User + Contraseña de Aplicación en Mi Empresa.
// ===========================
function enviarReciboEmail(id) {
  const r   = DB.getItem('recibos', id);
  const inq = DB.getItem('inquilinos', r?.inquilino_id);
  if (!r || !inq) return;
  const empresa = DB.getEmpresa() || {};
  if (!empresa.gmail_user || !empresa.gmail_pass) {
    toast('Configura el email Gmail en Mi Empresa primero.', 'error');
    navigate('empresa');
    return;
  }
  if (!inq.email) {
    toast('Este inquilino no tiene email en su ficha.', 'error');
    return;
  }
  openModal('Enviar recibo por email', `
    <p>Se enviará el recibo <strong>${r.numero_recibo}</strong> a:</p>
    <p style="font-size:18px;margin:16px 0;text-align:center"><strong>${inq.nombre}</strong><br>
    <span style="color:var(--gray-500)">${inq.email}</span></p>
    <p style="font-size:13px;color:var(--gray-500)">Remitente: ${empresa.gmail_user}</p>
    <div id="email-result" style="margin-top:12px"></div>
  `, `<button class="btn btn-primary" onclick="confirmarEnvioEmail(${id})">✉ Enviar ahora</button>
      <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>`);
}

async function confirmarEnvioEmail(id) {
  const btn = event.target;
  const div = document.getElementById('email-result');
  btn.disabled = true;
  const r = DB.getItem('recibos', id);

  // Generar PDF si jsPDF está disponible
  let pdfBase64 = '';
  if (window.jspdf) {
    btn.textContent = 'Generando PDF…';
    div.innerHTML = `<div style="color:var(--color-info);background:var(--color-info-light);border-radius:8px;padding:10px;font-size:13px">⏳ Generando PDF del recibo…</div>`;
    try { pdfBase64 = await _generarPDFBase64(id, 'a4'); } catch(e) { console.warn('PDF err:', e); }
  }

  btn.textContent = 'Enviando…';
  div.innerHTML = `<div style="color:var(--color-info);background:var(--color-info-light);border-radius:8px;padding:10px;font-size:13px">⏳ Enviando email…</div>`;

  let res;
  try {
    const resp = await fetch('assets/php/email.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ recibo_id: id, pdf_base64: pdfBase64, pdf_filename: (r?.numero_recibo||'recibo')+'.pdf' })
    });
    res = await resp.json();
  } catch { res = { error: 'Error de conexión con el servidor' }; }

  if (res.ok) {
    div.innerHTML = `<div style="color:var(--green);background:var(--green-light);border-radius:8px;padding:12px">✅ ${res.mensaje}</div>`;
    btn.textContent = '✓ Enviado';
    // Registrar envío de email en el log de auditoría (fire-and-forget)
    const _inqEmail = DB.getItem('inquilinos', r?.inquilino_id) || {};
    registrarActividad('email_enviado', 'recibos', id,
      (r?.numero_recibo || '') + ' — ' + (_inqEmail.nombre || '') + ' <' + (_inqEmail.email || '') + '>');
  } else {
    div.innerHTML = `<div style="color:var(--red);background:var(--red-light);border-radius:8px;padding:12px">❌ ${res.error}</div>`;
    btn.disabled = false; btn.textContent = 'Reintentar';
  }
}

// Muestra el modal de confirmación para enviar un recibo por WhatsApp.
// El botón de apertura es un <a href target="_blank"> real — los navegadores
// NUNCA bloquean enlaces HTML, a diferencia de window.open() desde JavaScript.
function enviarReciboWhatsapp(id) {
  const r   = DB.getItem('recibos', id);
  const inq = DB.getItem('inquilinos', r?.inquilino_id);
  if (!r || !inq) return;

  const telRaw = String(inq.movil || inq.telefono || '');
  if (!telRaw.trim()) { toast('Este inquilino no tiene teléfono en su ficha.', 'error'); return; }

  // Limpiar el número y construir formato internacional para wa.me
  let tel = telRaw.replace(/[\s\-()+.]/g, '');
  if (tel.startsWith('00')) tel = tel.slice(2);    // 0034... → 34...
  if (/^[67]\d{8}$/.test(tel)) tel = '34' + tel;  // 6xx/7xx sin prefijo → +34

  const empresa = DB.getEmpresa() || {};
  const msg = `*Recibo de alquiler*\n\nNº recibo: ${r.numero_recibo}\nPeríodo: ${r.concepto_periodo || ''}\nFecha emisión: ${fmtDateShort(r.fecha_emision)}\nImporte: ${fmtMoney(r.importe_total)}\nEstado: ${r.estado}` +
    (empresa.nombre   ? `\n\n${empresa.nombre}`    : '') +
    (empresa.telefono ? `\nTel: ${empresa.telefono}` : '');

  const waUrl = _buildWAUrl(tel, msg);

  openModal('Enviar recibo por WhatsApp', `
    <p>Se abrirá WhatsApp con el recibo <strong>${esc(r.numero_recibo)}</strong> para:</p>
    <p style="font-size:17px;margin:12px 0;text-align:center">
      <strong>${esc(inq.nombre)}</strong><br>
      <span style="color:var(--gray-500)">${esc(telRaw)}</span>
    </p>
    <div style="background:var(--green-light);border:1px solid var(--green);border-radius:8px;padding:10px;
                font-size:12px;white-space:pre-wrap;font-family:monospace;color:var(--green)">
${esc(msg)}</div>
  `, `
    <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
    <button class="btn btn-primary" style="background:#25d366;border-color:#1da851;color:#fff"
       onclick="window.open('${esc(waUrl)}','_blank');closeModal();if(_cfgVisi('whatsappPDF'))setTimeout(function(){_descargarPDFReciboWA(${id})},400)">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:4px">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
      </svg>
      Abrir WhatsApp
    </button>`);
}

// Descarga el PDF de un recibo individual tras abrir WhatsApp.
// Se llama con setTimeout desde el onclick del enlace de WhatsApp para
// no interferir con la apertura del enlace en la nueva pestaña.
// Intenta compartir el PDF usando la Web Share API del navegador.
// En Chrome/Edge (Win11) y navegadores móviles abre el diálogo nativo de
// compartir con el fichero ya cargado → el usuario elige WhatsApp y lo envía.
// En Firefox de escritorio (no soporta archivos) descarga el PDF como fallback.
async function _compartirODescargar(b64, filename) {
  // Convertir base64 a File para la Web Share API
  const bytes = atob(b64);
  const arr   = new Uint8Array(bytes.length);
  for (let i = 0; i < bytes.length; i++) arr[i] = bytes.charCodeAt(i);
  const file  = new File([arr], filename, { type: 'application/pdf' });

  if (navigator.canShare && navigator.canShare({ files: [file] })) {
    try {
      await navigator.share({ files: [file], title: filename });
      return; // El usuario compartió (o canceló el diálogo)
    } catch (e) {
      if (e.name === 'AbortError') return; // Cancelado por el usuario — no descargar
      // Otro error → caer al download
    }
  }

  // Fallback: descargar el PDF para adjuntarlo manualmente
  const link = document.createElement('a');
  link.href  = 'data:application/pdf;base64,' + b64;
  link.download = filename;
  link.click();
  toast('PDF descargado — adjúntalo manualmente en WhatsApp');
}

// Genera el PDF de un recibo individual y lo comparte/descarga para WhatsApp.
function _descargarPDFReciboWA(id) {
  const r = DB.getItem('recibos', id);
  if (!r || !window.jspdf || !window.html2canvas) return;
  toast('Generando PDF…');
  _generarPDFBase64(id, 'a4')
    .then(b64 => _compartirODescargar(b64, (r.numero_recibo || 'recibo') + '.pdf'))
    .catch(e => toast('Error al generar PDF: ' + e.message, 'error'));
}

// Genera el PDF del lote y lo comparte/descarga para WhatsApp.
// Lee los filtros antes de cerrar el modal (se llama con setTimeout breve).
function _descargarPDFLoteWA() {
  if (!window.jspdf || !window.html2canvas) return;
  const recibos = _filtrarRecibosLote();
  if (!recibos.length) return;
  const formato = document.getElementById('lote-formato')?.value || 'a4';
  const nombre  = _nombreLote();
  const sorted  = recibos.sort((a, b) => (a.numero_recibo || '').localeCompare(b.numero_recibo || ''));
  closeModalForce();
  toast(`Generando PDF (${sorted.length} recibo${sorted.length !== 1 ? 's' : ''})…`);
  _generarPDFLoteDoc(sorted, formato)
    .then(doc => _compartirODescargar(doc.output('datauristring').split(',')[1], `recibos-${nombre}.pdf`))
    .catch(e => toast('Error al generar PDF: ' + e.message, 'error'));
}
