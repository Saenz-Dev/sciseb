package com.sciseb.domain;

import jakarta.persistence.*;
import lombok.Getter;
import lombok.NoArgsConstructor;
import lombok.Setter;

import java.time.LocalDateTime;

@Entity
@Table(name = "caso_paso")
@Getter @Setter @NoArgsConstructor
public class CasoPaso {
    @EmbeddedId
    private CasoPasoId id;

    @MapsId("casoId")
    @ManyToOne(fetch = FetchType.LAZY, optional = false)
    @JoinColumn(name = "id_caso")
    private Caso caso;

    @MapsId("idPaso")
    @ManyToOne(fetch = FetchType.EAGER, optional = false)
    @JoinColumn(name = "id_paso")
    private PasoRuta paso;

    @Column(nullable = false)
    private boolean efectuado;

    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "id_usuario")
    private Usuario usuario;

    @Column(name = "fecha_efectuado")
    private LocalDateTime fechaEfectuado;

    private String nota;

    public CasoPaso(Caso caso, PasoRuta paso) {
        this.id = new CasoPasoId(caso.getId(), paso.getId());
        this.caso = caso;
        this.paso = paso;
    }
}
