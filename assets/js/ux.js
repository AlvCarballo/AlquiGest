// ===========================
// BÚSQUEDA GLOBAL EN CABECERA (M-F01)
// Filtra en tiempo real entre inquilinos, inmuebles y contratos.
// Muestra un panel desplegable con un máximo de 8 resultados.
// ===========================
function busquedaGlobal(query) {
  const panel = document.getElementById('search-results');
  if (!query || query.length < 2) { panel.classList.remove('open'); return; }
  const q = query.toLowerCase();
  const resultados = [];

  // Buscar en inquilinos por nombre, NIF o email
  DB.get('inquilinos').forEach(i => {
    if ((i.nombre||'').toLowerCase().includes(q) ||
        (i.nif||'').toLowerCase().includes(q) ||
        (i.email||'').toLowerCase().includes(q)) {
      resultados.push({ tipo: 'inquilino', titulo: i.nombre,
        info: (i.nif||'') + (i.email ? ' · ' + i.email : ''), page: 'inquilinos' });
    }
  });

  // Buscar en inmuebles por nombre compuesto o referencia catastral
  DB.get('inmuebles').forEach(inm => {
    const nombre = getInmuebleNombre(inm);
    if (nombre.toLowerCase().includes(q) ||
        (inm.referencia_catastral||'').toLowerCase().includes(q)) {
      resultados.push({ tipo: 'inmueble', titulo: nombre, info: inm.tipo || '', page: 'inmuebles' });
    }
  });

  // Buscar contratos por nombre del inquilino asociado
  DB.get('contratos').forEach(c => {
    const inq = DB.getItem('inquilinos', c.inquilino_id);
    const inm = DB.getItem('inmuebles', c.inmueble_id);
    if (inq && (inq.nombre||'').toLowerCase().includes(q)) {
      resultados.push({ tipo: 'contrato', titulo: 'Contrato: ' + inq.nombre,
        info: getInmuebleNombre(inm) + ' · ' + c.estado, page: 'contratos' });
    }
  });

  // Buscar recibos por número de recibo
  DB.get('recibos').forEach(r => {
    if ((r.numero_recibo||'').toLowerCase().includes(q)) {
      resultados.push({ tipo: 'recibo', titulo: r.numero_recibo,
        info: (r.concepto_periodo||'') + (r.estado ? ' · ' + r.estado : ''), page: 'recibos' });
    }
  });

  // Buscar facturas por número de factura
  DB.get('facturas').forEach(f => {
    if ((f.numero_factura||'').toLowerCase().includes(q)) {
      resultados.push({ tipo: 'factura', titulo: f.numero_factura,
        info: (f.cliente_nombre||'') + (f.estado ? ' · ' + f.estado : ''), page: 'facturas' });
    }
  });

  if (resultados.length === 0) {
    panel.innerHTML = `<div class="search-empty">Sin resultados para "${esc(query)}"</div>`;
  } else {
    const badges = { inquilino: 'badge-inquilino', inmueble: 'badge-inmueble', contrato: 'badge-contrato', recibo: 'badge-contrato', factura: 'badge-blue' };
    panel.innerHTML = resultados.slice(0, 8).map(r =>
      `<div class="search-result-item" onclick="navigate('${r.page}');document.getElementById('search-input').value='';document.getElementById('search-results').classList.remove('open')">
        <span class="search-result-badge ${badges[r.tipo]}">${r.tipo}</span>
        <div><div>${esc(r.titulo)}</div><div class="search-result-info">${esc(r.info)}</div></div>
      </div>`
    ).join('');
  }
  panel.classList.add('open');
}

// ===========================
// MODO OSCURO (M-UX01)
// Alterna la clase 'dark' en <body> y guarda la preferencia en localStorage.
// ===========================
function toggleModoOscuro() {
  const isDark = document.body.classList.toggle('dark');
  localStorage.setItem('ag_dark_mode', isDark ? '1' : '0');
  document.getElementById('dark-icon-sun').style.display  = isDark ? 'none' : '';
  document.getElementById('dark-icon-moon').style.display = isDark ? ''     : 'none';
}
// Restaurar preferencia guardada al arrancar la página
(function() {
  if (localStorage.getItem('ag_dark_mode') === '1') {
    document.body.classList.add('dark');
    const sun  = document.getElementById('dark-icon-sun');
    const moon = document.getElementById('dark-icon-moon');
    if (sun)  sun.style.display  = 'none';
    if (moon) moon.style.display = '';
  }
})();

// ===========================
// ATAJOS DE TECLADO (M-UX03)
// Escape → cerrar modal activo
// Alt+D  → Dashboard     Alt+R → Recibos
// Alt+C  → Contratos     Alt+F → Facturas
// Alt+I  → Inquilinos    Alt+G → Generar recibos
// ===========================
document.addEventListener('keydown', function(e) {
  // Escape cierra el modal si está abierto
  if (e.key === 'Escape') {
    if (document.getElementById('modal-overlay').classList.contains('open')) closeModal();
    return;
  }
  // Ignorar atajos si el foco está en un campo de texto
  if (['INPUT','SELECT','TEXTAREA'].includes(document.activeElement.tagName)) return;
  if (!e.altKey) return;
  const mapa = { d: 'dashboard', r: 'recibos', c: 'contratos', f: 'facturas', i: 'inquilinos', g: 'generar' };
  const destino = mapa[e.key.toLowerCase()];
  if (destino) { e.preventDefault(); navigate(destino); }
});

// ===========================
// AVISO DE CAMBIOS SIN GUARDAR (M-UX02)
// Intercepta el cierre del modal si el usuario ha editado algún campo.
// ===========================
let _modalDirty = false;

// Marcar el modal como modificado cuando el usuario escribe en él
document.addEventListener('input', function() {
  if (document.getElementById('modal-overlay').classList.contains('open')) _modalDirty = true;
});

// Envolver closeModal para pedir confirmación si hay cambios sin guardar
const _closeModalOriginal = closeModal;
closeModal = function() {
  if (_modalDirty && !confirm('Hay cambios sin guardar. ¿Deseas cerrar de todas formas?')) return;
  _modalDirty = false;
  _closeModalOriginal();
};

// Cierre forzado sin aviso: usar en funciones de guardado/acción programática
function closeModalForce() {
  _modalDirty = false;
  _closeModalOriginal();
}

// Resetear el flag al abrir un nuevo modal
const _openModalOriginal = openModal;
openModal = function(title, bodyHtml, footerHtml, large) {
  _modalDirty = false;
  _openModalOriginal(title, bodyHtml, footerHtml, large);
};

