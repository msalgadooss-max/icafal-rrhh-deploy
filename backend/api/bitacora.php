<?php
/**
 * v5 - Bitácora de actividad en lenguaje natural: últimos eventos de
 * trazabilidad_logs a través de TODAS las postulaciones, redactados
 * como frases legibles (ver includes/functions.php::traducirAccionLog).
 * Solo nombre, cargo, quién hizo la acción y cuándo -- nunca datos de
 * datos_contratacion, así que es seguro para cualquier rol interno.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

iniciarSesionSegura();
requireRol(['Jefe_Terreno', 'Admin_Contrato', 'Prevencionista', 'Jefe_Bodega', 'Jefe_Administrativo', 'Gerencia', 'Desarrollador']);
exigirMetodo('GET');

$pdo = obtenerConexion();
$stmt = $pdo->query(
    "SELECT l.accion, l.fecha_hora, p.nombre_completo, c.nombre_cargo, u.nombre AS usuario_nombre
       FROM trazabilidad_logs l
       JOIN postulaciones p ON p.id = l.postulacion_id
       JOIN cargos c ON c.id = p.cargo_id
       LEFT JOIN usuarios u ON u.id = l.usuario_id
      ORDER BY l.fecha_hora DESC, l.id DESC
      LIMIT 40"
);

$eventos = array_map(function ($e) {
    return [
        'nombre_completo' => $e['nombre_completo'],
        'nombre_cargo'    => $e['nombre_cargo'],
        'autor'           => $e['usuario_nombre'] ?? 'El propio postulante',
        'descripcion'     => traducirAccionLog($e['accion']),
        'fecha_hora'      => $e['fecha_hora'],
    ];
}, $stmt->fetchAll());

responderOk(['eventos' => $eventos]);
