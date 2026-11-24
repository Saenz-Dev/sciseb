package com.sciseb.repo;

import com.sciseb.domain.InstitucionEducativa;
import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;
import java.util.Optional;

public interface InstitucionRepository extends JpaRepository<InstitucionEducativa, Integer> {
    List<InstitucionEducativa> findByMunicipioIdOrderByNombreAsc(Integer municipioId);
    Optional<InstitucionEducativa> findByNombreIgnoreCaseAndMunicipioId(String nombre, Integer municipioId);
}
