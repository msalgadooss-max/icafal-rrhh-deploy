<?php
/**
 * Cambio de estado: Aprobado_admin -> Induccion_ok.
 * Se marca cuando el prevencionista confirma que ya dictó la charla ODI.
 *
 * v7: candado -- requiere que el JAO ya haya verificado la identidad
 * (día 1). Antes exigía estado 'Datos_completados', que nunca se
 * alcanzaba en la práctica (ver nota en listar.php de este mismo módulo).
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

iniciarSesionSegura();
$usuario = requireRol(['Prevencionista']);
exigirModuloActivo(MODULO_PREVENCION_ACTIVO, 'Prevención');
exigirMetodo('POST');
exigirCsrfValido();

$body = leerJsonBody();
$postulacionId = (int)($body['postulacion_id'] ?? 0);
if ($postulacionId <= 0) {
    responderError('postulacion_id inválido.', 422);
}

$pdo = obtenerConexion();

$stmtCheck = $pdo->prepare('SELECT estado, identidad_verificada_at FROM postulaciones WHERE id = :id');
$stmtCheck->execute(['id' => $postulacionId]);
$postulacion = $stmtCheck->fetch();

if (!$postulacion) {
    responderError('Postulación no encontrada.', 404);
}
if ($postulacion['estado'] !== 'Aprobado_admin') {
    responderError('La postulación no está en estado Aprobado_admin.', 409);
}
if ($postulacion['identidad_verificada_at'] === null) {
    responderError('El JAO todavía no verifica la identidad de esta persona (día 1).', 409);
}

fijarUsuarioContextoBD($pdo, $usuario['id']);

$stmt = $pdo->prepare('UPDATE postulaciones SET estado = "Induccion_ok" WHERE id = :id');
$stmt->execute(['id' => $postulacionId]);

responderOk(['mensaje' => 'Inducción ODI registrada correctamente.']);
