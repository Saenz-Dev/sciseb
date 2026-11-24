package com.sciseb.service;

import org.junit.jupiter.api.RepeatedTest;
import org.junit.jupiter.api.Test;

import static org.assertj.core.api.Assertions.assertThat;

class UsuarioServiceTest {

    @RepeatedTest(50)
    void laClaveTemporalCumpleLaPolitica() {
        String c = UsuarioService.generarClaveTemporal();
        assertThat(c).hasSize(12).matches(".*[a-z].*").matches(".*[A-Z].*").matches(".*[0-9].*");
    }

    @Test
    void soloReconoceLosDosRoles() {
        assertThat(UsuarioService.esRolValido("ADMIN")).isTrue();
        assertThat(UsuarioService.esRolValido("USUARIO")).isTrue();
        assertThat(UsuarioService.esRolValido("ROOT")).isFalse();
    }
}
