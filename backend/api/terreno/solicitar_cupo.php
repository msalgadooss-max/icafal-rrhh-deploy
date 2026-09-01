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
 *
 * v6.10 - Si el cargo que necesita no esta en el catalogo, puede pedir
 * uno nuevo escribiendo el nombre (cargo_nuevo_nombre) en vez de elegir
 * un cargo_id existente. El cargo real NO se crea aqui -- se crea recien
 * al aprobar (para no llenar el catalogo con propuestas que nunca se
 * aprueban). Si el nombre escrito coincide con uno ya existente
 * (sin importar mayusculas/tildes de caja), se reusa ese cargo en vez de
 * proponer uno duplicado.
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
$cargoNuevoNombre = limpiarTexto($body['cargo_nuevo_nombre'] ?? '', 100);
$cantidad = (int)($body['cantidad'] ?? 0);

if ($cargoId <= 0 && $cargoNuevoNombre === '') {
    responderError('Debes seleccionar un cargo o escribir el nombre de uno nuevo.', 422);
}
if ($cargoId > 0 && $cargoNuevoNombre !== '') {
    responderError('Elige un cargo de la lista o escribe uno nuevo, no ambos.', 422);
}
if ($cantidad <= 0 || $cantidad > 500) {
    responderError('La cantidad de cupos debe ser un número entre 1 y 500.', 422);
}

$pdo = obtenerConexion();

if ($cargoId > 0) {
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
    $nombreParaMensaje = $cargo['nombre_cargo'];
} else {
    // ¿Ya existe un cargo activo con ese mismo nombre (sin distinguir
    // mayúsculas/minúsculas)? Si es así, se reusa en vez de duplicar.
    $stmtExiste = $pdo->prepare('SELECT id, nombre_cargo FROM cargos WHERE activo = 1 AND LOWER(nombre_cargo) = LOWER(:nombre)');
    $stmtExiste->execute(['nombre' => $cargoNuevoNombre]);
    $existente = $stmtExiste->fetch();

    if ($existente) {
        $stmtInsert = $pdo->prepare(
            'INSERT INTO solicitudes_cupo (cargo_id, cantidad, usuario_id, estado) VALUES (:cargo_id, :cantidad, :usuario_id, "Pendiente")'
        );
        $stmtInsert->execute(['cargo_id' => $existente['id'], 'cantidad' => $cantidad, 'usuario_id' => $usuario['id']]);
        $nombreParaMensaje = $existente['nombre_cargo'] . ' (ya existía en el catálogo)';
    } else {
        $stmtInsert = $pdo->prepare(
            'INSERT INTO solicitudes_cupo (cargo_nuevo_nombre, cantidad, usuario_id, estado) VALUES (:nombre, :cantidad, :usuario_id, "Pendiente")'
        );
        $stmtInsert->execute(['nombre' => $cargoNuevoNombre, 'cantidad' => $cantidad, 'usuario_id' => $usuario['id']]);
        $nombreParaMensaje = $cargoNuevoNombre . ' (cargo nuevo, no está en el catálogo todavía)';
    }
}

responderOk(['mensaje' => "Solicitud de $cantidad cupos para \"$nombreParaMensaje\" enviada. Queda pendiente de aprobación del Administrador de Contrato."]);
