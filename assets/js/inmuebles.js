// ===========================
// INMUEBLES
// ===========================
let _inmueblesPag = 1;
function renderInmuebles(params={}) {
  const fincaId = params.finca_id || null;
  const fincas  = DB.get('fincas');
  let items     = DB.get('inmuebles');
  if (fincaId) items = items.filter(i => i.finca_id === fincaId);
  items = [...items].sort((a, b) => {
    const fa = fincas.find(f => f.id === a.finca_id);
    const fb = fincas.find(f => f.id === b.finca_id);
    const na = fa ? (fa.nombre || [fa.sigla,fa.calle,fa.numero].filter(Boolean).join(' ')) : '';
    const nb = fb ? (fb.nombre || [fb.sigla,fb.calle,fb.numero].filter(Boolean).join(' ')) : '';
    const cmp = na.localeCompare(nb, 'es', {sensitivity:'base'});
    if (cmp !== 0) return cmp;
    return getInmuebleNombre(a).localeCompare(getInmuebleNombre(b), 'es', {sensitivity:'base'});
  });
  const _INM_PP = Math.max(5, parseInt(_cfgGet('filas_inmuebles', '20')) || 20);
  const _inmTotPag = Math.max(1, Math.ceil(items.length / _INM_PP));
  _inmueblesPag = Math.max(1, Math.min(_inmueblesPag, _inmTotPag));
  const inmueblesPag = items.slice((_inmueblesPag - 1) * _INM_PP, _inmueblesPag * _INM_PP);

  document.getElementById('header-actions').innerHTML = `
    ${fincaId ? `<button class="btn btn-secondary" onclick="navigate('fincas')">← Fincas</button>` : ''}
    <button class="btn btn-primary" onclick="modalInmueble(null,${fincaId||'null'})">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Nuevo piso / local
    </button>`;

  document.getElementById('content').innerHTML = `
    <div class="card">
      <div class="card-header">
        <div class="card-title">Pisos / Locales (${items.length})</div>
        <div class="search-bar" style="width:260px">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="search" placeholder="Buscar..." oninput="filterTable(this,'tbl-inmuebles',[0,1,2])">
        </div>
      </div>
      <div class="table-wrap">
        <table id="tbl-inmuebles">
          <thead><tr><th>Inmueble</th><th>Finca</th><th>Tipo</th><th>Ref. catastral</th><th>Estado</th><th></th></tr></thead>
          <tbody>
            ${items.length ? (() => {
              const _fincaMap = new Map(fincas.map(f => [f.id, f]));
              const _inqMap   = new Map(DB.get('inquilinos').map(i => [i.id, i]));
              const _activoByInm = new Map();
              DB.get('contratos').forEach(c => { if (c.estado === 'activo') _activoByInm.set(c.inmueble_id, c); });
              return inmueblesPag.map(inm => {
                const finca = _fincaMap.get(inm.finca_id);
                const contrato = _activoByInm.get(inm.id);
                const inq = contrato ? _inqMap.get(contrato.inquilino_id) : null;
                return `<tr>
                <td><strong>${esc(getInmuebleNombre(inm))}</strong></td>
                <td>${finca ? esc(finca.nombre||[finca.sigla,finca.calle,finca.numero].filter(Boolean).join(' ')) : '<span style="color:var(--gray-400)">—</span>'}</td>
                <td>${esc(inm.tipo||'-')}</td>
                <td style="font-size:11px;color:var(--gray-400)">${esc(inm.referencia_catastral||'-')}</td>
                <td>${contrato
                  ? `<span class="badge badge-green">Alquilado</span><br><small>${inq?esc(inq.nombre):''}</small>`
                  : '<span class="badge badge-orange">Libre</span>'}</td>
                <td class="td-actions">
                  <button class="btn btn-sm btn-secondary btn-icon" title="Editar" onclick="modalInmueble(${inm.id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  </button>
                  ${_cfgVisi('VisiBorrarInm') ? `<button class="btn btn-sm btn-danger btn-icon" title="Eliminar" onclick="deleteInmueble(${inm.id})">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                  </button>` : ''}
                </td>
              </tr>`;
              }).join('');
            })() : `<tr><td colspan="6"><div class="empty-state">
              <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24"><path d="M3 22V9l9-7 9 7v13"/><rect x="9" y="14" width="6" height="8"/></svg>
              <p>Sin pisos</p><small>Pulsa "Nuevo piso / local"</small></div></td></tr>`}
          </tbody>
        </table>
      </div>
      ${_inmTotPag > 1 ? `
        <div class="table-pagination">
          <button class="btn btn-sm btn-secondary" onclick="_inmueblesPag=${_inmueblesPag-1};renderInmuebles(navParams)" ${_inmueblesPag<=1?'disabled':''}>‹ Ant.</button>
          <span>Página ${_inmueblesPag} de ${_inmTotPag} · ${items.length} pisos/locales</span>
          <button class="btn btn-sm btn-secondary" onclick="_inmueblesPag=${_inmueblesPag+1};renderInmuebles(navParams)" ${_inmueblesPag>=_inmTotPag?'disabled':''}>Sig. ›</button>
        </div>` : ''}
    </div>`;
  makeTableSortable('tbl-inmuebles', {col:1, dir:1});
}

function modalInmueble(id=null, defaultFincaId=null) {
  const inm   = id ? DB.getItem('inmuebles', id) : {};
  const fincas = DB.get('fincas');
  const fId   = inm.finca_id || defaultFincaId;
  openModal(id ? 'Editar piso / local' : 'Nuevo piso / local', `
    <form id="form-inmueble" class="form-grid form-grid-2">
      <div class="form-group" style="grid-column:1/-1">
        <label>Finca / Edificio *</label>
        <select name="finca_id" required>
          <option value="">-- Seleccionar finca --</option>
          ${fincas.map(f=>`<option value="${f.id}" ${fId===f.id?'selected':''}>${f.nombre||[f.sigla,f.calle,f.numero].filter(Boolean).join(' ')}</option>`).join('')}
        </select>
      </div>
      <div class="form-group">
        <label>Planta *</label>
        <input name="planta" value="${inm.planta||''}" required placeholder="BAJO, 1, 2, ENT...">
      </div>
      <div class="form-group">
        <label>Puerta / Letra</label>
        <input name="puerta" value="${inm.puerta||''}" placeholder="A, B, IZQ, DCH...">
      </div>
      <div class="form-group">
        <label>Tipo</label>
        <select name="tipo">
          ${['Vivienda','Local','Garaje','Trastero','Nave Industrial','Oficina','Otro'].map(t=>
            `<option value="${t}" ${inm.tipo===t?'selected':''}>${t}</option>`).join('')}
        </select>
      </div>
      <div class="form-group">
        <label>Metros cuadrados</label>
        <input name="metros" type="number" value="${inm.metros||''}" placeholder="75">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>Referencia catastral</label>
        <input name="referencia_catastral" value="${inm.referencia_catastral||''}" placeholder="5829701TG3452H0001HO">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>Cédula de habitabilidad</label>
        <input name="cedula" value="${inm.cedula||''}">
      </div>
      <div class="form-group" style="grid-column:1/-1">
        <label>Observaciones</label>
        <textarea name="observaciones">${inm.observaciones||''}</textarea>
      </div>
    </form>`,
    `<button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
     <button class="btn btn-primary" onclick="saveInmueble(${id||'null'})">Guardar</button>`);
}

async function saveInmueble(id) {
  const form = document.getElementById('form-inmueble');
  if (!form.checkValidity()) { form.reportValidity(); return; }
  const data = Object.fromEntries(new FormData(form));
  data.finca_id = parseInt(data.finca_id);
  if (id) data.id = id;
  await DB.save('inmuebles', data);
  closeModalForce();
  toast(id ? 'Piso actualizado' : 'Piso creado');
  renderInmuebles(navParams);
}

async function deleteInmueble(id) {
  const contratos = DB.get('contratos').filter(c => c.inmueble_id === id);
  if (contratos.length)
    return toast(`No se puede eliminar: tiene ${contratos.length} contrato(s) asociado(s)`, 'error');
  if (!confirm('Este registro se marcará como eliminado y dejará de aparecer en los listados normales, pero se conservará para mantener el histórico.\n\n¿Desea continuar?')) return;
  const r = await DB.delete('inmuebles', id);
  if (!r.ok) return toast(r.error || 'No se puede eliminar este inmueble', 'error');
  toast('Piso eliminado', 'info');
  renderInmuebles(navParams);
}
