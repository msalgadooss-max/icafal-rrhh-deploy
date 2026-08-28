<?php
/**
 * Cambio de estado: Datos_completados -> Induccion_ok.
 * Se marca cuando el prevencionista confirma que ya dictó la charla ODI.
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

$stmtCheck = $pdo->prepare('SELECT estado FROM postulaciones WHERE id = :id');
$stmtCheck->execute(['id' => $postulacionId]);
$postulacion = $stmtCheck->fetch();

if (!$postulacion) {
    responderError('Postulación no encontrada.', 404);
}
if ($postulacion['estado'] !== 'Datos_completados') {
    responderError('La postulación no está en estado Datos_completados.', 409);
}

fijarUsuarioContextoBD($pdo, $usuario['id']);

$stmt = $pdo->prepare('UPDATE postulaciones SET estado = "Induccion_ok" WHERE id = :id');
$stmt->execute(['id' => $postulacionId]);

responderOk(['mensaje' => 'Inducción ODI registrada correctamente.']);
