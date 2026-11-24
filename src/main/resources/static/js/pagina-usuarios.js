const ESTADOS = {
  ACTIVO: ['Activo', 'b-ok'], PENDIENTE_CAMBIO_CLAVE: ['Debe cambiar la clave', 'b-warn'],
  BLOQUEADO: ['Bloqueado', 'b-bad'], INACTIVO: ['Inhabilitado', 'b-gris'],
};

if (exigirSesion({ soloAdmin: true })) {
  pintarMenu('usuarios.html');
  $('#form-usuario').on('submit', (ev) => { ev.preventDefault(); crearUsuario(); });
  $('#btn-cerrar-clave').on('click', () => $('#alerta-clave-temporal').prop('hidden', true));
  $('#btn-copiar-clave').on('click', copiarClave);
  cargar();
}

async function cargar() {
  try {
    const [usuarios, cat] = await Promise.all([Api.get('/usuarios'), cargarCatalogos(true)]);
    llenarSelect($('#u-area'), cat.areas);
    pintarUsuarios(usuarios);
  } catch (e) { $('#alerta-error').text(e.message).prop('hidden', false); }
}

function pintarUsuarios(usuarios) {
  const $cuerpo = $('#tabla-usuarios').empty();
  usuarios.forEach((u) => {
    const [texto, clase] = ESTADOS[u.estado];
    const $fila = $(`
      <tr>
        <td class="nombre"></td><td class="correo"></td><td class="area"></td><td class="rol"></td>
        <td><span class="badge ${clase}"></span></td><td class="peq ultimo"></td><td class="col-acciones"></td>
      </tr>`);
    $fila.find('.nombre').text(u.nombre);
    $fila.find('.correo').text(u.correo);
    $fila.find('.area').text(u.area);
    $fila.find('.rol').text(u.rol === 'ADMIN' ? 'Administrador' : 'Usuario');
    $fila.find('.badge').text(texto);
    $fila.find('.ultimo').text(fechaHora(u.ultimoAcceso));
    const $celda = $fila.find('.col-acciones');
    if (u.id === Sesion.usuario.id) {
      $celda.html('<span class="mut peq">(usted)</span>');
    } else {
      const $acc = $('<div class="acciones"></div>');
      if (u.estado !== 'INACTIVO') {
        $acc.append($('<button class="btn btn-chico">Restablecer clave</button>').on('click', () => restablecerClave(u)));
        $acc.append($('<button class="btn btn-chico">Inhabilitar</button>').on('click', () => cambiarEstado(u, 'INACTIVO')));
      } else {
        $acc.append($('<button class="btn btn-chico">Reactivar</button>').on('click', () => cambiarEstado(u, 'ACTIVO')));
      }
      $celda.append($acc);
    }
    $cuerpo.append($fila);
  });
}

function limpiarErroresUsuario() {
  ['nombre', 'correo', 'areaId', 'rol'].forEach((c) => $('#campo-' + c).removeClass('error').find('.msg-error').prop('hidden', true));
}

async function crearUsuario() {
  limpiarErroresUsuario();
  const cuerpo = { nombre: $('#u-nombre').val(), correo: $('#u-correo').val(), areaId: $('#u-area').val() || null, rol: $('#u-rol').val() };
  const $btn = $('#btn-crear-usuario').prop('disabled', true);
  try {
    const r = await Api.post('/usuarios', cuerpo);
    mostrarClaveTemporal(r.usuario.nombre, r.claveTemporal);
    $('#u-nombre').val(''); $('#u-correo').val(''); $('#u-area').val(''); $('#u-rol').val('USUARIO');
    cargar();
  } catch (e) {
    const claves = Object.keys(e.errores || {});
    if (claves.length) claves.forEach((c) => $('#campo-' + c).addClass('error').find('.msg-error').text(e.errores[c]).prop('hidden', false));
    else avisar(e.message, 'error');
  } finally {
    $btn.prop('disabled', false);
  }
}

async function cambiarEstado(u, estado) {
  const texto = estado === 'INACTIVO' ? 'inhabilitar' : 'reactivar';
  const si = await confirmar({
    titulo: 'Confirmar',
    mensaje: `¿Desea ${texto} a ${u.nombre}?` + (estado === 'ACTIVO' ? ' Podrá ingresar con su clave anterior y deberá cambiarla.' : ' Dejará de poder ingresar de inmediato.'),
    textoOk: texto.charAt(0).toUpperCase() + texto.slice(1), peligro: estado === 'INACTIVO',
  });
  if (!si) return;
  try { await Api.patch(`/usuarios/${u.id}/estado`, { estado }); avisar('Estado actualizado.'); cargar(); } catch (e) { avisar(e.message, 'error'); }
}

async function restablecerClave(u) {
  const si = await confirmar({ titulo: 'Restablecer clave', mensaje: `Se generará una clave temporal para ${u.nombre} y deberá cambiarla al ingresar.`, textoOk: 'Restablecer' });
  if (!si) return;
  try {
    const r = await Api.post(`/usuarios/${u.id}/restablecer-clave`);
    mostrarClaveTemporal(u.nombre, r.claveTemporal);
    cargar();
  } catch (e) { avisar(e.message, 'error'); }
}

function mostrarClaveTemporal(nombre, clave) {
  $('#ct-nombre').text(nombre);
  $('#ct-clave').text(clave);
  $('#alerta-clave-temporal').prop('hidden', false).data('clave', clave);
}
async function copiarClave() {
  try { await navigator.clipboard.writeText($('#alerta-clave-temporal').data('clave')); avisar('Clave copiada.'); }
  catch { avisar('Selecciónela y cópiela manualmente.', 'error'); }
}
