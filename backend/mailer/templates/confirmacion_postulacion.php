<?php
/**
 * Plantilla de correo enviado al postular (Fase 0).
 *
 * v10.14 (pedido explícito del usuario, item 1 de la lista post-prueba):
 * se quita el código de seguimiento -- desde que el segundo factor de
 * seguimiento.html pasó a ser RUT + correo (ya no el código), mostrarlo
 * acá solo confundía. Se deja únicamente la instrucción de acceder con
 * RUT y correo.
 * Variables esperadas: $nombreCompleto, $urlSeguimiento
 */
return <<<HTML
<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#1f2937">
  <h2 style="color:#111827">¡Postulación recibida!</h2>
  <p>Hola <strong>{$nombreCompleto}</strong>, gracias por postular a ICAFAL.</p>
  <p>Para ver el estado de tu postulación, accede a este link con tu RUT y tu correo electrónico:</p>
  <p style="text-align:center;margin:24px 0">
    <a href="{$urlSeguimiento}" style="background:#2563eb;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold">
      Ver estado de mi postulación
    </a>
  </p>
  <p style="font-size:12px;color:#6b7280">
    Este correo fue generado automáticamente. Si no realizaste esta
    postulación, puedes ignorarlo.
  </p>
</div>
HTML;
