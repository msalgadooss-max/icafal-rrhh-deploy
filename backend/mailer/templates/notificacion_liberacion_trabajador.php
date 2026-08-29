<?php
/**
 * v6.9 - Plantilla de correo enviado al Capataz que seleccionó al
 * trabajador (y/o a Jefe_Terreno) cuando el proceso queda 100% cerrado
 * (Contratado): "ve a buscarlo" -- pedido de Ricardo en la reunión
 * 28-ago, para cerrar el ciclo completo: quien lo trajo es quien lo
 * lleva a su puesto de trabajo.
 * Variables esperadas: $nombreCompleto, $rut, $cargo
 */
return <<<HTML
<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#1f2937">
  <h2 style="color:#059669">✔ Trabajador listo para ingresar a terreno</h2>
  <p>El proceso de contratación de esta persona ya está 100% cerrado (contrato, inducción y EPP completos):</p>
  <table style="width:100%;border-collapse:collapse;margin:16px 0">
    <tr>
      <td style="padding:8px 0;color:#6b7280;width:120px">Nombre</td>
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
  </table>
  <p><b>Puedes ir a buscarlo</b> y conducirlo a su estación de trabajo.</p>
  <p style="font-size:12px;color:#6b7280">
    Este correo fue generado automáticamente por el sistema de reclutamiento ICAFAL.
  </p>
</div>
HTML;
