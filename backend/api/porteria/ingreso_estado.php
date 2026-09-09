<?php
/**
 * v7 - Consulta pública (sin login) para el QR de "ingreso a faena" --
 * ver notificarIngresoFaena() y frontend/public/ingreso_faena.html.
 * Muestra lo mínimo (nombre, RUT, cargo) más si todavía se puede
 * confirmar el ingreso o si ya se hizo antes. Nunca datos de
 * datos_contratacion.
 *
 * v10.9: "puede_confirmar" ya no exige 'Aprobado_admin' -- basta con
 * que el Capataz ya haya seleccionado a la persona (ver
 * terreno/aprobar.php, que es donde ahora se envía este QR).
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
$puedeConfirmar = !$yaConfirmado
    && !in_array($postulacion['estado'], ['Pendiente', 'En_banco', 'Rechazado'], true);

responderOk([
    'nombre_completo'   => $postulacion['nombre_completo'],
    'rut'               => $postulacion['rut'],
    'cargo'             => $postulacion['nombre_cargo'],
    'ya_confirmado'     => $yaConfirmado,
    'puede_confirmar'   => $puedeConfirmar,
]);
