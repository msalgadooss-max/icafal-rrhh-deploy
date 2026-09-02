<?php
/**
 * v7 - Confirma el "ingreso a faena" del día 1: Portería lo llama desde
 * el QR público (frontend/public/ingreso_faena.html) o desde su propio
 * dashboard autenticado (frontend/dashboards/porteria.html) -- en ambos
 * casos el "crédito" para hacer esta acción es tener el par correcto
 * RUT + código de seguimiento, igual que el resto de las consultas de
 * Portería (nunca requiere sesión). Cambio: ingreso_faena_at = NOW().
 * No cambia `estado` -- es un sub-gate, igual que aprobado_jt_at, que
 * habilita la siguiente acción (ver admin_general/verificar_identidad.php).
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/rut.php';

exigirMetodo('POST');

$body = leerJsonBody();
$rutCrudo = trim((string)($body['rut'] ?? ''));
$codigo = strtoupper(limpiarTexto($body['codigo'] ?? '', 10));

if ($rutCrudo === '' || $codigo === '') {
    responderError('RUT y código de seguimiento son obligatorios.', 422);
}

$rutNormalizado = normalizarRut($rutCrudo);

$pdo = obtenerConexion();
$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare(
        'SELECT id, estado, ingreso_faena_at
           FROM postulaciones
          WHERE (rut = :rut_crudo OR rut = :rut_norm) AND codigo_seguimiento = :codigo
          FOR UPDATE'
    );
    $stmt->execute(['rut_crudo' => $rutCrudo, 'rut_norm' => $rutNormalizado, 'codigo' => $codigo]);
    $postulacion = $stmt->fetch();

    if (!$postulacion) {
        throw new RuntimeException('No se encontró ninguna credencial con esos datos.|404');
    }
    if ($postulacion['ingreso_faena_at'] !== null) {
        throw new RuntimeException('El ingreso de esta persona ya estaba confirmado.|409');
    }
    if ($postulacion['estado'] !== 'Aprobado_admin') {
        throw new RuntimeException('Esta postulación todavía no está lista para confirmar ingreso a faena.|409');
    }

    $stmtUpdate = $pdo->prepare('UPDATE postulaciones SET ingreso_faena_at = NOW() WHERE id = :id');
    $stmtUpdate->execute(['id' => $postulacion['id']]);

    registrarLog($pdo, (int)$postulacion['id'], null, 'Portería confirmó el ingreso a faena.');

    $pdo->commit();
} catch (RuntimeException $e) {
    $pdo->rollBack();
    [$mensaje, $status] = explode('|', $e->getMessage());
    responderError($mensaje, (int)$status);
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('porteria/marcar_ingreso error: ' . $e->getMessage());
    responderError('No fue posible confirmar el ingreso.', 500);
}

responderOk(['mensaje' => 'Ingreso a faena confirmado.']);
