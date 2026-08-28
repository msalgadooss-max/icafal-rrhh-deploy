<?php
/**
 * v5 - Panel de Desarrollador: lista los usuarios internos activos
 * (sin contraseña) para poder "entrar como" cualquiera de ellos y
 * revisar su dashboard sin tener que pedir cada clave por separado.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

iniciarSesionSegura();
requireRol(['Desarrollador']);
exigirMetodo('GET');

$pdo = obtenerConexion();
$stmt = $pdo->query(
    "SELECT id, nombre, correo, rol
       FROM usuarios
      WHERE activo = 1 AND rol != 'Desarrollador'
      ORDER BY FIELD(rol, 'Jefe_Terreno','Admin_Contrato','Jefe_Administrativo','Prevencionista','Jefe_Bodega','Porteria','Gerencia'), nombre"
);

responderOk(['usuarios' => $stmt->fetchAll()]);
