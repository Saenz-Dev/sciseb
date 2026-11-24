package com.sciseb.service;

import com.sciseb.config.ApiException;
import com.sciseb.config.Sesion;
import com.sciseb.domain.AccionCaso;
import com.sciseb.domain.Caso;
import com.sciseb.domain.EnlaceEvidencia;
import com.sciseb.domain.EstadoCaso;
import com.sciseb.dto.Dto.*;
import com.sciseb.repo.AccionRepository;
import com.sciseb.repo.CasoRepository;
import com.sciseb.repo.EnlaceRepository;
import com.sciseb.repo.UsuarioRepository;
import com.sciseb.util.CasoValidador;
import lombok.RequiredArgsConstructor;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.net.URI;
import java.time.LocalDateTime;
import java.util.List;
import java.util.Map;

/** Historial de acciones y enlaces de evidencia (solo enlaces https; nunca se suben archivos). */
@Service
@RequiredArgsConstructor
public class AccionEnlaceService {
    private final CasoRepository casos;
    private final AccionRepository acciones;
    private final EnlaceRepository enlaces;
    private final UsuarioRepository usuarios;
    private final AuditoriaService auditoria;

    private Caso casoActivo(Long id) {
        Caso c = casos.findById(id).orElseThrow(() -> ApiException.noEncontrado("No existe el caso " + id + "."));
        if (c.getEstado() == EstadoCaso.ARCHIVADO)
            throw ApiException.conflicto("CASO_ARCHIVADO", "El caso está archivado y no se puede modificar.");
        return c;
    }

    // ------------------------------------------------------------------ acciones

    @Transactional(readOnly = true)
    public List<AccionRes> listarAcciones(Sesion s, Long idCaso) {
        Caso c = casos.findById(idCaso).orElseThrow(() -> ApiException.noEncontrado("No existe el caso " + idCaso + "."));
        boolean abierto = c.getEstado() == EstadoCaso.ACTIVO;
        return acciones.listarPorCaso(idCaso).stream().map(a -> new AccionRes(a.getId(), a.getTexto(),
                a.getUsuario().getNombre(), a.getUsuario().getArea().getNombre(), a.getUsuario().getArea().getId(),
                a.getFechaRegistro(), a.getFechaModificacion(),
                abierto && a.getUsuario().getArea().getId().equals(s.areaId()))).toList();
    }

    private static String textoValido(String t) {
        String texto = t == null ? null : t.trim();
        if (texto == null || texto.isEmpty() || texto.length() > 2000)
            throw ApiException.validacion(Map.of("texto", "Escriba la acción realizada (máximo 2000 caracteres)."));
        return texto;
    }

    @Transactional
    public List<AccionRes> crearAccion(Sesion s, Long idCaso, TextoReq r) {
        String texto = textoValido(r.texto());
        Caso c = casoActivo(idCaso);
        AccionCaso a = new AccionCaso();
        a.setCaso(c);
        a.setUsuario(usuarios.getReferenceById(s.id()));
        a.setTexto(texto);
        a.setFechaRegistro(LocalDateTime.now());
        acciones.saveAndFlush(a);
        auditoria.registrar(s.id(), idCaso, "REGISTRAR_ACCION", "acción " + a.getId());
        return listarAcciones(s, idCaso);
    }

    @Transactional
    public List<AccionRes> editarAccion(Sesion s, Integer idAccion, TextoReq r) {
        String texto = textoValido(r.texto());
        AccionCaso a = acciones.findById(idAccion).orElseThrow(() -> ApiException.noEncontrado("No existe la acción."));
        Long idCaso = a.getCaso().getId();
        casoActivo(idCaso);
        if (!a.getUsuario().getArea().getId().equals(s.areaId()))
            throw ApiException.prohibido("Solo el área que registró la acción puede modificarla.");
        a.setTexto(texto);
        a.setFechaModificacion(LocalDateTime.now());
        acciones.saveAndFlush(a);
        auditoria.registrar(s.id(), idCaso, "EDITAR_ACCION", "acción " + idAccion);
        return listarAcciones(s, idCaso);
    }

    // ------------------------------------------------------------------ enlaces

    @Transactional(readOnly = true)
    public List<EnlaceRes> listarEnlaces(Sesion s, Long idCaso) {
        Caso c = casos.findById(idCaso).orElseThrow(() -> ApiException.noEncontrado("No existe el caso " + idCaso + "."));
        boolean abierto = c.getEstado() == EstadoCaso.ACTIVO;
        return enlaces.listarActivosPorCaso(idCaso).stream().map(e -> new EnlaceRes(e.getId(), e.getTitulo(), e.getUrl(),
                e.getUsuario().getNombre(), e.getUsuario().getArea().getNombre(), e.getFechaRegistro(),
                abierto && e.getUsuario().getArea().getId().equals(s.areaId()))).toList();
    }

    /** Solo https, con servidor y sin usuario ni clave dentro de la dirección. */
    static String urlValida(String u) {
        String url = u == null ? "" : u.trim();
        String error = "Escriba un enlace que empiece por https:// (por ejemplo, la carpeta compartida de la institución).";
        if (url.isEmpty() || url.length() > 500 || url.chars().anyMatch(Character::isWhitespace))
            throw ApiException.validacion(Map.of("url", error));
        try {
            URI uri = new URI(url);
            if (!"https".equalsIgnoreCase(uri.getScheme()) || uri.getHost() == null || uri.getUserInfo() != null)
                throw ApiException.validacion(Map.of("url", error));
        } catch (java.net.URISyntaxException e) {
            throw ApiException.validacion(Map.of("url", error));
        }
        return url;
    }

    @Transactional
    public List<EnlaceRes> crearEnlace(Sesion s, Long idCaso, EnlaceReq r) {
        String titulo = CasoValidador.limpiar(r.titulo());
        if (titulo == null || titulo.length() > 150)
            throw ApiException.validacion(Map.of("titulo", "Escriba el nombre del documento (máximo 150 caracteres)."));
        String url = urlValida(r.url());
        Caso c = casoActivo(idCaso);
        if (!Boolean.TRUE.equals(r.confirmarDuplicado()) && enlaces.existsByCasoIdAndUrlAndActivoTrue(idCaso, url)) {
            throw ApiException.conflicto("ENLACE_DUPLICADO", "Este enlace ya está registrado en el caso. ¿Desea agregarlo de nuevo?");
        }
        EnlaceEvidencia e = new EnlaceEvidencia();
        e.setCaso(c);
        e.setUsuario(usuarios.getReferenceById(s.id()));
        e.setTitulo(titulo);
        e.setUrl(url);
        e.setActivo(true);
        e.setFechaRegistro(LocalDateTime.now());
        enlaces.saveAndFlush(e);
        auditoria.registrar(s.id(), idCaso, "AGREGAR_ENLACE", "enlace " + e.getId());
        return listarEnlaces(s, idCaso);
    }

    @Transactional
    public List<EnlaceRes> quitarEnlace(Sesion s, Integer idEnlace) {
        EnlaceEvidencia e = enlaces.findById(idEnlace).orElseThrow(() -> ApiException.noEncontrado("No existe el enlace."));
        Long idCaso = e.getCaso().getId();
        casoActivo(idCaso);
        if (!e.getUsuario().getArea().getId().equals(s.areaId()))
            throw ApiException.prohibido("Solo el área que agregó el enlace puede retirarlo.");
        e.setActivo(false);
        enlaces.saveAndFlush(e);
        auditoria.registrar(s.id(), idCaso, "QUITAR_ENLACE", "enlace " + idEnlace);
        return listarEnlaces(s, idCaso);
    }
}
