package com.sciseb.service;

import com.sciseb.config.ApiException;
import com.sciseb.config.AppProperties;
import com.sciseb.config.Sesion;
import com.sciseb.domain.ClasificacionSiuce;
import com.sciseb.domain.ImportacionCsv;
import com.sciseb.domain.Sede;
import com.sciseb.dto.Dto.*;
import com.sciseb.repo.*;
import com.sciseb.util.CasoValidador;
import com.sciseb.util.CasoValidador.Datos;
import lombok.RequiredArgsConstructor;
import org.apache.commons.csv.CSVFormat;
import org.apache.commons.csv.CSVParser;
import org.apache.commons.csv.CSVRecord;
import org.springframework.http.HttpStatus;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.io.IOException;
import java.io.StringReader;
import java.nio.ByteBuffer;
import java.nio.charset.CharacterCodingException;
import java.nio.charset.CodingErrorAction;
import java.nio.charset.StandardCharsets;
import java.time.Instant;
import java.time.LocalDate;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.time.format.DateTimeParseException;
import java.util.*;
import java.util.concurrent.ConcurrentHashMap;

/**
 * Importación de casos desde CSV (CU06). El archivo NO se guarda: se lee en memoria, se valida fila por
 * fila con las mismas reglas del registro manual y solo las filas válidas quedan en una vista previa
 * temporal (en memoria, vence a los 15 minutos) hasta que la persona confirma. La confirmación es atómica.
 */
@Service
@RequiredArgsConstructor
public class ImportacionService {

    public static final List<String> COLUMNAS = List.of(
            "id_caso", "presunto_agresor", "victima_iniciales", "cedula", "area_asignatura", "institucion", "sede",
            "municipio", "provincia", "clasificacion", "ruta", "n_radicado", "resolucion_seb", "resolucion_rectoral",
            "ubicacion", "fecha_ocurrencia", "lugar_ocurrencia", "disciplinario", "decision_comite", "penal");

    static final List<String> OBLIGATORIAS = List.of("presunto_agresor", "victima_iniciales", "cedula",
            "institucion", "sede", "municipio", "provincia", "clasificacion");

    private static final DateTimeFormatter FECHA_LATINA = DateTimeFormatter.ofPattern("d/M/uuuu");

    private record Pendiente(int fila, Datos datos, Integer sedeId, Integer clasificacionId, boolean rutaActiva) {}

    private record Preparada(String token, Integer usuarioId, String archivo, int total, int rechazadasCount,
                             List<FilaRechazada> rechazadas, List<Pendiente> pendientes, Instant vence) {}

    private final Map<String, Preparada> vistas = new ConcurrentHashMap<>();

    private final AppProperties props;
    private final ProvinciaRepository provincias;
    private final MunicipioRepository municipios;
    private final InstitucionRepository instituciones;
    private final SedeRepository sedes;
    private final ClasificacionRepository clasificaciones;
    private final AgresorRepository agresores;
    private final CasoRepository casos;
    private final UsuarioRepository usuarios;
    private final ImportacionRepository importaciones;
    private final CasoService casoService;
    private final AuditoriaService auditoria;

    // ------------------------------------------------------------------ plantilla

    public String plantillaCsv() {
        return "﻿" + String.join(";", COLUMNAS) + "\r\n";
    }

    // ------------------------------------------------------------------ vista previa

    private static String decodificar(byte[] bytes) {
        int ini = (bytes.length >= 3 && (bytes[0] & 0xFF) == 0xEF && (bytes[1] & 0xFF) == 0xBB && (bytes[2] & 0xFF) == 0xBF) ? 3 : 0;
        try {
            return StandardCharsets.UTF_8.newDecoder()
                    .onMalformedInput(CodingErrorAction.REPORT).onUnmappableCharacter(CodingErrorAction.REPORT)
                    .decode(ByteBuffer.wrap(bytes, ini, bytes.length - ini)).toString();
        } catch (CharacterCodingException e) {
            // CSV guardado por Excel en español ("CSV (delimitado por comas)") suele venir en Windows-1252
            return new String(bytes, ini, bytes.length - ini, java.nio.charset.Charset.forName("windows-1252"));
        }
    }

    private static String valor(CSVRecord r, String col) {
        return r.isMapped(col) && r.isSet(col) ? CasoValidador.limpiar(r.get(col)) : null;
    }

    /** Una o más víctimas separadas por ';' en la misma celda (ej. "J.P.; M.G."). */
    private static List<String> victimas(String celda) {
        if (celda == null) return List.of();
        return Arrays.stream(celda.split(";"))
                .map(CasoValidador::normalizarIniciales)
                .filter(Objects::nonNull)
                .toList();
    }

    private static LocalDate fecha(String t) {
        if (t == null) return null;
        try {
            return LocalDate.parse(t);
        } catch (DateTimeParseException e) {
            try {
                return LocalDate.parse(t, FECHA_LATINA);
            } catch (DateTimeParseException e2) {
                return null;
            }
        }
    }

    private static String claveClasificacion(String t) {
        if (t == null) return null;
        String x = t.toUpperCase(Locale.ROOT).replace("TIPO", "").replace(" ", "");
        return switch (x) {
            case "I", "1" -> "Tipo I";
            case "II", "2" -> "Tipo II";
            case "III", "3" -> "Tipo III";
            default -> null;
        };
    }

    private static Boolean ruta(String t) {
        if (t == null) return false;
        return switch (t.toLowerCase(Locale.ROOT)) {
            case "activa", "si", "sí", "true", "1" -> true;
            case "inactiva", "no", "false", "0" -> false;
            default -> null;
        };
    }

    @Transactional(readOnly = true)
    public VistaPrevia vistaPrevia(Sesion s, String nombreArchivo, byte[] contenido) {
        purgar();
        if (contenido == null || contenido.length == 0)
            throw ApiException.solicitudInvalida("El archivo está vacío.");
        String nombre = nombreArchivo == null ? "archivo.csv" : nombreArchivo.replaceAll("[\\r\\n\\\\/]", "_");
        if (nombre.length() > 150) nombre = nombre.substring(nombre.length() - 150);
        if (!nombre.toLowerCase(Locale.ROOT).endsWith(".csv"))
            throw ApiException.solicitudInvalida("Seleccione un archivo con extensión .csv.");

        String texto = decodificar(contenido);
        int fin = texto.indexOf('\n');
        String primera = fin < 0 ? texto : texto.substring(0, fin);
        char delimitador = primera.chars().filter(ch -> ch == ';').count() > primera.chars().filter(ch -> ch == ',').count() ? ';' : ',';

        CSVFormat formato = CSVFormat.DEFAULT.builder().setDelimiter(delimitador).setHeader()
                .setSkipHeaderRecord(true).setIgnoreEmptyLines(true).setTrim(true).setIgnoreHeaderCase(true).build();
        List<CSVRecord> registros;
        Set<String> encabezados;
        try (CSVParser parser = CSVParser.parse(new StringReader(texto), formato)) {
            encabezados = new HashSet<>();
            for (String h : parser.getHeaderNames()) if (h != null) encabezados.add(h.trim().toLowerCase(Locale.ROOT));
            registros = parser.getRecords();
        } catch (IOException | IllegalArgumentException e) {
            throw ApiException.solicitudInvalida("No se pudo leer el archivo. Verifique que sea un CSV con la plantilla del sistema.");
        }
        List<String> faltan = OBLIGATORIAS.stream().filter(c -> !encabezados.contains(c)).toList();
        if (!faltan.isEmpty())
            throw new ApiException(HttpStatus.UNPROCESSABLE_ENTITY, "COLUMNAS_FALTANTES",
                    "Al archivo le faltan estas columnas: " + String.join(", ", faltan) + ". Use la plantilla del sistema.", null);
        if (registros.isEmpty())
            throw ApiException.solicitudInvalida("El archivo no tiene filas de datos.");
        int max = props.importacion().maxFilas();
        if (registros.size() > max)
            throw new ApiException(HttpStatus.UNPROCESSABLE_ENTITY, "DEMASIADAS_FILAS",
                    "El archivo tiene " + registros.size() + " filas y el máximo es " + max + ". Divídalo en varios archivos.", null);

        List<Pendiente> validas = new ArrayList<>();
        List<FilaRechazada> rechazadas = new ArrayList<>();
        List<FilaImportacion> vista = new ArrayList<>();
        Set<String> idsArchivo = new HashSet<>();
        Map<String, String> nombresPorCedula = new HashMap<>();
        int contador = 1; // la fila 1 del archivo es el encabezado
        LocalDate hoy = LocalDate.now();

        for (CSVRecord r : registros) {
            int fila = ++contador;
            List<String> errores = new ArrayList<>();

            Datos d = new Datos(valor(r, "id_caso"), valor(r, "presunto_agresor"),
                    victimas(valor(r, "victima_iniciales")), valor(r, "cedula"),
                    valor(r, "area_asignatura"), valor(r, "n_radicado"), valor(r, "resolucion_seb"),
                    valor(r, "resolucion_rectoral"), valor(r, "ubicacion"), fecha(valor(r, "fecha_ocurrencia")),
                    valor(r, "lugar_ocurrencia"), valor(r, "disciplinario"), valor(r, "decision_comite"), valor(r, "penal"));
            Map<String, String> fallos = CasoValidador.validar(d, hoy);
            if (valor(r, "fecha_ocurrencia") != null && d.fechaOcurrencia() == null)
                fallos.put("fechaOcurrencia", "La fecha debe estar en formato AAAA-MM-DD o DD/MM/AAAA.");
            errores.addAll(fallos.values());

            // ubicación geográfica por nombre: todo es opcional, pero si viene un nombre debe existir en el catálogo
            Integer sedeId = null;
            String nProv = valor(r, "provincia"), nMun = valor(r, "municipio"), nIe = valor(r, "institucion"), nSede = valor(r, "sede");
            if (nProv != null || nMun != null || nIe != null || nSede != null) {
                var prov = nProv == null ? null : provincias.findByNombreIgnoreCase(nProv).orElse(null);
                if (nProv != null && prov == null) {
                    errores.add("La provincia no existe en el catálogo.");
                } else {
                    var mun = nMun == null ? null : (prov == null ? null : municipios.findByNombreIgnoreCaseAndProvinciaId(nMun, prov.getId()).orElse(null));
                    if (nMun != null && mun == null) {
                        errores.add("El municipio no existe en esa provincia del catálogo.");
                    } else {
                        var ie = nIe == null ? null : (mun == null ? null : instituciones.findByNombreIgnoreCaseAndMunicipioId(nIe, mun.getId()).orElse(null));
                        if (nIe != null && ie == null) {
                            errores.add("La institución educativa no existe en ese municipio del catálogo.");
                        } else if (nSede != null) {
                            var sd = ie == null ? null : sedes.findByNombreIgnoreCaseAndInstitucionId(nSede, ie.getId()).orElse(null);
                            if (sd == null) errores.add("La sede no existe en esa institución del catálogo.");
                            else sedeId = sd.getId();
                        }
                    }
                }
            }

            String celdaCl = valor(r, "clasificacion");
            Integer clId = null;
            if (celdaCl != null) {
                String nCl = claveClasificacion(celdaCl);
                clId = nCl == null ? null : clasificaciones.findByNombreIgnoreCase(nCl).map(ClasificacionSiuce::getId).orElse(null);
                if (clId == null) errores.add("La clasificación debe ser Tipo I, Tipo II o Tipo III.");
            }

            Boolean rutaActiva = ruta(valor(r, "ruta"));
            if (rutaActiva == null) errores.add("La ruta debe ser Activa o Inactiva.");

            if (d.idCaso() != null && fallos.get("idCaso") == null) {
                if (!idsArchivo.add(d.idCaso())) errores.add("El ID del caso está repetido dentro del archivo.");
                else if (casos.existsByIdCaso(d.idCaso())) errores.add("Ya existe un caso con ese ID.");
            }
            if (d.cedula() != null && fallos.get("cedula") == null) {
                var ex = agresores.findByCedula(d.cedula());
                if (d.presuntoAgresor() != null && fallos.get("presuntoAgresor") == null) {
                    String nombreNorm = d.presuntoAgresor().toLowerCase(Locale.ROOT);
                    String previo = nombresPorCedula.putIfAbsent(d.cedula(), nombreNorm);
                    if (previo != null && !previo.equals(nombreNorm)) {
                        errores.add("La cédula aparece en el archivo con nombres distintos.");
                    } else if (ex.isPresent() && !ex.get().getNombre().equalsIgnoreCase(d.presuntoAgresor())) {
                        errores.add("La cédula ya está registrada con otro nombre; regístrelo manualmente para confirmarlo.");
                    }
                } else if (ex.isEmpty()) {
                    // cédula nueva: el nombre es obligatorio porque la tabla presunto_agresor exige ambos datos
                    errores.add("Esta cédula no está registrada; indique también el nombre del presunto agresor.");
                }
            }

            String idMostrar = d.idCaso();
            if (errores.isEmpty()) {
                validas.add(new Pendiente(fila, d, sedeId, clId, rutaActiva));
                vista.add(new FilaImportacion(fila, idMostrar, true, List.of()));
            } else {
                rechazadas.add(new FilaRechazada(fila, idMostrar, String.join(" ", errores)));
                vista.add(new FilaImportacion(fila, idMostrar, false, errores));
            }
        }

        String token = UUID.randomUUID().toString();
        vistas.put(token, new Preparada(token, s.id(), nombre, registros.size(), rechazadas.size(), rechazadas, validas,
                Instant.now().plusSeconds(props.importacion().minutosVistaPrevia() * 60L)));
        return new VistaPrevia(token, nombre, registros.size(), validas.size(), rechazadas.size(), vista);
    }

    private void purgar() {
        Instant ahora = Instant.now();
        vistas.values().removeIf(p -> p.vence().isBefore(ahora));
    }

    public void cancelar(Sesion s, String token) {
        Preparada p = vistas.get(token);
        if (p != null && p.usuarioId().equals(s.id())) vistas.remove(token);
    }

    // ------------------------------------------------------------------ confirmación

    /** Todo o nada: si falla una fila, no se guarda ninguna. */
    @Transactional
    public ResultadoImportacion confirmar(Sesion s, String token) {
        purgar();
        Preparada p = token == null ? null : vistas.get(token);
        if (p == null || !p.usuarioId().equals(s.id()))
            throw new ApiException(HttpStatus.GONE, "VISTA_PREVIA_VENCIDA",
                    "La vista previa venció o no existe. Cargue el archivo de nuevo.", null);
        if (p.pendientes().isEmpty())
            throw ApiException.conflicto("SIN_FILAS_VALIDAS", "No hay filas válidas para importar.");
        vistas.remove(token); // evita confirmar dos veces la misma vista previa

        var yo = usuarios.getReferenceById(s.id());
        for (Pendiente f : p.pendientes()) {
            if (f.datos().idCaso() != null && casos.existsByIdCaso(f.datos().idCaso()))
                throw ApiException.conflicto("ID_DUPLICADO", "Mientras tanto se registró un caso con el ID de la fila "
                        + f.fila() + ". No se importó nada; cargue el archivo de nuevo.");
            Sede sede = f.sedeId() == null ? null : sedes.getReferenceById(f.sedeId());
            ClasificacionSiuce cl = f.clasificacionId() == null ? null : clasificaciones.getReferenceById(f.clasificacionId());
            casoService.persistirNuevo(yo, f.datos(), sede, cl, f.rutaActiva(), false);
        }

        ImportacionCsv imp = new ImportacionCsv();
        imp.setUsuario(yo);
        imp.setNombreArchivo(p.archivo());
        imp.setFecha(LocalDateTime.now());
        imp.setFilasTotal(p.total());
        imp.setFilasImportadas(p.pendientes().size());
        imp.setFilasRechazadas(p.rechazadasCount());
        importaciones.save(imp);
        auditoria.registrar(s.id(), null, "IMPORTAR_CSV", "importación " + imp.getId() + ": "
                + imp.getFilasImportadas() + " importadas, " + imp.getFilasRechazadas() + " rechazadas");
        return new ResultadoImportacion(imp.getId(), p.total(), p.pendientes().size(), p.rechazadasCount(), p.rechazadas());
    }
}
