package com.sciseb.web;

import com.sciseb.config.Sesion;
import com.sciseb.dto.Dto.*;
import com.sciseb.service.*;
import lombok.RequiredArgsConstructor;
import org.springframework.http.ContentDisposition;
import org.springframework.http.HttpHeaders;
import org.springframework.http.HttpStatus;
import org.springframework.http.MediaType;
import org.springframework.http.ResponseEntity;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.web.bind.annotation.*;

import java.nio.charset.StandardCharsets;
import java.util.List;

@RestController
@RequestMapping("/api")
@RequiredArgsConstructor
public class CasoController {
    private final CasoService casos;
    private final AccionEnlaceService accionesEnlaces;
    private final ExportacionService exportacion;

    @GetMapping("/indicadores")
    public Indicadores indicadores() {
        return casos.indicadores();
    }

    @GetMapping("/casos")
    public PaginaCasos listar(@RequestParam(required = false) String q,
                              @RequestParam(required = false) Integer provinciaId,
                              @RequestParam(required = false) Integer clasificacionId,
                              @RequestParam(required = false) String ruta,
                              @RequestParam(required = false) String estado,
                              @RequestParam(required = false) Boolean borrador,
                              @RequestParam(defaultValue = "0") int pagina,
                              @RequestParam(defaultValue = "20") int tamano) {
        return casos.listar(new CasoFiltro(q, provinciaId, clasificacionId, ruta, estado, borrador), pagina, tamano);
    }

    @GetMapping("/casos/exportar")
    public ResponseEntity<byte[]> exportar(@AuthenticationPrincipal Sesion s,
                                           @RequestParam(required = false) String q,
                                           @RequestParam(required = false) Integer provinciaId,
                                           @RequestParam(required = false) Integer clasificacionId,
                                           @RequestParam(required = false) String ruta,
                                           @RequestParam(required = false) String estado,
                                           @RequestParam(required = false) Boolean borrador,
                                           @RequestParam(defaultValue = "csv") String formato) {
        var a = exportacion.exportar(s, new CasoFiltro(q, provinciaId, clasificacionId, ruta, estado, borrador), formato);
        return ResponseEntity.ok()
                .header(HttpHeaders.CONTENT_DISPOSITION, ContentDisposition.attachment()
                        .filename(a.nombre(), StandardCharsets.UTF_8).build().toString())
                .contentType(MediaType.parseMediaType(a.tipo()))
                .body(a.bytes());
    }

    @GetMapping("/casos/{id}")
    public CasoDetalle obtener(@PathVariable Long id) {
        return casos.obtener(id);
    }

    /** Autocompletado del formulario: busca un presunto agresor ya registrado por cédula o por nombre. */
    @GetMapping("/agresores/buscar")
    public AgresorRes buscarAgresor(@RequestParam(required = false) String cedula,
                                    @RequestParam(required = false) String nombre) {
        return casos.buscarAgresor(com.sciseb.util.CasoValidador.limpiar(cedula), com.sciseb.util.CasoValidador.limpiar(nombre));
    }

    @PostMapping("/casos")
    @ResponseStatus(HttpStatus.CREATED)
    public CasoDetalle crear(@AuthenticationPrincipal Sesion s, @RequestBody CasoReq r) {
        return casos.crear(s, r);
    }

    @PutMapping("/casos/{id}")
    public CasoDetalle actualizar(@AuthenticationPrincipal Sesion s, @PathVariable Long id, @RequestBody CasoReq r) {
        return casos.actualizar(s, id, r);
    }

    @PostMapping("/casos/{id}/archivar")
    @PreAuthorize("hasRole('ADMIN')")
    public CasoDetalle archivar(@AuthenticationPrincipal Sesion s, @PathVariable Long id, @RequestBody MotivoReq r) {
        return casos.archivar(s, id, r.motivo());
    }

    @PostMapping("/casos/{id}/reactivar")
    @PreAuthorize("hasRole('ADMIN')")
    public CasoDetalle reactivar(@AuthenticationPrincipal Sesion s, @PathVariable Long id, @RequestBody MotivoReq r) {
        return casos.reactivar(s, id, r.motivo());
    }

    // ---- ruta de atención ----
    @PutMapping("/casos/{id}/ruta")
    public CasoDetalle ruta(@AuthenticationPrincipal Sesion s, @PathVariable Long id, @RequestBody RutaReq r) {
        return casos.cambiarRuta(s, id, Boolean.TRUE.equals(r.activa()));
    }

    @PutMapping("/casos/{id}/pasos/{numero}")
    public CasoDetalle paso(@AuthenticationPrincipal Sesion s, @PathVariable Long id,
                            @PathVariable int numero, @RequestBody PasoReq r) {
        return casos.actualizarPaso(s, id, numero, r);
    }

    // ---- acciones ----
    @GetMapping("/casos/{id}/acciones")
    public List<AccionRes> acciones(@AuthenticationPrincipal Sesion s, @PathVariable Long id) {
        return accionesEnlaces.listarAcciones(s, id);
    }

    @PostMapping("/casos/{id}/acciones")
    @ResponseStatus(HttpStatus.CREATED)
    public List<AccionRes> crearAccion(@AuthenticationPrincipal Sesion s, @PathVariable Long id, @RequestBody TextoReq r) {
        return accionesEnlaces.crearAccion(s, id, r);
    }

    @PutMapping("/acciones/{idAccion}")
    public List<AccionRes> editarAccion(@AuthenticationPrincipal Sesion s, @PathVariable Integer idAccion, @RequestBody TextoReq r) {
        return accionesEnlaces.editarAccion(s, idAccion, r);
    }

    // ---- enlaces de evidencia ----
    @GetMapping("/casos/{id}/enlaces")
    public List<EnlaceRes> enlaces(@AuthenticationPrincipal Sesion s, @PathVariable Long id) {
        return accionesEnlaces.listarEnlaces(s, id);
    }

    @PostMapping("/casos/{id}/enlaces")
    @ResponseStatus(HttpStatus.CREATED)
    public List<EnlaceRes> crearEnlace(@AuthenticationPrincipal Sesion s, @PathVariable Long id, @RequestBody EnlaceReq r) {
        return accionesEnlaces.crearEnlace(s, id, r);
    }

    @DeleteMapping("/enlaces/{idEnlace}")
    public List<EnlaceRes> quitarEnlace(@AuthenticationPrincipal Sesion s, @PathVariable Integer idEnlace) {
        return accionesEnlaces.quitarEnlace(s, idEnlace);
    }
}
