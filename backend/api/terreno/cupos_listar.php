<?php
/**
 * v4 - Lista de cargos activos con sus cupos actuales, para que
 * Jefe_Terreno vea el estado de dotacion antes de solicitar mas cupos.
 * No expone nada de datos_contratacion; solo la tabla cargos.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

iniciarSesionSegura();
requireRol(['Jefe_Terreno']);
exigirMetodo('GET');

$pdo = obtenerConexion();
$stmt = $pdo->query(
    'SELECT id, nombre_cargo, cupos_totales, cupos_activos
       FROM cargos
      WHERE activo = 1
      ORDER BY nombre_cargo ASC'
);

responderOk(['cargos' => $stmt->fetchAll(), 'obra' => OBRA_NOMBRE]);
