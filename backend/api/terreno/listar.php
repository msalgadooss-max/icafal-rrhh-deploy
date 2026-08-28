<?php
/**
 * Fase 1 - Dashboard Jefe de Terreno.
 * IMPORTANTE: esta consulta NUNCA hace JOIN con datos_contratacion.
 * El Jefe de Terreno solo debe ver los datos publicos de la
 * postulacion (los mismos que llenó el postulante en Fase 0).
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

iniciarSesionSegura();
$usuario = requireRol(['Jefe_Terreno']);
exigirMetodo('GET');

$pdo = obtenerConexion();

$stmt = $pdo->query(
    'SELECT p.id, p.tipo_documento, p.rut, p.nombre_completo, p.telefono, p.correo, p.comuna,
            c.nombre_cargo, p.creado_at,
            (p.cv_ruta_archivo IS NOT NULL) AS tiene_cv
       FROM postulaciones p
       JOIN cargos c ON c.id = p.cargo_id
      WHERE p.estado = "Pendiente"
      ORDER BY p.creado_at ASC'
);

$postulaciones = array_map(function ($p) {
    $p['tiene_cv'] = (bool)$p['tiene_cv'];
    return $p;
}, $stmt->fetchAll());

responderOk([
    'postulaciones' => $postulaciones,
    'aprobaciones_hoy' => contarAprobacionesHoy($pdo, $usuario['id']),
    'limite_aprobaciones_diarias' => LIMITE_APROBACIONES_DIARIAS_TERRENO,
]);
