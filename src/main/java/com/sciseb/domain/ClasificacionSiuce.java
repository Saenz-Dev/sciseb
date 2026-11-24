package com.sciseb.domain;

import jakarta.persistence.*;
import lombok.Getter;
import lombok.NoArgsConstructor;
import lombok.Setter;

@Entity
@Table(name = "clasificacion_siuce")
@Getter @Setter @NoArgsConstructor
public class ClasificacionSiuce {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    @Column(name = "id_clasificacion")
    private Integer id;

    @Column(nullable = false, unique = true, length = 20)
    private String nombre;
}
