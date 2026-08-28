<?php
/**
 * v4 - Consulta autenticada para el rol Porteria: mismo resultado
 * minimo que porteria/validar.php (nombre, RUT, cargo, estado de
 * acceso), pero detrás de login -- para cuando el guardia prefiere
 * escribir el RUT + código a mano en su propio dashboard en vez de
 * escanear el QR del postulante. Nunca toca datos_contratacion.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/rut.php';

iniciarSesionSegura();
requireRol(['Porteria']);
exigirMetodo('POST');

$body = leerJsonBody();
$rutCrudo = trim((string)($body['rut'] ?? ''));
$codigo = strtoupper(limpiarTexto($body['codigo'] ?? '', 10));

if ($rutCrudo === '' || $codigo === '') {
    responderError('RUT y código de seguimiento son obligatorios.', 422);
}

$rutNormalizado = normalizarRut($rutCrudo);

$pdo = obtenerConexion();
$stmt = $pdo->prepare(
    'SELECT p.nombre_completo, p.rut, p.estado, c.nombre_cargo
       FROM postulaciones p
       JOIN cargos c ON c.id = p.cargo_id
      WHERE (p.rut = :rut_crudo OR p.rut = :rut_norm) AND p.codigo_seguimiento = :codigo
      LIMIT 1'
);
$stmt->execute(['rut_crudo' => $rutCrudo, 'rut_norm' => $rutNormalizado, 'codigo' => $codigo]);
$postulacion = $stmt->fetch();

if (!$postulacion) {
    responderError('No se encontró ninguna credencial con esos datos.', 404);
}

$autorizado = in_array($postulacion['estado'], ['EPP_listo', 'Contratado'], true);
$mensaje = null;
if ($autorizado) {
    $mensaje = $postulacion['estado'] === 'Contratado'
        ? 'Proceso realizado exitosamente. Habilitado para ir a oficina JAO a firmar el Contrato.'
        : 'Ingreso permitido a la obra.';
}

responderOk([
    'nombre_completo' => $postulacion['nombre_completo'],
    'rut'             => $postulacion['rut'],
    'cargo'           => $postulacion['nombre_cargo'],
    'estado_acceso'   => $autorizado ? 'AUTORIZADO' : 'NO AUTORIZADO',
    'mensaje'         => $mensaje,
]);
