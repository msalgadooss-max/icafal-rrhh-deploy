<?php
/**
 * v7: selección en terreno en DOS pasos secuenciales (reunión Ricardo,
 * 31-ago) -- este mismo endpoint hace cosas distintas según el rol de
 * quien llama, para no duplicar la ruta ni el botón en el frontend:
 *
 *   - Jefe_Terreno (paso 1): filtra una postulación 'Pendiente' sin
 *     filtrar aún. Marca aprobado_jt_at/aprobado_jt_por -- el estado
 *     NO cambia (sigue 'Pendiente'), por eso se deja un log manual
 *     (el trigger automático solo dispara con cambios de estado).
 *     Recién ahí la postulación aparece en el panel del Capataz.
 *
 *   - Capataz (paso 2, en persona/portería): solo puede actuar sobre
 *     postulaciones que Jefe_Terreno ya filtró (aprobado_jt_at IS NOT
 *     NULL). Su selección es la que de verdad hace avanzar el estado a
 *     'Pre_aprobado_terreno' -- mismo comportamiento que existía antes
 *     de este cambio.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

iniciarSesionSegura();
$usuario = requireRol(['Jefe_Terreno', 'Capataz']);
exigirMetodo('POST');
exigirCsrfValido();

$body = leerJsonBody();
$postulacionId = (int)($body['postulacion_id'] ?? 0);
if ($postulacionId <= 0) {
    responderError('postulacion_id inválido.', 422);
}

$pdo = obtenerConexion();

if ($usuario['rol'] === 'Jefe_Terreno') {
    $stmtCheck = $pdo->prepare('SELECT estado, aprobado_jt_at FROM postulaciones WHERE id = :id FOR UPDATE');
    $stmtCheck->execute(['id' => $postulacionId]);
    $postulacion = $stmtCheck->fetch();

    if (!$postulacion) {
        responderError('Postulación no encontrada.', 404);
    }
    if ($postulacion['estado'] !== 'Pendiente') {
        responderError('La postulación ya no está en estado Pendiente.', 409);
    }
    if ($postulacion['aprobado_jt_at'] !== null) {
        responderError('Ya fue aprobada por Jefe de Terreno.', 409);
    }

    $stmt = $pdo->prepare(
        'UPDATE postulaciones
            SET aprobado_jt_at = NOW(), aprobado_jt_por = :uid
          WHERE id = :id AND estado = "Pendiente" AND aprobado_jt_at IS NULL'
    );
    $stmt->execute(['uid' => $usuario['id'], 'id' => $postulacionId]);

    registrarLog($pdo, $postulacionId, $usuario['id'], 'Aprobó el primer filtro (Jefe de Terreno). Pasa a selección del Capataz.');

    responderOk(['mensaje' => 'Aprobada. Pasa a selección del Capataz en terreno.']);
}

// --- Capataz: selección final, en persona -------------------------------
exigirCupoDiarioAprobaciones($pdo, $usuario['id']);

$pdo->beginTransaction();

try {
    $stmtCheck = $pdo->prepare('SELECT estado, aprobado_jt_at FROM postulaciones WHERE id = :id FOR UPDATE');
    $stmtCheck->execute(['id' => $postulacionId]);
    $postulacion = $stmtCheck->fetch();

    if (!$postulacion) {
        throw new RuntimeException('Postulación no encontrada.|404');
    }
    if ($postulacion['estado'] !== 'Pendiente') {
        throw new RuntimeException('La postulación ya no está en estado Pendiente.|409');
    }
    if ($postulacion['aprobado_jt_at'] === null) {
        throw new RuntimeException('Esta postulación todavía no pasa el primer filtro de Jefe de Terreno.|409');
    }

    fijarUsuarioContextoBD($pdo, $usuario['id']);
    $stmt = $pdo->prepare('UPDATE postulaciones SET estado = "Pre_aprobado_terreno" WHERE id = :id AND estado = "Pendiente"');
    $stmt->execute(['id' => $postulacionId]);

    $pdo->commit();
} catch (RuntimeException $e) {
    $pdo->rollBack();
    [$mensaje, $status] = explode('|', $e->getMessage());
    responderError($mensaje, (int)$status);
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('terreno/aprobar error: ' . $e->getMessage());
    responderError('No fue posible aprobar la postulación.', 500);
}

responderOk(['mensaje' => 'Seleccionado. Pasa a revisión del Administrador de Contrato.']);
