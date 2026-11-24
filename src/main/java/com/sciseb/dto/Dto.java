package com.sciseb.dto;

import java.time.LocalDate;
import java.time.LocalDateTime;
import java.util.List;
import java.util.Map;

/** Objetos de entrada y salida de la API REST. */
public final class Dto {
    private Dto() {}

    // ---- autenticación y usuarios ----
    public record LoginReq(String correo, String clave) {}
    public record CambioClaveReq(String claveActual, String claveNueva) {}
    public record UsuarioRes(Integer id, String nombre, String correo, Integer areaId, String area,
                             String rol, String estado, LocalDateTime ultimoAcceso) {}
    public record LoginRes(String token, UsuarioRes usuario, boolean debeCambiarClave) {}
    public record UsuarioReq(String nombre, String correo, Integer areaId, String rol) {}
    public record EstadoReq(String estado) {}
    public record ClaveTemporalRes(UsuarioRes usuario, String claveTemporal) {}

    // ---- catálogos ----
    public record Item(Integer id, String nombre) {}
    public record Catalogos(List<Item> provincias, List<Item> clasificaciones, List<Item> areas, List<Item> pasos) {}
    public record Hijo(Integer id, String nombre, Integer padreId) {}
    public record NombreReq(String nombre, Integer padreId) {}
    public record AgresorRes(String nombre, String cedula, String areaAsignatura) {}

    // ---- casos ----
    public record CasoReq(
            String idCaso, String presuntoAgresor, String cedula, String areaAsignatura,
            List<String> victimas, Integer sedeId, Integer clasificacionId, Boolean rutaActiva,
            String nRadicado, String resolucionSeb, String resolucionRectoral, String ubicacionAgresor,
            LocalDate fechaOcurrencia, String lugarOcurrencia, String disciplinario,
            String decisionComite, String penal, Integer version, Boolean confirmarNombreDistinto) {}

    public record CasoResumen(
            Long id, String idCaso, String presuntoAgresor, String cedula, boolean agresorBorrador,
            List<String> victimas, String institucion, String sede, String municipio, String provincia,
            String clasificacion, boolean rutaActiva, String estado, int pasosEfectuados,
            LocalDateTime fechaUltimaEdicion, String editadoPor) {}

    public record PaginaCasos(List<CasoResumen> contenido, long total, int pagina, int tamano) {}

    public record PasoRes(Integer id, String nombre, boolean efectuado, String usuario, String area,
                          LocalDateTime fecha, String nota) {}

    public record CasoDetalle(
            Long id, String idCaso, String presuntoAgresor, String cedula, boolean agresorBorrador, String areaAsignatura,
            List<String> victimas, Integer provinciaId, String provincia, Integer municipioId, String municipio,
            Integer institucionId, String institucion, Integer sedeId, String sede,
            Integer clasificacionId, String clasificacion, boolean rutaActiva,
            String nRadicado, String resolucionSeb, String resolucionRectoral, String ubicacionAgresor,
            LocalDate fechaOcurrencia, String lugarOcurrencia, String disciplinario,
            String decisionComite, String penal, String estado, Integer version,
            String creadoPor, String creadoPorArea, LocalDateTime fechaCreacion,
            String editadoPor, String editadoPorArea, LocalDateTime fechaUltimaEdicion,
            List<PasoRes> pasos, int pasosEfectuados) {}

    public record MotivoReq(String motivo) {}
    public record RutaReq(Boolean activa) {}
    public record PasoReq(Boolean efectuado, String nota) {}

    // ---- acciones y enlaces ----
    public record TextoReq(String texto) {}
    public record AccionRes(Integer id, String texto, String usuario, String area, Integer areaId,
                            LocalDateTime fechaRegistro, LocalDateTime fechaModificacion, boolean editable) {}
    public record EnlaceReq(String titulo, String url, Boolean confirmarDuplicado) {}
    public record EnlaceRes(Integer id, String titulo, String url, String usuario, String area,
                            LocalDateTime fechaRegistro, boolean eliminable) {}

    // ---- importación CSV ----
    public record FilaImportacion(int fila, String idCaso, boolean valida, List<String> errores) {}
    public record VistaPrevia(String token, String archivo, int total, int validas, int rechazadas,
                              List<FilaImportacion> filas) {}
    public record FilaRechazada(int fila, String idCaso, String motivo) {}
    public record ResultadoImportacion(Integer idImportacion, int total, int importadas, int rechazadas,
                                       List<FilaRechazada> filasRechazadas) {}

    // ---- otros ----
    public record Indicadores(long total, long rutaActiva, long tipoI, long tipoIII, long rutaCompleta) {}
    public record AuditoriaRes(Long id, LocalDateTime fecha, String usuario, String area, String accion,
                               Long casoId, String detalle) {}
    public record PaginaAuditoria(List<AuditoriaRes> contenido, long total, int pagina, int tamano) {}
    public record ErrorRes(String codigo, String mensaje, Map<String, String> errores) {}
}
