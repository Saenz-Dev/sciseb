package com.sciseb.service;

import com.sciseb.config.ApiException;
import com.sciseb.config.JwtService;
import com.sciseb.config.Sesion;
import com.sciseb.domain.EstadoUsuario;
import com.sciseb.domain.Usuario;
import com.sciseb.dto.Dto.CambioClaveReq;
import com.sciseb.dto.Dto.LoginReq;
import com.sciseb.dto.Dto.LoginRes;
import com.sciseb.dto.Dto.UsuarioRes;
import com.sciseb.repo.UsuarioRepository;
import lombok.RequiredArgsConstructor;
import org.springframework.http.HttpStatus;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.time.LocalDateTime;
import java.util.Map;
import java.util.regex.Pattern;

@Service
@RequiredArgsConstructor
public class AuthService {
    static final int MAX_INTENTOS = 5;
    private static final Pattern POLITICA = Pattern.compile("^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).{10,72}$");

    private final UsuarioRepository usuarios;
    private final PasswordEncoder encoder;
    private final JwtService jwt;
    private final AuditoriaService auditoria;

    /** noRollbackFor: el contador de intentos fallidos debe guardarse aunque se lance el error 401. */
    @Transactional(noRollbackFor = ApiException.class)
    public LoginRes login(LoginReq r) {
        ApiException credenciales = new ApiException(HttpStatus.UNAUTHORIZED, "CREDENCIALES",
                "El correo o la clave no son correctos.", null);
        if (r == null || r.correo() == null || r.clave() == null) throw credenciales;

        Usuario u = usuarios.findByCorreoIgnoreCase(r.correo().trim()).orElse(null);
        if (u == null) throw credenciales;

        if (u.getEstado() == EstadoUsuario.BLOQUEADO) {
            throw new ApiException(HttpStatus.LOCKED, "CUENTA_BLOQUEADA",
                    "La cuenta está bloqueada por intentos fallidos. Pida al Administrador que restablezca su clave.", null);
        }
        if (u.getEstado() == EstadoUsuario.INACTIVO) {
            throw new ApiException(HttpStatus.FORBIDDEN, "CUENTA_INACTIVA",
                    "La cuenta está inhabilitada. Comuníquese con el Administrador.", null);
        }
        if (!encoder.matches(r.clave(), u.getClaveHash())) {
            u.setIntentosFallidos(u.getIntentosFallidos() + 1);
            if (u.getIntentosFallidos() >= MAX_INTENTOS) {
                u.setEstado(EstadoUsuario.BLOQUEADO);
                auditoria.registrar(u.getId(), null, "CUENTA_BLOQUEADA", "5 intentos fallidos");
                throw new ApiException(HttpStatus.LOCKED, "CUENTA_BLOQUEADA",
                        "La cuenta se bloqueó por 5 intentos fallidos. Pida al Administrador que restablezca su clave.", null);
            }
            throw credenciales;
        }
        u.setIntentosFallidos(0);
        u.setUltimoAcceso(LocalDateTime.now());
        auditoria.registrar(u.getId(), null, "INICIO_SESION", null);
        return new LoginRes(jwt.crear(u), UsuarioService.aRes(u), u.getEstado() == EstadoUsuario.PENDIENTE_CAMBIO_CLAVE);
    }

    @Transactional(readOnly = true)
    public UsuarioRes yo(Sesion s) {
        return UsuarioService.aRes(usuarios.findById(s.id()).orElseThrow(() -> ApiException.noEncontrado("Usuario no encontrado.")));
    }

    @Transactional
    public UsuarioRes cambiarClave(Sesion s, CambioClaveReq r) {
        Usuario u = usuarios.findById(s.id()).orElseThrow(() -> ApiException.noEncontrado("Usuario no encontrado."));
        if (r == null || r.claveActual() == null || r.claveNueva() == null)
            throw ApiException.solicitudInvalida("Escriba la clave actual y la nueva.");
        if (!encoder.matches(r.claveActual(), u.getClaveHash()))
            throw ApiException.validacion(Map.of("claveActual", "La clave actual no es correcta."));
        if (!POLITICA.matcher(r.claveNueva()).matches())
            throw ApiException.validacion(Map.of("claveNueva",
                    "La clave nueva debe tener mínimo 10 caracteres, con mayúscula, minúscula y número."));
        if (r.claveNueva().equals(r.claveActual()))
            throw ApiException.validacion(Map.of("claveNueva", "La clave nueva debe ser distinta de la actual."));
        u.setClaveHash(encoder.encode(r.claveNueva()));
        u.setEstado(EstadoUsuario.ACTIVO);
        auditoria.registrar(u.getId(), null, "CAMBIO_CLAVE", null);
        return UsuarioService.aRes(u);
    }
}
