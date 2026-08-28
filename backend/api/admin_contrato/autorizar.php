<?php
/**
 * v6.5 - Administrador de Contrato autoriza la contratación. Vuelve al
 * orden SECUENCIAL: recien aqui se le otorga al postulante el acceso a
 * Etapa 2 (token + correo "tu contratación ha sido autorizada, completa
 * tus datos"). Antes de esto, el postulante no tiene ningun link -- ya
 * no se cruza en paralelo con que el postulante llene sus datos.
 *
 * Sigue existiendo intentarAvanzarAAprobadoAdmin() porque el paso
 * siguiente (JAO) igual depende de dos condiciones (admin_autorizado_at
 * Y que exista datos_contratacion), solo que ahora la primera SIEMPRE
 * ocurre antes que la segunda por diseño.
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
        'SELECT estado, admin_autorizado_at FROM postulaciones WHERE id = :id FOR UPDATE'
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

    // v6.5: aqui es donde el postulante recibe por primera vez el
    // acceso a Etapa 2 -- antes de esto no tenia ningun token.
    otorgarAccesoEtapa2($pdo, $postulacionId, $usuario['id']);

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

responderOk(['mensaje' => 'Contratación autorizada. Se le envió al postulante el enlace para completar sus datos.']);
