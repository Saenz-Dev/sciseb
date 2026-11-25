// Guarda la sesión (token JWT + datos del usuario) en sessionStorage: se borra al cerrar la pestaña.
// El token se renueva en cada petición (el backend lo manda en X-Token-Renovado) y la sesión
// se da por terminada si pasan más de 30 minutos sin usarla.

const CLAVE_SESION = 'sciseb.sesion';
const MINUTOS_INACTIVIDAD = 30;

function sesionLeer() {
  try {
    return JSON.parse(sessionStorage.getItem(CLAVE_SESION)) || {};
  } catch {
    return {};
  }
}

function sesionGuardar(s) {
  try {
    sessionStorage.setItem(CLAVE_SESION, JSON.stringify(s));
  } catch {
    // El almacenamiento puede estar bloqueado (modo privado); la sesión sigue funcionando en memoria.
  }
}

const Sesion = {
  datos: sesionLeer(),

  get token() { return this.datos.token || null; },
  get usuario() { return this.datos.usuario || null; },
  get debeCambiarClave() { return !!this.datos.debeCambiarClave; },
  get esAdmin() { return this.usuario && this.usuario.rol === 'ADMIN'; },
  get activa() { return !!this.token && Date.now() < (this.datos.vence || 0); },

  iniciar(token, usuario, debeCambiarClave) {
    this.datos = { token, usuario, debeCambiarClave, vence: Date.now() + MINUTOS_INACTIVIDAD * 60 * 1000 };
    sesionGuardar(this.datos);
  },
  renovarToken(token) {
    this.datos.token = token;
    this.datos.vence = Date.now() + MINUTOS_INACTIVIDAD * 60 * 1000;
    sesionGuardar(this.datos);
  },
  claveCambiada(usuario) {
    this.datos.usuario = usuario;
    this.datos.debeCambiarClave = false;
    sesionGuardar(this.datos);
  },
  cerrar() {
    this.datos = {};
    try { sessionStorage.removeItem(CLAVE_SESION); } catch { /* nada que hacer */ }
  },
};

/** Debe llamarse al inicio de cada página que no sea de ingreso: exige sesión y clave ya cambiada. */
function exigirSesion({ soloAdmin = false } = {}) {
  if (!Sesion.activa) {
    if (Sesion.token) Sesion.cerrar();
    location.href = 'index.html';
    return false;
  }
  if (Sesion.debeCambiarClave) {
    location.href = 'cambiar-clave.html';
    return false;
  }
  if (soloAdmin && !Sesion.esAdmin) {
    location.href = 'panel.html';
    return false;
  }
  return true;
}
