<?php
/**
 * v7 - Segunda acción del JAO, separada de la verificación de
 * documentos (día 1, ver verificar_identidad.php). Esta se hace al día
 * siguiente, a las 8am, cuando el trabajador vuelve a firmar su
 * contrato. Reemplaza lo que antes hacía admin_general/finalizar.php,
 * pero YA NO deja a la postulación en 'Contratado' -- eso ahora lo hace
 * Bodega al entregar el EPP (ver bodega/marcar_epp.php), que es el
 * candado siguiente y quien de verdad descuenta el cupo.
 *
 * Requisitos, en orden:
 *   1) estado = 'Induccion_ok' (Prevención ya hizo la IRL)
 *   2) identidad_verificada_at (JAO ya verificó documentos, día 1)
 *   3) datos_jao completo (código de ficha, etc.)
 *   4) sin documentos rechazados pendientes de corrección
 *   5) cierre de remuneraciones no activo
 *   6) contrato_firmado_at todavía NULL (no se firma dos veces)
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

iniciarSesionSegura();
$usuario = requireRol(['Jefe_Administrativo']);
exigirMetodo('POST');
exigirCsrfValido();

$body = leerJsonBody();
$postulacionId = (int)($body['postulacion_id'] ?? 0);
if ($postulacionId <= 0) {
    responderError('postulacion_id inválido.', 422);
}

$pdo = obtenerConexion();

if (cierreRemuneracionesActivo($pdo)) {
    responderError(
        'Cierre de mes de remuneraciones activo: no se pueden firmar contratos hasta que se reabra. El resto del proceso sigue funcionando con normalidad.',
        423
    );
}

$pdo->beginTransaction();

try {
    $stmtCheck = $pdo->prepare(
        'SELECT id, estado, identidad_verificada_at, contrato_firmado_at
           FROM postulaciones
          WHERE id = :id
          FOR UPDATE'
    );
    $stmtCheck->execute(['id' => $postulacionId]);
    $postulacion = $stmtCheck->fetch();

    if (!$postulacion) {
        throw new RuntimeException('Postulación no encontrada.|404');
    }
    if ($postulacion['estado'] !== 'Induccion_ok') {
        throw new RuntimeException('La postulación no está en estado Induccion_ok.|409');
    }
    if ($postulacion['identidad_verificada_at'] === null) {
        throw new RuntimeException('Debes verificar la identidad (RUT vs. cédula) antes de firmar el contrato.|409');
    }
    if ($postulacion['contrato_firmado_at'] !== null) {
        throw new RuntimeException('El contrato ya estaba firmado.|409');
    }

    $stmtJao = $pdo->prepare('SELECT id FROM datos_jao WHERE postulacion_id = :id');
    $stmtJao->execute(['id' => $postulacionId]);
    if (!$stmtJao->fetch()) {
        throw new RuntimeException('Debes completar los datos de nómina antes de firmar el contrato.|409');
    }

    $stmtDocPendiente = $pdo->prepare(
        'SELECT COUNT(*) FROM postulacion_documentos
          WHERE postulacion_id = :id AND rechazado_at IS NOT NULL AND resubido_at IS NULL'
    );
    $stmtDocPendiente->execute(['id' => $postulacionId]);
    if ((int)$stmtDocPendiente->fetchColumn() > 0) {
        throw new RuntimeException('Hay un documento observado pendiente de corrección por el postulante.|409');
    }

    $stmt = $pdo->prepare(
        'UPDATE postulaciones SET contrato_firmado_at = NOW(), contrato_firmado_por = :uid WHERE id = :id'
    );
    $stmt->execute(['uid' => $usuario['id'], 'id' => $postulacionId]);

    registrarLog($pdo, $postulacionId, $usuario['id'], 'Firmó el contrato (día 2). Pasa a Bodega para entrega de EPP.');

    $pdo->commit();
} catch (RuntimeException $e) {
    $pdo->rollBack();
    [$mensaje, $status] = explode('|', $e->getMessage());
    responderError($mensaje, (int)$status);
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('firmar_contrato error: ' . $e->getMessage());
    responderError('No fue posible registrar la firma del contrato.', 500);
}

responderOk(['mensaje' => 'Contrato firmado. Pasa a Bodega para la entrega de EPP.']);
