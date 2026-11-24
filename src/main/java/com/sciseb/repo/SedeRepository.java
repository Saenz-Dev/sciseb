package com.sciseb.repo;

import com.sciseb.domain.Sede;
import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;
import java.util.Optional;

public interface SedeRepository extends JpaRepository<Sede, Integer> {
    List<Sede> findByInstitucionIdOrderByNombreAsc(Integer institucionId);
    Optional<Sede> findByNombreIgnoreCaseAndInstitucionId(String nombre, Integer institucionId);
}
