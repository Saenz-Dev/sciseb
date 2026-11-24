package com.sciseb.web;

import com.sciseb.dto.Dto.PaginaAuditoria;
import com.sciseb.service.AuditoriaService;
import lombok.RequiredArgsConstructor;
import org.springframework.format.annotation.DateTimeFormat;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;

import java.time.LocalDate;

@RestController
@RequestMapping("/api/auditoria")
@PreAuthorize("hasRole('ADMIN')")
@RequiredArgsConstructor
public class AuditoriaController {
    private final AuditoriaService auditoria;

    @GetMapping
    public PaginaAuditoria consultar(@RequestParam(required = false) Long idCaso,
                                     @RequestParam(required = false) Integer usuarioId,
                                     @RequestParam(required = false) @DateTimeFormat(iso = DateTimeFormat.ISO.DATE) LocalDate desde,
                                     @RequestParam(required = false) @DateTimeFormat(iso = DateTimeFormat.ISO.DATE) LocalDate hasta,
                                     @RequestParam(defaultValue = "0") int pagina,
                                     @RequestParam(defaultValue = "30") int tamano) {
        return auditoria.consultar(idCaso, usuarioId, desde, hasta, pagina, tamano);
    }
}
