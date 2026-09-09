<?php
/**
 * Plantilla de correo enviado cuando el Capataz selecciona al postulante
 * en portería (ver terreno/aprobar.php, otorgarAccesoEtapa2()), para que
 * avance a la Etapa 2 (Fase 2). v10.2: nunca decirle "contratación" al
 * postulante aca -- todavia esta postulando, recien le falta completar
 * sus datos y documentos; se confirma o no mas adelante (ver
 * postulacion_no_continua.php / contratacion_exitosa_qr.php).
 * Variables esperadas: $nombreCompleto, $urlFormularioPrivado
 */
return <<<HTML
<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#1f2937">
  <h2 style="color:#111827">¡Buenas noticias, {$nombreCompleto}!</h2>
  <p>Tu postulación avanzó a la siguiente etapa. Para continuar necesitamos
  que completes tus datos personales y previsionales, y subas tus
  documentos.</p>
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
