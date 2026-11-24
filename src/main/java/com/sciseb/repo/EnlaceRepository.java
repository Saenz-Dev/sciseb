package com.sciseb.repo;

import com.sciseb.domain.EnlaceEvidencia;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Query;
import org.springframework.data.repository.query.Param;

import java.util.List;

public interface EnlaceRepository extends JpaRepository<EnlaceEvidencia, Integer> {
    @Query("select e from EnlaceEvidencia e join fetch e.usuario u join fetch u.area "
            + "where e.caso.id = :idCaso and e.activo = true order by e.fechaRegistro desc")
    List<EnlaceEvidencia> listarActivosPorCaso(@Param("idCaso") Long idCaso);

    boolean existsByCasoIdAndUrlAndActivoTrue(Long idCaso, String url);
}
