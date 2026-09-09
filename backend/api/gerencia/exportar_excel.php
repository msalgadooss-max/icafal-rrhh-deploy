<?php
/**
 * v10.10 (pedido explícito del usuario, rol Subgerente/Gerencia) -
 * Exporta a Excel TODO el embudo (todas las postulaciones, sin importar
 * su fase -- mismo alcance que gerencia/listar.php), filtrando por
 * rango de fecha de creación. A diferencia del export de Admin_Contrato
 * (que solo mira "Personal Autorizado" y filtra por admin_autorizado_at),
 * Gerencia ve el proceso completo desde el día 0, así que el filtro acá
 * es sobre `creado_at` -- cuándo se postuló, no cuándo se autorizó.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

$vendorAutoload = __DIR__ . '/../../../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    responderError('Falta instalar dependencias (composer install) para generar el Excel.', 500);
}
require_once $vendorAutoload;

iniciarSesionSegura();
requireRol(['Gerencia']);
exigirMetodo('GET');

$desde = limpiarTexto($_GET['desde'] ?? '', 19);
$hasta = limpiarTexto($_GET['hasta'] ?? '', 19);

$pdo = obtenerConexion();

$sql = 'SELECT p.tipo_documento, p.rut, p.nombre_completo, p.comuna, p.estado,
               c.nombre_cargo, p.creado_at, p.actualizado_at
          FROM postulaciones p
          JOIN cargos c ON c.id = p.cargo_id
         WHERE 1 = 1';
$params = [];
if ($desde !== '') { $sql .= ' AND p.creado_at >= ?'; $params[] = $desde; }
if ($hasta !== '') { $sql .= ' AND p.creado_at <= ?'; $params[] = $hasta; }
$sql .= ' ORDER BY p.creado_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$filas = $stmt->fetchAll();

if (!$filas) {
    responderError('No hay postulaciones en ese rango de fechas.', 404);
}

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$hoja = $spreadsheet->getActiveSheet();
$hoja->setTitle('Todo el proceso');

$encabezados = ['Tipo Doc.', 'RUT', 'Nombre', 'Comuna', 'Cargo', 'Estado', 'Fecha postulación', 'Última actualización'];
foreach ($encabezados as $i => $h) {
    $hoja->setCellValue([$i + 1, 1], $h);
}
$hoja->getStyle('1:1')->getFont()->setBold(true);

foreach ($filas as $idx => $f) {
    $fila = $idx + 2;
    $valores = [
        $f['tipo_documento'],
        $f['rut'],
        $f['nombre_completo'],
        $f['comuna'],
        $f['nombre_cargo'],
        $f['estado'],
        $f['creado_at'],
        $f['actualizado_at'],
    ];
    foreach ($valores as $i => $v) {
        $hoja->setCellValue([$i + 1, $fila], $v);
    }
}
foreach (range(1, count($encabezados)) as $i) {
    $hoja->getColumnDimensionByColumn($i)->setAutoSize(true);
}

$nombreArchivo = 'proceso_completo_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
exit;
