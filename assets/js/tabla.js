// ===========================
// TABLAS ORDENABLES (clic en cabecera)
// Al llamar a makeTableSortable(id, {col, dir}) se añade lógica de ordenación
// a todas las columnas con texto en la cabecera.
// Detecta automáticamente el tipo de dato de la celda:
//   · "Enero 2026" → fecha por mes+año español
//   · Número (con punto de miles, coma decimal, símbolo €) → numérico
//   · "dd/mm/aaaa" → fecha
//   · Resto → texto con localeCompare('es')
// ===========================
function makeTableSortable(tableId, defaultSort) {
  const table = document.getElementById(tableId);
  if (!table) return;
  const ths = Array.from(table.querySelectorAll('thead th'));
  ths.forEach((th, i) => {
    if (!th.textContent.trim()) return;
    th.style.cursor = 'pointer';
    th.style.userSelect = 'none';
    th.title = 'Clic para ordenar';
    let dir = (defaultSort && defaultSort.col === i) ? defaultSort.dir : 0;
    if (defaultSort && defaultSort.col === i) {
      th.textContent += dir === 1 ? ' ▲' : ' ▼';
    }
    th.addEventListener('click', () => {
      dir = dir === 1 ? -1 : 1;
      ths.forEach(t => {
        if (t !== th) { t._agDir = 0; t.textContent = t.textContent.replace(/ [▲▼]$/, ''); }
      });
      th.textContent = th.textContent.replace(/ [▲▼]$/, '') + (dir === 1 ? ' ▲' : ' ▼');
      const tbody = table.querySelector('tbody');
      const rows = Array.from(tbody.querySelectorAll('tr'));
      const MESES_ES = {enero:0,febrero:1,marzo:2,abril:3,mayo:4,junio:5,julio:6,agosto:7,septiembre:8,octubre:9,noviembre:10,diciembre:11};
      rows.sort((a, b) => {
        const ta = (a.querySelectorAll('td')[i]?.textContent || '').trim();
        const tb = (b.querySelectorAll('td')[i]?.textContent || '').trim();
        // "Enero 2026" style (periodo)
        const pa = ta.toLowerCase().match(/^([a-záéíóú]+)\s+(\d{4})$/);
        const pb = tb.toLowerCase().match(/^([a-záéíóú]+)\s+(\d{4})$/);
        if (pa && pb) {
          const va = new Date(pa[2], MESES_ES[pa[1]] ?? 0, 1);
          const vb = new Date(pb[2], MESES_ES[pb[1]] ?? 0, 1);
          return dir * (va - vb);
        }
        const na = parseFloat(ta.replace(/\./g,'').replace(',','.').replace(/[^\d.\-]/g,''));
        const nb = parseFloat(tb.replace(/\./g,'').replace(',','.').replace(/[^\d.\-]/g,''));
        if (!isNaN(na) && !isNaN(nb)) return dir * (na - nb);
        const da = ta.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
        const db = tb.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
        if (da && db) return dir * (new Date(da[3],da[2]-1,da[1]) - new Date(db[3],db[2]-1,db[1]));
        return dir * ta.localeCompare(tb, 'es', { sensitivity:'base' });
      });
      rows.forEach(r => tbody.appendChild(r));
    });
  });
}

// ============================================================
// COMPONENTE: acción principal + menú "Más" agrupado
// Propuesta UX 08/07/2026 — ver UX_UI_ANALISIS_PROPUESTA.md
//
// accionesFila(principal, grupos) construye el HTML de la celda de
// acciones de una fila de tabla:
//   - principal: {label,title,cls,onclick} | null → botón siempre visible
//   - grupos: [{ titulo, items:[{label,title,icon,cls,danger,onclick,oculto}] }]
//     Cada item con oculto=true no se renderiza (mismo criterio que los
//     _cfgVisi('VisiXxx') que ya decidían visibilidad antes).
//
// El menú se posiciona con position:fixed calculado en JS (no CSS puro)
// para no quedar recortado por el overflow-x:auto de .table-wrap.
// ============================================================
let _ddMoreSeq = 0;
function accionesFila(principal, grupos) {
  const idMenu = 'ddm' + (_ddMoreSeq++);
  const principalHtml = principal
    ? `<button class="btn btn-sm ${principal.cls || 'btn-secondary'}" title="${esc(principal.title || principal.label)}" onclick="${principal.onclick}">${esc(principal.label)}</button>`
    : '';

  const bloques = (grupos || []).map(g => {
    const visibles = (g.items || []).filter(it => it && !it.oculto);
    if (!visibles.length) return '';
    return `<div class="dd-more-group-label">${esc(g.titulo)}</div>` +
      visibles.map(it =>
        `<button class="dd-more-item${it.danger ? ' danger' : ''}" title="${esc(it.title || it.label)}" onclick="cerrarMenusMas();${it.onclick}">${it.icon ? it.icon + ' ' : ''}${esc(it.label)}</button>`
      ).join('');
  }).filter(Boolean);

  if (!bloques.length) {
    // Sin acciones secundarias: no se muestra "Más" vacío.
    return `<div class="row-actions">${principalHtml}</div>`;
  }

  return `<div class="row-actions">
    ${principalHtml}
    <div class="dd-more">
      <button class="btn btn-sm btn-secondary" title="Más acciones" onclick="toggleMenuMas(this,'${idMenu}')">Más ▾</button>
      <div class="dd-more-panel" id="${idMenu}">${bloques.join('<div class="dd-more-divider"></div>')}</div>
    </div>
  </div>`;
}

function toggleMenuMas(btn, idMenu) {
  const panel = document.getElementById(idMenu);
  if (!panel) return;
  const abierto = panel.classList.contains('open');
  cerrarMenusMas();
  if (abierto) return;
  panel.classList.add('open');
  const r = btn.getBoundingClientRect();
  const pw = panel.offsetWidth;
  const ph = panel.offsetHeight;
  let left = r.right - pw;
  if (left < 8) left = 8;
  const maxLeft = window.innerWidth - pw - 8;
  if (left > maxLeft) left = Math.max(8, maxLeft);
  // Si no cabe debajo del botón, se abre hacia arriba en su lugar.
  const cabeDebajo = (r.bottom + 4 + ph) <= window.innerHeight - 8;
  panel.style.top = cabeDebajo ? (r.bottom + 4) + 'px' : Math.max(8, r.top - ph - 4) + 'px';
  panel.style.left = left + 'px';
}
function cerrarMenusMas() {
  document.querySelectorAll('.dd-more-panel.open').forEach(p => p.classList.remove('open'));
}
document.addEventListener('click', e => { if (!e.target.closest('.dd-more')) cerrarMenusMas(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') { cerrarMenusMas(); cerrarPanelDetalle(); } });
// Reposicionar/cerrar si la ventana cambia de tamaño o se hace scroll (evita menús "flotando" desalineados)
window.addEventListener('resize', cerrarMenusMas);
window.addEventListener('scroll', cerrarMenusMas, true);

// ============================================================
// COMPONENTE: panel lateral de detalle
// abrirPanelDetalle(eyebrow, titulo, html) construye/reutiliza un único
// panel deslizante a la derecha, reutilizado por todos los módulos.
// ============================================================
function abrirPanelDetalle(eyebrow, titulo, html) {
  let panel = document.getElementById('panel-detalle');
  if (!panel) {
    document.body.insertAdjacentHTML('beforeend', `
      <div class="panel-overlay" id="panel-overlay" onclick="cerrarPanelDetalle()"></div>
      <div class="panel-lateral" id="panel-detalle" role="dialog" aria-modal="true">
        <div class="panel-lateral-head">
          <div>
            <div class="panel-lateral-eyebrow" id="panel-detalle-eyebrow"></div>
            <div class="panel-lateral-titulo" id="panel-detalle-titulo"></div>
          </div>
          <button class="btn btn-sm btn-secondary btn-icon" title="Cerrar" onclick="cerrarPanelDetalle()">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>
        <div class="panel-lateral-body" id="panel-detalle-body"></div>
      </div>`);
    panel = document.getElementById('panel-detalle');
  }
  document.getElementById('panel-detalle-eyebrow').textContent = eyebrow || '';
  document.getElementById('panel-detalle-titulo').textContent = titulo || '';
  document.getElementById('panel-detalle-body').innerHTML = html;
  requestAnimationFrame(() => {
    document.getElementById('panel-overlay').classList.add('open');
    panel.classList.add('open');
  });
}
function cerrarPanelDetalle() {
  document.getElementById('panel-overlay')?.classList.remove('open');
  document.getElementById('panel-detalle')?.classList.remove('open');
}

// ============================================================
// COMPONENTE: barra de acciones masivas
// initBarraMasiva(cfg) engancha las casillas de una tabla a una barra
// de acciones contextual. cfg = { checkboxClass, barId, countId, rowTrClass }
// ============================================================
function _bulkSeleccionados(checkboxClass) {
  return Array.from(document.querySelectorAll('.' + checkboxClass + ':checked')).map(c => parseInt(c.value));
}
function actualizarBarraMasiva(checkboxClass, barId, countId) {
  const marcadas = document.querySelectorAll('.' + checkboxClass + ':checked');
  const bar = document.getElementById(barId);
  if (!bar) return;
  bar.classList.toggle('show', marcadas.length > 0);
  const countEl = document.getElementById(countId);
  if (countEl) countEl.textContent = marcadas.length + (marcadas.length === 1 ? ' seleccionado' : ' seleccionados');
  document.querySelectorAll('.' + checkboxClass).forEach(c => {
    c.closest('tr')?.classList.toggle('tr-selected', c.checked);
  });
}
function toggleTodasFilas(master, checkboxClass, barId, countId) {
  document.querySelectorAll('.' + checkboxClass).forEach(c => { c.checked = master.checked; });
  actualizarBarraMasiva(checkboxClass, barId, countId);
}
function limpiarSeleccionMasiva(checkboxClass, barId, countId) {
  document.querySelectorAll('.' + checkboxClass).forEach(c => { c.checked = false; });
  actualizarBarraMasiva(checkboxClass, barId, countId);
  const master = document.querySelector('.' + checkboxClass + '-master');
  if (master) master.checked = false;
}

// ===========================
// BÚSQUEDA / FILTRO DE TABLA EN TIEMPO REAL
// filterTable() muestra u oculta filas según el texto escrito.
// Usa un debounce de 150 ms para no filtrar en cada pulsación de tecla.
// cols = array de índices de columnas a comparar (ej: [0,1,2])
// ===========================
const _filterTimers = {};
function filterTable(input, tableId, cols) {
  clearTimeout(_filterTimers[tableId]);
  _filterTimers[tableId] = setTimeout(() => {
    const q = input.value.toLowerCase();
    const rows = document.querySelectorAll(`#${tableId} tbody tr`);
    rows.forEach(row => {
      const tds = row.querySelectorAll('td');
      const text = cols.map(c => (tds[c]?.textContent||'').toLowerCase()).join(' ');
      row.style.display = text.includes(q) ? '' : 'none';
    });
  }, 150);
}
