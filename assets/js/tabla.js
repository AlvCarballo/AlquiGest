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
