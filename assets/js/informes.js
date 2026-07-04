// ===========================
function renderInformes() {
  document.getElementById('header-actions').innerHTML = '';
  const anyo = new Date().getFullYear();
  const infBtn = (id) => `
    <button class="btn btn-primary btn-sm" onclick="exportarInforme('${id}')">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Descargar Excel
    </button>`;
  const infCard = (id, icon, titulo, desc) => `
    <div style="border:1px solid var(--gray-200);border-radius:8px;padding:16px;display:flex;flex-direction:column">
      <div style="font-size:26px;margin-bottom:8px">${icon}</div>
      <div style="font-weight:600;margin-bottom:4px">${titulo}</div>
      <div style="font-size:12px;color:var(--gray-500);margin-bottom:12px;flex:1">${desc}</div>
      ${infBtn(id)}
    </div>`;
  document.getElementById('content').innerHTML = `
    <div class="card">
      <div class="card-header"><div class="card-title">Informes Excel</div></div>
      <div class="card-body">

        <div class="form-group" style="max-width:200px;margin-bottom:28px">
          <label>Año del informe</label>
          <input type="number" id="inf-anyo" value="${anyo}" min="2000" max="2099" style="padding:8px">
        </div>

        <!-- Informes generales -->
        <div style="font-size:11px;font-weight:700;color:var(--gray-400);letter-spacing:.08em;text-transform:uppercase;margin-bottom:12px">
          Informes de gestión
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px;margin-bottom:32px">
          ${infCard('recibos-anyo',      '📋', 'Todos los recibos del año',   'Listado completo con base, IVA, IRPF, total, cobrado y pendiente.')}
          ${infCard('ingresos-finca',    '🏢', 'Ingresos por finca',          'Ingresos mensuales agrupados por edificio o finca, con total anual.')}
          ${infCard('ingresos-piso',     '🏠', 'Ingresos por piso/unidad',    'Detalle mensual de cada inmueble, con inquilino y total anual.')}
          ${infCard('pendientes',        '⏳', 'Recibos pendientes',          'Todos los recibos sin cobrar o con pago parcial, con importes.')}
          ${infCard('historico-cobros',  '💰', 'Histórico de cobros',         'Todos los pagos recibidos con fecha, método y cuenta.')}
          ${infCard('resumen-propietario','👤','Resumen por propietario',     'Ingresos facturados y cobrados agrupados por propietario y finca.')}
        </div>

        <!-- Separador fiscal -->
        <div style="border-top:2px solid #bae6fd;margin:0 0 20px;position:relative">
          <span style="position:absolute;top:-11px;left:16px;background:white;padding:0 10px;
            font-size:11px;font-weight:700;color:#0369a1;letter-spacing:.08em;text-transform:uppercase">
            Informes fiscales · Hacienda España
          </span>
        </div>
        <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:13px;color:#0c4a6e;display:flex;gap:10px;align-items:flex-start">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <div>
            <strong>Basados en normativa IRPF española (LIRPF, arts. 22-24).</strong>
            Estos informes son una ayuda para preparar la documentación.
            <strong>Revise siempre los datos con su asesor fiscal o gestor</strong> antes de presentar cualquier declaración.
            Los <em>gastos deducibles</em> no están registrados en la aplicación y deben añadirse manualmente en el Excel.
          </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px">
          ${infCard('fiscal-trimestral', '📊',
            'Rendimientos trimestrales (IRPF)',
            'Ingresos por inmueble desglosados en T1-T4, con reducción del 60 % para arrendamiento de vivienda (art. 23.2 LIRPF). Base para la Declaración de la Renta.')}
          ${infCard('irpf-anual', '📄',
            'Declaración de la Renta — Cap. Inmobiliario',
            'Informe completo para la sección F del Modelo 100 (Rendimientos del Capital Inmobiliario): ingresos íntegros, reducción 60 %, columna para gastos deducibles y recordatorio de cada gasto aplicable.')}
          ${infCard('modelo-115', '🏛️',
            'Modelo 115 / 180 — Retenciones arrendamiento',
            'Base imponible y cuota de retención (19 %) por trimestre para el Modelo 115 (trimestral) y el Modelo 180 (resumen anual). Solo aplica si el arrendatario es empresa o autónomo.')}
          ${infCard('iva-trimestral', '📑',
            'Modelo 303 — IVA trimestral (arrendamientos)',
            'Desglose por trimestre de la base imponible y cuotas de IVA repercutido en recibos de arrendamiento. Aplicable cuando el arrendamiento está sujeto a IVA (locales, oficinas, naves).')}
        </div>

      </div>
    </div>
  `;
}

// Descarga el informe redirigiendo al servidor (export.php genera el XLSX).
// No usa librerías externas: todo el XLSX se construye con ZipArchive nativo.
function exportarInforme(tipo) {
  const anyo = parseInt(document.getElementById('inf-anyo')?.value) || new Date().getFullYear();
  window.location.href = `assets/php/export.php?tipo=${encodeURIComponent(tipo)}&anyo=${anyo}`;
  toast('Descargando Excel...', 'success');
}

// ===========================
// EMAIL — ENVÍO DE RECIBO POR CORREO
// confirmarEnvioEmail() genera el PDF del recibo (si jsPDF está cargado),
// lo codifica en base64 y lo envía junto con los datos del recibo a email.php.
// email.php hace el diálogo SMTP con Gmail directamente por sockets PHP
// (sin librerías, sin php.ini especial) usando STARTTLS en el puerto 587.
// Requiere configurar Gmail User + Contraseña de Aplicación en Mi Empresa.