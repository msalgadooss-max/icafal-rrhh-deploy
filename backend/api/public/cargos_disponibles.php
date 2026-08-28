<?php
/**
 * Lista publica de cargos, para poblar el select del formulario de
 * postulacion (Fase 0). Solo se exponen los campos necesarios para no
 * filtrar informacion interna de dotacion total (nunca cupos_totales,
 * que refleja la planificacion completa de la obra).
 *
 * v2: ya NO se ocultan los cargos sin cupo -- se muestran igual, con
 * tiene_cupo=false, para que quien postule a un cargo lleno pueda
 * igual dejar sus datos en el Banco de Postulantes en vez de encontrar
 * un formulario vacio sin ninguna opcion que elegir.
 *
 * v4: se agrega cupos_disponibles (= cupos_activos) para que el
 * postulante vea explicitamente "Jornal Concretero -- 6 cupos
 * disponibles", tal como lo pidio el Jefe de Terreno. Estos cupos
 * nacen exclusivamente del flujo de "Solicitar cupos" de Jefe_Terreno
 * (ver terreno/solicitar_cupo.php) -- ya no se cargan a mano en la BD.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

exigirMetodo('GET');

$pdo = obtenerConexion();
$stmt = $pdo->query(
    'SELECT id, nombre_cargo, cupos_activos AS cupos_disponibles, (cupos_activos > 0) AS tiene_cupo
       FROM cargos
      WHERE activo = 1
      ORDER BY nombre_cargo ASC'
);

$cargos = array_map(function ($c) {
    $c['tiene_cupo'] = (bool)$c['tiene_cupo'];
    $c['cupos_disponibles'] = (int)$c['cupos_disponibles'];
    return $c;
}, $stmt->fetchAll());

responderOk(['cargos' => $cargos, 'obra' => OBRA_NOMBRE]);
