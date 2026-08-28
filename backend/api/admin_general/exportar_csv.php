<?php
/**
 * "Exportar Carga Masiva": genera un CSV con el JOIN completo de
 * postulaciones + datos_contratacion + cargos, listo para importar en
 * el software de remuneraciones. Solo incluye contratados que aun no
 * se han exportado (exportado_at IS NULL) para evitar duplicar filas
 * en exportaciones sucesivas, y marca exportado_at al terminar.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

iniciarSesionSegura();
$usuario = requireRol(['Jefe_Administrativo']);
exigirMetodo('GET');

$pdo = obtenerConexion();

$stmt = $pdo->query(
    'SELECT p.id, p.rut, p.nombre_completo, p.telefono, p.correo, p.comuna,
            c.nombre_cargo,
            d.fecha_nacimiento, d.estado_civil, d.sexo, d.direccion_exacta,
            d.afp, d.isapre_fonasa, d.banco, d.tipo_cuenta, d.numero_cuenta,
            d.contacto_emergencia_nombre, d.contacto_emergencia_telefono,
            p.actualizado_at AS fecha_ingreso
       FROM postulaciones p
       JOIN cargos c ON c.id = p.cargo_id
       JOIN datos_contratacion d ON d.postulacion_id = p.id
      WHERE p.estado = "Contratado" AND p.exportado_at IS NULL
      ORDER BY p.actualizado_at ASC'
);
$filas = $stmt->fetchAll();

if (!$filas) {
    responderError('No hay contrataciones pendientes de exportar.', 404);
}

$nombreArchivo = 'carga_masiva_remuneraciones_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');

$salida = fopen('php://output', 'w');
fputs($salida, "\xEF\xBB\xBF"); // BOM UTF-8 para que Excel muestre bien las tildes/ñ

fputcsv($salida, [
    'RUT', 'Nombre Completo', 'Telefono', 'Correo', 'Comuna', 'Cargo',
    'Fecha Nacimiento', 'Estado Civil', 'Sexo', 'Direccion',
    'AFP', 'Isapre/Fonasa', 'Banco', 'Tipo Cuenta', 'Numero Cuenta',
    'Contacto Emergencia', 'Telefono Emergencia', 'Fecha Ingreso',
], ';');

$idsExportados = [];
foreach ($filas as $fila) {
    fputcsv($salida, [
        $fila['rut'], $fila['nombre_completo'], $fila['telefono'], $fila['correo'],
        $fila['comuna'], $fila['nombre_cargo'], $fila['fecha_nacimiento'],
        $fila['estado_civil'], $fila['sexo'], $fila['direccion_exacta'],
        $fila['afp'], $fila['isapre_fonasa'], $fila['banco'], $fila['tipo_cuenta'],
        $fila['numero_cuenta'], $fila['contacto_emergencia_nombre'],
        $fila['contacto_emergencia_telefono'], $fila['fecha_ingreso'],
    ], ';');
    $idsExportados[] = (int)$fila['id'];
}
fclose($salida);

// Marcar como exportadas y dejar trazabilidad, DESPUES de generar el
// archivo, para no perder datos si algo falla a mitad de la escritura.
$in = implode(',', array_fill(0, count($idsExportados), '?'));
$stmtMarcar = $pdo->prepare("UPDATE postulaciones SET exportado_at = NOW() WHERE id IN ($in)");
$stmtMarcar->execute($idsExportados);

foreach ($idsExportados as $id) {
    registrarLog($pdo, $id, $usuario['id'], 'Incluido en exportación de carga masiva a remuneraciones.');
}

exit;
