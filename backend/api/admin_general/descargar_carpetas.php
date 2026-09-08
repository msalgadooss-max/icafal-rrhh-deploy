<?php
/**
 * v10 - "Descargar carpetas": el JAO elige (con checkboxes, mismo patrón
 * que exportar_excel.php) a qué trabajadores contratados descargarles su
 * carpeta completa de documentos (la misma que arma
 * generarCarpetaDocumentosPersonales()/renombrarCarpetaConCodigoFicha()
 * en includes/functions.php, nombrada "<código_ficha>_<apellidos>_<nombre>")
 * -- así puede llevarla a su PC y de ahí subirla a Buk, sin depender de
 * que el servidor escriba directo en el disco de nadie (no es posible
 * desde una app en la nube).
 *
 * Si se piden varias personas, se entrega un solo ZIP con una carpeta
 * por persona adentro. Si es una sola, igual se entrega como ZIP (más
 * simple y consistente que decidir el formato según la cantidad).
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

iniciarSesionSegura();
$usuario = requireRol(['Jefe_Administrativo']);
exigirMetodo('GET');

if (!class_exists('ZipArchive')) {
    responderError('El servidor no tiene la extensión ZIP habilitada.', 500);
}

$idsSolicitados = array_filter(array_map('intval', explode(',', (string)($_GET['ids'] ?? ''))), fn ($v) => $v > 0);
if (!$idsSolicitados) {
    responderError('Debes indicar al menos un "ids".', 422);
}

$pdo = obtenerConexion();
$in = implode(',', array_fill(0, count($idsSolicitados), '?'));
$stmt = $pdo->prepare(
    "SELECT p.id, p.nombre, p.apellido, p.segundo_apellido, p.codigo_seguimiento,
            j.codigo_ficha
       FROM postulaciones p
       LEFT JOIN datos_jao j ON j.postulacion_id = p.id
      WHERE p.estado IN ('Contratado', 'Proceso_completo') AND p.id IN ($in)"
);
$stmt->execute(array_values($idsSolicitados));
$personas = $stmt->fetchAll();

if (!$personas) {
    responderError('Ninguna de las personas seleccionadas está Contratada.', 404);
}

$base = carpetaBasePostulantes();
$tmpZip = tempnam(sys_get_temp_dir(), 'icafal_carpetas_');
$zip = new ZipArchive();
if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    responderError('No fue posible preparar el archivo ZIP.', 500);
}

$agregadas = 0;
foreach ($personas as $p) {
    $prefijo = $p['codigo_ficha'] ?: $p['codigo_seguimiento'];
    $nombreCarpeta = nombreCarpetaPostulante($p, $prefijo);
    $rutaCarpeta = $base . '/' . $nombreCarpeta;
    if (!is_dir($rutaCarpeta)) {
        continue; // esta persona todavía no tiene documentos generados en carpeta
    }
    $agregadas++;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rutaCarpeta, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $archivo) {
        if ($archivo->getFilename() === '.htaccess') {
            continue;
        }
        $rutaLocal = $nombreCarpeta . '/' . substr($archivo->getPathname(), strlen($rutaCarpeta) + 1);
        $zip->addFile($archivo->getPathname(), str_replace('\\', '/', $rutaLocal));
    }
}
$zip->close();

if ($agregadas === 0) {
    unlink($tmpZip);
    responderError('Ninguna de las personas seleccionadas tiene todavía una carpeta de documentos generada.', 404);
}

$nombreDescarga = count($personas) === 1
    ? nombreCarpetaPostulante($personas[0], $personas[0]['codigo_ficha'] ?: $personas[0]['codigo_seguimiento']) . '.zip'
    : 'carpetas_trabajadores_' . date('Y-m-d') . '.zip';

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $nombreDescarga . '"');
header('Content-Length: ' . filesize($tmpZip));
readfile($tmpZip);
unlink($tmpZip);
exit;
