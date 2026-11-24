package com.sciseb.web;

import com.sciseb.config.Sesion;
import com.sciseb.dto.Dto.*;
import com.sciseb.service.UsuarioService;
import lombok.RequiredArgsConstructor;
import org.springframework.http.HttpStatus;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.web.bind.annotation.*;

import java.util.List;

@RestController
@RequestMapping("/api/usuarios")
@PreAuthorize("hasRole('ADMIN')")
@RequiredArgsConstructor
public class UsuarioController {
    private final UsuarioService usuarios;

    @GetMapping
    public List<UsuarioRes> listar() {
        return usuarios.listar();
    }

    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    public ClaveTemporalRes crear(@AuthenticationPrincipal Sesion s, @RequestBody UsuarioReq r) {
        return usuarios.crear(s, r);
    }

    @PatchMapping("/{id}/estado")
    public UsuarioRes estado(@AuthenticationPrincipal Sesion s, @PathVariable Integer id, @RequestBody EstadoReq r) {
        return usuarios.cambiarEstado(s, id, r.estado());
    }

    @PostMapping("/{id}/restablecer-clave")
    public ClaveTemporalRes restablecer(@AuthenticationPrincipal Sesion s, @PathVariable Integer id) {
        return usuarios.restablecerClave(s, id);
    }
}
