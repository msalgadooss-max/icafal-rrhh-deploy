<?php
/**
 * Fase 5 - Dashboard Jefe de Bodega.
 * Necesita las tallas para preparar el kit de EPP, por lo que SI hace
 * join con datos_contratacion, pero con allowlist explícita de
 * columnas: solo las 3 tallas. Nunca se seleccionan afp/banco/etc,
 * aunque esas columnas existan en la misma fila.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

iniciarSesionSegura();
requireRol(['Jefe_Bodega']);
exigirModuloActivo(MODULO_BODEGA_ACTIVO, 'Bodega');
exigirMetodo('GET');

$pdo = obtenerConexion();
$stmt = $pdo->query(
    'SELECT p.id, p.rut, p.nombre_completo, c.nombre_cargo,
            d.talla_calzado, d.talla_pantalon, d.talla_polera
       FROM postulaciones p
       JOIN cargos c ON c.id = p.cargo_id
       JOIN datos_contratacion d ON d.postulacion_id = p.id
      WHERE p.estado = "Induccion_ok"
      ORDER BY p.actualizado_at ASC'
);

responderOk(['postulaciones' => $stmt->fetchAll()]);
