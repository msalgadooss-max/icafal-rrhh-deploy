<?php
/**
 * v2 - Cierre de remuneraciones.
 * GET: cualquier rol interno autenticado puede consultar el estado
 *      (Gerencia lo necesita para su panel de solo lectura).
 * POST: solo Jefe_Administrativo puede activarlo/desactivarlo -- es
 *      quien conoce la ventana real del software de remuneraciones.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

iniciarSesionSegura();
$pdo = obtenerConexion();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    responderOk(['activo' => cierreRemuneracionesActivo($pdo)]);
}

$usuario = requireRol(['Jefe_Administrativo']);
exigirMetodo('POST');
exigirCsrfValido();

$body = leerJsonBody();
$activo = (bool)($body['activo'] ?? false);

$stmt = $pdo->prepare('UPDATE cierre_remuneraciones SET activo = :activo, actualizado_por = :uid WHERE id = 1');
$stmt->execute(['activo' => $activo ? 1 : 0, 'uid' => $usuario['id']]);

responderOk([
    'activo'  => $activo,
    'mensaje' => $activo
        ? 'Cierre de remuneraciones activado: "Finalizar Contratación" queda bloqueado hasta que lo reabras.'
        : 'Cierre de remuneraciones desactivado: ya se pueden finalizar contrataciones.',
]);
