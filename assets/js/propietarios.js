// ===========================
// PROPIETARIOS
// CRUD completo: lista, crear, editar y eliminar propietarios.
// La eliminación está bloqueada si el propietario tiene fincas asociadas.
// ===========================
let _propietariosPag = 1;
function renderPropietarios() {
  const items = DB.get('propietarios');
  const _PROP_PP = Math.max(5, parseInt(_cfgGet('filas_propietarios', '20')) || 20);
  const _propTotPag = Math.max(1, Math.ceil(items.length / _PROP_PP));
  _propietariosPag = Math.max(1, Math.min(_propietariosPag, _propTotPag));
  const itemsPag = items.slice((_propietariosPag - 1) * _PROP_PP, _propietariosPag * _PROP_PP);
  document.getElementById('header-actions').innerHTML = `
    <button class="btn btn-primary" onclick="modalPropietario()">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nuevo propietario
    </button>`;

  document.getElementById('content').innerHTML = `
    <div class="card">
      <div class="card-header">
        <div class="card-title">Propietarios (${items.length})</div>
        <div class="search-bar" style="width:260px">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="search" placeholder="Buscar..." oninput="filterTable(this,'tbl-propietarios',[0,1,2])">
        </div>
      </div>
      <div class="table-wrap">
        <table id="tbl-propietarios">
          <thead><tr><th>Nombre</th><th>NIF</th><th>Teléfono</th><th>Email</th><th>Inmuebles</th><th></th></tr></thead>
          <tbody>
            ${items.length ? (() => {
              const _fincasByProp = {};
              DB.get('fincas').forEach(f => { (_fincasByProp[f.propietario_id] ||= []).push(f.id); });
              const _inmByFinca = {};
              DB.get('inmuebles').forEach(i => { _inmByFinca[i.finca_id] = (_inmByFinca[i.finca_id] || 0) + 1; });
              return itemsPag.map(p => {
                const fincaIds = _fincasByProp[p.id] || [];
                const ninm = fincaIds.reduce((s, fid) => s + (_inmByFinca[fid] || 0), 0);
                return `<tr>
                <td><strong>${esc(p.nombre)}</strong></td>
                <td>${esc(p.nif||'-')}</td>
                <td>${esc(p.telefono||'-')}</td>
                <td>${esc(p.email||'-')}</td>
                <td><span class="badge badge-blue">${ninm}</span></td>
                <td class="td-actions">
                  ${_cfgVisi('VisiIRPFProp') ? `<button class="btn btn-sm btn-secondary" style="font-size:11px" title="Informe IRPF anual" onclick="modalInformeIRPFProp(${p.id})">IRPF</button>` : ''}
                  <button class="btn btn-sm btn-secondary btn-icon" title="Editar" onclick="modalPropietario(${p.id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  </button>
                  ${_cfgVisi('VisiBorrarProp') ? `<button class="btn btn-sm btn-danger btn-icon" title="Eliminar" onclick="deletePropietario(${p.id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                  </button>` : ''}
                </td>
              </tr>`;
              }).join('');
            })() : '<tr><td colspan="6"><div class="empty-state"><svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg><p>Sin propietarios</p><small>Pulsa "Nuevo propietario" para comenzar</small></div></td></tr>'}
          </tbody>
        </table>
      </div>
      ${_propTotPag > 1 ? `
        <div class="table-pagination">
          <button class="btn btn-sm btn-secondary" onclick="_propietariosPag=${_propietariosPag-1};renderPropietarios()" ${_propietariosPag<=1?'disabled':''}>‹ Ant.</button>
          <span>Página ${_propietariosPag} de ${_propTotPag} · ${items.length} propietarios</span>
          <button class="btn btn-sm btn-secondary" onclick="_propietariosPag=${_propietariosPag+1};renderPropietarios()" ${_propietariosPag>=_propTotPag?'disabled':''}>Sig. ›</button>
        </div>` : ''}
    </div>
  `;
  makeTableSortable('tbl-propietarios');
}

function modalPropietario(id=null) {
  const p = id ? DB.getItem('propietarios', id) : {};
  openModal(id ? 'Editar propietario' : 'Nuevo propietario', `
    <form id="form-propietario" class="form-grid form-grid-2">
      <div class="form-group" style="grid-column:1/-1">
        <div class="form-section-title">Datos personales</div>
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>Nombre completo *</label>
        <input name="nombre" value="${p.nombre||''}" required placeholder="Apellidos, Nombre o Razón Social">
      </div>
      <div class="form-group">
        <label>NIF / CIF</label>
        <input name="nif" value="${p.nif||''}" placeholder="12345678A">
      </div>
      <div class="form-group">
        <label>Teléfono</label>
        <input name="telefono" value="${p.telefono||''}" placeholder="600 000 000">
      </div>
      <div class="form-group">
        <label>Email</label>
        <input name="email" type="email" value="${p.email||''}" placeholder="propietario@email.com">
      </div>
      <div class="form-group">
        <label>Tipo IRPF</label>
        <select name="irpf">
          <option value="" ${!p.irpf?'selected':''}>Sin IRPF</option>
          <option value="F" ${p.irpf==='F'?'selected':''}>Física</option>
          <option value="J" ${p.irpf==='J'?'selected':''}>Jurídica</option>
        </select>
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <div class="form-section-title" style="margin-top:4px">Dirección</div>
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>Dirección</label>
        <input name="direccion" value="${p.direccion||''}" placeholder="Calle, número, piso...">
      </div>
      <div class="form-group">
        <label>C.P.</label>
        <input name="cp" value="${p.cp||''}" placeholder="28001">
      </div>
      <div class="form-group">
        <label>Municipio</label>
        <input name="municipio" value="${p.municipio||''}" placeholder="Madrid">
      </div>
      <div class="form-group">
        <label>Provincia</label>
        <input name="provincia" value="${p.provincia||''}" placeholder="Madrid">
      </div>
      <div class="form-group">
        <label>País</label>
        <input name="pais" value="${p.pais||'España'}" placeholder="España">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <div class="form-section-title" style="margin-top:4px">Datos bancarios</div>
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>IBAN</label>
        <input name="iban" value="${p.iban||''}" placeholder="ES00 0000 0000 0000 0000 0000">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>Observaciones</label>
        <textarea name="observaciones">${p.observaciones||''}</textarea>
      </div>
    </form>
  `, `
    <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
    <button class="btn btn-primary" onclick="savePropietario(${id||'null'})">Guardar</button>
  `);
}

async function savePropietario(id) {
  const form = document.getElementById('form-propietario');
  if (!form.checkValidity()) { form.reportValidity(); return; }
  const data = Object.fromEntries(new FormData(form));
  if (id) data.id = id;
  await DB.save('propietarios', data);
  closeModalForce();
  toast(id ? 'Propietario actualizado' : 'Propietario creado');
  renderPropietarios();
}

async function deletePropietario(id) {
  const inm = DB.get('inmuebles').filter(i => i.propietario_id === id);
  if (inm.length) { toast('No se puede eliminar: tiene inmuebles asociados', 'error'); return; }
  if (!confirm('¿Eliminar este propietario?')) return;
  await DB.delete('propietarios', id);
  toast('Propietario eliminado', 'info');
  renderPropietarios();
}

// ===========================
// INFORME IRPF ANUAL POR PROPIETARIO [14]
// Permite seleccionar el año y descargar un Excel con los recibos
// cobrados de los inmuebles del propietario seleccionado.
// ===========================
function modalInformeIRPFProp(propietarioId) {
  const p = DB.getItem('propietarios', propietarioId);
  if (!p) return;
  const anyoActual = new Date().getFullYear();
  openModal(
    `Informe IRPF anual — ${esc(p.nombre)}`,
    `<p style="color:var(--gray-600);margin-bottom:16px;font-size:14px">
      Descarga un Excel con todos los recibos cobrados de los inmuebles de este propietario en el año seleccionado.
    </p>
    <div class="form-group">
      <label>Año fiscal</label>
      <select id="irpf-prop-anyo" style="width:150px;padding:6px 8px">
        ${[anyoActual, anyoActual-1, anyoActual-2, anyoActual-3].map(function(y) {
          return '<option value="' + y + '">' + y + '</option>';
        }).join('')}
      </select>
    </div>`,
    `<button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
    <button class="btn btn-primary" onclick="descargarInformeIRPFProp(${propietarioId})">Descargar Excel</button>`
  );
}

function descargarInformeIRPFProp(propietarioId) {
  const anyo = parseInt(document.getElementById('irpf-prop-anyo')?.value) || new Date().getFullYear();
  // Redirige a export.php con el caso irpf-propietario y los parámetros necesarios
  const url = `assets/php/export.php?tipo=irpf-propietario&propietario_id=${propietarioId}&anyo=${anyo}`;
  window.open(url, '_blank');
  closeModalForce();
}
