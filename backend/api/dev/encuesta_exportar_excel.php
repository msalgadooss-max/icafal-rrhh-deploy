<?php
/**
 * v10.14 (pedido explícito del usuario: "las puedo descargar para hacer
 * mis mediciones?") - Exporta todas las respuestas de la encuesta de
 * satisfacción del piloto a Excel: una hoja "Datos" con cada respuesta
 * en crudo, y una hoja "Resumen" con el promedio de cada pregunta (y de
 * la edad) calculado con fórmulas reales de Excel -- si alguien agrega
 * o corrige una fila a mano en "Datos", el resumen se recalcula solo.
 *
 * Restringido al rol Desarrollador: es el único perfil usado para
 * coordinar el piloto, y la encuesta en sí es anónima (no hay RUT ni
 * nombre que proteger), así que no hace falta abrir esto a más roles.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

$vendorAutoload = __DIR__ . '/../../../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    responderError('Falta instalar dependencias (composer install) para generar el Excel.', 500);
}
require_once $vendorAutoload;

iniciarSesionSegura();
requireRol(['Desarrollador']);
exigirMetodo('GET');

$pdo = obtenerConexion();
$stmt = $pdo->query(
    'SELECT edad, cargo_probado, claridad_pasos, facilidad_datos, facilidad_documentos,
            claridad_correos, tiempo_espera, claridad_seguimiento, dificultad_general,
            recomendaria, comentario, creado_at
       FROM encuesta_satisfaccion
      ORDER BY creado_at ASC'
);
$filas = $stmt->fetchAll();

if (!$filas) {
    responderError('Todavía no hay respuestas de la encuesta.', 404);
}

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

// --- Hoja "Datos": una fila en crudo por respuesta -------------------------
$datos = $spreadsheet->getActiveSheet();
$datos->setTitle('Datos');
$encabezados = [
    'Edad', 'Cargo probado', 'Claridad de los pasos', 'Facilidad datos personales',
    'Facilidad subir documentos', 'Claridad de los correos', 'Tiempo de espera',
    'Claridad del seguimiento', 'Dificultad general', 'Recomendaría', 'Comentario', 'Fecha',
];
foreach ($encabezados as $i => $h) {
    $datos->setCellValue([$i + 1, 1], $h);
}
$datos->getStyle('1:1')->getFont()->setBold(true);

foreach ($filas as $idx => $f) {
    $fila = $idx + 2;
    $valores = [
        (int)$f['edad'], $f['cargo_probado'], (int)$f['claridad_pasos'], (int)$f['facilidad_datos'],
        (int)$f['facilidad_documentos'], (int)$f['claridad_correos'], (int)$f['tiempo_espera'],
        (int)$f['claridad_seguimiento'], (int)$f['dificultad_general'], (int)$f['recomendaria'],
        $f['comentario'], $f['creado_at'],
    ];
    foreach ($valores as $i => $v) {
        $datos->setCellValue([$i + 1, $fila], $v);
    }
}
foreach (range(1, count($encabezados)) as $i) {
    $datos->getColumnDimensionByColumn($i)->setAutoSize(true);
}

$ultimaFila = count($filas) + 1;

// --- Hoja "Resumen": promedios reales (formula AVERAGE, no un numero
// calculado en PHP) para que se recalculen solos si alguien edita o
// agrega filas en "Datos" mas adelante. -------------------------------------
$resumen = $spreadsheet->createSheet();
$resumen->setTitle('Resumen');
$resumen->setCellValue('A1', 'Medición');
$resumen->setCellValue('B1', 'Promedio (1-7)');
$resumen->getStyle('A1:B1')->getFont()->setBold(true);

$columnasPromedio = [
    'Edad promedio (años)'        => 'A',
    'Claridad de los pasos'       => 'C',
    'Facilidad datos personales'  => 'D',
    'Facilidad subir documentos'  => 'E',
    'Claridad de los correos'     => 'F',
    'Tiempo de espera'            => 'G',
    'Claridad del seguimiento'    => 'H',
    'Dificultad general'          => 'I',
    'Recomendaría'                => 'J',
];
$filaResumen = 2;
foreach ($columnasPromedio as $etiqueta => $col) {
    $resumen->setCellValue("A{$filaResumen}", $etiqueta);
    $resumen->setCellValue("B{$filaResumen}", "=AVERAGE(Datos!{$col}2:{$col}{$ultimaFila})");
    $filaResumen++;
}
$resumen->setCellValue("A{$filaResumen}", 'Total de respuestas');
$resumen->setCellValue("B{$filaResumen}", count($filas));
$resumen->getColumnDimension('A')->setAutoSize(true);
$resumen->getColumnDimension('B')->setAutoSize(true);

$spreadsheet->setActiveSheetIndex(0);

$nombreArchivo = 'encuesta_piloto_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
exit;
