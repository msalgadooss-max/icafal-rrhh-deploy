<?php
/**
 * Utilidad de linea de comandos para crear/actualizar un usuario interno
 * con contraseña hasheada correctamente. NO exponer via navegador: solo
 * debe ejecutarse por SSH/CLI.
 *
 * Uso:
 *   php crear_usuario.php "Nombre Apellido" correo@empresa.cl ClaveSegura123 Jefe_Terreno
 *
 * Roles validos: Jefe_Terreno, Admin_Contrato, Prevencionista,
 *                Jefe_Bodega, Jefe_Administrativo, Gerencia, Porteria
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse por linea de comandos.');
}

require_once __DIR__ . '/../config/database.php';

[$nombre, $correo, $password, $rol] = [$argv[1] ?? null, $argv[2] ?? null, $argv[3] ?? null, $argv[4] ?? null];

$rolesValidos = ['Jefe_Terreno', 'Admin_Contrato', 'Prevencionista', 'Jefe_Bodega', 'Jefe_Administrativo', 'Gerencia', 'Porteria'];

if (!$nombre || !$correo || !$password || !in_array($rol, $rolesValidos, true)) {
    fwrite(STDERR, "Uso: php crear_usuario.php \"Nombre\" correo@empresa.cl Clave123 " . implode('|', $rolesValidos) . "\n");
    exit(1);
}

$pdo = obtenerConexion();
$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare(
    'INSERT INTO usuarios (nombre, correo, password, rol)
     VALUES (:nombre, :correo, :password, :rol)
     ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), password = VALUES(password), rol = VALUES(rol)'
);
$stmt->execute(['nombre' => $nombre, 'correo' => $correo, 'password' => $hash, 'rol' => $rol]);

echo "Usuario '$correo' creado/actualizado correctamente con rol $rol.\n";
