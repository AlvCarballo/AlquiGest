// ============================================================
//  AlquiGest – Módulo de plantillas DOCX
//
//  Sistema de plantillas Word con variables dinámicas.
//  Las variables se escriben en el documento como {{NombreVariable}}
//  y son sustituidas por los valores reales al generar el documento.
//
//  Funciones principales:
//    renderPlantillas()                          → página de gestión
//    generarDocumentoDesdePlantilla(tipo, id, pid) → motor genérico
//    modalVariablesDisponibles()                 → referencia de variables
// ============================================================

// ── Catálogo de variables disponibles (solo para UI) ─────────
// El catálogo autoritativo vive en plantillas.php::catalogoVariables().
// Este objeto JS se usa para mostrar el modal de ayuda y la tabla de vars.
var _VARS_REGISTRO = {
    // ── Sistema: fechas y valores del momento actual ──────────
    'FechaActual':        { grupo: 'Sistema',      desc: 'Fecha de hoy en formato dd/mm/aaaa',                               ej: new Date().toLocaleDateString('es-ES') },
    'FechaHoy':           { grupo: 'Sistema',      desc: 'Fecha larga en español: «29 de Junio del 2026»',                   ej: '29 de Junio del 2026' },
    'AnioActual':         { grupo: 'Sistema',      desc: 'Año actual en cuatro dígitos',                                     ej: new Date().getFullYear().toString() },
    'MesActual':          { grupo: 'Sistema',      desc: 'Nombre del mes actual en español (minúsculas)',                     ej: new Date().toLocaleDateString('es-ES', {month:'long'}) },
    // ── Empresa ──────────────────────────────────────────────
    'NombreEmpresa':      { grupo: 'Empresa',      desc: 'Nombre de la empresa gestora',                                     ej: 'Gestiones García S.L.' },
    'CIFEmpresa':         { grupo: 'Empresa',      desc: 'CIF/NIF de la empresa',                                            ej: 'B12345678' },
    'DireccionEmpresa':   { grupo: 'Empresa',      desc: 'Dirección completa de la empresa',                                 ej: 'Av. Principal 1, 28001 Madrid' },
    'TelefonoEmpresa':    { grupo: 'Empresa',      desc: 'Teléfono de la empresa',                                           ej: '910 000 001' },
    'EmailEmpresa':       { grupo: 'Empresa',      desc: 'Email de la empresa',                                              ej: 'info@empresa.com' },
    'IBANEmpresa':        { grupo: 'Empresa',      desc: 'IBAN de la cuenta bancaria de la empresa',                         ej: 'ES12 3456 7890 1234 5678' },
    // ── Propietario ──────────────────────────────────────────
    'NombrePropietario':  { grupo: 'Propietario',  desc: 'Nombre del propietario del inmueble',                              ej: 'García López, Ana' },
    'NIFPropietario':     { grupo: 'Propietario',  desc: 'NIF/CIF del propietario',                                          ej: '12345678A' },
    'DireccionPropietario':{ grupo: 'Propietario', desc: 'Dirección del propietario',                                        ej: 'Calle Mayor 1, Madrid' },
    // ── Inquilino principal ───────────────────────────────────
    'NombreInquilino':    { grupo: 'Inquilino',    desc: 'Nombre completo del arrendatario principal',                       ej: 'Martínez Ruiz, Pedro' },
    'NIFInquilino':       { grupo: 'Inquilino',    desc: 'NIF/NIE del arrendatario principal',                               ej: '87654321B' },
    'TelefonoInquilino':  { grupo: 'Inquilino',    desc: 'Teléfono o móvil del arrendatario',                                ej: '600 000 001' },
    'EmailInquilino':     { grupo: 'Inquilino',    desc: 'Email del arrendatario',                                           ej: 'pedro@gmail.com' },
    'IBANInquilino':      { grupo: 'Inquilino',    desc: 'IBAN bancario del arrendatario (vacío si no está informado)',       ej: 'ES98 2100 0418 4502 0005 1332' },
    'DireccionInquilino': { grupo: 'Inquilino',    desc: 'Dirección del arrendatario',                                       ej: 'C/ Ejemplo 5, 28001 Madrid' },
    // ── Bloque multiinquilino: principal + todos los secundarios ─
    // Cada marcador debe ocupar un párrafo propio en la plantilla Word (sin otro texto).
    // El bloque se repite una vez por inquilino: primero el principal, luego los secundarios.
    'InicioMultiinquilino':       { grupo: 'Bloque multiinquilino', desc: 'Inicio del bloque — párrafo exclusivo con este marcador',               ej: '{{InicioMultiinquilino}}' },
    'NombreInquilinomultiple':    { grupo: 'Bloque multiinquilino', desc: 'Nombre de cada inquilino dentro del bloque',                            ej: 'Martínez Ruiz, Pedro' },
    'NIFInquilinomultiple':       { grupo: 'Bloque multiinquilino', desc: 'NIF/NIE de cada inquilino dentro del bloque',                           ej: '87654321B' },
    'DireccionInquilinomultiple': { grupo: 'Bloque multiinquilino', desc: 'Dirección de cada inquilino dentro del bloque',                         ej: 'C/ Ejemplo 5, 28001 Madrid' },
    '/InicioMultiinquilino':      { grupo: 'Bloque multiinquilino', desc: 'Fin del bloque — párrafo exclusivo con este marcador',                   ej: '{{/InicioMultiinquilino}}' },
    // ── Bloque repetitivo: solo inquilinos secundarios ─────────
    // Los marcadores deben ocupar un párrafo propio en la plantilla Word (sin otro texto en la línea).
    '#INQUILINOS_SECUNDARIOS':  { grupo: 'Bloque inq. secundarios', desc: 'Inicio del bloque solo-secundarios — párrafo exclusivo',                 ej: '{{#INQUILINOS_SECUNDARIOS}}' },
    'InqNombre':                { grupo: 'Bloque inq. secundarios', desc: 'Nombre del inquilino secundario (solo dentro del bloque)',                ej: 'Ruiz Pérez, Ana' },
    'InqNIF':                   { grupo: 'Bloque inq. secundarios', desc: 'NIF/NIE del inquilino secundario (solo dentro del bloque)',               ej: '44556677E' },
    'InqDireccion':             { grupo: 'Bloque inq. secundarios', desc: 'Dirección del inquilino secundario (solo dentro del bloque)',             ej: 'Av. España 5, 28003 Madrid' },
    'InqTelefono':              { grupo: 'Bloque inq. secundarios', desc: 'Teléfono del inquilino secundario (solo dentro del bloque)',              ej: '600 111 222' },
    'InqEmail':                 { grupo: 'Bloque inq. secundarios', desc: 'Email del inquilino secundario (solo dentro del bloque)',                 ej: 'ana@email.com' },
    '/INQUILINOS_SECUNDARIOS':  { grupo: 'Bloque inq. secundarios', desc: 'Fin del bloque solo-secundarios — párrafo exclusivo',                     ej: '{{/INQUILINOS_SECUNDARIOS}}' },
    // ── Variables numeradas por posición: inquilinos secundarios ─
    // El índice empieza en 1. Si la posición no existe → cadena vacía (nunca error).
    'Inquilinos_Secundarios_Nombre_1':    { grupo: 'Inq. secundarios (pos.)', desc: 'Nombre del 1er inquilino secundario',    ej: 'Martínez Gómez, Carlos' },
    'Inquilinos_Secundarios_NIF_1':       { grupo: 'Inq. secundarios (pos.)', desc: 'NIF del 1er inquilino secundario',       ej: '33445566F' },
    'Inquilinos_Secundarios_Direccion_1': { grupo: 'Inq. secundarios (pos.)', desc: 'Dirección del 1er inquilino secundario', ej: 'Calle Luna 8, 28010 Madrid' },
    'Inquilinos_Secundarios_Telefono_1':  { grupo: 'Inq. secundarios (pos.)', desc: 'Teléfono del 1er inquilino secundario',  ej: '611 222 333' },
    'Inquilinos_Secundarios_Email_1':     { grupo: 'Inq. secundarios (pos.)', desc: 'Email del 1er inquilino secundario',     ej: 'carlos@email.com' },
    'Inquilinos_Secundarios_Nombre_2':    { grupo: 'Inq. secundarios (pos.)', desc: 'Nombre del 2º inquilino secundario',     ej: 'Ruiz Pérez, Ana' },
    'Inquilinos_Secundarios_NIF_2':       { grupo: 'Inq. secundarios (pos.)', desc: 'NIF del 2º inquilino secundario',        ej: '44556677E' },
    'Inquilinos_Secundarios_Direccion_2': { grupo: 'Inq. secundarios (pos.)', desc: 'Dirección del 2º inquilino secundario',  ej: 'Av. España 5, 28003 Madrid' },
    'Inquilinos_Secundarios_Telefono_2':  { grupo: 'Inq. secundarios (pos.)', desc: 'Teléfono del 2º inquilino secundario',   ej: '600 111 222' },
    'Inquilinos_Secundarios_Email_2':     { grupo: 'Inq. secundarios (pos.)', desc: 'Email del 2º inquilino secundario',      ej: 'ana@email.com' },
    'Inquilinos_Secundarios_Nombre_3':    { grupo: 'Inq. secundarios (pos.)', desc: 'Nombre del 3er inquilino secundario (el índice puede continuar: _4, _5…)', ej: 'López Vega, Luis' },
    'Inquilinos_Secundarios_NIF_3':       { grupo: 'Inq. secundarios (pos.)', desc: 'NIF del 3er inquilino secundario',       ej: '55667788G' },
    'Inquilinos_Secundarios_Direccion_3': { grupo: 'Inq. secundarios (pos.)', desc: 'Dirección del 3er inquilino secundario', ej: 'C/ Sol 12, Madrid' },
    'Inquilinos_Secundarios_Telefono_3':  { grupo: 'Inq. secundarios (pos.)', desc: 'Teléfono del 3er inquilino secundario',  ej: '622 333 444' },
    'Inquilinos_Secundarios_Email_3':     { grupo: 'Inq. secundarios (pos.)', desc: 'Email del 3er inquilino secundario',     ej: 'luis@email.com' },
    // ── Inmueble ──────────────────────────────────────────────
    'DireccionInmueble':  { grupo: 'Inmueble',     desc: 'Dirección completa del inmueble arrendado',                        ej: 'Av. Constitución 8 1º A, Madrid' },
    'RefCatastral':       { grupo: 'Inmueble',     desc: 'Referencia catastral del inmueble',                                ej: '1234567AB1234' },
    'TipoInmueble':       { grupo: 'Inmueble',     desc: 'Tipo de inmueble (vivienda, local…)',                              ej: 'Vivienda' },
    'MunicipioInmueble':  { grupo: 'Inmueble',     desc: 'Municipio donde está el inmueble',                                 ej: 'Madrid' },
    'ProvinciaInmueble':  { grupo: 'Inmueble',     desc: 'Provincia del inmueble',                                           ej: 'Madrid' },
    // ── Contrato ─────────────────────────────────────────────
    'FechaInicio':        { grupo: 'Contrato',     desc: 'Fecha de inicio del contrato (dd/mm/aaaa)',                        ej: '01/01/2024' },
    'FechaFin':           { grupo: 'Contrato',     desc: 'Fecha de vencimiento del contrato',                                ej: '31/12/2025' },
    'Duracion':           { grupo: 'Contrato',     desc: 'Duración del contrato en texto',                                   ej: '1 año y 6 meses' },
    'MetodoRevision':     { grupo: 'Contrato',     desc: 'Tipo de revisión anual de la renta',                               ej: 'IPC' },
    'DiaPago':            { grupo: 'Contrato',     desc: 'Día del mes de pago de la renta',                                  ej: '5' },
    'MotivoTemporada':    { grupo: 'Contrato',     desc: 'Motivo del arrendamiento de temporada',                            ej: 'Estudios universitarios 2024-2025' },
    // ── Facturación ──────────────────────────────────────────
    'Renta':              { grupo: 'Facturación',  desc: 'Importe mensual de la renta (con €)',                              ej: '850,00 €' },
    'RentaLetras':        { grupo: 'Facturación',  desc: 'Renta mensual escrita en letras',                                  ej: 'ochocientos cincuenta euros' },
    'IVA':                { grupo: 'Facturación',  desc: 'Porcentaje de IVA del contrato',                                   ej: '21,00%' },
    'IRPF':               { grupo: 'Facturación',  desc: 'Porcentaje de retención IRPF',                                     ej: '15,00%' },
    // ── Fianza ───────────────────────────────────────────────
    'Fianza':             { grupo: 'Fianza',       desc: 'Importe de la fianza (con €)',                                     ej: '1.700,00 €' },
    'FianzaLetras':       { grupo: 'Fianza',       desc: 'Importe de la fianza escrito en letras',                           ej: 'mil setecientos euros' },
    // ── Fiador ───────────────────────────────────────────────
    'NombreFiador':       { grupo: 'Fiador',       desc: 'Nombre completo del fiador solidario',                             ej: 'García Ruiz, José' },
    'NIFFiador':          { grupo: 'Fiador',       desc: 'NIF del fiador solidario',                                         ej: '11223344C' },
    'DireccionFiador':    { grupo: 'Fiador',       desc: 'Dirección completa del fiador',                                    ej: 'Calle Ejemplo 3, Madrid' },
    // ── Fotos y anexos del contrato ───────────────────────────
    // FotosContrato: párrafo exclusivo; abre diálogo de carga de fotos.
    // ListaMuebles: cualquier párrafo; abre cuadro de texto de mobiliario.
    // Ambas comparten el mismo modal al generar.
    'FotosContrato': { grupo: 'Fotos', desc: 'Tabla de fotos embebidas. El párrafo debe contener solo esta variable.', ej: '{{FotosContrato}}' },
    'ListaMuebles':  { grupo: 'Fotos', desc: 'Descripción del mobiliario del inmueble (texto largo, admite saltos de línea).', ej: 'Sofá 3 plazas, mesa de comedor...' },
};

// Tipos de documento con etiquetas legibles
var _TIPOS_DOC = {
    contrato_arrendamiento: 'Contrato de arrendamiento',
    fianza:                 'Fianza',
    renovacion:             'Renovación / Prórroga',
    comunicacion:           'Comunicación',
    otro:                   'Otro',
};

// Estado de paginación de la página de plantillas
var _plantillasPag = 1;

// ── Página principal de plantillas ───────────────────────────
function renderPlantillas() {
    document.getElementById('header-actions').innerHTML =
        '<button class="btn btn-primary" onclick="modalSubirPlantilla()">' +
        '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>' +
        'Subir plantilla</button>' +
        '<button class="btn btn-secondary" onclick="modalVariablesDisponibles()" style="margin-left:8px">' +
        '{ } Variables</button>';

    document.getElementById('content').innerHTML =
        '<div class="content-header">' +
        '  <div>' +
        '    <h2 style="margin:0">Plantillas DOCX</h2>' +
        '    <div style="font-size:13px;color:var(--gray-500);margin-top:4px">' +
        '      Gestiona plantillas Word con variables dinámicas como <code>{{Renta}}</code>, <code>{{NombreInquilino}}</code>, etc.' +
        '    </div>' +
        '  </div>' +
        '</div>' +
        '<div id="plantillas-lista"><div style="text-align:center;padding:60px;color:var(--gray-400)">Cargando…</div></div>';

    _plantillasCargar();
}

// Carga el listado desde el servidor y lo renderiza
function _plantillasCargar() {
    fetch('assets/php/plantillas.php?action=list')
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.ok) { toast('Error al cargar plantillas: ' + res.error, 'error'); return; }
            _plantillasRenderLista(res.plantillas || []);
        })
        .catch(function(e) {
            var el = document.getElementById('plantillas-lista');
            if (el) el.innerHTML =
                '<div style="padding:16px;background:var(--red-light);color:var(--red);border-radius:8px">' +
                'Error al conectar con el servidor: ' + esc(e.message) + '</div>';
        });
}

// Renderiza la lista de plantillas agrupada por tipo
function _plantillasRenderLista(plantillas) {
    var el = document.getElementById('plantillas-lista');
    if (!el) return;

    if (!plantillas.length) {
        el.innerHTML =
            '<div style="text-align:center;padding:60px;color:var(--gray-400)">' +
            '  <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="display:block;margin:0 auto 12px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>' +
            '  <div style="font-size:16px;font-weight:600;margin-bottom:8px">No hay plantillas todavía</div>' +
            '  <div style="font-size:13px">Sube tu primera plantilla DOCX con variables como <code>{{Renta}}</code></div>' +
            '  <button class="btn btn-primary" onclick="modalSubirPlantilla()" style="margin-top:16px">Subir primera plantilla</button>' +
            '</div>';
        return;
    }

    // Agrupar por tipo_documento
    var grupos = {};
    plantillas.forEach(function(p) {
        if (!grupos[p.tipo_documento]) grupos[p.tipo_documento] = [];
        grupos[p.tipo_documento].push(p);
    });

    var html = '';
    Object.keys(grupos).sort().forEach(function(tipo) {
        var label = _TIPOS_DOC[tipo] || tipo;
        html += '<div class="card" style="margin-bottom:16px">' +
                '  <div class="card-header">' +
                '    <div class="card-title">' + esc(label) + ' (' + grupos[tipo].length + ')</div>' +
                '  </div>' +
                '  <table class="data-table">' +
                '    <thead><tr><th>Nombre</th><th>Descripción</th><th>Estado</th><th>Acciones</th></tr></thead>' +
                '    <tbody>';

        grupos[tipo].forEach(function(p) {
            var badgeActiva = p.activa
                ? '<span class="badge badge-green">Activa</span>'
                : '<span class="badge badge-gray">Inactiva</span>';
            var badgeDefault = p.por_defecto
                ? '<span class="badge badge-blue" style="margin-left:4px">Por defecto</span>'
                : '';

            html += '<tr>' +
                '  <td style="font-weight:500">' + esc(p.nombre) + badgeDefault + '</td>' +
                '  <td style="color:var(--gray-500);font-size:12px">' + esc(p.descripcion || '—') + '</td>' +
                '  <td>' + badgeActiva + '</td>' +
                '  <td>' + accionesFila(
                    { label:'Vista previa', cls:'btn-secondary', onclick:"_plantillaPreview(" + p.id + ",'" + p.tipo_documento.replace(/'/g,"\\'") + "')" },
                    [
                      { titulo:'Usar', items:[
                        { label:'Descargar original', icon:'⬇', onclick:"_plantillaDescargar(" + p.id + ")" },
                      ]},
                      { titulo:'Gestionar', items:[
                        { label:'Renombrar', icon:'✎', onclick:"_plantillaRenombrar(" + p.id + ",'" + p.nombre.replace(/\\/g,'\\\\').replace(/'/g,"\\'") + "')" },
                        { label:'Duplicar', icon:'⧉', onclick:"_plantillaDuplicar(" + p.id + ")" },
                        { label: p.activa ? 'Desactivar' : 'Activar', icon: p.activa ? '⏸' : '▶', onclick:"_plantillaSetActiva(" + p.id + "," + (p.activa ? 0 : 1) + ")" },
                        p.por_defecto
                          ? { label:'Quitar por defecto', icon:'✕', onclick:"_plantillaSetDefault(" + p.id + ",'" + p.tipo_documento.replace(/'/g,"\\'") + "',0)" }
                          : { label:'Marcar como por defecto', icon:'⭐', onclick:"_plantillaSetDefault(" + p.id + ",'" + p.tipo_documento.replace(/'/g,"\\'") + "',1)" },
                        { label:'Eliminar', icon:'🗑', danger:true, onclick:"_plantillaEliminar(" + p.id + ",'" + p.nombre.replace(/\\/g,'\\\\').replace(/'/g,"\\'") + "')" },
                      ]},
                    ]
                  ) +
                '  </td>' +
                '</tr>';
        });

        html += '  </tbody></table></div>';
    });

    el.innerHTML = html;
}

// ── Modal: Subir nueva plantilla ──────────────────────────────
function modalSubirPlantilla() {
    var opcionesTipo = Object.keys(_TIPOS_DOC).map(function(k) {
        return '<option value="' + k + '">' + _TIPOS_DOC[k] + '</option>';
    }).join('');

    openModal('Subir plantilla DOCX', `
      <div style="background:var(--color-info-light);border:1px solid var(--color-info-muted);border-radius:8px;padding:12px;margin-bottom:16px;font-size:13px">
        <strong>Cómo crear una plantilla:</strong><br>
        Escribe tus variables en el documento Word usando la sintaxis <code>{{NombreVariable}}</code>.<br>
        Ejemplo: <code>El contrato de <strong>{{DireccionInmueble}}</strong> tiene una renta de <strong>{{Renta}}</strong>.</code>
      </div>
      <div class="form-group">
        <label>Fichero DOCX <span style="color:var(--red)">*</span></label>
        <div class="csv-dropzone" id="pt-dropzone"
             onclick="document.getElementById('pt-file').click()"
             ondragover="event.preventDefault();this.classList.add('drag-over')"
             ondragleave="this.classList.remove('drag-over')"
             ondrop="event.preventDefault();this.classList.remove('drag-over');_plantillaArchivoSeleccionado(event.dataTransfer.files[0])">
          <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="margin-bottom:8px"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          <div id="pt-file-label" style="font-weight:600">Arrastra el DOCX aquí o haz clic</div>
          <div style="font-size:12px;color:var(--gray-400);margin-top:4px">Solo ficheros .docx · Máximo 10 MB</div>
          <input type="file" id="pt-file" accept=".docx" style="display:none" onchange="_plantillaArchivoSeleccionado(this.files[0])">
        </div>
      </div>
      <div class="form-group">
        <label>Nombre de la plantilla <span style="color:var(--red)">*</span></label>
        <input type="text" id="pt-nombre" placeholder="Ej: Contrato de arrendamiento estándar">
      </div>
      <div class="form-group">
        <label>Tipo de documento</label>
        <select id="pt-tipo">${opcionesTipo}</select>
      </div>
      <div class="form-group">
        <label>Descripción (opcional)</label>
        <textarea id="pt-descripcion" rows="2" placeholder="Descripción breve de cuándo usar esta plantilla"></textarea>
      </div>
      <div style="display:flex;align-items:center;gap:8px">
        <input type="checkbox" id="pt-default">
        <label for="pt-default" style="margin:0;font-weight:normal">Marcar como plantilla por defecto para este tipo</label>
      </div>`,
        '<button class="btn btn-primary" onclick="_plantillaSubir()">Subir plantilla</button>' +
        '<button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>'
    );
}

// Actualiza la etiqueta del dropzone al seleccionar fichero
function _plantillaArchivoSeleccionado(file) {
    if (!file) return;
    var etiqueta = document.getElementById('pt-file-label');
    if (etiqueta) etiqueta.textContent = file.name + ' (' + Math.round(file.size / 1024) + ' KB)';

    // Auto-rellenar nombre si está vacío
    var inputNombre = document.getElementById('pt-nombre');
    if (inputNombre && !inputNombre.value.trim()) {
        inputNombre.value = file.name.replace(/\.docx$/i, '').replace(/_/g, ' ');
    }
}

// Envía la plantilla al servidor
function _plantillaSubir() {
    var fileInput = document.getElementById('pt-file');
    var file = fileInput && fileInput.files[0];
    if (!file) { toast('Selecciona un fichero DOCX', 'error'); return; }

    var nombre = (document.getElementById('pt-nombre') || {}).value.trim();
    if (!nombre) { toast('Introduce un nombre para la plantilla', 'error'); return; }

    var fd = new FormData();
    fd.append('archivo', file);
    fd.append('nombre', nombre);
    fd.append('tipo_documento', (document.getElementById('pt-tipo') || {}).value || 'otro');
    fd.append('descripcion',    (document.getElementById('pt-descripcion') || {}).value || '');
    fd.append('por_defecto',    document.getElementById('pt-default').checked ? '1' : '0');

    var btn = document.querySelector('.modal-footer .btn-primary');
    if (btn) { btn.disabled = true; btn.textContent = 'Subiendo…'; }

    fetch('assets/php/plantillas.php?action=upload', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (btn) { btn.disabled = false; btn.textContent = 'Subir plantilla'; }
            if (res.ok) {
                closeModalForce();
                toast('Plantilla subida correctamente', 'success');
                _plantillasCargar();
            } else {
                toast('Error: ' + res.error, 'error');
            }
        })
        .catch(function(e) {
            if (btn) { btn.disabled = false; btn.textContent = 'Subir plantilla'; }
            toast('Error de red: ' + e.message, 'error');
        });
}

// ── Acciones sobre plantillas existentes ──────────────────────

function _plantillaDescargar(id) {
    window.location.href = 'assets/php/plantillas.php?action=download&id=' + id;
}

function _plantillaRenombrar(id, nombreActual) {
    openModal('Renombrar plantilla',
        '<div class="form-group">' +
        '<label>Nuevo nombre</label>' +
        '<input type="text" id="pt-nuevo-nombre" value="' + esc(nombreActual) + '">' +
        '</div>',
        '<button class="btn btn-primary" onclick="_plantillaRenombrarConfirmar(' + id + ')">Guardar</button>' +
        '<button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>'
    );
    setTimeout(function() {
        var inp = document.getElementById('pt-nuevo-nombre');
        if (inp) { inp.focus(); inp.select(); }
    }, 100);
}

function _plantillaRenombrarConfirmar(id) {
    var nombre = (document.getElementById('pt-nuevo-nombre') || {}).value.trim();
    if (!nombre) { toast('El nombre no puede estar vacío', 'error'); return; }
    fetch('assets/php/plantillas.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'rename', id: id, nombre: nombre })
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.ok) { closeModalForce(); toast('Plantilla renombrada', 'success'); _plantillasCargar(); }
        else toast('Error: ' + res.error, 'error');
    });
}

function _plantillaDuplicar(id) {
    fetch('assets/php/plantillas.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'duplicate', id: id })
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.ok) { toast('Plantilla duplicada correctamente', 'success'); _plantillasCargar(); }
        else toast('Error: ' + res.error, 'error');
    });
}

function _plantillaSetActiva(id, activa) {
    fetch('assets/php/plantillas.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'setActiva', id: id, activa: activa })
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.ok) { _plantillasCargar(); }
        else toast('Error: ' + res.error, 'error');
    });
}

function _plantillaSetDefault(id, tipoDocumento, valor) {
    fetch('assets/php/plantillas.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'setDefault', id: id, tipo_documento: tipoDocumento, valor: valor })
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.ok) {
            toast(valor ? 'Plantilla marcada como por defecto' : 'Plantilla quitada como por defecto', 'success');
            _plantillasCargar();
        } else toast('Error: ' + res.error, 'error');
    });
}

function _plantillaEliminar(id, nombre) {
    openModal('Eliminar plantilla',
        '<p>¿Eliminar la plantilla <strong>' + esc(nombre) + '</strong>?</p>' +
        '<p style="color:var(--red);font-size:13px">Esta acción no se puede deshacer. El fichero DOCX también se eliminará del servidor.</p>',
        '<button class="btn btn-danger" onclick="_plantillaEliminarConfirmar(' + id + ')">Eliminar</button>' +
        '<button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>'
    );
}

function _plantillaEliminarConfirmar(id) {
    fetch('assets/php/plantillas.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'delete', id: id })
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.ok) { closeModalForce(); toast('Plantilla eliminada', 'success'); _plantillasCargar(); }
        else toast('Error: ' + res.error, 'error');
    });
}

// ── Vista previa de la plantilla ──────────────────────────────
function _plantillaPreview(plantillaId, tipoDoc) {
    // Determinar tipo de entidad y entidad según tipo de documento
    // Para la preview sin entidad concreta, usamos tipo genérico
    var tipo = tipoDoc === 'contrato_arrendamiento' ? 'contrato' : tipoDoc;
    var entidadId = 0;

    openModal('Vista previa de plantilla',
        '<div style="text-align:center;padding:40px;color:var(--gray-400)">Cargando previsualización…</div>',
        '<button class="btn btn-secondary" onclick="closeModal()">Cerrar</button>',
        false
    );

    fetch('assets/php/plantillas.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'preview', plantilla_id: plantillaId, tipo: tipo, entidad_id: entidadId })
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        var contenido = document.getElementById('modal-body');
        if (!contenido) return;
        if (!res.ok) {
            contenido.innerHTML = '<div style="color:var(--red);padding:12px">' + esc(res.error) + '</div>';
            return;
        }

        var avisoVars = res.variables_desconocidas && res.variables_desconocidas.length
            ? '<div style="background:var(--orange-light);border:1px solid var(--color-warn-muted);border-radius:6px;padding:10px;font-size:12px;margin-bottom:12px">' +
              '<strong>Variables no resueltas (aparecerán como &lt;&lt;…&gt;&gt; en el DOCX):</strong> ' +
              res.variables_desconocidas.map(function(v){ return '<code>' + esc(v) + '</code>'; }).join(', ') +
              '</div>'
            : '';

        contenido.innerHTML =
            '<p style="font-size:12px;color:var(--gray-500);margin-bottom:12px">' +
            'Vista previa con variables de sistema. Las variables en <span style="background:var(--green-light);padding:0 3px">verde</span> están resueltas. ' +
            'Las variables en <span style="background:var(--red-light);padding:0 3px;color:var(--red)">rojo</span> no están disponibles sin seleccionar una entidad.' +
            '</p>' +
            avisoVars +
            res.html;
    })
    .catch(function(e) {
        var contenido = document.getElementById('modal-body');
        if (contenido) contenido.innerHTML = '<div style="color:var(--red)">Error: ' + esc(e.message) + '</div>';
    });
}

// ── Modal: Variables disponibles ──────────────────────────────
function modalVariablesDisponibles() {
    // Agrupar variables por grupo
    var grupos = {};
    Object.keys(_VARS_REGISTRO).forEach(function(key) {
        var v = _VARS_REGISTRO[key];
        if (!grupos[v.grupo]) grupos[v.grupo] = [];
        grupos[v.grupo].push({ var: key, desc: v.desc, ej: v.ej });
    });

    // Orden lógico de grupos (las vars de inquilino juntas; Sistema al principio)
    var _ordenGrupos = [
        'Sistema', 'Empresa', 'Propietario', 'Inquilino',
        'Bloque multiinquilino', 'Bloque inq. secundarios', 'Inq. secundarios (pos.)',
        'Inmueble', 'Contrato', 'Facturación', 'Fianza', 'Fiador', 'Fotos',
    ];
    var gruposOrdenados = Object.keys(grupos).sort(function(a, b) {
        var ia = _ordenGrupos.indexOf(a), ib = _ordenGrupos.indexOf(b);
        if (ia === -1) ia = 999;
        if (ib === -1) ib = 999;
        return ia !== ib ? ia - ib : a.localeCompare(b, 'es');
    });

    var html = '';
    gruposOrdenados.forEach(function(grupo) {
        html += '<div style="margin-bottom:20px">' +
                '<div style="font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.08em;' +
                'color:var(--gray-500);border-bottom:1px solid var(--gray-200);padding-bottom:6px;margin-bottom:10px">' +
                esc(grupo) + '</div>' +
                '<table class="data-table" style="font-size:12px">' +
                '<thead><tr><th>Variable</th><th>Descripción</th><th>Ejemplo</th></tr></thead><tbody>';

        grupos[grupo].forEach(function(v) {
            html += '<tr>' +
                '<td><code style="background:var(--gray-100);padding:2px 6px;border-radius:4px;font-size:11px">{{' + esc(v.var) + '}}</code></td>' +
                '<td style="color:var(--gray-600)">' + esc(v.desc) + '</td>' +
                '<td style="color:var(--gray-400);font-style:italic">' + esc(v.ej) + '</td>' +
                '</tr>';
        });

        html += '</tbody></table></div>';
    });

    openModal('Variables disponibles',
        '<p style="font-size:13px;color:var(--gray-500);margin-bottom:16px">' +
        'Escribe estas variables en tu plantilla Word usando la sintaxis <code style="background:var(--gray-100);padding:2px 6px">{{NombreVariable}}</code>. ' +
        'Se sustituirán automáticamente al generar el documento.</p>' +
        '<div style="max-height:60vh;overflow-y:auto">' + html + '</div>',
        '<button class="btn btn-secondary" onclick="closeModal()">Cerrar</button>',
        false
    );
}

// ============================================================
//  MOTOR GENÉRICO DE GENERACIÓN
// ============================================================

// Punto de entrada para generar un documento DOCX desde cualquier módulo.
// tipo:       'contrato', 'fianza', etc.
// entidadId:  ID del contrato, recibo, etc.
// plantillaId: ID de la plantilla (0 = mostrar selector si hay varias)
function generarDocumentoDesdePlantilla(tipo, entidadId, plantillaId) {
    if (plantillaId) {
        _ejecutarGeneracion(tipo, entidadId, plantillaId);
        return;
    }

    // Sin plantillaId: cargar lista y ofrecer selector
    fetch('assets/php/plantillas.php?action=list')
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.ok || !res.plantillas.length) {
                openModal('Sin plantillas disponibles',
                    '<p>No tienes plantillas DOCX configuradas.</p>' +
                    '<p>Ve a <strong>Configuración → Plantillas</strong> para subir tu primera plantilla.</p>',
                    '<button class="btn btn-primary" onclick="closeModalForce();navigate(\'plantillas\')">Ir a Plantillas</button>' +
                    '<button class="btn btn-secondary" onclick="closeModal()">Cerrar</button>'
                );
                return;
            }

            // Filtrar plantillas activas y del tipo correspondiente
            var tipoDB = tipo === 'contrato' ? 'contrato_arrendamiento' : tipo;
            var coinciden = res.plantillas.filter(function(p) {
                return p.activa && (p.tipo_documento === tipoDB || p.tipo_documento === 'otro');
            });

            // Si hay exactamente una plantilla coincidente, usarla directamente sin selector
            // Si hay varias y una es por defecto, usarla directamente
            var porDefecto = coinciden.find(function(p) { return p.por_defecto; });
            if (coinciden.length === 1) {
                _ejecutarGeneracion(tipo, entidadId, coinciden[0].id);
                return;
            }
            if (porDefecto) {
                _ejecutarGeneracion(tipo, entidadId, porDefecto.id);
                return;
            }
            if (!coinciden.length) coinciden = res.plantillas.filter(function(p) { return p.activa; });
            if (!coinciden.length) {
                toast('No hay plantillas activas disponibles', 'error');
                return;
            }

            // Mostrar selector de plantilla
            var opcionesHtml = coinciden.map(function(p) {
                return '<option value="' + p.id + '"' + (p.por_defecto ? ' selected' : '') + '>' +
                       esc(p.nombre) + ' — ' + esc(_TIPOS_DOC[p.tipo_documento] || p.tipo_documento) + '</option>';
            }).join('');

            openModal('Seleccionar plantilla',
                '<div class="form-group">' +
                '<label>Plantilla a utilizar</label>' +
                '<select id="pt-selector-id">' + opcionesHtml + '</select>' +
                '</div>' +
                '<div style="margin-top:12px">' +
                '<label style="display:flex;align-items:center;gap:8px;font-weight:normal;cursor:pointer">' +
                '<input type="checkbox" id="pt-preview-check"> Mostrar vista previa antes de descargar' +
                '</label></div>',
                '<button class="btn btn-primary" onclick="_generarDesdeSelectorModal(\'' + tipo + '\',' + entidadId + ')">Generar DOCX</button>' +
                '<button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>'
            );
        })
        .catch(function(e) { toast('Error al cargar plantillas: ' + e.message, 'error'); });
}

// Ejecuta la generación desde el modal de selección
function _generarDesdeSelectorModal(tipo, entidadId) {
    var plantillaId = parseInt((document.getElementById('pt-selector-id') || {}).value || '0', 10);
    var preview     = (document.getElementById('pt-preview-check') || {}).checked;
    if (!plantillaId) { toast('Selecciona una plantilla', 'error'); return; }

    if (preview) {
        closeModalForce();
        _plantillaPreviewConEntidad(plantillaId, tipo, entidadId);
    } else {
        closeModalForce();
        _ejecutarGeneracion(tipo, entidadId, plantillaId);
    }
}

// Preview con entidad real (contrato, etc.)
function _plantillaPreviewConEntidad(plantillaId, tipo, entidadId) {
    openModal('Vista previa — Variables resueltas',
        '<div style="text-align:center;padding:40px;color:var(--gray-400)">Resolviendo variables…</div>',
        '<button class="btn btn-primary" onclick="_ejecutarGeneracion(\'' + tipo + '\',' + entidadId + ',' + plantillaId + ');closeModalForce()">Descargar DOCX</button>' +
        '<button class="btn btn-secondary" onclick="closeModal()">Cerrar</button>',
        false
    );

    fetch('assets/php/plantillas.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'preview', plantilla_id: plantillaId, tipo: tipo, entidad_id: entidadId })
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        var cuerpo = document.getElementById('modal-body');
        if (!cuerpo || !res.ok) return;

        var avisoVars = res.variables_desconocidas && res.variables_desconocidas.length
            ? '<div style="background:var(--orange-light);border:1px solid var(--color-warn-muted);border-radius:6px;padding:10px;font-size:12px;margin-bottom:10px">' +
              '<strong>Variables no reconocidas</strong> (se marcarán como &lt;&lt;…&gt;&gt;): ' +
              res.variables_desconocidas.map(function(v){ return '<code>' + esc(v) + '</code>'; }).join(', ') +
              '</div>' : '';

        cuerpo.innerHTML = avisoVars + res.html;
    })
    .catch(function() {});
}

// Inyecta el keyframe de la barra de progreso (una sola vez)
function _inyectarProgressCSS() {
    if (document.getElementById('docx-progress-style')) return;
    var s = document.createElement('style');
    s.id = 'docx-progress-style';
    s.textContent = [
        '@keyframes docx-fill{',
        '  0%{width:0%} 10%{width:20%} 30%{width:45%} 55%{width:62%} 75%{width:74%} 100%{width:82%}',
        '}',
    ].join('');
    document.head.appendChild(s);
}

// Analiza si la plantilla contiene {{FotosContrato}} y/o {{ListaMuebles}} antes de generar.
// Llama a callback(tieneFotos, tieneMuebles).
function _analizarPlantilla(plantillaId, callback) {
    fetch('assets/php/plantillas.php?action=analizar&plantilla_id=' + plantillaId)
        .then(function(r) { return r.json(); })
        .then(function(res) {
            callback(res.ok ? !!res.tiene_fotos : false, res.ok ? !!res.tiene_muebles : false);
        })
        .catch(function() { callback(false, false); });
}

// Ejecuta la descarga del DOCX generado.
// Abre el modal combinado si la plantilla contiene {{FotosContrato}} y/o {{ListaMuebles}}.
function _ejecutarGeneracion(tipo, entidadId, plantillaId) {
    _analizarPlantilla(plantillaId, function(tieneFotos, tieneMuebles) {
        if (tieneFotos || tieneMuebles) {
            _modalFotosContrato(tipo, entidadId, plantillaId, tieneFotos, tieneMuebles);
        } else {
            _ejecutarGeneracionDocx(tipo, entidadId, plantillaId);
        }
    });
}

// Generación estándar sin fotos (antes llamada _ejecutarGeneracion internamente)
function _ejecutarGeneracionDocx(tipo, entidadId, plantillaId) {
    _inyectarProgressCSS();

    // Modal con barra de progreso indeterminada
    openModal('Generando documento…',
        '<div style="padding:12px 0 4px">' +
        '<p style="text-align:center;color:var(--gray-500);font-size:13px;margin-bottom:18px">' +
        'Procesando plantilla y sustituyendo variables…</p>' +
        '<div style="background:var(--gray-200);border-radius:8px;height:10px;overflow:hidden">' +
        '<div id="docx-pbar" style="height:100%;background:var(--blue);border-radius:8px;width:0%;' +
        'animation:docx-fill 6s ease-out forwards"></div>' +
        '</div>' +
        '<p id="docx-pbar-label" style="text-align:center;font-size:11px;color:var(--gray-400);margin-top:8px">Conectando con el servidor…</p>' +
        '</div>',
        '' // sin botones — se cierra automáticamente al terminar
    );

    // Mensaje dinámico que cambia mientras espera
    var _msgs = ['Leyendo plantilla DOCX…', 'Resolviendo variables…', 'Construyendo documento…', 'Casi listo…'];
    var _mi = 0;
    var _ticker = setInterval(function() {
        _mi = (_mi + 1) % _msgs.length;
        var lbl = document.getElementById('docx-pbar-label');
        if (lbl) lbl.textContent = _msgs[_mi];
    }, 1400);

    fetch('assets/php/plantillas.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'generar', plantilla_id: plantillaId, tipo: tipo, entidad_id: entidadId })
    })
    .then(function(r) {
        var varsDesc = r.headers.get('X-AlquiGest-VarsDesconocidas');
        if (!r.ok) {
            return r.json().then(function(e) { throw new Error(e.error || 'Error del servidor'); });
        }
        return r.blob().then(function(blob) { return { blob: blob, varsDesc: varsDesc }; });
    })
    .then(function(data) {
        clearInterval(_ticker);

        // Completar barra al 100 %
        var bar = document.getElementById('docx-pbar');
        var lbl = document.getElementById('docx-pbar-label');
        if (bar) { bar.style.animation = 'none'; bar.style.transition = 'width .25s ease'; bar.style.width = '100%'; }
        if (lbl) lbl.textContent = '¡Listo!';

        setTimeout(function() {
            closeModalForce();

            var url = URL.createObjectURL(data.blob);
            var a   = document.createElement('a');
            a.href     = url;
            a.download = 'documento_' + new Date().toISOString().slice(0,10).replace(/-/g,'') + '.docx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            if (data.varsDesc) toast('Aviso: variables no resueltas → ' + data.varsDesc, 'info');
            toast('Documento generado y descargado', 'success');
        }, 350);
    })
    .catch(function(e) {
        clearInterval(_ticker);
        closeModalForce();
        toast('Error al generar: ' + e.message, 'error');
    });
}

// ============================================================
//  FOTOS EN PLANTILLAS ({{FotosContrato}})
// ============================================================

// Estado del modal combinado (fotos + mobiliario)
var _fotosState = { fotos: [], tipo: '', entidadId: 0, plantillaId: 0, tieneFotos: false, tieneMuebles: false };

// Abre el modal combinado de fotos y/o mobiliario antes de generar el DOCX.
// tieneFotos: la plantilla contiene {{FotosContrato}}
// tieneMuebles: la plantilla contiene {{ListaMuebles}}
function _modalFotosContrato(tipo, entidadId, plantillaId, tieneFotos, tieneMuebles) {
    _fotosState.fotos        = [];
    _fotosState.tipo         = tipo;
    _fotosState.entidadId    = entidadId;
    _fotosState.tieneFotos   = tieneFotos;
    _fotosState.tieneMuebles = tieneMuebles;
    _fotosState.plantillaId = plantillaId;

    // Título dinámico según qué variables especiales tiene la plantilla
    var tituloModal = (tieneFotos && tieneMuebles) ? 'Fotos y mobiliario del contrato'
                    : tieneFotos                   ? 'Fotos del contrato'
                    :                                'Mobiliario del contrato';

    // Sección de fotos (solo si la plantilla tiene {{FotosContrato}})
    var secFotos = tieneFotos ? (
        '<div style="background:var(--color-info-light);border:1px solid var(--color-info-muted);border-radius:8px;padding:10px 14px;' +
        'margin-bottom:14px;font-size:13px">' +
        'La plantilla contiene <code>{{FotosContrato}}</code>. ' +
        'Sube las imágenes que quieres incluir (JPG, PNG o WebP).' +
        '</div>' +
        '<div class="form-group" style="margin-bottom:10px">' +
        '  <label>Número de columnas</label>' +
        '  <select id="fotos-columnas" style="width:120px">' +
        '    <option value="1">1 columna</option>' +
        '    <option value="2" selected>2 columnas</option>' +
        '    <option value="3">3 columnas</option>' +
        '  </select>' +
        '</div>' +
        '<div class="csv-dropzone" id="fotos-dropzone" style="margin-bottom:14px"' +
        '     onclick="document.getElementById(\'fotos-file-input\').click()"' +
        '     ondragover="event.preventDefault();this.classList.add(\'drag-over\')"' +
        '     ondragleave="this.classList.remove(\'drag-over\')"' +
        '     ondrop="event.preventDefault();this.classList.remove(\'drag-over\');_fotosSeleccionar(event.dataTransfer.files)">' +
        '  <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="display:block;margin:0 auto 6px"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>' +
        '  <div style="font-size:13px;font-weight:600">Arrastra fotos aquí o haz clic</div>' +
        '  <div style="font-size:11px;color:var(--gray-400);margin-top:3px">JPG, PNG, WebP · Sin límite de cantidad</div>' +
        '  <input type="file" id="fotos-file-input" accept=".jpg,.jpeg,.png,.webp" multiple style="display:none"' +
        '         onchange="_fotosSeleccionar(this.files)">' +
        '</div>' +
        '<div id="fotos-preview" style="max-height:260px;overflow-y:auto"></div>'
    ) : '';

    // Sección de mobiliario (solo si la plantilla tiene {{ListaMuebles}})
    var secMuebles = tieneMuebles ? (
        '<div style="background:var(--green-light);border:1px solid var(--green);border-radius:8px;padding:10px 14px;' +
        'margin-bottom:12px;font-size:13px' + (tieneFotos ? ';margin-top:16px' : '') + '">' +
        'La plantilla contiene <code>{{ListaMuebles}}</code>. ' +
        'Introduce la descripción del mobiliario incluido en el inmueble.' +
        '</div>' +
        '<div class="form-group">' +
        '  <label for="lista-muebles-input">Descripción del mobiliario</label>' +
        '  <textarea id="lista-muebles-input" rows="5" style="width:100%;resize:vertical;font-family:inherit;font-size:13px"' +
        '    placeholder="Ej: Sofá 3 plazas, mesa de comedor con 4 sillas, cama doble 150x190, frigorífico..."></textarea>' +
        '</div>'
    ) : '';

    openModal(tituloModal, secFotos + secMuebles,
        '<button class="btn btn-primary" id="fotos-btn-generar" onclick="_ejecutarGeneracionConFotos()">Generar DOCX</button>' +
        '<button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>'
    );
    if (tieneFotos) _fotosRender();
}

// Añade archivos seleccionados al estado
function _fotosSeleccionar(files) {
    for (var i = 0; i < files.length; i++) {
        var f = files[i];
        var ext = f.name.split('.').pop().toLowerCase();
        if (['jpg', 'jpeg', 'png', 'webp'].indexOf(ext) < 0) {
            toast('Formato no admitido: ' + f.name + ' (usa JPG, PNG o WebP)', 'error');
            continue;
        }
        _fotosState.fotos.push({ file: f, url: URL.createObjectURL(f), id: Date.now() + Math.random() });
    }
    var inp = document.getElementById('fotos-file-input');
    if (inp) inp.value = '';
    _fotosRender();
}

// Renderiza la cuadrícula de miniaturas con controles
function _fotosRender() {
    var el = document.getElementById('fotos-preview');
    if (!el) return;
    var fotos = _fotosState.fotos;
    if (!fotos.length) {
        el.innerHTML = '<div style="text-align:center;padding:20px;color:var(--gray-400);font-size:13px">' +
                       'Sin fotos añadidas todavía</div>';
        return;
    }

    var cols = parseInt((document.getElementById('fotos-columnas') || {}).value || '2', 10);
    var colPct = Math.floor(100 / cols);

    var html = '<div style="margin-bottom:6px;font-size:12px;color:var(--gray-500)">' +
               fotos.length + ' foto' + (fotos.length !== 1 ? 's' : '') + '</div>' +
               '<div style="display:flex;flex-wrap:wrap;gap:8px">';

    fotos.forEach(function(foto, idx) {
        html += '<div style="width:calc(' + colPct + '% - 8px);box-sizing:border-box;' +
                'border:1px solid var(--gray-200);border-radius:6px;overflow:hidden">' +
                '  <img src="' + foto.url + '" style="width:100%;height:110px;object-fit:cover;display:block">' +
                '  <div style="padding:4px 6px;background:#fff;display:flex;align-items:center;gap:3px">' +
                '    <span style="flex:1;font-size:10px;color:var(--gray-500);overflow:hidden;white-space:nowrap;text-overflow:ellipsis">' +
                esc(foto.file.name) + '</span>' +
                (idx > 0
                    ? '<button onclick="_fotosMover(' + idx + ',-1)" title="Mover izquierda" style="border:none;background:none;cursor:pointer;padding:1px 4px;font-size:13px;color:var(--gray-600)">◄</button>'
                    : '<span style="width:22px"></span>') +
                (idx < fotos.length - 1
                    ? '<button onclick="_fotosMover(' + idx + ',1)" title="Mover derecha" style="border:none;background:none;cursor:pointer;padding:1px 4px;font-size:13px;color:var(--gray-600)">►</button>'
                    : '<span style="width:22px"></span>') +
                '    <button onclick="_fotosEliminar(' + idx + ')" title="Eliminar" ' +
                '     style="border:none;background:none;cursor:pointer;padding:1px 5px;color:var(--red);font-size:15px;font-weight:700">×</button>' +
                '  </div>' +
                '</div>';
    });

    html += '</div>';
    el.innerHTML = html;
}

// Mueve una foto en la lista (dir = -1 izquierda, +1 derecha)
function _fotosMover(idx, dir) {
    var fotos = _fotosState.fotos;
    var newIdx = idx + dir;
    if (newIdx < 0 || newIdx >= fotos.length) return;
    var tmp = fotos[idx];
    fotos[idx] = fotos[newIdx];
    fotos[newIdx] = tmp;
    _fotosRender();
}

// Elimina una foto de la lista y libera su object URL
function _fotosEliminar(idx) {
    var removida = _fotosState.fotos.splice(idx, 1)[0];
    if (removida) URL.revokeObjectURL(removida.url);
    _fotosRender();
}

// Envía el DOCX con fotos al servidor usando multipart/form-data
function _ejecutarGeneracionConFotos() {
    var fotos    = _fotosState.fotos;
    var columnas = parseInt((document.getElementById('fotos-columnas') || {}).value || '2', 10);

    var fd = new FormData();
    fd.append('action',       'generarConFotos');
    fd.append('plantilla_id', _fotosState.plantillaId);
    fd.append('tipo',         _fotosState.tipo);
    fd.append('entidad_id',   _fotosState.entidadId);
    fd.append('columnas',     columnas);
    fotos.forEach(function(f) { fd.append('fotos[]', f.file, f.file.name); });

    // Texto del mobiliario (solo si la plantilla tiene {{ListaMuebles}})
    if (_fotosState.tieneMuebles) {
        var listaMuebles = (document.getElementById('lista-muebles-input') || {}).value || '';
        fd.append('lista_muebles', listaMuebles);
    }

    fotos.forEach(function(f) { URL.revokeObjectURL(f.url); });

    closeModalForce();
    _inyectarProgressCSS();

    var _msgs = ['Subiendo fotos…', 'Procesando imágenes…', 'Construyendo tabla…', 'Casi listo…'];
    var _mi = 0;
    var n = fotos.length;

    openModal('Generando documento con fotos…',
        '<div style="padding:12px 0 4px">' +
        '<p style="text-align:center;color:var(--gray-500);font-size:13px;margin-bottom:18px">' +
        'Insertando ' + n + ' foto' + (n !== 1 ? 's' : '') + ' en el documento…</p>' +
        '<div style="background:var(--gray-200);border-radius:8px;height:10px;overflow:hidden">' +
        '<div id="docx-pbar" style="height:100%;background:var(--blue);border-radius:8px;width:0%;' +
        'animation:docx-fill 8s ease-out forwards"></div>' +
        '</div>' +
        '<p id="docx-pbar-label" style="text-align:center;font-size:11px;color:var(--gray-400);margin-top:8px">Subiendo…</p>' +
        '</div>',
        ''
    );

    var _ticker = setInterval(function() {
        _mi = (_mi + 1) % _msgs.length;
        var lbl = document.getElementById('docx-pbar-label');
        if (lbl) lbl.textContent = _msgs[_mi];
    }, 1800);

    fetch('assets/php/plantillas.php', { method: 'POST', body: fd })
    .then(function(r) {
        var varsDesc = r.headers.get('X-AlquiGest-VarsDesconocidas');
        if (!r.ok) {
            return r.json().then(function(e) { throw new Error(e.error || 'Error del servidor'); });
        }
        return r.blob().then(function(blob) { return { blob: blob, varsDesc: varsDesc }; });
    })
    .then(function(data) {
        clearInterval(_ticker);
        var bar = document.getElementById('docx-pbar');
        var lbl = document.getElementById('docx-pbar-label');
        if (bar) { bar.style.animation = 'none'; bar.style.transition = 'width .25s ease'; bar.style.width = '100%'; }
        if (lbl) lbl.textContent = '¡Listo!';
        setTimeout(function() {
            closeModalForce();
            var url = URL.createObjectURL(data.blob);
            var a   = document.createElement('a');
            a.href     = url;
            a.download = 'documento_' + new Date().toISOString().slice(0,10).replace(/-/g,'') + '.docx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            if (data.varsDesc) toast('Aviso: variables no resueltas → ' + data.varsDesc, 'info');
            toast('Documento con fotos generado y descargado', 'success');
        }, 350);
    })
    .catch(function(e) {
        clearInterval(_ticker);
        closeModalForce();
        toast('Error al generar con fotos: ' + e.message, 'error');
    });
}
