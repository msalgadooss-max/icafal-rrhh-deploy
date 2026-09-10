<?php
/**
 * v7 - Correo con el QR que Portería escanea, con su propia cámara o
 * tablet, para confirmar que la persona se presentó y dejarla pasar.
 *
 * v10.9 (pedido explícito del usuario): ahora sale JUSTO cuando el
 * Capataz selecciona a la persona en portería (junto con el correo del
 * link de Etapa 2) -- todavía no llenó sus datos ni entregó documentos,
 * eso lo hace recién adentro, en la sala de espera.
 *
 * v10.14 (bug encontrado en la prueba E2E + pedido explícito del
 * usuario): el texto se acorta y ya no dice "fuiste seleccionado" (da a
 * entender que ya está contratado, cuando recién está empezando) ni
 * "subir documentos" (mejor "entrega tus documentos"). Además se
 * agrega un link de texto de respaldo bajo el QR -- por si la imagen no
 * carga en algún cliente de correo, el botón/link siempre funciona.
 * Variables esperadas: $nombreCompleto, $cargo, $qrImagenUrl, $urlValidacion
 */
return <<<HTML
<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#1f2937">
  <h2 style="color:#1d4e89">Hola {$nombreCompleto}</h2>
  <p>Preséntate en portería con este código QR. Ahí vas a completar tus datos
  personales y entregar tus documentos para el cargo de <strong>{$cargo}</strong>.</p>

  <div style="background:#eaf1fa;border:1px solid #bcd4ee;border-radius:10px;padding:18px;text-align:center;margin:20px 0">
    <img src="{$qrImagenUrl}" alt="Código QR de ingreso" width="180" height="180" style="display:block;margin:0 auto;background:#fff;padding:8px;border-radius:8px">
    <p style="margin:12px 0 0;font-size:13px;color:#1d4e89">Muéstraselo a Portería al llegar.</p>
    <p style="margin:8px 0 0;font-size:12px;"><a href="{$urlValidacion}" style="color:#1d4e89">¿No ves el código? Toca aquí</a></p>
  </div>

  <p style="font-size:12px;color:#6b7280">
    Este correo fue generado automáticamente por el sistema de reclutamiento ICAFAL.
  </p>
</div>
HTML;
