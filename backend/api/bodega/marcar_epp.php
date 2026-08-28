<?php
/**
 * Cambio de estado: Induccion_ok -> EPP_listo.
 * A partir de este cambio, el modulo de seguimiento publico del
 * postulante pasa a color verde ("INGRESO PERMITIDO") y se habilita
 * el QR de porteria (ver frontend/public/seguimiento.html).
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

iniciarSesionSegura();
$usuario = requireRol(['Jefe_Bodega']);
exigirModuloActivo(MODULO_BODEGA_ACTIVO, 'Bodega');
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
if ($postulacion['estado'] !== 'Induccion_ok') {
    responderError('La postulación no está en estado Induccion_ok.', 409);
}

fijarUsuarioContextoBD($pdo, $usuario['id']);

$stmt = $pdo->prepare('UPDATE postulaciones SET estado = "EPP_listo" WHERE id = :id');
$stmt->execute(['id' => $postulacionId]);

responderOk(['mensaje' => 'Kit de EPP marcado como listo.']);
