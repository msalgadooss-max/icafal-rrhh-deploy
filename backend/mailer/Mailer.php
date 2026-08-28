<?php
/**
 * Envoltorio de envio de correo. Usa PHPMailer via SMTP si esta
 * instalado (composer install), y si no, cae automaticamente a la
 * funcion mail() nativa de PHP para que el sistema siga funcionando en
 * hosting sin Composer.
 */

require_once __DIR__ . '/../config/config.php';

$vendorAutoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

final class Mailer
{
    /**
     * @param array<int, array{cid: string, datos: string, nombre?: string, tipo?: string}> $imagenesEmbebidas
     *        Imágenes embebidas por CID (ej. un QR), referenciadas en el HTML como
     *        <img src="cid:el_cid_elegido">. Solo funcionan con PHPMailer -- se
     *        ignoran silenciosamente si el sistema cae al fallback de mail() nativo.
     * @return bool true si el correo se encolo/envio correctamente.
     */
    public static function enviar(string $destinatario, string $nombreDestinatario, string $asunto, string $htmlBody, array $imagenesEmbebidas = []): bool
    {
        if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
            return self::enviarConPhpMailer($destinatario, $nombreDestinatario, $asunto, $htmlBody, $imagenesEmbebidas);
        }
        return self::enviarConMailNativo($destinatario, $asunto, $htmlBody);
    }

    private static function enviarConPhpMailer(string $destinatario, string $nombre, string $asunto, string $html, array $imagenesEmbebidas = []): bool
    {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            // Sin credenciales (ej. Mailpit en desarrollo local) se omite
            // la autenticación SMTP; en producción, con SMTP_USER definido,
            // se autentica normalmente contra el servidor real.
            $mail->SMTPAuth   = SMTP_USER !== '';
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            if (SMTP_SECURE !== '') {
                $mail->SMTPSecure = SMTP_SECURE;
            }
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';
            // v6.3: algunos hostings gratuitos bloquean el puerto SMTP
            // saliente sin avisar (conexion se queda colgada, no da
            // error). Con el servidor de desarrollo de PHP (una sola
            // peticion a la vez) eso congela TODA la app. Un timeout
            // corto asegura que, si el SMTP no responde, el envio
            // falle rapido en vez de bloquear el resto del sistema.
            $mail->Timeout     = 8;
            $mail->SMTPKeepAlive = false;

            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($destinatario, $nombre);

            foreach ($imagenesEmbebidas as $img) {
                $mail->addStringEmbeddedImage(
                    $img['datos'],
                    $img['cid'],
                    $img['nombre'] ?? 'imagen.png',
                    \PHPMailer\PHPMailer\PHPMailer::ENCODING_BASE64,
                    $img['tipo'] ?? 'image/png'
                );
            }

            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body    = $html;
            $mail->AltBody  = strip_tags($html);

            $mail->send();
            return true;
        } catch (\Throwable $e) {
            error_log('Mailer (PHPMailer) error: ' . $e->getMessage());
            return false;
        }
    }

    private static function enviarConMailNativo(string $destinatario, string $asunto, string $html): bool
    {
        $headers  = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
        $headers .= 'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>' . "\r\n";

        return @mail($destinatario, $asunto, $html, $headers);
    }
}
