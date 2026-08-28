<?php
/**
 * Prueba rapida y unica de SMTP real (Gmail/Workspace), sin tocar la
 * base de datos ni crear ninguna postulacion. Solo para confirmar que
 * las credenciales configuradas en config.php realmente envian correo.
 * Uso: php backend/tools/test_smtp.php destinatario@dominio.cl
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse por linea de comandos.');
}

require_once __DIR__ . '/../mailer/Mailer.php';

$destinatario = $argv[1] ?? null;
if (!$destinatario) {
    fwrite(STDERR, "Uso: php test_smtp.php destinatario@dominio.cl\n");
    exit(1);
}

$ok = Mailer::enviar(
    $destinatario,
    'Prueba ICAFAL',
    'Prueba de envío real - Sistema RRHH ICAFAL',
    '<p>Este es un correo de prueba del sistema de reclutamiento ICAFAL.</p><p>Si lo recibiste, el envío real vía SMTP quedó configurado correctamente.</p>'
);

echo $ok ? "OK: correo enviado (revisa la bandeja de $destinatario).\n" : "FALLO: revisa el log de errores de PHP para el detalle.\n";
