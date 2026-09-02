<?php
/**
 * v9 - Catálogo de cursos de Prevención (evolución del módulo de
 * "videos de inducción" de v6.9, reunión Ricardo 31-ago). El postulante
 * ve, por categoría, cada curso con su video y su evaluación simple
 * (preguntas abiertas), y el estado que Prevención ya le puso
 * (Pendiente/Aprobado/Reprobado) una vez que la revisa. Misma identidad
 * de dos factores que ya protege todo el módulo de seguimiento (RUT +
 * código).
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

$stmtCursos = $pdo->prepare(
    'SELECT c.id, c.categoria, c.titulo, c.descripcion, c.duracion_estimada, c.url,
            c.preguntas_evaluacion,
            pc.visto_at, pc.respuestas, pc.enviado_at, pc.estado, pc.comentario_evaluador
       FROM cursos_induccion c
       LEFT JOIN postulacion_cursos pc
              ON pc.curso_id = c.id AND pc.postulacion_id = :pid
      WHERE c.activo = 1
      ORDER BY c.categoria ASC, c.orden ASC'
);
$stmtCursos->execute(['pid' => $postulacion['id']]);
$cursos = array_map(function ($c) {
    return [
        'id' => (int)$c['id'],
        'categoria' => $c['categoria'],
        'titulo' => $c['titulo'],
        'descripcion' => $c['descripcion'],
        'duracion_estimada' => $c['duracion_estimada'],
        'url' => $c['url'],
        'preguntas' => json_decode($c['preguntas_evaluacion'], true) ?? [],
        'visto' => $c['visto_at'] !== null,
        'respuestas' => $c['respuestas'] !== null ? json_decode($c['respuestas'], true) : null,
        'enviado' => $c['enviado_at'] !== null,
        // Sin fila en postulacion_cursos todavia = 'Pendiente' por defecto.
        'estado' => $c['estado'] ?? 'Pendiente',
        'comentario_evaluador' => $c['comentario_evaluador'],
    ];
}, $stmtCursos->fetchAll());

responderOk([
    'nombre_completo' => $postulacion['nombre_completo'],
    'cursos' => $cursos,
]);
