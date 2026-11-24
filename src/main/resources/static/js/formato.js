// Funciones para mostrar fechas y porcentajes en español (Colombia).

const OPC_FECHA_HORA = { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' };
const OPC_FECHA = { day: '2-digit', month: 'short', year: 'numeric' };

function fechaHora(iso) {
  if (!iso) return '—';
  const d = new Date(iso);
  return Number.isNaN(d.getTime()) ? '—' : d.toLocaleString('es-CO', OPC_FECHA_HORA);
}

/** Para fechas sin hora (AAAA-MM-DD) se evita el corrimiento por zona horaria. */
function soloFecha(iso) {
  if (!iso) return '—';
  const d = new Date(iso + 'T00:00:00');
  return Number.isNaN(d.getTime()) ? '—' : d.toLocaleDateString('es-CO', OPC_FECHA);
}

function porcentaje(hechos, total = 12) {
  return Math.round((hechos / total) * 100);
}
