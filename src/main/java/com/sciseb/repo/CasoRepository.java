package com.sciseb.repo;

import com.sciseb.domain.Caso;
import com.sciseb.domain.EstadoCaso;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.data.domain.Sort;
import org.springframework.data.jpa.domain.Specification;
import org.springframework.data.jpa.repository.EntityGraph;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.JpaSpecificationExecutor;
import org.springframework.data.jpa.repository.Query;
import org.springframework.data.repository.query.Param;

import java.util.List;

public interface CasoRepository extends JpaRepository<Caso, Long>, JpaSpecificationExecutor<Caso> {

    boolean existsByIdCaso(String idCaso);

    @Override
    @EntityGraph(attributePaths = {"agresor", "sede", "sede.institucion", "sede.institucion.municipio",
            "sede.institucion.municipio.provincia", "clasificacion", "editadoPor", "creadoPor"})
    Page<Caso> findAll(Specification<Caso> spec, Pageable pageable);

    @Override
    @EntityGraph(attributePaths = {"agresor", "sede", "sede.institucion", "sede.institucion.municipio",
            "sede.institucion.municipio.provincia", "clasificacion", "editadoPor", "creadoPor"})
    List<Caso> findAll(Specification<Caso> spec, Sort sort);

    long countByEstado(EstadoCaso estado);

    long countByEstadoAndRutaActiva(EstadoCaso estado, boolean rutaActiva);

    @Query("select count(c) from Caso c where c.estado = :estado and c.clasificacion.nombre = :nombre")
    long contarPorClasificacion(@Param("estado") EstadoCaso estado, @Param("nombre") String nombre);

    @Query("select count(c) from Caso c where c.estado = :estado and "
            + "(select count(cp) from CasoPaso cp where cp.caso = c and cp.efectuado = true) = 12")
    long contarRutaCompleta(@Param("estado") EstadoCaso estado);

    @Query("select count(c) from Caso c where c.agresor.id = :idAgresor")
    long contarPorAgresor(@Param("idAgresor") Integer idAgresor);
}
