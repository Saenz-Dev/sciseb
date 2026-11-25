const TITULOS = { areas: 'Áreas', municipios: 'Municipios', instituciones: 'Instituciones educativas', sedes: 'Sedes' };
let tab = 'areas';
let catalogosBase = null;
let municipios = [], instituciones = [], sedes = [];

if (exigirSesion({ soloAdmin: true })) {
  pintarMenu('catalogos.html');
  $('.tabs [role=tab]').on('click', function () { cambiarTab($(this).data('tab')); });
  $('#sel-provincia').on('change', alCambiarProvincia);
  $('#sel-municipio').on('change', alCambiarMunicipio);
  $('#sel-institucion').on('change', recargarNiveles);
  $('#nombre-nuevo').on('input', function () { $('#btn-agregar').prop('disabled', !$(this).val().trim()); });
  $('#form-crear').on('submit', (ev) => { ev.preventDefault(); crear(); });
  iniciar();
}

async function iniciar() {
  catalogosBase = await cargarCatalogos(true);
  llenarSelect($('#sel-provincia'), catalogosBase.provincias);
  cambiarTab('areas');
}

function cambiarTab(nombre) {
  tab = nombre;
  $('.tabs [role=tab]').attr('aria-selected', 'false');
  $(`.tabs [data-tab="${nombre}"]`).attr('aria-selected', 'true');
  $('#titulo-nivel').text(TITULOS[nombre]);
  $('#ayuda-areas').prop('hidden', nombre !== 'areas');
  $('#selectores').prop('hidden', nombre === 'areas');
  $('#sel-municipio-wrap').prop('hidden', nombre !== 'instituciones' && nombre !== 'sedes');
  $('#sel-institucion-wrap').prop('hidden', nombre !== 'sedes');
  $('#nombre-nuevo').val('');
  if (nombre === 'areas') {
    $('#sel-provincia').val(''); municipios = []; instituciones = []; sedes = [];
    $('#sel-municipio').val('').prop('disabled', true);
    $('#sel-institucion').val('').prop('disabled', true);
  }
  pintarNivel();
}

async function alCambiarProvincia() {
  instituciones = []; sedes = [];
  $('#sel-municipio').prop('disabled', !$('#sel-provincia').val()).val('');
  $('#sel-institucion').prop('disabled', true).val('');
  await recargarNiveles();
}
async function alCambiarMunicipio() {
  sedes = [];
  $('#sel-institucion').prop('disabled', !$('#sel-municipio').val()).val('');
  await recargarNiveles();
}

async function recargarNiveles() {
  const provinciaId = $('#sel-provincia').val();
  const municipioId = $('#sel-municipio').val();
  const institucionId = $('#sel-institucion').val();
  municipios = provinciaId ? await Api.get('/catalogos/municipios', { provinciaId }) : [];
  instituciones = municipioId ? await Api.get('/catalogos/instituciones', { municipioId }) : [];
  sedes = institucionId ? await Api.get('/catalogos/sedes', { institucionId }) : [];
  llenarSelect($('#sel-municipio'), municipios);
  if (municipioId) $('#sel-municipio').val(municipioId);
  llenarSelect($('#sel-institucion'), instituciones);
  if (institucionId) $('#sel-institucion').val(institucionId);
  pintarNivel();
}

function nivelActual() {
  return {
    areas: { lista: catalogosBase.areas, puedeCrear: false },
    municipios: { lista: municipios, puedeCrear: !!$('#sel-provincia').val() },
    instituciones: { lista: instituciones, puedeCrear: !!$('#sel-municipio').val() },
    sedes: { lista: sedes, puedeCrear: !!$('#sel-institucion').val() },
  }[tab];
}

function padreActual() {
  return { municipios: $('#sel-provincia').val(), instituciones: $('#sel-municipio').val(), sedes: $('#sel-institucion').val() }[tab];
}

function pintarNivel() {
  const n = nivelActual();
  $('#form-crear').prop('hidden', !n.puedeCrear);
  $('#btn-agregar').prop('disabled', true);
  const $lista = $('#lista-nivel').empty();
  if (!n.lista.length) {
    $('#sin-registros').text(tab === 'areas' || n.puedeCrear ? 'No hay registros.' : 'Seleccione primero el nivel anterior.').prop('hidden', false);
    return;
  }
  $('#sin-registros').prop('hidden', true);
  n.lista.forEach((x) => $lista.append(filaItem(x)));
}

function filaItem(x) {
  const $fila = $('<div class="item-lista" style="display:flex; gap:1rem; justify-content:space-between; align-items:center; flex-wrap:wrap"></div>');
  function vista() {
    $fila.empty().append($('<span></span>').text(x.nombre))
      .append($('<button class="btn btn-chico">Renombrar</button>').on('click', edicion));
  }
  function edicion() {
    const $input = $('<input maxlength="150" style="max-width:360px">').val(x.nombre);
    $fila.empty().append($input)
      .append($('<span class="acciones"></span>')
        .append($('<button class="btn btn-pri btn-chico">Guardar</button>').on('click', () => renombrar(x, $input.val(), vista)))
        .append($('<button class="btn btn-chico">Cancelar</button>').on('click', vista)));
  }
  vista();
  return $fila;
}

async function crear() {
  try {
    await Api.post('/catalogos/' + tab, { nombre: $('#nombre-nuevo').val(), padreId: padreActual() });
    $('#nombre-nuevo').val('');
    await recargarNiveles();
    avisar('Agregado.');
  } catch (e) { avisar((e.errores && e.errores.nombre) || e.message, 'error'); }
}

async function renombrar(x, nombreNuevo, alTerminar) {
  try {
    if (tab === 'areas') { await Api.put('/catalogos/areas/' + x.id, { nombre: nombreNuevo }); catalogosBase = await cargarCatalogos(true); }
    else { await Api.put(`/catalogos/${tab}/${x.id}`, { nombre: nombreNuevo }); await recargarNiveles(); return; }
    avisar('Nombre actualizado.');
    pintarNivel();
  } catch (e) { avisar(e.message, 'error'); alTerminar(); }
}
