let vistaPrevia = null;
let resultadoImportacion = null;

if (exigirSesion()) {
  pintarMenu('importar.html');

  $('#btn-plantilla').on('click', async () => {
    try { guardarArchivo(await Api.descargar('/importaciones/plantilla')); } catch (e) { avisar(e.message, 'error'); }
  });

  $('#entrada-archivo').on('change', function () {
    $('#btn-revisar').prop('disabled', !this.files.length);
    $('#seccion-vista-previa, #seccion-resultado').prop('hidden', true);
    $('#alerta-error').prop('hidden', true);
  });

  $('#btn-revisar').on('click', revisarArchivo);
  $('#chk-solo-malas').on('change', pintarFilas);
  $('#btn-importar').on('click', importar);
  $('#btn-cancelar-vista').on('click', cancelarVistaPrevia);
  $('#btn-informe').on('click', descargarInforme);
}

async function revisarArchivo() {
  const archivo = $('#entrada-archivo')[0].files[0];
  if (!archivo) { $('#alerta-error').text('Seleccione un archivo .csv.').prop('hidden', false); return; }
  $('#alerta-error').prop('hidden', true);
  $('#seccion-vista-previa, #seccion-resultado').prop('hidden', true);
  const $btn = $('#btn-revisar').prop('disabled', true).text('Revisando…');
  try {
    const fd = new FormData();
    fd.append('archivo', archivo);
    vistaPrevia = await Api.subir('/importaciones/vista-previa', fd);
    $('#vp-total').text(vistaPrevia.total);
    $('#vp-validas').text(vistaPrevia.validas);
    $('#vp-rechazadas').text(vistaPrevia.rechazadas);
    $('#chk-solo-malas').prop('checked', false);
    $('#vp-nota-300').prop('hidden', vistaPrevia.filas.length <= 300);
    $('#btn-importar').text('Importar ' + vistaPrevia.validas + ' caso(s)').prop('disabled', !vistaPrevia.validas);
    pintarFilas();
    $('#seccion-vista-previa').prop('hidden', false);
  } catch (e) {
    vistaPrevia = null;
    $('#alerta-error').text(e.message).prop('hidden', false);
  } finally {
    $btn.prop('disabled', false).text('Revisar archivo');
  }
}

function pintarFilas() {
  const soloMalas = $('#chk-solo-malas').is(':checked');
  const filas = (soloMalas ? vistaPrevia.filas.filter((f) => !f.valida) : vistaPrevia.filas).slice(0, 300);
  const $cuerpo = $('#vp-filas').empty();
  filas.forEach((f) => {
    const $fila = $(`<tr class="${f.valida ? '' : 'fila-mala'}"><td></td><td></td><td></td><td></td></tr>`);
    $fila.find('td').eq(0).text(f.fila);
    $fila.find('td').eq(1).text(f.idCaso || '—');
    $fila.find('td').eq(2).html(`<span class="badge ${f.valida ? 'b-ok' : 'b-bad'}"></span>`).find('.badge').text(f.valida ? 'Válida' : 'Con errores');
    const $celda = $fila.find('td').eq(3);
    if (f.errores.length) {
      const $ul = $('<ul style="margin:0; padding-left:1.1rem"></ul>');
      f.errores.forEach((m) => $ul.append($('<li></li>').text(m)));
      $celda.append($ul);
    } else $celda.html('<span class="mut">—</span>');
    $cuerpo.append($fila);
  });
}

async function importar() {
  const si = await confirmar({
    titulo: 'Importar casos',
    mensaje: `Se registrarán ${vistaPrevia.validas} caso(s) con sus 12 pasos pendientes.` +
      (vistaPrevia.rechazadas ? ` Las ${vistaPrevia.rechazadas} fila(s) con errores no se importarán.` : '') + ' Esta acción se guarda todo junto.',
    textoOk: `Importar ${vistaPrevia.validas}`,
  });
  if (!si) return;
  const $btn = $('#btn-importar').prop('disabled', true).text('Importando…');
  try {
    resultadoImportacion = await Api.post('/importaciones/confirmar', { token: vistaPrevia.token });
    vistaPrevia = null;
    $('#seccion-vista-previa').prop('hidden', true);
    $('#entrada-archivo').val('');
    $('#btn-revisar').prop('disabled', true);
    const r = resultadoImportacion;
    $('#resultado-texto').html(`Se importaron <b>${r.importadas}</b> de ${r.total} fila(s). ` + (r.rechazadas ? `${r.rechazadas} no se importaron.` : ''));
    $('#btn-informe').prop('hidden', !r.rechazadas);
    $('#seccion-resultado').prop('hidden', false);
    avisar(`Se importaron ${r.importadas} caso(s).`);
  } catch (e) {
    $('#alerta-error').text(e.message).prop('hidden', false);
    if (e.codigo === 'VISTA_PREVIA_VENCIDA' || e.codigo === 'ID_DUPLICADO') { vistaPrevia = null; $('#seccion-vista-previa').prop('hidden', true); }
  } finally {
    $btn.prop('disabled', false).text('Importar');
  }
}

function cancelarVistaPrevia() {
  const token = vistaPrevia && vistaPrevia.token;
  vistaPrevia = null;
  $('#seccion-vista-previa').prop('hidden', true);
  $('#entrada-archivo').val('');
  $('#btn-revisar').prop('disabled', true);
  if (token) Api.del('/importaciones/vista-previa/' + token).catch(() => {});
}

/** Informe de filas rechazadas: fila, ID y motivo. No incluye datos personales. */
function descargarInforme() {
  const filas = (resultadoImportacion && resultadoImportacion.filasRechazadas) || [];
  const celda = (v) => '"' + neutralizarFormula(String(v ?? '')).replace(/"/g, '""') + '"';
  const csv = '﻿' + ['fila;id_caso;motivo', ...filas.map((f) => [f.fila, celda(f.idCaso), celda(f.motivo)].join(';'))].join('\r\n');
  guardarArchivo({ blob: new Blob([csv], { type: 'text/csv;charset=utf-8' }), nombre: 'informe_filas_rechazadas.csv' });
}
