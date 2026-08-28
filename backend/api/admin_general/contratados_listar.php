<?php
/**
 * v4 - Lista de TODAS las postulaciones en estado 'Contratado' (ya
 * exportadas o no), para que el JAO elija con checkboxes exactamente a
 * quiénes incluir en la próxima exportación a Buk (ver
 * exportar_excel.php, que ahora acepta una lista de IDs en vez de
 * exportar siempre todo lo pendiente automáticamente).
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

iniciarSesionSegura();
requireRol(['Jefe_Administrativo']);
exigirMetodo('GET');

$pdo = obtenerConexion();
$stmt = $pdo->query(
    'SELECT p.id, p.tipo_documento, p.rut, p.nombre_completo, c.nombre_cargo,
            p.exportado_at, p.actualizado_at
       FROM postulaciones p
       JOIN cargos c ON c.id = p.cargo_id
      WHERE p.estado = "Contratado"
      ORDER BY p.exportado_at IS NOT NULL ASC, p.actualizado_at DESC'
);

responderOk(['postulaciones' => $stmt->fetchAll()]);
