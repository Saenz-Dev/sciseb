package com.sciseb.repo;

import com.sciseb.domain.Municipio;
import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;
import java.util.Optional;

public interface MunicipioRepository extends JpaRepository<Municipio, Integer> {
    List<Municipio> findByProvinciaIdOrderByNombreAsc(Integer provinciaId);
    Optional<Municipio> findByNombreIgnoreCaseAndProvinciaId(String nombre, Integer provinciaId);
}
