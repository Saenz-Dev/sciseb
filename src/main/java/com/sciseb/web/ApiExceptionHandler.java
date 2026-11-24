package com.sciseb.web;

import com.sciseb.config.ApiException;
import com.sciseb.dto.Dto.ErrorRes;
import lombok.extern.slf4j.Slf4j;
import org.springframework.dao.DataIntegrityViolationException;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.http.converter.HttpMessageNotReadableException;
import org.springframework.orm.ObjectOptimisticLockingFailureException;
import org.springframework.security.access.AccessDeniedException;
import org.springframework.web.HttpRequestMethodNotSupportedException;
import org.springframework.web.bind.MissingServletRequestParameterException;
import org.springframework.web.bind.annotation.ExceptionHandler;
import org.springframework.web.bind.annotation.RestControllerAdvice;
import org.springframework.web.method.annotation.MethodArgumentTypeMismatchException;
import org.springframework.web.multipart.MaxUploadSizeExceededException;
import org.springframework.web.multipart.support.MissingServletRequestPartException;
import org.springframework.web.servlet.resource.NoResourceFoundException;

@RestControllerAdvice
@Slf4j
public class ApiExceptionHandler {

    private ResponseEntity<ErrorRes> resp(HttpStatus s, String codigo, String msg) {
        return ResponseEntity.status(s).body(new ErrorRes(codigo, msg, null));
    }

    @ExceptionHandler(ApiException.class)
    public ResponseEntity<ErrorRes> negocio(ApiException e) {
        return ResponseEntity.status(e.getEstado()).body(new ErrorRes(e.getCodigo(), e.getMessage(), e.getErrores()));
    }

    @ExceptionHandler(AccessDeniedException.class)
    public ResponseEntity<ErrorRes> denegado(AccessDeniedException e) {
        return resp(HttpStatus.FORBIDDEN, "PROHIBIDO", "No tiene permiso para realizar esta acción.");
    }

    @ExceptionHandler(ObjectOptimisticLockingFailureException.class)
    public ResponseEntity<ErrorRes> concurrencia(ObjectOptimisticLockingFailureException e) {
        return resp(HttpStatus.CONFLICT, "CONFLICTO_VERSION",
                "Otra persona modificó este caso mientras usted lo editaba. Recargue la página y vuelva a intentarlo.");
    }

    @ExceptionHandler(DataIntegrityViolationException.class)
    public ResponseEntity<ErrorRes> integridad(DataIntegrityViolationException e) {
        log.warn("Violación de integridad: {}", e.getMostSpecificCause().getMessage());
        return resp(HttpStatus.CONFLICT, "INTEGRIDAD",
                "No se pudo guardar porque el dato ya existe o está relacionado con otros registros.");
    }

    @ExceptionHandler(MaxUploadSizeExceededException.class)
    public ResponseEntity<ErrorRes> archivoGrande(MaxUploadSizeExceededException e) {
        return resp(HttpStatus.PAYLOAD_TOO_LARGE, "ARCHIVO_GRANDE", "El archivo supera el tamaño máximo de 5 MB.");
    }

    @ExceptionHandler({HttpMessageNotReadableException.class, MethodArgumentTypeMismatchException.class,
            MissingServletRequestParameterException.class, MissingServletRequestPartException.class})
    public ResponseEntity<ErrorRes> malaSolicitud(Exception e) {
        return resp(HttpStatus.BAD_REQUEST, "SOLICITUD_INVALIDA",
                "La solicitud no es válida. Revise los datos enviados (por ejemplo, el formato de las fechas).");
    }

    @ExceptionHandler(HttpRequestMethodNotSupportedException.class)
    public ResponseEntity<ErrorRes> metodo(HttpRequestMethodNotSupportedException e) {
        return resp(HttpStatus.METHOD_NOT_ALLOWED, "METODO", "Operación no permitida.");
    }

    @ExceptionHandler(NoResourceFoundException.class)
    public ResponseEntity<ErrorRes> recursoNoEncontrado(NoResourceFoundException e) {
        return resp(HttpStatus.NOT_FOUND, "NO_ENCONTRADO", "Recurso no encontrado.");
    }

    @ExceptionHandler(Exception.class)
    public ResponseEntity<ErrorRes> inesperado(Exception e) {
        log.error("Error inesperado", e);
        return resp(HttpStatus.INTERNAL_SERVER_ERROR, "ERROR_INTERNO",
                "Ocurrió un error inesperado. Intente de nuevo en unos minutos.");
    }
}
