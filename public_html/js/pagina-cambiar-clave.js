if (!Sesion.token) location.href = 'index.html';

$('#btn-salir').on('click', () => { Sesion.cerrar(); location.href = 'index.html'; });

function limpiarErrores() {
  ['actual', 'nueva', 'repetir'].forEach((k) => { $('#campo-' + k).removeClass('error'); $('#error-' + k).prop('hidden', true); });
  $('#alerta-general').prop('hidden', true);
}
function marcarError(campo, mensaje) {
  $('#campo-' + campo).addClass('error');
  $('#error-' + campo).text(mensaje).prop('hidden', false);
}

$('#form-clave').on('submit', async function (ev) {
  ev.preventDefault();
  limpiarErrores();
  const claveActual = $('#ca').val();
  const claveNueva = $('#cn').val();
  const repetir = $('#cr').val();

  let hayErrores = false;
  if (!claveActual) { marcarError('actual', 'Escriba la clave actual (la temporal que le entregó el Administrador).'); hayErrores = true; }
  if (!POLITICA_CLAVE.test(claveNueva)) { marcarError('nueva', 'Mínimo 10 caracteres, con mayúscula, minúscula y número.'); hayErrores = true; }
  if (repetir !== claveNueva) { marcarError('repetir', 'Las dos claves nuevas no coinciden.'); hayErrores = true; }
  if (hayErrores) return;

  const $btn = $('#btn-guardar').prop('disabled', true).text('Guardando…');
  try {
    const usuario = await Api.post('/auth/cambiar-clave', { claveActual, claveNueva });
    Sesion.claveCambiada(usuario);
    avisarSimple('Clave actualizada.');
    location.href = 'panel.html';
  } catch (e) {
    const campos = { claveActual: 'actual', claveNueva: 'nueva', repetir: 'repetir' };
    const claves = Object.keys(e.errores || {});
    if (claves.length) claves.forEach((k) => marcarError(campos[k] || 'actual', e.errores[k]));
    else $('#alerta-general').text(e.message).prop('hidden', false);
  } finally {
    $btn.prop('disabled', false).text('Guardar clave');
  }
});

// Esta página no tiene el contenedor de avisos del resto del sitio; basta con la alerta del formulario.
function avisarSimple() {}
