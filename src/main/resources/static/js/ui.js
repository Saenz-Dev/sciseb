// Piezas de interfaz compartidas por todas las páginas: menú de navegación, avisos y el
// cuadro de confirmación. Cada página debe tener en su HTML:
//   <header id="cab"></header> ... <div id="avisos" class="avisos" aria-live="polite"></div>
//   <dialog id="dlg-confirmar"></dialog>

/** Dibuja el menú superior según la sesión activa. paginaActual identifica el enlace resaltado. */
function pintarMenu(paginaActual) {
  const cab = $('#cab');
  if (!cab.length || !Sesion.activa || Sesion.debeCambiarClave) return;
  const u = Sesion.usuario;
  const enlace = (href, texto) => `<a href="${href}" class="${paginaActual === href ? 'activo' : ''}">${texto}</a>`;
  let nav = enlace('panel.html', 'Panel') + enlace('casos.html', 'Casos') + enlace('importar.html', 'Importar CSV');
  if (Sesion.esAdmin) {
    nav += enlace('usuarios.html', 'Usuarios') + enlace('catalogos.html', 'Catálogos') + enlace('auditoria.html', 'Auditoría');
  }
  cab.html(`
    <div class="cab-in">
      <a href="panel.html" class="marca"><i aria-hidden="true">SCI</i> Casos SIUCE</a>
      <nav class="nav" aria-label="Principal">${nav}</nav>
      <div class="usuario">
        <span><b>${escaparHtml(u.nombre)}</b><br><span class="mut">${escaparHtml(u.area)} · ${u.rol === 'ADMIN' ? 'Administrador' : 'Usuario'}</span></span>
        <button class="btn btn-chico" id="btn-salir">Salir</button>
      </div>
    </div>`);
  cab.find('#btn-salir').on('click', () => { Sesion.cerrar(); location.href = 'index.html'; });
}

function escaparHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

let contadorAvisos = 0;
/** Muestra un aviso breve en la esquina inferior derecha. tipo: 'ok' o 'error'. */
function avisar(texto, tipo = 'ok') {
  const cont = $('#avisos');
  if (!cont.length) return;
  const id = 'aviso-' + (++contadorAvisos);
  const el = $(`<div id="${id}" class="aviso ${tipo}" role="${tipo === 'error' ? 'alert' : 'status'}"></div>`).text(texto);
  cont.append(el);
  setTimeout(() => el.remove(), tipo === 'error' ? 8000 : 4000);
}

/** Muestra un cuadro de confirmación y devuelve una promesa con true/false. */
function confirmar({ titulo, mensaje, textoOk = 'Confirmar', peligro = false }) {
  return new Promise((resolver) => {
    const dlg = document.getElementById('dlg-confirmar');
    $(dlg).html(`
      <h2></h2><p></p>
      <div class="acciones" style="justify-content:flex-end">
        <button class="btn" id="dlg-cancelar">Cancelar</button>
        <button class="btn ${peligro ? 'btn-peligro' : 'btn-pri'}" id="dlg-ok"></button>
      </div>`);
    $(dlg).find('h2').text(titulo);
    $(dlg).find('p').text(mensaje);
    $(dlg).find('#dlg-ok').text(textoOk);
    const cerrar = (valor) => { dlg.close(); resolver(valor); };
    $(dlg).find('#dlg-cancelar').on('click', () => cerrar(false));
    $(dlg).find('#dlg-ok').on('click', () => cerrar(true));
    dlg.addEventListener('cancel', (ev) => { ev.preventDefault(); cerrar(false); }, { once: true });
    dlg.showModal();
  });
}
