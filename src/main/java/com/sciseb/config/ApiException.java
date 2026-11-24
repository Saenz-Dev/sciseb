package com.sciseb.config;

import org.springframework.http.HttpStatus;

import java.util.Map;

/** Error de negocio con código HTTP y mensaje en español para la persona usuaria. */
public class ApiException extends RuntimeException {
    private final HttpStatus estado;
    private final String codigo;
    private final transient Map<String, String> errores;

    public ApiException(HttpStatus estado, String codigo, String mensaje, Map<String, String> errores) {
        super(mensaje);
        this.estado = estado;
        this.codigo = codigo;
        this.errores = errores;
    }

    public static ApiException noEncontrado(String mensaje) {
        return new ApiException(HttpStatus.NOT_FOUND, "NO_ENCONTRADO", mensaje, null);
    }

    public static ApiException solicitudInvalida(String mensaje) {
        return new ApiException(HttpStatus.BAD_REQUEST, "SOLICITUD_INVALIDA", mensaje, null);
    }

    public static ApiException validacion(Map<String, String> errores) {
        return new ApiException(HttpStatus.UNPROCESSABLE_ENTITY, "VALIDACION",
                "Hay datos que debe corregir antes de guardar.", errores);
    }

    public static ApiException conflicto(String codigo, String mensaje) {
        return new ApiException(HttpStatus.CONFLICT, codigo, mensaje, null);
    }

    public static ApiException prohibido(String mensaje) {
        return new ApiException(HttpStatus.FORBIDDEN, "PROHIBIDO", mensaje, null);
    }

    public HttpStatus getEstado() { return estado; }
    public String getCodigo() { return codigo; }
    public Map<String, String> getErrores() { return errores; }
}
