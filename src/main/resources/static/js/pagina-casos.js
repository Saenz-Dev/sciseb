let pagina = 0;
const TAMANO = 20;
let total = 0;
let temporizadorBusqueda;

if (exigirSesion()) {
  pintarMenu('casos.html');
  if (Sesion.esAdmin) $('#ayuda-cedula').prop('hidden', true);
  iniciar();
}

async function iniciar() {
  try {
    const cat = await cargarCatalogos();
    llenarSelect($('#f-prov'), cat.provincias, { placeholder: 'Todas' });
    llenarSelect($('#f-clas'), cat.clasificaciones, { placeholder: 'Todas' });
  } catch { /* se puede filtrar sin catálogos cargados */ }

  $('#f-q').on('input', () => { clearTimeout(temporizadorBusqueda); temporizadorBusqueda = setTimeout(() => { pagina = 0; buscar(); }, 300); });
  $('#f-prov, #f-clas, #f-ruta, #f-estado, #f-borrador').on('change', () => { pagina = 0; buscar(); });
  $('#form-filtros').on('submit', (ev) => { ev.preventDefault(); pagina = 0; buscar(); });
  $('#btn-limpiar').on('click', () => {
    $('#f-q').val(''); $('#f-prov').val(''); $('#f-clas').val(''); $('#f-ruta').val(''); $('#f-estado').val('ACTIVO'); $('#f-borrador').val('');
    pagina = 0; buscar();
  });
  $('#btn-anterior').on('click', () => { if (pagina > 0) { pagina--; buscar(); } });
  $('#btn-siguiente').on('click', () => { if ((pagina + 1) * TAMANO < total) { pagina++; buscar(); } });
  $('#btn-exportar-csv').on('click', () => exportar('csv'));
  $('#btn-exportar-xlsx').on('click', () => exportar('xlsx'));

  buscar();
}

function filtroActual() {
  return {
    q: $('#f-q').val().trim(), provinciaId: $('#f-prov').val(), clasificacionId: $('#f-clas').val(),
    ruta: $('#f-ruta').val(), estado: $('#f-estado').val(), borrador: $('#f-borrador').val(),
  };
}

async function buscar() {
  $('#alerta-error').prop('hidden', true);
  try {
    const r = await Api.get('/casos', { ...filtroActual(), pagina, tamano: TAMANO });
    total = r.total;
    $('#total-casos').text(total + ' caso(s) encontrados');
    const $cuerpo = $('#tabla-casos tbody').empty();
    if (!r.contenido.length) {
      $('#sin-resultados').prop('hidden', false);
      $('#tabla-casos').prop('hidden', true);
    } else {
      $('#sin-resultados').prop('hidden', true);
      r.contenido.forEach((c) => $cuerpo.append(filaCaso(c)));
      $('#tabla-casos').prop('hidden', false);
    }
    const totalPaginas = Math.max(1, Math.ceil(total / TAMANO));
    $('#texto-pagina').text(`Página ${pagina + 1} de ${totalPaginas}`);
    $('#btn-anterior').prop('disabled', pagina === 0);
    $('#btn-siguiente').prop('disabled', pagina + 1 >= totalPaginas);
  } catch (e) {
    $('#alerta-error').text(e.message).prop('hidden', false);
  }
}

function filaCaso(c) {
  const pct = (c.pasosEfectuados / 12) * 100;
  const $fila = $(`
    <tr class="fila-link" tabindex="0">
      <td><b></b><br><span class="badge b-gris archivado" hidden>Archivado</span></td>
      <td class="agresor"></td>
      <td class="cedula"></td>
      <td class="institucion"></td>
      <td class="municipio"></td>
      <td><span class="badge clasificacion"></span></td>
      <td><span class="badge ruta"></span></td>
      <td><div class="mini"><div class="barra"><i style="width:${pct}%"></i></div>${c.pasosEfectuados}/12</div></td>
      <td class="peq edicion"></td>
    </tr>`);
  $fila.find('b').text(c.idCaso || '—');
  if (c.estado === 'ARCHIVADO') $fila.find('.archivado').prop('hidden', false);
  const notaBorrador = c.agresorBorrador ? ' <span class="badge b-warn">Borrador</span>' : '';
  $fila.find('.agresor').html(`${escaparHtml(c.presuntoAgresor || '—')}${notaBorrador}<br><span class="mut peq">Víctima(s): ${escaparHtml(c.victimas.join(', ') || '—')}</span>`);
  $fila.find('.cedula').text(c.cedula || '—');
  $fila.find('.institucion').html(`${escaparHtml(c.institucion || '—')}<br><span class="mut peq">${escaparHtml(c.sede || '')}</span>`);
  $fila.find('.municipio').html(`${escaparHtml(c.municipio || '—')}<br><span class="mut peq">${escaparHtml(c.provincia || '')}</span>`);
  $fila.find('.clasificacion').addClass(c.clasificacion ? (c.clasificacion === 'Tipo III' ? 'b-bad' : 'b-warn') : 'b-gris').text(c.clasificacion || '—');
  $fila.find('.ruta').addClass(c.rutaActiva ? 'b-ok' : 'b-gris').text(c.rutaActiva ? 'Activa' : 'Inactiva');
  $fila.find('.edicion').html(`${fechaHora(c.fechaUltimaEdicion)}<br><span class="mut"></span>`).find('.mut').text(c.editadoPor);
  $fila.on('click keydown', (ev) => { if (ev.type === 'click' || ev.key === 'Enter') location.href = 'caso-detalle.html?id=' + c.id; });
  return $fila;
}

async function exportar(formato) {
  try {
    guardarArchivo(await Api.descargar('/casos/exportar', { ...filtroActual(), formato }));
    avisar('Archivo descargado.');
  } catch (e) {
    avisar(e.message, 'error');
  }
}
