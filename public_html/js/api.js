// Cliente de la API REST del backend. Usa jQuery ($.ajax) para las peticiones y devuelve
// promesas normales para poder usar async/await en cada página.

class ApiError extends Error {
  constructor(status, cuerpo) {
    super((cuerpo && cuerpo.mensaje) || 'Ocurrió un error inesperado. Intente de nuevo.');
    this.status = status;
    this.codigo = (cuerpo && cuerpo.codigo) || '';
    this.errores = (cuerpo && cuerpo.errores) || {};
  }
}

function apiUrl(ruta, params) {
  const q = new URLSearchParams();
  Object.entries(params || {}).forEach(([k, v]) => {
    if (v !== undefined && v !== null && v !== '') q.append(k, v);
  });
  const s = q.toString();
  // Sin "/" inicial a propósito: relativo a la carpeta de la página actual (todas las páginas
  // viven juntas en el mismo nivel), así funciona igual si la app queda en la raíz del dominio
  // o en una subcarpeta (p. ej. /siuce/) sin tener que tocar este archivo según el despliegue.
  return 'api' + ruta + (s ? '?' + s : '');
}

/**
 * Llama la API. opciones admite: params (query string), cuerpo (JSON), formData (subir archivo),
 * binario (true si la respuesta es un archivo para descargar, no JSON).
 */
function apiLlamar(metodo, ruta, opciones = {}) {
  const { params, cuerpo, formData, binario } = opciones;
  const cabeceras = {};
  if (Sesion.token) cabeceras.Authorization = 'Bearer ' + Sesion.token;

  const ajax = {
    url: apiUrl(ruta, params),
    method: metodo,
    headers: cabeceras,
    xhrFields: binario ? { responseType: 'blob' } : undefined,
  };
  if (formData) {
    ajax.data = formData;
    ajax.contentType = false;
    ajax.processData = false;
  } else if (cuerpo !== undefined) {
    ajax.data = JSON.stringify(cuerpo);
    ajax.contentType = 'application/json';
  }

  return new Promise((resolver, rechazar) => {
    $.ajax(ajax).done((datos, _texto, jqXHR) => {
      const renovado = jqXHR.getResponseHeader('X-Token-Renovado');
      if (renovado && Sesion.token) Sesion.renovarToken(renovado);
      if (binario) {
        const disposicion = jqXHR.getResponseHeader('Content-Disposition') || '';
        const m = /filename\*=UTF-8''([^;]+)/i.exec(disposicion) || /filename="?([^";]+)"?/i.exec(disposicion);
        resolver({ blob: datos, nombre: m ? decodeURIComponent(m[1]) : 'archivo' });
      } else {
        resolver(datos === '' ? null : datos);
      }
    }).fail((jqXHR) => {
      const renovado = jqXHR.getResponseHeader && jqXHR.getResponseHeader('X-Token-Renovado');
      if (renovado && Sesion.token) Sesion.renovarToken(renovado);
      let cuerpoError = null;
      try { cuerpoError = JSON.parse(jqXHR.responseText); } catch { /* respuesta sin cuerpo JSON */ }
      if (jqXHR.status === 0) {
        rechazar(new ApiError(0, { codigo: 'SIN_CONEXION', mensaje: 'No hay conexión con el servidor. Revise su conexión e intente de nuevo.' }));
        return;
      }
      if (jqXHR.status === 401 && ruta !== '/auth/login') Sesion.cerrar();
      rechazar(new ApiError(jqXHR.status, cuerpoError));
    });
  });
}

const Api = {
  get: (ruta, params) => apiLlamar('GET', ruta, { params }),
  post: (ruta, cuerpo) => apiLlamar('POST', ruta, { cuerpo: cuerpo === undefined ? {} : cuerpo }),
  put: (ruta, cuerpo) => apiLlamar('PUT', ruta, { cuerpo: cuerpo === undefined ? {} : cuerpo }),
  patch: (ruta, cuerpo) => apiLlamar('PATCH', ruta, { cuerpo: cuerpo === undefined ? {} : cuerpo }),
  del: (ruta) => apiLlamar('DELETE', ruta, {}),
  subir: (ruta, formData) => apiLlamar('POST', ruta, { formData }),
  descargar: (ruta, params) => apiLlamar('GET', ruta, { params, binario: true }),
};

/** Guarda en el equipo un archivo recibido de la API ({ blob, nombre }). */
function guardarArchivo({ blob, nombre }) {
  const a = document.createElement('a');
  const u = URL.createObjectURL(blob);
  a.href = u;
  a.download = nombre;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(u), 1000);
}
