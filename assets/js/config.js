// ===========================
// CONSTANTES DE ESTADO
// Valores posibles de los campos 'estado' de cada entidad.
// Usar siempre estas constantes en lugar de cadenas literales
// para evitar errores tipográficos y facilitar refactorizaciones.
// ===========================
const ESTADO = Object.freeze({
  // Estados de recibo
  PENDIENTE:   'pendiente',
  COBRADO:     'cobrado',
  PARCIAL:     'parcial',
  DEVUELTO:    'devuelto',
  ANULADO:     'anulado',
  // Estados de contrato
  ACTIVO:      'activo',
  FINALIZADO:  'finalizado',
  RESCINDIDO:  'rescindido',
  // Estados de factura
  EMITIDA:     'emitida',
  RECTIFICADA: 'rectificada',
});

const VF_ESTADO = Object.freeze({
  NO_ENVIADO:      'no_enviado',
  PENDIENTE_ENVIO: 'pendiente_envio',
  ENVIADO:         'enviado',
  ERROR:           'error',
});

// ===========================
// BASE DE DATOS (MySQL via api.php) — M-T07: fetch + async/await
// Todos los accesos a datos pasan por este objeto.
// · init()    → carga toda la BD en memoria (async, una sola vez al arrancar)
// · get()     → lee de la caché en memoria (síncrono, muy rápido)
// · save()    → escribe en la BD y actualiza la caché (async)
// · delete()  → borra en la BD y actualiza la caché (async)
// ===========================
const DB = {
  _cache: null,

  // Carga toda la BD en memoria mediante fetch (async).
  // Se llama UNA SOLA VEZ desde init.js antes de navegar.
  async init() {
    let data = null;
    try {
      const resp = await fetch('assets/php/api.php?action=getAll');
      if (!resp.ok) throw new Error('HTTP ' + resp.status);
      data = await resp.json();
    } catch(e) {
      data = null;
    }
    if (!data || data.error) {
      const msg = (data && data.error)
        ? data.error
        : 'No se puede conectar con api.php. Abre la app desde Apache (localhost), no como fichero local.';
      const el = document.getElementById('content');
      if (el) {
        el.innerHTML = `<div style="max-width:520px;margin:60px auto;text-align:center;padding:40px;background:white;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.1)">
          <div style="font-size:48px">⚠️</div>
          <h2 style="color:#991b1b;margin:16px 0 8px">Error de conexión MySQL</h2>
          <p style="color:#4b5563;margin-bottom:12px">${msg}</p>
          <p style="font-size:13px;color:#6b7280">Comprueba que MAMP está en marcha y que has ejecutado <strong>install.php</strong>.</p>
          <a href="assets/php/install.php" style="display:inline-block;margin-top:20px;background:#1e40af;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none">Abrir install.php →</a>
        </div>`;
      }
      throw new Error(msg);
    }
    this._cache = data;
  },

  // Devuelve todos los registros de una tabla (lee de caché, síncrono).
  get(col) {
    return (this._cache && this._cache[col]) ? this._cache[col] : [];
  },

  // Devuelve un registro por id (lee de caché, síncrono).
  getItem(col, id) {
    return this.get(col).find(i => i.id === id) || null;
  },

  // Guarda un registro en la BD y actualiza la caché (async).
  // Devuelve el objeto guardado (con id real) o null si hay error.
  async save(col, item) {
    let result = null;
    try {
      const resp = await fetch('assets/php/api.php?action=save&table=' + encodeURIComponent(col), {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(item),
      });
      if (!resp.ok) throw new Error('HTTP ' + resp.status);
      result = await resp.json();
    } catch(e) {
      console.error('DB.save error [' + col + ']', e);
      if (typeof toast === 'function') toast('Error al guardar: ' + e.message, 'error');
      return null;
    }
    if (!result || result.error) {
      const msg = result?.error || 'Error de comunicación con el servidor';
      console.error('DB.save error [' + col + ']', result);
      if (typeof toast === 'function') toast('Error al guardar: ' + msg, 'error');
      return null;
    }
    // Actualizar caché local con el resultado devuelto por la BD
    if (!this._cache[col]) this._cache[col] = [];
    const idx = this._cache[col].findIndex(i => i.id === result.id);
    if (idx >= 0) this._cache[col][idx] = result;
    else          this._cache[col].push(result);
    // Propagar el id real al objeto original (útil para INSERT nuevo)
    item.id = result.id;
    return result;
  },

  // Elimina un registro de la BD y lo quita de la caché (async).
  // Devuelve { ok:true } si se ha borrado, o { ok:false, error, code, details }
  // si el backend lo ha rechazado (p.ej. por tener dependencias asociadas).
  // El backend es quien decide si el borrado físico está permitido: la caché
  // local solo se actualiza cuando la respuesta confirma que se ha borrado.
  async delete(col, id) {
    let result = null;
    try {
      const resp = await fetch('assets/php/api.php?action=delete&table=' + encodeURIComponent(col) + '&id=' + id, {
        method: 'POST',
      });
      result = await resp.json().catch(() => null);
    } catch(e) {
      console.error('DB.delete error [' + col + ']', e);
      return { ok: false, error: 'Error de comunicación con el servidor' };
    }
    if (!result || result.ok === false || result.error) {
      const msg = (result && result.error) || 'No se ha podido eliminar el registro';
      console.error('DB.delete error [' + col + ']', result);
      return { ok: false, error: msg, code: result?.code, details: result?.details };
    }
    if (this._cache?.[col]) {
      this._cache[col] = this._cache[col].filter(i => i.id !== id);
    }
    return { ok: true };
  },

  // Devuelve el registro de la tabla empresa (siempre es el primero).
  getEmpresa() {
    return this.get('empresa')[0] || null;
  },

  // Guarda o actualiza los datos de empresa (async).
  async setEmpresa(val) {
    const existing = this.getEmpresa();
    if (existing) val.id = existing.id;
    return await this.save('empresa', val);
  },
};
