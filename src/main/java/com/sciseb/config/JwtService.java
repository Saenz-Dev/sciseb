package com.sciseb.config;

import com.sciseb.domain.Usuario;
import io.jsonwebtoken.JwtException;
import io.jsonwebtoken.Jwts;
import io.jsonwebtoken.security.Keys;
import org.springframework.stereotype.Service;

import javax.crypto.SecretKey;
import java.nio.charset.StandardCharsets;
import java.util.Date;

@Service
public class JwtService {
    private final SecretKey clave;
    private final long minutos;

    public JwtService(AppProperties props) {
        byte[] bytes = props.jwt().secret().getBytes(StandardCharsets.UTF_8);
        if (bytes.length < 32) {
            throw new IllegalStateException("app.jwt.secret (JWT_SECRET) debe tener al menos 32 caracteres");
        }
        this.clave = Keys.hmacShaKeyFor(bytes);
        this.minutos = props.jwt().expiracionMinutos();
    }

    public String crear(Usuario u) {
        Date ahora = new Date();
        return Jwts.builder()
                .subject(String.valueOf(u.getId()))
                .issuedAt(ahora)
                .expiration(new Date(ahora.getTime() + minutos * 60_000L))
                .signWith(clave)
                .compact();
    }

    /** Devuelve el id del usuario o null si el token no es válido o venció. */
    public Integer leerId(String token) {
        try {
            String sub = Jwts.parser().verifyWith(clave).build().parseSignedClaims(token).getPayload().getSubject();
            return Integer.valueOf(sub);
        } catch (JwtException | IllegalArgumentException e) {
            return null;
        }
    }
}
