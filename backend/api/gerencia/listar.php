<?php
/**
 * Panel de Gerencia ("usuario maestro") - Fase 0 a 7, TODO el embudo.
 * Rol de SOLO LECTURA: esta carpeta no tiene ningun endpoint de
 * escritura (aprobar/rechazar/avanzar), a propósito, para que Gerencia
 * jamás pueda alterar el proceso desde este panel.
 *
 * Igual que Terreno/Bodega, esta consulta NO hace join con
 * datos_contratacion: Gerencia ve el AVANCE del proceso, no datos
 * sensibles (AFP, banco, etc.) de cada persona.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

iniciarSesionSegura();
requireRol(['Gerencia']);
exigirMetodo('GET');

$pdo = obtenerConexion();
$stmt = $pdo->query(
    'SELECT p.id, p.tipo_documento, p.rut, p.nombre_completo, p.comuna, p.estado,
            c.nombre_cargo, p.creado_at, p.actualizado_at
       FROM postulaciones p
       JOIN cargos c ON c.id = p.cargo_id
      ORDER BY p.actualizado_at DESC'
);
$postulaciones = $stmt->fetchAll();

// Resumen de conteo por estado, para las tarjetas de KPI del panel.
// v2: incluye 'En_banco' (Banco de Postulantes).
$resumen = array_fill_keys([
    'En_banco', 'Pendiente', 'Pre_aprobado_terreno', 'Aprobado_admin', 'Datos_completados',
    'Induccion_ok', 'EPP_listo', 'Contratado', 'Rechazado',
], 0);
foreach ($postulaciones as $p) {
    $resumen[$p['estado']]++;
}

// Cupos por cargo, para ver dotación disponible vs. total de un vistazo.
$stmtCargos = $pdo->query('SELECT nombre_cargo, cupos_totales, cupos_activos FROM cargos WHERE activo = 1 ORDER BY nombre_cargo');

responderOk([
    'postulaciones' => $postulaciones,
    'resumen_estados' => $resumen,
    'cargos' => $stmtCargos->fetchAll(),
    'cierre_remuneraciones_activo' => cierreRemuneracionesActivo($pdo),
    'modulos' => [
        'prevencion' => MODULO_PREVENCION_ACTIVO,
        'bodega'     => MODULO_BODEGA_ACTIVO,
    ],
]);
