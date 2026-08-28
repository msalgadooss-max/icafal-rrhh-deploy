<?php
/**
 * v3.4 - Exporta a Excel el "Personal Autorizado" por Admin_Contrato,
 * con el mismo filtro de fecha/hora que la vista, incluyendo el tiempo
 * hasta la contratación de cada uno.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

$vendorAutoload = __DIR__ . '/../../../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    responderError('Falta instalar dependencias (composer install) para generar el Excel.', 500);
}
require_once $vendorAutoload;

iniciarSesionSegura();
requireRol(['Admin_Contrato']);
exigirMetodo('GET');

$desde = limpiarTexto($_GET['desde'] ?? '', 19);
$hasta = limpiarTexto($_GET['hasta'] ?? '', 19);

$pdo = obtenerConexion();

$sql = "SELECT p.tipo_documento, p.rut, p.nombre_completo, p.estado,
               c.nombre_cargo, p.admin_autorizado_at,
               ap.fecha_hora AS fecha_aprobacion_terreno,
               CASE WHEN p.estado = 'Contratado' THEN p.actualizado_at ELSE NULL END AS fecha_contratado
          FROM postulaciones p
          JOIN cargos c ON c.id = p.cargo_id
          LEFT JOIN (
                SELECT postulacion_id, MIN(fecha_hora) AS fecha_hora
                  FROM trazabilidad_logs
                 WHERE accion IN (
                        'Cambio de estado: Pendiente -> Pre_aprobado_terreno',
                        'Cambio de estado: En_banco -> Pre_aprobado_terreno'
                       )
                 GROUP BY postulacion_id
               ) ap ON ap.postulacion_id = p.id
         WHERE p.admin_autorizado_at IS NOT NULL";
$params = [];
if ($desde !== '') { $sql .= ' AND p.admin_autorizado_at >= ?'; $params[] = $desde; }
if ($hasta !== '') { $sql .= ' AND p.admin_autorizado_at <= ?'; $params[] = $hasta; }
$sql .= ' ORDER BY p.admin_autorizado_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$filas = $stmt->fetchAll();

if (!$filas) {
    responderError('No hay personal autorizado en ese rango de fechas.', 404);
}

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$hoja = $spreadsheet->getActiveSheet();
$hoja->setTitle('Personal Autorizado');

$encabezados = ['Tipo Doc.', 'RUT', 'Nombre', 'Cargo', 'Estado actual', 'Aprobado por Terreno', 'Autorizado por Admin', 'Fecha Contratación', 'Horas hasta contratación'];
foreach ($encabezados as $i => $h) {
    $hoja->setCellValue([$i + 1, 1], $h);
}
$hoja->getStyle('1:1')->getFont()->setBold(true);

foreach ($filas as $idx => $f) {
    $fila = $idx + 2;
    $horas = null;
    if ($f['fecha_contratado'] && $f['fecha_aprobacion_terreno']) {
        $horas = round((strtotime($f['fecha_contratado']) - strtotime($f['fecha_aprobacion_terreno'])) / 3600, 1);
    }
    $valores = [
        $f['tipo_documento'],
        $f['rut'],
        $f['nombre_completo'],
        $f['nombre_cargo'],
        $f['estado'],
        $f['fecha_aprobacion_terreno'] ?? '',
        $f['admin_autorizado_at'] ?? '',
        $f['fecha_contratado'] ?? '',
        $horas ?? '',
    ];
    foreach ($valores as $i => $v) {
        $hoja->setCellValue([$i + 1, $fila], $v);
    }
}
foreach (range(1, count($encabezados)) as $i) {
    $hoja->getColumnDimensionByColumn($i)->setAutoSize(true);
}

$nombreArchivo = 'personal_autorizado_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
exit;
