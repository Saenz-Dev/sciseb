package com.sciseb.util;

import com.sciseb.util.CasoValidador.Datos;
import org.junit.jupiter.api.Test;

import java.time.LocalDate;
import java.util.List;
import java.util.Map;

import static org.assertj.core.api.Assertions.assertThat;

class CasoValidadorTest {
    private static final LocalDate HOY = LocalDate.of(2026, 10, 27);
    private static final LocalDate FECHA_OK = LocalDate.of(2026, 10, 1);

    private static Datos base(String id, String nombre, String iniciales, String cedula, LocalDate fecha) {
        List<String> victimas = iniciales == null ? List.of() : List.of(iniciales);
        return new Datos(id, nombre, victimas, cedula, null, null, null, null, null, fecha, null, null, null, null);
    }

    @Test
    void casoCompletoEsValido() {
        Datos d = new Datos("00123", "María Pérez", List.of("J.P."), "12345678", "Matemáticas", "RAD-2026/01", "123", "456",
                "Secretaría de Educación", FECHA_OK, "Aula 3", "DIS-1", "Decisión del comité", "123456789012345678901234567890");
        assertThat(CasoValidador.validar(d, HOY)).isEmpty();
    }

    @Test
    void elIdEsOpcionalPeroSiVieneDebeTenerCincoNumeros() {
        for (String malo : new String[]{"123", "123456", "12a45", " 1234"}) {
            Map<String, String> e = CasoValidador.validar(base(malo, "María Pérez", "JP", "12345678", FECHA_OK), HOY);
            assertThat(e).containsKey("idCaso");
        }
        assertThat(CasoValidador.validar(base("00001", "María Pérez", "JP", "12345678", FECHA_OK), HOY)).isEmpty();
        assertThat(CasoValidador.validar(base(null, "María Pérez", "JP", "12345678", FECHA_OK), HOY)).isEmpty();
    }

    @Test
    void laCedulaSoloLlevaNumeros() {
        assertThat(CasoValidador.validar(base("12345", "María Pérez", "JP", "12.345.678", FECHA_OK), HOY)).containsKey("cedula");
        assertThat(CasoValidador.validar(base("12345", "María Pérez", "JP", "1234", FECHA_OK), HOY)).containsKey("cedula");
    }

    @Test
    void lasVictimasSonOpcionalesPeroSiVienenDebenSerSoloIniciales() {
        assertThat(CasoValidador.validar(base("12345", "María Pérez", "Juan Perez 1", "12345678", FECHA_OK), HOY))
                .containsKey("victimas");
        assertThat(CasoValidador.validar(base("12345", "María Pérez", "Juan Perez", "12345678", FECHA_OK), HOY))
                .containsKey("victimas");
        assertThat(CasoValidador.validar(base("12345", "María Pérez", null, "12345678", FECHA_OK), HOY))
                .doesNotContainKey("victimas");
        assertThat(CasoValidador.normalizarIniciales(" j.p. ")).isEqualTo("J.P.");
    }

    @Test
    void aceptaVariasVictimasValidas() {
        Datos d = new Datos("12345", "María Pérez", List.of("J.P.", "M.G."), "12345678", null, null, null, null,
                null, FECHA_OK, null, null, null, null);
        assertThat(CasoValidador.validar(d, HOY)).isEmpty();
    }

    @Test
    void rechazaSiUnaVictimaDeVariasEsInvalida() {
        Datos d = new Datos("12345", "María Pérez", List.of("J.P.", "nombre completo"), "12345678", null, null, null, null,
                null, FECHA_OK, null, null, null, null);
        assertThat(CasoValidador.validar(d, HOY)).containsKey("victimas");
    }

    @Test
    void laFechaEsOpcionalPeroNoPuedeSerFuturaNiMuyAntigua() {
        assertThat(CasoValidador.validar(base("12345", "María Pérez", "JP", "12345678", HOY.plusDays(1)), HOY)).containsKey("fechaOcurrencia");
        assertThat(CasoValidador.validar(base("12345", "María Pérez", "JP", "12345678", null), HOY)).isEmpty();
        assertThat(CasoValidador.validar(base("12345", "María Pérez", "JP", "12345678", HOY), HOY)).isEmpty();
    }

    @Test
    void resolucionesYPenalSoloNumeros() {
        Datos d = new Datos("12345", "María Pérez", List.of("JP"), "12345678", null, null, "12A", "4-5", null, FECHA_OK, null, null, null, "abc");
        assertThat(CasoValidador.validar(d, HOY)).containsKeys("resolucionSeb", "resolucionRectoral", "penal");
    }

    @Test
    void elNombreNoLlevaDigitos() {
        assertThat(CasoValidador.validar(base("12345", "M4ria", "JP", "12345678", FECHA_OK), HOY)).containsKey("presuntoAgresor");
    }

    @Test
    void unCasoCompletamenteVacioEsValido() {
        Datos d = new Datos(null, null, List.of(), null, null, null, null, null, null, null, null, null, null, null);
        assertThat(CasoValidador.validar(d, HOY)).isEmpty();
    }

    @Test
    void elNombreYLaCedulaSonIndependientesSePuedeInformarSoloUno() {
        // La cédula sola es válida (se resuelve o crea el agresor al guardar, ver CasoService).
        Datos soloCedula = new Datos(null, null, List.of(), "12345678", null, null, null, null, null, null, null, null, null, null);
        assertThat(CasoValidador.validar(soloCedula, HOY)).isEmpty();

        // El nombre solo, sin cédula, también es válido: queda como borrador (ver CasoService.persistirNuevo).
        Datos soloNombre = new Datos(null, "María Pérez", List.of(), null, null, null, null, null, null, null, null, null, null, null);
        assertThat(CasoValidador.validar(soloNombre, HOY)).isEmpty();
    }

    @Test
    void enmascaraLaCedula() {
        assertThat(CasoValidador.enmascararCedula("12345678")).isEqualTo("*****678");
        assertThat(CasoValidador.enmascararCedula(null)).isEqualTo("****");
    }

    @Test
    void neutralizaFormulasEnExportaciones() {
        assertThat(CasoValidador.neutralizarFormula("=1+1")).isEqualTo("'=1+1");
        assertThat(CasoValidador.neutralizarFormula("texto normal")).isEqualTo("texto normal");
    }

    @Test
    void limpiaEspaciosYVacios() {
        assertThat(CasoValidador.limpiar("   ")).isNull();
        assertThat(CasoValidador.limpiar("  a    b ")).isEqualTo("a b");
    }
}
