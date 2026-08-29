<?php
/**
 * v2 - Sirve el CV subido en la Fase 0. Es la unica forma de acceder
 * al archivo: la carpeta backend/uploads/ esta bloqueada por
 * .htaccess, y este endpoint exige sesion de Jefe_Terreno antes de
 * leer cualquier cosa del disco.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

iniciarSesionSegura();
requireRol(['Jefe_Terreno', 'Capataz']);
exigirMetodo('GET');

$postulacionId = (int)($_GET['postulacion_id'] ?? 0);
if ($postulacionId <= 0) {
    responderError('postulacion_id inválido.', 422);
}

$pdo = obtenerConexion();
$stmt = $pdo->prepare('SELECT cv_ruta_archivo FROM postulaciones WHERE id = :id');
$stmt->execute(['id' => $postulacionId]);
$ruta = $stmt->fetchColumn();

if (!$ruta) {
    responderError('Esta postulación no tiene CV adjunto.', 404);
}

$rutaCompleta = realpath(__DIR__ . '/../../uploads/' . $ruta);
$carpetaUploads = realpath(__DIR__ . '/../../uploads');

// Verificacion extra de que el archivo resuelto sigue DENTRO de la
// carpeta de uploads (defensa adicional ante path traversal, aunque
// el nombre ya se genera aleatoriamente al guardarlo).
if (!$rutaCompleta || !$carpetaUploads || !str_starts_with($rutaCompleta, $carpetaUploads)) {
    responderError('Archivo no encontrado.', 404);
}
if (!is_file($rutaCompleta)) {
    responderError('Archivo no encontrado.', 404);
}

$extension = strtolower(pathinfo($rutaCompleta, PATHINFO_EXTENSION));
$tiposMime = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png'];
$mime = $tiposMime[$extension] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="cv-postulacion-' . $postulacionId . '.' . $extension . '"');
header('Content-Length: ' . filesize($rutaCompleta));
readfile($rutaCompleta);
exit;
