<?php
/**
 * Copiar este archivo a "config.php" (mismo directorio) y completar los
 * datos reales del hosting. "config.php" NO debe subirse a un repositorio
 * publico ni quedar accesible por HTTP (ver backend/.htaccess).
 */

// --- Base de datos -----------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'icafal_rrhh');
define('DB_USER', 'usuario_bd');
define('DB_PASS', 'clave_bd');
define('DB_CHARSET', 'utf8mb4');

// --- URL base publica del sistema (sin slash final) ---------------------
define('BASE_URL', 'https://www.tuempresa.cl/rrhh');

// --- Correo saliente (SMTP) ---------------------------------------------
define('SMTP_HOST', 'smtp.tuempresa.cl');
define('SMTP_PORT', 587);
define('SMTP_USER', 'notificaciones@tuempresa.cl');
define('SMTP_PASS', 'clave_smtp');
define('SMTP_SECURE', 'tls'); // 'tls' o 'ssl'
define('SMTP_FROM_EMAIL', 'notificaciones@tuempresa.cl');
define('SMTP_FROM_NAME', 'RRHH ICAFAL');

// --- Reglas de negocio ---------------------------------------------------
define('TOKEN_PRIVADO_HORAS_VALIDEZ', 72); // horas de validez del link privado
define('TOKEN_SUBSANACION_HORAS_VALIDEZ', 72); // horas de validez del link para corregir un documento rechazado
define('CODIGO_SEGUIMIENTO_LARGO', 6);
define('BANCO_RETENCION_MESES', 6); // cuanto se conservan los datos de quien queda "En_banco"

// --- Obra activa (v4) -------------------------------------------------------
// Nombre de la obra para la que corre esta tanda de postulacion; se muestra
// en el formulario publico (Etapa 1) para que el postulante sepa a que
// proyecto especifico postula. Si en el futuro se manejan varias obras a la
// vez, esto pasa a ser una columna editable por Jefe_Terreno en vez de una
// constante fija.
define('OBRA_NOMBRE', 'Obra H57 Padre Hurtado IV');

// --- Limite diario de aprobaciones de Jefe_Terreno (v4) ---------------------
// Maximo de aprobaciones (Pendiente/En_banco -> Pre_aprobado_terreno) que un
// mismo usuario Jefe_Terreno puede realizar por dia calendario. Se cuenta via
// trazabilidad_logs -- ver includes/functions.php::contarAprobacionesHoy().
define('LIMITE_APROBACIONES_DIARIAS_TERRENO', 25);

// --- Modulos activos (v2) --------------------------------------------------
// Mientras esten en false: los dashboards de Prevencion y Bodega responden
// "modulo no disponible" y el Jefe Administrativo puede finalizar la
// contratacion directamente después de 'Datos_completados', sin exigir
// pasar por esos dos pasos. Poner en true reactiva el flujo completo tal
// como se definio originalmente (Fase 4 y Fase 5), sin perder datos: el rol,
// las tablas y los endpoints siguen existiendo, solo estan pausados.
define('MODULO_PREVENCION_ACTIVO', false);
define('MODULO_BODEGA_ACTIVO', false);

// --- Sesion ---------------------------------------------------------------
define('SESSION_NAME', 'icafal_rrhh_sesion');

// --- Modo debug (poner en false en produccion) -----------------------------
define('APP_DEBUG', false);
