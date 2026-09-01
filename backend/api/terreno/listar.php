<?php
/**
 * Fase 1 - Dashboard Jefe de Terreno / Capataz.
 * IMPORTANTE: esta consulta NUNCA hace JOIN con datos_contratacion.
 * El Jefe de Terreno solo debe ver los datos publicos de la
 * postulacion (los mismos que llenó el postulante en Fase 0).
 *
 * v7: la selección en terreno pasó a ser SECUENCIAL en dos pasos
 * (reunión Ricardo, 31-ago) -- Jefe_Terreno y Capataz ya NO son
 * intercambiables viendo la misma lista:
 *   - Jefe_Terreno ve las postulaciones que aún nadie ha filtrado
 *     (aprobado_jt_at IS NULL) y hace el primer filtro.
 *   - Capataz ve solo lo que Jefe_Terreno ya aprobó
 *     (aprobado_jt_at IS NOT NULL) y hace la selección final en
 *     persona, en portería.
 * Ambas listas comparten estado='Pendiente' porque el primer filtro de
 * Jefe_Terreno NO cambia el estado -- solo el Capataz, al seleccionar,
 * hace avanzar a 'Pre_aprobado_terreno' (ver terreno/aprobar.php).
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

iniciarSesionSegura();
$usuario = requireRol(['Jefe_Terreno', 'Capataz']);
exigirMetodo('GET');

$pdo = obtenerConexion();

$filtroPaso = $usuario['rol'] === 'Capataz'
    ? 'p.aprobado_jt_at IS NOT NULL'
    : 'p.aprobado_jt_at IS NULL';

$stmt = $pdo->prepare(
    "SELECT p.id, p.tipo_documento, p.rut, p.nombre_completo, p.telefono, p.correo, p.comuna,
            c.nombre_cargo, p.creado_at,
            (p.cv_ruta_archivo IS NOT NULL) AS tiene_cv,
            p.experiencia_sin_cv,
            p.aprobado_jt_at, uj.nombre AS aprobado_jt_por_nombre
       FROM postulaciones p
       JOIN cargos c ON c.id = p.cargo_id
       LEFT JOIN usuarios uj ON uj.id = p.aprobado_jt_por
      WHERE p.estado = 'Pendiente' AND $filtroPaso
      ORDER BY p.creado_at ASC"
);
$stmt->execute();

$postulaciones = array_map(function ($p) {
    $p['tiene_cv'] = (bool)$p['tiene_cv'];
    return $p;
}, $stmt->fetchAll());

responderOk([
    'postulaciones' => $postulaciones,
    'aprobaciones_hoy' => contarAprobacionesHoy($pdo, $usuario['id']),
    'limite_aprobaciones_diarias' => LIMITE_APROBACIONES_DIARIAS_TERRENO,
]);
