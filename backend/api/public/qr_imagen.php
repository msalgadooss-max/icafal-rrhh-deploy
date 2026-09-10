<?php
/**
 * v10.14 (bug encontrado en la prueba E2E completa, pedido explícito
 * del usuario: "no está saliendo el QR"): genera la imagen PNG del QR
 * bajo una URL real, en vez de incrustarla como data: URI directamente
 * en el cuerpo del correo (como se hacía antes en notificarIngresoFaena()
 * y notificarContratacionExitosa()).
 *
 * Gmail (y varios otros clientes de correo) bloquea o quita las
 * imágenes con src="data:..." de los correos HTML recibidos, por
 * seguridad -- por eso el QR simplemente no aparecía. Una URL de
 * imagen normal como esta, en cambio, se muestra sin problema en
 * cualquier cliente.
 *
 * Público y sin sesión (igual que el resto de las consultas de
 * Portería): solo genera una imagen a partir de una URL que ya viaja
 * de todas formas en el mismo correo -- no expone ningún dato nuevo.
 * Para que este endpoint no sirva como generador de QR de cualquier
 * texto arbitrario, solo acepta URLs que empiecen con nuestro propio
 * BASE_URL.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

exigirMetodo('GET');

$vendorAutoload = __DIR__ . '/../../../vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    responderError('No disponible.', 500);
}
require_once $vendorAutoload;

$url = (string)($_GET['u'] ?? '');
if ($url === '' || strpos($url, BASE_URL) !== 0) {
    responderError('URL inválida.', 422);
}

$opciones = new \chillerlan\QRCode\QROptions([
    'outputInterface' => \chillerlan\QRCode\Output\QRGdImagePNG::class,
    'outputBase64'    => false,
    'scale'           => 6,
]);
$png = (new \chillerlan\QRCode\QRCode($opciones))->render($url);

header('Content-Type: image/png');
header('Cache-Control: no-store');
echo $png;
