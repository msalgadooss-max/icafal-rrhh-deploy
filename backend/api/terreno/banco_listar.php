<?php
/**
 * v2 - Banco de Postulantes.
 * Gente que dejó sus datos con interés en un cargo que en ese momento
 * no tenía cupos. Estar aquí no es "estar postulando": la postulación
 * formal recién nace cuando Jefe_Terreno invita a alguien a un cupo ya
 * autorizado (ver banco_invitar.php).
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

iniciarSesionSegura();
requireRol(['Jefe_Terreno']);
exigirMetodo('GET');

$pdo = obtenerConexion();
$stmt = $pdo->query(
    'SELECT p.id, p.tipo_documento, p.rut, p.nombre_completo, p.telefono, p.comuna, p.creado_at,
            c.nombre_cargo AS cargo_interes, c.id AS cargo_interes_id
       FROM postulaciones p
       JOIN cargos c ON c.id = p.cargo_id
      WHERE p.estado = "En_banco"
      ORDER BY p.creado_at ASC'
);
$banco = array_map(function ($fila) {
    $fila['retencion_hasta'] = fechaRetencionBanco($fila['creado_at']);
    return $fila;
}, $stmt->fetchAll());

// Cargos con cupo hoy, para el selector de "invitar a...".
$stmtCupos = $pdo->query('SELECT id, nombre_cargo FROM cargos WHERE activo = 1 AND cupos_activos > 0 ORDER BY nombre_cargo');

responderOk([
    'banco' => $banco,
    'cargos_con_cupo' => $stmtCupos->fetchAll(),
]);
