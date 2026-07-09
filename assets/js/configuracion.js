// ===========================
// CONFIGURACIÓN DEL SISTEMA
// Página para editar los parámetros globales almacenados en la tabla
// 'configuracion'. Los cambios se guardan en la BD y surten efecto
// en la siguiente navegación a la sección correspondiente.
// La página usa pestañas: cada grupo es una pestaña independiente.
// ===========================

const _CFG_GRUPOS = [
  // ── Pestaña 1: Dashboard ─────────────────────────────────────
  {
    id: 'dashboard',
    titulo: 'Dashboard',
    icono: '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
    desc: 'Controla qué elementos aparecen en la pantalla de inicio (Dashboard).',
    params: [
      {
        v: 'dash_kpis', label: 'Tarjetas de resumen', tipo: 'toggle',
        desc: 'Muestra las 4 tarjetas superiores: Propietarios, Inmuebles, Contratos activos y Pendiente de cobro.',
        ayuda: 'Las tarjetas de resumen (KPIs) aparecen en la parte superior del Dashboard y ofrecen una visión rápida del estado de la cartera.\n\nDesactivarlas si prefieres una pantalla de inicio más limpia o si los datos no son relevantes para tu flujo de trabajo diario.',
      },
      {
        v: 'dash_alerta_ipc', label: 'Alerta IPC/IRAV pendiente', tipo: 'toggle',
        desc: 'Muestra el aviso naranja cuando hay contratos con revisión IPC o IRAV pendiente este mes.',
        ayuda: 'Este aviso aparece en el mes del aniversario de cada contrato con revisión IPC o IRAV cuando aún no se ha aplicado la subida de renta.\n\nDesactivar si prefieres gestionar las revisiones directamente desde la sección Contratos sin ver el aviso en el Dashboard.',
      },
      {
        v: 'dash_alerta_backup', label: 'Alerta de backup', tipo: 'toggle',
        desc: 'Muestra el aviso azul cuando no se ha hecho backup en los últimos 7 días.',
        ayuda: 'Este aviso aparece si nunca se ha descargado un backup o si el último fue hace más de 7 días.\n\nDesactivar si realizas copias de seguridad por otro método (ej: backup automático del servidor MySQL) y no necesitas el recordatorio visual.',
      },
      {
        v: 'dash_avisos_revision', label: 'Avisos de revisión urgentes', tipo: 'toggle',
        desc: 'Muestra la tarjeta de revisiones anuales próximas (contratos con aniversario en los próximos 30 días).',
        ayuda: 'Esta tarjeta aparece cuando hay contratos con revisión anual de renta en los próximos 30 días.\n\nMuestra el inmueble, el inquilino, la fecha de revisión, el año del contrato y los días restantes. Desactivar si ya gestionas las revisiones por otro medio.',
      },
      {
        v: 'dash_renovaciones', label: 'Próximas renovaciones de contrato', tipo: 'toggle',
        desc: 'Muestra la tabla de contratos vencidos o con vencimiento en los próximos 6 meses.',
        ayuda: 'Esta tabla lista todos los contratos activos cuya fecha de fin está dentro de los próximos 6 meses, o que ya han vencido.\n\nIncluye el tiempo restante, el plazo para avisar al inquilino y el botón "Gestionar" para acceder al contrato directamente.',
      },
      {
        v: 'dash_revisiones', label: 'Tabla de revisiones anuales', tipo: 'toggle',
        desc: 'Muestra la tabla completa con las próximas revisiones anuales de todos los contratos activos.',
        ayuda: 'Muestra todos los contratos activos con la fecha de su próxima revisión anual de renta, el año del contrato y los días restantes.\n\nÚtil para planificar con antelación. Desactivar si la tabla resulta demasiado larga o no necesitas ver todos los contratos a la vez.',
      },
      {
        v: 'dash_ultimos_recibos', label: 'Últimos recibos', tipo: 'toggle',
        desc: 'Muestra la tabla con los 5 últimos recibos generados y su estado.',
        ayuda: 'Muestra los 5 recibos más recientes con número, inquilino, importe y estado.\n\nDesactivar si navegas directamente a la sección Recibos para consultar el estado de los cobros y no necesitas el resumen en el Dashboard.',
      },
      {
        v: 'dash_cobrado_mes', label: 'Cobrado este mes', tipo: 'toggle',
        desc: 'Muestra la tarjeta con el importe total cobrado en el mes actual y los recibos pendientes.',
        ayuda: 'Muestra el total cobrado en el mes en curso, el número de recibos pendientes y el número total de recibos cobrados.\n\nDesactivar si prefieres ver estos datos en la sección de Informes o si el Dashboard te resulta más limpio sin ella.',
      },
      {
        v: 'dash_graficos', label: 'Gráficos de ingresos y ocupación', tipo: 'toggle',
        desc: 'Muestra los gráficos de barras (ingresos últimos 6 meses) y de dona (ocupación de inmuebles).',
        ayuda: 'Los dos gráficos muestran de forma visual los ingresos cobrados en los últimos 6 meses y el porcentaje de inmuebles ocupados respecto al total.\n\nDesactivar si prefieres una pantalla de inicio más rápida o si los gráficos no aportan información relevante para tu flujo habitual.',
      },
      {
        v: 'dash_cobros_esperados', label: 'Previsión de cobros del mes', tipo: 'toggle',
        desc: 'Muestra el widget con los cobros esperados, cobrados y pendientes para el mes actual.',
        ayuda: 'Muestra una tarjeta con el total de recibos emitidos para el mes en curso, desglosado en cobrado, pendiente y barra de progreso de recaudación.\n\nDesactivar si no necesitas ver este resumen en el Dashboard o si ya consultas los cobros desde la sección Recibos.',
      },
      {
        v: 'dash_backup_dias', label: 'Días sin backup para avisar', tipo: 'number', min: 1, max: 365, def: '7',
        desc: 'Número de días sin backup a partir del cual aparece el aviso en el Dashboard.',
        ayuda: 'Si han transcurrido más de este número de días desde el último backup descargado, el Dashboard muestra un aviso recordatorio.\n\nPor defecto son 7 días (una semana). Auméntalo si haces backups con menos frecuencia (ej: 30 para mensual). El aviso solo aparece si la alerta de backup también está activada.',
      },
      {
        v: 'dash_log_actividad', label: 'Últimas actividades', tipo: 'toggle',
        desc: 'Muestra el widget con las 5 últimas acciones registradas en el log de auditoría (cobros, facturas, bajas, etc.).',
        ayuda: 'El widget de actividad reciente muestra las últimas acciones registradas en el sistema: cobros, facturas generadas, anulaciones, subidas de IPC, etc.\n\nRequiere que el log de actividad esté activado en config.php. Desactivar este widget si prefieres consultar el historial desde el menú Informes > Actividad.',
      },
    ],
  },

  // ── Pestaña 2: Paginación ────────────────────────────────────
  {
    id: 'paginacion',
    titulo: 'Paginación',
    icono: '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="4" rx="1"/><rect x="3" y="10" width="18" height="4" rx="1"/><rect x="3" y="17" width="12" height="4" rx="1"/></svg>',
    desc: 'Número de filas que muestra cada tabla paginada.',
    params: [
      {
        v: 'filas_dashboard', label: 'Dashboard', tipo: 'number', min: 1, max: 50, def: '6',
        desc: 'Filas por página en las tarjetas del Dashboard.',
        ayuda: 'Controla cuántas filas aparecen en cada página de las tarjetas de "Renovaciones próximas" y "Revisiones anuales" del Dashboard.\n\nValores bajos (3–5) van bien en pantallas pequeñas o cuando hay pocos contratos. Valores altos (10–20) muestran más información de un vistazo. El mínimo es 1.',
      },
      {
        v: 'filas_propietarios', label: 'Propietarios', tipo: 'number', min: 5, max: 200, def: '20',
        desc: 'Filas por página en la tabla de Propietarios.',
        ayuda: 'Controla cuántos propietarios se muestran por página en la sección Propietarios.\n\nEl valor por defecto es 20. El mínimo es 5.',
      },
      {
        v: 'filas_fincas', label: 'Fincas', tipo: 'number', min: 5, max: 200, def: '20',
        desc: 'Filas por página en la tabla de Fincas.',
        ayuda: 'Controla cuántas fincas se muestran por página en la sección Fincas / Edificios.\n\nEl valor por defecto es 20. El mínimo es 5.',
      },
      {
        v: 'filas_inmuebles', label: 'Pisos / Locales', tipo: 'number', min: 5, max: 200, def: '20',
        desc: 'Filas por página en la tabla de Pisos / Locales.',
        ayuda: 'Controla cuántos pisos y locales se muestran por página en la sección Pisos / Locales.\n\nEl valor por defecto es 20. El mínimo es 5.',
      },
      {
        v: 'filas_inquilinos', label: 'Inquilinos', tipo: 'number', min: 5, max: 200, def: '20',
        desc: 'Filas por página en la tabla de Inquilinos.',
        ayuda: 'Controla cuántos inquilinos se muestran por página en la sección Inquilinos.\n\nEl valor por defecto es 20. El mínimo es 5.',
      },
      {
        v: 'filas_contratos', label: 'Contratos', tipo: 'number', min: 5, max: 200, def: '20',
        desc: 'Filas por página en la tabla de Contratos.',
        ayuda: 'Controla cuántos contratos se muestran por página en la sección Contratos.\n\nEl valor por defecto es 20. El mínimo es 5.',
      },
      {
        v: 'filas_recibos', label: 'Recibos', tipo: 'number', min: 5, max: 200, def: '30',
        desc: 'Filas por página en la tabla de Recibos.',
        ayuda: 'Controla cuántos recibos se muestran por página en la sección Recibos.\n\nEl valor por defecto es 30. Si tienes muchos recibos y quieres verlos todos de un vistazo, sube este número (ej: 50 o 100). Si la página tarda en cargar, bájalo. El mínimo es 5.',
      },
      {
        v: 'filas_facturas', label: 'Facturas', tipo: 'number', min: 5, max: 200, def: '20',
        desc: 'Filas por página en la tabla de Facturas.',
        ayuda: 'Controla cuántas facturas se muestran por página en la sección Facturas.\n\nEl valor por defecto es 20. El mínimo es 5.',
      },
    ],
  },

  // ── Pestaña 3: Visibilidad de botones ───────────────────────
  {
    id: 'visibilidad',
    titulo: 'Botones',
    icono: '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
    desc: 'Muestra u oculta los botones de acción en cada tabla. Los botones de Editar siempre son visibles.',
    params: [
      // ── Contratos ──────────────────────────────────────────
      { tipo: 'heading', label: 'Contratos' },
      {
        v: 'VisiGenerarReciboCont', label: 'Generar recibo', tipo: 'toggle',
        desc: 'Muestra el botón "Generar recibo" en la tabla de contratos activos.',
        ayuda: 'Si está desactivado, el botón verde "Generar recibo" no aparece en la fila de cada contrato activo.\n\nÚtil si los recibos se generan desde la sección Generar Recibos en lugar de uno a uno desde la tabla de contratos.',
      },
      {
        v: 'VisiRenovarCont', label: 'Renovar contrato', tipo: 'toggle',
        desc: 'Muestra el botón "Renovar" en la tabla de contratos activos.',
        ayuda: 'Si está desactivado, el botón "Renovar" no aparece en la fila de cada contrato activo.\n\nDesactivar si las renovaciones se gestionan manualmente editando las fechas del contrato.',
      },
      {
        v: 'VisiHistorialCont', label: 'Historial de rentas', tipo: 'toggle',
        desc: 'Muestra el botón "Historial" en la tabla de contratos activos.',
        ayuda: 'Si está desactivado, el botón "Historial" no aparece en la fila de cada contrato activo.\n\nEl historial de rentas muestra todas las subidas aplicadas al contrato a lo largo del tiempo.',
      },
      {
        v: 'VisiBajaCont', label: 'Dar de baja', tipo: 'toggle',
        desc: 'Muestra el botón "Baja" en la tabla de contratos activos.',
        ayuda: 'Si está desactivado, el botón rojo "Baja" no aparece en la fila de cada contrato activo.\n\nDesactivar para evitar que se den de baja contratos por accidente. El contrato puede darse de baja igualmente editándolo.',
      },
      {
        v: 'VisiPDFCont', label: 'PDF del contrato', tipo: 'toggle', def: '0',
        desc: 'Muestra el botón "PDF" para generar el PDF del contrato.',
        ayuda: 'Si está desactivado, el botón "PDF" no aparece en la fila de cada contrato.\n\nEl PDF del contrato incluye todos los datos: inmueble, inquilino, renta, fechas y cláusulas.',
      },
      {
        v: 'VisiFianzaCont', label: 'Justificante de fianza', tipo: 'toggle',
        desc: 'Muestra el botón "Fianza" para generar el justificante PDF de la fianza.',
        ayuda: 'Si está desactivado, el botón "Fianza" no aparece aunque el contrato tenga fianza registrada.\n\nEl justificante de fianza es el documento que acredita el depósito de la fianza por el inquilino.',
      },
      {
        v: 'VisiDocxCont', label: 'Generar DOCX', tipo: 'toggle',
        desc: 'Muestra la opción "Contrato en DOCX" (menú Más) para generar documentos desde plantillas Word.',
        ayuda: 'Si está desactivado, la opción "Contrato en DOCX" no aparece en el menú "Más" de cada contrato.\n\nRequiere tener el módulo de plantillas activo (pestaña Documentos) y al menos una plantilla subida.',
      },
      // ── Recibos ────────────────────────────────────────────
      { tipo: 'heading', label: 'Recibos' },
      {
        v: 'VisiCobrarReci', label: 'Cobrar / Ver cobros', tipo: 'toggle',
        desc: 'Muestra el botón "Cobrar" (pendientes) y "Ver cobros" (cobrados) en la tabla de recibos.',
        ayuda: 'Si está desactivado, los botones "Cobrar" y "Ver cobros" no aparecen en la tabla de recibos.\n\nDesactivar solo si los cobros se registran por otro canal y no se usan desde esta tabla.',
      },
      {
        v: 'VisiEmailReci', label: 'Enviar por email', tipo: 'toggle',
        desc: 'Muestra el botón de email ✉ en cada fila de la tabla de recibos.',
        ayuda: 'Si está desactivado, el botón de envío por email no aparece en la tabla de recibos.\n\nRequiere que el inquilino tenga un email registrado. Desactivar si no se usan notificaciones por email.',
      },
      {
        v: 'VisiImprimirReci', label: 'Imprimir recibo', tipo: 'toggle',
        desc: 'Muestra el botón de imprimir/PDF en cada fila de la tabla de recibos.',
        ayuda: 'Si está desactivado, el botón de impresión no aparece en la tabla de recibos.\n\nEste botón abre el modal de previsualización e impresión del recibo en PDF.',
      },
      {
        v: 'VisiFacturaReci', label: 'Generar / ver factura', tipo: 'toggle',
        desc: 'Muestra el botón de generar factura legal o ver la factura vinculada al recibo.',
        ayuda: 'Si está desactivado, el botón de factura no aparece en la tabla de recibos.\n\nRequiere tener el módulo de facturación activo. Muestra "Generar factura" si el recibo no tiene factura, o "Ver factura" si ya está vinculado a una.',
      },
      {
        v: 'VisiAnularReci', label: 'Anular recibo', tipo: 'toggle',
        desc: 'Muestra el botón Anular en la tabla de Recibos.',
        ayuda: 'Si está desactivado, el botón "Anular" no aparece en la lista de Recibos.\n\nAnular un recibo lo marca como "anulado" y lo excluye de los totales pendientes. Esta acción no se puede deshacer desde la interfaz.',
      },
      {
        v: 'VisiAnularPago', label: 'Anular cobro', tipo: 'toggle',
        desc: 'Muestra el botón Anular cobro en el panel de detalle de un recibo.',
        ayuda: 'Si está desactivado, no se puede anular un cobro ya registrado desde el modal de detalle del recibo.\n\nÚtil para evitar modificaciones accidentales de pagos ya contabilizados.',
      },
      // ── Facturas ───────────────────────────────────────────
      { tipo: 'heading', label: 'Facturas' },
      {
        v: 'VisiImprimirFact', label: 'Imprimir / PDF', tipo: 'toggle',
        desc: 'Muestra el botón de imprimir/PDF en cada fila de la tabla de facturas.',
        ayuda: 'Si está desactivado, el botón de impresión no aparece en la tabla de facturas.\n\nEste botón abre el modal de previsualización e impresión de la factura.',
      },
      {
        v: 'VisiEmailFact', label: 'Enviar por email', tipo: 'toggle',
        desc: 'Muestra el botón de email ✉ en cada fila de la tabla de facturas.',
        ayuda: 'Si está desactivado, el botón de envío por email no aparece en la tabla de facturas.\n\nRequiere que el cliente tenga un email registrado en el recibo.',
      },
      {
        v: 'VisiReciboOrigenFact', label: 'Ver recibo origen', tipo: 'toggle',
        desc: 'Muestra el botón para navegar al recibo que originó la factura.',
        ayuda: 'Si está desactivado, el icono de enlace al recibo origen no aparece en la tabla de facturas.\n\nÚtil para trazabilidad: permite saltar directamente del recibo a su factura y viceversa.',
      },
      {
        v: 'VisiAEATFact', label: 'Enviar a AEAT (VERI*FACTU)', tipo: 'toggle',
        desc: 'Muestra el botón de envío a la AEAT cuando VERI*FACTU está activo.',
        ayuda: 'Si está desactivado, el botón "🛡 AEAT" no aparece en la tabla de facturas.\n\nSolo relevante si el módulo VERI*FACTU está activado en la pestaña correspondiente.',
      },
      {
        v: 'VisiXMLFact', label: 'Ver XML AEAT', tipo: 'toggle',
        desc: 'Muestra el botón para ver el XML enviado a la AEAT en facturas ya enviadas.',
        ayuda: 'Si está desactivado, el botón "Ver XML" no aparece en facturas con estado "enviado".\n\nPermite revisar el XML generado para una factura ya enviada al SIF de la AEAT.',
      },
      {
        v: 'VisiAnularFact', label: 'Anular factura', tipo: 'toggle',
        desc: 'Muestra el botón Anular en las facturas en estado "emitida".',
        ayuda: 'Si está desactivado, el botón "Anular" no aparece en la tabla de facturas.\n\nAnular una factura la marca como "anulada". Si VERI*FACTU está activo, se genera automáticamente una factura rectificativa.',
      },
      // ── Inquilinos ─────────────────────────────────────────
      { tipo: 'heading', label: 'Inquilinos' },
      {
        v: 'VisiPagosInq', label: 'Historial de pagos', tipo: 'toggle',
        desc: 'Muestra el botón "Pagos" en la tabla de inquilinos.',
        ayuda: 'Si está desactivado, el botón "Pagos" no aparece en la lista de inquilinos.\n\nEste botón abre el historial completo de recibos y pagos del inquilino.',
      },
      {
        v: 'VisiHistorialInq', label: 'Historial completo', tipo: 'toggle',
        desc: 'Muestra el botón "Historial" en la tabla de inquilinos.',
        ayuda: 'Si está desactivado, el botón "Historial" no aparece en la lista de inquilinos.\n\nMuestra el historial de contratos, inmuebles y actividad del inquilino.',
      },
      {
        v: 'VisiBorrarInq', label: 'Eliminar inquilino', tipo: 'toggle',
        desc: 'Muestra el botón Eliminar en la lista de Inquilinos.',
        ayuda: 'Si está desactivado, el botón Eliminar no aparece en la lista de Inquilinos.',
      },
      // ── Propietarios ───────────────────────────────────────
      { tipo: 'heading', label: 'Propietarios' },
      {
        v: 'VisiIRPFProp', label: 'Informe IRPF', tipo: 'toggle',
        desc: 'Muestra el botón "IRPF" para generar el informe fiscal anual del propietario.',
        ayuda: 'Si está desactivado, el botón "IRPF" no aparece en la lista de propietarios.\n\nEste informe resume los ingresos por alquiler del propietario para la declaración anual del IRPF.',
      },
      {
        v: 'VisiBorrarProp', label: 'Eliminar propietario', tipo: 'toggle',
        desc: 'Muestra el botón Eliminar en la lista de Propietarios.',
        ayuda: 'Si está desactivado, el botón rojo "Eliminar" no aparece en la lista de Propietarios.\n\nÚtil para evitar borrados accidentales. Nota: aunque el botón esté visible, no es posible eliminar un propietario si tiene fincas asociadas.',
      },
      // ── Fincas / Inmuebles ─────────────────────────────────
      { tipo: 'heading', label: 'Fincas / Inmuebles' },
      {
        v: 'VisiBorrarFinc', label: 'Eliminar finca', tipo: 'toggle',
        desc: 'Muestra el botón Eliminar en la lista de Fincas.',
        ayuda: 'Si está desactivado, el botón Eliminar no aparece en la lista de Fincas.\n\nNo es posible eliminar una finca si tiene inmuebles asociados, aunque el botón esté visible.',
      },
      {
        v: 'VisiBorrarInm', label: 'Eliminar inmueble', tipo: 'toggle',
        desc: 'Muestra el botón Eliminar en la lista de Inmuebles.',
        ayuda: 'Si está desactivado, el botón Eliminar no aparece en la lista de Inmuebles.\n\nNo es posible eliminar un inmueble si tiene contratos activos.',
      },
      // ── Sistema ────────────────────────────────────────────
      { tipo: 'heading', label: 'Sistema' },
      {
        v: 'VisiBackupJSON', label: 'Descargar backup JSON', tipo: 'toggle', def: '0',
        desc: 'Muestra el botón de descarga de backup JSON en Mi Empresa.',
        ayuda: 'Si está activado, aparece el botón "Descargar backup JSON" en la cabecera de Mi Empresa, que permite exportar todos los datos de la base de datos en formato JSON.\n\nDesactivado por defecto para mantener la interfaz limpia. Actívalo solo cuando necesites hacer una copia de seguridad manual.',
      },
    ],
  },

  // ── Pestaña 4: WhatsApp ──────────────────────────────────────
  {
    id: 'whatsapp',
    titulo: 'WhatsApp',
    icono: '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
    desc: 'Opciones para el envío de notificaciones a inquilinos por WhatsApp.',
    params: [
      {
        v: 'whatsappVis', label: 'Mostrar botones de WhatsApp', tipo: 'toggle',
        desc: 'Muestra los botones de WhatsApp en recibos y en el modal de impresión en lote.',
        ayuda: 'Activa o desactiva todos los botones de envío por WhatsApp de la aplicación: el icono en cada fila de la tabla de recibos y el botón del modal de impresión en lote.\n\nDesactívalo si no usas WhatsApp como canal de comunicación con inquilinos.',
      },
      {
        v: 'whatsappPDF', label: 'Adjuntar PDF al enviar', tipo: 'toggle',
        desc: 'Descarga el PDF del recibo automáticamente al pulsar el botón de WhatsApp.',
        ayuda: 'Cuando está activo, al pulsar el botón de WhatsApp en un recibo se genera y descarga automáticamente el PDF del recibo, además de abrir WhatsApp con el mensaje predefinido.\n\nAsí puedes adjuntarlo manualmente en la conversación de WhatsApp. Si no necesitas el PDF, desactívalo para acelerar el proceso.',
      },
      {
        v: 'whatsappNativo', label: 'Abrir en ventana emergente', tipo: 'toggle',
        desc: 'Abre WhatsApp Web en ventana emergente (activo) o como enlace directo (inactivo).',
        ayuda: 'Activo: WhatsApp Web se abre en una ventana emergente (window.open). Permite seguir usando la aplicación en la ventana principal.\n\nDesactivado: se usa un enlace href directo, que nunca es bloqueado por el navegador pero puede abrir WhatsApp Desktop si está instalado en el equipo, o navegar en la misma pestaña.',
      },
    ],
  },

  // ── Pestaña 5: VERI*FACTU ───────────────────────────────────
  {
    id: 'verifactu',
    titulo: 'VERI*FACTU',
    icono: '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
    desc: 'Conexión con el Sistema Inmediato de Facturación (SIF) de la AEAT.',
    params: [
      {
        v: 'verifactu_activo', label: 'Activar VERI*FACTU', tipo: 'toggle',
        desc: 'Habilita el envío de facturas a la AEAT. Mantener desactivado hasta tener todo listo.',
        ayuda: 'Al activar, AlquiGest comenzará a enviar las facturas al SIF de la AEAT de forma automática.\n\nIMPORTANTE: NO activar hasta haber configurado y probado el certificado digital en el entorno de "pruebas" y haber recibido confirmación de alta por parte de la AEAT.',
      },
      {
        v: 'verifactu_entorno', label: 'Entorno AEAT', tipo: 'select', opciones: ['pruebas', 'produccion'],
        desc: 'pruebas → prewww1.aeat.es · produccion → www1.aeat.es',
        ayuda: 'Selecciona "pruebas" para verificar que la integración funciona correctamente sin consecuencias fiscales reales. La URL de pruebas es prewww1.aeat.es.\n\nCambia a "produccion" (www1.aeat.es) solo cuando el certificado esté en vigor y la AEAT haya confirmado el alta del sistema en el registro oficial.',
      },
      {
        v: 'verifactu_cert_path', label: 'Ruta del certificado', tipo: 'text',
        desc: 'Ruta relativa al certificado .p12 / .pfx (ej: certs/cert_verifactu.p12)',
        ayuda: 'Ruta relativa desde la carpeta raíz de AlquiGest al fichero de certificado digital (.p12 o .pfx) expedido por la FNMT u otra entidad de certificación autorizada por la AEAT.\n\nEl fichero debe estar en el servidor MAMP, no en el navegador. Ejemplo: certs/mi_certificado.p12',
      },
      {
        v: 'verifactu_cert_pass', label: 'Contraseña del certificado', tipo: 'password',
        desc: 'Contraseña del fichero .p12 / .pfx.',
        ayuda: 'Contraseña que protege el certificado digital .p12 o .pfx.\n\nSe guarda en la base de datos local de MAMP (solo accesible desde localhost). No se transmite a ningún servidor externo salvo en el momento de firmar una factura para enviarla a la AEAT.',
      },
      {
        v: 'verifactu_nif_sif', label: 'NIF obligado (SIF)', tipo: 'text',
        desc: 'NIF del obligado de emisión ante el SIF (normalmente igual al NIF de empresa).',
        ayuda: 'El NIF que se usa para identificarse ante el Sistema Inmediato de Facturación de la AEAT.\n\nNormalmente coincide con el CIF/NIF introducido en "Mi Empresa". Solo difiere si el obligado tributario ante la AEAT es una persona o entidad distinta del emisor habitual.',
      },
      {
        v: 'verifactu_sistema_nombre', label: 'Nombre del sistema', tipo: 'text',
        desc: 'Nombre del sistema informático de facturación declarado ante la AEAT.',
        ayuda: 'Identificador del software de facturación tal como fue declarado en el registro de sistemas de la AEAT. Por defecto es "AlquiGest".\n\nNo modificar salvo indicación expresa de la AEAT o si has realizado un registro con un nombre diferente.',
      },
      {
        v: 'verifactu_sistema_version', label: 'Versión del sistema', tipo: 'text',
        desc: 'Versión del sistema informático declarada ante la AEAT (ej: 2.0.0).',
        ayuda: 'Versión del software declarada ante la AEAT en el momento del registro. Se actualiza con cada nueva versión de AlquiGest.\n\nSolo modificar si has actualizado AlquiGest y la nueva versión requiere declarar una versión diferente ante la AEAT.',
      },
      {
        v: 'verifactu_num_instalacion', label: 'Número de instalación', tipo: 'number', min: 1, max: 999, def: '1',
        desc: 'Número de instalación del sistema (1 para instalación única).',
        ayuda: 'Identificador numérico de la instalación cuando hay varios equipos usando el mismo sistema de facturación con el mismo NIF.\n\nEn la mayoría de casos es 1 (instalación única en un solo equipo o servidor MAMP). Solo cambiar si la AEAT o un asesor fiscal indican lo contrario.',
      },
    ],
  },

  // ── Pestaña 6: Documentos ────────────────────────────────────
  {
    id: 'documentos',
    titulo: 'Documentos',
    icono: '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
    desc: 'Configuración del sistema de plantillas DOCX para generación de documentos.',
    params: [
      {
        v: 'docs_plantillas_activas', label: 'Sistema de plantillas DOCX', tipo: 'toggle',
        desc: 'Activa o desactiva el módulo de plantillas DOCX en toda la aplicación.',
        ayuda: 'Cuando está activo, aparece la sección "Plantillas" en el menú de Configuración y la opción "Contrato en DOCX" (menú Más) en cada contrato para generar documentos personalizados.\n\nDesactivar si no usas plantillas Word o prefieres generar documentos solo mediante la función PDF integrada.',
      },
      {
        v: 'docs_permitir_pdf', label: 'Generar PDF desde plantilla (no disponible)', tipo: 'toggle',
        desc: 'La conversión de DOCX a PDF requiere LibreOffice en el servidor. No disponible en MAMP/Windows.',
        ayuda: 'Esta función requiere tener LibreOffice instalado en el servidor y accesible desde PHP mediante exec().\n\nEn instalaciones MAMP locales (Windows/Mac) esta funcionalidad no está disponible. La alternativa es abrir el DOCX generado en Word y exportarlo a PDF manualmente.',
      },
      {
        v: 'dash_plantillas_estado', label: 'Mostrar estado de plantillas en Dashboard', tipo: 'toggle',
        desc: 'Muestra un resumen del número de plantillas DOCX activas en la pantalla de inicio.',
        ayuda: 'Cuando está activo, el Dashboard incluye una pequeña tarjeta indicando cuántas plantillas DOCX están activas y disponibles para generar documentos.\n\nDesactivar si prefieres mantener el Dashboard más limpio y no necesitas ver este indicador.',
      },
    ],
  },

  // ── Pestaña final: Visibilidad del menú ─────────────────────
  {
    id: 'menu',
    titulo: 'Menú',
    icono: '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>',
    desc: 'Decide qué opciones aparecen en el menú lateral. Dashboard y Parámetros son siempre visibles.',
    params: [
      { tipo: 'heading', label: 'Maestros' },
      {
        v: 'menu_propietarios', label: 'Propietarios', tipo: 'toggle',
        desc: 'Muestra la opción Propietarios en el menú lateral.',
        ayuda: 'Oculta la opción Propietarios del menú lateral.\n\nLos datos existentes no se borran; la sección simplemente deja de aparecer en el menú. Puedes reactivarla en cualquier momento.',
      },
      {
        v: 'menu_fincas', label: 'Fincas / Edificios', tipo: 'toggle',
        desc: 'Muestra la opción Fincas / Edificios en el menú lateral.',
        ayuda: 'Oculta la opción Fincas / Edificios del menú lateral.\n\nLos datos existentes no se borran. Útil si gestionas inmuebles sueltos sin agruparlos en fincas.',
      },
      {
        v: 'menu_inmuebles', label: 'Pisos / Locales', tipo: 'toggle',
        desc: 'Muestra la opción Pisos / Locales en el menú lateral.',
        ayuda: 'Oculta la opción Pisos / Locales del menú lateral.\n\nLos datos existentes no se borran.',
      },
      {
        v: 'menu_inquilinos', label: 'Inquilinos', tipo: 'toggle',
        desc: 'Muestra la opción Inquilinos en el menú lateral.',
        ayuda: 'Oculta la opción Inquilinos del menú lateral.\n\nLos datos existentes no se borran. La gestión de inquilinos seguirá siendo accesible desde la ficha de cada contrato.',
      },
      { tipo: 'heading', label: 'Alquileres' },
      {
        v: 'menu_contratos', label: 'Contratos', tipo: 'toggle',
        desc: 'Muestra la opción Contratos en el menú lateral.',
        ayuda: 'Oculta la opción Contratos del menú lateral.\n\nLos datos existentes no se borran.',
      },
      {
        v: 'menu_recibos', label: 'Recibos', tipo: 'toggle',
        desc: 'Muestra la opción Recibos en el menú lateral.',
        ayuda: 'Oculta la opción Recibos del menú lateral.\n\nLos recibos existentes no se borran. Útil si no utilizas la gestión de cobros.',
      },
      {
        v: 'menu_facturas', label: 'Facturas', tipo: 'toggle',
        desc: 'Muestra la opción Facturas en el menú lateral.',
        ayuda: 'Oculta la opción Facturas del menú lateral.\n\nLas facturas existentes no se borran. Útil si no emites facturas y operas solo con recibos.',
      },
      {
        v: 'menu_generar', label: 'Generar Recibos', tipo: 'toggle',
        desc: 'Muestra la opción Generar Recibos en el menú lateral.',
        ayuda: 'Oculta la opción de generación masiva de recibos del menú lateral.\n\nÚtil si prefieres generar los recibos uno a uno desde cada contrato.',
      },
      { tipo: 'heading', label: 'Informes' },
      {
        v: 'menu_informes', label: 'Informes Excel', tipo: 'toggle',
        desc: 'Muestra la opción Informes Excel en el menú lateral.',
        ayuda: 'Oculta la opción de exportación a Excel del menú lateral.',
      },
      {
        v: 'menu_calendario', label: 'Calendario Cobros', tipo: 'toggle',
        desc: 'Muestra la opción Calendario de Cobros en el menú lateral.',
        ayuda: 'Oculta el Calendario de Cobros del menú lateral.',
      },
      {
        v: 'menu_morosidad', label: 'Morosidad', tipo: 'toggle',
        desc: 'Muestra la opción Informe de Morosidad en el menú lateral.',
        ayuda: 'Oculta el informe de morosidad del menú lateral.',
      },
      {
        v: 'menu_actividad', label: 'Actividad', tipo: 'toggle',
        desc: 'Muestra la opción Historial de Actividad en el menú lateral.',
        ayuda: 'Oculta el historial de actividad del menú lateral.',
      },
      { tipo: 'heading', label: 'Configuración' },
      {
        v: 'menu_empresa', label: 'Mi Empresa', tipo: 'toggle',
        desc: 'Muestra la opción Mi Empresa en el menú lateral.',
        ayuda: 'Oculta la sección Mi Empresa del menú lateral.\n\nLos datos de empresa siguen disponibles internamente (se usan en PDF de recibos y facturas).',
      },
      {
        v: 'menu_verifactu', label: 'VERI*FACTU', tipo: 'toggle',
        desc: 'Muestra la opción VERI*FACTU en el menú lateral.',
        ayuda: 'Oculta la sección VERI*FACTU del menú lateral.\n\nÚtil si no utilizas facturación electrónica ni el sistema VERI*FACTU de la AEAT.',
      },
      {
        v: 'menu_plantillas', label: 'Plantillas', tipo: 'toggle',
        desc: 'Muestra la opción Plantillas DOCX en el menú lateral.',
        ayuda: 'Oculta la sección de Plantillas DOCX del menú lateral.\n\nÚtil si no utilizas la generación de documentos Word personalizados.',
      },
    ],
  },
];

// Índice de la pestaña activa (persiste mientras la SPA no se recarga)
let _cfgTabActual = 0;

// ── Estilos propios de la página de configuración ────────────
(function _inyectarCfgCSS() {
  if (document.getElementById('cfg-styles')) return;
  const s = document.createElement('style');
  s.id = 'cfg-styles';
  s.textContent = `
    /* ── Pestañas ─────────────────────────────────────── */
    .cfg-tabs {
      display: flex; gap: 2px; margin-bottom: 20px;
      border-bottom: 2px solid var(--gray-200);
      overflow-x: auto; overflow-y: hidden; padding-bottom: 0;
      /* overflow-y:hidden es necesario: al fijar solo overflow-x el eje Y
         calcula a "auto" (regla CSS de overflow), y basta 1px de más por el
         icono+texto para que el navegador dibuje una barra vertical con
         flechas ↑↓ (09/07/2026). */
    }
    .cfg-tabs::-webkit-scrollbar { height: 6px; width: 0; }
    .cfg-tabs::-webkit-scrollbar-thumb { background: var(--gray-300); border-radius: 3px; }
    .cfg-tab {
      display: flex; align-items: center; gap: 6px; flex-shrink: 0;
      padding: 8px 16px; border: none; background: transparent;
      color: var(--gray-500); font-size: 13px; font-weight: 500;
      cursor: pointer; border-bottom: 2px solid transparent;
      margin-bottom: -2px; border-radius: 6px 6px 0 0;
      white-space: nowrap; transition: color .15s, border-color .15s, background .15s;
    }
    .cfg-tab:hover { color: var(--gray-700); background: var(--gray-50); }
    .cfg-tab-active {
      color: var(--blue); border-bottom-color: var(--blue);
      background: var(--blue-light);
    }
    /* ── Filas de parámetros ──────────────────────────── */
    .cfg-card .card-header { align-items: flex-start; }
    .cfg-rows { display: flex; flex-direction: column; padding: 0 20px; }
    .cfg-row {
      display: flex; align-items: center; justify-content: space-between;
      gap: 16px; padding: 12px 0; border-bottom: 1px solid var(--gray-100);
    }
    .cfg-row:last-child { border-bottom: none; }
    .cfg-row-info { flex: 1; min-width: 0; }
    .cfg-row-label { font-weight: 500; font-size: 14px; color: var(--gray-800); }
    .cfg-row-desc { font-size: 12px; color: var(--gray-500); margin-top: 2px; }
    .cfg-row-control { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
    /* ── Toggle ───────────────────────────────────────── */
    .cfg-toggle { position: relative; display: inline-block; width: 44px; height: 24px; }
    .cfg-toggle input { opacity: 0; width: 0; height: 0; position: absolute; }
    .cfg-toggle-slider {
      position: absolute; cursor: pointer; inset: 0;
      background: var(--gray-300); border-radius: 24px; transition: background .2s;
    }
    .cfg-toggle-slider::before {
      content: ''; position: absolute;
      width: 18px; height: 18px; left: 3px; bottom: 3px;
      background: #fff; border-radius: 50%; transition: transform .2s;
    }
    .cfg-toggle input:checked + .cfg-toggle-slider { background: var(--primary, #1a56db); }
    .cfg-toggle input:checked + .cfg-toggle-slider::before { transform: translateX(20px); }
    /* ── Botón ayuda ──────────────────────────────────── */
    .btn-cfg-help {
      width: 22px; height: 22px; border-radius: 50%; flex-shrink: 0;
      background: var(--gray-100); border: 1px solid var(--gray-200);
      color: var(--gray-500); cursor: pointer; font-size: 11px; font-weight: 700;
      display: inline-flex; align-items: center; justify-content: center;
      line-height: 1; padding: 0;
    }
    .btn-cfg-help:hover { background: var(--gray-200); color: var(--gray-700); }
    .cfg-card-header-left { display: flex; align-items: center; gap: 8px; }
    .cfg-help-pre { white-space: pre-wrap; line-height: 1.7; color: var(--gray-700); font-size: 14px; }
    .cfg-row-heading {
      border-bottom: none; padding-bottom: 0; padding-top: 20px; margin-top: 4px;
    }
    .cfg-row-heading:first-child { padding-top: 8px; }
    .cfg-heading-label {
      font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px;
      color: var(--gray-400); padding-bottom: 4px; border-bottom: 1px solid var(--gray-100); width: 100%;
    }
  `;
  document.head.appendChild(s);
})();

function renderConfiguracion() {
  const g = _CFG_GRUPOS[_cfgTabActual] || _CFG_GRUPOS[0];

  document.getElementById('header-actions').innerHTML = `
    <button class="btn btn-primary" onclick="guardarConfigGrupo('${g.id}')">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
      Guardar
    </button>`;

  // ── Pestañas ──────────────────────────────────────────────
  const tabsHtml = _CFG_GRUPOS.map((grupo, i) =>
    `<button class="cfg-tab ${i === _cfgTabActual ? 'cfg-tab-active' : ''}"
             onclick="_cfgTabActual=${i};renderConfiguracion()">
       ${grupo.icono} <span>${esc(grupo.titulo)}</span>
     </button>`
  ).join('');

  // ── Filas del grupo activo ────────────────────────────────
  const rowsHtml = g.params.map(p => {
    if (p.tipo === 'heading') {
      return `<div class="cfg-row cfg-row-heading"><div class="cfg-heading-label">${esc(p.label)}</div></div>`;
    }
    const def = p.def !== undefined ? p.def : (p.tipo === 'toggle' ? '1' : '');
    const val = _cfgGet(p.v, def);
    let control = '';

    if (p.tipo === 'toggle') {
      control = `<label class="cfg-toggle" title="${val !== '0' ? 'Activo' : 'Inactivo'}">
        <input type="checkbox" data-var="${esc(p.v)}" ${val !== '0' ? 'checked' : ''}>
        <span class="cfg-toggle-slider"></span>
      </label>`;
    } else if (p.tipo === 'select') {
      const opts = (p.opciones || []).map(o =>
        `<option value="${esc(o)}" ${val === o ? 'selected' : ''}>${esc(o)}</option>`
      ).join('');
      control = `<select data-var="${esc(p.v)}" style="width:160px">${opts}</select>`;
    } else if (p.tipo === 'number') {
      control = `<input type="number" data-var="${esc(p.v)}" value="${esc(val)}"
        min="${p.min || 1}" max="${p.max || 9999}" style="width:90px;text-align:right">`;
    } else if (p.tipo === 'password') {
      const _pid = `cfg-pass-${p.v.replace(/[^a-z0-9]/gi, '_')}`;
      control = `<div style="display:flex;gap:6px;align-items:center">
        <input type="password" id="${_pid}" data-var="${esc(p.v)}" value="${esc(val)}"
          style="width:200px" autocomplete="new-password">
        <button type="button" onclick="_togglePass('${_pid}',this)" title="Mostrar/ocultar"
          style="padding:5px 9px;border:1px solid var(--gray-300);border-radius:6px;background:var(--gray-50);cursor:pointer;flex-shrink:0">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>`;
    } else {
      control = `<input type="text" data-var="${esc(p.v)}" value="${esc(val)}" style="width:220px">`;
    }

    return `<div class="cfg-row">
      <div class="cfg-row-info">
        <div class="cfg-row-label">${esc(p.label)}</div>
        <div class="cfg-row-desc">${esc(p.desc)}</div>
      </div>
      <div class="cfg-row-control">
        ${control}
        <button class="btn-cfg-help" title="Ayuda sobre este parámetro"
          onclick='cfgMostrarAyuda(${JSON.stringify(p.label)},${JSON.stringify(p.ayuda)})'>?</button>
      </div>
    </div>`;
  }).join('');

  document.getElementById('content').innerHTML = `
    <div style="max-width:860px">
      <div class="cfg-tabs">${tabsHtml}</div>
      <div class="card cfg-card" id="cfg-grupo-${g.id}">
        <div class="card-header">
          <div class="cfg-card-header-left">
            ${g.icono}
            <div>
              <div class="card-title">${esc(g.titulo)}</div>
              <div style="font-size:12px;color:var(--gray-500);margin-top:2px;font-weight:400">${esc(g.desc)}</div>
            </div>
          </div>
        </div>
        <div class="cfg-rows">${rowsHtml}</div>
      </div>
    </div>
  `;
}

async function guardarConfigGrupo(grupoId) {
  const grupo = _CFG_GRUPOS.find(g => g.id === grupoId);
  if (!grupo) return;
  const card = document.getElementById('cfg-grupo-' + grupoId);
  if (!card) return;

  let guardados = 0;
  for (const p of grupo.params) {
    if (p.tipo === 'heading') continue;
    const el = card.querySelector('[data-var="' + p.v + '"]');
    if (!el) continue;
    const valor = p.tipo === 'toggle' ? (el.checked ? '1' : '0') : el.value.trim();
    const existing = DB.get('configuracion').find(c => c.variable === p.v);
    const obj = existing
      ? { id: existing.id, variable: p.v, valor, descripcion: existing.descripcion || p.desc }
      : { variable: p.v, valor, descripcion: p.desc };
    const result = await DB.save('configuracion', obj);
    if (result) guardados++;
  }

  if (guardados > 0) {
    toast(`${esc(grupo.titulo)}: ${guardados} parámetro${guardados !== 1 ? 's' : ''} guardado${guardados !== 1 ? 's' : ''}`, 'success');
    // Si se guardó la pestaña de menú, aplicar cambios visualmente de inmediato
    if (grupoId === 'menu') _aplicarVisibilidadMenu();
  } else {
    toast('Error al guardar', 'error');
  }
}

function cfgMostrarAyuda(titulo, texto) {
  openModal(
    titulo,
    `<div class="cfg-help-pre">${esc(texto)}</div>`,
    `<button class="btn btn-secondary" onclick="closeModal()">Cerrar</button>`
  );
}
