<?php
/**
 * v3.4 - "Personal Autorizado": historico de todo lo que Admin_Contrato
 * ha autorizado, con filtro por rango de fecha/hora (sobre
 * admin_autorizado_at) y el KPI de tiempo hasta la contratación,
 * medido desde que Jefe_Terreno aprobó (no desde que Admin_Contrato
 * autorizó) hasta que el JAO finalizó -- ese es el ciclo completo que
 * pidió el usuario.
 *
 * v6.5 - Desglose en 3 tramos SECUENCIALES (todos en horas Y minutos,
 * no solo horas, para trazar el tiempo con precisión):
 *   - "admin": desde que Terreno aprobó hasta que Admin_Contrato
 *     autorizó (admin_autorizado_at). Es justo esa autorización la que
 *     le da al postulante el acceso a Etapa 2.
 *   - "postulante": desde que Admin_Contrato autorizó hasta que el
 *     postulante completó su Etapa 2 (datos_contratacion.creado_at).
 *     Antes (v3.1-v6.4, flujo en paralelo) este tramo se medía desde
 *     la aprobación de Terreno; ahora se mide desde la autorización del
 *     Administrador porque el postulante no puede ni empezar antes.
 *   - "jao": desde que la postulación entró a 'Aprobado_admin' hasta
 *     que se finalizó la contratación.
 * La fecha de aprobación de Terreno y la de entrada a 'Aprobado_admin'
 * se leen de trazabilidad_logs (mismo patrón que terreno/historico.php).
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
               c.nombre_cargo, p.admin_autorizado_at,
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
         WHERE p.admin_autorizado_at IS NOT NULL";

$params = [];
if ($desde !== '') {
    $sql .= ' AND p.admin_autorizado_at >= ?';
    $params[] = $desde;
}
if ($hasta !== '') {
    $sql .= ' AND p.admin_autorizado_at <= ?';
    $params[] = $hasta;
}
$sql .= ' ORDER BY p.admin_autorizado_at DESC';

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

$acumTotal = $acumPostulante = $acumAdmin = $acumJao = 0;
$nTotal = $nPostulante = $nAdmin = $nJao = 0;

foreach ($filas as &$f) {
    $minTotal = minutosEntre($f['fecha_aprobacion_terreno'], $f['fecha_contratado']);
    $minPostulante = minutosEntre($f['admin_autorizado_at'], $f['fecha_datos_completados']);
    $minAdmin = minutosEntre($f['fecha_aprobacion_terreno'], $f['admin_autorizado_at']);
    $minJao = minutosEntre($f['fecha_aprobado_admin'], $f['fecha_contratado']);

    $f['tiempo_total'] = formatearDuracion($minTotal);
    $f['tiempo_postulante'] = formatearDuracion($minPostulante);
    $f['tiempo_admin'] = formatearDuracion($minAdmin);
    $f['tiempo_jao'] = formatearDuracion($minJao);

    if ($minTotal !== null) { $acumTotal += $minTotal; $nTotal++; }
    if ($minPostulante !== null) { $acumPostulante += $minPostulante; $nPostulante++; }
    if ($minAdmin !== null) { $acumAdmin += $minAdmin; $nAdmin++; }
    if ($minJao !== null) { $acumJao += $minJao; $nJao++; }
}
unset($f);

responderOk([
    'postulaciones' => $filas,
    'kpi_cantidad_contratados' => $nTotal,
    'kpi_promedio_total' => $nTotal > 0 ? formatearDuracion($acumTotal / $nTotal) : null,
    'kpi_promedio_postulante' => $nPostulante > 0 ? formatearDuracion($acumPostulante / $nPostulante) : null,
    'kpi_promedio_admin' => $nAdmin > 0 ? formatearDuracion($acumAdmin / $nAdmin) : null,
    'kpi_promedio_jao' => $nJao > 0 ? formatearDuracion($acumJao / $nJao) : null,
]);
