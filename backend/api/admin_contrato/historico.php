<?php
/**
 * v3.4 - "Personal Autorizado": historico con el KPI de tiempo hasta la
 * contratación.
 *
 * v10.13 (pedido explícito del usuario, tras describir de nuevo el
 * proceso completo): Admin_Contrato ya no autoriza postulación por
 * postulación (ver admin_contrato/autorizar.php, retirado del panel) --
 * su tarea termina al aprobar los cupos. Por eso el filtro de fecha y
 * el punto de partida del reporte dejan de ser admin_autorizado_at y
 * pasan a ser el momento en que el Capataz selecciona a la persona en
 * portería (fecha_aprobacion_terreno, leída de trazabilidad_logs, igual
 * que antes). El tramo "Administrador autorizando" desaparece del
 * desglose por no tener ya sentido; quedan dos tramos: postulante
 * llenando Etapa 2, y JAO hasta finalizar.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

iniciarSesionSegura();
requireRol(['Admin_Contrato']);
exigirMetodo('GET');

$desde = limpiarTexto($_GET['desde'] ?? '', 19); // "AAAA-MM-DD HH:MM" o con segundos
$hasta = limpiarTexto($_GET['hasta'] ?? '', 19);

$pdo = obtenerConexion();

$sql = "SELECT p.id, p.tipo_documento, p.rut, p.nombre_completo, p.estado,
               c.nombre_cargo,
               ap.fecha_hora AS fecha_aprobacion_terreno,
               d.creado_at AS fecha_datos_completados,
               aa.fecha_hora AS fecha_aprobado_admin,
               co.fecha_hora AS fecha_contratado
          FROM postulaciones p
          JOIN cargos c ON c.id = p.cargo_id
          LEFT JOIN datos_contratacion d ON d.postulacion_id = p.id
          LEFT JOIN (
                SELECT postulacion_id,
                       MIN(fecha_hora) AS fecha_hora
                  FROM trazabilidad_logs
                 WHERE accion IN (
                        'Cambio de estado: Pendiente -> Pre_aprobado_terreno',
                        'Cambio de estado: En_banco -> Pre_aprobado_terreno'
                       )
                 GROUP BY postulacion_id
               ) ap ON ap.postulacion_id = p.id
          LEFT JOIN (
                SELECT postulacion_id,
                       MIN(fecha_hora) AS fecha_hora
                  FROM trazabilidad_logs
                 WHERE accion = 'Cambio de estado: Pre_aprobado_terreno -> Aprobado_admin'
                 GROUP BY postulacion_id
               ) aa ON aa.postulacion_id = p.id
          -- v7: se lee el momento real en que Bodega marcó 'Contratado'
          -- (bodega/marcar_epp.php) desde la bitácora, en vez de
          -- p.actualizado_at -- así el KPI no se distorsiona cuando,
          -- más tarde, Jefe_Terreno/Capataz confirman la recepción y
          -- el estado avanza a 'Proceso_completo'.
          LEFT JOIN (
                SELECT postulacion_id,
                       MIN(fecha_hora) AS fecha_hora
                  FROM trazabilidad_logs
                 WHERE accion = 'Cambio de estado: Induccion_ok -> Contratado'
                 GROUP BY postulacion_id
               ) co ON co.postulacion_id = p.id
         WHERE ap.fecha_hora IS NOT NULL";

$params = [];
if ($desde !== '') {
    $sql .= ' AND ap.fecha_hora >= ?';
    $params[] = $desde;
}
if ($hasta !== '') {
    $sql .= ' AND ap.fecha_hora <= ?';
    $params[] = $hasta;
}
$sql .= ' ORDER BY ap.fecha_hora DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$filas = $stmt->fetchAll();

/** Minutos entre dos fechas (float, sin redondear todavía), o null si falta alguna. */
function minutosEntre(?string $desde, ?string $hasta): ?float
{
    if (!$desde || !$hasta) {
        return null;
    }
    return (strtotime($hasta) - strtotime($desde)) / 60;
}

/** "2h 35min" / "45min" / "3h" -- nunca solo horas con decimales. */
function formatearDuracion(?float $minutos): ?string
{
    if ($minutos === null) {
        return null;
    }
    $totalMin = (int)round($minutos);
    $h = intdiv($totalMin, 60);
    $m = $totalMin % 60;
    if ($h > 0 && $m > 0) return "{$h}h {$m}min";
    if ($h > 0) return "{$h}h";
    return "{$m}min";
}

$acumTotal = $acumPostulante = $acumJao = 0;
$nTotal = $nPostulante = $nJao = 0;

foreach ($filas as &$f) {
    $minTotal = minutosEntre($f['fecha_aprobacion_terreno'], $f['fecha_contratado']);
    $minPostulante = minutosEntre($f['fecha_aprobacion_terreno'], $f['fecha_datos_completados']);
    $minJao = minutosEntre($f['fecha_aprobado_admin'], $f['fecha_contratado']);

    $f['tiempo_total'] = formatearDuracion($minTotal);
    $f['tiempo_postulante'] = formatearDuracion($minPostulante);
    $f['tiempo_jao'] = formatearDuracion($minJao);

    if ($minTotal !== null) { $acumTotal += $minTotal; $nTotal++; }
    if ($minPostulante !== null) { $acumPostulante += $minPostulante; $nPostulante++; }
    if ($minJao !== null) { $acumJao += $minJao; $nJao++; }
}
unset($f);

responderOk([
    'postulaciones' => $filas,
    'kpi_cantidad_contratados' => $nTotal,
    'kpi_promedio_total' => $nTotal > 0 ? formatearDuracion($acumTotal / $nTotal) : null,
    'kpi_promedio_postulante' => $nPostulante > 0 ? formatearDuracion($acumPostulante / $nPostulante) : null,
    'kpi_promedio_jao' => $nJao > 0 ? formatearDuracion($acumJao / $nJao) : null,
]);
