<?php
/**
 * v6.9 - El postulante marca un video de inducción como visto (o el
 * propio reproductor lo marca solo al terminar). Idempotente: volver a
 * marcar el mismo video no genera duplicados ni error.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/rut.php';

exigirMetodo('POST');

$body = leerJsonBody();
$documentoCrudo = trim((string)($body['rut'] ?? ''));
$codigo = strtoupper(limpiarTexto($body['codigo_seguimiento'] ?? '', 10));
$videoId = (int)($body['video_id'] ?? 0);

if ($documentoCrudo === '' || $codigo === '' || $videoId <= 0) {
    responderError('Faltan datos.', 422);
}

$documentoRut = normalizarRut($documentoCrudo);

$pdo = obtenerConexion();
$stmt = $pdo->prepare(
    'SELECT id, admin_autorizado_at FROM postulaciones
      WHERE (rut = :doc_crudo OR rut = :doc_rut) AND codigo_seguimiento = :codigo
      LIMIT 1'
);
$stmt->execute(['doc_crudo' => $documentoCrudo, 'doc_rut' => $documentoRut, 'codigo' => $codigo]);
$postulacion = $stmt->fetch();

if (!$postulacion) {
    responderError('No se encontró una postulación con esos datos.', 404);
}
if ($postulacion['admin_autorizado_at'] === null) {
    responderError('Todavía no está disponible la inducción para tu proceso.', 409);
}

$stmtVideo = $pdo->prepare('SELECT id FROM videos_induccion WHERE id = :id AND activo = 1');
$stmtVideo->execute(['id' => $videoId]);
if (!$stmtVideo->fetch()) {
    responderError('Video no encontrado.', 404);
}

$stmtInsert = $pdo->prepare(
    'INSERT IGNORE INTO postulante_videos_vistos (postulacion_id, video_id) VALUES (:pid, :vid)'
);
$stmtInsert->execute(['pid' => $postulacion['id'], 'vid' => $videoId]);

responderOk(['mensaje' => 'Video marcado como visto.']);
