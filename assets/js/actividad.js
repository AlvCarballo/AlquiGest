// ============================================================
//  AlquiGest – actividad.js
//  Log de auditoría: registrar acciones y mostrar historial.
//
//  registrarActividad()  → fire-and-forget a api.php?action=log
//  renderActividad()     → página de historial con filtros
// ============================================================

// Mapa de tipos de acción a etiquetas legibles
var _ACT_TIPOS = {
    cobro:              'Cobro registrado',
    anulacion_pago:     'Pago anulado',
    anulacion_recibo:   'Recibo anulado',
    generacion_lote:    'Lote generado',
    factura_generada:   'Factura generada',
    email_enviado:      'Email enviado',
    baja_contrato:      'Baja de contrato',
    subida_ipc:         'Subida IPC / IRAV',
    eliminacion_logica: 'Registro eliminado',
    login_correcto:     'Inicio de sesión',
    login_fallido:      'Intento de acceso fallido',
    logout:             'Cierre de sesión',
    usuario_creado:     'Usuario creado',
    usuario_editado:    'Usuario editado',
    usuario_eliminado:  'Usuario eliminado',
};

// Colores de badge por tipo (variables de tema en vez de hex fijos —
// 08/07/2026, ver UX_UI_MODO_OSCURO_COLORES.md — así se adaptan al modo oscuro)
var _ACT_COLOR = {
    cobro:              'var(--green)',
    anulacion_pago:     'var(--orange)',
    anulacion_recibo:   'var(--red)',
    generacion_lote:    'var(--blue)',
    factura_generada:   'var(--color-info)',
    email_enviado:      'var(--blue)',
    baja_contrato:      'var(--red)',
    subida_ipc:         'var(--orange)',
    eliminacion_logica: 'var(--red)',
    login_correcto:     'var(--green)',
    login_fallido:      'var(--red)',
    logout:             'var(--text-secondary)',
    usuario_creado:     'var(--green)',
    usuario_editado:    'var(--blue)',
    usuario_eliminado:  'var(--red)',
};
var _ACT_BG = {
    cobro:              'var(--green-light)',
    anulacion_pago:     'var(--orange-light)',
    anulacion_recibo:   'var(--red-light)',
    generacion_lote:    'var(--blue-light)',
    factura_generada:   'var(--color-info-light)',
    email_enviado:      'var(--blue-light)',
    baja_contrato:      'var(--red-light)',
    subida_ipc:         'var(--orange-light)',
    eliminacion_logica: 'var(--red-light)',
    login_correcto:     'var(--green-light)',
    login_fallido:      'var(--red-light)',
    logout:             'var(--gray-100)',
    usuario_creado:     'var(--green-light)',
    usuario_editado:    'var(--blue-light)',
    usuario_eliminado:  'var(--red-light)',
};

// Alta/modificación genéricas de entidades de negocio (assets/php/api.php,
// acción 'save'): tipo_accion llega como "alta_<tabla>" / "modificacion_<tabla>".
// En vez de listar cada tabla, se deriva la etiqueta a partir del prefijo.
function _actTipoLabel(tipo) {
    if (_ACT_TIPOS[tipo]) return _ACT_TIPOS[tipo];
    if (tipo.indexOf('alta_') === 0)         return 'Alta en ' + tipo.slice(5);
    if (tipo.indexOf('modificacion_') === 0) return 'Modificación en ' + tipo.slice(13);
    return tipo;
}

// ── registrarActividad ────────────────────────────────────────
// Envía una entrada al log sin bloquear la operación principal.
function registrarActividad(tipo, entidad, entidadId, desc) {
    try {
        fetch('assets/php/api.php?action=log', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tipo_accion: tipo,
                entidad:     entidad   || '',
                entidad_id:  entidadId || null,
                descripcion: desc      || '',
            }),
        }).catch(function () {});
    } catch (e) {}
}

// ── renderActividad ───────────────────────────────────────────
function renderActividad() {
    var hoy    = new Date().toISOString().slice(0, 10);
    var hace30 = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);

    var opcionesTipo = Object.keys(_ACT_TIPOS).map(function (k) {
        return '<option value="' + k + '">' + _ACT_TIPOS[k] + '</option>';
    }).join('');

    document.getElementById('content').innerHTML =
        '<div class="content-header">' +
        '  <div>' +
        '    <h2 style="margin:0">Historial de actividad</h2>' +
        '    <div style="font-size:13px;color:var(--gray-500);margin-top:4px">Registro de acciones realizadas en la aplicación</div>' +
        '  </div>' +
        '</div>' +

        // Filtros
        '<div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-bottom:16px;' +
        '     padding:14px 16px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px">' +

        '  <div style="display:flex;flex-direction:column;gap:4px">' +
        '    <label style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--gray-500)">Desde</label>' +
        '    <input type="date" id="act-desde" value="' + hace30 + '" style="min-width:140px">' +
        '  </div>' +

        '  <div style="display:flex;flex-direction:column;gap:4px">' +
        '    <label style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--gray-500)">Hasta</label>' +
        '    <input type="date" id="act-hasta" value="' + hoy + '" style="min-width:140px">' +
        '  </div>' +

        '  <div style="display:flex;flex-direction:column;gap:4px">' +
        '    <label style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--gray-500)">Tipo de acción</label>' +
        '    <select id="act-tipo" style="min-width:180px">' +
        '      <option value="">Todos los tipos</option>' +
        opcionesTipo +
        '    </select>' +
        '  </div>' +

        '  <div style="display:flex;flex-direction:column;gap:4px">' +
        '    <label style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--gray-500)">Usuario</label>' +
        '    <select id="act-usuario" style="min-width:160px"><option value="">Todos los usuarios</option></select>' +
        '  </div>' +

        '  <button class="btn btn-primary" onclick="_actividadBuscar()">Buscar</button>' +
        '</div>' +

        // Resultados
        '<div id="act-resultados"><div style="text-align:center;padding:40px;color:var(--gray-400)">Cargando…</div></div>';

    _actividadBuscar();
}

// Consulta al servidor y actualiza la tabla
function _actividadBuscar() {
    var desde   = (document.getElementById('act-desde')    || {}).value || '';
    var hasta   = (document.getElementById('act-hasta')    || {}).value || '';
    var tipo    = (document.getElementById('act-tipo')     || {}).value || '';
    var usuario = (document.getElementById('act-usuario')  || {}).value || '';

    var url = 'assets/php/api.php?action=getLog&limite=200';
    if (desde)   url += '&desde='      + encodeURIComponent(desde);
    if (hasta)   url += '&hasta='      + encodeURIComponent(hasta);
    if (tipo)    url += '&tipo='       + encodeURIComponent(tipo);
    if (usuario !== '') url += '&usuario_id=' + encodeURIComponent(usuario);

    var el = document.getElementById('act-resultados');
    if (el) el.innerHTML = '<div style="text-align:center;padding:40px;color:var(--gray-400)">Cargando…</div>';

    fetch(url)
        .then(function (r) { return r.json(); })
        .then(function (filas) {
            _actividadRenderTabla(filas);
            // Solo repoblar el desplegable de usuarios cuando no hay filtro de
            // usuario activo, para no ir perdiendo opciones a medida que se filtra.
            if (usuario === '') _actividadPoblarUsuarios(filas);
        })
        .catch(function () {
            var el2 = document.getElementById('act-resultados');
            if (el2) el2.innerHTML =
                '<div style="padding:16px;background:var(--red-light);color:var(--red);border-radius:8px">' +
                '  Error al cargar el historial. Comprueba que la tabla log_actividad existe (reinstala o actualiza la BD).' +
                '</div>';
        });
}

// Rellena el desplegable "Usuario" con los usuarios distintos presentes en
// los resultados cargados (sin depender de un endpoint solo-admin de listado).
function _actividadPoblarUsuarios(filas) {
    var sel = document.getElementById('act-usuario');
    if (!sel) return;
    var actual = sel.value;
    var vistos = {};
    var opciones = ['<option value="">Todos los usuarios</option>'];
    (filas || []).forEach(function (f) {
        var id = f.usuario_id || 0;
        if (vistos[id]) return;
        vistos[id] = true;
        var label = id ? (f.usuario_nombre || f.usuario_username || ('Usuario #' + id)) : 'Sistema';
        opciones.push('<option value="' + id + '">' + esc(label) + '</option>');
    });
    sel.innerHTML = opciones.join('');
    sel.value = actual;
}

// Renderiza la tabla de resultados
function _actividadRenderTabla(filas) {
    var el = document.getElementById('act-resultados');
    if (!el) return;

    if (!filas || filas.length === 0) {
        el.innerHTML =
            '<div style="text-align:center;padding:60px;color:var(--gray-400)">' +
            '  <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin-bottom:10px;display:block;margin-left:auto;margin-right:auto">' +
            '    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>' +
            '    <polyline points="14 2 14 8 20 8"/>' +
            '    <line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>' +
            '  </svg>' +
            '  <div style="font-size:15px;font-weight:600;margin-bottom:4px">Sin registros</div>' +
            '  <div>No se encontraron registros con los filtros indicados.</div>' +
            '</div>';
        return;
    }

    var filas_html = filas.map(function (f) {
        var label  = _actTipoLabel(f.tipo_accion);
        var color  = _ACT_COLOR[f.tipo_accion]  || 'var(--text-secondary)';
        var bg     = _ACT_BG[f.tipo_accion]     || 'var(--gray-100)';
        var fecha  = (f.fecha || '').replace('T', ' ').slice(0, 16);
        var entInfo = f.entidad
            ? (f.entidad + (f.entidad_id ? ' #' + f.entidad_id : ''))
            : '—';
        var usuario = f.usuario_id
            ? esc(f.usuario_nombre || f.usuario_username || ('Usuario #' + f.usuario_id))
            : '<span style="color:var(--gray-400)">Sistema</span>';
        return '<tr>' +
            '<td style="white-space:nowrap;color:var(--gray-500);font-size:12px">' + fecha + '</td>' +
            '<td><span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;' +
            'background:' + bg + ';color:' + color + '">' + label + '</span></td>' +
            '<td style="font-size:12px">' + usuario + '</td>' +
            '<td style="color:var(--gray-500);font-size:12px">' + entInfo + '</td>' +
            '<td style="font-size:13px">' + esc(f.descripcion || '') + '</td>' +
            '</tr>';
    }).join('');

    el.innerHTML =
        '<table class="data-table">' +
        '  <thead><tr>' +
        '    <th>Fecha</th><th>Tipo</th><th>Usuario</th><th>Entidad</th><th>Detalle</th>' +
        '  </tr></thead>' +
        '  <tbody>' + filas_html + '</tbody>' +
        '</table>' +
        '<p style="font-size:12px;color:var(--gray-400);text-align:right;margin-top:8px">' +
        filas.length + ' registro' + (filas.length !== 1 ? 's' : '') + ' encontrado' + (filas.length !== 1 ? 's' : '') +
        '</p>';
}
