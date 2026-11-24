package com.sciseb.repo;

import com.sciseb.domain.ImportacionCsv;
import org.springframework.data.jpa.repository.JpaRepository;

public interface ImportacionRepository extends JpaRepository<ImportacionCsv, Integer> {
}
