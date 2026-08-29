<?php
/**
 * v6.9 - Jefe_Terreno SOLICITA una cantidad de cupos para un cargo
 * especifico (ej. "necesito 8 Jornal Concretero"). Desde la reunion con
 * Ricardo (28-ago): esto YA NO abre cupos de inmediato -- queda
 * "Pendiente" hasta que Admin_Contrato la apruebe (ver
 * admin_contrato/solicitudes_cupo_aprobar.php). Solo una solicitud
 * APROBADA es lo que en esa reunion se llamo "vacante": recien ahi se
 * suma a cupos_totales/cupos_activos del cargo y el postulante puede
 * ver ese cargo con cupos disponibles en el formulario publico.
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

$stmtCargo = $pdo->prepare('SELECT id, nombre_cargo FROM cargos WHERE id = :id AND activo = 1');
$stmtCargo->execute(['id' => $cargoId]);
$cargo = $stmtCargo->fetch();
if (!$cargo) {
    responderError('El cargo seleccionado no existe.', 404);
}

$stmtInsert = $pdo->prepare(
    'INSERT INTO solicitudes_cupo (cargo_id, cantidad, usuario_id, estado) VALUES (:cargo_id, :cantidad, :usuario_id, "Pendiente")'
);
$stmtInsert->execute(['cargo_id' => $cargoId, 'cantidad' => $cantidad, 'usuario_id' => $usuario['id']]);

responderOk(['mensaje' => "Solicitud de $cantidad cupos para \"{$cargo['nombre_cargo']}\" enviada. Queda pendiente de aprobación del Administrador de Contrato."]);
