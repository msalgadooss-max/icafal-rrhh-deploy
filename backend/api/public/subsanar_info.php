<?php
/**
 * v5 - Valida el token de subsanación y devuelve SOLO los documentos
 * que siguen observados (rechazados y aún no resubidos), con el motivo
 * indicado por el JAO. Público, sin sesión -- el token es de un solo
 * propósito y expira.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

exigirMetodo('GET');

$token = limpiarTexto($_GET['token'] ?? '', 64);
if ($token === '') {
    responderError('Token no proporcionado.', 422);
}

$pdo = obtenerConexion();
$stmt = $pdo->prepare(
    'SELECT id, nombre_completo, token_subsanacion_expira_at
       FROM postulaciones
      WHERE token_subsanacion = :token'
);
$stmt->execute(['token' => $token]);
$postulacion = $stmt->fetch();

if (!$postulacion) {
    responderError('El enlace no es válido.', 404);
}
if (strtotime($postulacion['token_subsanacion_expira_at']) < time()) {
    responderError('El enlace expiró. Pide a la empresa que te reenvíe la observación.', 410);
}

$stmtDocs = $pdo->prepare(
    'SELECT tipo, motivo_rechazo
       FROM postulacion_documentos
      WHERE postulacion_id = :id AND rechazado_at IS NOT NULL AND resubido_at IS NULL'
);
$stmtDocs->execute(['id' => $postulacion['id']]);
$etiquetas = etiquetasDocumentos();
$documentos = array_map(function ($d) use ($etiquetas) {
    return [
        'tipo'      => $d['tipo'],
        'etiqueta'  => $etiquetas[$d['tipo']] ?? $d['tipo'],
        'motivo'    => $d['motivo_rechazo'],
    ];
}, $stmtDocs->fetchAll());

if (!$documentos) {
    responderError('Ya no hay ningún documento pendiente de corrección.', 409);
}

responderOk([
    'nombre_completo' => $postulacion['nombre_completo'],
    'documentos'      => $documentos,
]);
