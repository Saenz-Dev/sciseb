const idCasoEditar = new URLSearchParams(location.search).get('id'); // null = registrar, si no = editar
let versionActual = null;

if (exigirSesion()) {
  pintarMenu('casos.html');
  $('#btn-cancelar').on('click', () => history.back());
  $('#btn-agregar-victima').on('click', () => agregarFilaVictima(''));
  $('#cedula').on('blur', () => autocompletarAgresor('cedula', $('#cedula').val()));
  $('#presuntoAgresor').on('blur', () => autocompletarAgresor('nombre', $('#presuntoAgresor').val()));
  $('#provinciaId').on('change', alCambiarProvincia);
  $('#municipioId').on('change', alCambiarMunicipio);
  $('#institucionId').on('change', alCambiarInstitucion);
  $('#form-caso').on('submit', (ev) => { ev.preventDefault(); guardar(false); });
  iniciar();
}

function agregarFilaVictima(valor) {
  const $fila = $(`
    <div style="display:flex; gap:.5rem; align-items:center; margin-bottom:.4rem">
      <input class="victima" maxlength="10" autocomplete="off" style="text-transform:uppercase" placeholder="Ej. J.P.">
      <button type="button" class="btn btn-chico btn-quitar">Quitar</button>
    </div>`);
  $fila.find('.victima').val(valor);
  $fila.find('.btn-quitar').on('click', () => { if ($('#lista-victimas .victima').length > 1) $fila.remove(); });
  $('#lista-victimas').append($fila);
}

async function iniciar() {
  try {
    const cat = await cargarCatalogos();
    llenarSelect($('#clasificacionId'), cat.clasificaciones);
    llenarSelect($('#provinciaId'), cat.provincias);

    if (idCasoEditar) {
      $('#titulo-pagina').text('Editar caso');
      const c = await Api.get('/casos/' + idCasoEditar);
      if (c.estado === 'ARCHIVADO') {
        $('#alerta-general').text('Este caso está archivado y no se puede editar. Pida al Administrador que lo reactive.').prop('hidden', false);
        $('#cargando').prop('hidden', true);
        return;
      }
      $('#titulo-pagina').text('Editar caso ' + (c.idCaso || ('#' + idCasoEditar)));
      versionActual = c.version;
      $('#idCaso').val(c.idCaso || '');
      $('#presuntoAgresor').val(c.presuntoAgresor);
      $('#cedula').val(c.cedula);
      $('#areaAsignatura').val(c.areaAsignatura || '');
      $('#lista-victimas').empty();
      (c.victimas && c.victimas.length ? c.victimas : ['']).forEach(agregarFilaVictima);
      $('#clasificacionId').val(c.clasificacionId);
      $('#rutaActiva').val(String(c.rutaActiva));
      $('#nRadicado').val(c.nRadicado || '');
      $('#resolucionSeb').val(c.resolucionSeb || '');
      $('#resolucionRectoral').val(c.resolucionRectoral || '');
      $('#ubicacionAgresor').val(c.ubicacionAgresor || '');
      $('#fechaOcurrencia').val(c.fechaOcurrencia || '');
      $('#lugarOcurrencia').val(c.lugarOcurrencia || '');
      $('#disciplinario').val(c.disciplinario || '');
      $('#decisionComite').val(c.decisionComite || '');
      $('#penal').val(c.penal || '');

      $('#provinciaId').val(c.provinciaId);
      await cargarMunicipios();
      $('#municipioId').prop('disabled', false).val(c.municipioId);
      await cargarInstituciones();
      $('#institucionId').prop('disabled', false).val(c.institucionId);
      await cargarSedes();
      $('#sedeId').prop('disabled', false).val(c.sedeId);
    } else {
      agregarFilaVictima('');
      $('#fechaOcurrencia').attr('max', new Date().toISOString().slice(0, 10));
    }
    $('#cargando').prop('hidden', true);
    $('#form-caso').prop('hidden', false);
  } catch (e) {
    $('#alerta-general').text(e.message).prop('hidden', false);
    $('#cargando').prop('hidden', true);
  }
}

/** Si la cédula o el nombre ya están registrados, completa el otro campo automáticamente. */
async function autocompletarAgresor(campo, valor) {
  const v = limpiar(valor);
  if (!v) return;
  try {
    const ag = await Api.get('/agresores/buscar', campo === 'cedula' ? { cedula: v } : { nombre: v });
    if (!ag) return;
    if (campo === 'cedula' && !limpiar($('#presuntoAgresor').val())) $('#presuntoAgresor').val(ag.nombre);
    if (campo === 'nombre' && !limpiar($('#cedula').val())) $('#cedula').val(ag.cedula);
    if (!limpiar($('#areaAsignatura').val()) && ag.areaAsignatura) $('#areaAsignatura').val(ag.areaAsignatura);
  } catch {
    // autocompletado silencioso: un fallo aquí no debe interrumpir el registro del caso
  }
}

async function cargarMunicipios() {
  const provinciaId = $('#provinciaId').val();
  llenarSelect($('#municipioId'), provinciaId ? await Api.get('/catalogos/municipios', { provinciaId }) : []);
}
async function cargarInstituciones() {
  const municipioId = $('#municipioId').val();
  const instituciones = municipioId ? await Api.get('/catalogos/instituciones', { municipioId }) : [];
  llenarSelect($('#institucionId'), instituciones);
  $('#sin-instituciones').prop('hidden', !(municipioId && !instituciones.length));
}
async function cargarSedes() {
  const institucionId = $('#institucionId').val();
  llenarSelect($('#sedeId'), institucionId ? await Api.get('/catalogos/sedes', { institucionId }) : []);
}

async function alCambiarProvincia() {
  llenarSelect($('#municipioId'), []); llenarSelect($('#institucionId'), []); llenarSelect($('#sedeId'), []);
  $('#municipioId').prop('disabled', !$('#provinciaId').val());
  $('#institucionId, #sedeId').prop('disabled', true);
  await cargarMunicipios();
}
async function alCambiarMunicipio() {
  llenarSelect($('#institucionId'), []); llenarSelect($('#sedeId'), []);
  $('#institucionId').prop('disabled', !$('#municipioId').val());
  $('#sedeId').prop('disabled', true);
  await cargarInstituciones();
}
async function alCambiarInstitucion() {
  $('#sedeId').prop('disabled', !$('#institucionId').val());
  await cargarSedes();
}

function leerFormulario() {
  return {
    idCaso: $('#idCaso').val(), presuntoAgresor: $('#presuntoAgresor').val(), cedula: $('#cedula').val(),
    areaAsignatura: $('#areaAsignatura').val(),
    victimas: $('#lista-victimas .victima').map((_, el) => $(el).val()).get(),
    provinciaId: $('#provinciaId').val() || null, municipioId: $('#municipioId').val() || null,
    institucionId: $('#institucionId').val() || null, sedeId: $('#sedeId').val() || null,
    clasificacionId: $('#clasificacionId').val() || null, rutaActiva: $('#rutaActiva').val() === 'true',
    nRadicado: $('#nRadicado').val(), resolucionSeb: $('#resolucionSeb').val(), resolucionRectoral: $('#resolucionRectoral').val(),
    ubicacionAgresor: $('#ubicacionAgresor').val(), fechaOcurrencia: $('#fechaOcurrencia').val(), lugarOcurrencia: $('#lugarOcurrencia').val(),
    disciplinario: $('#disciplinario').val(), decisionComite: $('#decisionComite').val(), penal: $('#penal').val(),
  };
}

function cuerpoApi(f, confirmarNombreDistinto) {
  return {
    idCaso: limpiar(f.idCaso), presuntoAgresor: limpiar(f.presuntoAgresor), cedula: limpiar(f.cedula),
    areaAsignatura: limpiar(f.areaAsignatura),
    victimas: f.victimas.map(normalizarIniciales).filter(Boolean),
    sedeId: f.sedeId, clasificacionId: f.clasificacionId, rutaActiva: f.rutaActiva,
    nRadicado: limpiar(f.nRadicado), resolucionSeb: limpiar(f.resolucionSeb), resolucionRectoral: limpiar(f.resolucionRectoral),
    ubicacionAgresor: limpiar(f.ubicacionAgresor), fechaOcurrencia: f.fechaOcurrencia || null, lugarOcurrencia: limpiar(f.lugarOcurrencia),
    disciplinario: limpiar(f.disciplinario), decisionComite: limpiar(f.decisionComite), penal: limpiar(f.penal),
    version: versionActual, confirmarNombreDistinto: confirmarNombreDistinto,
  };
}

function limpiarErroresForm() {
  $('#form-caso .campo').removeClass('error').each((_, el) => $(el).find('.msg-error').prop('hidden', true));
  $('#alerta-general').prop('hidden', true);
}
function mostrarErrores(errores) {
  Object.entries(errores).forEach(([campo, mensaje]) => {
    const $campo = $('#campo-' + campo);
    $campo.addClass('error');
    $campo.find('.msg-error').text(mensaje).prop('hidden', false);
  });
}

async function guardar(confirmarNombreDistinto) {
  limpiarErroresForm();
  const f = leerFormulario();
  const errores = validarCaso(f);
  if (Object.keys(errores).length) {
    mostrarErrores(errores);
    $('#alerta-general').text('Revise los campos marcados en rojo.').prop('hidden', false);
    $('.campo.error input, .campo.error select, .campo.error textarea').first().trigger('focus');
    return;
  }
  const $btn = $('#btn-guardar').prop('disabled', true).text('Guardando…');
  try {
    const cuerpo = cuerpoApi(f, confirmarNombreDistinto);
    const r = idCasoEditar ? await Api.put('/casos/' + idCasoEditar, cuerpo) : await Api.post('/casos', cuerpo);
    const notaBorrador = r.agresorBorrador ? ' El presunto agresor quedó como borrador: falta la cédula para completarlo.' : '';
    avisar((idCasoEditar ? 'Caso actualizado.' : 'Caso registrado. Se crearon los 12 pasos de la ruta de atención.') + notaBorrador);
    location.href = 'caso-detalle.html?id=' + r.id;
  } catch (e) {
    if (e.codigo === 'NOMBRE_DISTINTO') {
      $btn.prop('disabled', false).text(idCasoEditar ? 'Guardar cambios' : 'Registrar caso');
      if (await confirmar({ titulo: 'La cédula ya existe', mensaje: e.message, textoOk: 'Es la misma persona, continuar' })) return guardar(true);
      return;
    }
    if (e.codigo === 'VALIDACION') mostrarErrores(e.errores);
    $('#alerta-general').text(e.message).prop('hidden', false);
  } finally {
    $btn.prop('disabled', false).text(idCasoEditar ? 'Guardar cambios' : 'Registrar caso');
  }
}
