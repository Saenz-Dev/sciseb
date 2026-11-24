package com.sciseb.web;

import com.sciseb.config.ApiException;
import com.sciseb.config.Sesion;
import com.sciseb.dto.Dto.ResultadoImportacion;
import com.sciseb.dto.Dto.VistaPrevia;
import com.sciseb.service.ImportacionService;
import lombok.RequiredArgsConstructor;
import org.springframework.http.ContentDisposition;
import org.springframework.http.HttpHeaders;
import org.springframework.http.HttpStatus;
import org.springframework.http.MediaType;
import org.springframework.http.ResponseEntity;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.multipart.MultipartFile;

import java.io.IOException;
import java.nio.charset.StandardCharsets;
import java.util.Map;

@RestController
@RequestMapping("/api/importaciones")
@RequiredArgsConstructor
public class ImportacionController {
    private final ImportacionService importacion;

    @GetMapping("/plantilla")
    public ResponseEntity<byte[]> plantilla() {
        return ResponseEntity.ok()
                .header(HttpHeaders.CONTENT_DISPOSITION, ContentDisposition.attachment()
                        .filename("plantilla_casos.csv", StandardCharsets.UTF_8).build().toString())
                .contentType(MediaType.parseMediaType("text/csv; charset=UTF-8"))
                .body(importacion.plantillaCsv().getBytes(StandardCharsets.UTF_8));
    }

    @PostMapping(value = "/vista-previa", consumes = MediaType.MULTIPART_FORM_DATA_VALUE)
    public VistaPrevia vistaPrevia(@AuthenticationPrincipal Sesion s, @RequestParam("archivo") MultipartFile archivo) {
        try {
            return importacion.vistaPrevia(s, archivo.getOriginalFilename(), archivo.getBytes());
        } catch (IOException e) {
            throw ApiException.solicitudInvalida("No se pudo leer el archivo.");
        }
    }

    @PostMapping("/confirmar")
    @ResponseStatus(HttpStatus.CREATED)
    public ResultadoImportacion confirmar(@AuthenticationPrincipal Sesion s, @RequestBody Map<String, String> cuerpo) {
        return importacion.confirmar(s, cuerpo.get("token"));
    }

    @DeleteMapping("/vista-previa/{token}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    public void cancelar(@AuthenticationPrincipal Sesion s, @PathVariable String token) {
        importacion.cancelar(s, token);
    }
}
