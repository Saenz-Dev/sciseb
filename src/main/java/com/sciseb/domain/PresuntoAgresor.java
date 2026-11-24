package com.sciseb.domain;

import jakarta.persistence.*;
import lombok.Getter;
import lombok.NoArgsConstructor;
import lombok.Setter;

@Entity
@Table(name = "presunto_agresor")
@Getter @Setter @NoArgsConstructor
public class PresuntoAgresor {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    @Column(name = "id_agresor")
    private Integer id;

    @Column(nullable = false, length = 120)
    private String nombre;

    @Column(nullable = false, unique = true, length = 12)
    private String cedula;

    @Column(name = "area_asignatura", length = 80)
    private String areaAsignatura;
}
