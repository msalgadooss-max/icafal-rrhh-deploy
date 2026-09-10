<?php
/**
 * v6.5 - Administrador de Contrato autoriza la contratación.
 *
 * v10.7 (pedido explícito del usuario): esta autorización pasó a ser un
 * dato puramente INTERNO -- el postulante ya no la ve ni es notificado
 * por ella. El correo con el link de Etapa 2 ahora sale antes, apenas
 * el Capataz lo selecciona en portería (ver terreno/aprobar.php,
 * otorgarAccesoEtapa2()); acá solo se deja registrado admin_autorizado_at
 * como respaldo/trazabilidad interna.
 *
 * v10.13 (pedido explícito del usuario, tras describir de nuevo el
 * proceso completo): "el rol del administrador terminó" al aprobar los
 * cupos -- este endpoint queda SIN USAR, ningún botón lo llama (se
 * retiró la pestaña "Por Autorizar" de admin_contrato.html). No se
 * borra por si hace falta reactivarlo. intentarAvanzarAAprobadoAdmin()
 * ya NO depende de admin_autorizado_at -- avanza solo con que el
 * postulante complete su Etapa 2.
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
    registrarLog($pdo, $postulacionId, $usuario['id'], 'Administrador de Contrato autorizó la contratación (registro interno).');

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

responderOk(['mensaje' => 'Autorización registrada.']);
