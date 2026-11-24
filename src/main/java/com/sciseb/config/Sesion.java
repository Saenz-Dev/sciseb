package com.sciseb.config;

import com.sciseb.domain.Rol;

/** Datos del usuario autenticado que se guardan en el contexto de seguridad. */
public record Sesion(Integer id, String correo, String nombre, Integer areaId, String area, String rol) {
    public boolean esAdmin() {
        return Rol.ADMIN.equals(rol);
    }
}
