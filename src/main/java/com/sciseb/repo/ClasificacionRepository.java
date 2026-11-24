package com.sciseb.repo;

import com.sciseb.domain.ClasificacionSiuce;
import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;
import java.util.Optional;

public interface ClasificacionRepository extends JpaRepository<ClasificacionSiuce, Integer> {
    List<ClasificacionSiuce> findAllByOrderByIdAsc();
    Optional<ClasificacionSiuce> findByNombreIgnoreCase(String nombre);
}
