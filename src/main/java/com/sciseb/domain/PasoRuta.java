package com.sciseb.domain;

import jakarta.persistence.*;
import lombok.Getter;
import lombok.NoArgsConstructor;
import lombok.Setter;

@Entity
@Table(name = "paso_ruta")
@Getter @Setter @NoArgsConstructor
public class PasoRuta {
    public static final int TOTAL = 12;

    @Id
    @Column(name = "id_paso")
    private Integer id;

    @Column(nullable = false, length = 100)
    private String nombre;
}
