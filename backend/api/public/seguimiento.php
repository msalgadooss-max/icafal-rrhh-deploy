<?php
/**
 * Modulo de seguimiento publico. El postulante se identifica con
 * RUT + codigo_seguimiento (dos factores que solo el conoce) y recibe
 * su estado actual mapeado a una linea de tiempo, sin exponer nunca
 * datos de la tabla datos_contratacion.
 *
 * Cuando el estado es 'EPP_listo' o 'Contratado' se marca
 * autorizado_ingreso=true; el frontend pinta la pantalla en VERDE y
 * genera el QR de portería con esos mismos datos minimos.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/rut.php';

exigirMetodo('POST');

$body = leerJsonBody();
$documentoCrudo = trim((string)($body['rut'] ?? ''));
$codigo = strtoupper(limpiarTexto($body['codigo_seguimiento'] ?? '', 10));

if ($documentoCrudo === '' || $codigo === '') {
    responderError('Tu documento y el código de seguimiento son obligatorios.', 422);
}

// v3: el formulario no sabe de antemano si es RUT u "Otro" documento,
// asi que se prueban ambas formas contra lo guardado (que para RUT
// siempre quedo normalizado con guion al postular).
$documentoRut = normalizarRut($documentoCrudo);

$pdo = obtenerConexion();
$stmt = $pdo->prepare(
    'SELECT p.id, p.nombre_completo, p.rut, p.estado, p.codigo_seguimiento, p.creado_at, p.admin_autorizado_at,
            p.token_privado, p.token_expira_at,
            c.nombre_cargo,
            (SELECT COUNT(*) FROM datos_contratacion d WHERE d.postulacion_id = p.id) > 0 AS etapa2_completada,
            (SELECT COUNT(*) FROM postulacion_documentos pd
              WHERE pd.postulacion_id = p.id AND pd.rechazado_at IS NOT NULL AND pd.resubido_at IS NULL) > 0 AS documento_observado
       FROM postulaciones p
       JOIN cargos c ON c.id = p.cargo_id
      WHERE (p.rut = :doc_crudo OR p.rut = :doc_rut) AND p.codigo_seguimiento = :codigo
      LIMIT 1'
);
$stmt->execute(['doc_crudo' => $documentoCrudo, 'doc_rut' => $documentoRut, 'codigo' => $codigo]);
$postulacion = $stmt->fetch();

if (!$postulacion) {
    responderError('No se encontró una postulación con esos datos.', 404);
}

// v2: orden dinamico segun los modulos realmente activos (ver
// includes/functions.php) -- si Prevencion/Bodega estan pausados, no
// se muestran esos pasos en la linea de tiempo.
$ordenEstados = ordenEstadosActivos();

$estadoActual = $postulacion['estado'];
$autorizadoIngreso = in_array($estadoActual, ['EPP_listo', 'Contratado'], true);
$enBanco = $estadoActual === 'En_banco';

// v6.6: si al postulante no le llegó (o no encuentra) el correo con el
// enlace de Etapa 2, este mismo módulo de seguimiento -- ya protegido
// por RUT + código de seguimiento -- le permite continuar sin depender
// del correo. "puede_completar_etapa2" marca que ya le corresponde
// (Admin_Contrato autorizó y aún no completa sus datos); "url_etapa2"
// solo viene si además tiene un token vigente ahora mismo -- si no,
// el frontend ofrece "generar mi enlace" (ver reenviar_etapa2.php).
$puedeCompletarEtapa2 = $estadoActual === 'Pre_aprobado_terreno'
    && $postulacion['admin_autorizado_at'] !== null
    && !$postulacion['etapa2_completada'];
$tokenVigente = $postulacion['token_privado'] !== null
    && $postulacion['token_expira_at'] !== null
    && strtotime($postulacion['token_expira_at']) > time();

responderOk([
    'postulacion' => [
        'nombre_completo'    => $postulacion['nombre_completo'],
        'rut'                => $postulacion['rut'],
        'cargo'              => $postulacion['nombre_cargo'],
        'estado'             => $estadoActual,
        'rechazado'          => $estadoActual === 'Rechazado',
        'en_banco'           => $enBanco,
        'retencion_hasta'    => $enBanco ? fechaRetencionBanco($postulacion['creado_at']) : null,
        // v6.5: esta bandera permite iluminar "Datos completados" en la
        // linea de tiempo aunque el `estado` en si todavia no haya
        // llegado a 'Aprobado_admin' (falta que el JAO reciba y procese).
        'etapa2_completada'  => (bool)$postulacion['etapa2_completada'],
        // v6.5: ilumina "Autorización Administrador de contrato" en la
        // linea de tiempo -- en el flujo secuencial esto SIEMPRE se
        // enciende antes que 'etapa2_completada', porque es justo esta
        // autorización la que le entrega al postulante el acceso a
        // Etapa 2 (ver admin_contrato/autorizar.php).
        'admin_autorizado'   => $postulacion['admin_autorizado_at'] !== null,
        // v5: aviso de respaldo por si el correo de rechazo no llega --
        // el link de subsanación real igual se manda solo por correo,
        // por seguridad (no queremos exponerlo a quien solo tiene RUT
        // y codigo_seguimiento, que es informacion mas facil de adivinar).
        'documento_observado' => (bool)$postulacion['documento_observado'],
        'orden_estados'      => $ordenEstados,
        'codigo_seguimiento' => $postulacion['codigo_seguimiento'],
        'autorizado_ingreso' => $autorizadoIngreso,
        'creado_at'          => $postulacion['creado_at'],
        'puede_completar_etapa2' => $puedeCompletarEtapa2,
        'url_etapa2' => ($puedeCompletarEtapa2 && $tokenVigente)
            ? BASE_URL . '/frontend/public/completar.html?token=' . $postulacion['token_privado']
            : null,
    ],
]);
