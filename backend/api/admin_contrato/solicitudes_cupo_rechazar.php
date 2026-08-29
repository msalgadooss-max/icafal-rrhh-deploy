<?php
/**
 * v6.9 - Administrador de Contrato rechaza una solicitud de cupo de
 * Jefe_Terreno (ej. no hay presupuesto, obra no lo necesita todavía).
 * No abre ningún cupo -- el cargo queda exactamente como estaba.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

iniciarSesionSegura();
$usuario = requireRol(['Admin_Contrato']);
exigirMetodo('POST');
exigirCsrfValido();

$body = leerJsonBody();
$solicitudId = (int)($body['solicitud_id'] ?? 0);
$motivo = limpiarTexto($body['motivo'] ?? '', 255);

if ($solicitudId <= 0) {
    responderError('solicitud_id inválido.', 422);
}

$pdo = obtenerConexion();

$stmtCheck = $pdo->prepare('SELECT estado FROM solicitudes_cupo WHERE id = :id');
$stmtCheck->execute(['id' => $solicitudId]);
$solicitud = $stmtCheck->fetch();

if (!$solicitud) {
    responderError('Solicitud no encontrada.', 404);
}
if ($solicitud['estado'] !== 'Pendiente') {
    responderError('Esta solicitud ya fue resuelta.', 409);
}

$stmt = $pdo->prepare(
    'UPDATE solicitudes_cupo
        SET estado = "Rechazada", resuelta_por = :uid, resuelta_at = NOW(), motivo_rechazo = :motivo
      WHERE id = :id'
);
$stmt->execute(['uid' => $usuario['id'], 'id' => $solicitudId, 'motivo' => $motivo !== '' ? $motivo : null]);

responderOk(['mensaje' => 'Solicitud de cupo rechazada.']);
