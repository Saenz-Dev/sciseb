const idCaso = new URLSearchParams(location.search).get('id');
let caso = null, acciones = [], enlaces = [], abierto = false;

if (exigirSesion()) {
  pintarMenu('casos.html');
  if (!idCaso) { location.href = 'casos.html'; }
  else {
    $('.tabs [role=tab]').on('click', function () { cambiarPestana($(this).data('tab')); });
    $('#btn-editar').attr('href', 'caso-form.html?id=' + idCaso);
    $('#btn-archivar').on('click', () => archivarOReactivar(false));
    $('#btn-reactivar').on('click', () => archivarOReactivar(true));
    $('#form-accion').on('submit', (ev) => { ev.preventDefault(); agregarAccion(); });
    $('#form-enlace').on('submit', (ev) => { ev.preventDefault(); agregarEnlace(false); });
    cargar();
  }
}

function cambiarPestana(nombre) {
  $('.tabs [role=tab]').attr('aria-selected', 'false');
  $(`.tabs [data-tab="${nombre}"]`).attr('aria-selected', 'true');
  $('[data-panel]').prop('hidden', true);
  $(`[data-panel="${nombre}"]`).prop('hidden', false);
}

async function cargar() {
  try {
    const [c, a, e] = await Promise.all([
      Api.get('/casos/' + idCaso), Api.get(`/casos/${idCaso}/acciones`), Api.get(`/casos/${idCaso}/enlaces`),
    ]);
    aplicarCaso(c);
    acciones = a;
    enlaces = e;
    pintarAcciones();
    pintarEnlaces();
    $('#cargando').prop('hidden', true);
    $('#contenido').prop('hidden', false);
  } catch (e) {
    $('#cargando').prop('hidden', true);
    $('#alerta-error').html(escaparHtml(e.message) + ' <a href="casos.html">Volver a casos</a>').prop('hidden', false);
  }
}

function aplicarCaso(c) {
  caso = c;
  abierto = c.estado === 'ACTIVO';
  $('#titulo-caso').contents().filter((_, n) => n.nodeType === 3).remove();
  $('#titulo-caso').prepend('Caso ' + (c.idCaso || '#' + c.id) + ' ');
  $('#badge-clasificacion').attr('class', 'badge ' + (c.clasificacion ? (c.clasificacion === 'Tipo III' ? 'b-bad' : 'b-warn') : 'b-gris')).text(c.clasificacion || '—');
  $('#badge-ruta').attr('class', 'badge ' + (c.rutaActiva ? 'b-ok' : 'b-gris')).text('Ruta ' + (c.rutaActiva ? 'activa' : 'inactiva'));
  $('#badge-archivado').prop('hidden', abierto);
  $('#badge-borrador').prop('hidden', !c.agresorBorrador);
  $('#subtitulo-caso').text(`${c.presuntoAgresor || 'Sin presunto agresor asignado'} · ${c.institucion || 'Sin institución asignada'}${c.municipio ? ', ' + c.municipio : ''}`);
  $('#alerta-archivado').prop('hidden', abierto);
  $('#btn-editar').prop('hidden', !abierto);
  $('#btn-archivar').prop('hidden', !(Sesion.esAdmin && abierto));
  $('#btn-reactivar').prop('hidden', !(Sesion.esAdmin && !abierto));

  $('#d-agresor').text(c.presuntoAgresor || '—');
  $('#d-agresor-borrador').prop('hidden', !c.agresorBorrador);
  $('#d-cedula').text(c.cedula || '—');
  $('#d-area').text(c.areaAsignatura || '—');
  $('#d-victimas').text(c.victimas.join(', ') || '—');
  $('#d-institucion').text(c.institucion || '—');
  $('#d-sede').text(c.sede || '—');
  $('#d-municipio').text(c.municipio || '—');
  $('#d-provincia').text(c.provincia || '—');
  $('#d-fecha-ocurrencia').text(soloFecha(c.fechaOcurrencia));
  $('#d-lugar').text(c.lugarOcurrencia || '—');
  $('#d-radicado').text(c.nRadicado || '—');
  $('#d-resolucion-seb').text(c.resolucionSeb || '—');
  $('#d-resolucion-rectoral').text(c.resolucionRectoral || '—');
  $('#d-disciplinario').text(c.disciplinario || '—');
  $('#d-penal').text(c.penal || '—');
  $('#d-ubicacion').text(c.ubicacionAgresor || '—');
  $('#d-decision').text(c.decisionComite || '—');
  $('#d-creado-por').html(`${escaparHtml(c.creadoPor)} <span class="mut">(${escaparHtml(c.creadoPorArea)})</span>`);
  $('#d-fecha-creacion').text(fechaHora(c.fechaCreacion));
  $('#d-editado-por').html(`${escaparHtml(c.editadoPor)} <span class="mut">(${escaparHtml(c.editadoPorArea)})</span>`);
  $('#d-fecha-edicion').text(fechaHora(c.fechaUltimaEdicion));

  $('#tab-ruta-contador').text(c.pasosEfectuados);
  $('#tab-acciones-contador').text(acciones.length);
  $('#tab-evidencias-contador').text(enlaces.length);
  pintarRuta();
}

function pintarRuta() {
  const pct = porcentaje(caso.pasosEfectuados);
  $('#ruta-hechos').text(caso.pasosEfectuados);
  $('#ruta-pct').text(pct);
  $('#ruta-barra').attr({ 'aria-valuenow': caso.pasosEfectuados, 'aria-valuemin': 0, 'aria-valuemax': 12 });
  $('#ruta-barra-i').css('width', pct + '%');
  $('#ruta-estado-badge').attr('class', 'badge ' + (caso.rutaActiva ? 'b-ok' : 'b-gris')).text('Ruta ' + (caso.rutaActiva ? 'activa' : 'inactiva'));
  $('#btn-cambiar-ruta').prop('hidden', !abierto).text(caso.rutaActiva ? 'Desactivar ruta' : 'Activar ruta')
    .off('click').on('click', () => cambiarRuta(!caso.rutaActiva));

  const $lista = $('#lista-pasos').empty();
  caso.pasos.forEach((p) => {
    const $paso = $(`
      <div class="paso ${p.efectuado ? 'hecho' : ''}">
        <div class="num" aria-hidden="true"></div>
        <div>
          <div class="nombre"></div>
          <div class="peq estado"></div>
          <div class="edicion-nota" ${abierto ? '' : 'hidden'} style="margin-top:.5rem; display:flex; gap:.4rem; flex-wrap:wrap">
            <input class="nota-paso" aria-label="Nota del paso ${p.id}" placeholder="Nota (opcional)" maxlength="1000" style="flex:1; min-width:180px">
            <button type="button" class="btn btn-chico btn-guardar-nota">Guardar nota</button>
          </div>
          <div class="peq nota-fija" style="margin-top:.4rem" ${!abierto && p.nota ? '' : 'hidden'}></div>
        </div>
        <div class="acc-paso"></div>
      </div>`);
    $paso.find('.num').text(p.efectuado ? '✓' : p.id);
    $paso.find('.nombre').text(p.id + '. ' + p.nombre);
    $paso.find('.estado').addClass(p.efectuado ? '' : 'mut')
      .text(p.efectuado ? `Efectuado por ${p.usuario} (${p.area}) · ${fechaHora(p.fecha)}` : 'Pendiente');
    $paso.find('.nota-paso').val(p.nota || '');
    $paso.find('.nota-fija').text('Nota: ' + (p.nota || ''));
    $paso.find('.btn-guardar-nota').prop('disabled', true).on('click', () => guardarNotaPaso(p));
    $paso.find('.nota-paso').on('input', function () { $paso.find('.btn-guardar-nota').prop('disabled', $(this).val() === (p.nota || '')); });

    const $accPaso = $paso.find('.acc-paso');
    if (abierto) {
      if (!p.efectuado) $accPaso.append($('<button class="btn btn-pri btn-chico">Marcar efectuado</button>').on('click', () => marcarPaso(p)));
      else $accPaso.append($('<button class="btn btn-chico">Devolver a pendiente</button>').on('click', () => desmarcarPaso(p)));
    } else {
      $accPaso.append($(`<span class="badge ${p.efectuado ? 'b-ok' : 'b-gris'}"></span>`).text(p.efectuado ? 'Efectuado' : 'Pendiente'));
    }
    $lista.append($paso);
  });
}

async function ejecutar(fn, mensajeOk) {
  try {
    await fn();
    if (mensajeOk) avisar(mensajeOk);
  } catch (e) {
    avisar(e.message, 'error');
    if (e.codigo === 'CASO_ARCHIVADO') cargar();
  }
}

const cambiarRuta = (activa) => ejecutar(async () => aplicarCaso(await Api.put(`/casos/${idCaso}/ruta`, { activa })), activa ? 'Ruta activada.' : 'Ruta desactivada.');
const marcarPaso = (p) => ejecutar(async () => aplicarCaso(await Api.put(`/casos/${idCaso}/pasos/${p.id}`, { efectuado: true })), `Paso ${p.id} marcado como efectuado.`);

async function desmarcarPaso(p) {
  const si = await confirmar({ titulo: 'Devolver el paso a pendiente', mensaje: `¿Seguro que el paso ${p.id} (${p.nombre}) aún no se ha efectuado? Se borrará quién y cuándo lo marcó.`, textoOk: 'Sí, devolver a pendiente', peligro: true });
  if (si) ejecutar(async () => aplicarCaso(await Api.put(`/casos/${idCaso}/pasos/${p.id}`, { efectuado: false })), `Paso ${p.id} vuelve a pendiente.`);
}
function guardarNotaPaso(p) {
  const nota = $('#lista-pasos .paso').eq(p.id - 1).find('.nota-paso').val();
  ejecutar(async () => aplicarCaso(await Api.put(`/casos/${idCaso}/pasos/${p.id}`, { nota })), 'Nota guardada.');
}

// ---- acciones ----
async function agregarAccion() {
  $('#error-accion').prop('hidden', true);
  const texto = $('#texto-accion').val();
  if (!texto.trim()) { $('#error-accion').text('Escriba la acción realizada.').prop('hidden', false); return; }
  await ejecutar(async () => {
    acciones = await Api.post(`/casos/${idCaso}/acciones`, { texto });
    $('#texto-accion').val('');
    $('#tab-acciones-contador').text(acciones.length);
    pintarAcciones();
  }, 'Acción registrada.');
}
function pintarAcciones() {
  $('#sin-acciones').prop('hidden', !!acciones.length);
  const $lista = $('#lista-acciones').empty();
  acciones.forEach((a) => {
    const $item = $(`
      <div class="item-lista">
        <div class="peq" style="display:flex; gap:.5rem; flex-wrap:wrap; align-items:center">
          <span class="badge b-info"></span><b class="usuario"></b>
          <span class="mut fecha"></span>
          <span class="mut mod" hidden></span>
        </div>
        <div class="vista">
          <p style="margin:.35rem 0 0; white-space:pre-wrap" class="texto"></p>
          <button class="btn-link peq btn-editar-accion" hidden>Editar</button>
        </div>
        <div class="edicion" hidden>
          <textarea maxlength="2000" style="margin-top:.4rem"></textarea>
          <div class="acciones" style="margin-top:.4rem">
            <button class="btn btn-pri btn-chico btn-guardar-accion">Guardar</button>
            <button class="btn btn-chico btn-cancelar-accion">Cancelar</button>
          </div>
        </div>
      </div>`);
    $item.find('.badge').text(a.area);
    $item.find('.usuario').text(a.usuario);
    $item.find('.fecha').text(fechaHora(a.fechaRegistro));
    if (a.fechaModificacion) $item.find('.mod').text('· modificada el ' + fechaHora(a.fechaModificacion)).prop('hidden', false);
    $item.find('.texto').text(a.texto);
    $item.find('textarea').val(a.texto);
    if (a.editable) {
      $item.find('.btn-editar-accion').prop('hidden', false).on('click', () => { $item.find('.vista').prop('hidden', true); $item.find('.edicion').prop('hidden', false); });
      $item.find('.btn-cancelar-accion').on('click', () => { $item.find('.edicion').prop('hidden', true); $item.find('.vista').prop('hidden', false); });
      $item.find('.btn-guardar-accion').on('click', async () => {
        const nuevoTexto = $item.find('textarea').val();
        if (!nuevoTexto.trim()) return;
        await ejecutar(async () => { acciones = await Api.put('/acciones/' + a.id, { texto: nuevoTexto }); pintarAcciones(); }, 'Acción modificada.');
      });
    }
    $lista.append($item);
  });
}

// ---- enlaces ----
async function agregarEnlace(confirmarDuplicado) {
  $('#campo-titulo-enlace, #campo-url-enlace').removeClass('error').find('.msg-error').prop('hidden', true);
  const titulo = $('#titulo-enlace').val();
  const url = $('#url-enlace').val().trim();
  if (!titulo.trim()) { $('#campo-titulo-enlace').addClass('error').find('.msg-error').text('Escriba el nombre del documento.').prop('hidden', false); return; }
  if (!/^https:\/\/\S+$/i.test(url)) { $('#campo-url-enlace').addClass('error').find('.msg-error').text('Escriba un enlace que empiece por https://').prop('hidden', false); return; }
  try {
    enlaces = await Api.post(`/casos/${idCaso}/enlaces`, { titulo, url, confirmarDuplicado });
    $('#titulo-enlace').val(''); $('#url-enlace').val('');
    $('#tab-evidencias-contador').text(enlaces.length);
    pintarEnlaces();
    avisar('Enlace agregado.');
  } catch (e) {
    if (e.codigo === 'ENLACE_DUPLICADO') {
      if (await confirmar({ titulo: 'Enlace repetido', mensaje: e.message, textoOk: 'Agregar de nuevo' })) return agregarEnlace(true);
      return;
    }
    if (e.codigo === 'VALIDACION') {
      if (e.errores.titulo) $('#campo-titulo-enlace').addClass('error').find('.msg-error').text(e.errores.titulo).prop('hidden', false);
      if (e.errores.url) $('#campo-url-enlace').addClass('error').find('.msg-error').text(e.errores.url).prop('hidden', false);
    } else avisar(e.message, 'error');
  }
}
function pintarEnlaces() {
  $('#sin-enlaces').prop('hidden', !!enlaces.length);
  const $lista = $('#lista-enlaces').empty();
  enlaces.forEach((e) => {
    const $item = $(`
      <div class="item-lista" style="display:flex; justify-content:space-between; gap:1rem; flex-wrap:wrap">
        <div>
          <a target="_blank" rel="noopener noreferrer"><b></b></a>
          <div class="peq mut"></div>
        </div>
        <button class="btn btn-chico" hidden>Quitar</button>
      </div>`);
    $item.find('a').attr('href', e.url).find('b').text(e.titulo);
    $item.find('.peq.mut').text(`Agregado por ${e.usuario} (${e.area}) · ${fechaHora(e.fechaRegistro)}`);
    if (e.eliminable) $item.find('button').prop('hidden', false).on('click', () => quitarEnlace(e));
    $lista.append($item);
  });
}
async function quitarEnlace(e) {
  const si = await confirmar({ titulo: 'Quitar enlace', mensaje: `¿Quitar «${e.titulo}» de las evidencias? El documento original no se borra.`, textoOk: 'Quitar', peligro: true });
  if (si) ejecutar(async () => { enlaces = await Api.del('/enlaces/' + e.id); $('#tab-evidencias-contador').text(enlaces.length); pintarEnlaces(); }, 'Enlace quitado.');
}

// ---- archivar / reactivar ----
async function archivarOReactivar(reactivar) {
  const motivo = window.prompt(reactivar ? 'Motivo para reactivar el caso (mínimo 5 caracteres):' : 'Motivo para archivar el caso (mínimo 5 caracteres):');
  if (motivo === null) return; // el usuario dio "Cancelar"; con "Aceptar" y vacío, motivo es "" (no null)
  const limpio = motivo.trim();
  if (limpio.length < 5 || limpio.length > 300) {
    avisar('Escriba el motivo (de 5 a 300 caracteres).', 'error');
    return;
  }
  ejecutar(async () => aplicarCaso(await Api.post(`/casos/${idCaso}/${reactivar ? 'reactivar' : 'archivar'}`, { motivo: limpio })),
    reactivar ? 'Caso reactivado.' : 'Caso archivado. No se borró: puede reactivarlo.');
}
