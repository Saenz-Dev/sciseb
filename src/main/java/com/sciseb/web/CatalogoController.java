package com.sciseb.web;

import com.sciseb.config.Sesion;
import com.sciseb.dto.Dto.*;
import com.sciseb.service.CatalogoService;
import lombok.RequiredArgsConstructor;
import org.springframework.http.HttpStatus;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.web.bind.annotation.*;

import java.util.List;

@RestController
@RequestMapping("/api/catalogos")
@RequiredArgsConstructor
public class CatalogoController {
    private final CatalogoService catalogo;

    @GetMapping
    public Catalogos todos() {
        return catalogo.catalogos();
    }

    @GetMapping("/municipios")
    public List<Hijo> municipios(@RequestParam Integer provinciaId) {
        return catalogo.municipios(provinciaId);
    }

    @GetMapping("/instituciones")
    public List<Hijo> instituciones(@RequestParam Integer municipioId) {
        return catalogo.instituciones(municipioId);
    }

    @GetMapping("/sedes")
    public List<Hijo> sedes(@RequestParam Integer institucionId) {
        return catalogo.sedes(institucionId);
    }

    @PostMapping("/municipios")
    @PreAuthorize("hasRole('ADMIN')")
    @ResponseStatus(HttpStatus.CREATED)
    public Hijo crearMunicipio(@AuthenticationPrincipal Sesion s, @RequestBody NombreReq r) {
        return catalogo.crearMunicipio(s, r.nombre(), r.padreId());
    }

    @PostMapping("/instituciones")
    @PreAuthorize("hasRole('ADMIN')")
    @ResponseStatus(HttpStatus.CREATED)
    public Hijo crearInstitucion(@AuthenticationPrincipal Sesion s, @RequestBody NombreReq r) {
        return catalogo.crearInstitucion(s, r.nombre(), r.padreId());
    }

    @PostMapping("/sedes")
    @PreAuthorize("hasRole('ADMIN')")
    @ResponseStatus(HttpStatus.CREATED)
    public Hijo crearSede(@AuthenticationPrincipal Sesion s, @RequestBody NombreReq r) {
        return catalogo.crearSede(s, r.nombre(), r.padreId());
    }

    @PutMapping("/municipios/{id}")
    @PreAuthorize("hasRole('ADMIN')")
    public Hijo renombrarMunicipio(@AuthenticationPrincipal Sesion s, @PathVariable Integer id, @RequestBody NombreReq r) {
        return catalogo.renombrarMunicipio(s, id, r.nombre());
    }

    @PutMapping("/instituciones/{id}")
    @PreAuthorize("hasRole('ADMIN')")
    public Hijo renombrarInstitucion(@AuthenticationPrincipal Sesion s, @PathVariable Integer id, @RequestBody NombreReq r) {
        return catalogo.renombrarInstitucion(s, id, r.nombre());
    }

    @PutMapping("/sedes/{id}")
    @PreAuthorize("hasRole('ADMIN')")
    public Hijo renombrarSede(@AuthenticationPrincipal Sesion s, @PathVariable Integer id, @RequestBody NombreReq r) {
        return catalogo.renombrarSede(s, id, r.nombre());
    }

    @PutMapping("/areas/{id}")
    @PreAuthorize("hasRole('ADMIN')")
    public Item renombrarArea(@AuthenticationPrincipal Sesion s, @PathVariable Integer id, @RequestBody NombreReq r) {
        return catalogo.renombrarArea(s, id, r.nombre());
    }
}
