<?php
/**
 * v3.2 - Histórico de Jefe de Terreno: "Personal Aprobado en Proceso"
 * y "Personal Contratado", separados de la cola de "Postulantes" y del
 * "Banco de Postulantes" (esos dos siguen en terreno/listar.php y
 * terreno/banco_listar.php). Se puede filtrar por rango de fechas y
 * por quién aprobó (útil cuando haya más de un Jefe_Terreno).
 *
 * "Quién aprobó" y "cuándo" no son columnas propias: se leen de
 * trazabilidad_logs, tomando la PRIMERA vez que la postulación entró a
 * 'Pre_aprobado_terreno' (ya sea por aprobación directa o por
 * invitación desde el Banco de Postulantes).
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

iniciarSesionSegura();
requireRol(['Jefe_Terreno']);
exigirMetodo('GET');

$vista = $_GET['vista'] ?? 'en_proceso';
if (!in_array($vista, ['en_proceso', 'contratados'], true)) {
    responderError('Vista inválida.', 422);
}

$desde = limpiarTexto($_GET['desde'] ?? '', 10);
$hasta = limpiarTexto($_GET['hasta'] ?? '', 10);
$aprobadoPor = (int)($_GET['aprobado_por'] ?? 0);

$pdo = obtenerConexion();

$estadosEnProceso = ['Pre_aprobado_terreno', 'Datos_completados', 'Aprobado_admin', 'Induccion_ok', 'EPP_listo'];
$condicionEstado = $vista === 'contratados'
    ? 'p.estado = "Contratado"'
    : 'p.estado IN (' . implode(',', array_fill(0, count($estadosEnProceso), '?')) . ')';

$params = $vista === 'contratados' ? [] : $estadosEnProceso;

$sql = "SELECT p.id, p.tipo_documento, p.rut, p.nombre_completo, p.comuna, p.estado,
               c.nombre_cargo,
               ap.fecha_hora AS fecha_aprobacion, ap.usuario_id AS aprobado_por_id,
               u.nombre AS aprobado_por_nombre
          FROM postulaciones p
          JOIN cargos c ON c.id = p.cargo_id
          JOIN (
                SELECT postulacion_id,
                       MIN(fecha_hora) AS fecha_hora,
                       SUBSTRING_INDEX(GROUP_CONCAT(usuario_id ORDER BY fecha_hora SEPARATOR ','), ',', 1) AS usuario_id
                  FROM trazabilidad_logs
                 WHERE accion IN (
                        'Cambio de estado: Pendiente -> Pre_aprobado_terreno',
                        'Cambio de estado: En_banco -> Pre_aprobado_terreno'
                       )
                 GROUP BY postulacion_id
               ) ap ON ap.postulacion_id = p.id
          LEFT JOIN usuarios u ON u.id = ap.usuario_id
         WHERE $condicionEstado";

if ($desde !== '') {
    $sql .= ' AND ap.fecha_hora >= ?';
    $params[] = $desde . ' 00:00:00';
}
if ($hasta !== '') {
    $sql .= ' AND ap.fecha_hora <= ?';
    $params[] = $hasta . ' 23:59:59';
}
if ($aprobadoPor > 0) {
    $sql .= ' AND ap.usuario_id = ?';
    $params[] = $aprobadoPor;
}
$sql .= ' ORDER BY ap.fecha_hora DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$stmtTerrenos = $pdo->query("SELECT id, nombre FROM usuarios WHERE rol = 'Jefe_Terreno' AND activo = 1 ORDER BY nombre");

responderOk([
    'postulaciones' => $stmt->fetchAll(),
    'jefes_terreno' => $stmtTerrenos->fetchAll(),
]);
