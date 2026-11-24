package com.sciseb.repo;

import com.sciseb.domain.Provincia;
import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;
import java.util.Optional;

public interface ProvinciaRepository extends JpaRepository<Provincia, Integer> {
    List<Provincia> findAllByOrderByNombreAsc();
    Optional<Provincia> findByNombreIgnoreCase(String nombre);
}
