<?php
/**
 * v10.14 (pedido explícito del usuario) - Encuesta de satisfacción
 * anónima para los trabajadores de prueba del piloto: 8 preguntas del
 * 1 al 7 sobre distintas aristas del proceso, más un comentario abierto
 * opcional. Pública (sin login) y no pide RUT a propósito -- lo que
 * importa es la percepción general del proceso, no rastrear quién
 * respondió (ver frontend/public/encuesta.html).
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

exigirMetodo('POST');

$body = leerJsonBody();

$preguntas = [
    'claridad_pasos', 'facilidad_datos', 'facilidad_documentos', 'claridad_correos',
    'tiempo_espera', 'claridad_seguimiento', 'dificultad_general', 'recomendaria',
];

$valores = [];
foreach ($preguntas as $clave) {
    $valor = (int)($body[$clave] ?? 0);
    if ($valor < 1 || $valor > 7) {
        responderError('Todas las preguntas deben responderse del 1 al 7.', 422);
    }
    $valores[$clave] = $valor;
}

// v10.14 (pedido explícito del usuario): "sin nombres y con edad como
// dato... es importante marcar eso... para sacar una medición y
// promedio" -- se pide la edad (obligatoria, para el promedio) y se
// quita el nombre por completo, la encuesta queda anónima.
$edad = (int)($body['edad'] ?? 0);
if ($edad < 15 || $edad > 90) {
    responderError('Indica una edad válida.', 422);
}

$cargoProbado = limpiarTexto($body['cargo_probado'] ?? '', 100);
$comentario = limpiarTexto($body['comentario'] ?? '', 1000);

$pdo = obtenerConexion();
$stmt = $pdo->prepare(
    'INSERT INTO encuesta_satisfaccion
        (edad, cargo_probado, claridad_pasos, facilidad_datos, facilidad_documentos,
         claridad_correos, tiempo_espera, claridad_seguimiento, dificultad_general,
         recomendaria, comentario)
     VALUES (:edad, :cargo, :p1, :p2, :p3, :p4, :p5, :p6, :p7, :p8, :comentario)'
);
$stmt->execute([
    'edad'       => $edad,
    'cargo'      => $cargoProbado !== '' ? $cargoProbado : null,
    'p1'         => $valores['claridad_pasos'],
    'p2'         => $valores['facilidad_datos'],
    'p3'         => $valores['facilidad_documentos'],
    'p4'         => $valores['claridad_correos'],
    'p5'         => $valores['tiempo_espera'],
    'p6'         => $valores['claridad_seguimiento'],
    'p7'         => $valores['dificultad_general'],
    'p8'         => $valores['recomendaria'],
    'comentario' => $comentario !== '' ? $comentario : null,
]);

responderOk(['mensaje' => '¡Gracias por tu tiempo!']);
