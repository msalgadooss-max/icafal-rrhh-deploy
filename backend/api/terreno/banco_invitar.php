<?php
/**
 * v2 - Invitar a alguien del Banco de Postulantes a un cupo ya
 * autorizado. Cambio de estado: En_banco -> Pre_aprobado_terreno
 * (equivalente a que Jefe_Terreno ya la hubiese aprobado en Fase 1,
 * porque es justamente Jefe_Terreno quien decide invitarla). Se puede
 * asignar el mismo cargo de interés u otro que sí tenga cupo.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

iniciarSesionSegura();
$usuario = requireRol(['Jefe_Terreno']);
exigirMetodo('POST');
exigirCsrfValido();

$body = leerJsonBody();
$postulacionId = (int)($body['postulacion_id'] ?? 0);
$cargoId = (int)($body['cargo_id'] ?? 0);

if ($postulacionId <= 0 || $cargoId <= 0) {
    responderError('postulacion_id y cargo_id son obligatorios.', 422);
}

$pdo = obtenerConexion();
exigirCupoDiarioAprobaciones($pdo, $usuario['id']);

$pdo->beginTransaction();

try {
    $stmtCheck = $pdo->prepare('SELECT estado FROM postulaciones WHERE id = :id FOR UPDATE');
    $stmtCheck->execute(['id' => $postulacionId]);
    $postulacion = $stmtCheck->fetch();

    if (!$postulacion) {
        throw new RuntimeException('Postulación no encontrada.|404');
    }
    if ($postulacion['estado'] !== 'En_banco') {
        throw new RuntimeException('Esta persona ya no está en el Banco de Postulantes.|409');
    }

    $stmtCargo = $pdo->prepare('SELECT nombre_cargo, cupos_activos FROM cargos WHERE id = :id AND activo = 1');
    $stmtCargo->execute(['id' => $cargoId]);
    $cargo = $stmtCargo->fetch();
    if (!$cargo) {
        throw new RuntimeException('El cargo seleccionado no existe.|404');
    }
    if ((int)$cargo['cupos_activos'] <= 0) {
        throw new RuntimeException('Ese cargo no tiene cupos disponibles en este momento.|409');
    }

    // Se asigna el cargo antes de otorgar el acceso, para que quede
    // guardado correctamente aunque cambie respecto al cargo de interes
    // original.
    $stmtAsignar = $pdo->prepare('UPDATE postulaciones SET cargo_id = :cargo_id WHERE id = :id');
    $stmtAsignar->execute(['cargo_id' => $cargoId, 'id' => $postulacionId]);

    registrarLog(
        $pdo,
        $postulacionId,
        $usuario['id'],
        'Invitada desde el Banco de Postulantes a un cupo de "' . $cargo['nombre_cargo'] . '".'
    );

    // v6.5: igual que una aprobacion normal desde la Etapa 1, esto YA NO
    // da acceso directo a Etapa 2 -- queda igual de "Pre_aprobado_terreno"
    // esperando que Admin_Contrato autorice primero.
    fijarUsuarioContextoBD($pdo, $usuario['id']);
    $stmtEstado = $pdo->prepare('UPDATE postulaciones SET estado = "Pre_aprobado_terreno" WHERE id = :id AND estado = "En_banco"');
    $stmtEstado->execute(['id' => $postulacionId]);

    $pdo->commit();
} catch (RuntimeException $e) {
    $pdo->rollBack();
    [$mensaje, $status] = explode('|', $e->getMessage());
    responderError($mensaje, (int)$status);
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('banco_invitar error: ' . $e->getMessage());
    responderError('No fue posible invitar a esta persona.', 500);
}

responderOk(['mensaje' => 'Persona invitada al proceso. Pasó a revisión del Administrador de Contrato.']);
