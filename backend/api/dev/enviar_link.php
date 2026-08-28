<?php
/**
 * v5 - Envía por correo el link público de postulación, como
 * alternativa al QR (útil para pruebas cuando no se puede escanear,
 * ej. postulante de prueba en otra ciudad).
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

iniciarSesionSegura();
requireRol(['Desarrollador']);
exigirMetodo('POST');
exigirCsrfValido();

$body = leerJsonBody();
$correo = filter_var(trim((string)($body['correo'] ?? '')), FILTER_VALIDATE_EMAIL);
$nombre = limpiarTexto($body['nombre'] ?? '', 100);

if (!$correo) {
    responderError('Correo inválido.', 422);
}

require_once __DIR__ . '/../../mailer/Mailer.php';
$urlPostulacion = BASE_URL . '/frontend/public/index.html';
$html = (function () use ($nombre, $urlPostulacion) {
    return require __DIR__ . '/../../mailer/templates/link_postulacion.php';
})();

$ok = Mailer::enviar($correo, $nombre !== '' ? $nombre : 'Postulante', 'Postula a ICAFAL - Enlace de postulación', $html);
if (!$ok) {
    responderError('No fue posible enviar el correo. Revisa la configuración SMTP.', 500);
}

responderOk(['mensaje' => 'Enlace enviado correctamente a ' . $correo . '.']);
