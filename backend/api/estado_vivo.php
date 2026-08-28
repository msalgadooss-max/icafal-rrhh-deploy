<?php
/**
 * v6.5 - "Estado en vivo" / "Estado del proceso": una vista compacta,
 * igual para todos los roles internos, que muestra en qué fase visual
 * está cada trabajador activo (estilo rastreo de pedido). No expone
 * NINGÚN dato sensible de datos_contratacion -- solo nombre, cargo y
 * una etiqueta de fase, así que es seguro mostrarlo tal cual en
 * Terreno, Admin_Contrato, JAO y Gerencia por igual. También alimenta
 * la pestaña "Estado del proceso" de Admin_Contrato (misma data, vista
 * en tabla completa en vez de widget resumido).
 *
 * La fase se calcula, no se guarda: combina el `estado` con
 * admin_autorizado_at y si ya existe su fila en datos_contratacion.
 * Desde v6.5 el flujo es SECUENCIAL (ya no en paralelo): el postulante
 * no puede tener datos_contratacion sin que admin_autorizado_at ya
 * esté puesto, porque es justo esa autorización la que le entrega el
 * acceso a Etapa 2 (ver admin_contrato/autorizar.php).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

iniciarSesionSegura();
$usuario = requireRol(['Jefe_Terreno', 'Admin_Contrato', 'Prevencionista', 'Jefe_Bodega', 'Jefe_Administrativo', 'Gerencia']);
exigirMetodo('GET');

$pdo = obtenerConexion();
$stmt = $pdo->query(
    "SELECT p.id, p.nombre_completo, p.estado, p.admin_autorizado_at, c.nombre_cargo,
            (SELECT COUNT(*) FROM datos_contratacion d WHERE d.postulacion_id = p.id) > 0 AS etapa2_completada,
            (SELECT COUNT(*) FROM postulacion_documentos pd
              WHERE pd.postulacion_id = p.id AND pd.rechazado_at IS NOT NULL AND pd.resubido_at IS NULL) > 0 AS documento_observado
       FROM postulaciones p
       JOIN cargos c ON c.id = p.cargo_id
      WHERE p.estado NOT IN ('Rechazado', 'En_banco')
      ORDER BY p.actualizado_at DESC
      LIMIT 30"
);
$filas = $stmt->fetchAll();

function faseVisual(array $p): string
{
    $completo = (bool)$p['etapa2_completada'];
    $autorizado = $p['admin_autorizado_at'] !== null;

    // v5: un documento observado (rechazado por el JAO, aún sin
    // corregir) manda por sobre la fase normal -- es lo mas urgente que
    // hay que saber sobre esta persona en este momento.
    if ((bool)$p['documento_observado']) {
        return 'Documento observado, esperando corrección del postulante';
    }

    return match ($p['estado']) {
        'Pendiente' => 'Postulación recibida, esperando revisión de Terreno',
        'Pre_aprobado_terreno' => match (true) {
            !$autorizado => 'Esperando autorización del Administrador de Contrato',
            $completo => 'A punto de pasar a cierre', // caso raro: join aun no corrio
            default => 'Autorizado, completando datos y documentos (Etapa 2)',
        },
        'Aprobado_admin' => 'En revisión Jefe Administrativo',
        'Induccion_ok' => 'Inducción de seguridad realizada',
        'EPP_listo' => 'Kit de EPP listo, cierre final',
        'Contratado' => '✔ Contratado',
        default => $p['estado'],
    };
}

/**
 * v6 - Rol interno que tiene la "pelota" en este momento (o null si a
 * nadie del staff le toca actuar, ej. se espera al postulante). Sirve
 * para que, cuando el que mira el widget es justo ese rol, se le pueda
 * marcar "pendiente en tu bandeja" en vez de un genérico "en revisión".
 */
function rolPendiente(array $p): ?string
{
    if ((bool)$p['documento_observado']) {
        return null; // se espera al postulante, no a un rol interno
    }
    $autorizado = $p['admin_autorizado_at'] !== null;

    return match ($p['estado']) {
        'Pendiente' => 'Jefe_Terreno',
        'Pre_aprobado_terreno' => $autorizado ? null : 'Admin_Contrato',
        'Aprobado_admin' => 'Jefe_Administrativo',
        'Induccion_ok' => 'Prevencionista',
        'EPP_listo' => 'Jefe_Bodega',
        default => null,
    };
}

/**
 * v4 - Línea de progreso ("inicio y meta") para el detalle interactivo
 * del widget: mismos pasos que ve el propio postulante en
 * seguimiento.js::timelineHtml(), calculados aquí para no exponer otro
 * endpoint nuevo ni duplicar la consulta.
 */
function pasosProgreso(array $p): array
{
    $etiquetas = [
        'Pendiente' => 'Postulación recibida',
        'Pre_aprobado_terreno' => 'Pre-aprobado por Jefe de Terreno',
        'Aprobado_admin' => 'En revisión Jefe Administrativo',
        'Induccion_ok' => 'Inducción de seguridad',
        'EPP_listo' => 'Kit de EPP listo',
        'Contratado' => 'Contratado',
    ];
    $orden = ordenEstadosActivos();
    $idxActual = array_search($p['estado'], $orden, true);
    $completo = (bool)$p['etapa2_completada'];
    $autorizadoAdmin = $p['admin_autorizado_at'] !== null;

    $pasos = [];
    foreach ($orden as $idx => $estado) {
        $pasos[] = ['etiqueta' => $etiquetas[$estado] ?? $estado, 'completado' => $idxActual !== false && $idx <= $idxActual];
        if ($estado === 'Pre_aprobado_terreno') {
            // v6.5: dos hitos sinteticos (no son estados reales), ahora en
            // orden SECUENCIAL real: Admin_Contrato autoriza PRIMERO, y
            // recien ahi el postulante recibe el acceso a Etapa 2 (ver
            // admin_contrato/autorizar.php). intentarAvanzarAAprobadoAdmin()
            // igual valida ambas condiciones antes de pasar a 'Aprobado_admin'.
            $yaSuperado = $idxActual !== false && $idx < $idxActual;
            $pasos[] = ['etiqueta' => 'Autorización Administrador de contrato', 'completado' => $autorizadoAdmin || $yaSuperado];
            $pasos[] = ['etiqueta' => 'Datos completados por el postulante', 'completado' => $completo || $yaSuperado];
        }
    }
    return $pasos;
}

$resultado = array_map(function ($p) use ($usuario) {
    return [
        'id' => (int)$p['id'],
        'nombre_completo' => $p['nombre_completo'],
        'nombre_cargo' => $p['nombre_cargo'],
        'fase' => faseVisual($p),
        'contratado' => $p['estado'] === 'Contratado',
        'pasos' => pasosProgreso($p),
        // v6: true si el rol que está mirando el widget es justo el que
        // tiene que actuar ahora sobre esta persona.
        'pendiente_de_ti' => rolPendiente($p) === $usuario['rol'],
    ];
}, $filas);

responderOk(['trabajadores' => $resultado]);
