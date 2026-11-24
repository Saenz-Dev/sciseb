package com.sciseb.repo;

import com.sciseb.domain.PasoRuta;
import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;

public interface PasoRutaRepository extends JpaRepository<PasoRuta, Integer> {
    List<PasoRuta> findAllByOrderByIdAsc();
}
