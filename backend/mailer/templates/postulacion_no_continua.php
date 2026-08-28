<?php
/**
 * v6.1 - Correo enviado al postulante cuando Jefe_Terreno o
 * Admin_Contrato rechazan su postulación. El texto se redactó a
 * propósito para:
 *  - Nunca mencionar ni insinuar el motivo real del rechazo (evita
 *    exponer a la empresa a un reclamo por discriminación bajo la
 *    Ley N° 20.609, aunque el motivo real no tenga nada que ver con
 *    una categoría protegida -- simplemente no se declara ninguno).
 *  - Dejar explícito que no es un juicio sobre la persona, solo sobre
 *    el ajuste al proceso puntual.
 *  - Invitar a postular a futuro, como señal adicional de buena fe.
 *  - Repetir el plazo de conservación de datos (Ley 19.628), ya
 *    informado en el consentimiento de la Etapa 1.
 * Variables esperadas: $nombreCompleto, $cargo
 */
return <<<HTML
<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#1f2937">
  <h2 style="color:#111827">Resultado de tu postulación</h2>
  <p>Hola {$nombreCompleto},</p>
  <p>Gracias por tu interés en formar parte de ICAFAL y por el tiempo dedicado a tu
  postulación para el cargo de <strong>{$cargo}</strong>.</p>
  <p>Luego de revisar los antecedentes del proceso, te informamos que
  <strong>no continuarás en esta etapa de selección</strong>. Esta decisión responde
  exclusivamente a los requerimientos específicos de esta búsqueda en particular y
  <strong>no constituye un juicio sobre tu idoneidad, capacidades o desempeño</strong>.</p>
  <p>Te invitamos a postular a futuras oportunidades laborales en ICAFAL cuando se
  ajusten a tu perfil.</p>
  <p style="font-size:12px;color:#6b7280">
    Tus datos se conservarán por el plazo informado al momento de tu postulación y
    luego serán eliminados, conforme a la Ley N° 19.628 sobre Protección de la Vida
    Privada. Este correo fue generado automáticamente.
  </p>
</div>
HTML;
