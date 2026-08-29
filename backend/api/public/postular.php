<?php
/**
 * Etapa 1 - Postulacion publica (accedida via QR).
 * Genera el codigo_seguimiento y dispara el correo de confirmacion.
 * No requiere sesion.
 *
 * v3 - Campos alineados a "Template Empleados.xls" (columnas VERDES):
 * tipo_documento, numero de documento, apellido, segundo_apellido,
 * nombre, telefono, correo. Ademas se agregan region+comuna (para que
 * Terreno vea de entrada la cercania a la obra -- pedido explicito del
 * usuario) y los campos propios de este sistema que Buk no modela:
 * cargo postulado, CV y consentimiento Ley 19.628.
 *
 * v2 - Banco de Postulantes: si el cargo elegido existe pero no tiene
 * cupos_activos, la postulacion queda en estado 'En_banco' en vez de
 * ser rechazada (ver backend/api/terreno/banco_invitar.php).
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/rut.php';
require_once __DIR__ . '/../../includes/listas_buk.php';
require_once __DIR__ . '/../../mailer/Mailer.php';

exigirMetodo('POST');

$tipoDocumento    = $_POST['tipo_documento'] ?? '';
$numeroDocumento  = limpiarTexto($_POST['numero_documento'] ?? '', 20);
$apellido         = limpiarTexto($_POST['apellido'] ?? '', 100);
$segundoApellido  = limpiarTexto($_POST['segundo_apellido'] ?? '', 100);
$nombre           = limpiarTexto($_POST['nombre'] ?? '', 100);
$telefono         = limpiarTexto($_POST['telefono'] ?? '', 20);
$correo           = filter_var(trim((string)($_POST['correo'] ?? '')), FILTER_VALIDATE_EMAIL);
$region           = limpiarTexto($_POST['region'] ?? '', 100);
$comuna           = limpiarTexto($_POST['comuna'] ?? '', 100);
$cargoId          = (int)($_POST['cargo_id'] ?? 0);
$consentimiento   = (bool)($_POST['consentimiento_ley19628'] ?? false);

$listas = listasBuk();
$regionesComunas = regionesConComunas();

// --- Validaciones server-side (nunca confiar solo en el frontend) -------
$errores = [];
if (!in_array($tipoDocumento, $listas['tipo_documento'], true)) {
    $errores[] = 'Tipo de documento inválido.';
} elseif (!validarDocumento($tipoDocumento, $numeroDocumento)) {
    $errores[] = $tipoDocumento === 'RUT' ? 'RUT inválido.' : 'Debes indicar tu número de documento.';
}
if ($apellido === '')                        $errores[] = 'Apellido es obligatorio.';
if ($segundoApellido === '')                 $errores[] = 'Segundo apellido es obligatorio.';
if ($nombre === '')                          $errores[] = 'Nombre es obligatorio.';
if ($telefono === '')                        $errores[] = 'Teléfono es obligatorio.';
if (!$correo)                                $errores[] = 'Correo inválido.';
if (!isset($regionesComunas[$region]))       $errores[] = 'Región inválida.';
elseif (!in_array($comuna, $regionesComunas[$region], true)) {
    $errores[] = 'La comuna no corresponde a la región seleccionada.';
}
if ($cargoId <= 0)                           $errores[] = 'Debe seleccionar un cargo.';
if (!$consentimiento)                        $errores[] = 'Debe aceptar el tratamiento de datos personales (Ley 19.628).';

if ($errores) {
    responderError(implode(' ', $errores), 422);
}

$numeroDocumento = normalizarDocumento($tipoDocumento, $numeroDocumento);
$nombreCompleto = trim($nombre . ' ' . $apellido . ' ' . $segundoApellido);

$pdo = obtenerConexion();

// El cargo debe existir y estar activo (los cupos ya no son requisito
// para poder postular: sin cupos, la persona queda en el Banco).
$stmtCargo = $pdo->prepare('SELECT id, cupos_activos FROM cargos WHERE id = :id AND activo = 1');
$stmtCargo->execute(['id' => $cargoId]);
$cargo = $stmtCargo->fetch();
if (!$cargo) {
    responderError('El cargo seleccionado no existe.', 404);
}
$tieneCupo = (int)$cargo['cupos_activos'] > 0;
$estadoInicial = $tieneCupo ? 'Pendiente' : 'En_banco';

// Documento unico: una persona no puede tener mas de una postulacion activa.
$stmtDup = $pdo->prepare('SELECT id FROM postulaciones WHERE rut = :rut');
$stmtDup->execute(['rut' => $numeroDocumento]);
if ($stmtDup->fetch()) {
    responderError('Ya existe una postulación registrada con ese documento. Usa el módulo de seguimiento para ver su estado.', 409);
}

// v6.9: "No tengo CV" -- si el postulante no adjunta CV, puede en vez de
// eso contar su ultima experiencia en 3 campos simples (pedido de
// Ricardo, reunion 28-ago, para no bloquear a quien nunca ha trabajado
// o no trae su CV a mano). Se exige uno de los dos caminos, no ninguno.
$experienciaCargo = limpiarTexto($_POST['experiencia_cargo'] ?? '', 150);
$experienciaFecha = limpiarTexto($_POST['experiencia_fecha'] ?? '', 100);
$experienciaDescripcion = limpiarTexto($_POST['experiencia_descripcion'] ?? '', 1000);
$tieneArchivoCv = ($_FILES['cv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

$cvRuta = null;
$experienciaSinCv = null;

if ($tieneArchivoCv) {
    try {
        $cvRuta = guardarArchivoSubido($_FILES['cv'], 'cv', 'tu CV');
    } catch (RuntimeException $e) {
        responderError($e->getMessage(), 422);
    }
} elseif ($experienciaDescripcion !== '') {
    $partes = [];
    if ($experienciaCargo !== '') $partes[] = $experienciaCargo;
    if ($experienciaFecha !== '') $partes[] = $experienciaFecha;
    $experienciaSinCv = (implode(' — ', $partes) !== '' ? implode(' — ', $partes) . "\n" : '') . $experienciaDescripcion;
} else {
    responderError('Debes adjuntar tu CV, o marcar "No tengo CV" y contarnos tu experiencia.', 422);
}

$codigoSeguimiento = generarCodigoSeguimiento($pdo);

$stmt = $pdo->prepare(
    'INSERT INTO postulaciones
        (tipo_documento, rut, nombre_completo, nombre, apellido, segundo_apellido,
         telefono, correo, region, comuna, cargo_id, obra,
         codigo_seguimiento, estado, consentimiento_ley19628, cv_ruta_archivo, experiencia_sin_cv)
     VALUES
        (:tipo_documento, :rut, :nombre_completo, :nombre, :apellido, :segundo_apellido,
         :telefono, :correo, :region, :comuna, :cargo_id, :obra,
         :codigo, :estado, 1, :cv_ruta, :experiencia_sin_cv)'
);
$stmt->execute([
    'tipo_documento'   => $tipoDocumento,
    'rut'              => $numeroDocumento,
    'nombre_completo'  => $nombreCompleto,
    'nombre'           => $nombre,
    'apellido'         => $apellido,
    'segundo_apellido' => $segundoApellido !== '' ? $segundoApellido : null,
    'telefono'         => $telefono,
    'correo'           => $correo,
    'region'           => $region,
    'comuna'           => $comuna,
    'cargo_id'         => $cargoId,
    'obra'             => OBRA_NOMBRE,
    'codigo'           => $codigoSeguimiento,
    'estado'           => $estadoInicial,
    'cv_ruta'          => $cvRuta,
    'experiencia_sin_cv' => $experienciaSinCv,
]);

$postulacionId = (int)$pdo->lastInsertId();
$accionLog = $tieneCupo
    ? 'Postulación creada por el postulante (Etapa 1).'
    : 'Quedó en el Banco de Postulantes (Etapa 1): el cargo elegido no tenía cupos disponibles al momento de postular.';
registrarLog($pdo, $postulacionId, null, $accionLog);

// --- Correo de confirmacion (no bloqueante: si falla, igual respondemos) --
$urlSeguimiento = BASE_URL . '/frontend/public/seguimiento.html?rut=' . urlencode($numeroDocumento);
$html = (function () use ($nombreCompleto, $codigoSeguimiento, $urlSeguimiento) {
    return require __DIR__ . '/../../mailer/templates/confirmacion_postulacion.php';
})();
Mailer::enviar($correo, $nombreCompleto, 'Confirmación de tu postulación - ICAFAL', $html);

responderOk([
    'mensaje' => $tieneCupo
        ? 'Postulación registrada correctamente.'
        : 'En este momento no hay cupos para ese cargo. Guardamos tus datos en nuestro Banco de Postulantes y te contactaremos apenas se abra uno.',
    'en_banco'           => !$tieneCupo,
    'codigo_seguimiento' => $codigoSeguimiento,
], 201);
