package com.sciseb.web;

import com.sciseb.config.Sesion;
import com.sciseb.dto.Dto.*;
import com.sciseb.service.AuthService;
import lombok.RequiredArgsConstructor;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.web.bind.annotation.*;

@RestController
@RequestMapping("/api/auth")
@RequiredArgsConstructor
public class AuthController {
    private final AuthService auth;

    @PostMapping("/login")
    public LoginRes login(@RequestBody LoginReq r) {
        return auth.login(r);
    }

    @GetMapping("/yo")
    public UsuarioRes yo(@AuthenticationPrincipal Sesion s) {
        return auth.yo(s);
    }

    @PostMapping("/cambiar-clave")
    public UsuarioRes cambiarClave(@AuthenticationPrincipal Sesion s, @RequestBody CambioClaveReq r) {
        return auth.cambiarClave(s, r);
    }
}
