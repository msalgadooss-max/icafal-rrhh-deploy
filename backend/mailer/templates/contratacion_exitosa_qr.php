<?php
/**
 * v6.1 - Correo final al postulante cuando el JAO finaliza la
 * contratación (estado -> 'Contratado'). Incluye el QR de acceso
 * embebido (vía CID, ver Mailer::enviar) que debe presentar en
 * Portería para poder ingresar a la obra.
 * Variables esperadas: $nombreCompleto, $cargo, $qrDataUri
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
    <img src="{$qrDataUri}" alt="Código QR de acceso" width="180" height="180" style="display:block;margin:0 auto;background:#fff;padding:8px;border-radius:8px">
    <p style="margin:12px 0 0;font-size:13px;color:#166534">Muestra este código en Portería para poder ingresar a la obra.</p>
  </div>

  <p style="font-size:12px;color:#6b7280">
    Este correo fue generado automáticamente por el sistema de reclutamiento ICAFAL.
  </p>
</div>
HTML;
