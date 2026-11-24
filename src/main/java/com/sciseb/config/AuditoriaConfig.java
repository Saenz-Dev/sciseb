package com.sciseb.config;

import com.sciseb.domain.Usuario;
import com.sciseb.repo.UsuarioRepository;
import org.springframework.beans.factory.ObjectProvider;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.data.domain.AuditorAware;
import org.springframework.data.jpa.repository.config.EnableJpaAuditing;
import org.springframework.security.core.Authentication;
import org.springframework.security.core.context.SecurityContextHolder;

import java.util.Optional;

/** Alimenta @CreatedBy y @LastModifiedBy de los casos con la persona autenticada. */
@Configuration
@EnableJpaAuditing(auditorAwareRef = "auditorAware")
public class AuditoriaConfig {

    @Bean
    AuditorAware<Usuario> auditorAware(ObjectProvider<UsuarioRepository> repo) {
        return () -> {
            Authentication a = SecurityContextHolder.getContext().getAuthentication();
            if (a != null && a.getPrincipal() instanceof Sesion s) {
                return Optional.of(repo.getObject().getReferenceById(s.id()));
            }
            return Optional.empty();
        };
    }
}
