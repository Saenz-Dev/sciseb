// Catálogos fijos (provincias, clasificaciones, áreas, pasos): se piden una sola vez y se guardan
// en memoria mientras dure la página.

let _catalogosCache = null;

async function cargarCatalogos(forzar = false) {
  if (!_catalogosCache || forzar) _catalogosCache = await Api.get('/catalogos');
  return _catalogosCache;
}

/** Llena un <select> con { id, nombre }, conservando una opción inicial si se indica. */
function llenarSelect($select, lista, { placeholder = 'Seleccione…' } = {}) {
  $select.empty();
  if (placeholder !== null) $select.append(`<option value="">${placeholder}</option>`);
  lista.forEach((x) => $select.append(`<option value="${x.id}">${escaparHtml(x.nombre)}</option>`));
}
