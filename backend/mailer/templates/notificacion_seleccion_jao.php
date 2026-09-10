<?php
/**
 * v10.13 (pedido explícito del usuario, tras describir de nuevo el
 * proceso completo): correo a cada Jefe_Administrativo apenas el
 * Capataz selecciona a alguien en portería -- un aviso temprano ("viene
 * en camino"), distinto del que llega más adelante cuando esa misma
 * persona ya completó su Etapa 2 y está lista para revisión
 * (ver notificacion_jao.php / notificarAprobacionAJao()).
 * Variables esperadas: $nombreCompleto, $rut, $cargo
 */
return <<<HTML
<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#1f2937">
  <h2 style="color:#111827">El Capataz seleccionó a un postulante</h2>
  <p>Viene en camino a completar su Etapa 2 (datos y documentos):</p>
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
  <p>Te avisaremos de nuevo cuando complete sus datos y esté listo para tu revisión.</p>
  <p style="font-size:12px;color:#6b7280">
    Este correo fue generado automáticamente por el sistema de reclutamiento ICAFAL.
  </p>
</div>
HTML;
