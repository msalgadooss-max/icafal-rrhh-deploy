<?php
/**
 * Plantilla de correo enviado al postular (Fase 0).
 * Variables esperadas: $nombreCompleto, $codigoSeguimiento, $urlSeguimiento
 */
return <<<HTML
<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#1f2937">
  <h2 style="color:#111827">¡Postulación recibida!</h2>
  <p>Hola <strong>{$nombreCompleto}</strong>, gracias por postular a ICAFAL.</p>
  <p>Tu código de seguimiento es:</p>
  <p style="font-size:28px;font-weight:bold;letter-spacing:4px;background:#f3f4f6;padding:12px 20px;border-radius:8px;text-align:center">
    {$codigoSeguimiento}
  </p>
  <p>Guárdalo junto a tu RUT: los necesitarás para revisar el estado de tu
  postulación en cualquier momento.</p>
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
