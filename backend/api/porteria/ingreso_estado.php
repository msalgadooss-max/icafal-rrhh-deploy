<?php
/**
 * v7 - Consulta pública (sin login) para el QR de "ingreso a faena" del
 * día 1 -- ver notificarIngresoFaena() y frontend/public/ingreso_faena.html.
 * Muestra lo mínimo (nombre, RUT, cargo) más si todavía se puede
 * confirmar el ingreso o si ya se hizo antes. Nunca datos de
 * datos_contratacion.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/rut.php';

exigirMetodo('GET');

$rut = trim((string)($_GET['rut'] ?? ''));
$codigo = strtoupper(limpiarTexto($_GET['codigo'] ?? '', 10));

if ($rut === '' || $codigo === '') {
    responderError('Datos de acceso inválidos.', 422);
}

$pdo = obtenerConexion();
$stmt = $pdo->prepare(
    'SELECT p.nombre_completo, p.rut, p.estado, p.ingreso_faena_at, c.nombre_cargo
       FROM postulaciones p
       JOIN cargos c ON c.id = p.cargo_id
      WHERE p.rut = :rut AND p.codigo_seguimiento = :codigo
      LIMIT 1'
);
$stmt->execute(['rut' => $rut, 'codigo' => $codigo]);
$postulacion = $stmt->fetch();

if (!$postulacion) {
    responderError('Credencial no encontrada.', 404);
}

$yaConfirmado = $postulacion['ingreso_faena_at'] !== null;
$puedeConfirmar = !$yaConfirmado && $postulacion['estado'] === 'Aprobado_admin';

responderOk([
    'nombre_completo'   => $postulacion['nombre_completo'],
    'rut'               => $postulacion['rut'],
    'cargo'             => $postulacion['nombre_cargo'],
    'ya_confirmado'     => $yaConfirmado,
    'puede_confirmar'   => $puedeConfirmar,
]);
