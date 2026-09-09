<?php
/**
 * v7: selección en terreno en DOS pasos secuenciales (reunión Ricardo,
 * 31-ago) -- este mismo endpoint hace cosas distintas según el rol de
 * quien llama, para no duplicar la ruta ni el botón en el frontend:
 *
 *   - Jefe_Terreno (paso 1): filtra una postulación 'Pendiente' sin
 *     filtrar aún. Marca aprobado_jt_at/aprobado_jt_por -- el estado
 *     NO cambia (sigue 'Pendiente'), por eso se deja un log manual
 *     (el trigger automático solo dispara con cambios de estado).
 *     Recién ahí la postulación aparece en el panel del Capataz.
 *
 *   - Capataz (paso 2, en persona/portería): solo puede actuar sobre
 *     postulaciones que Jefe_Terreno ya filtró (aprobado_jt_at IS NOT
 *     NULL). Su selección es la que de verdad hace avanzar el estado a
 *     'Pre_aprobado_terreno' -- mismo comportamiento que existía antes
 *     de este cambio.
 *
 *   - v10.5 (Mejorar APP, punto 2): el postulante ya no elige cargo al
 *     postular (nace con el cargo interno "Por asignar"). Es justo en
 *     este paso, cuando el Capataz selecciona a la persona en persona,
 *     donde se asigna el cargo real -- por eso el chequeo de cupos
 *     (que antes vivía en public/postular.php) se movió para acá.
 *
 *   - v10.7 (pedido explícito del usuario, tras describir el proceso
 *     completo en detalle): el correo con el link de Etapa 2 ahora se
 *     envía JUSTO ACÁ, apenas el Capataz selecciona a la persona en
 *     portería -- para que lo llene ahí mismo, en la sala de espera, con
 *     su celular. Antes salía recién cuando el Administrador de
 *     Contrato autorizaba por separado (otro rol, otro momento); esa
 *     autorización ahora es un dato puramente interno que el postulante
 *     nunca ve ni es notificado (ver admin_contrato/autorizar.php).
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

iniciarSesionSegura();
$usuario = requireRol(['Jefe_Terreno', 'Capataz']);
exigirMetodo('POST');
exigirCsrfValido();

$body = leerJsonBody();
$postulacionId = (int)($body['postulacion_id'] ?? 0);
if ($postulacionId <= 0) {
    responderError('postulacion_id inválido.', 422);
}
// v10.5: solo lo usa la rama Capataz (ver más abajo), pero se lee acá
// arriba junto al resto del body por prolijidad.
$cargoId = (int)($body['cargo_id'] ?? 0);

$pdo = obtenerConexion();

if ($usuario['rol'] === 'Jefe_Terreno') {
    $stmtCheck = $pdo->prepare('SELECT estado, aprobado_jt_at FROM postulaciones WHERE id = :id FOR UPDATE');
    $stmtCheck->execute(['id' => $postulacionId]);
    $postulacion = $stmtCheck->fetch();

    if (!$postulacion) {
        responderError('Postulación no encontrada.', 404);
    }
    if ($postulacion['estado'] !== 'Pendiente') {
        responderError('La postulación ya no está en estado Pendiente.', 409);
    }
    if ($postulacion['aprobado_jt_at'] !== null) {
        responderError('Ya fue aprobada por Jefe de Terreno.', 409);
    }

    $stmt = $pdo->prepare(
        'UPDATE postulaciones
            SET aprobado_jt_at = NOW(), aprobado_jt_por = :uid
          WHERE id = :id AND estado = "Pendiente" AND aprobado_jt_at IS NULL'
    );
    $stmt->execute(['uid' => $usuario['id'], 'id' => $postulacionId]);

    registrarLog($pdo, $postulacionId, $usuario['id'], 'Aprobó el primer filtro (Jefe de Terreno). Pasa a selección del Capataz.');

    responderOk(['mensaje' => 'Aprobada. Pasa a selección del Capataz en terreno.']);
}

// --- Capataz: selección final, en persona -------------------------------
if ($cargoId <= 0) {
    responderError('Debes indicar el cargo que le vas a asignar.', 422);
}

exigirCupoDiarioAprobaciones($pdo, $usuario['id']);

$pdo->beginTransaction();

try {
    $stmtCheck = $pdo->prepare('SELECT estado, aprobado_jt_at FROM postulaciones WHERE id = :id FOR UPDATE');
    $stmtCheck->execute(['id' => $postulacionId]);
    $postulacion = $stmtCheck->fetch();

    if (!$postulacion) {
        throw new RuntimeException('Postulación no encontrada.|404');
    }
    if ($postulacion['estado'] !== 'Pendiente') {
        throw new RuntimeException('La postulación ya no está en estado Pendiente.|409');
    }
    if ($postulacion['aprobado_jt_at'] === null) {
        throw new RuntimeException('Esta postulación todavía no pasa el primer filtro de Jefe de Terreno.|409');
    }

    // v10.5: el cargo real recién se valida y se fija acá -- antes de
    // esto la postulación apuntaba al cargo interno "Por asignar".
    $stmtCargo = $pdo->prepare('SELECT id, cupos_activos FROM cargos WHERE id = :id AND activo = 1 FOR UPDATE');
    $stmtCargo->execute(['id' => $cargoId]);
    $cargo = $stmtCargo->fetch();
    if (!$cargo) {
        throw new RuntimeException('El cargo seleccionado no existe.|404');
    }
    if ((int)$cargo['cupos_activos'] <= 0) {
        throw new RuntimeException('Ese cargo ya no tiene cupos disponibles. Elige otro.|409');
    }

    fijarUsuarioContextoBD($pdo, $usuario['id']);
    $stmt = $pdo->prepare(
        'UPDATE postulaciones SET estado = "Pre_aprobado_terreno", cargo_id = :cargo_id
          WHERE id = :id AND estado = "Pendiente"'
    );
    $stmt->execute(['cargo_id' => $cargoId, 'id' => $postulacionId]);

    // v10.7: recién ahora el postulante recibe su primer correo para
    // seguir avanzando -- el link de Etapa 2 (datos personales + subir
    // documentos), para completarlo ahí mismo en la sala de espera.
    otorgarAccesoEtapa2($pdo, $postulacionId, $usuario['id']);

    $pdo->commit();
} catch (RuntimeException $e) {
    $pdo->rollBack();
    [$mensaje, $status] = explode('|', $e->getMessage());
    responderError($mensaje, (int)$status);
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('terreno/aprobar error: ' . $e->getMessage());
    responderError('No fue posible aprobar la postulación.', 500);
}

responderOk(['mensaje' => 'Seleccionado. Pasa a revisión del Administrador de Contrato.']);
