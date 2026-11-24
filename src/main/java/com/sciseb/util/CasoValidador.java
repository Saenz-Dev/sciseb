package com.sciseb.util;

import java.time.LocalDate;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import java.util.regex.Pattern;

/**
 * Reglas de validación de un caso (DAD, Tabla 3). Clase Java pura, sin dependencias de Spring,
 * usada tanto por el registro manual como por la importación CSV.
 */
public final class CasoValidador {

    public record Datos(
            String idCaso, String presuntoAgresor, List<String> victimas, String cedula,
            String areaAsignatura, String nRadicado, String resolucionSeb, String resolucionRectoral,
            String ubicacionAgresor, LocalDate fechaOcurrencia, String lugarOcurrencia,
            String disciplinario, String decisionComite, String penal) {}

    private static final Pattern ID = Pattern.compile("^[0-9]{5}$");
    private static final Pattern CEDULA = Pattern.compile("^[0-9]{5,12}$");
    private static final Pattern NOMBRE = Pattern.compile("^\\p{L}[\\p{L} .'-]{2,119}$");
    private static final Pattern INICIALES = Pattern.compile("^([A-ZÁÉÍÓÚÑ]\\.?){1,5}$");
    private static final Pattern ALFANUM = Pattern.compile("^[A-Za-z0-9/_. -]{1,30}$");
    private static final Pattern RESOLUCION = Pattern.compile("^[0-9]{1,20}$");
    private static final Pattern PENAL = Pattern.compile("^[0-9]{1,30}$");
    private static final LocalDate FECHA_MINIMA = LocalDate.of(2000, 1, 1);

    private CasoValidador() {}

    /** Quita espacios sobrantes; el texto vacío se vuelve nulo. */
    public static String limpiar(String s) {
        if (s == null) return null;
        String t = s.trim().replaceAll("\\s+", " ");
        return t.isEmpty() ? null : t;
    }

    public static String normalizarIniciales(String s) {
        String t = limpiar(s);
        return t == null ? null : t.replace(" ", "").toUpperCase(java.util.Locale.ROOT);
    }

    /** Devuelve campo -> mensaje de error; el mapa vacío significa que el caso es válido. */
    public static Map<String, String> validar(Datos d, LocalDate hoy) {
        Map<String, String> e = new LinkedHashMap<>();

        if (d.idCaso() != null && !ID.matcher(d.idCaso()).matches())
            e.put("idCaso", "El ID del caso debe tener exactamente 5 números (por ejemplo 00123).");

        if (d.presuntoAgresor() != null && !NOMBRE.matcher(d.presuntoAgresor()).matches())
            e.put("presuntoAgresor", "Escriba el nombre del presunto agresor (solo letras, de 3 a 120 caracteres).");

        if (d.victimas() != null && d.victimas().stream().anyMatch(v -> v == null || !INICIALES.matcher(v).matches()))
            e.put("victimas", "Escriba solo las iniciales de cada presunta víctima (de 1 a 5 letras, por ejemplo J.P.). Nunca el nombre.");

        // La cédula es opcional: si no se conoce todavía, el nombre del presunto agresor (si se dio)
        // queda guardado como borrador hasta que se complete la cédula (ver CasoService.persistirNuevo).
        // Si la cédula ya está registrada, el nombre se completa automáticamente al guardar.
        if (d.cedula() != null && !CEDULA.matcher(d.cedula()).matches())
            e.put("cedula", "La cédula debe tener entre 5 y 12 números, sin puntos ni espacios.");

        if (d.areaAsignatura() != null && d.areaAsignatura().length() > 80)
            e.put("areaAsignatura", "El área o asignatura no puede superar 80 caracteres.");

        if (d.nRadicado() != null && !ALFANUM.matcher(d.nRadicado()).matches())
            e.put("nRadicado", "El radicado solo puede tener letras, números y los signos / _ . - (máximo 30).");

        if (d.resolucionSeb() != null && !RESOLUCION.matcher(d.resolucionSeb()).matches())
            e.put("resolucionSeb", "La resolución SEB debe contener solo números.");

        if (d.resolucionRectoral() != null && !RESOLUCION.matcher(d.resolucionRectoral()).matches())
            e.put("resolucionRectoral", "La resolución rectoral debe contener solo números.");

        if (d.ubicacionAgresor() != null && d.ubicacionAgresor().length() > 500)
            e.put("ubicacionAgresor", "La ubicación no puede superar 500 caracteres.");

        if (d.fechaOcurrencia() != null) {
            if (d.fechaOcurrencia().isAfter(hoy))
                e.put("fechaOcurrencia", "La fecha de ocurrencia no puede ser futura.");
            else if (d.fechaOcurrencia().isBefore(FECHA_MINIMA))
                e.put("fechaOcurrencia", "La fecha de ocurrencia no es válida.");
        }

        if (d.lugarOcurrencia() != null && d.lugarOcurrencia().length() > 120)
            e.put("lugarOcurrencia", "El lugar de ocurrencia no puede superar 120 caracteres.");

        if (d.disciplinario() != null && !ALFANUM.matcher(d.disciplinario()).matches())
            e.put("disciplinario", "El dato disciplinario solo puede tener letras, números y los signos / _ . - (máximo 30).");

        if (d.decisionComite() != null && d.decisionComite().length() > 2000)
            e.put("decisionComite", "La decisión del comité no puede superar 2000 caracteres.");

        if (d.penal() != null && !PENAL.matcher(d.penal()).matches())
            e.put("penal", "El número penal debe contener solo números (máximo 30).");

        return e;
    }

    /** Muestra solo los últimos 3 dígitos: 12345678 -> ****678. */
    public static String enmascararCedula(String cedula) {
        if (cedula == null || cedula.length() < 4) return "****";
        return "*".repeat(cedula.length() - 3) + cedula.substring(cedula.length() - 3);
    }

    /** Evita inyección de fórmulas al abrir un CSV/Excel exportado. */
    public static String neutralizarFormula(String s) {
        if (s == null || s.isEmpty()) return s;
        char c = s.charAt(0);
        return (c == '=' || c == '+' || c == '-' || c == '@' || c == '\t' || c == '\r') ? "'" + s : s;
    }
}
