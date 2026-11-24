package com.sciseb.config;

import org.springframework.boot.context.properties.ConfigurationProperties;

@ConfigurationProperties(prefix = "app")
public record AppProperties(Jwt jwt, Cors cors, Admin admin, Importacion importacion, Exportacion exportacion) {
    public record Jwt(String secret, int expiracionMinutos) {}
    public record Cors(String origenes) {}
    public record Admin(String correo, String clave) {}
    public record Importacion(int maxFilas, int minutosVistaPrevia) {}
    public record Exportacion(int maxFilas) {}
}
