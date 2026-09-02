<?php
/**
 * Fase 4 - Dashboard Experto en Prevención.
 * Ve candidatos que ya completaron sus datos privados, pero esta
 * consulta NO trae columnas de datos_contratacion: el prevencionista
 * solo necesita identificar a la persona para dictar la charla ODI,
 * no ver AFP/banco/etc.
 *
 * v7: candado nuevo -- solo se ven (y se puede actuar sobre) postulantes
 * a quienes el JAO YA verificó la identidad (día 1). Antes filtraba por
 * el estado 'Datos_completados', que en la práctica nunca se alcanzaba
 * (el flujo secuencial deja el estado en 'Aprobado_admin' hasta que
 * Prevención marca la IRL) -- este cambio corrige ese bug de paso, y de
 * paso agrega el candado que pidió Ricardo.
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
      WHERE p.estado = "Aprobado_admin" AND p.identidad_verificada_at IS NOT NULL
      ORDER BY p.actualizado_at ASC'
);

responderOk(['postulaciones' => $stmt->fetchAll()]);
