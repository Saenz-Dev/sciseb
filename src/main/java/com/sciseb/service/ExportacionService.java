package com.sciseb.service;

import com.sciseb.config.ApiException;
import com.sciseb.config.AppProperties;
import com.sciseb.config.Sesion;
import com.sciseb.domain.Caso;
import com.sciseb.domain.Victima;
import com.sciseb.repo.CasoRepository;
import com.sciseb.util.CasoValidador;
import lombok.RequiredArgsConstructor;
import org.apache.commons.csv.CSVFormat;
import org.apache.commons.csv.CSVPrinter;
import org.apache.poi.ss.usermodel.*;
import org.apache.poi.xssf.usermodel.XSSFWorkbook;
import org.springframework.data.domain.Sort;
import org.springframework.http.HttpStatus;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.io.OutputStreamWriter;
import java.nio.charset.StandardCharsets;
import java.time.format.DateTimeFormatter;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;

@Service
@RequiredArgsConstructor
public class ExportacionService {
    private static final List<String> ENCABEZADOS = List.of("ID del caso", "Presunto agresor", "Cédula",
            "Presunta víctima", "Institución educativa", "Sede", "Área / asignatura", "Municipio", "Provincia",
            "Clasificación SIUCE", "Ruta", "N° de radicado", "Resolución SEB", "Resolución rectoral", "Ubicación",
            "Fecha de ocurrencia", "Lugar de ocurrencia", "Disciplinario", "Decisión del comité", "Penal", "Estado",
            "Pasos efectuados (de 12)", "Creado por", "Fecha de creación", "Editado por", "Fecha de última edición");
    private static final DateTimeFormatter FH = DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm");

    public record Archivo(byte[] bytes, String nombre, String tipo) {}

    private final CasoRepository casos;
    private final CasoService casoService;
    private final AppProperties props;
    private final AuditoriaService auditoria;

    @Transactional // no es de solo lectura: registra la exportación en la auditoría
    public Archivo exportar(Sesion s, CasoFiltro filtro, String formato) {
        boolean xlsx = "xlsx".equalsIgnoreCase(formato);
        if (!xlsx && !"csv".equalsIgnoreCase(formato))
            throw ApiException.solicitudInvalida("El formato debe ser csv o xlsx.");
        var spec = CasoSpecs.desde(filtro);
        long total = casos.count(spec);
        int max = props.exportacion().maxFilas();
        if (total > max)
            throw new ApiException(HttpStatus.UNPROCESSABLE_ENTITY, "DEMASIADOS_CASOS",
                    "Hay " + total + " casos con estos filtros y el máximo por archivo es " + max + ". Aplique más filtros.", null);

        List<Caso> lista = casos.findAll(spec, Sort.by(Sort.Direction.ASC, "id"));
        Map<Long, Integer> efectuados = casoService.contarEfectuados(lista.stream().map(Caso::getId).toList());
        List<List<String>> filas = new ArrayList<>();
        for (Caso c : lista) {
            var agresor = c.getAgresor();
            var sede = c.getSede();
            var ie = sede == null ? null : sede.getInstitucion();
            var mun = ie == null ? null : ie.getMunicipio();
            var cl = c.getClasificacion();
            // La cédula completa solo la exporta el Administrador; las demás áreas la reciben enmascarada.
            String cedula = agresor == null ? null
                    : (s.esAdmin() ? agresor.getCedula() : CasoValidador.enmascararCedula(agresor.getCedula()));
            String victimas = String.join("; ", c.getVictimas().stream().map(Victima::getIniciales).toList());
            filas.add(List.of(n(c.getIdCaso()), n(agresor != null ? agresor.getNombre() : c.getNombreAgresorBorrador()), n(cedula), victimas,
                    n(ie == null ? null : ie.getNombre()), n(sede == null ? null : sede.getNombre()),
                    n(agresor == null ? null : agresor.getAreaAsignatura()), n(mun == null ? null : mun.getNombre()),
                    n(mun == null ? null : mun.getProvincia().getNombre()), n(cl == null ? null : cl.getNombre()),
                    c.isRutaActiva() ? "Activa" : "Inactiva",
                    n(c.getNRadicado()), n(c.getResolucionSeb()), n(c.getResolucionRectoral()), n(c.getUbicacionAgresor()),
                    n(c.getFechaOcurrencia() == null ? null : c.getFechaOcurrencia().toString()), n(c.getLugarOcurrencia()), n(c.getDisciplinario()),
                    n(c.getDecisionComite()), n(c.getPenal()), c.getEstado().name(),
                    String.valueOf(efectuados.getOrDefault(c.getId(), 0)), c.getCreadoPor().getNombre(),
                    c.getFechaCreacion().format(FH), c.getEditadoPor().getNombre(), c.getFechaUltimaEdicion().format(FH)));
        }
        auditoria.registrar(s.id(), null, "EXPORTAR", filas.size() + " casos, " + (xlsx ? "xlsx" : "csv"));
        String base = "casos_siuce_" + java.time.LocalDate.now().toString().replace("-", "");
        try {
            return xlsx
                    ? new Archivo(excel(filas), base + ".xlsx", "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet")
                    : new Archivo(csv(filas), base + ".csv", "text/csv; charset=UTF-8");
        } catch (IOException e) {
            throw new ApiException(HttpStatus.INTERNAL_SERVER_ERROR, "ERROR_EXPORTACION", "No se pudo generar el archivo.", null);
        }
    }

    private static String n(String s) {
        return s == null ? "" : CasoValidador.neutralizarFormula(s);
    }

    private static byte[] csv(List<List<String>> filas) throws IOException {
        ByteArrayOutputStream out = new ByteArrayOutputStream();
        out.write(new byte[]{(byte) 0xEF, (byte) 0xBB, (byte) 0xBF}); // BOM para que Excel lea las tildes
        try (OutputStreamWriter w = new OutputStreamWriter(out, StandardCharsets.UTF_8);
             CSVPrinter p = new CSVPrinter(w, CSVFormat.DEFAULT.builder().setDelimiter(';').setRecordSeparator("\r\n").build())) {
            p.printRecord(ENCABEZADOS);
            for (List<String> f : filas) p.printRecord(f);
        }
        return out.toByteArray();
    }

    private static byte[] excel(List<List<String>> filas) throws IOException {
        try (Workbook wb = new XSSFWorkbook(); ByteArrayOutputStream out = new ByteArrayOutputStream()) {
            Sheet hoja = wb.createSheet("Casos");
            CellStyle negrita = wb.createCellStyle();
            Font f = wb.createFont();
            f.setBold(true);
            negrita.setFont(f);
            Row h = hoja.createRow(0);
            for (int i = 0; i < ENCABEZADOS.size(); i++) {
                Cell c = h.createCell(i);
                c.setCellValue(ENCABEZADOS.get(i));
                c.setCellStyle(negrita);
            }
            for (int r = 0; r < filas.size(); r++) {
                Row row = hoja.createRow(r + 1);
                List<String> fila = filas.get(r);
                for (int i = 0; i < fila.size(); i++) row.createCell(i).setCellValue(fila.get(i));
            }
            for (int i = 0; i < ENCABEZADOS.size(); i++) hoja.setColumnWidth(i, 18 * 256);
            hoja.createFreezePane(0, 1);
            wb.write(out);
            return out.toByteArray();
        }
    }
}
