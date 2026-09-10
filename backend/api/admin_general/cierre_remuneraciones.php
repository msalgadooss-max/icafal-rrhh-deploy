<?php
/**
 * v2 - Cierre de remuneraciones.
 * GET: cualquier rol interno autenticado puede consultar el estado
 *      (Gerencia lo necesita para su panel de solo lectura).
 * POST: solo Jefe_Administrativo puede activarlo/desactivarlo, o
 *      programar una ventana de fechas -- es quien conoce la ventana
 *      real del software de remuneraciones.
 *
 * v10.14 (pedido explícito del usuario, item 15): "que el JAO enviara
 * una solicitud de aceptación de fechas de cierre" -- se agrega
 * desde/hasta, además del interruptor manual `activo` que ya existía
 * (queda como override rápido para un cierre de emergencia sin fechas
 * definidas).
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

iniciarSesionSegura();
$pdo = obtenerConexion();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    requireLogin();
    $stmt = $pdo->query('SELECT activo, desde, hasta FROM cierre_remuneraciones WHERE id = 1');
    $fila = $stmt->fetch();
    responderOk([
        'activo' => cierreRemuneracionesActivo($pdo),
        'desde'  => $fila['desde'] ?? null,
        'hasta'  => $fila['hasta'] ?? null,
    ]);
}

$usuario = requireRol(['Jefe_Administrativo']);
exigirMetodo('POST');
exigirCsrfValido();

$body = leerJsonBody();
$activo = (bool)($body['activo'] ?? false);
$desde = limpiarTexto($body['desde'] ?? '', 10);
$hasta = limpiarTexto($body['hasta'] ?? '', 10);

if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
    responderError('La fecha "desde" no puede ser posterior a "hasta".', 422);
}

$stmt = $pdo->prepare(
    'UPDATE cierre_remuneraciones
        SET activo = :activo, desde = :desde, hasta = :hasta, actualizado_por = :uid
      WHERE id = 1'
);
$stmt->execute([
    'activo' => $activo ? 1 : 0,
    'desde' => $desde !== '' ? $desde : null,
    'hasta' => $hasta !== '' ? $hasta : null,
    'uid' => $usuario['id'],
]);

$quedaActivo = cierreRemuneracionesActivo($pdo);
$mensaje = $quedaActivo
    ? ($desde !== '' && $hasta !== ''
        ? "Cierre de remuneraciones programado del {$desde} al {$hasta}. \"Finalizar Contratación\" queda bloqueado en ese rango; \"Solicitar Cupos\" sigue funcionando, pero con un aviso de que los cupos se liberarán recién el mes siguiente."
        : 'Cierre de remuneraciones activado manualmente: "Finalizar Contratación" queda bloqueado hasta que lo reabras.')
    : 'Cierre de remuneraciones desactivado: ya se pueden finalizar contrataciones con normalidad.';

responderOk([
    'activo'  => $quedaActivo,
    'desde'   => $desde !== '' ? $desde : null,
    'hasta'   => $hasta !== '' ? $hasta : null,
    'mensaje' => $mensaje,
]);
