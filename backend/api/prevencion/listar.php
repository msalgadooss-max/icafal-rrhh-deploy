<?php
/**
 * Fase 4 - Dashboard Experto en Prevención.
 * Ve candidatos que ya completaron sus datos privados, pero esta
 * consulta NO trae columnas de datos_contratacion: el prevencionista
 * solo necesita identificar a la persona para dictar la charla ODI,
 * no ver AFP/banco/etc.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

iniciarSesionSegura();
requireRol(['Prevencionista']);
exigirModuloActivo(MODULO_PREVENCION_ACTIVO, 'Prevención');
exigirMetodo('GET');

$pdo = obtenerConexion();
$stmt = $pdo->query(
    'SELECT p.id, p.rut, p.nombre_completo, c.nombre_cargo, p.actualizado_at,
            (SELECT COUNT(*) FROM videos_induccion WHERE activo = 1) AS videos_total,
            (SELECT COUNT(*) FROM postulante_videos_vistos sv
              JOIN videos_induccion v ON v.id = sv.video_id AND v.activo = 1
             WHERE sv.postulacion_id = p.id) AS videos_vistos
       FROM postulaciones p
       JOIN cargos c ON c.id = p.cargo_id
      WHERE p.estado = "Datos_completados"
      ORDER BY p.actualizado_at ASC'
);

responderOk(['postulaciones' => $stmt->fetchAll()]);
