<?php
/**
 * v9 - El postulante marca un curso como visto (o el propio reproductor
 * lo marca solo al terminar el video). Idempotente: volver a marcar el
 * mismo curso no pierde una evaluación ya enviada ni cambia su estado,
 * solo registra que lo vio.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/rut.php';

exigirMetodo('POST');

$body = leerJsonBody();
$documentoCrudo = trim((string)($body['rut'] ?? ''));
$codigo = strtoupper(limpiarTexto($body['codigo_seguimiento'] ?? '', 10));
$cursoId = (int)($body['curso_id'] ?? 0);

if ($documentoCrudo === '' || $codigo === '' || $cursoId <= 0) {
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

$stmtCurso = $pdo->prepare('SELECT id FROM cursos_induccion WHERE id = :id AND activo = 1');
$stmtCurso->execute(['id' => $cursoId]);
if (!$stmtCurso->fetch()) {
    responderError('Curso no encontrado.', 404);
}

$stmtUpsert = $pdo->prepare(
    'INSERT INTO postulacion_cursos (postulacion_id, curso_id, visto_at)
     VALUES (:pid, :cid, NOW())
     ON DUPLICATE KEY UPDATE visto_at = COALESCE(visto_at, NOW())'
);
$stmtUpsert->execute(['pid' => $postulacion['id'], 'cid' => $cursoId]);

responderOk(['mensaje' => 'Curso marcado como visto.']);
