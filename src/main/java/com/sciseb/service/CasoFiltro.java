package com.sciseb.service;

/** Filtros de la lista de casos. ruta: "activa" | "inactiva" | null. estado: "ACTIVO" (por defecto) | "ARCHIVADO" | "TODOS". */
public record CasoFiltro(String q, Integer provinciaId, Integer clasificacionId, String ruta, String estado, Boolean borrador) {}
