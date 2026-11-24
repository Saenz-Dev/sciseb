package com.sciseb.service;

import com.sciseb.domain.Caso;
import com.sciseb.domain.EstadoCaso;
import jakarta.persistence.criteria.Join;
import jakarta.persistence.criteria.JoinType;
import jakarta.persistence.criteria.Predicate;
import org.springframework.data.jpa.domain.Specification;

import java.util.ArrayList;
import java.util.List;
import java.util.Locale;

public final class CasoSpecs {
    private CasoSpecs() {}

    private static String escapar(String s) {
        return s.replace("\\", "\\\\").replace("%", "\\%").replace("_", "\\_");
    }

    public static Specification<Caso> desde(CasoFiltro f) {
        return (root, query, cb) -> {
            List<Predicate> ps = new ArrayList<>();
            // LEFT JOIN: un caso sin agresor o sin sede asignados todavía debe seguir apareciendo en el listado.
            Join<Object, Object> agresor = root.join("agresor", JoinType.LEFT);
            Join<Object, Object> sede = root.join("sede", JoinType.LEFT);
            Join<Object, Object> ie = sede.join("institucion", JoinType.LEFT);
            Join<Object, Object> mun = ie.join("municipio", JoinType.LEFT);

            if (f.q() != null && !f.q().isBlank()) {
                String like = "%" + escapar(f.q().trim().toLowerCase(Locale.ROOT)) + "%";
                ps.add(cb.or(
                        cb.like(cb.lower(root.get("idCaso")), like, '\\'),
                        cb.like(cb.lower(agresor.get("nombre")), like, '\\'),
                        cb.like(cb.lower(root.get("nombreAgresorBorrador")), like, '\\'),
                        cb.like(agresor.get("cedula"), like, '\\'),
                        cb.like(cb.lower(ie.get("nombre")), like, '\\'),
                        cb.like(cb.lower(mun.get("nombre")), like, '\\'),
                        cb.like(cb.lower(root.get("nRadicado")), like, '\\')));
            }
            if (f.provinciaId() != null) ps.add(cb.equal(mun.get("provincia").get("id"), f.provinciaId()));
            if (f.clasificacionId() != null) ps.add(cb.equal(root.get("clasificacion").get("id"), f.clasificacionId()));
            if ("activa".equalsIgnoreCase(f.ruta())) ps.add(cb.isTrue(root.get("rutaActiva")));
            if ("inactiva".equalsIgnoreCase(f.ruta())) ps.add(cb.isFalse(root.get("rutaActiva")));
            // Borrador: presunto agresor con nombre escrito pero sin cédula todavía (ver Caso.nombreAgresorBorrador).
            if (Boolean.TRUE.equals(f.borrador()))
                ps.add(cb.and(cb.isNull(root.get("agresor")), cb.isNotNull(root.get("nombreAgresorBorrador"))));

            String est = f.estado() == null || f.estado().isBlank() ? "ACTIVO" : f.estado().toUpperCase(Locale.ROOT);
            if (!"TODOS".equals(est)) {
                ps.add(cb.equal(root.get("estado"), "ARCHIVADO".equals(est) ? EstadoCaso.ARCHIVADO : EstadoCaso.ACTIVO));
            }
            return cb.and(ps.toArray(new Predicate[0]));
        };
    }
}
