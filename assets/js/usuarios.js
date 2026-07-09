// ===========================
// GESTIÓN DE USUARIOS (solo admin)
// La UI solo se muestra a administradores (ver ux.js / AlquiGest.php), pero
// la protección real está en el backend (api.php: listUsuarios/saveUsuario/
// deleteUsuario exigen sesión con rol 'admin'). Si un usuario normal llega
// aquí (URL manipulada, consola, etc.), el fetch devuelve 403 y se muestra
// el mensaje de "sin permisos" en vez de la tabla.
// ===========================

function renderUsuarios() {
  document.getElementById('content').innerHTML =
    '<div class="content-header">' +
    '  <div>' +
    '    <h2 style="margin:0">Usuarios</h2>' +
    '    <div style="font-size:13px;color:var(--gray-500);margin-top:4px">Administradores y usuarios con acceso a la aplicación</div>' +
    '  </div>' +
    '  <button class="btn btn-primary" onclick="modalUsuario()">+ Nuevo usuario</button>' +
    '</div>' +
    '<div id="usuarios-tabla-wrap"><div style="text-align:center;padding:40px;color:var(--gray-400)">Cargando…</div></div>';

  _usuariosCargar();
}

function _usuariosCargar() {
  fetch('assets/php/api.php?action=listUsuarios')
    .then(r => r.json().then(data => ({ status: r.status, data })))
    .then(({ status, data }) => {
      if (status === 403 || data.ok === false) {
        document.getElementById('usuarios-tabla-wrap').innerHTML =
          '<div style="padding:24px;background:var(--red-light);color:var(--red);border-radius:8px">' +
          esc(data.error || 'No tienes permisos para acceder a esta sección.') + '</div>';
        return;
      }
      _usuariosRenderTabla(data.usuarios || []);
    })
    .catch(() => {
      document.getElementById('usuarios-tabla-wrap').innerHTML =
        '<div style="padding:24px;background:var(--red-light);color:var(--red);border-radius:8px">Error al cargar los usuarios.</div>';
    });
}

function _usuariosRenderTabla(usuarios) {
  const filas = usuarios.map(u => {
    const rolBadge = u.rol === 'admin'
      ? '<span class="badge badge-blue">Administrador</span>'
      : '<span class="badge badge-gray">Usuario</span>';
    const estadoBadge = u.activo == 1
      ? '<span class="badge badge-green">Activo</span>'
      : '<span class="badge badge-red">Inactivo</span>';
    const esYo = window.AG_USER && window.AG_USER.id === u.id;
    return '<tr>' +
      '<td><strong>' + esc(u.nombre) + '</strong>' + (esYo ? ' <span style="color:var(--gray-400);font-size:11px">(tú)</span>' : '') + '</td>' +
      '<td>' + esc(u.username) + '</td>' +
      '<td>' + esc(u.email || '—') + '</td>' +
      '<td>' + rolBadge + '</td>' +
      '<td>' + estadoBadge + '</td>' +
      '<td style="font-size:12px;color:var(--gray-500)">' + (u.ultimo_login ? esc(u.ultimo_login) : 'Nunca') + '</td>' +
      '<td>' +
      '  <button class="btn btn-sm btn-secondary" onclick=\'modalUsuario(' + JSON.stringify(u).replace(/'/g, "&#39;") + ')\'>Editar</button> ' +
      (esYo ? '' : '<button class="btn btn-sm btn-danger" onclick="eliminarUsuario(' + u.id + ',' + JSON.stringify(u.username) + ')">Eliminar</button>') +
      '</td>' +
      '</tr>';
  }).join('');

  document.getElementById('usuarios-tabla-wrap').innerHTML =
    '<table class="data-table">' +
    '  <thead><tr><th>Nombre</th><th>Usuario</th><th>Email</th><th>Rol</th><th>Estado</th><th>Último acceso</th><th></th></tr></thead>' +
    '  <tbody>' + (filas || '<tr><td colspan="7" style="text-align:center;color:var(--gray-400);padding:24px">Sin usuarios</td></tr>') + '</tbody>' +
    '</table>';
}

// ── Modal de alta/edición ──────────────────────────────────────
function modalUsuario(u) {
  const esEdicion = !!(u && u.id);
  const titulo = esEdicion ? 'Editar usuario' : 'Nuevo usuario';
  openModal(titulo,
    '<form id="form-usuario" class="form-grid form-grid-2">' +
    '  <div class="form-group">' +
    '    <label>Nombre completo *</label>' +
    '    <input name="nombre" required value="' + esc(u ? u.nombre : '') + '">' +
    '  </div>' +
    '  <div class="form-group">' +
    '    <label>Email</label>' +
    '    <input name="email" type="email" value="' + esc(u ? (u.email || '') : '') + '">' +
    '  </div>' +
    '  <div class="form-group">' +
    '    <label>Usuario (login) *</label>' +
    '    <input name="username" required pattern="[a-zA-Z0-9._\\-]{3,60}" value="' + esc(u ? u.username : '') + '" ' + (esEdicion ? '' : 'autofocus') + '>' +
    '  </div>' +
    '  <div class="form-group">' +
    '    <label>Rol *</label>' +
    '    <select name="rol">' +
    '      <option value="user"' + (u && u.rol === 'user' ? ' selected' : '') + '>Usuario</option>' +
    '      <option value="admin"' + (u && u.rol === 'admin' ? ' selected' : '') + '>Administrador</option>' +
    '    </select>' +
    '  </div>' +
    '  <div class="form-group">' +
    '    <label>' + (esEdicion ? 'Nueva contraseña (dejar en blanco para no cambiarla)' : 'Contraseña *') + '</label>' +
    '    <input name="password" type="password" autocomplete="new-password" minlength="8" ' + (esEdicion ? '' : 'required') + '>' +
    '    <small style="color:var(--gray-400)">Mínimo 8 caracteres.</small>' +
    '  </div>' +
    '  <div class="form-group">' +
    '    <label style="display:flex;align-items:center;gap:6px;font-weight:400"><input type="checkbox" name="activo" style="width:auto"' + (!u || u.activo == 1 ? ' checked' : '') + '> Activo</label>' +
    '  </div>' +
    '</form>',
    '<button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>' +
    '<button class="btn btn-primary" onclick="guardarUsuario(' + (esEdicion ? u.id : 'null') + ')">Guardar</button>'
  );
}

async function guardarUsuario(id) {
  const form = document.getElementById('form-usuario');
  if (!form.checkValidity()) { form.reportValidity(); return; }
  const data = Object.fromEntries(new FormData(form));
  data.activo = form.activo.checked ? 1 : 0;
  if (id) data.id = id;
  data._csrf = window.AG_CSRF;

  const resp = await fetch('assets/php/api.php?action=saveUsuario', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  const result = await resp.json().catch(() => null);
  if (!result || !result.ok) {
    toast((result && result.error) || 'Error al guardar el usuario', 'error');
    return;
  }
  closeModalForce();
  toast(id ? 'Usuario actualizado' : 'Usuario creado', 'info');
  renderUsuarios();
}

async function eliminarUsuario(id, username) {
  if (!confirm('¿Eliminar el usuario "' + username + '"? Podrá conservarse en el historial de auditoría, pero no podrá volver a iniciar sesión.')) return;
  const resp = await fetch('assets/php/api.php?action=deleteUsuario&id=' + id + '&_csrf=' + encodeURIComponent(window.AG_CSRF), {
    method: 'POST',
  });
  const result = await resp.json().catch(() => null);
  if (!result || !result.ok) {
    toast((result && result.error) || 'Error al eliminar el usuario', 'error');
    return;
  }
  toast('Usuario eliminado', 'info');
  renderUsuarios();
}
