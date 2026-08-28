<?php
/**
 * v5 - El postulante reenvía SOLO los documentos que el JAO marcó como
 * observados, usando el token de subsanación. Cada archivo llega en un
 * campo cuyo nombre es el propio `tipo` (ej. $_FILES['cedula_identidad']),
 * y solo se acepta si ese tipo está efectivamente observado para esta
 * postulación -- así no se puede colar la resubida de un documento que
 * nadie pidió corregir.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

exigirMetodo('POST');

$token = limpiarTexto($_POST['token'] ?? '', 64);
if ($token === '') {
    responderError('Token no proporcionado.', 422);
}

$pdo = obtenerConexion();
$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare(
        'SELECT id, token_subsanacion_expira_at FROM postulaciones WHERE token_subsanacion = :token FOR UPDATE'
    );
    $stmt->execute(['token' => $token]);
    $postulacion = $stmt->fetch();

    if (!$postulacion) {
        throw new RuntimeException('El enlace no es válido.|404');
    }
    if (strtotime($postulacion['token_subsanacion_expira_at']) < time()) {
        throw new RuntimeException('El enlace expiró. Pide a la empresa que te reenvíe la observación.|410');
    }
    $postulacionId = (int)$postulacion['id'];

    $stmtObservados = $pdo->prepare(
        'SELECT tipo FROM postulacion_documentos
          WHERE postulacion_id = :id AND rechazado_at IS NOT NULL AND resubido_at IS NULL'
    );
    $stmtObservados->execute(['id' => $postulacionId]);
    $tiposObservados = $stmtObservados->fetchAll(PDO::FETCH_COLUMN);

    if (!$tiposObservados) {
        throw new RuntimeException('Ya no hay ningún documento pendiente de corrección.|409');
    }

    $etiquetas = etiquetasDocumentos();
    $actualizados = [];
    foreach ($tiposObservados as $tipo) {
        if (($_FILES[$tipo]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue; // el postulante puede corregir de a uno por vez
        }
        $ruta = guardarArchivoSubido($_FILES[$tipo], 'documentos', $etiquetas[$tipo] ?? $tipo);
        $stmtUpdate = $pdo->prepare(
            'UPDATE postulacion_documentos
                SET ruta_archivo = :ruta, resubido_at = NOW()
              WHERE postulacion_id = :pid AND tipo = :tipo'
        );
        $stmtUpdate->execute(['ruta' => $ruta, 'pid' => $postulacionId, 'tipo' => $tipo]);
        registrarLog($pdo, $postulacionId, null, 'Resubió el documento "' . ($etiquetas[$tipo] ?? $tipo) . '" tras una observación.');
        $actualizados[] = $tipo;
    }

    if (!$actualizados) {
        throw new RuntimeException('No se recibió ningún archivo.|422');
    }

    // Si ya no queda ningún documento observado pendiente, el token de
    // subsanación se invalida (es de un solo propósito).
    $stmtQuedan = $pdo->prepare(
        'SELECT COUNT(*) FROM postulacion_documentos
          WHERE postulacion_id = :id AND rechazado_at IS NOT NULL AND resubido_at IS NULL'
    );
    $stmtQuedan->execute(['id' => $postulacionId]);
    if ((int)$stmtQuedan->fetchColumn() === 0) {
        $stmtInvalidar = $pdo->prepare(
            'UPDATE postulaciones SET token_subsanacion = NULL, token_subsanacion_expira_at = NULL WHERE id = :id'
        );
        $stmtInvalidar->execute(['id' => $postulacionId]);
    }

    $pdo->commit();
} catch (RuntimeException $e) {
    $pdo->rollBack();
    [$mensaje, $status] = explode('|', $e->getMessage());
    responderError($mensaje, (int)$status);
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('subsanar_enviar error: ' . $e->getMessage());
    responderError('No fue posible guardar la corrección. Intenta nuevamente.', 500);
}

responderOk(['mensaje' => 'Documento(s) corregido(s) correctamente. Tu contacto en la empresa lo revisará.']);
