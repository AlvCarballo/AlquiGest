<?php
// ============================================================
//  AlquiGest — Propuesta UX/UI (prototipo NO productivo)
//  Página autocontenida: CSS y JS propios, datos simulados,
//  sin llamadas a api.php ni a la base de datos.
//  No se enlaza desde el menú de la aplicación real.
//  Ver documentación completa en UX_UI_ANALISIS_PROPUESTA.md
// ============================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AlquiGest — Propuesta UX/UI (prototipo)</title>
<style>
  :root{
    --blue:#1a56db; --blue-light:#e8f0fe; --blue-dark:#1242a8;
    --green:#057a55; --green-light:#def7ec;
    --red:#c81e1e; --red-light:#fde8e8;
    --orange:#c27803; --orange-light:#fdf6b2;
    --gray-50:#f9fafb; --gray-100:#f3f4f6; --gray-200:#e5e7eb; --gray-300:#d1d5db;
    --gray-400:#9ca3af; --gray-500:#6b7280; --gray-600:#4b5563; --gray-700:#374151; --gray-800:#1f2937;
    --radius:8px; --radius-lg:12px;
    --shadow:0 1px 3px rgba(0,0,0,.10),0 1px 2px rgba(0,0,0,.06);
    --shadow-lg:0 10px 25px rgba(0,0,0,.12);
  }
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{
    font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
    background:var(--gray-100); color:var(--gray-800); line-height:1.5;
    padding-bottom:80px;
  }
  a{color:var(--blue)}
  .uxp-banner{
    background:#111827; color:#fff; padding:14px 24px; text-align:center; font-size:13px;
  }
  .uxp-banner strong{color:#fbbf24}
  .uxp-banner a{color:#93c5fd; margin-left:10px}
  .uxp-topbar{
    background:#fff; border-bottom:1px solid var(--gray-200); padding:18px 24px;
    position:sticky; top:0; z-index:50; box-shadow:var(--shadow);
  }
  .uxp-topbar h1{font-size:20px; font-weight:700; margin-bottom:2px}
  .uxp-topbar p{font-size:13px; color:var(--gray-500)}
  .uxp-nav{
    display:flex; gap:4px; flex-wrap:wrap; margin-top:14px;
  }
  .uxp-nav button{
    border:none; background:transparent; padding:8px 14px; font-size:13px; font-weight:600;
    color:var(--gray-600); border-radius:6px; cursor:pointer;
  }
  .uxp-nav button:hover{background:var(--gray-100)}
  .uxp-nav button.active{background:var(--blue); color:#fff}
  .uxp-wrap{max-width:1180px; margin:0 auto; padding:24px}
  .uxp-section{display:none}
  .uxp-section.active{display:block; animation:fadein .15s ease}
  @keyframes fadein{from{opacity:0} to{opacity:1}}
  .uxp-card{
    background:#fff; border:1px solid var(--gray-200); border-radius:var(--radius-lg);
    padding:22px; margin-bottom:20px; box-shadow:var(--shadow);
  }
  .uxp-card h2{font-size:16px; margin-bottom:6px}
  .uxp-card > p.uxp-desc{font-size:13px; color:var(--gray-500); margin-bottom:16px}
  .uxp-tag{
    display:inline-block; font-size:11px; font-weight:700; letter-spacing:.04em;
    padding:3px 10px; border-radius:20px; text-transform:uppercase; margin-bottom:10px;
  }
  .uxp-tag.antes{background:var(--red-light); color:var(--red)}
  .uxp-tag.despues{background:var(--green-light); color:var(--green)}

  .uxp-compare{display:grid; grid-template-columns:1fr 1fr; gap:20px}
  @media (max-width:860px){.uxp-compare{grid-template-columns:1fr}}
  .uxp-compare-col{border:1px solid var(--gray-200); border-radius:var(--radius); padding:16px; background:var(--gray-50)}

  table.uxp-table{width:100%; border-collapse:collapse; font-size:13px; background:#fff}
  table.uxp-table th{
    text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:.03em;
    color:var(--gray-500); padding:8px 10px; border-bottom:2px solid var(--gray-200);
  }
  table.uxp-table td{padding:10px; border-bottom:1px solid var(--gray-100); vertical-align:middle}
  table.uxp-table tr:last-child td{border-bottom:none}

  .uxp-btn{
    display:inline-flex; align-items:center; gap:5px; border:1px solid transparent;
    padding:6px 11px; font-size:12px; font-weight:600; border-radius:6px; cursor:pointer;
    background:var(--gray-100); color:var(--gray-700);
  }
  .uxp-btn:hover{background:var(--gray-200)}
  .uxp-btn.icon{padding:6px 8px; width:30px; justify-content:center}
  .uxp-btn.primary{background:var(--blue); color:#fff}
  .uxp-btn.primary:hover{background:var(--blue-dark)}
  .uxp-btn.success{background:var(--green); color:#fff}
  .uxp-btn.success:hover{background:#045f42}
  .uxp-btn.danger{background:var(--red); color:#fff}
  .uxp-btn.ghost{background:transparent; border-color:var(--gray-300); color:var(--gray-600)}
  .uxp-btn.ghost:hover{background:var(--gray-100)}
  .uxp-btn.xs{padding:3px 6px; font-size:16px; line-height:1}

  .uxp-old-row-actions{display:flex; gap:4px; flex-wrap:wrap}
  .uxp-old-row-actions .uxp-btn{padding:5px 7px; font-size:11px}

  .uxp-badge{display:inline-block; font-size:11px; font-weight:700; padding:3px 9px; border-radius:20px}
  .b-orange{background:var(--orange-light); color:var(--orange)}
  .b-blue{background:var(--blue-light); color:var(--blue)}
  .b-green{background:var(--green-light); color:var(--green)}
  .b-red{background:var(--red-light); color:var(--red)}
  .b-purple{background:#ede9fe; color:#6d28d9}
  .b-gray{background:var(--gray-100); color:var(--gray-500)}

  /* Menu "Más" */
  .uxp-more{position:relative; display:inline-block}
  .uxp-more-panel{
    display:none; position:absolute; right:0; top:calc(100% + 4px); z-index:60;
    background:#fff; border:1px solid var(--gray-200); border-radius:var(--radius);
    box-shadow:var(--shadow-lg); min-width:220px; padding:6px; text-align:left;
  }
  .uxp-more-panel.open{display:block}
  .uxp-more-group-label{font-size:10px; text-transform:uppercase; letter-spacing:.04em; color:var(--gray-400); padding:8px 10px 4px}
  .uxp-more-item{
    display:flex; align-items:center; gap:9px; width:100%; border:none; background:none;
    text-align:left; padding:8px 10px; font-size:13px; color:var(--gray-700); border-radius:6px; cursor:pointer;
  }
  .uxp-more-item:hover{background:var(--gray-100)}
  .uxp-more-item.danger{color:var(--red)}
  .uxp-more-item.danger:hover{background:var(--red-light)}
  .uxp-more-divider{height:1px; background:var(--gray-100); margin:4px 6px}

  /* Panel lateral de detalle */
  .uxp-overlay{
    position:fixed; inset:0; background:rgba(17,24,39,.35); z-index:90; display:none;
  }
  .uxp-overlay.open{display:block}
  .uxp-panel{
    position:fixed; top:0; right:0; height:100%; width:400px; max-width:92vw;
    background:#fff; z-index:91; box-shadow:-8px 0 24px rgba(0,0,0,.15);
    transform:translateX(100%); transition:transform .22s ease; display:flex; flex-direction:column;
  }
  .uxp-panel.open{transform:translateX(0)}
  .uxp-panel-head{padding:18px 20px; border-bottom:1px solid var(--gray-200); display:flex; justify-content:space-between; align-items:flex-start}
  .uxp-panel-body{padding:20px; overflow-y:auto; flex:1}
  .uxp-panel-foot{padding:16px 20px; border-top:1px solid var(--gray-200); display:flex; gap:8px}
  .uxp-kv{display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--gray-100); font-size:13px}
  .uxp-kv b{color:var(--gray-800)}
  .uxp-panel-group-title{font-size:11px; text-transform:uppercase; color:var(--gray-400); letter-spacing:.04em; margin:18px 0 8px}

  /* Acciones agrupadas */
  .uxp-group-row{display:flex; gap:10px; flex-wrap:wrap}
  .uxp-group{
    border:1px solid var(--gray-200); border-radius:var(--radius); padding:10px 12px; flex:1; min-width:170px;
  }
  .uxp-group-title{font-size:10px; text-transform:uppercase; color:var(--gray-400); letter-spacing:.04em; margin-bottom:8px}
  .uxp-group .uxp-btn{margin:2px 4px 2px 0}

  /* Acciones masivas */
  .uxp-bulkbar{
    display:none; align-items:center; gap:14px; background:var(--blue-dark); color:#fff;
    padding:10px 16px; border-radius:var(--radius); margin-bottom:12px; font-size:13px; font-weight:600;
  }
  .uxp-bulkbar.show{display:flex}
  .uxp-bulkbar .uxp-btn{background:rgba(255,255,255,.15); color:#fff}
  .uxp-bulkbar .uxp-btn:hover{background:rgba(255,255,255,.28)}
  .uxp-bulkbar .spacer{flex:1}
  .uxp-bulkbar .link{background:none; border:none; color:#cbd5e1; font-size:12px; cursor:pointer; text-decoration:underline}

  /* Filtros */
  details.uxp-filters{border:1px solid var(--gray-200); border-radius:var(--radius); padding:0; margin-bottom:14px; background:var(--gray-50)}
  details.uxp-filters summary{
    padding:10px 14px; cursor:pointer; font-size:13px; font-weight:600; color:var(--gray-600); list-style:none;
    display:flex; align-items:center; gap:8px;
  }
  details.uxp-filters summary::-webkit-details-marker{display:none}
  details.uxp-filters summary::before{content:'▸'; display:inline-block; transition:transform .15s ease}
  details.uxp-filters[open] summary::before{transform:rotate(90deg)}
  details.uxp-filters[open] summary{border-bottom:1px solid var(--gray-200)}
  .uxp-filters-body{padding:14px; display:flex; gap:10px; flex-wrap:wrap}
  .uxp-filters-body select, .uxp-filters-body input{
    border:1px solid var(--gray-300); border-radius:6px; padding:7px 10px; font-size:13px;
  }
  .uxp-searchbar{
    display:flex; align-items:center; gap:8px; background:#fff; border:1px solid var(--gray-300);
    border-radius:8px; padding:8px 12px; margin-bottom:12px; max-width:420px;
  }
  .uxp-searchbar input{border:none; outline:none; font-size:13px; flex:1}

  code.uxp-path{background:var(--gray-100); padding:2px 7px; border-radius:5px; font-size:12px}
  .uxp-rules{font-size:13px}
  .uxp-rules li{margin-bottom:6px}
  .uxp-checkbox{width:16px; height:16px}
  .uxp-note{font-size:12px; color:var(--gray-500); margin-top:10px}
</style>
</head>
<body>

<div class="uxp-banner">
  🎨 <strong>PROTOTIPO — no productivo.</strong> Esta pantalla no está enlazada al menú de AlquiGest y no modifica datos reales.
  <a href="AlquiGest.php">← Volver a la aplicación</a>
  <a href="UX_UI_ANALISIS_PROPUESTA.md" target="_blank">Ver documento completo (MD)</a>
</div>

<div class="uxp-topbar">
  <div class="uxp-wrap" style="padding:0">
    <h1>Propuesta UX/UI — Reducción de carga visual</h1>
    <p>Comparativa Antes / Después para tablas, acciones, estados, filtros y detalle. Datos simulados.</p>
    <nav class="uxp-nav" id="uxp-nav">
      <button data-tab="t1" class="active">1. Antes / Después</button>
      <button data-tab="t2">2. Estados por documento</button>
      <button data-tab="t3">3. Menú "Más"</button>
      <button data-tab="t4">4. Panel de detalle</button>
      <button data-tab="t5">5. Acciones agrupadas</button>
      <button data-tab="t6">6. Acciones masivas</button>
      <button data-tab="t7">7. Filtros plegables</button>
      <button data-tab="t8">8. Reglas de diseño</button>
    </nav>
  </div>
</div>

<div class="uxp-wrap">

  <!-- ============ TAB 1: ANTES / DESPUÉS ============ -->
  <section class="uxp-section active" id="t1">
    <div class="uxp-card">
      <h2>Recibos — tabla actual vs. tabla propuesta</h2>
      <p class="uxp-desc">El mismo recibo cobrado (REC-202607-00005, 1.452,00 €), tal como se ve hoy y tal como se propone.</p>
      <div class="uxp-compare">
        <div class="uxp-compare-col">
          <span class="uxp-tag antes">Antes — 7 elementos por fila</span>
          <table class="uxp-table">
            <thead><tr><th>Nº Recibo</th><th>Total</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
              <tr>
                <td>REC-202607-00005</td>
                <td>1.452,00&nbsp;€</td>
                <td><span class="uxp-badge b-green">Cobrado</span></td>
                <td>
                  <div class="uxp-old-row-actions">
                    <button class="uxp-btn ghost">Ver cobros</button>
                    <button class="uxp-btn ghost icon" title="Email">✉</button>
                    <button class="uxp-btn success icon" title="WhatsApp">📲</button>
                    <button class="uxp-btn primary icon" title="Imprimir">🖶</button>
                    <button class="uxp-btn ghost icon" title="Editar">✎</button>
                    <button class="uxp-btn danger icon" title="Anular">⊘</button>
                    <button class="uxp-btn ghost ico" title="Factura" style="background:#ede9fe;color:#6d28d9">FAC</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
          <p class="uxp-note">7 controles con el mismo peso visual, incluida una acción destructiva (Anular) al lado de una de consulta.</p>
        </div>
        <div class="uxp-compare-col">
          <span class="uxp-tag despues">Después — 1 acción principal + Más</span>
          <table class="uxp-table">
            <thead><tr><th>Nº Recibo</th><th>Total</th><th>Estado</th><th>Acciones</th></tr></thead>
            <tbody>
              <tr>
                <td>REC-202607-00005</td>
                <td>1.452,00&nbsp;€</td>
                <td><span class="uxp-badge b-green">Cobrado</span></td>
                <td>
                  <div style="display:flex; gap:6px; justify-content:flex-end">
                    <button class="uxp-btn ghost" onclick="alert('Simulación: abre el panel de detalle con el historial de cobros.')">Ver cobro</button>
                    <div class="uxp-more">
                      <button class="uxp-btn ghost" onclick="uxpToggleMore(this)">Más ▾</button>
                      <div class="uxp-more-panel">
                        <div class="uxp-more-group-label">Comunicación</div>
                        <button class="uxp-more-item">✉ Enviar por email</button>
                        <button class="uxp-more-item">📲 Enviar por WhatsApp</button>
                        <div class="uxp-more-divider"></div>
                        <div class="uxp-more-group-label">Documentos</div>
                        <button class="uxp-more-item">🖶 Descargar / imprimir PDF</button>
                        <button class="uxp-more-item">🧾 Generar factura</button>
                        <div class="uxp-more-divider"></div>
                        <div class="uxp-more-group-label">Gestión</div>
                        <button class="uxp-more-item">✎ Editar</button>
                        <button class="uxp-more-item danger">⊘ Anular recibo</button>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
          <p class="uxp-note">Toca "Más ▾" para ver el menú agrupado real (funciona). Una sola acción destaca; el resto está a un clic de distancia, sin ruido permanente.</p>
        </div>
      </div>
    </div>

    <div class="uxp-card">
      <h2>Los 5 estados de Recibos, ya con el patrón propuesto</h2>
      <p class="uxp-desc">Cada estado muestra solo la acción principal que tiene sentido para ese estado. Nunca se ofrecen acciones imposibles (p. ej. "Cobrar" en un recibo Anulado).</p>
      <table class="uxp-table">
        <thead><tr><th>Recibo</th><th>Total</th><th>Pagado</th><th>Estado</th><th>Acción principal</th><th></th></tr></thead>
        <tbody id="uxp-recibos-demo"></tbody>
      </table>
    </div>
  </section>

  <!-- ============ TAB 2: ESTADOS ============ -->
  <section class="uxp-section" id="t2">
    <div class="uxp-card">
      <h2>Estados actuales — inconsistencias detectadas</h2>
      <p class="uxp-desc">Mismo color usado para conceptos distintos, y un estado muerto que no debería poder aparecer.</p>
      <table class="uxp-table">
        <thead><tr><th>Módulo</th><th>Estado</th><th>Color actual</th><th>Problema</th></tr></thead>
        <tbody>
          <tr><td>Recibos</td><td>Parcial</td><td><span class="uxp-badge b-blue">Parcial</span></td><td>Mismo azul que "Rectificativo" — estados con significado opuesto (cobro en curso vs. corrección) se confunden a simple vista.</td></tr>
          <tr><td>Recibos</td><td>Rectificativo</td><td><span class="uxp-badge b-blue">Rectificativo</span></td><td>Ver fila anterior.</td></tr>
          <tr><td>Recibos</td><td>"devuelto"</td><td><span class="uxp-badge b-orange">Devuelto</span></td><td>Estado mapeado en <code class="uxp-path">badgeEstadoRecibo()</code> pero que ningún flujo real asigna hoy — código muerto que puede inducir a error si se reutiliza.</td></tr>
          <tr><td>Facturas</td><td>Emitida</td><td><span class="uxp-badge b-green">Emitida</span></td><td>Mismo verde que VERI*FACTU "Enviado" en la misma fila — dos badges verdes con significado distinto compiten por la misma señal visual.</td></tr>
        </tbody>
      </table>
    </div>
    <div class="uxp-card">
      <h2>Propuesta: paleta de estados coherente y exclusiva por familia</h2>
      <table class="uxp-table">
        <thead><tr><th>Familia</th><th>Estado</th><th>Badge propuesto</th></tr></thead>
        <tbody>
          <tr><td rowspan="5">Recibos</td><td>Pendiente</td><td><span class="uxp-badge b-orange">Pendiente</span></td></tr>
          <tr><td>Parcial</td><td><span class="uxp-badge b-purple">Parcial</span></td></tr>
          <tr><td>Cobrado</td><td><span class="uxp-badge b-green">Cobrado</span></td></tr>
          <tr><td>Anulado</td><td><span class="uxp-badge b-gray">Anulado</span></td></tr>
          <tr><td>Rectificativo</td><td><span class="uxp-badge b-blue">Rectificativo</span></td></tr>
          <tr><td rowspan="3">Facturas</td><td>Emitida</td><td><span class="uxp-badge b-green">Emitida</span></td></tr>
          <tr><td>Rectificada</td><td><span class="uxp-badge b-gray">Rectificada</span></td></tr>
          <tr><td>Anulada</td><td><span class="uxp-badge b-red">Anulada</span></td></tr>
          <tr><td rowspan="4">VERI·FACTU</td><td>No enviado</td><td><span class="uxp-badge b-gray">No enviado</span></td></tr>
          <tr><td>Pendiente envío</td><td><span class="uxp-badge b-orange">Pendiente envío</span></td></tr>
          <tr><td>Enviado</td><td><span class="uxp-badge b-blue">Enviado ✓</span></td></tr>
          <tr><td>Error</td><td><span class="uxp-badge b-red">Error AEAT</span></td></tr>
        </tbody>
      </table>
      <p class="uxp-note">Regla: verde = completado/positivo · naranja = pendiente de acción · gris = cerrado/histórico (sin acción posible) · azul = informativo/corrección · rojo = solo error o anulación. Un mismo color nunca representa dos conceptos distintos dentro del mismo módulo.</p>
    </div>
  </section>

  <!-- ============ TAB 3: MENU MAS ============ -->
  <section class="uxp-section" id="t3">
    <div class="uxp-card">
      <h2>Menú "Más" — ejemplo interactivo</h2>
      <p class="uxp-desc">Pulsa el botón para abrir el menú. Agrupa por Comunicación / Documentos / Gestión y separa lo destructivo con un divisor y color rojo solo en el texto, nunca como botón suelto en la fila.</p>
      <div style="display:flex; justify-content:flex-end; max-width:260px">
        <div class="uxp-more">
          <button class="uxp-btn primary" onclick="uxpToggleMore(this)">Más ▾</button>
          <div class="uxp-more-panel">
            <div class="uxp-more-group-label">Comunicación</div>
            <button class="uxp-more-item">✉ Enviar por email</button>
            <button class="uxp-more-item">📲 Enviar por WhatsApp</button>
            <div class="uxp-more-divider"></div>
            <div class="uxp-more-group-label">Documentos</div>
            <button class="uxp-more-item">🖶 Imprimir / PDF</button>
            <button class="uxp-more-item">🧾 Generar factura</button>
            <button class="uxp-more-item">📄 Generar DOCX</button>
            <div class="uxp-more-divider"></div>
            <div class="uxp-more-group-label">Gestión</div>
            <button class="uxp-more-item">✎ Editar</button>
            <button class="uxp-more-item">🕓 Ver historial</button>
            <button class="uxp-more-item danger">⊘ Anular</button>
          </div>
        </div>
      </div>
      <p class="uxp-note">Todos los iconos llevan <code class="uxp-path">title</code> (tooltip nativo). Cierra al hacer clic fuera o al pulsar Escape.</p>
    </div>
  </section>

  <!-- ============ TAB 4: PANEL DETALLE ============ -->
  <section class="uxp-section" id="t4">
    <div class="uxp-card">
      <h2>Panel lateral de detalle</h2>
      <p class="uxp-desc">La tabla queda para consultar y localizar. Las acciones avanzadas y el histórico completo viven en un panel lateral que se abre desde la fila, sin navegar a otra pantalla.</p>
      <table class="uxp-table">
        <thead><tr><th>Nº Recibo</th><th>Inquilino</th><th>Total</th><th>Estado</th><th></th></tr></thead>
        <tbody>
          <tr>
            <td>REC-202607-00005</td>
            <td>Comercial Díaz S.L.</td>
            <td>1.452,00&nbsp;€</td>
            <td><span class="uxp-badge b-green">Cobrado</span></td>
            <td><button class="uxp-btn ghost" onclick="uxpOpenPanel()">Ver detalle →</button></td>
          </tr>
        </tbody>
      </table>
      <p class="uxp-note">En la app real, un clic o doble clic sobre la fila también abriría este panel (además del botón explícito, para quien prefiera un objetivo de clic más grande).</p>
    </div>
  </section>

  <!-- ============ TAB 5: ACCIONES AGRUPADAS ============ -->
  <section class="uxp-section" id="t5">
    <div class="uxp-card">
      <h2>Agrupación de acciones por función</h2>
      <p class="uxp-desc">Dentro del panel de detalle o del menú "Más", las acciones se agrupan siempre igual en toda la aplicación — mismo orden, mismos nombres de grupo.</p>
      <div class="uxp-group-row">
        <div class="uxp-group">
          <div class="uxp-group-title">Comunicación</div>
          <button class="uxp-btn ghost">✉ Email</button>
          <button class="uxp-btn ghost">📲 WhatsApp</button>
        </div>
        <div class="uxp-group">
          <div class="uxp-group-title">Documentos</div>
          <button class="uxp-btn ghost">🖶 PDF</button>
          <button class="uxp-btn ghost">🧾 Factura</button>
          <button class="uxp-btn ghost">📄 DOCX</button>
        </div>
        <div class="uxp-group">
          <div class="uxp-group-title">Gestión</div>
          <button class="uxp-btn ghost">✎ Editar</button>
          <button class="uxp-btn ghost">🕓 Historial</button>
          <button class="uxp-btn danger">⊘ Anular</button>
        </div>
      </div>
      <p class="uxp-note">Este mismo agrupado se aplicaría igual en Recibos, Facturas y Contratos — hoy cada módulo ordena sus botones de forma distinta.</p>
    </div>
  </section>

  <!-- ============ TAB 6: ACCIONES MASIVAS ============ -->
  <section class="uxp-section" id="t6">
    <div class="uxp-card">
      <h2>Selección múltiple y acciones masivas</h2>
      <p class="uxp-desc">Marca alguna fila para ver aparecer la barra de acciones masivas.</p>
      <div class="uxp-bulkbar" id="uxp-bulkbar">
        <span id="uxp-bulk-count">0 seleccionados</span>
        <div class="spacer"></div>
        <button class="uxp-btn">✉ Enviar</button>
        <button class="uxp-btn">🖶 Exportar PDF</button>
        <button class="uxp-btn">Más ▾</button>
        <button class="link" onclick="uxpClearSelection()">Deseleccionar todo</button>
      </div>
      <table class="uxp-table">
        <thead><tr><th style="width:30px"><input type="checkbox" class="uxp-checkbox" onclick="uxpToggleAll(this)"></th><th>Nº Recibo</th><th>Inquilino</th><th>Total</th><th>Estado</th></tr></thead>
        <tbody>
          <tr><td><input type="checkbox" class="uxp-checkbox uxp-row-check" onclick="uxpUpdateBulk()"></td><td>REC-202607-00001</td><td>Rodríguez Pérez, Laura</td><td>700,00&nbsp;€</td><td><span class="uxp-badge b-orange">Pendiente</span></td></tr>
          <tr><td><input type="checkbox" class="uxp-checkbox uxp-row-check" onclick="uxpUpdateBulk()"></td><td>REC-202607-00002</td><td>González Sánchez, Pedro</td><td>650,00&nbsp;€</td><td><span class="uxp-badge b-orange">Pendiente</span></td></tr>
          <tr><td><input type="checkbox" class="uxp-checkbox uxp-row-check" onclick="uxpUpdateBulk()"></td><td>REC-202607-00003</td><td>Martín López, María</td><td>720,00&nbsp;€</td><td><span class="uxp-badge b-orange">Pendiente</span></td></tr>
        </tbody>
      </table>
      <p class="uxp-note">Útil para "enviar recordatorio a todos los pendientes del mes" o "exportar en lote", una necesidad real que hoy obliga a repetir la acción recibo a recibo.</p>
    </div>
  </section>

  <!-- ============ TAB 7: FILTROS ============ -->
  <section class="uxp-section" id="t7">
    <div class="uxp-card">
      <h2>Filtros — actual vs. plegable</h2>
      <div class="uxp-compare">
        <div class="uxp-compare-col">
          <span class="uxp-tag antes">Antes — todo visible siempre</span>
          <div style="display:flex; gap:8px; flex-wrap:wrap">
            <select><option>Todos los estados</option></select>
            <select><option>Todos los propietarios</option></select>
            <select><option>Todos los inquilinos</option></select>
            <input placeholder="Desde">
            <input placeholder="Hasta">
            <input placeholder="Buscar en tabla...">
          </div>
          <p class="uxp-note">6 controles de filtro ocupando una fila completa en todas las pantallas, aunque casi nunca se usen todos a la vez.</p>
        </div>
        <div class="uxp-compare-col">
          <span class="uxp-tag despues">Después — búsqueda visible + avanzado plegado</span>
          <div class="uxp-searchbar">🔎 <input placeholder="Buscar inquilino, inmueble, nº de recibo…"></div>
          <details class="uxp-filters">
            <summary>Filtros avanzados (estado, propietario, inquilino, fechas)</summary>
            <div class="uxp-filters-body">
              <select><option>Todos los estados</option></select>
              <select><option>Todos los propietarios</option></select>
              <select><option>Todos los inquilinos</option></select>
              <input placeholder="Desde">
              <input placeholder="Hasta">
            </div>
          </details>
          <p class="uxp-note">La búsqueda rápida cubre el caso de uso más frecuente; el resto se pliega en un <code class="uxp-path">&lt;details&gt;</code> nativo (accesible por teclado, sin JS adicional).</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ============ TAB 8: REGLAS ============ -->
  <section class="uxp-section" id="t8">
    <div class="uxp-card">
      <h2>Reglas de diseño propuestas</h2>
      <ul class="uxp-rules">
        <li>Máximo 2 elementos de acción visibles por fila: 1 acción principal + "Más ▾".</li>
        <li>La acción destructiva (Anular, Eliminar) nunca es el botón principal visible en la fila; vive dentro de "Más", en rojo solo el texto, y siempre pide confirmación.</li>
        <li>No se muestran acciones que no aplican al estado del registro (p. ej. "Cobrar" no aparece en un recibo Anulado).</li>
        <li>Todo icono sin texto lleva <code class="uxp-path">title</code> (tooltip).</li>
        <li>Los botones con texto se reservan para la acción principal de la fila; el resto son iconos o entradas de menú.</li>
        <li>Las acciones legales/fiscales (anular factura, enviar a AEAT) siempre piden confirmación explícita, tal como ya ocurre hoy.</li>
        <li>Las tablas priorizan lectura: nunca más de 2 badges de estado por fila con el mismo color.</li>
        <li>Mismo patrón (fila → acción principal + Más → panel de detalle) en todos los módulos con tabla.</li>
      </ul>
    </div>
  </section>

</div>

<!-- Panel lateral -->
<div class="uxp-overlay" id="uxp-overlay" onclick="uxpClosePanel()"></div>
<div class="uxp-panel" id="uxp-panel">
  <div class="uxp-panel-head">
    <div>
      <div style="font-size:11px;color:var(--gray-500);text-transform:uppercase;letter-spacing:.04em">Recibo</div>
      <div style="font-size:18px;font-weight:700">REC-202607-00005</div>
    </div>
    <button class="uxp-btn ghost icon" onclick="uxpClosePanel()">✕</button>
  </div>
  <div class="uxp-panel-body">
    <span class="uxp-badge b-green">Cobrado</span>
    <div class="uxp-kv"><span>Inquilino</span><b>Comercial Díaz S.L.</b></div>
    <div class="uxp-kv"><span>Inmueble</span><b>AV Constitución 8 BAJO A</b></div>
    <div class="uxp-kv"><span>Período</span><b>Julio 2026</b></div>
    <div class="uxp-kv"><span>Total</span><b>1.452,00 €</b></div>
    <div class="uxp-kv"><span>Pagado</span><b style="color:var(--green)">1.452,00 €</b></div>

    <div class="uxp-panel-group-title">Historial de cobros</div>
    <div class="uxp-kv"><span>01/07/2026 · Transferencia</span><b>1.452,00 €</b></div>

    <div class="uxp-panel-group-title">Documentos asociados</div>
    <div class="uxp-kv"><span>Factura</span><b>No generada</b></div>

    <div class="uxp-panel-group-title">Comunicación</div>
    <div style="display:flex; gap:8px">
      <button class="uxp-btn ghost">✉ Email</button>
      <button class="uxp-btn ghost">📲 WhatsApp</button>
    </div>

    <div class="uxp-panel-group-title">Gestión</div>
    <div style="display:flex; gap:8px; flex-wrap:wrap">
      <button class="uxp-btn ghost">🖶 PDF</button>
      <button class="uxp-btn ghost">🧾 Generar factura</button>
      <button class="uxp-btn ghost">✎ Editar</button>
      <button class="uxp-btn danger">⊘ Anular</button>
    </div>
  </div>
  <div class="uxp-panel-foot">
    <button class="uxp-btn ghost" style="flex:1" onclick="uxpClosePanel()">Cerrar</button>
  </div>
</div>

<script>
// ---- Tabs ----
document.getElementById('uxp-nav').addEventListener('click', function(e){
  var btn = e.target.closest('button[data-tab]');
  if(!btn) return;
  document.querySelectorAll('#uxp-nav button').forEach(function(b){ b.classList.remove('active'); });
  btn.classList.add('active');
  document.querySelectorAll('.uxp-section').forEach(function(s){ s.classList.remove('active'); });
  document.getElementById(btn.dataset.tab).classList.add('active');
  window.scrollTo({top:0, behavior:'smooth'});
});

// ---- Menú "Más" ----
function uxpToggleMore(btn){
  var panel = btn.nextElementSibling;
  var wasOpen = panel.classList.contains('open');
  document.querySelectorAll('.uxp-more-panel.open').forEach(function(p){ p.classList.remove('open'); });
  if(!wasOpen) panel.classList.add('open');
}
document.addEventListener('click', function(e){
  if(!e.target.closest('.uxp-more')){
    document.querySelectorAll('.uxp-more-panel.open').forEach(function(p){ p.classList.remove('open'); });
  }
});
document.addEventListener('keydown', function(e){
  if(e.key === 'Escape'){
    document.querySelectorAll('.uxp-more-panel.open').forEach(function(p){ p.classList.remove('open'); });
    uxpClosePanel();
  }
});

// ---- Panel lateral ----
function uxpOpenPanel(){
  document.getElementById('uxp-overlay').classList.add('open');
  document.getElementById('uxp-panel').classList.add('open');
}
function uxpClosePanel(){
  document.getElementById('uxp-overlay').classList.remove('open');
  document.getElementById('uxp-panel').classList.remove('open');
}

// ---- Acciones masivas ----
function uxpToggleAll(master){
  document.querySelectorAll('.uxp-row-check').forEach(function(c){ c.checked = master.checked; });
  uxpUpdateBulk();
}
function uxpUpdateBulk(){
  var n = document.querySelectorAll('.uxp-row-check:checked').length;
  var bar = document.getElementById('uxp-bulkbar');
  document.getElementById('uxp-bulk-count').textContent = n + (n === 1 ? ' seleccionado' : ' seleccionados');
  bar.classList.toggle('show', n > 0);
}
function uxpClearSelection(){
  document.querySelectorAll('.uxp-row-check').forEach(function(c){ c.checked = false; });
  uxpUpdateBulk();
}

// ---- Demo de recibos por estado (tab 1) ----
var UXP_RECIBOS = [
  { num:'REC-202607-00006', total:'800,00 €',   pagado:'—',          estado:'pendiente',     badge:'b-orange', accion:'Cobrar', cls:'success' },
  { num:'REC-202606-00003', total:'720,00 €',   pagado:'360,00 €',   estado:'parcial',        badge:'b-purple', accion:'Completar cobro', cls:'success' },
  { num:'REC-202605-00005', total:'1.452,00 €', pagado:'1.452,00 €', estado:'cobrado',        badge:'b-green',  accion:'Ver cobro', cls:'ghost' },
  { num:'REC-202607-00005', total:'1.452,00 €', pagado:'1.452,00 €', estado:'anulado',        badge:'b-gray',   accion:'Ver', cls:'ghost' },
  { num:'RER-202607-00002', total:'-1.452,00 €',pagado:'-1.452,00 €',estado:'rectificativo',  badge:'b-blue',   accion:'Ver', cls:'ghost' },
];
var UXP_LABELS = { pendiente:'Pendiente', parcial:'Parcial', cobrado:'Cobrado', anulado:'Anulado', rectificativo:'Rectificativo' };
(function(){
  var tbody = document.getElementById('uxp-recibos-demo');
  tbody.innerHTML = UXP_RECIBOS.map(function(r){
    return '<tr>' +
      '<td>' + r.num + '</td>' +
      '<td>' + r.total + '</td>' +
      '<td>' + r.pagado + '</td>' +
      '<td><span class="uxp-badge ' + r.badge + '">' + UXP_LABELS[r.estado] + '</span></td>' +
      '<td><button class="uxp-btn ' + r.cls + '">' + r.accion + '</button></td>' +
      '<td>' +
        '<div class="uxp-more">' +
          '<button class="uxp-btn ghost" onclick="uxpToggleMore(this)">Más ▾</button>' +
          '<div class="uxp-more-panel">' +
            '<div class="uxp-more-group-label">Comunicación</div>' +
            '<button class="uxp-more-item">✉ Email</button>' +
            '<button class="uxp-more-item">📲 WhatsApp</button>' +
            '<div class="uxp-more-divider"></div>' +
            '<div class="uxp-more-group-label">Documentos</div>' +
            '<button class="uxp-more-item">🖶 PDF</button>' +
            (r.estado==='pendiente'||r.estado==='parcial'||r.estado==='cobrado' ? '<button class="uxp-more-item">🧾 Generar factura</button>' : '') +
            '<div class="uxp-more-divider"></div>' +
            '<div class="uxp-more-group-label">Gestión</div>' +
            '<button class="uxp-more-item">✎ Editar</button>' +
            (r.estado==='pendiente'||r.estado==='parcial'||r.estado==='cobrado' ? '<button class="uxp-more-item danger">⊘ Anular</button>' : '') +
          '</div>' +
        '</div>' +
      '</td>' +
    '</tr>';
  }).join('');
})();
</script>
</body>
</html>
