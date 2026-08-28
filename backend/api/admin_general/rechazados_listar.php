<?php
/**
 * v6 - Lista de solo lectura de todas las postulaciones 'Rechazado',
 * para que el JAO tenga visibilidad completa del embudo (contratados,
 * rechazados y pendientes) sin tener que pedirle el dato a Terreno.
 * El motivo (si Terreno lo escribió) se lee de trazabilidad_logs.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

iniciarSesionSegura();
requireRol(['Jefe_Administrativo']);
exigirMetodo('GET');

$pdo = obtenerConexion();
$stmt = $pdo->query(
    "SELECT p.id, p.tipo_documento, p.rut, p.nombre_completo, c.nombre_cargo, p.actualizado_at
       FROM postulaciones p
       JOIN cargos c ON c.id = p.cargo_id
      WHERE p.estado = 'Rechazado'
      ORDER BY p.actualizado_at DESC"
);
$postulaciones = $stmt->fetchAll();

$ids = array_column($postulaciones, 'id');
$motivos = [];
if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmtMotivos = $pdo->prepare(
        "SELECT postulacion_id, accion FROM trazabilidad_logs
          WHERE postulacion_id IN ($in) AND accion LIKE 'Motivo de rechazo:%'
          ORDER BY fecha_hora DESC"
    );
    $stmtMotivos->execute($ids);
    foreach ($stmtMotivos->fetchAll() as $fila) {
        if (!isset($motivos[$fila['postulacion_id']])) {
            $motivos[$fila['postulacion_id']] = trim(str_replace('Motivo de rechazo:', '', $fila['accion']));
        }
    }
}

foreach ($postulaciones as &$p) {
    $p['motivo'] = $motivos[$p['id']] ?? null;
}
unset($p);

responderOk(['postulaciones' => $postulaciones]);
