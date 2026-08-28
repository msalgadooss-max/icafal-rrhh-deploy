<?php
/**
 * Etapa 3 (cierre) - Dashboard Jefe Administrativo (JAO).
 * Revisa la ficha completa (datos + documentos) y llena los campos
 * amarillos (datos_jao) antes de "Finalizar Contratación".
 *
 * v2: el estado que se exige como "listo para cerrar" es dinamico
 * (estadoPrevioAContratado()) -- con Prevencion y Bodega pausados,
 * eso es 'Aprobado_admin' (v3: cambio de 'Datos_completados' porque el
 * orden del pipeline se invirtio, ver functions.php).
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

iniciarSesionSegura();
requireRol(['Jefe_Administrativo']);
exigirMetodo('GET');

$pdo = obtenerConexion();
$stmt = $pdo->prepare(
    'SELECT p.id, p.tipo_documento, p.rut, p.nombre_completo, p.telefono, p.correo, p.comuna,
            p.identidad_verificada_at, uv.nombre AS identidad_verificada_por_nombre,
            c.nombre_cargo,
            d.fecha_nacimiento, d.estado_civil, d.sexo, d.nacionalidad, d.direccion_exacta,
            d.region, d.comuna AS comuna_etapa2, d.ciudad, d.pais,
            d.afp, d.afp_alerta_jao, d.isapre_fonasa, d.estudios, d.banco, d.tipo_cuenta, d.numero_cuenta,
            d.contacto_emergencia_nombre, d.contacto_emergencia_telefono,
            d.talla_calzado, d.talla_overol,
            j.id AS datos_jao_id
       FROM postulaciones p
       JOIN cargos c ON c.id = p.cargo_id
       JOIN datos_contratacion d ON d.postulacion_id = p.id
       LEFT JOIN datos_jao j ON j.postulacion_id = p.id
       LEFT JOIN usuarios uv ON uv.id = p.identidad_verificada_por
      WHERE p.estado = :estado
      ORDER BY p.actualizado_at ASC'
);
$stmt->execute(['estado' => estadoPrevioAContratado()]);
$postulaciones = $stmt->fetchAll();

$idsMostrados = array_column($postulaciones, 'id');
$documentosPorPostulacion = [];
if ($idsMostrados) {
    $in = implode(',', array_fill(0, count($idsMostrados), '?'));
    // v5: se trae el detalle de rechazo de cada documento (no solo el
    // tipo) para que el dashboard muestre el estado de observación por
    // documento y el motivo indicado por el JAO.
    $stmtDocs = $pdo->prepare(
        "SELECT postulacion_id, tipo, rechazado_at, motivo_rechazo, resubido_at
           FROM postulacion_documentos WHERE postulacion_id IN ($in)"
    );
    $stmtDocs->execute($idsMostrados);
    foreach ($stmtDocs->fetchAll() as $fila) {
        $documentosPorPostulacion[$fila['postulacion_id']][] = [
            'tipo'            => $fila['tipo'],
            'observado'       => $fila['rechazado_at'] !== null && $fila['resubido_at'] === null,
            'motivo_rechazo'  => $fila['motivo_rechazo'],
            'resubido'        => $fila['resubido_at'] !== null,
        ];
    }
}
foreach ($postulaciones as &$p) {
    $detalleDocs = $documentosPorPostulacion[$p['id']] ?? [];
    $p['documentos'] = array_column($detalleDocs, 'tipo');
    $p['documentos_detalle'] = $detalleDocs;
    $p['tiene_documento_observado'] = (bool)array_filter($detalleDocs, fn ($d) => $d['observado']);
    $p['tiene_datos_jao'] = $p['datos_jao_id'] !== null;
    $p['afp_alerta_jao'] = (bool)$p['afp_alerta_jao'];
    $p['identidad_verificada'] = $p['identidad_verificada_at'] !== null;
    unset($p['datos_jao_id']);
}
unset($p);

responderOk([
    'postulaciones' => $postulaciones,
    'cierre_remuneraciones_activo' => cierreRemuneracionesActivo($pdo),
]);
