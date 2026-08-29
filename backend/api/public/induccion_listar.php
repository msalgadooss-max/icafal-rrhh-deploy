<?php
/**
 * v6.9 - Módulo de inducción en video (Ricardo, reunión 28-ago): el
 * postulante ve los videos de seguridad desde su celular apenas es
 * autorizado, antes de presentarse. Misma identidad de dos factores que
 * ya protege todo el módulo de seguimiento (RUT + código).
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/rut.php';

exigirMetodo('POST');

$body = leerJsonBody();
$documentoCrudo = trim((string)($body['rut'] ?? ''));
$codigo = strtoupper(limpiarTexto($body['codigo_seguimiento'] ?? '', 10));

if ($documentoCrudo === '' || $codigo === '') {
    responderError('Tu documento y el código de seguimiento son obligatorios.', 422);
}

$documentoRut = normalizarRut($documentoCrudo);

$pdo = obtenerConexion();
$stmt = $pdo->prepare(
    'SELECT id, nombre_completo, admin_autorizado_at
       FROM postulaciones
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

$stmtVideos = $pdo->prepare(
    'SELECT v.id, v.titulo, v.url, v.orden,
            (sv.id IS NOT NULL) AS visto
       FROM videos_induccion v
       LEFT JOIN postulante_videos_vistos sv
              ON sv.video_id = v.id AND sv.postulacion_id = :pid
      WHERE v.activo = 1
      ORDER BY v.orden ASC'
);
$stmtVideos->execute(['pid' => $postulacion['id']]);
$videos = array_map(function ($v) {
    $v['visto'] = (bool)$v['visto'];
    return $v;
}, $stmtVideos->fetchAll());

responderOk([
    'nombre_completo' => $postulacion['nombre_completo'],
    'videos' => $videos,
]);
