<?php
/**
 * v3.1 - Administrador de Contrato autoriza la contratación.
 * Ya NO espera a que el postulante termine su Etapa 2: autoriza en
 * base a lo que aprobó Jefe_Terreno (datos Etapa 1 + CV), en paralelo
 * a que el postulante sigue llenando sus datos. Marca
 * admin_autorizado_at y, si el postulante YA había terminado antes,
 * el cambio de estado a 'Aprobado_admin' (y el aviso al JAO) ocurre
 * de inmediato; si no, queda pendiente y se completa solo apenas el
 * postulante termine (ver intentarAvanzarAAprobadoAdmin()).
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

iniciarSesionSegura();
$usuario = requireRol(['Admin_Contrato']);
exigirMetodo('POST');
exigirCsrfValido();

$body = leerJsonBody();
$postulacionId = (int)($body['postulacion_id'] ?? 0);
if ($postulacionId <= 0) {
    responderError('postulacion_id inválido.', 422);
}

$pdo = obtenerConexion();
$pdo->beginTransaction();

try {
    $stmtCheck = $pdo->prepare(
        'SELECT estado, admin_autorizado_at,
                (SELECT COUNT(*) FROM datos_contratacion d WHERE d.postulacion_id = postulaciones.id) AS tiene_datos
           FROM postulaciones
          WHERE id = :id
          FOR UPDATE'
    );
    $stmtCheck->execute(['id' => $postulacionId]);
    $postulacion = $stmtCheck->fetch();

    if (!$postulacion) {
        throw new RuntimeException('Postulación no encontrada.|404');
    }
    if ($postulacion['estado'] !== 'Pre_aprobado_terreno') {
        throw new RuntimeException('La postulación no está en estado Pre_aprobado_terreno.|409');
    }
    if ($postulacion['admin_autorizado_at'] !== null) {
        throw new RuntimeException('Esta postulación ya fue autorizada.|409');
    }

    fijarUsuarioContextoBD($pdo, $usuario['id']);

    $stmt = $pdo->prepare(
        'UPDATE postulaciones SET admin_autorizado_at = NOW(), admin_autorizado_por = :uid WHERE id = :id'
    );
    $stmt->execute(['uid' => $usuario['id'], 'id' => $postulacionId]);
    registrarLog($pdo, $postulacionId, $usuario['id'], 'Administrador de Contrato autorizó la contratación.');

    intentarAvanzarAAprobadoAdmin($pdo, $postulacionId);

    $pdo->commit();
} catch (RuntimeException $e) {
    $pdo->rollBack();
    [$mensaje, $status] = explode('|', $e->getMessage());
    responderError($mensaje, (int)$status);
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('admin_contrato/autorizar error: ' . $e->getMessage());
    responderError('No fue posible autorizar la contratación.', 500);
}

$yaCompleto = (int)$postulacion['tiene_datos'] > 0;
responderOk([
    'mensaje' => $yaCompleto
        ? 'Contratación autorizada. El postulante ya había completado sus datos: se notificó al Jefe Administrativo.'
        : 'Contratación autorizada. En cuanto el postulante termine de completar sus datos, se notificará automáticamente al Jefe Administrativo.',
]);
