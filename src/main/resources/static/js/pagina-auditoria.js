const ETIQUETAS = {
  INICIO_SESION: 'Inicio de sesión', CUENTA_BLOQUEADA: 'Cuenta bloqueada', CAMBIO_CLAVE: 'Cambio de clave',
  CREAR_USUARIO: 'Crear usuario', INHABILITAR_USUARIO: 'Inhabilitar usuario', REACTIVAR_USUARIO: 'Reactivar usuario',
  RESTABLECER_CLAVE: 'Restablecer clave', CREAR_CASO: 'Crear caso', EDITAR_CASO: 'Editar caso',
  ARCHIVAR_CASO: 'Archivar caso', REACTIVAR_CASO: 'Reactivar caso', ACTIVAR_RUTA: 'Activar ruta', DESACTIVAR_RUTA: 'Desactivar ruta',
  PASO_EFECTUADO: 'Paso efectuado', PASO_PENDIENTE: 'Paso devuelto a pendiente', NOTA_PASO: 'Nota de paso',
  REGISTRAR_ACCION: 'Registrar acción', EDITAR_ACCION: 'Editar acción', AGREGAR_ENLACE: 'Agregar enlace', QUITAR_ENLACE: 'Quitar enlace',
  IMPORTAR_CSV: 'Importar CSV', EXPORTAR: 'Exportar casos', CREAR_CATALOGO: 'Crear en catálogo', EDITAR_CATALOGO: 'Editar catálogo',
};

let pagina = 0;
const TAMANO = 30;
let total = 0;

if (exigirSesion({ soloAdmin: true })) {
  pintarMenu('auditoria.html');
  $('#form-filtros').on('submit', (ev) => { ev.preventDefault(); pagina = 0; buscar(); });
  $('#btn-anterior').on('click', () => { if (pagina > 0) { pagina--; buscar(); } });
  $('#btn-siguiente').on('click', () => { if ((pagina + 1) * TAMANO < total) { pagina++; buscar(); } });
  iniciar();
}

async function iniciar() {
  try {
    const usuarios = await Api.get('/usuarios');
    usuarios.forEach((u) => $('#f-usuario').append(`<option value="${u.id}">${escaparHtml(u.nombre)}</option>`));
  } catch { /* se puede consultar sin la lista de usuarios */ }
  buscar();
}

async function buscar() {
  $('#alerta-error').prop('hidden', true);
  try {
    const filtro = { idCaso: $('#f-caso').val(), usuarioId: $('#f-usuario').val(), desde: $('#f-desde').val(), hasta: $('#f-hasta').val() };
    const r = await Api.get('/auditoria', { ...filtro, pagina, tamano: TAMANO });
    total = r.total;
    $('#total-registros').text(total + ' registro(s)');
    const $cuerpo = $('#tabla-auditoria tbody').empty();
    if (!r.contenido.length) {
      $('#sin-registros').prop('hidden', false);
      $('#tabla-auditoria').prop('hidden', true);
    } else {
      $('#sin-registros').prop('hidden', true);
      r.contenido.forEach((a) => {
        const $fila = $('<tr><td class="peq" style="white-space:nowrap"></td><td></td><td></td><td></td><td></td><td class="peq"></td></tr>');
        const celdas = $fila.find('td');
        celdas.eq(0).text(fechaHora(a.fecha));
        celdas.eq(1).text(a.usuario);
        celdas.eq(2).text(a.area);
        celdas.eq(3).text(ETIQUETAS[a.accion] || a.accion);
        celdas.eq(4).text(a.casoId || '—');
        celdas.eq(5).text(a.detalle || '—');
        $cuerpo.append($fila);
      });
      $('#tabla-auditoria').prop('hidden', false);
    }
    const totalPaginas = Math.max(1, Math.ceil(total / TAMANO));
    $('#texto-pagina').text(`Página ${pagina + 1} de ${totalPaginas}`);
    $('#btn-anterior').prop('disabled', pagina === 0);
    $('#btn-siguiente').prop('disabled', pagina + 1 >= totalPaginas);
  } catch (e) {
    $('#alerta-error').text(e.message).prop('hidden', false);
  }
}
