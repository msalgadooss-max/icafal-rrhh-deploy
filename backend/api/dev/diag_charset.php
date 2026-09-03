<?php
/**
 * v9.1 - Diagnóstico TEMPORAL para aislar la corrupción de tildes/"ñ"
 * detectada probando el piloto de punta a punta. Muestra las variables
 * de charset que MySQL realmente está usando en esta conexión, el
 * CREATE TABLE real de 'postulaciones', y el hex exacto de un valor ya
 * corrupto en la base, para comparar contra lo esperado.
 *
 * BORRAR este archivo una vez resuelto -- no debe quedar en producción.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

iniciarSesionSegura();
requireRol(['Desarrollador']);
exigirMetodo('GET');

$pdo = obtenerConexion();

$vars = $pdo->query("SHOW VARIABLES LIKE '%char%'")->fetchAll();
$collationVars = $pdo->query("SHOW VARIABLES LIKE '%collation%'")->fetchAll();
$createTable = $pdo->query("SHOW CREATE TABLE postulaciones")->fetch();

$stmtEjemplo = $pdo->prepare(
    'SELECT id, nombre_completo, HEX(nombre_completo) AS hex_nombre
       FROM postulaciones
      WHERE id = :id'
);
$stmtEjemplo->execute(['id' => 1]);
$ejemplo = $stmtEjemplo->fetch();

responderOk([
    'character_variables' => $vars,
    'collation_variables' => $collationVars,
    'create_table_postulaciones' => $createTable['Create Table'] ?? null,
    'ejemplo_id_1' => $ejemplo,
    'php_mb_internal_encoding' => mb_internal_encoding(),
    'php_default_charset_ini' => ini_get('default_charset'),
]);
