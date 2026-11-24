package com.sciseb.domain;

import jakarta.persistence.*;
import lombok.AllArgsConstructor;
import lombok.Getter;
import lombok.NoArgsConstructor;
import lombok.Setter;

@Entity
@Table(name = "caso_victima")
@Getter @Setter @NoArgsConstructor
public class Victima {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    @Column(name = "id_victima")
    private Long id;

    @ManyToOne(fetch = FetchType.LAZY, optional = false)
    @JoinColumn(name = "id_caso", nullable = false)
    private Caso caso;

    @Column(nullable = false, length = 10)
    private String iniciales;

    public Victima(Caso caso, String iniciales) {
        this.caso = caso;
        this.iniciales = iniciales;
    }
}
