// ===========================
// FINCAS / EDIFICIOS
// Cada finca agrupa uno o varios inmuebles y pertenece a un propietario.
// La eliminación está bloqueada si la finca tiene inmuebles asociados.
// Desde la lista se puede navegar directamente a los inmuebles de la finca.
// ===========================
let _fincasPag = 1;
function renderFincas() {
  const fincas = DB.get('fincas');
  const propietarios = DB.get('propietarios');
  const _FINC_PP = Math.max(5, parseInt(_cfgGet('filas_fincas', '20')) || 20);
  const _fincTotPag = Math.max(1, Math.ceil(fincas.length / _FINC_PP));
  _fincasPag = Math.max(1, Math.min(_fincasPag, _fincTotPag));
  const fincasPag = fincas.slice((_fincasPag - 1) * _FINC_PP, _fincasPag * _FINC_PP);
  document.getElementById('header-actions').innerHTML = `
    <button class="btn btn-primary" onclick="modalFinca()">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nueva finca
    </button>`;
  document.getElementById('content').innerHTML = `
    <div class="card">
      <div class="card-header">
        <div class="card-title">Fincas / Edificios (${fincas.length})</div>
        <div class="search-bar" style="width:260px">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="search" placeholder="Buscar..." oninput="filterTable(this,'tbl-fincas',[0,1])">
        </div>
      </div>
      <div class="table-wrap">
        <table id="tbl-fincas">
          <thead><tr><th>Nombre / Dirección</th><th>Propietario</th><th>Pisos</th><th>Recibos</th><th></th></tr></thead>
          <tbody>
            ${fincas.length ? (() => {
              const _propMap = new Map(propietarios.map(p => [p.id, p]));
              const _inmByFinca = {};
              const _recByFinca = {};
              DB.get('inmuebles').forEach(i => { (_inmByFinca[i.finca_id] ||= []).push(i.id); });
              DB.get('recibos').forEach(r => { _recByFinca[r.inmueble_id] = (_recByFinca[r.inmueble_id] || 0) + 1; });
              return fincasPag.map(f => {
                const prop = _propMap.get(f.propietario_id);
                const inmIds = _inmByFinca[f.id] || [];
                const nInm = inmIds.length;
                const nRec = inmIds.reduce((s, id) => s + (_recByFinca[id] || 0), 0);
                return `<tr>
                <td>
                  <strong>${esc(f.nombre||[f.sigla,f.calle,f.numero].filter(Boolean).join(' '))}</strong>
                  <br><small style="color:var(--gray-400)">${esc(f.cp||'')} ${esc(f.municipio||'')} ${f.provincia?'('+esc(f.provincia)+')':''}</small>
                </td>
                <td>${prop ? esc(prop.nombre) : '<span style="color:var(--red)">Sin asignar</span>'}</td>
                <td><span class="badge badge-blue">${nInm} pisos</span></td>
                <td><span class="badge badge-orange">${nRec} recibos</span></td>
                <td class="td-actions">${accionesFila(
                  { label:'Ver pisos', cls:'btn-secondary', onclick:`navigate('inmuebles',{finca_id:${f.id},titulo:'Pisos – ${(f.nombre||f.calle||'').replace(/'/g,"\\'")}'})` },
                  [{ titulo:'Gestión', items:[
                    { label:'Ver recibos', icon:'🧾', onclick:`navigate('recibos',{finca_id:${f.id},titulo:'Recibos – ${(f.nombre||f.calle||'').replace(/'/g,"\\'")}'})` },
                    { label:'Editar', icon:'✎', onclick:`modalFinca(${f.id})` },
                    { label:'Eliminar', icon:'🗑', danger:true, onclick:`deleteFinca(${f.id})`, oculto: !_cfgVisi('VisiBorrarFinc') },
                  ]}]
                )}</td>
              </tr>`;
              }).join('');
            })() : `<tr><td colspan="5"><div class="empty-state">
              <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="15" rx="1"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
              <p>Sin fincas</p><small>Pulsa "Nueva finca" para comenzar</small></div></td></tr>`}
          </tbody>
        </table>
      </div>
      ${_fincTotPag > 1 ? `
        <div class="table-pagination">
          <button class="btn btn-sm btn-secondary" onclick="_fincasPag=${_fincasPag-1};renderFincas()" ${_fincasPag<=1?'disabled':''}>‹ Ant.</button>
          <span>Página ${_fincasPag} de ${_fincTotPag} · ${fincas.length} fincas</span>
          <button class="btn btn-sm btn-secondary" onclick="_fincasPag=${_fincasPag+1};renderFincas()" ${_fincasPag>=_fincTotPag?'disabled':''}>Sig. ›</button>
        </div>` : ''}
    </div>`;
  makeTableSortable('tbl-fincas');
}

function modalFinca(id=null) {
  const f = id ? DB.getItem('fincas', id) : {};
  const propietarios = DB.get('propietarios');
  openModal(id ? 'Editar finca' : 'Nueva finca / edificio', `
    <form id="form-finca" class="form-grid form-grid-2">
      <div class="form-group" style="grid-column:1/-1">
        <label>Nombre identificativo</label>
        <input name="nombre" value="${f.nombre||''}" placeholder="Ej: Edificio Alhóndiga 5">
      </div>
      <div class="form-group">
        <label>Propietario *</label>
        <select name="propietario_id" required>
          <option value="">-- Seleccionar --</option>
          ${propietarios.map(p=>`<option value="${p.id}" ${f.propietario_id===p.id?'selected':''}>${p.nombre}</option>`).join('')}
        </select>
      </div>
      <div class="form-group">
        <label>Sigla de vía</label>
        <select name="sigla">
          ${['C','AV','PZ','PG','CR','CL','BV','RD'].map(s=>`<option value="${s}" ${f.sigla===s?'selected':''}>${s}</option>`).join('')}
        </select>
      </div>
      <div class="form-group">
        <label>Nombre de la calle *</label>
        <input name="calle" value="${f.calle||''}" required placeholder="ALHONDIGA">
      </div>
      <div class="form-group">
        <label>Número / Portal</label>
        <input name="numero" value="${f.numero||''}" placeholder="5">
      </div>
      <div class="form-group">
        <label>Código Postal</label>
        <input name="cp" value="${f.cp||''}" placeholder="41003">
      </div>
      <div class="form-group">
        <label>Municipio</label>
        <input name="municipio" value="${f.municipio||''}" placeholder="Sevilla">
      </div>
      <div class="form-group">
        <label>Provincia</label>
        <input name="provincia" value="${f.provincia||''}" placeholder="Sevilla">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>Observaciones</label>
        <textarea name="observaciones">${f.observaciones||''}</textarea>
      </div>
    </form>`,
    `<button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
     <button class="btn btn-primary" onclick="saveFinca(${id||'null'})">Guardar</button>`);
}

async function saveFinca(id) {
  const form = document.getElementById('form-finca');
  if (!form.checkValidity()) { form.reportValidity(); return; }
  const data = Object.fromEntries(new FormData(form));
  data.propietario_id = parseInt(data.propietario_id);
  if (id) data.id = id;
  await DB.save('fincas', data);
  closeModalForce();
  toast(id ? 'Finca actualizada' : 'Finca creada');
  renderFincas();
}

async function deleteFinca(id) {
  if (DB.get('inmuebles').some(i => i.finca_id === id))
    return toast('No se puede eliminar: tiene pisos asociados', 'error');
  if (!confirm('¿Eliminar esta finca?')) return;
  await DB.delete('fincas', id);
  toast('Finca eliminada', 'info');
  renderFincas();
}
