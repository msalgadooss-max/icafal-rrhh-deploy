<?php
/**
 * v3.1 - Dashboard Administrador de Contrato.
 * Ve postulaciones que Jefe_Terreno ya aprobo, EN PARALELO a que el
 * postulante llena su Etapa 2 -- no espera a que esa Etapa 2 este
 * completa (por eso ya no hace join con datos_contratacion: a esta
 * altura puede no existir todavia). Solo necesita el CV para decidir,
 * no los demas documentos (esos los revisa el JAO mas adelante).
 *
 * admin_autorizado_at IS NULL filtra a los que aun no ha autorizado --
 * una vez que autoriza, desaparece de esta lista aunque el postulante
 * todavia no haya terminado de llenar sus datos.
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
            (p.cv_ruta_archivo IS NOT NULL) AS tiene_cv,
            (SELECT COUNT(*) FROM datos_contratacion d WHERE d.postulacion_id = p.id) > 0 AS postulante_ya_completo
       FROM postulaciones p
       JOIN cargos c ON c.id = p.cargo_id
      WHERE p.estado = "Pre_aprobado_terreno" AND p.admin_autorizado_at IS NULL
      ORDER BY p.actualizado_at ASC'
);

$postulaciones = array_map(function ($p) {
    $p['tiene_cv'] = (bool)$p['tiene_cv'];
    $p['postulante_ya_completo'] = (bool)$p['postulante_ya_completo'];
    return $p;
}, $stmt->fetchAll());

responderOk(['postulaciones' => $postulaciones]);
