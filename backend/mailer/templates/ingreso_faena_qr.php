<?php
/**
 * v7 - Correo enviado apenas la postulación llega a 'Aprobado_admin'
 * (Administrador de Contrato autorizó Y el postulante ya completó su
 * Etapa 2). Incluye el QR que Portería escanea, con la cámara de su
 * propio celular, para confirmar que la persona se presentó -- recién
 * ahí el JAO puede empezar a verificar sus documentos.
 * Variables esperadas: $nombreCompleto, $cargo, $qrDataUri
 */
return <<<HTML
<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#1f2937">
  <h2 style="color:#1d4e89">¡Ya vienes avanzando, {$nombreCompleto}!</h2>
  <p>Tu postulación para el cargo de <strong>{$cargo}</strong> fue autorizada y ya completaste
  tus datos. El siguiente paso es presentarte en la obra para que el equipo administrativo
  revise tus documentos.</p>

  <div style="background:#eaf1fa;border:1px solid #bcd4ee;border-radius:10px;padding:18px;text-align:center;margin:20px 0">
    <p style="margin:0 0 12px;font-weight:bold;color:#1d4e89">Tu código de ingreso a faena</p>
    <img src="{$qrDataUri}" alt="Código QR de ingreso" width="180" height="180" style="display:block;margin:0 auto;background:#fff;padding:8px;border-radius:8px">
    <p style="margin:12px 0 0;font-size:13px;color:#1d4e89">Muéstraselo a Portería al llegar -- con eso confirman tu ingreso.</p>
  </div>

  <p style="font-size:12px;color:#6b7280">
    Este correo fue generado automáticamente por el sistema de reclutamiento ICAFAL.
  </p>
</div>
HTML;
