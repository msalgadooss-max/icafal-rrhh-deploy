<?php
/**
 * v6.9 - Historial de las solicitudes de cupo que ha hecho ESTE
 * Jefe_Terreno, con su estado (Pendiente/Aprobada/Rechazada) -- para que
 * sepa si el Administrador de Contrato ya resolvió su pedido, sin tener
 * que preguntar. Ver admin_contrato/solicitudes_cupo_aprobar.php y
 * .../solicitudes_cupo_rechazar.php.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

iniciarSesionSegura();
$usuario = requireRol(['Jefe_Terreno']);
exigirMetodo('GET');

$pdo = obtenerConexion();
$stmt = $pdo->prepare(
    'SELECT s.id, s.cantidad, s.estado, s.motivo_rechazo, s.creado_at, s.resuelta_at,
            c.nombre_cargo,
            uR.nombre AS resuelta_por_nombre
       FROM solicitudes_cupo s
       JOIN cargos c ON c.id = s.cargo_id
       LEFT JOIN usuarios uR ON uR.id = s.resuelta_por
      WHERE s.usuario_id = :uid
      ORDER BY s.creado_at DESC
      LIMIT 50'
);
$stmt->execute(['uid' => $usuario['id']]);

responderOk(['solicitudes' => $stmt->fetchAll()]);
