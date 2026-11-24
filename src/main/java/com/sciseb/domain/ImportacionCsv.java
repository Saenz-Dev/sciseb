package com.sciseb.domain;

import jakarta.persistence.*;
import lombok.Getter;
import lombok.NoArgsConstructor;
import lombok.Setter;

import java.time.LocalDateTime;

@Entity
@Table(name = "importacion_csv")
@Getter @Setter @NoArgsConstructor
public class ImportacionCsv {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    @Column(name = "id_importacion")
    private Integer id;

    @ManyToOne(fetch = FetchType.LAZY, optional = false)
    @JoinColumn(name = "id_usuario", nullable = false)
    private Usuario usuario;

    @Column(name = "nombre_archivo", nullable = false, length = 150)
    private String nombreArchivo;

    @Column(nullable = false)
    private LocalDateTime fecha;

    @Column(name = "filas_total", nullable = false)
    private int filasTotal;

    @Column(name = "filas_importadas", nullable = false)
    private int filasImportadas;

    @Column(name = "filas_rechazadas", nullable = false)
    private int filasRechazadas;
}
