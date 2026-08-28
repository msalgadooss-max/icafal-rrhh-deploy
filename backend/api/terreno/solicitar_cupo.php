<?php
/**
 * v4 - Jefe_Terreno solicita una cantidad de cupos para un cargo
 * especifico (ej. "necesito 8 Jornal Concretero"). Esto es lo que
 * determina lo que ve el postulante en el formulario publico
 * ("6 cupos disponibles"): sin una solicitud, un cargo activo parte
 * siempre con cupos_totales=0 / cupos_activos=0.
 *
 * Cada solicitud queda registrada en solicitudes_cupo (bitacora a nivel
 * de cargo, separada de trazabilidad_logs que es por postulacion) y
 * SUMA la cantidad pedida a cupos_totales y cupos_activos del cargo
 * (no reemplaza el valor -- si ya habia 6 y se piden 8 mas, quedan 14).
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

iniciarSesionSegura();
$usuario = requireRol(['Jefe_Terreno']);
exigirMetodo('POST');
exigirCsrfValido();

$body = leerJsonBody();
$cargoId = (int)($body['cargo_id'] ?? 0);
$cantidad = (int)($body['cantidad'] ?? 0);

if ($cargoId <= 0) {
    responderError('Debes seleccionar un cargo.', 422);
}
if ($cantidad <= 0 || $cantidad > 500) {
    responderError('La cantidad de cupos debe ser un número entre 1 y 500.', 422);
}

$pdo = obtenerConexion();
$pdo->beginTransaction();

try {
    $stmtCargo = $pdo->prepare('SELECT id, nombre_cargo FROM cargos WHERE id = :id AND activo = 1 FOR UPDATE');
    $stmtCargo->execute(['id' => $cargoId]);
    $cargo = $stmtCargo->fetch();
    if (!$cargo) {
        throw new RuntimeException('El cargo seleccionado no existe.|404');
    }

    $stmtUpdate = $pdo->prepare(
        'UPDATE cargos
            SET cupos_totales = cupos_totales + :cantidad1,
                cupos_activos = cupos_activos + :cantidad2
          WHERE id = :id'
    );
    $stmtUpdate->execute(['cantidad1' => $cantidad, 'cantidad2' => $cantidad, 'id' => $cargoId]);

    $stmtInsert = $pdo->prepare(
        'INSERT INTO solicitudes_cupo (cargo_id, cantidad, usuario_id) VALUES (:cargo_id, :cantidad, :usuario_id)'
    );
    $stmtInsert->execute(['cargo_id' => $cargoId, 'cantidad' => $cantidad, 'usuario_id' => $usuario['id']]);

    $pdo->commit();
} catch (RuntimeException $e) {
    $pdo->rollBack();
    [$mensaje, $status] = explode('|', $e->getMessage());
    responderError($mensaje, (int)$status);
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('terreno/solicitar_cupo error: ' . $e->getMessage());
    responderError('No fue posible registrar la solicitud de cupos.', 500);
}

responderOk(['mensaje' => "Se agregaron $cantidad cupos a \"{$cargo['nombre_cargo']}\"."]);
