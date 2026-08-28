<?php
/**
 * v5 - El JAO rechaza UN documento puntual (incluida la cédula, para
 * el caso "el RUT de la foto no coincide con el declarado") con un
 * comentario obligatorio. A diferencia de terreno/rechazar.php, esto
 * NO cambia el `estado` de la postulación ni deshace lo ya avanzado
 * (admin_autorizado_at y la existencia de datos_contratacion siguen
 * intactos) -- solo bloquea "Finalizar Contratación" hasta que ese
 * documento puntual se resuba (ver finalizar.php), y le envía al
 * postulante un link de un solo propósito para corregir justo eso.
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
$tipoDocumento = (string)($body['tipo_documento'] ?? '');
$motivo = limpiarTexto($body['motivo'] ?? '', 500);

$etiquetas = etiquetasDocumentos();
if ($postulacionId <= 0 || !isset($etiquetas[$tipoDocumento])) {
    responderError('Solicitud inválida.', 422);
}
if ($motivo === '') {
    responderError('Debes indicar el motivo del rechazo, para que el postulante sepa qué corregir.', 422);
}

$pdo = obtenerConexion();
$pdo->beginTransaction();

try {
    $stmtDoc = $pdo->prepare(
        'SELECT pd.id, p.nombre_completo, p.correo
           FROM postulacion_documentos pd
           JOIN postulaciones p ON p.id = pd.postulacion_id
          WHERE pd.postulacion_id = :pid AND pd.tipo = :tipo
          FOR UPDATE'
    );
    $stmtDoc->execute(['pid' => $postulacionId, 'tipo' => $tipoDocumento]);
    $documento = $stmtDoc->fetch();

    if (!$documento) {
        throw new RuntimeException('Ese documento no fue subido por el postulante.|404');
    }

    $stmtUpdate = $pdo->prepare(
        'UPDATE postulacion_documentos
            SET rechazado_at = NOW(), rechazado_por = :uid, motivo_rechazo = :motivo, resubido_at = NULL
          WHERE id = :id'
    );
    $stmtUpdate->execute(['uid' => $usuario['id'], 'motivo' => $motivo, 'id' => $documento['id']]);

    // Si se rechaza la cédula, la identidad ya no puede seguir marcada
    // como verificada -- el JAO tendrá que revisar la foto nueva y
    // volver a confirmar (o rechazar de nuevo) cuando la resuban.
    if ($tipoDocumento === 'cedula_identidad') {
        $stmtLimpiar = $pdo->prepare(
            'UPDATE postulaciones SET identidad_verificada_at = NULL, identidad_verificada_por = NULL WHERE id = :id'
        );
        $stmtLimpiar->execute(['id' => $postulacionId]);
    }

    // Token de un solo propósito para que el postulante corrija SOLO
    // lo observado, sin repetir toda la Etapa 2.
    $token = generarTokenPrivado();
    $expira = (new DateTime())->modify('+' . TOKEN_SUBSANACION_HORAS_VALIDEZ . ' hours')->format('Y-m-d H:i:s');
    $stmtToken = $pdo->prepare(
        'UPDATE postulaciones SET token_subsanacion = :token, token_subsanacion_expira_at = :expira WHERE id = :id'
    );
    $stmtToken->execute(['token' => $token, 'expira' => $expira, 'id' => $postulacionId]);

    registrarLog(
        $pdo,
        $postulacionId,
        $usuario['id'],
        'Rechazó el documento "' . $etiquetas[$tipoDocumento] . '": ' . $motivo
    );

    $pdo->commit();
} catch (RuntimeException $e) {
    $pdo->rollBack();
    [$mensaje, $status] = explode('|', $e->getMessage());
    responderError($mensaje, (int)$status);
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('rechazar_documento error: ' . $e->getMessage());
    responderError('No fue posible rechazar el documento.', 500);
}

// --- Correo al postulante (no bloqueante) --------------------------------
require_once __DIR__ . '/../../mailer/Mailer.php';
$nombreCompleto = $documento['nombre_completo'];
$etiquetaDoc = $etiquetas[$tipoDocumento];
$urlSubsanacion = BASE_URL . '/frontend/public/subsanar.html?token=' . $token;
$html = (function () use ($nombreCompleto, $etiquetaDoc, $motivo, $urlSubsanacion) {
    return require __DIR__ . '/../../mailer/templates/documento_rechazado.php';
})();
Mailer::enviar($documento['correo'], $nombreCompleto, 'Necesitamos que corrijas un documento - ICAFAL', $html);

responderOk(['mensaje' => 'Documento rechazado. Se le avisó al postulante para que lo corrija.']);
