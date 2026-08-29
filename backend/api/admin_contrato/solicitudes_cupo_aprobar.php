<?php
/**
 * v6.9 - Administrador de Contrato aprueba una solicitud de cupo de
 * Jefe_Terreno. Recién en este momento se abre la "vacante": se suma la
 * cantidad pedida a cupos_totales/cupos_activos del cargo (antes de esto
 * el cargo no muestra esos cupos como disponibles para postular).
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

iniciarSesionSegura();
$usuario = requireRol(['Admin_Contrato']);
exigirMetodo('POST');
exigirCsrfValido();

$body = leerJsonBody();
$solicitudId = (int)($body['solicitud_id'] ?? 0);
if ($solicitudId <= 0) {
    responderError('solicitud_id inválido.', 422);
}

$pdo = obtenerConexion();
$pdo->beginTransaction();

try {
    $stmtCheck = $pdo->prepare(
        'SELECT s.estado, s.cantidad, s.cargo_id, c.nombre_cargo
           FROM solicitudes_cupo s
           JOIN cargos c ON c.id = s.cargo_id
          WHERE s.id = :id
          FOR UPDATE'
    );
    $stmtCheck->execute(['id' => $solicitudId]);
    $solicitud = $stmtCheck->fetch();

    if (!$solicitud) {
        throw new RuntimeException('Solicitud no encontrada.|404');
    }
    if ($solicitud['estado'] !== 'Pendiente') {
        throw new RuntimeException('Esta solicitud ya fue resuelta.|409');
    }

    $stmtCargo = $pdo->prepare(
        'UPDATE cargos
            SET cupos_totales = cupos_totales + :cantidad1,
                cupos_activos = cupos_activos + :cantidad2
          WHERE id = :cargo_id'
    );
    $stmtCargo->execute([
        'cantidad1' => $solicitud['cantidad'],
        'cantidad2' => $solicitud['cantidad'],
        'cargo_id' => $solicitud['cargo_id'],
    ]);

    $stmtUpdate = $pdo->prepare(
        'UPDATE solicitudes_cupo
            SET estado = "Aprobada", resuelta_por = :uid, resuelta_at = NOW()
          WHERE id = :id'
    );
    $stmtUpdate->execute(['uid' => $usuario['id'], 'id' => $solicitudId]);

    $pdo->commit();
} catch (RuntimeException $e) {
    $pdo->rollBack();
    [$mensaje, $status] = explode('|', $e->getMessage());
    responderError($mensaje, (int)$status);
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('solicitudes_cupo_aprobar error: ' . $e->getMessage());
    responderError('No fue posible aprobar la solicitud.', 500);
}

responderOk(['mensaje' => "Vacante abierta: {$solicitud['cantidad']} cupos de \"{$solicitud['nombre_cargo']}\"."]);
