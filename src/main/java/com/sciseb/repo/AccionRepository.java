package com.sciseb.repo;

import com.sciseb.domain.AccionCaso;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Query;
import org.springframework.data.repository.query.Param;

import java.util.List;

public interface AccionRepository extends JpaRepository<AccionCaso, Integer> {
    @Query("select a from AccionCaso a join fetch a.usuario u join fetch u.area "
            + "where a.caso.id = :idCaso order by a.fechaRegistro desc")
    List<AccionCaso> listarPorCaso(@Param("idCaso") Long idCaso);
}
