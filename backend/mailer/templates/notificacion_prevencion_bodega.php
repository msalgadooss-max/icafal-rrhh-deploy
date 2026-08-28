<?php
/**
 * v4.1 - Plantilla de correo enviado a Prevencionista y Jefe_Bodega en
 * el mismo momento en que se notifica al JAO (ver
 * functions.php::notificarPrevencionYBodega()): cuando la postulacion
 * junta las dos condiciones del flujo en paralelo (Admin_Contrato
 * autorizo Y el postulante completo su Etapa 2) y pasa a
 * 'Aprobado_admin'. Prevencion solo necesita saber que viene una nueva
 * persona para agendar la induccion; Bodega ademas necesita las tallas
 * para preparar el kit de EPP (calzado y overol).
 * Variables esperadas: $nombreCompleto, $rut, $cargo, $tallaCalzado,
 * $tallaOverol, $esBodega (bool)
 */
$filaTallas = '';
if ($esBodega) {
    $filaTallas = "
    <tr>
      <td style=\"padding:8px 0;color:#6b7280\">N° de calzado</td>
      <td style=\"padding:8px 0;font-weight:bold\">{$tallaCalzado}</td>
    </tr>
    <tr>
      <td style=\"padding:8px 0;color:#6b7280\">Talla de overol</td>
      <td style=\"padding:8px 0;font-weight:bold\">{$tallaOverol}</td>
    </tr>";
}

$mensajeIntro = $esBodega
    ? 'Se autorizó una nueva contratación. Prepara el kit de EPP con estos datos:'
    : 'Se autorizó una nueva contratación. Considérala para agendar la inducción de seguridad:';

return <<<HTML
<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#1f2937">
  <h2 style="color:#111827">Nueva contratación autorizada</h2>
  <p>{$mensajeIntro}</p>
  <table style="width:100%;border-collapse:collapse;margin:16px 0">
    <tr>
      <td style="padding:8px 0;color:#6b7280;width:140px">Nombre</td>
      <td style="padding:8px 0;font-weight:bold">{$nombreCompleto}</td>
    </tr>
    <tr>
      <td style="padding:8px 0;color:#6b7280">RUT</td>
      <td style="padding:8px 0;font-weight:bold">{$rut}</td>
    </tr>
    <tr>
      <td style="padding:8px 0;color:#6b7280">Cargo</td>
      <td style="padding:8px 0;font-weight:bold">{$cargo}</td>
    </tr>
    {$filaTallas}
  </table>
  <p style="font-size:12px;color:#6b7280">
    Este correo fue generado automáticamente por el sistema de reclutamiento ICAFAL.
  </p>
</div>
HTML;
