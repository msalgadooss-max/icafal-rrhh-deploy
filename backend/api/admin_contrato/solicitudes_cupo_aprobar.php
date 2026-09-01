<?php
/**
 * v6.9 - Administrador de Contrato aprueba una solicitud de cupo de
 * Jefe_Terreno. Recién en este momento se abre la "vacante": se suma la
 * cantidad pedida a cupos_totales/cupos_activos del cargo (antes de esto
 * el cargo no muestra esos cupos como disponibles para postular).
 *
 * v6.10 - Si la solicitud es por un cargo NUEVO (cargo_id NULL,
 * cargo_nuevo_nombre con texto), el cargo recién se crea aquí, al
 * aprobar -- nunca antes, para no llenar el catálogo con propuestas que
 * el Administrador termina rechazando.
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
        'SELECT s.estado, s.cantidad, s.cargo_id, s.cargo_nuevo_nombre, c.nombre_cargo
           FROM solicitudes_cupo s
           LEFT JOIN cargos c ON c.id = s.cargo_id
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

    $cargoId = $solicitud['cargo_id'];
    $nombreCargo = $solicitud['nombre_cargo'];

    if ($cargoId === null) {
        $nombreCargo = $solicitud['cargo_nuevo_nombre'];

        // v6.10: puede que OTRA solicitud por este mismo cargo nuevo ya
        // se haya aprobado entre que este pedido se creó y ahora (ej. dos
        // Jefe_Terreno pidiendo lo mismo) -- si el cargo ya existe, se
        // reusa y solo se le suman los cupos, en vez de crear un
        // duplicado con capitalización distinta.
        $stmtExiste = $pdo->prepare('SELECT id FROM cargos WHERE activo = 1 AND LOWER(nombre_cargo) = LOWER(:nombre)');
        $stmtExiste->execute(['nombre' => $nombreCargo]);
        $existente = $stmtExiste->fetch();

        if ($existente) {
            $cargoId = (int)$existente['id'];
            $stmtCargo = $pdo->prepare(
                'UPDATE cargos
                    SET cupos_totales = cupos_totales + :cantidad1,
                        cupos_activos = cupos_activos + :cantidad2
                  WHERE id = :cargo_id'
            );
            $stmtCargo->execute([
                'cantidad1' => $solicitud['cantidad'],
                'cantidad2' => $solicitud['cantidad'],
                'cargo_id' => $cargoId,
            ]);
        } else {
            // Cargo realmente nuevo -- se crea recien ahora, con los
            // cupos ya incluidos desde el inicio.
            $stmtCrear = $pdo->prepare(
                'INSERT INTO cargos (nombre_cargo, cupos_totales, cupos_activos, activo)
                 VALUES (:nombre, :cantidad1, :cantidad2, 1)'
            );
            $stmtCrear->execute([
                'nombre' => $nombreCargo,
                'cantidad1' => $solicitud['cantidad'],
                'cantidad2' => $solicitud['cantidad'],
            ]);
            $cargoId = (int)$pdo->lastInsertId();
        }

        $stmtLink = $pdo->prepare('UPDATE solicitudes_cupo SET cargo_id = :cargo_id WHERE id = :id');
        $stmtLink->execute(['cargo_id' => $cargoId, 'id' => $solicitudId]);
    } else {
        $stmtCargo = $pdo->prepare(
            'UPDATE cargos
                SET cupos_totales = cupos_totales + :cantidad1,
                    cupos_activos = cupos_activos + :cantidad2
              WHERE id = :cargo_id'
        );
        $stmtCargo->execute([
            'cantidad1' => $solicitud['cantidad'],
            'cantidad2' => $solicitud['cantidad'],
            'cargo_id' => $cargoId,
        ]);
    }

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

responderOk(['mensaje' => "Vacante abierta: {$solicitud['cantidad']} cupos de \"{$nombreCargo}\"."]);
