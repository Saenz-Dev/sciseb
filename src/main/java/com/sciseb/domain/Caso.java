package com.sciseb.domain;

import jakarta.persistence.*;
import lombok.Getter;
import lombok.NoArgsConstructor;
import lombok.Setter;
import org.springframework.data.annotation.CreatedBy;
import org.springframework.data.annotation.CreatedDate;
import org.springframework.data.annotation.LastModifiedBy;
import org.springframework.data.annotation.LastModifiedDate;
import org.springframework.data.jpa.domain.support.AuditingEntityListener;

import org.hibernate.annotations.BatchSize;

import java.time.LocalDate;
import java.time.LocalDateTime;
import java.util.ArrayList;
import java.util.List;

@Entity
@Table(name = "caso")
@EntityListeners(AuditingEntityListener.class)
@Getter @Setter @NoArgsConstructor
public class Caso {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    /** ID de negocio de 5 dígitos (SIUCE): opcional, puede asignarse después. Único cuando está presente. */
    @Column(name = "id_caso", length = 5)
    private String idCaso;

    /** Opcional: el caso se puede registrar sin agresor asignado todavía y completarse después. */
    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "id_agresor")
    private PresuntoAgresor agresor;

    /**
     * Nombre del presunto agresor escrito en el formulario cuando todavía no se conoce su cédula
     * (la tabla presunto_agresor exige ambos datos a la vez, así que mientras falte la cédula este
     * nombre queda aquí, como borrador, en vez de crear un registro de agresor). Se borra en cuanto
     * se completa la cédula.
     */
    @Column(name = "nombre_agresor_borrador", length = 120)
    private String nombreAgresorBorrador;

    /** Opcional: el caso se puede registrar sin sede asignada todavía y completarse después. */
    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "id_sede")
    private Sede sede;

    /** Opcional: el caso se puede registrar sin clasificación asignada todavía y completarse después. */
    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "id_clasificacion")
    private ClasificacionSiuce clasificacion;

    @OneToMany(mappedBy = "caso", cascade = CascadeType.ALL, orphanRemoval = true)
    @BatchSize(size = 20)
    private List<Victima> victimas = new ArrayList<>();

    @Column(name = "ruta_activa", nullable = false)
    private boolean rutaActiva;

    @Column(name = "n_radicado", length = 30)
    private String nRadicado;

    @Column(name = "resolucion_seb", length = 20)
    private String resolucionSeb;

    @Column(name = "resolucion_rectoral", length = 20)
    private String resolucionRectoral;

    @Column(name = "ubicacion_agresor")
    private String ubicacionAgresor;

    @Column(name = "fecha_ocurrencia")
    private LocalDate fechaOcurrencia;

    @Column(name = "lugar_ocurrencia", length = 120)
    private String lugarOcurrencia;

    @Column(length = 30)
    private String disciplinario;

    @Column(name = "decision_comite")
    private String decisionComite;

    @Column(length = 30)
    private String penal;

    @Enumerated(EnumType.STRING)
    @Column(nullable = false, length = 15)
    private EstadoCaso estado = EstadoCaso.ACTIVO;

    /** Control de concurrencia optimista: nulo mientras el caso no se ha guardado. */
    @Version
    private Integer version;

    @CreatedBy
    @ManyToOne(fetch = FetchType.LAZY, optional = false)
    @JoinColumn(name = "creado_por", nullable = false, updatable = false)
    private Usuario creadoPor;

    @CreatedDate
    @Column(name = "fecha_creacion", nullable = false, updatable = false)
    private LocalDateTime fechaCreacion;

    @LastModifiedBy
    @ManyToOne(fetch = FetchType.LAZY, optional = false)
    @JoinColumn(name = "editado_por", nullable = false)
    private Usuario editadoPor;

    @LastModifiedDate
    @Column(name = "fecha_ultima_edicion", nullable = false)
    private LocalDateTime fechaUltimaEdicion;
}
