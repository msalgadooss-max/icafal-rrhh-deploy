<?php
/**
 * v9 - Detalle del catálogo de cursos de un postulante puntual, para que
 * Prevención pueda leer sus respuestas y decidir Aprobado/Reprobado
 * curso por curso (ver evaluar_curso.php). Igual que el resto del
 * módulo, no expone datos de datos_contratacion.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

iniciarSesionSegura();
requireRol(['Prevencionista']);
exigirModuloActivo(MODULO_PREVENCION_ACTIVO, 'Prevención');
exigirMetodo('GET');

$postulacionId = (int)($_GET['postulacion_id'] ?? 0);
if ($postulacionId <= 0) {
    responderError('postulacion_id inválido.', 422);
}

$pdo = obtenerConexion();

$stmtPostulacion = $pdo->prepare('SELECT id, rut, nombre_completo FROM postulaciones WHERE id = :id');
$stmtPostulacion->execute(['id' => $postulacionId]);
$postulacion = $stmtPostulacion->fetch();
if (!$postulacion) {
    responderError('Postulación no encontrada.', 404);
}

$stmt = $pdo->prepare(
    'SELECT c.id, c.categoria, c.titulo, c.preguntas_evaluacion,
            pc.visto_at, pc.respuestas, pc.enviado_at, pc.estado,
            pc.comentario_evaluador, u.nombre AS evaluado_por_nombre, pc.evaluado_at
       FROM cursos_induccion c
       LEFT JOIN postulacion_cursos pc
              ON pc.curso_id = c.id AND pc.postulacion_id = :pid
       LEFT JOIN usuarios u ON u.id = pc.evaluado_por
      WHERE c.activo = 1
      ORDER BY c.categoria ASC, c.orden ASC'
);
$stmt->execute(['pid' => $postulacionId]);
$cursos = array_map(function ($c) {
    return [
        'id' => (int)$c['id'],
        'categoria' => $c['categoria'],
        'titulo' => $c['titulo'],
        'preguntas' => json_decode($c['preguntas_evaluacion'], true) ?? [],
        'visto' => $c['visto_at'] !== null,
        'respuestas' => $c['respuestas'] !== null ? json_decode($c['respuestas'], true) : null,
        'enviado' => $c['enviado_at'] !== null,
        'estado' => $c['estado'] ?? 'Pendiente',
        'comentario_evaluador' => $c['comentario_evaluador'],
        'evaluado_por_nombre' => $c['evaluado_por_nombre'],
        'evaluado_at' => $c['evaluado_at'],
    ];
}, $stmt->fetchAll());

responderOk([
    'rut' => $postulacion['rut'],
    'nombre_completo' => $postulacion['nombre_completo'],
    'cursos' => $cursos,
]);
