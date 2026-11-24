if (exigirSesion()) {
  pintarMenu('panel.html');
  cargarPanel();
}

async function cargarPanel() {
  try {
    const [ind, pagina] = await Promise.all([Api.get('/indicadores'), Api.get('/casos', { tamano: 6 })]);
    $('#ind-total').text(ind.total);
    $('#ind-ruta-activa').text(ind.rutaActiva);
    $('#ind-tipo1').text(ind.tipoI);
    $('#ind-tipo3').text(ind.tipoIII);
    $('#ind-ruta-completa').text(ind.rutaCompleta);
    $('#indicadores').prop('hidden', false);

    const recientes = pagina.contenido;
    if (!recientes.length) { $('#sin-casos').prop('hidden', false); return; }
    const $cuerpo = $('#tabla-recientes tbody').empty();
    recientes.forEach((c) => {
      const pct = (c.pasosEfectuados / 12) * 100;
      const $fila = $(`
        <tr class="fila-link" tabindex="0">
          <td><b></b></td>
          <td class="agresor"></td>
          <td class="institucion"></td>
          <td class="clasificacion"></td>
          <td><div class="mini"><div class="barra"><i style="width:${pct}%"></i></div>${c.pasosEfectuados}/12</div></td>
          <td class="peq"></td>
        </tr>`);
      $fila.find('b').text(c.idCaso || '—');
      $fila.find('.agresor').text(c.presuntoAgresor);
      $fila.find('.institucion').html(`${escaparHtml(c.institucion)}<br><span class="mut peq">${escaparHtml(c.municipio)}</span>`);
      $fila.find('.clasificacion').html(`<span class="badge ${c.clasificacion === 'Tipo III' ? 'b-bad' : 'b-warn'}"></span>`).find('.badge').text(c.clasificacion);
      $fila.find('td.peq').html(`${fechaHora(c.fechaUltimaEdicion)}<br><span class="mut"></span>`).find('.mut').text(c.editadoPor);
      $fila.on('click keydown', (ev) => { if (ev.type === 'click' || ev.key === 'Enter') location.href = 'caso-detalle.html?id=' + c.id; });
      $cuerpo.append($fila);
    });
    $('#tabla-recientes').prop('hidden', false);
  } catch (e) {
    $('#alerta-error').text(e.message).prop('hidden', false);
  }
}
