<?php
/**
 * v6.4 - Redime un token de acceso directo (ver generar_qr_acceso.php):
 * público, sin sesión previa -- es justamente el link que alguien
 * escanea desde su propio celular para entrar sin pedir clave. Si el
 * token es válido y no ha expirado, deja la sesión autenticada como
 * ese usuario y redirige a su dashboard.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

iniciarSesionSegura();
exigirMetodo('GET');

$token = limpiarTexto($_GET['token'] ?? '', 64);

$RUTA_POR_ROL = [
    'Jefe_Terreno'         => 'terreno.html',
    'Capataz'              => 'capataz.html',
    'Admin_Contrato'       => 'admin_contrato.html',
    'Prevencionista'       => 'prevencion.html',
    'Jefe_Bodega'          => 'bodega.html',
    'Jefe_Administrativo'  => 'admin_general.html',
    'Gerencia'             => 'gerencia.html',
    'Porteria'             => 'porteria.html',
];

function mostrarError(string $mensaje): never
{
    http_response_code(410);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Enlace no válido</title></head>'
        . '<body style="font-family:Arial,sans-serif;max-width:420px;margin:80px auto;text-align:center;color:#1f2937">'
        . '<h2>Enlace no válido</h2><p>' . htmlspecialchars($mensaje) . '</p>'
        . '<p style="font-size:13px;color:#6b7280">Pide un enlace nuevo a tu contacto en ICAFAL.</p>'
        . '</body></html>';
    exit;
}

if ($token === '') {
    mostrarError('Falta el token de acceso.');
}

$pdo = obtenerConexion();
$stmt = $pdo->prepare(
    'SELECT q.expira_at, u.id, u.nombre, u.correo, u.rol, u.activo
       FROM dev_qr_accesos q
       JOIN usuarios u ON u.id = q.usuario_objetivo_id
      WHERE q.token = :token'
);
$stmt->execute(['token' => $token]);
$fila = $stmt->fetch();

if (!$fila || !$fila['activo']) {
    mostrarError('Este enlace no existe o el usuario ya no está activo.');
}
if (strtotime($fila['expira_at']) < time()) {
    mostrarError('Este enlace de acceso expiró.');
}

session_regenerate_id(true);
$_SESSION['usuario'] = [
    'id'     => (int)$fila['id'],
    'nombre' => $fila['nombre'],
    'correo' => $fila['correo'],
    'rol'    => $fila['rol'],
];
generarCsrfToken();

$ruta = $RUTA_POR_ROL[$fila['rol']] ?? null;
if (!$ruta) {
    mostrarError('No hay un dashboard configurado para este rol.');
}

header('Location: ' . BASE_URL . '/frontend/dashboards/' . $ruta);
exit;
