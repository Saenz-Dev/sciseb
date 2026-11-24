package com.sciseb.config;

import com.fasterxml.jackson.databind.ObjectMapper;
import com.sciseb.domain.EstadoUsuario;
import com.sciseb.domain.Usuario;
import com.sciseb.dto.Dto.ErrorRes;
import com.sciseb.repo.UsuarioRepository;
import jakarta.servlet.FilterChain;
import jakarta.servlet.ServletException;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import lombok.RequiredArgsConstructor;
import org.springframework.http.MediaType;
import org.springframework.security.authentication.UsernamePasswordAuthenticationToken;
import org.springframework.security.core.authority.SimpleGrantedAuthority;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.web.filter.OncePerRequestFilter;

import java.io.IOException;
import java.nio.charset.StandardCharsets;
import java.util.List;

/**
 * Valida el token en cada petición, vuelve a leer el usuario (para que inhabilitar una cuenta surta
 * efecto de inmediato) y entrega un token renovado en la cabecera X-Token-Renovado: así la sesión
 * solo vence tras 30 minutos sin uso.
 *
 * No es un bean (@Component) a propósito: si lo fuera, Spring Boot también lo registraría como filtro de
 * servlet y el contexto de seguridad que crea se perdería antes de llegar a la cadena de Spring Security.
 */
@RequiredArgsConstructor
public class JwtFilter extends OncePerRequestFilter {
    public static final String CABECERA_RENOVADO = "X-Token-Renovado";

    private final JwtService jwt;
    private final UsuarioRepository usuarios;
    private final ObjectMapper mapper;

    @Override
    protected void doFilterInternal(HttpServletRequest req, HttpServletResponse res, FilterChain chain)
            throws ServletException, IOException {
        String h = req.getHeader("Authorization");
        if (h != null && h.startsWith("Bearer ")) {
            Integer id = jwt.leerId(h.substring(7));
            Usuario u = id == null ? null : usuarios.findById(id).orElse(null);
            if (u != null && (u.getEstado() == EstadoUsuario.ACTIVO
                    || u.getEstado() == EstadoUsuario.PENDIENTE_CAMBIO_CLAVE)) {
                boolean pendiente = u.getEstado() == EstadoUsuario.PENDIENTE_CAMBIO_CLAVE;
                if (pendiente && !req.getRequestURI().startsWith("/api/auth/")) {
                    escribir(res, 403, new ErrorRes("CAMBIO_CLAVE",
                            "Debe cambiar la clave temporal antes de continuar.", null));
                    return;
                }
                Sesion s = new Sesion(u.getId(), u.getCorreo(), u.getNombre(), u.getArea().getId(),
                        u.getArea().getNombre(), u.getRol().getNombre());
                var auth = new UsernamePasswordAuthenticationToken(s, null,
                        List.of(new SimpleGrantedAuthority("ROLE_" + s.rol())));
                SecurityContextHolder.getContext().setAuthentication(auth);
                res.setHeader(CABECERA_RENOVADO, jwt.crear(u));
            }
        }
        chain.doFilter(req, res);
    }

    void escribir(HttpServletResponse res, int estado, ErrorRes cuerpo) throws IOException {
        res.setStatus(estado);
        res.setContentType(MediaType.APPLICATION_JSON_VALUE);
        res.setCharacterEncoding(StandardCharsets.UTF_8.name());
        mapper.writeValue(res.getWriter(), cuerpo);
    }
}
