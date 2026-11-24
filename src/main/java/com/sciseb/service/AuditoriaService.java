package com.sciseb.service;

import com.sciseb.domain.Auditoria;
import com.sciseb.dto.Dto.AuditoriaRes;
import com.sciseb.dto.Dto.PaginaAuditoria;
import com.sciseb.repo.AuditoriaRepository;
import com.sciseb.repo.UsuarioRepository;
import jakarta.persistence.criteria.Predicate;
import lombok.RequiredArgsConstructor;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.PageRequest;
import org.springframework.data.domain.Sort;
import org.springframework.data.jpa.domain.Specification;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.time.LocalDate;
import java.time.LocalDateTime;
import java.util.ArrayList;
import java.util.List;

/** El detalle de la auditoría nunca debe incluir datos personales: solo ids y nombres de campos. */
@Service
@RequiredArgsConstructor
public class AuditoriaService {
    private final AuditoriaRepository repo;
    private final UsuarioRepository usuarios;

    @Transactional
    public void registrar(Integer idUsuario, Long casoId, String accion, String detalle) {
        Auditoria a = new Auditoria();
        a.setUsuario(usuarios.getReferenceById(idUsuario));
        a.setCasoId(casoId);
        a.setAccion(accion);
        a.setDetalle(detalle);
        a.setFecha(LocalDateTime.now());
        repo.save(a);
    }

    @Transactional(readOnly = true)
    public PaginaAuditoria consultar(Long casoId, Integer usuarioId, LocalDate desde, LocalDate hasta,
                                     int pagina, int tamano) {
        Specification<Auditoria> spec = (root, query, cb) -> {
            if (query.getResultType() != Long.class && query.getResultType() != long.class) {
                root.fetch("usuario");
            }
            List<Predicate> ps = new ArrayList<>();
            if (casoId != null) ps.add(cb.equal(root.get("casoId"), casoId));
            if (usuarioId != null) ps.add(cb.equal(root.get("usuario").get("id"), usuarioId));
            if (desde != null) ps.add(cb.greaterThanOrEqualTo(root.get("fecha"), desde.atStartOfDay()));
            if (hasta != null) ps.add(cb.lessThan(root.get("fecha"), hasta.plusDays(1).atStartOfDay()));
            return cb.and(ps.toArray(new Predicate[0]));
        };
        int size = Math.max(1, Math.min(tamano, 100));
        int page = Math.max(0, pagina);
        Page<Auditoria> p = repo.findAll(spec, PageRequest.of(page, size, Sort.by(Sort.Direction.DESC, "fecha", "id")));
        List<AuditoriaRes> filas = p.getContent().stream().map(a -> new AuditoriaRes(
                a.getId(), a.getFecha(), a.getUsuario().getNombre(), a.getUsuario().getArea().getNombre(),
                a.getAccion(), a.getCasoId(), a.getDetalle())).toList();
        return new PaginaAuditoria(filas, p.getTotalElements(), page, size);
    }
}
