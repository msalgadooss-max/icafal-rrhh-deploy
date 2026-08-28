<?php
/**
 * v6.4 - Genera (o reutiliza, si ya hay uno vigente) un link de acceso
 * directo por QR para un usuario interno especifico: quien lo escanea
 * entra derecho al dashboard de ese rol, sin pedir correo ni clave.
 * Pensado para repartir a otras personas en una demo. Ver dev/qr_login.php.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

iniciarSesionSegura();
$usuario = requireRol(['Desarrollador']);
exigirMetodo('POST');
exigirCsrfValido();

$body = leerJsonBody();
$usuarioObjetivoId = (int)($body['usuario_id'] ?? 0);
if ($usuarioObjetivoId <= 0) {
    responderError('usuario_id inválido.', 422);
}

$pdo = obtenerConexion();
$stmt = $pdo->prepare('SELECT id, nombre, correo, rol, activo FROM usuarios WHERE id = :id');
$stmt->execute(['id' => $usuarioObjetivoId]);
$objetivo = $stmt->fetch();

if (!$objetivo || !$objetivo['activo']) {
    responderError('Usuario no encontrado o inactivo.', 404);
}
if ($objetivo['rol'] === 'Desarrollador') {
    responderError('No se pueden generar accesos directos para el rol Desarrollador.', 422);
}

// Reutiliza un token vigente si ya existe, para no acumular filas cada
// vez que alguien vuelve a abrir el panel.
$stmt = $pdo->prepare(
    'SELECT token FROM dev_qr_accesos
      WHERE usuario_objetivo_id = :id AND expira_at > NOW()
      ORDER BY creado_at DESC LIMIT 1'
);
$stmt->execute(['id' => $usuarioObjetivoId]);
$token = $stmt->fetchColumn();

if (!$token) {
    $token = generarTokenPrivado();
    $expira = (new DateTime())->modify('+7 days')->format('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        'INSERT INTO dev_qr_accesos (token, usuario_objetivo_id, creado_por, expira_at)
         VALUES (:token, :objetivo, :creador, :expira)'
    );
    $stmt->execute(['token' => $token, 'objetivo' => $usuarioObjetivoId, 'creador' => $usuario['id'], 'expira' => $expira]);
}

responderOk([
    'token' => $token,
    'url' => BASE_URL . '/backend/api/dev/qr_login.php?token=' . $token,
    'nombre' => $objetivo['nombre'],
    'rol' => $objetivo['rol'],
]);
