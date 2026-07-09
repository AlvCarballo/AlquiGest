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
  // Los gráficos del Dashboard (Chart.js) llevan sus colores calculados en el
  // momento de crearse — sin esto, si se cambia de tema estando ya en el
  // Dashboard, se quedaban con los colores del tema anterior hasta navegar
  // fuera y volver (08/07/2026, ver UX_UI_MODO_OSCURO_COLORES.md §2/§4).
  if (typeof _redibujarGraficosDashboard === 'function') _redibujarGraficosDashboard();
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
// ACCESIBILIDAD DE TECLADO — MENÚ LATERAL
// Los .nav-item son <div onclick>, no <button>/<a>: con tabindex="0" y
// role="button" (añadidos en AlquiGest.php) ya entran en el orden de
// tabulación, pero un div no activa su onclick con Enter/Espacio de forma
// nativa como sí hace un <button> — hay que simularlo aquí (08/07/2026,
// ver UX_UI_MODO_OSCURO_COLORES.md §2/§4 C06).
// ===========================
document.addEventListener('keydown', function(e) {
  if ((e.key === 'Enter' || e.key === ' ') && e.target.classList && e.target.classList.contains('nav-item')) {
    e.preventDefault();
    e.target.click();
  }
});

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

// ===========================
// MENÚ DE USUARIO Y SESIÓN
// window.AG_USER / window.AG_CSRF los inyecta AlquiGest.php desde la sesión
// PHP (assets/php/auth.php). El backend es quien decide permisos: aquí solo
// se adapta la interfaz (ocultar "Usuarios" si no es admin, etc.).
// ===========================
function _iniciarUX_Usuario() {
  const esAdmin = !!(window.AG_USER && window.AG_USER.rol === 'admin');
  const navUsuarios = document.getElementById('nav-usuarios');
  if (navUsuarios) navUsuarios.style.display = esAdmin ? '' : 'none';
}
document.addEventListener('DOMContentLoaded', _iniciarUX_Usuario);
// init.js navega antes de que a veces termine DOMContentLoaded en algunos navegadores;
// llamar también de forma inmediata cubre ese caso sin duplicar efectos visibles.
_iniciarUX_Usuario();

function toggleUserMenu(e) {
  e.stopPropagation();
  const panel = document.getElementById('user-menu-panel');
  const isOpen = panel.style.display === 'block';
  panel.style.display = isOpen ? 'none' : 'block';
  if (!isOpen) _renderUserMenuPanel();
}

function _renderUserMenuPanel() {
  const panel = document.getElementById('user-menu-panel');
  const u = window.AG_USER;
  if (!u) { panel.innerHTML = ''; return; }
  const rolLabel = u.rol === 'admin' ? 'Administrador' : 'Usuario';
  panel.innerHTML =
    '<div class="user-menu-header">' +
    '  <div class="um-nombre">' + esc(u.nombre) + '</div>' +
    '  <span class="um-rol">' + rolLabel + '</span>' +
    '</div>' +
    (u.rol === 'admin'
      ? '<button class="user-menu-item" onclick="toggleUserMenu(event);navigate(\'usuarios\')">👥 Gestión de usuarios</button>'
      : '') +
    '<button class="user-menu-item danger" onclick="cerrarSesion()">⎋ Cerrar sesión</button>';
}

document.addEventListener('click', e => {
  const wrap = document.getElementById('user-menu-wrap');
  if (wrap && !wrap.contains(e.target)) {
    const panel = document.getElementById('user-menu-panel');
    if (panel) panel.style.display = 'none';
  }
});

async function cerrarSesion() {
  try {
    await fetch('assets/php/api.php?action=logout', { method: 'POST' });
  } catch (e) { /* si falla la petición, igualmente redirigimos a login */ }
  window.location.href = 'login.php';
}

