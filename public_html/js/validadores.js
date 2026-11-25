// Mismas reglas que el servidor (com.sciseb.util.CasoValidador). El servidor siempre vuelve a
// validar: esto solo evita viajes de ida y vuelta innecesarios.

const REGLAS = {
  idCaso: /^[0-9]{5}$/,
  cedula: /^[0-9]{5,12}$/,
  nombre: /^\p{L}[\p{L} .'-]{2,119}$/u,
  iniciales: /^([A-ZÁÉÍÓÚÑ]\.?){1,5}$/,
  alfanum: /^[A-Za-z0-9/_. -]{1,30}$/,
  resolucion: /^[0-9]{1,20}$/,
  penal: /^[0-9]{1,30}$/,
};

const POLITICA_CLAVE = /^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).{10,72}$/;

function normalizarIniciales(s) {
  return (s || '').replace(/\s+/g, '').toUpperCase();
}

function limpiar(s) {
  const t = (s ?? '').trim().replace(/\s+/g, ' ');
  return t === '' ? null : t;
}

/** Evita que una celda que empieza por = + - @ se interprete como fórmula al abrir el CSV en Excel. */
function neutralizarFormula(s) {
  return /^[=+\-@\t\r]/.test(s || '') ? "'" + s : s;
}

/** f: objeto con los mismos campos que el formulario de caso. Devuelve { campo: mensaje }. */
function validarCaso(f) {
  const e = {};
  const v = (k) => limpiar(f[k]);

  if (v('idCaso') && !REGLAS.idCaso.test(v('idCaso'))) e.idCaso = 'El ID del caso debe tener exactamente 5 números (por ejemplo 00123).';
  if (v('presuntoAgresor') && !REGLAS.nombre.test(v('presuntoAgresor'))) e.presuntoAgresor = 'Escriba el nombre del presunto agresor (solo letras, de 3 a 120 caracteres).';

  const victimas = (f.victimas || []).map(normalizarIniciales).filter(Boolean);
  if (victimas.some((ini) => !REGLAS.iniciales.test(ini)))
    e.victimas = 'Escriba solo las iniciales de cada presunta víctima (de 1 a 5 letras, por ejemplo J.P.). Nunca el nombre.';

  // La cédula es opcional: si no se conoce todavía, el nombre (si se dio) queda como borrador hasta
  // que se complete la cédula. Si ya está registrada, el nombre se completa solo al guardar.
  if (v('cedula') && !REGLAS.cedula.test(v('cedula'))) e.cedula = 'La cédula debe tener entre 5 y 12 números, sin puntos ni espacios.';

  if (v('areaAsignatura') && v('areaAsignatura').length > 80) e.areaAsignatura = 'No puede superar 80 caracteres.';
  if (v('nRadicado') && !REGLAS.alfanum.test(v('nRadicado'))) e.nRadicado = 'Solo letras, números y los signos / _ . - (máximo 30).';
  if (v('resolucionSeb') && !REGLAS.resolucion.test(v('resolucionSeb'))) e.resolucionSeb = 'Debe contener solo números.';
  if (v('resolucionRectoral') && !REGLAS.resolucion.test(v('resolucionRectoral'))) e.resolucionRectoral = 'Debe contener solo números.';
  if (v('disciplinario') && !REGLAS.alfanum.test(v('disciplinario'))) e.disciplinario = 'Solo letras, números y los signos / _ . - (máximo 30).';
  if (v('penal') && !REGLAS.penal.test(v('penal'))) e.penal = 'Debe contener solo números (máximo 30).';
  if (v('ubicacionAgresor') && v('ubicacionAgresor').length > 500) e.ubicacionAgresor = 'No puede superar 500 caracteres.';
  if (v('lugarOcurrencia') && v('lugarOcurrencia').length > 120) e.lugarOcurrencia = 'No puede superar 120 caracteres.';
  if (v('decisionComite') && v('decisionComite').length > 2000) e.decisionComite = 'No puede superar 2000 caracteres.';

  if (f.fechaOcurrencia) {
    const d = new Date(f.fechaOcurrencia + 'T00:00:00');
    const hoy = new Date();
    const fin = new Date(hoy.getFullYear(), hoy.getMonth(), hoy.getDate());
    if (Number.isNaN(d.getTime())) e.fechaOcurrencia = 'La fecha de ocurrencia no es válida.';
    else if (d > fin) e.fechaOcurrencia = 'La fecha de ocurrencia no puede ser futura.';
    else if (d < new Date(2000, 0, 1)) e.fechaOcurrencia = 'La fecha de ocurrencia no es válida.';
  }
  return e;
}
