<?php
/**
 * Jefe de Terreno / Capataz rechaza una postulacion 'Pendiente'.
 * Cambio de estado: Pendiente -> Rechazado. El motivo se guarda como
 * log manual (ademas del log automatico del trigger) para dejar
 * constancia del porqué, no solo del qué.
 *
 * v6.9: abierto tambien al rol Capataz (ver terreno/listar.php).
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

iniciarSesionSegura();
$usuario = requireRol(['Jefe_Terreno', 'Capataz']);
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
if ($postulacion['estado'] !== 'Pendiente') {
    responderError('La postulación ya no está en estado Pendiente.', 409);
}

fijarUsuarioContextoBD($pdo, $usuario['id']);

$stmt = $pdo->prepare('UPDATE postulaciones SET estado = "Rechazado" WHERE id = :id');
$stmt->execute(['id' => $postulacionId]);

if ($motivo !== '') {
    registrarLog($pdo, $postulacionId, $usuario['id'], 'Motivo de rechazo: ' . $motivo);
}

// v6.1: correo con mensaje neutro (nunca el motivo real) -- ver
// mailer/templates/postulacion_no_continua.php.
try {
    notificarPostulacionNoContinua($postulacion);
} catch (\Throwable $e) {
    error_log('notificarPostulacionNoContinua error: ' . $e->getMessage());
}

responderOk(['mensaje' => 'Postulación rechazada.']);
