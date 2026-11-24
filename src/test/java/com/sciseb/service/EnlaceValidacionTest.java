package com.sciseb.service;

import com.sciseb.config.ApiException;
import org.junit.jupiter.api.Test;

import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

class EnlaceValidacionTest {

    @Test
    void aceptaSoloHttps() {
        assertThat(AccionEnlaceService.urlValida(" https://drive.google.com/drive/folders/abc ")).isEqualTo("https://drive.google.com/drive/folders/abc");
    }

    @Test
    void rechazaOtrosEsquemasYDireccionesRaras() {
        for (String malo : new String[]{null, "", "http://sitio.com/x", "javascript:alert(1)", "ftp://sitio.com",
                "https://usuario:clave@sitio.com/x", "https://", "https://sitio.com/con espacio", "file:///etc/passwd"}) {
            assertThatThrownBy(() -> AccionEnlaceService.urlValida(malo)).isInstanceOf(ApiException.class);
        }
    }
}
