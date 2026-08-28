<?php
/**
 * v6.5 - Dashboard Administrador de Contrato.
 * Ve postulaciones que Jefe_Terreno ya pre-aprobo y que TODAVIA no
 * autoriza. El postulante, a esta altura, nunca tiene datos_contratacion
 * -- el flujo es SECUENCIAL: recien cuando Admin_Contrato autoriza (ver
 * autorizar.php) el postulante recibe el link de Etapa 2. Solo necesita
 * el CV para decidir, no los demas documentos (esos los revisa el JAO
 * mas adelante, una vez completa la Etapa 2).
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

iniciarSesionSegura();
requireRol(['Admin_Contrato']);
exigirMetodo('GET');

$pdo = obtenerConexion();
$stmt = $pdo->query(
    'SELECT p.id, p.tipo_documento, p.rut, p.nombre_completo, p.telefono, p.correo, p.comuna,
            c.nombre_cargo, p.creado_at,
            (p.cv_ruta_archivo IS NOT NULL) AS tiene_cv
       FROM postulaciones p
       JOIN cargos c ON c.id = p.cargo_id
      WHERE p.estado = "Pre_aprobado_terreno" AND p.admin_autorizado_at IS NULL
      ORDER BY p.actualizado_at ASC'
);

$postulaciones = array_map(function ($p) {
    $p['tiene_cv'] = (bool)$p['tiene_cv'];
    return $p;
}, $stmt->fetchAll());

responderOk(['postulaciones' => $postulaciones]);
