<?php
/**
 * v5 - Correo enviado al postulante cuando el JAO rechaza un documento
 * puntual (ver admin_general/rechazar_documento.php).
 * Variables esperadas: $nombreCompleto, $etiquetaDoc, $motivo, $urlSubsanacion
 */
return <<<HTML
<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#1f2937">
  <h2 style="color:#111827">Necesitamos que corrijas un documento</h2>
  <p>Hola {$nombreCompleto},</p>
  <p>Revisamos tu postulación y necesitamos que vuelvas a subir este documento:</p>
  <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:14px 16px;margin:16px 0">
    <p style="margin:0 0 6px;font-weight:bold;color:#92400e">{$etiquetaDoc}</p>
    <p style="margin:0;color:#78350f">{$motivo}</p>
  </div>
  <p>El resto de tu proceso sigue avanzando con normalidad — esto no te hace perder lo que ya completaste.</p>
  <p style="text-align:center;margin:24px 0">
    <a href="{$urlSubsanacion}" style="background:#2563eb;color:#fff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:bold;display:inline-block">
      Corregir documento
    </a>
  </p>
  <p style="font-size:12px;color:#6b7280">
    Este correo fue generado automáticamente por el sistema de reclutamiento ICAFAL.
  </p>
</div>
HTML;
