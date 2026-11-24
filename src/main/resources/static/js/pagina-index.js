$(function () {
  // Si ya hay una sesión activa, se pasa directo al panel.
  if (Sesion.activa) { location.href = Sesion.debeCambiarClave ? 'cambiar-clave.html' : 'panel.html'; return; }

  if (!Sesion.token && sessionStorage.getItem('sciseb.mensajeSesion')) {
    $('#alerta-sesion').text(sessionStorage.getItem('sciseb.mensajeSesion')).prop('hidden', false);
    sessionStorage.removeItem('sciseb.mensajeSesion');
  }

  $('#form-login').on('submit', async function (ev) {
    ev.preventDefault();
    $('#alerta-error').prop('hidden', true);
    const correo = $('#correo').val().trim();
    const clave = $('#clave').val();
    if (!correo || !clave) { $('#alerta-error').text('Escriba su correo y su clave.').prop('hidden', false); return; }

    const $btn = $('#btn-ingresar').prop('disabled', true).text('Ingresando…');
    try {
      const r = await Api.post('/auth/login', { correo, clave });
      Sesion.iniciar(r.token, r.usuario, r.debeCambiarClave);
      location.href = r.debeCambiarClave ? 'cambiar-clave.html' : 'panel.html';
    } catch (e) {
      $('#alerta-error').text(e.message).prop('hidden', false);
    } finally {
      $btn.prop('disabled', false).text('Ingresar');
      $('#clave').val('');
    }
  });
});
