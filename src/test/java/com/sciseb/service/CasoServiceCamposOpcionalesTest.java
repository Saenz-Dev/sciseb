package com.sciseb.service;

import com.sciseb.config.ApiException;
import com.sciseb.config.Sesion;
import com.sciseb.domain.Rol;
import com.sciseb.domain.Usuario;
import com.sciseb.dto.Dto.CasoDetalle;
import com.sciseb.dto.Dto.CasoReq;
import com.sciseb.repo.ClasificacionRepository;
import com.sciseb.repo.SedeRepository;
import com.sciseb.repo.UsuarioRepository;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.transaction.annotation.Transactional;

import java.util.List;

import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;
import static org.junit.jupiter.api.Assumptions.assumeTrue;

/**
 * El Administrador debe poder registrar un caso sin agresor, sede ni clasificación asignados
 * todavía y completarlo después. Si se escribe el nombre del presunto agresor pero no su cédula,
 * el caso se guarda con ese nombre como borrador (no se crea un presunto_agresor real porque esa
 * tabla exige también la cédula). Prueba contra la base de datos real, dentro de una transacción
 * que Spring revierte sola al terminar: no deja datos de prueba en la base.
 */
@SpringBootTest
@Transactional
class CasoServiceCamposOpcionalesTest {

    @Autowired private CasoService casoService;
    @Autowired private UsuarioRepository usuarios;
    @Autowired private SedeRepository sedes;
    @Autowired private ClasificacionRepository clasificaciones;

    private Sesion sesionDeUnAdmin() {
        Usuario u = usuarios.findAll().stream()
                .filter(x -> Rol.ADMIN.equals(x.getRol().getNombre()))
                .findFirst().orElseThrow(() -> new IllegalStateException("No hay ningún Administrador en la base de pruebas."));
        return new Sesion(u.getId(), u.getCorreo(), u.getNombre(), u.getArea().getId(), u.getArea().getNombre(), Rol.ADMIN);
    }

    private static CasoReq conCedula(String cedula, String nombre) {
        return new CasoReq(null, nombre, cedula, null, List.of(), null, null, null,
                null, null, null, null, null, null, null, null, null, null, null);
    }

    @Test
    void unCasoSinAgresorSedeNiClasificacionSeRegistraYQuedaAsi() {
        CasoDetalle d = casoService.crear(sesionDeUnAdmin(), conCedula(null, null));

        assertThat(d.presuntoAgresor()).isNull();
        assertThat(d.cedula()).isNull();
        assertThat(d.agresorBorrador()).isFalse();
        assertThat(d.victimas()).isEmpty();
        assertThat(d.sedeId()).isNull();
        assertThat(d.sede()).isNull();
        assertThat(d.clasificacionId()).isNull();
        assertThat(d.clasificacion()).isNull();
        // Aun sin agresor/sede/clasificación, los 12 pasos de la ruta de atención se crean igual.
        assertThat(d.pasos()).hasSize(12);
    }

    @Test
    void unNombreSinCedulaQuedaComoBorrador() {
        CasoDetalle d = casoService.crear(sesionDeUnAdmin(), conCedula(null, "Persona Sin Cedula Todavia"));

        assertThat(d.presuntoAgresor()).isEqualTo("Persona Sin Cedula Todavia");
        assertThat(d.cedula()).isNull();
        assertThat(d.agresorBorrador()).isTrue();
    }

    @Test
    void siLaCedulaYaEstaRegistradaElNombreSeCompletaSolo() {
        casoService.crear(sesionDeUnAdmin(), conCedula("55544433", "Persona Ya Registrada"));

        CasoDetalle segundo = casoService.crear(sesionDeUnAdmin(), conCedula("55544433", null));

        assertThat(segundo.presuntoAgresor()).isEqualTo("Persona Ya Registrada");
        assertThat(segundo.cedula()).isEqualTo("55544433");
        assertThat(segundo.agresorBorrador()).isFalse();
    }

    @Test
    void siHaySedesYClasificacionesElCasoCompletoSigueFuncionandoIgual() {
        var sede = sedes.findAll().stream().findFirst();
        var clasificacion = clasificaciones.findAllByOrderByIdAsc().stream().findFirst();
        assumeTrue(sede.isPresent(), "No hay ninguna sede cargada en el catálogo de pruebas; se omite esta prueba.");
        assumeTrue(clasificacion.isPresent(), "No hay ninguna clasificación cargada en el catálogo de pruebas; se omite esta prueba.");

        CasoReq r = new CasoReq(null, "Caso De Prueba Automatica", "99988877", null,
                List.of("J.P."), sede.get().getId(), clasificacion.get().getId(), false,
                null, null, null, null, null, null, null, null, null, null, null);

        CasoDetalle d = casoService.crear(sesionDeUnAdmin(), r);

        assertThat(d.presuntoAgresor()).isEqualTo("Caso De Prueba Automatica");
        assertThat(d.cedula()).isEqualTo("99988877");
        assertThat(d.victimas()).containsExactly("J.P.");
        assertThat(d.sedeId()).isEqualTo(sede.get().getId());
        assertThat(d.clasificacionId()).isEqualTo(clasificacion.get().getId());
    }

    @Test
    void siLaCedulaEsNuevaElNombreEsObligatorioParaRegistrarla() {
        assertThatThrownBy(() -> casoService.crear(sesionDeUnAdmin(), conCedula("11122233", null)))
                .isInstanceOf(ApiException.class)
                .satisfies(e -> assertThat(((ApiException) e).getErrores()).containsKey("presuntoAgresor"));
    }

    @Test
    void completarLaCedulaAlEditarQuitaElBorrador() {
        CasoDetalle creado = casoService.crear(sesionDeUnAdmin(), conCedula(null, "Persona En Borrador Al Editar"));
        assertThat(creado.agresorBorrador()).isTrue();

        CasoReq edicion = new CasoReq(null, "Persona En Borrador Al Editar", "66677788", null, List.of(),
                null, null, false, null, null, null, null, null, null, null, null, null, creado.version(), null);
        CasoDetalle editado = casoService.actualizar(sesionDeUnAdmin(), creado.id(), edicion);

        assertThat(editado.agresorBorrador()).isFalse();
        assertThat(editado.cedula()).isEqualTo("66677788");
        assertThat(editado.presuntoAgresor()).isEqualTo("Persona En Borrador Al Editar");
    }
}
