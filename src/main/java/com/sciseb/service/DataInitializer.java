package com.sciseb.service;

import com.sciseb.config.AppProperties;
import com.sciseb.domain.EstadoUsuario;
import com.sciseb.domain.Rol;
import com.sciseb.domain.Usuario;
import com.sciseb.repo.AreaRepository;
import com.sciseb.repo.RolRepository;
import com.sciseb.repo.UsuarioRepository;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.boot.CommandLineRunner;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.stereotype.Component;
import org.springframework.transaction.annotation.Transactional;

/** Crea el primer Administrador la primera vez que arranca el sistema con la base de datos vacía. */
@Component
@RequiredArgsConstructor
@Slf4j
public class DataInitializer implements CommandLineRunner {
    private final UsuarioRepository usuarios;
    private final AreaRepository areas;
    private final RolRepository roles;
    private final PasswordEncoder encoder;
    private final AppProperties props;

    @Override
    @Transactional
    public void run(String... args) {
        if (usuarios.count() > 0) return;
        Usuario u = new Usuario();
        u.setNombre("Administrador del sistema");
        u.setCorreo(props.admin().correo().trim().toLowerCase());
        u.setClaveHash(encoder.encode(props.admin().clave()));
        u.setArea(areas.findAllByOrderByIdAsc().get(0));
        u.setRol(roles.findByNombre(Rol.ADMIN).orElseThrow());
        u.setEstado(EstadoUsuario.PENDIENTE_CAMBIO_CLAVE);
        usuarios.save(u);
        log.info("Se creó el Administrador inicial {}. Debe cambiar la clave en el primer ingreso.", u.getCorreo());
    }
}
