<?php
/**
 * v6.1 - Admin_Contrato rechaza una postulacion 'Pre_aprobado_terreno'
 * (antes de autorizarla). Cambio de estado: Pre_aprobado_terreno ->
 * Rechazado. Igual que terreno/rechazar.php: el motivo queda en la
 * bitácora interna, pero el correo al postulante nunca lo menciona.
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
$motivo = limpiarTexto($body['motivo'] ?? '', 255);

if ($postulacionId <= 0) {
    responderError('postulacion_id inválido.', 422);
}

$pdo = obtenerConexion();

$stmtCheck = $pdo->prepare(
    'SELECT p.estado, p.nombre_completo, p.correo, c.nombre_cargo
       FROM postulaciones p
       JOIN cargos c ON c.id = p.cargo_id
      WHERE p.id = :id'
);
$stmtCheck->execute(['id' => $postulacionId]);
$postulacion = $stmtCheck->fetch();

if (!$postulacion) {
    responderError('Postulación no encontrada.', 404);
}
if ($postulacion['estado'] !== 'Pre_aprobado_terreno') {
    responderError('La postulación ya no está en un estado que se pueda rechazar desde aquí.', 409);
}

fijarUsuarioContextoBD($pdo, $usuario['id']);

$stmt = $pdo->prepare('UPDATE postulaciones SET estado = "Rechazado" WHERE id = :id');
$stmt->execute(['id' => $postulacionId]);

if ($motivo !== '') {
    registrarLog($pdo, $postulacionId, $usuario['id'], 'Motivo de rechazo: ' . $motivo);
}

try {
    notificarPostulacionNoContinua($postulacion);
} catch (\Throwable $e) {
    error_log('notificarPostulacionNoContinua error: ' . $e->getMessage());
}

responderOk(['mensaje' => 'Postulación rechazada.']);
