package com.sciseb.service;

import com.sciseb.config.ApiException;
import com.sciseb.config.Sesion;
import com.sciseb.domain.InstitucionEducativa;
import com.sciseb.domain.Municipio;
import com.sciseb.domain.Sede;
import com.sciseb.dto.Dto.Catalogos;
import com.sciseb.dto.Dto.Hijo;
import com.sciseb.dto.Dto.Item;
import com.sciseb.repo.*;
import lombok.RequiredArgsConstructor;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.util.List;

@Service
@RequiredArgsConstructor
public class CatalogoService {
    private final ProvinciaRepository provincias;
    private final ClasificacionRepository clasificaciones;
    private final AreaRepository areas;
    private final PasoRutaRepository pasos;
    private final MunicipioRepository municipios;
    private final InstitucionRepository instituciones;
    private final SedeRepository sedes;
    private final AuditoriaService auditoria;

    @Transactional(readOnly = true)
    public Catalogos catalogos() {
        return new Catalogos(
                provincias.findAllByOrderByNombreAsc().stream().map(p -> new Item(p.getId(), p.getNombre())).toList(),
                clasificaciones.findAllByOrderByIdAsc().stream().map(c -> new Item(c.getId(), c.getNombre())).toList(),
                areas.findAllByOrderByIdAsc().stream().map(a -> new Item(a.getId(), a.getNombre())).toList(),
                pasos.findAllByOrderByIdAsc().stream().map(p -> new Item(p.getId(), p.getNombre())).toList());
    }

    @Transactional(readOnly = true)
    public List<Hijo> municipios(Integer provinciaId) {
        return municipios.findByProvinciaIdOrderByNombreAsc(provinciaId).stream()
                .map(m -> new Hijo(m.getId(), m.getNombre(), provinciaId)).toList();
    }

    @Transactional(readOnly = true)
    public List<Hijo> instituciones(Integer municipioId) {
        return instituciones.findByMunicipioIdOrderByNombreAsc(municipioId).stream()
                .map(i -> new Hijo(i.getId(), i.getNombre(), municipioId)).toList();
    }

    @Transactional(readOnly = true)
    public List<Hijo> sedes(Integer institucionId) {
        return sedes.findByInstitucionIdOrderByNombreAsc(institucionId).stream()
                .map(s -> new Hijo(s.getId(), s.getNombre(), institucionId)).toList();
    }

    private static String nombreValido(String n) {
        String t = n == null ? "" : n.trim().replaceAll("\\s+", " ");
        if (t.length() < 2 || t.length() > 150) throw ApiException.solicitudInvalida("Escriba un nombre de 2 a 150 caracteres.");
        return t;
    }

    @Transactional
    public Hijo crearMunicipio(Sesion s, String nombre, Integer provinciaId) {
        String n = nombreValido(nombre);
        var prov = provincias.findById(provinciaId).orElseThrow(() -> ApiException.noEncontrado("La provincia no existe."));
        if (municipios.findByNombreIgnoreCaseAndProvinciaId(n, provinciaId).isPresent())
            throw ApiException.conflicto("DUPLICADO", "Ese municipio ya existe en la provincia.");
        Municipio m = new Municipio();
        m.setNombre(n);
        m.setProvincia(prov);
        municipios.save(m);
        auditoria.registrar(s.id(), null, "CREAR_CATALOGO", "municipio " + m.getId());
        return new Hijo(m.getId(), m.getNombre(), provinciaId);
    }

    @Transactional
    public Hijo crearInstitucion(Sesion s, String nombre, Integer municipioId) {
        String n = nombreValido(nombre);
        var mun = municipios.findById(municipioId).orElseThrow(() -> ApiException.noEncontrado("El municipio no existe."));
        if (instituciones.findByNombreIgnoreCaseAndMunicipioId(n, municipioId).isPresent())
            throw ApiException.conflicto("DUPLICADO", "Esa institución ya existe en el municipio.");
        InstitucionEducativa i = new InstitucionEducativa();
        i.setNombre(n);
        i.setMunicipio(mun);
        instituciones.save(i);
        auditoria.registrar(s.id(), null, "CREAR_CATALOGO", "institución " + i.getId());
        return new Hijo(i.getId(), i.getNombre(), municipioId);
    }

    @Transactional
    public Hijo crearSede(Sesion s, String nombre, Integer institucionId) {
        String n = nombreValido(nombre);
        var ie = instituciones.findById(institucionId).orElseThrow(() -> ApiException.noEncontrado("La institución no existe."));
        if (sedes.findByNombreIgnoreCaseAndInstitucionId(n, institucionId).isPresent())
            throw ApiException.conflicto("DUPLICADO", "Esa sede ya existe en la institución.");
        Sede sd = new Sede();
        sd.setNombre(n);
        sd.setInstitucion(ie);
        sedes.save(sd);
        auditoria.registrar(s.id(), null, "CREAR_CATALOGO", "sede " + sd.getId());
        return new Hijo(sd.getId(), sd.getNombre(), institucionId);
    }

    @Transactional
    public Hijo renombrarMunicipio(Sesion s, Integer id, String nombre) {
        Municipio m = municipios.findById(id).orElseThrow(() -> ApiException.noEncontrado("El municipio no existe."));
        m.setNombre(nombreValido(nombre));
        auditoria.registrar(s.id(), null, "EDITAR_CATALOGO", "municipio " + id);
        return new Hijo(m.getId(), m.getNombre(), m.getProvincia().getId());
    }

    @Transactional
    public Hijo renombrarInstitucion(Sesion s, Integer id, String nombre) {
        InstitucionEducativa i = instituciones.findById(id).orElseThrow(() -> ApiException.noEncontrado("La institución no existe."));
        i.setNombre(nombreValido(nombre));
        auditoria.registrar(s.id(), null, "EDITAR_CATALOGO", "institución " + id);
        return new Hijo(i.getId(), i.getNombre(), i.getMunicipio().getId());
    }

    @Transactional
    public Hijo renombrarSede(Sesion s, Integer id, String nombre) {
        Sede sd = sedes.findById(id).orElseThrow(() -> ApiException.noEncontrado("La sede no existe."));
        sd.setNombre(nombreValido(nombre));
        auditoria.registrar(s.id(), null, "EDITAR_CATALOGO", "sede " + id);
        return new Hijo(sd.getId(), sd.getNombre(), sd.getInstitucion().getId());
    }

    @Transactional
    public Item renombrarArea(Sesion s, Integer id, String nombre) {
        var a = areas.findById(id).orElseThrow(() -> ApiException.noEncontrado("El área no existe."));
        String n = nombreValido(nombre);
        if (n.length() > 80) throw ApiException.solicitudInvalida("El nombre del área no puede superar 80 caracteres.");
        a.setNombre(n);
        auditoria.registrar(s.id(), null, "EDITAR_CATALOGO", "área " + id);
        return new Item(a.getId(), a.getNombre());
    }
}
