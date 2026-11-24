package com.sciseb.service;

import com.sciseb.config.ApiException;
import com.sciseb.config.Sesion;
import com.sciseb.domain.*;
import com.sciseb.dto.Dto.*;
import com.sciseb.repo.*;
import com.sciseb.util.CasoValidador;
import com.sciseb.util.CasoValidador.Datos;
import jakarta.persistence.EntityManager;
import jakarta.persistence.PersistenceContext;
import lombok.RequiredArgsConstructor;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.PageRequest;
import org.springframework.data.domain.Sort;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

import java.time.LocalDate;
import java.time.LocalDateTime;
import java.util.*;

@Service
@RequiredArgsConstructor
public class CasoService {
    private final CasoRepository casos;
    private final AgresorRepository agresores;
    private final SedeRepository sedes;
    private final ClasificacionRepository clasificaciones;
    private final PasoRutaRepository pasosCatalogo;
    private final CasoPasoRepository casoPasos;
    private final UsuarioRepository usuarios;
    private final AuditoriaService auditoria;

    @PersistenceContext
    private EntityManager em;

    // ------------------------------------------------------------------ consulta

    @Transactional(readOnly = true)
    public PaginaCasos listar(CasoFiltro filtro, int pagina, int tamano) {
        int size = Math.max(1, Math.min(tamano, 100));
        int page = Math.max(0, pagina);
        Page<Caso> p = casos.findAll(CasoSpecs.desde(filtro),
                PageRequest.of(page, size, Sort.by(Sort.Direction.DESC, "fechaUltimaEdicion")));
        Map<Long, Integer> efectuados = contarEfectuados(p.getContent().stream().map(Caso::getId).toList());
        List<CasoResumen> filas = p.getContent().stream()
                .map(c -> resumen(c, efectuados.getOrDefault(c.getId(), 0))).toList();
        return new PaginaCasos(filas, p.getTotalElements(), page, size);
    }

    public Map<Long, Integer> contarEfectuados(Collection<Long> ids) {
        Map<Long, Integer> m = new HashMap<>();
        if (ids.isEmpty()) return m;
        for (Object[] fila : casoPasos.contarEfectuados(ids)) {
            m.put((Long) fila[0], ((Number) fila[1]).intValue());
        }
        return m;
    }

    static CasoResumen resumen(Caso c, int pasosEfectuados) {
        var sede = c.getSede();
        var ie = sede == null ? null : sede.getInstitucion();
        var mun = ie == null ? null : ie.getMunicipio();
        var agresor = c.getAgresor();
        var cl = c.getClasificacion();
        boolean esBorrador = agresor == null && c.getNombreAgresorBorrador() != null;
        return new CasoResumen(c.getId(), c.getIdCaso(),
                agresor != null ? agresor.getNombre() : c.getNombreAgresorBorrador(),
                agresor == null ? null : CasoValidador.enmascararCedula(agresor.getCedula()), esBorrador,
                c.getVictimas().stream().map(Victima::getIniciales).toList(),
                ie == null ? null : ie.getNombre(), sede == null ? null : sede.getNombre(),
                mun == null ? null : mun.getNombre(), mun == null ? null : mun.getProvincia().getNombre(),
                cl == null ? null : cl.getNombre(), c.isRutaActiva(), c.getEstado().name(), pasosEfectuados,
                c.getFechaUltimaEdicion(), c.getEditadoPor().getNombre());
    }

    @Transactional(readOnly = true)
    public CasoDetalle obtener(Long id) {
        return detalle(buscar(id));
    }

    @Transactional(readOnly = true)
    public Indicadores indicadores() {
        EstadoCaso a = EstadoCaso.ACTIVO;
        return new Indicadores(casos.countByEstado(a), casos.countByEstadoAndRutaActiva(a, true),
                casos.contarPorClasificacion(a, "Tipo I"), casos.contarPorClasificacion(a, "Tipo III"),
                casos.contarRutaCompleta(a));
    }

    Caso buscar(Long id) {
        return casos.findById(id).orElseThrow(() -> ApiException.noEncontrado("No existe el caso " + id + "."));
    }

    Caso buscarEditable(Long id) {
        Caso c = buscar(id);
        if (c.getEstado() == EstadoCaso.ARCHIVADO) {
            throw ApiException.conflicto("CASO_ARCHIVADO",
                    "El caso está archivado y no se puede modificar. Pida al Administrador que lo reactive.");
        }
        return c;
    }

    CasoDetalle detalle(Caso c) {
        List<CasoPaso> cps = casoPasos.listarPorCaso(c.getId());
        List<PasoRes> pasos = cps.stream().map(cp -> new PasoRes(cp.getPaso().getId(), cp.getPaso().getNombre(),
                cp.isEfectuado(), cp.getUsuario() == null ? null : cp.getUsuario().getNombre(),
                cp.getUsuario() == null ? null : cp.getUsuario().getArea().getNombre(),
                cp.getFechaEfectuado(), cp.getNota())).toList();
        int hechos = (int) pasos.stream().filter(PasoRes::efectuado).count();
        var agresor = c.getAgresor();
        var sede = c.getSede();
        var ie = sede == null ? null : sede.getInstitucion();
        var mun = ie == null ? null : ie.getMunicipio();
        var prov = mun == null ? null : mun.getProvincia();
        var cl = c.getClasificacion();
        boolean esBorrador = agresor == null && c.getNombreAgresorBorrador() != null;
        return new CasoDetalle(c.getId(), c.getIdCaso(),
                agresor != null ? agresor.getNombre() : c.getNombreAgresorBorrador(),
                agresor == null ? null : agresor.getCedula(), esBorrador,
                agresor == null ? null : agresor.getAreaAsignatura(),
                c.getVictimas().stream().map(Victima::getIniciales).toList(),
                prov == null ? null : prov.getId(), prov == null ? null : prov.getNombre(),
                mun == null ? null : mun.getId(), mun == null ? null : mun.getNombre(),
                ie == null ? null : ie.getId(), ie == null ? null : ie.getNombre(),
                sede == null ? null : sede.getId(), sede == null ? null : sede.getNombre(),
                cl == null ? null : cl.getId(), cl == null ? null : cl.getNombre(),
                c.isRutaActiva(), c.getNRadicado(), c.getResolucionSeb(), c.getResolucionRectoral(),
                c.getUbicacionAgresor(), c.getFechaOcurrencia(), c.getLugarOcurrencia(), c.getDisciplinario(),
                c.getDecisionComite(), c.getPenal(), c.getEstado().name(), c.getVersion(),
                c.getCreadoPor().getNombre(), c.getCreadoPor().getArea().getNombre(), c.getFechaCreacion(),
                c.getEditadoPor().getNombre(), c.getEditadoPor().getArea().getNombre(), c.getFechaUltimaEdicion(),
                pasos, hechos);
    }

    // ------------------------------------------------------------------ registro y edición

    static Datos datosDe(CasoReq r) {
        List<String> victimas = r.victimas() == null ? List.of() : r.victimas().stream()
                .map(CasoValidador::normalizarIniciales).filter(Objects::nonNull).toList();
        return new Datos(
                CasoValidador.limpiar(r.idCaso()),
                CasoValidador.limpiar(r.presuntoAgresor()),
                victimas,
                CasoValidador.limpiar(r.cedula()),
                CasoValidador.limpiar(r.areaAsignatura()),
                CasoValidador.limpiar(r.nRadicado()),
                CasoValidador.limpiar(r.resolucionSeb()),
                CasoValidador.limpiar(r.resolucionRectoral()),
                CasoValidador.limpiar(r.ubicacionAgresor()),
                r.fechaOcurrencia(),
                CasoValidador.limpiar(r.lugarOcurrencia()),
                CasoValidador.limpiar(r.disciplinario()),
                CasoValidador.limpiar(r.decisionComite()),
                CasoValidador.limpiar(r.penal()));
    }

    @Transactional
    public CasoDetalle crear(Sesion s, CasoReq r) {
        Datos d = datosDe(r);
        Map<String, String> errores = new LinkedHashMap<>(CasoValidador.validar(d, LocalDate.now()));
        Sede sede = null;
        if (r.sedeId() != null) {
            sede = sedes.findById(r.sedeId()).orElse(null);
            if (sede == null) errores.put("sedeId", "La sede indicada no existe.");
        }
        ClasificacionSiuce cl = null;
        if (r.clasificacionId() != null) {
            cl = clasificaciones.findById(r.clasificacionId()).orElse(null);
            if (cl == null) errores.put("clasificacionId", "La clasificación indicada no existe.");
        }
        if (d.idCaso() != null && !errores.containsKey("idCaso") && casos.existsByIdCaso(d.idCaso()))
            errores.put("idCaso", "Ya existe un caso con ese ID.");
        if (!errores.isEmpty()) throw ApiException.validacion(errores);

        Usuario yo = usuarios.getReferenceById(s.id());
        Caso c = persistirNuevo(yo, d, sede, cl, Boolean.TRUE.equals(r.rutaActiva()),
                Boolean.TRUE.equals(r.confirmarNombreDistinto()));
        auditoria.registrar(s.id(), c.getId(), "CREAR_CASO", null);
        return detalle(c);
    }

    /** Crea el caso, su presunto agresor (o reutiliza el existente) y los 12 pasos pendientes. Sin auditoría. */
    public Caso persistirNuevo(Usuario creador, Datos d, Sede sede, ClasificacionSiuce cl, boolean rutaActiva,
                        boolean permitirNombreDistinto) {
        // Sin cédula no se puede crear el presunto agresor (la tabla exige ambos datos): si se dio un
        // nombre, queda como borrador hasta que se complete la cédula (editando el caso más adelante).
        PresuntoAgresor ag = d.cedula() == null ? null
                : resolverAgresor(d.presuntoAgresor(), d.cedula(), d.areaAsignatura(), permitirNombreDistinto);
        LocalDateTime ahora = LocalDateTime.now();
        Caso c = new Caso();
        c.setIdCaso(d.idCaso());
        c.setAgresor(ag);
        c.setNombreAgresorBorrador(ag == null ? d.presuntoAgresor() : null);
        c.setSede(sede);
        c.setClasificacion(cl);
        c.setRutaActiva(rutaActiva);
        c.setNRadicado(d.nRadicado());
        c.setResolucionSeb(d.resolucionSeb());
        c.setResolucionRectoral(d.resolucionRectoral());
        c.setUbicacionAgresor(d.ubicacionAgresor());
        c.setFechaOcurrencia(d.fechaOcurrencia());
        c.setLugarOcurrencia(d.lugarOcurrencia());
        c.setDisciplinario(d.disciplinario());
        c.setDecisionComite(d.decisionComite());
        c.setPenal(d.penal());
        c.setEstado(EstadoCaso.ACTIVO);
        c.setCreadoPor(creador);
        c.setFechaCreacion(ahora);
        c.setEditadoPor(creador);
        c.setFechaUltimaEdicion(ahora);
        casos.save(c);
        for (String iniciales : d.victimas()) {
            c.getVictimas().add(new Victima(c, iniciales));
        }
        for (PasoRuta p : pasosCatalogo.findAllByOrderByIdAsc()) {
            em.persist(new CasoPaso(c, p));
        }
        return c;
    }

    private static String sinTildes(String s) {
        return java.text.Normalizer.normalize(s == null ? "" : s, java.text.Normalizer.Form.NFD)
                .replaceAll("\\p{M}", "").toLowerCase(Locale.ROOT).trim();
    }

    /**
     * Reutiliza al presunto agresor cuando la cédula ya existe (DAD, RF-007). Si la cédula es nueva,
     * el nombre pasa a ser obligatorio (la tabla presunto_agresor exige ambos datos a la vez).
     */
    PresuntoAgresor resolverAgresor(String nombre, String cedula, String area, boolean permitirNombreDistinto) {
        Optional<PresuntoAgresor> existente = agresores.findByCedula(cedula);
        if (existente.isPresent()) {
            PresuntoAgresor a = existente.get();
            if (nombre != null && !sinTildes(a.getNombre()).equals(sinTildes(nombre)) && !permitirNombreDistinto) {
                throw ApiException.conflicto("NOMBRE_DISTINTO", "Esa cédula ya está registrada a nombre de «"
                        + a.getNombre() + "». Verifique la cédula o confirme que se trata de la misma persona.");
            }
            if (area != null && a.getAreaAsignatura() == null) a.setAreaAsignatura(area);
            return a;
        }
        if (nombre == null) {
            throw ApiException.validacion(Map.of("presuntoAgresor",
                    "Esta cédula no está registrada. Escriba el nombre del presunto agresor para registrarlo."));
        }
        PresuntoAgresor a = new PresuntoAgresor();
        a.setNombre(nombre);
        a.setCedula(cedula);
        a.setAreaAsignatura(area);
        return agresores.save(a);
    }

    /** Autocompletado del formulario: busca un presunto agresor ya registrado por cédula o por nombre. */
    @Transactional(readOnly = true)
    public AgresorRes buscarAgresor(String cedula, String nombre) {
        Optional<PresuntoAgresor> encontrado = cedula != null ? agresores.findByCedula(cedula)
                : nombre != null ? agresores.findFirstByNombreIgnoreCase(nombre) : Optional.empty();
        return encontrado.map(a -> new AgresorRes(a.getNombre(), a.getCedula(), a.getAreaAsignatura())).orElse(null);
    }

    @Transactional
    public CasoDetalle actualizar(Sesion s, Long id, CasoReq r) {
        Caso c = buscarEditable(id);
        if (r.version() == null || !r.version().equals(c.getVersion())) {
            throw ApiException.conflicto("CONFLICTO_VERSION",
                    "Otra persona modificó este caso mientras usted lo editaba. Recargue la página y vuelva a intentarlo.");
        }
        Datos d = datosDe(r);
        Map<String, String> errores = new LinkedHashMap<>(CasoValidador.validar(d, LocalDate.now()));
        Sede sede = null;
        if (r.sedeId() != null) {
            sede = sedes.findById(r.sedeId()).orElse(null);
            if (sede == null) errores.put("sedeId", "La sede indicada no existe.");
        }
        ClasificacionSiuce cl = null;
        if (r.clasificacionId() != null) {
            cl = clasificaciones.findById(r.clasificacionId()).orElse(null);
            if (cl == null) errores.put("clasificacionId", "La clasificación indicada no existe.");
        }
        if (d.idCaso() != null && !errores.containsKey("idCaso") && !d.idCaso().equals(c.getIdCaso())
                && casos.existsByIdCaso(d.idCaso()))
            errores.put("idCaso", "Ya existe un caso con ese ID.");
        if (!errores.isEmpty()) throw ApiException.validacion(errores);

        List<String> cambios = new ArrayList<>();
        marcar(cambios, "idCaso", c.getIdCaso(), d.idCaso());
        c.setIdCaso(d.idCaso());
        PresuntoAgresor actual = c.getAgresor();
        if (d.cedula() == null) {
            // sin cédula: no hay (ni puede haber) agresor resuelto; el nombre, si se dio, queda como borrador
            if (actual != null) { c.setAgresor(null); cambios.add("presuntoAgresor"); }
            if (!Objects.equals(c.getNombreAgresorBorrador(), d.presuntoAgresor())) {
                c.setNombreAgresorBorrador(d.presuntoAgresor());
                if (actual == null) cambios.add("presuntoAgresor");
            }
        } else if (actual != null && actual.getCedula().equals(d.cedula())) {
            // misma persona: se corrige su nombre (si se dio) o área (el cambio vale para todos sus casos)
            if (d.presuntoAgresor() != null && !actual.getNombre().equals(d.presuntoAgresor())) { actual.setNombre(d.presuntoAgresor()); cambios.add("presuntoAgresor"); }
            if (!Objects.equals(actual.getAreaAsignatura(), d.areaAsignatura())) { actual.setAreaAsignatura(d.areaAsignatura()); cambios.add("areaAsignatura"); }
        } else {
            // antes sin agresor (quizás con nombre en borrador), o cambia de cédula
            c.setAgresor(resolverAgresor(d.presuntoAgresor(), d.cedula(), d.areaAsignatura(),
                    Boolean.TRUE.equals(r.confirmarNombreDistinto())));
            c.setNombreAgresorBorrador(null);
            cambios.add("cedula");
        }
        marcar(cambios, "sede", c.getSede() == null ? null : c.getSede().getId(), sede == null ? null : sede.getId());
        marcar(cambios, "clasificacion", c.getClasificacion() == null ? null : c.getClasificacion().getId(),
                cl == null ? null : cl.getId());
        List<String> victimasActuales = c.getVictimas().stream().map(Victima::getIniciales).toList();
        if (!victimasActuales.equals(d.victimas())) {
            cambios.add("victimas");
            c.getVictimas().clear();
            d.victimas().forEach(ini -> c.getVictimas().add(new Victima(c, ini)));
        }
        marcar(cambios, "rutaActiva", c.isRutaActiva(), Boolean.TRUE.equals(r.rutaActiva()));
        marcar(cambios, "nRadicado", c.getNRadicado(), d.nRadicado());
        marcar(cambios, "resolucionSeb", c.getResolucionSeb(), d.resolucionSeb());
        marcar(cambios, "resolucionRectoral", c.getResolucionRectoral(), d.resolucionRectoral());
        marcar(cambios, "ubicacionAgresor", c.getUbicacionAgresor(), d.ubicacionAgresor());
        marcar(cambios, "fechaOcurrencia", c.getFechaOcurrencia(), d.fechaOcurrencia());
        marcar(cambios, "lugarOcurrencia", c.getLugarOcurrencia(), d.lugarOcurrencia());
        marcar(cambios, "disciplinario", c.getDisciplinario(), d.disciplinario());
        marcar(cambios, "decisionComite", c.getDecisionComite(), d.decisionComite());
        marcar(cambios, "penal", c.getPenal(), d.penal());

        c.setSede(sede);
        c.setClasificacion(cl);
        c.setRutaActiva(Boolean.TRUE.equals(r.rutaActiva()));
        c.setNRadicado(d.nRadicado());
        c.setResolucionSeb(d.resolucionSeb());
        c.setResolucionRectoral(d.resolucionRectoral());
        c.setUbicacionAgresor(d.ubicacionAgresor());
        c.setFechaOcurrencia(d.fechaOcurrencia());
        c.setLugarOcurrencia(d.lugarOcurrencia());
        c.setDisciplinario(d.disciplinario());
        c.setDecisionComite(d.decisionComite());
        c.setPenal(d.penal());
        tocar(c, s);
        casos.saveAndFlush(c);
        auditoria.registrar(s.id(), id, "EDITAR_CASO", cambios.isEmpty() ? "sin cambios" : "campos: " + String.join(", ", cambios));
        return detalle(c);
    }

    private static void marcar(List<String> cambios, String campo, Object antes, Object despues) {
        if (!Objects.equals(antes, despues)) cambios.add(campo);
    }

    /** Registra quién y cuándo modificó el caso por última vez (también cuando cambia algo de su ruta). */
    void tocar(Caso c, Sesion s) {
        c.setEditadoPor(usuarios.getReferenceById(s.id()));
        c.setFechaUltimaEdicion(LocalDateTime.now());
    }

    // ------------------------------------------------------------------ ruta de atención

    @Transactional
    public CasoDetalle cambiarRuta(Sesion s, Long id, boolean activa) {
        Caso c = buscarEditable(id);
        if (c.isRutaActiva() != activa) {
            c.setRutaActiva(activa);
            tocar(c, s);
            casos.saveAndFlush(c);
            auditoria.registrar(s.id(), id, activa ? "ACTIVAR_RUTA" : "DESACTIVAR_RUTA", null);
        }
        return detalle(c);
    }

    @Transactional
    public CasoDetalle actualizarPaso(Sesion s, Long id, int numeroPaso, PasoReq r) {
        Caso c = buscarEditable(id);
        CasoPaso cp = casoPasos.findById(new CasoPasoId(id, numeroPaso))
                .orElseThrow(() -> ApiException.noEncontrado("El paso " + numeroPaso + " no existe."));
        String accion = null;
        if (r.efectuado() != null && r.efectuado() != cp.isEfectuado()) {
            cp.setEfectuado(r.efectuado());
            cp.setUsuario(r.efectuado() ? usuarios.getReferenceById(s.id()) : null);
            cp.setFechaEfectuado(r.efectuado() ? LocalDateTime.now() : null);
            accion = r.efectuado() ? "PASO_EFECTUADO" : "PASO_PENDIENTE";
        }
        if (r.nota() != null) {
            String nota = CasoValidador.limpiar(r.nota());
            if (nota != null && nota.length() > 1000)
                throw ApiException.validacion(Map.of("nota", "La nota no puede superar 1000 caracteres."));
            if (!Objects.equals(nota, cp.getNota())) {
                cp.setNota(nota);
                if (accion == null) accion = "NOTA_PASO";
            }
        }
        if (accion != null) {
            casoPasos.saveAndFlush(cp);
            tocar(c, s);
            casos.saveAndFlush(c);
            auditoria.registrar(s.id(), id, accion, "paso " + numeroPaso);
        }
        return detalle(c);
    }

    // ------------------------------------------------------------------ archivo

    @Transactional
    public CasoDetalle archivar(Sesion s, Long id, String motivo) {
        String m = CasoValidador.limpiar(motivo);
        if (m == null || m.length() < 5 || m.length() > 300)
            throw ApiException.validacion(Map.of("motivo", "Escriba el motivo (de 5 a 300 caracteres)."));
        Caso c = buscar(id);
        if (c.getEstado() == EstadoCaso.ARCHIVADO)
            throw ApiException.conflicto("YA_ARCHIVADO", "El caso ya está archivado.");
        c.setEstado(EstadoCaso.ARCHIVADO);
        tocar(c, s);
        casos.saveAndFlush(c);
        auditoria.registrar(s.id(), id, "ARCHIVAR_CASO", m);
        return detalle(c);
    }

    @Transactional
    public CasoDetalle reactivar(Sesion s, Long id, String motivo) {
        String m = CasoValidador.limpiar(motivo);
        if (m == null || m.length() < 5 || m.length() > 300)
            throw ApiException.validacion(Map.of("motivo", "Escriba el motivo (de 5 a 300 caracteres)."));
        Caso c = buscar(id);
        if (c.getEstado() == EstadoCaso.ACTIVO)
            throw ApiException.conflicto("YA_ACTIVO", "El caso ya está activo.");
        c.setEstado(EstadoCaso.ACTIVO);
        tocar(c, s);
        casos.saveAndFlush(c);
        auditoria.registrar(s.id(), id, "REACTIVAR_CASO", m);
        return detalle(c);
    }
}
