package com.sciseb.domain;

import jakarta.persistence.*;
import lombok.Getter;
import lombok.NoArgsConstructor;
import lombok.Setter;

import java.time.LocalDateTime;

/** Registro inmutable (el trigger de la base de datos impide UPDATE y DELETE). */
@Entity
@Table(name = "auditoria")
@Getter @Setter @NoArgsConstructor
public class Auditoria {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    @Column(name = "id_auditoria")
    private Long id;

    @ManyToOne(fetch = FetchType.LAZY, optional = false)
    @JoinColumn(name = "id_usuario", nullable = false)
    private Usuario usuario;

    @Column(name = "id_caso")
    private Long casoId;

    @Column(nullable = false, length = 40)
    private String accion;

    private String detalle;

    @Column(nullable = false)
    private LocalDateTime fecha;
}
