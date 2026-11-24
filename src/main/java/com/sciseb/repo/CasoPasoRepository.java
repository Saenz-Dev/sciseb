package com.sciseb.repo;

import com.sciseb.domain.CasoPaso;
import com.sciseb.domain.CasoPasoId;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Query;
import org.springframework.data.repository.query.Param;

import java.util.Collection;
import java.util.List;

public interface CasoPasoRepository extends JpaRepository<CasoPaso, CasoPasoId> {

    @Query("select cp from CasoPaso cp join fetch cp.paso left join fetch cp.usuario u left join fetch u.area "
            + "where cp.id.casoId = :casoId order by cp.id.idPaso")
    List<CasoPaso> listarPorCaso(@Param("casoId") Long casoId);

    /** Devuelve filas [id del caso, cantidad de pasos efectuados]. */
    @Query("select cp.id.casoId, count(cp) from CasoPaso cp "
            + "where cp.id.casoId in :ids and cp.efectuado = true group by cp.id.casoId")
    List<Object[]> contarEfectuados(@Param("ids") Collection<Long> ids);
}
