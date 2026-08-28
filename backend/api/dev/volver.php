<?php
/**
 * v5 - Restaura la sesión a la identidad real del Desarrollador después
 * de haber estado "entrando como" otro rol (ver dev/entrar_como.php).
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

iniciarSesionSegura();
requireLogin();
exigirMetodo('POST');
exigirCsrfValido();

if (!isset($_SESSION['dev_original'])) {
    responderError('No hay una sesión de desarrollador para restaurar.', 409);
}

$_SESSION['usuario'] = $_SESSION['dev_original'];
unset($_SESSION['dev_original']);

responderOk(['usuario' => $_SESSION['usuario']]);
