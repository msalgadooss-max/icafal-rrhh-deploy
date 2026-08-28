<?php
/**
 * v5 - Deja a la sesión actual actuando como otro usuario interno, sin
 * pedir su contraseña. Solo lo puede hacer quien es (o ya venía siendo)
 * Desarrollador: se acepta tanto si el rol actual de sesión es
 * 'Desarrollador' como si ya está en medio de una impersonación (para
 * poder saltar de un rol a otro sin volver primero al panel).
 *
 * $_SESSION['dev_original'] guarda la identidad REAL del desarrollador
 * la primera vez que entra a un rol prestado, y nunca se sobreescribe
 * mientras siga "prestado" -- así "Volver al panel" (dev/volver.php)
 * siempre restaura a la persona real, sin importar cuántas veces haya
 * cambiado de rol en el camino.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

iniciarSesionSegura();
$usuarioActual = requireLogin();
exigirMetodo('POST');
exigirCsrfValido();

$esDesarrollador = $usuarioActual['rol'] === 'Desarrollador' || isset($_SESSION['dev_original']);
if (!$esDesarrollador) {
    responderError('No tiene permisos para esta acción.', 403);
}

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
    responderError('No puedes entrar como otro Desarrollador desde aquí.', 422);
}

if (!isset($_SESSION['dev_original'])) {
    $_SESSION['dev_original'] = $usuarioActual;
}

$_SESSION['usuario'] = [
    'id'     => (int)$objetivo['id'],
    'nombre' => $objetivo['nombre'],
    'correo' => $objetivo['correo'],
    'rol'    => $objetivo['rol'],
];

$stmtLog = $pdo->prepare(
    'INSERT INTO dev_accesos (desarrollador_id, usuario_objetivo_id, rol_objetivo) VALUES (:dev, :objetivo, :rol)'
);
$stmtLog->execute([
    'dev'      => $_SESSION['dev_original']['id'],
    'objetivo' => $objetivo['id'],
    'rol'      => $objetivo['rol'],
]);

responderOk(['usuario' => $_SESSION['usuario']]);
