<?php
/**
 * Jefe de Terreno pre-aprueba una postulacion 'Pendiente'.
 * Cambio de estado: Pendiente -> Pre_aprobado_terreno.
 *
 * v6.5 - Vuelve al orden secuencial: esta pre-aprobacion YA NO le da
 * acceso a Etapa 2 al postulante. Ahora hace falta ADEMAS que
 * Admin_Contrato autorice (ver admin_contrato/autorizar.php) -- recien
 * ahi se genera el token y se le envia al postulante el correo para
 * completar sus datos. Mientras tanto, la postulacion queda visible
 * para Admin_Contrato en su pestaña "Por Autorizar", pero el postulante
 * no recibe nada todavia.
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

responderOk(['mensaje' => 'Postulación pre-aprobada. Pasó a revisión del Administrador de Contrato.']);
