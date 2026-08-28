<?php
/**
 * Jefe de Terreno aprueba una postulacion 'Pendiente'.
 * Cambio de estado: Pendiente -> Pre_aprobado_terreno.
 *
 * v3: esta aprobacion YA otorga el acceso a la Etapa 2 (genera el
 * token privado y envia el correo con el link) -- antes ese paso lo
 * hacia Admin_Contrato al "autorizar". Ahora Admin_Contrato revisa la
 * ficha con los datos y documentos YA completos, en vez de autorizar
 * a ciegas antes de que existan.
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
if ($postulacionId <= 0) {
    responderError('postulacion_id inválido.', 422);
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
    if ($postulacion['estado'] !== 'Pendiente') {
        throw new RuntimeException('La postulación ya no está en estado Pendiente.|409');
    }

    otorgarAccesoEtapa2($pdo, $postulacionId, $usuario['id']);

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

responderOk(['mensaje' => 'Postulación aprobada. Se envió al postulante el enlace para completar sus datos.']);
