<?php
/**
 * v10.14 (pedido explícito del usuario, item 17 de la lista post-prueba):
 * correo al postulante cuando el JAO verifica su identidad (día 1) --
 * antes este paso no le avisaba nada. Le confirma que avanzó y le dice
 * exactamente qué sigue: volver mañana a las 8am para el cierre
 * (contratación, IRL y entrega de EPP).
 * Variables esperadas: $nombreCompleto
 */
return <<<HTML
<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#1f2937">
  <h2 style="color:#111827">Avanzaste en tu proceso</h2>
  <p>Hola {$nombreCompleto}, tus datos y documentos ya fueron revisados.</p>
  <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px;margin:20px 0">
    <p style="margin:0;font-weight:bold;color:#1e3a8a">Debes presentarte mañana a las 08:00</p>
    <p style="margin:8px 0 0;color:#1e3a8a">Para ser contratado, realizar tu inducción de seguridad y recibir tu kit de EPP.</p>
  </div>
  <p style="font-size:12px;color:#6b7280">
    Este correo fue generado automáticamente por el sistema de reclutamiento ICAFAL.
  </p>
</div>
HTML;
