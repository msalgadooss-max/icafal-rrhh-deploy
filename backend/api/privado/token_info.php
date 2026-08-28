<?php
/**
 * Valida un token privado (link enviado en Fase 2) y devuelve los datos
 * minimos para prellenar el formulario. No requiere sesion: la propia
 * posesion del token (largo, aleatorio, enviado solo al correo del
 * postulante) actua como credencial de un solo uso.
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
    'SELECT id, nombre_completo, rut, estado, token_expira_at
       FROM postulaciones
      WHERE token_privado = :token
      LIMIT 1'
);
$stmt->execute(['token' => $token]);
$postulacion = $stmt->fetch();

if (!$postulacion) {
    responderError('El enlace no es válido.', 404);
}

if ($postulacion['estado'] !== 'Pre_aprobado_terreno') {
    responderError('Este enlace ya fue utilizado o la postulación no está en la etapa correspondiente.', 409);
}

if (strtotime($postulacion['token_expira_at']) < time()) {
    responderError('El enlace expiró. Solicita uno nuevo a tu contacto en la empresa.', 410);
}

responderOk([
    'postulacion' => [
        'nombre_completo' => $postulacion['nombre_completo'],
        'rut'             => $postulacion['rut'],
    ],
]);
