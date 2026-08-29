<?php
/**
 * Cierre del proceso: Jefe Administrativo finaliza la contratación.
 * Cambio de estado: estadoPrevioAContratado() -> Contratado (v2: ese
 * estado previo es dinamico segun que modulos esten activos).
 * Dentro de una transaccion:
 *   1) Se bloquea la fila del cargo (FOR UPDATE) y se verifica que
 *      queden cupos_activos > 0 antes de continuar (defensa a nivel
 *      de aplicacion, ademas del trigger que descuenta el cupo).
 *   2) Se actualiza el estado de la postulacion -> el trigger
 *      trg_postulaciones_descuenta_cupo baja cupos_activos en 1.
 *
 * v2: si el cierre de remuneraciones esta activo, este paso queda
 * bloqueado (igual que "Buk no acepta creación de fichas ni emisión
 * de contratos"), pero el resto del proceso sigue funcionando con
 * normalidad -- el bloqueo es de este endpoint, no del sistema.
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
        'Cierre de mes de remuneraciones activo: no se pueden finalizar contrataciones hasta que se reabra. El resto del proceso sigue funcionando con normalidad.',
        423
    );
}

$estadoRequerido = estadoPrevioAContratado();
$pdo->beginTransaction();

try {
    $stmtCheck = $pdo->prepare(
        'SELECT p.id, p.estado, p.cargo_id, p.identidad_verificada_at,
                p.rut, p.codigo_seguimiento, p.correo, p.nombre_completo,
                c.cupos_activos, c.nombre_cargo
           FROM postulaciones p
           JOIN cargos c ON c.id = p.cargo_id
          WHERE p.id = :id
          FOR UPDATE'
    );
    $stmtCheck->execute(['id' => $postulacionId]);
    $postulacion = $stmtCheck->fetch();

    if (!$postulacion) {
        throw new RuntimeException('Postulación no encontrada.|404');
    }
    if ($postulacion['estado'] !== $estadoRequerido) {
        throw new RuntimeException("La postulación no está en estado $estadoRequerido.|409");
    }
    if ((int)$postulacion['cupos_activos'] <= 0) {
        throw new RuntimeException('No quedan cupos activos disponibles para este cargo.|409');
    }

    // v3: no se puede cerrar sin haber llenado antes los campos de
    // nómina (amarillos) -- ver admin_general/guardar_datos_jao.php.
    $stmtJao = $pdo->prepare('SELECT id FROM datos_jao WHERE postulacion_id = :id');
    $stmtJao->execute(['id' => $postulacionId]);
    if (!$stmtJao->fetch()) {
        throw new RuntimeException('Debes completar los datos de nómina antes de finalizar la contratación.|409');
    }

    // v4: tampoco se puede cerrar sin la verificación manual de
    // identidad (RUT declarado vs. cédula subida) -- ver
    // admin_general/verificar_identidad.php.
    if ($postulacion['identidad_verificada_at'] === null) {
        throw new RuntimeException('Debes verificar la identidad (RUT vs. cédula) antes de finalizar la contratación.|409');
    }

    // v5: si hay algún documento rechazado que el postulante aún no ha
    // corregido, se bloquea SOLO este último paso -- el resto de lo ya
    // avanzado (autorización, datos completados, etc.) no se toca ni
    // se revierte.
    $stmtDocPendiente = $pdo->prepare(
        'SELECT COUNT(*) FROM postulacion_documentos
          WHERE postulacion_id = :id AND rechazado_at IS NOT NULL AND resubido_at IS NULL'
    );
    $stmtDocPendiente->execute(['id' => $postulacionId]);
    if ((int)$stmtDocPendiente->fetchColumn() > 0) {
        throw new RuntimeException('Hay un documento observado pendiente de corrección por el postulante.|409');
    }

    fijarUsuarioContextoBD($pdo, $usuario['id']);

    $stmt = $pdo->prepare('UPDATE postulaciones SET estado = "Contratado" WHERE id = :id');
    $stmt->execute(['id' => $postulacionId]);

    $pdo->commit();

    // v6.1: correo final con el QR de acceso a la obra, fuera de la
    // transaccion (que no falle el cierre si el envio de correo falla).
    try {
        notificarContratacionExitosa($pdo, $postulacion);
    } catch (\Throwable $e) {
        error_log('notificarContratacionExitosa error: ' . $e->getMessage());
    }

    // v6.9: avisa a quien lo seleccionó en portería (Capataz/Jefe_Terreno)
    // que ya puede ir a buscarlo -- cierra el ciclo completo de "dueños
    // de etapa" que pidió Ricardo.
    try {
        notificarLiberacionTrabajador($pdo, $postulacion);
    } catch (\Throwable $e) {
        error_log('notificarLiberacionTrabajador error: ' . $e->getMessage());
    }
} catch (RuntimeException $e) {
    $pdo->rollBack();
    [$mensaje, $status] = explode('|', $e->getMessage());
    responderError($mensaje, (int)$status);
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('finalizar contratacion error: ' . $e->getMessage());
    responderError('No fue posible finalizar la contratación.', 500);
}

responderOk(['mensaje' => 'Contratación finalizada. Cupo descontado.']);
