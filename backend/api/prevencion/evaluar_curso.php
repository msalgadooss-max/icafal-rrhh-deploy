<?php
/**
 * v9 - Prevención aprueba o reprueba UN curso puntual de un postulante,
 * después de leer sus respuestas (ver cursos_detalle.php). No hay
 * corrección automática -- esto es una decisión humana, con comentario
 * opcional que el postulante puede ver (por ejemplo, para explicarle qué
 * le faltó si lo reprueba). Solo se puede evaluar un curso que el
 * postulante ya envió (enviado_at IS NOT NULL).
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
$cursoId = (int)($body['curso_id'] ?? 0);
$estado = (string)($body['estado'] ?? '');
$comentario = limpiarTexto($body['comentario'] ?? '', 500);

if ($postulacionId <= 0 || $cursoId <= 0) {
    responderError('Datos inválidos.', 422);
}
if (!in_array($estado, ['Aprobado', 'Reprobado'], true)) {
    responderError('Estado inválido -- debe ser Aprobado o Reprobado.', 422);
}
if ($estado === 'Reprobado' && $comentario === '') {
    responderError('Al reprobar un curso, indica un comentario para que el postulante sepa qué corregir.', 422);
}

$pdo = obtenerConexion();

$stmtCheck = $pdo->prepare(
    'SELECT enviado_at FROM postulacion_cursos WHERE postulacion_id = :pid AND curso_id = :cid'
);
$stmtCheck->execute(['pid' => $postulacionId, 'cid' => $cursoId]);
$actual = $stmtCheck->fetch();

if (!$actual || $actual['enviado_at'] === null) {
    responderError('El postulante todavía no envía la evaluación de este curso.', 409);
}

fijarUsuarioContextoBD($pdo, $usuario['id']);

$stmt = $pdo->prepare(
    'UPDATE postulacion_cursos
        SET estado = :estado, evaluado_at = NOW(), evaluado_por = :uid, comentario_evaluador = :comentario
      WHERE postulacion_id = :pid AND curso_id = :cid'
);
$stmt->execute([
    'estado' => $estado,
    'uid' => $usuario['id'],
    'comentario' => $comentario !== '' ? $comentario : null,
    'pid' => $postulacionId,
    'cid' => $cursoId,
]);

registrarLog(
    $pdo,
    $postulacionId,
    $usuario['id'],
    $estado === 'Aprobado' ? 'Curso de Prevención aprobado.' : 'Curso de Prevención reprobado.'
);

responderOk(['mensaje' => $estado === 'Aprobado' ? 'Curso aprobado.' : 'Curso reprobado -- el postulante verá tu comentario.']);
