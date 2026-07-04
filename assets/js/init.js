// ===========================
// INIT — ARRANQUE DE LA APLICACIÓN
// Carga la caché de datos de forma asíncrona (M-T07) y navega al Dashboard.
// DB.init() hace una sola petición GET a api.php?action=getAll y rellena
// DB._cache. A partir de ese momento DB.get() es síncrono (lectura de memoria).
// ===========================
DB.init()
  .then(() => {
    _aplicarVisibilidadMenu(); // aplica config menu_* antes de mostrar la UI
    navigate('dashboard');
  })
  .catch(err => console.error('Error al inicializar AlquiGest:', err));
