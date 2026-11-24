package com.sciseb.repo;

import com.sciseb.domain.PresuntoAgresor;
import org.springframework.data.jpa.repository.JpaRepository;

import java.util.Optional;

public interface AgresorRepository extends JpaRepository<PresuntoAgresor, Integer> {
    Optional<PresuntoAgresor> findByCedula(String cedula);
    Optional<PresuntoAgresor> findFirstByNombreIgnoreCase(String nombre);
}
