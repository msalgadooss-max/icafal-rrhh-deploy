<?php
/**
 * v6.1 - Correo final al postulante cuando el JAO finaliza la
 * contratación (estado -> 'Contratado'). Incluye el QR de acceso que
 * debe presentar en Portería para poder ingresar a la obra.
 *
 * v10.14: la imagen del QR ahora es una URL real (ver
 * notificarContratacionExitosa()), no un data: URI -- Gmail y otros
 * clientes bloqueaban esa imagen. Se agrega un link de texto de
 * respaldo bajo el QR.
 * Variables esperadas: $nombreCompleto, $cargo, $qrImagenUrl, $urlValidacion
 */
return <<<HTML
<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#1f2937">
  <h2 style="color:#15803d">¡Proceso de contratación exitoso!</h2>
  <p>Hola {$nombreCompleto},</p>
  <p>Tu proceso de contratación para el cargo de <strong>{$cargo}</strong> fue completado
  exitosamente. Ahora debes <strong>presentarte en la oficina de RRHH</strong> para firmar
  tu contrato y completar tu incorporación.</p>

  <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:18px;text-align:center;margin:20px 0">
    <p style="margin:0 0 12px;font-weight:bold;color:#15803d">Tu código de acceso a la obra</p>
    <img src="{$qrImagenUrl}" alt="Código QR de acceso" width="180" height="180" style="display:block;margin:0 auto;background:#fff;padding:8px;border-radius:8px">
    <p style="margin:12px 0 0;font-size:13px;color:#166534">Muestra este código en Portería para poder ingresar a la obra.</p>
    <p style="margin:8px 0 0;font-size:12px;"><a href="{$urlValidacion}" style="color:#15803d">¿No ves el código? Toca aquí</a></p>
  </div>

  <p style="font-size:12px;color:#6b7280">
    Este correo fue generado automáticamente por el sistema de reclutamiento ICAFAL.
  </p>
</div>
HTML;
