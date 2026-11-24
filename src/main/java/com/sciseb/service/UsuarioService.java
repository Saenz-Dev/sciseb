package com.sciseb.service;

import com.sciseb.config.ApiException;
import com.sciseb.config.Sesion;
import com.sciseb.domain.EstadoUsuario;
import com.sciseb.domain.Rol;
import com.sciseb.domain.Usuario;
import com.sciseb.dto.Dto.ClaveTemporalRes;
import com.sciseb.dto.Dto.UsuarioReq;
import com.sciseb.dto.Dto.UsuarioRes;
import com.sciseb.repo.AreaRepository;
import com.sciseb.repo.RolRepository;
import com.sciseb.repo.UsuarioRepository;
import lombok.RequiredArgsConstructor;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.security.SecureRandom;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import java.util.regex.Pattern;

@Service
@RequiredArgsConstructor
public class UsuarioService {
    private static final Pattern CORREO = Pattern.compile("^[^@\\s]+@[^@\\s]+\\.[^@\\s]+$");
    private static final String MAYUS = "ABCDEFGHJKLMNPQRSTUVWXYZ";
    private static final String MINUS = "abcdefghijkmnpqrstuvwxyz";
    private static final String DIGITOS = "23456789";
    private static final SecureRandom RND = new SecureRandom();

    private final UsuarioRepository usuarios;
    private final AreaRepository areas;
    private final RolRepository roles;
    private final PasswordEncoder encoder;
    private final AuditoriaService auditoria;

    static UsuarioRes aRes(Usuario u) {
        return new UsuarioRes(u.getId(), u.getNombre(), u.getCorreo(), u.getArea().getId(), u.getArea().getNombre(),
                u.getRol().getNombre(), u.getEstado().name(), u.getUltimoAcceso());
    }

    /** Clave temporal de 12 caracteres con mayúscula, minúscula y número (sin caracteres ambiguos). */
    public static String generarClaveTemporal() {
        String todos = MAYUS + MINUS + DIGITOS;
        StringBuilder sb = new StringBuilder();
        sb.append(MAYUS.charAt(RND.nextInt(MAYUS.length())));
        sb.append(MINUS.charAt(RND.nextInt(MINUS.length())));
        sb.append(DIGITOS.charAt(RND.nextInt(DIGITOS.length())));
        while (sb.length() < 12) sb.append(todos.charAt(RND.nextInt(todos.length())));
        return sb.toString();
    }

    @Transactional(readOnly = true)
    public List<UsuarioRes> listar() {
        return usuarios.findAllByOrderByNombreAsc().stream().map(UsuarioService::aRes).toList();
    }

    @Transactional
    public ClaveTemporalRes crear(Sesion admin, UsuarioReq r) {
        Map<String, String> e = new LinkedHashMap<>();
        String nombre = r.nombre() == null ? "" : r.nombre().trim();
        String correo = r.correo() == null ? "" : r.correo().trim().toLowerCase();
        if (nombre.length() < 3 || nombre.length() > 120) e.put("nombre", "Escriba el nombre completo (3 a 120 caracteres).");
        if (!CORREO.matcher(correo).matches() || correo.length() > 120) e.put("correo", "Escriba un correo válido.");
        else if (usuarios.existsByCorreoIgnoreCase(correo)) e.put("correo", "Ya existe un usuario con ese correo.");
        var area = r.areaId() == null ? null : areas.findById(r.areaId()).orElse(null);
        if (area == null) e.put("areaId", "Seleccione el área.");
        var rol = r.rol() == null ? null : roles.findByNombre(r.rol()).orElse(null);
        if (rol == null) e.put("rol", "Seleccione el rol (ADMIN o USUARIO).");
        if (!e.isEmpty()) throw ApiException.validacion(e);

        String temporal = generarClaveTemporal();
        Usuario u = new Usuario();
        u.setNombre(nombre);
        u.setCorreo(correo);
        u.setArea(area);
        u.setRol(rol);
        u.setClaveHash(encoder.encode(temporal));
        u.setEstado(EstadoUsuario.PENDIENTE_CAMBIO_CLAVE);
        usuarios.save(u);
        auditoria.registrar(admin.id(), null, "CREAR_USUARIO", "usuario " + u.getId() + ", rol " + rol.getNombre());
        return new ClaveTemporalRes(aRes(u), temporal);
    }

    @Transactional
    public UsuarioRes cambiarEstado(Sesion admin, Integer id, String estado) {
        Usuario u = usuarios.findById(id).orElseThrow(() -> ApiException.noEncontrado("Usuario no encontrado."));
        if (!"ACTIVO".equals(estado) && !"INACTIVO".equals(estado))
            throw ApiException.solicitudInvalida("El estado debe ser ACTIVO o INACTIVO.");
        if (u.getId().equals(admin.id()))
            throw ApiException.conflicto("AUTO_INHABILITAR", "No puede cambiar el estado de su propia cuenta.");
        if ("INACTIVO".equals(estado)) {
            u.setEstado(EstadoUsuario.INACTIVO);
        } else {
            // Conserva su clave actual pero exige cambiarla en el siguiente ingreso
            u.setEstado(EstadoUsuario.PENDIENTE_CAMBIO_CLAVE);
            u.setIntentosFallidos(0);
        }
        auditoria.registrar(admin.id(), null, "INACTIVO".equals(estado) ? "INHABILITAR_USUARIO" : "REACTIVAR_USUARIO",
                "usuario " + u.getId());
        return aRes(u);
    }

    @Transactional
    public ClaveTemporalRes restablecerClave(Sesion admin, Integer id) {
        Usuario u = usuarios.findById(id).orElseThrow(() -> ApiException.noEncontrado("Usuario no encontrado."));
        if (u.getEstado() == EstadoUsuario.INACTIVO)
            throw ApiException.conflicto("CUENTA_INACTIVA", "La cuenta está inhabilitada; reactívela primero.");
        String temporal = generarClaveTemporal();
        u.setClaveHash(encoder.encode(temporal));
        u.setIntentosFallidos(0);
        u.setEstado(EstadoUsuario.PENDIENTE_CAMBIO_CLAVE);
        auditoria.registrar(admin.id(), null, "RESTABLECER_CLAVE", "usuario " + u.getId());
        return new ClaveTemporalRes(aRes(u), temporal);
    }

    public static boolean esRolValido(String r) {
        return Rol.ADMIN.equals(r) || Rol.USUARIO.equals(r);
    }
}
