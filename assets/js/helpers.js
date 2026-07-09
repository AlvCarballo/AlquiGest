// ===========================
// SEGURIDAD — escape HTML
// Convierte caracteres peligrosos a entidades HTML para evitar
// inyección de código al insertar datos de usuario en el DOM.
// Usarlo siempre que se muestre texto proveniente de la base de datos.
// ===========================
function esc(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ===========================
// ROUTING — NAVEGACIÓN ENTRE SECCIONES
// navigate() es el único punto de entrada para cambiar de pantalla.
// Actualiza el resaltado del menú, el título de la cabecera y llama
// a la función de renderizado correspondiente (renderDashboard, etc.).
// ===========================
let currentPage = 'dashboard';
let navParams = {};   // parámetros opcionales pasados entre páginas (ej: finca_id, inquilino_id)

function navigate(page, params = {}) {
  currentPage = page;
  navParams = params;
  document.querySelectorAll('.nav-item').forEach(el => {
    el.classList.toggle('active', el.dataset.page === page);
  });
  const titles = {
    dashboard: 'Dashboard', propietarios: 'Propietarios', fincas: 'Fincas / Edificios',
    inmuebles: 'Pisos / Locales', inquilinos: 'Inquilinos',
    contratos: 'Contratos', recibos: 'Recibos', generar: 'Generar Recibos',
    facturas: 'Facturas', informes: 'Informes Excel', empresa: 'Mi Empresa',
    verifactu: 'VERI*FACTU — Configuración',
    calendario:     'Calendario de Cobros',
    morosidad:      'Informe de Morosidad',
    actividad:      'Historial de Actividad',
    importar:       'Importar datos (CSV / Excel)',
    configuracion:  'Configuración del sistema',
    plantillas:     'Plantillas DOCX',
    usuarios:       'Gestión de usuarios',
  };
  document.getElementById('header-title').textContent = params.titulo || titles[page] || page;
  const pages = {
    dashboard: renderDashboard, propietarios: renderPropietarios,
    fincas: renderFincas, inmuebles: renderInmuebles,
    inquilinos: renderInquilinos, contratos: renderContratos,
    recibos: renderRecibos, generar: renderGenerarRecibos,
    facturas: renderFacturas, informes: renderInformes, empresa: renderEmpresa,
    verifactu: renderVerifactu,
    calendario:    renderCalendario,
    morosidad:     renderMorosidad,
    actividad:     renderActividad,
    importar:      renderImportar,
    configuracion: renderConfiguracion,
    plantillas:    renderPlantillas,
    usuarios:      renderUsuarios,
  };
  document.getElementById('header-actions').innerHTML = '';
  updateNotificationBell();
  if (pages[page]) {
    try {
      pages[page](params);
    } catch(err) {
      console.error('Error rendering page "' + page + '":', err);
      const el = document.getElementById('content');
      if (el) el.innerHTML = `<div style="max-width:520px;margin:60px auto;text-align:center;padding:40px;background:white;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.1)">
        <div style="font-size:48px">⚠️</div>
        <h2 style="color:#991b1b;margin:16px 0 8px">Error al cargar la página</h2>
        <p style="color:#4b5563;font-family:monospace;font-size:12px;background:#f3f4f6;padding:10px;border-radius:6px;text-align:left;word-break:break-all">${err.message}</p>
        <button onclick="navigate('dashboard')" style="margin-top:20px;background:#1e40af;color:#fff;padding:10px 24px;border-radius:8px;border:none;cursor:pointer">← Volver al Dashboard</button>
      </div>`;
    }
  }
}

// Lee un valor de la tabla configuracion; devuelve defaultVal si no existe.
function _cfgGet(variable, defaultVal = '') {
  const entry = (DB.get('configuracion') || []).find(c => c.variable === variable);
  return entry !== undefined ? String(entry.valor) : String(defaultVal);
}

// Helper: nombre legible de un inmueble
function getInmuebleNombre(inm) {
  if (!inm) return '—';
  const finca = DB.getItem('fincas', inm.finca_id);
  const base = finca
    ? `${finca.sigla||''} ${finca.calle||''} ${finca.numero||''}`.trim()
    : '';
  const unidad = `${inm.planta||''} ${inm.puerta||''}`.trim();
  return [base, unidad].filter(Boolean).join(' ');
}

// Helper: contrato activo de un inmueble
function getContratoActivo(inmuebleId) {
  return DB.get('contratos').find(c => c.inmueble_id === inmuebleId && c.estado === 'activo') || null;
}

// ===========================
// TOAST — MENSAJES DE NOTIFICACIÓN TEMPORAL
// Muestra un mensaje flotante en la esquina inferior derecha.
// Desaparece automáticamente a los 3 segundos.
// Tipos: 'success' (verde), 'error' (rojo), 'info' (azul)
// ===========================
function toast(msg, type='success') {
  const c = document.getElementById('toast-container');
  const t = document.createElement('div');
  t.className = `toast toast-${type}`;
  const icons = { success:'✓', error:'✕', info:'ℹ' };
  t.innerHTML = `<span>${icons[type]||'•'}</span>${msg}`;
  c.appendChild(t);
  setTimeout(() => t.remove(), 3000);
}

// ===========================
// MODAL — VENTANA EMERGENTE PROPIA
// openModal() inyecta contenido en el único modal reutilizable de la app
// y lo hace visible mediante la clase CSS "open".
// Los modales usan el prefijo "ag-" para no chocar con los de Bootstrap.
// ===========================
function openModal(title, bodyHtml, footerHtml, large=false) {
  document.getElementById('modal-title').textContent = title;
  document.getElementById('modal-body').innerHTML = bodyHtml;
  document.getElementById('modal-footer').innerHTML = footerHtml || '';
  const modal = document.getElementById('modal');
  modal.className = 'ag-modal' + (large ? ' ag-modal-lg' : '');
  document.getElementById('modal-overlay').classList.add('open');
}
function closeModal() {
  document.getElementById('modal-overlay').classList.remove('open');
}
function closeModalOnOverlay(e) {
  if (e.target === document.getElementById('modal-overlay')) closeModal();
}

// ===========================
// UTILIDADES — FORMATEO Y CONVERSIÓN
// fmtMoney   → importe en formato español (1.234,56 €)
// fmtDate    → fecha como dd/mm/aaaa
// fmtDateShort → fecha larga en español (ej: "3 de enero de 2026")
// montoEnLetras → importe numérico a texto (para pie de recibo)
// nextNumeroDoc → reserva el siguiente número de documento en el servidor (atómico)
// ===========================
function fmtMoney(n) {
  return new Intl.NumberFormat('es-ES', { style:'currency', currency:'EUR' }).format(n||0);
}
function fmtDate(d) {
  if (!d) return '-';
  return new Date(d).toLocaleDateString('es-ES');
}
function fmtDateShort(d) {
  if (!d) return '';
  const dt = new Date(d);
  return dt.toLocaleDateString('es-ES', { day:'2-digit', month:'long', year:'numeric' });
}
// Convierte un importe numérico en texto español para el pie del recibo.
// Ej: 850.50 → "OCHOCIENTOS CINCUENTA euros con CINCUENTA céntimos"
// Soporta hasta 999.999 € (rango más que suficiente para rentas de alquiler).
function montoEnLetras(n) {
  const num = Math.round(n * 100);
  const eur = Math.floor(num / 100);
  const cts = num % 100;
  const units = ['','uno','dos','tres','cuatro','cinco','seis','siete','ocho','nueve'];
  const teens = ['diez','once','doce','trece','catorce','quince','dieciséis','diecisiete','dieciocho','diecinueve'];
  const tens = ['','diez','veinte','treinta','cuarenta','cincuenta','sesenta','setenta','ochenta','noventa'];
  const hundreds = ['','ciento','doscientos','trescientos','cuatrocientos','quinientos','seiscientos','setecientos','ochocientos','novecientos'];
  function toWords(n) {
    if (n === 0) return 'cero';
    if (n < 0) return 'menos ' + toWords(-n);
    if (n === 100) return 'cien';
    if (n < 10) return units[n];
    if (n < 20) return teens[n-10];
    if (n < 100) {
      const t = Math.floor(n/10), u = n%10;
      return u ? tens[t] + ' y ' + units[u] : tens[t];
    }
    if (n < 1000) {
      const h = Math.floor(n/100), r = n%100;
      return hundreds[h] + (r ? ' ' + toWords(r) : '');
    }
    if (n < 1000000) {
      const k = Math.floor(n/1000), r = n%1000;
      const prefix = k===1 ? 'mil' : toWords(k) + ' mil';
      return prefix + (r ? ' ' + toWords(r) : '');
    }
    return n.toString();
  }
  let res = toWords(eur).toUpperCase() + ' euros';
  if (cts > 0) res += ' con ' + toWords(cts).toUpperCase() + ' céntimos';
  return res;
}
// Formatea un objeto Date como YYYY-MM-DD en hora local (evita offset UTC)
function fmtLocalISO(d) {
  return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
}
// Alterna la visibilidad de un campo contraseña. Usado en empresa.js y configuracion.js.
function _togglePass(inputId, btn) {
  const inp = document.getElementById(inputId);
  if (!inp) return;
  const visible = inp.type === 'text';
  inp.type = visible ? 'password' : 'text';
  btn.innerHTML = visible
    ? `<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`
    : `<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`;
}
// Reserva el siguiente número de documento en el servidor de forma atómica.
// Garantiza que nunca se generan duplicados aunque se llame desde varios
// contextos simultáneamente (usa SELECT FOR UPDATE en una transacción InnoDB).
//
// tipo    : 'REC', 'FAC', 'RET' (factura rectificativa), 'RER' (recibo rectificativo), etc.
// periodo : 'YYYYMM' — si se omite usa el mes actual
// prefijo : prefijo del número formateado — si se omite usa el tipo
//
// Retorna: Promise<{ seq: number, numero: string, tipo: string, periodo: string }>
//   ejemplo: { seq: 42, numero: 'REC-202606-00042', tipo: 'REC', periodo: '202606' }
//
// Lanza una excepción si el servidor devuelve un error (para capturar con try/catch).
async function nextNumeroDoc(tipo, periodo, prefijo) {
  const now  = new Date();
  const p    = periodo || (now.getFullYear() + String(now.getMonth()+1).padStart(2,'0'));
  const pref = prefijo || tipo;
  const url  = `assets/php/api.php?action=nextNumeroDoc`
             + `&tipo=${encodeURIComponent(tipo)}`
             + `&periodo=${encodeURIComponent(p)}`
             + `&prefijo=${encodeURIComponent(pref)}`;
  const res  = await fetch(url);
  const json = await res.json();
  if (!res.ok || json.error) throw new Error(json.error || 'Error al generar número de documento');
  return json;
}

// ===========================
// VISIBILIDAD DEL MENÚ
// Aplica la configuración `menu_*` de la tabla `configuracion` al menú lateral.
// Oculta o muestra cada item según el valor almacenado ('1' = visible, '0' = oculto).
// Dashboard y Parámetros son siempre visibles (no tienen clave en configuracion).
// Cuando todos los items de un grupo quedan ocultos, se oculta también el grupo.
// Se llama al arrancar la app y cada vez que se guarda la pestaña de menú.
// ===========================
function _aplicarVisibilidadMenu() {
  // Items controlables: clave config → id del nav-item en el DOM
  const MENU_ITEMS = [
    { clave: 'menu_propietarios', id: 'nav-propietarios', grupo: 'nav-group-maestros'   },
    { clave: 'menu_fincas',       id: 'nav-fincas',       grupo: 'nav-group-maestros'   },
    { clave: 'menu_inmuebles',    id: 'nav-inmuebles',    grupo: 'nav-group-maestros'   },
    { clave: 'menu_inquilinos',   id: 'nav-inquilinos',   grupo: 'nav-group-maestros'   },
    { clave: 'menu_contratos',    id: 'nav-contratos',    grupo: 'nav-group-alquileres' },
    { clave: 'menu_recibos',      id: 'nav-recibos',      grupo: 'nav-group-alquileres' },
    { clave: 'menu_facturas',     id: 'nav-facturas',     grupo: 'nav-group-alquileres' },
    { clave: 'menu_generar',      id: 'nav-generar',      grupo: 'nav-group-alquileres' },
    { clave: 'menu_informes',     id: 'nav-informes',     grupo: 'nav-group-informes'   },
    { clave: 'menu_calendario',   id: 'nav-calendario',   grupo: 'nav-group-informes'   },
    { clave: 'menu_morosidad',    id: 'nav-morosidad',    grupo: 'nav-group-informes'   },
    { clave: 'menu_actividad',    id: 'nav-actividad',    grupo: 'nav-group-informes'   },
    { clave: 'menu_empresa',      id: 'nav-empresa',      grupo: 'nav-group-config'     },
    { clave: 'menu_verifactu',    id: 'nav-verifactu',    grupo: 'nav-group-config'     },
    { clave: 'menu_plantillas',   id: 'nav-plantillas',   grupo: 'nav-group-config'     },
  ];

  // Grupos que se ocultan cuando todos sus items controlables están ocultos
  // (nav-group-config nunca se oculta porque siempre contiene Parámetros)
  const GRUPOS_OCULTABLES = ['nav-group-maestros', 'nav-group-alquileres', 'nav-group-informes'];

  // Rastrear qué grupos tienen al menos un item visible
  const grupoConItemVisible = {};

  MENU_ITEMS.forEach(function(item) {
    const el    = document.getElementById(item.id);
    if (!el) return;
    // Por defecto visible si la clave no existe en configuracion
    const valor = _cfgGet(item.clave, '1');
    const visible = valor !== '0';
    el.style.display = visible ? '' : 'none';
    if (visible) grupoConItemVisible[item.grupo] = true;
  });

  // Ocultar o mostrar el grupo completo según si tiene items visibles
  GRUPOS_OCULTABLES.forEach(function(grupoId) {
    const grupoEl = document.getElementById(grupoId);
    if (!grupoEl) return;
    grupoEl.style.display = grupoConItemVisible[grupoId] ? '' : 'none';
  });
}

function downloadPDF(pdf, filename) {
  console.log('[PDF] Iniciando:', filename);
  const b64 = pdf.output('datauristring').split(',')[1];
  console.log('[PDF] b64 longitud:', b64 ? b64.length : 0);
  if (!b64 || b64.length < 100) {
    toast('Error: PDF generado vacío', 'error');
    console.error('[PDF] b64 vacío');
    return;
  }
  const fd = new FormData();
  fd.append('data',   b64);
  fd.append('nombre', filename);
  // Paso 1: POST para guardar el PDF en temporal, devuelve token
  fetch('assets/php/pdf_download.php', { method: 'POST', body: fd })
    .then(res => {
      console.log('[PDF] POST status:', res.status);
      if (!res.ok) return res.text().then(t => { throw new Error('POST ' + res.status + ': ' + t); });
      return res.json();
    })
    .then(({ token }) => {
      console.log('[PDF] Token recibido:', token);
      // <a download> fuerza descarga con el nombre correcto sin navegar la página
      const link = document.createElement('a');
      link.href     = 'assets/php/pdf_download.php?token=' + token;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      setTimeout(() => document.body.removeChild(link), 2000);
    })
    .catch(err => {
      console.error('[PDF] Error:', err);
      toast('Error al descargar PDF: ' + err.message, 'error');
    });
}