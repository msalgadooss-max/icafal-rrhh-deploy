<?php
/**
 * Plantilla de correo enviado al autorizar la contratación (Fase 2).
 * Variables esperadas: $nombreCompleto, $urlFormularioPrivado
 */
return <<<HTML
<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#1f2937">
  <h2 style="color:#111827">¡Felicitaciones, {$nombreCompleto}!</h2>
  <p>Tu contratación ha sido autorizada. Para continuar con el proceso
  necesitamos que completes tus datos personales y de contratación.</p>
  <p style="text-align:center;margin:24px 0">
    <a href="{$urlFormularioPrivado}" style="background:#16a34a;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:bold">
      Completar mis datos
    </a>
  </p>
  <p>Este enlace es <strong>personal e intransferible</strong>. Si tienes
  problemas para abrirlo, solicita uno nuevo a tu contacto en la empresa.</p>
  <p style="font-size:12px;color:#6b7280">
    Este correo fue generado automáticamente. Si no reconoces este
    proceso, ignora este mensaje.
  </p>
</div>
HTML;
