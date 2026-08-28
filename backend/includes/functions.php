<?php
/**
 * Helpers comunes a todos los endpoints de la API.
 */

header('Content-Type: application/json; charset=utf-8');

/** Responde JSON y termina la ejecucion. */
function responderJson(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function responderError(string $mensaje, int $status = 400): never
{
    responderJson(['ok' => false, 'error' => $mensaje], $status);
}

function responderOk(array $data = [], int $status = 200): never
{
    responderJson(array_merge(['ok' => true], $data), $status);
}

/** Lee y decodifica el body JSON de la peticion actual. */
function leerJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        responderError('Cuerpo de la peticion invalido (JSON mal formado).', 400);
    }
    return $data;
}

/** Exige que el metodo HTTP sea el esperado. */
function exigirMetodo(string $metodo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $metodo) {
        responderError('Metodo no permitido.', 405);
    }
}

/** Sanitiza un string simple: recorta espacios y limita largo. */
function limpiarTexto(?string $valor, int $maxLargo = 255): string
{
    $valor = trim((string)$valor);
    return mb_substr($valor, 0, $maxLargo);
}

/**
 * Genera un codigo de seguimiento alfanumerico de 6 caracteres, evitando
 * caracteres ambiguos (0/O, 1/I/L), y garantiza que sea unico en la BD.
 */
function generarCodigoSeguimiento(PDO $pdo): string
{
    $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    do {
        $codigo = '';
        for ($i = 0; $i < CODIGO_SEGUIMIENTO_LARGO; $i++) {
            $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }
        $stmt = $pdo->prepare('SELECT id FROM postulaciones WHERE codigo_seguimiento = :codigo');
        $stmt->execute(['codigo' => $codigo]);
        $existe = $stmt->fetch();
    } while ($existe);

    return $codigo;
}

/** Genera un token privado criptograficamente seguro (64 hex = 32 bytes). */
function generarTokenPrivado(): string
{
    return bin2hex(random_bytes(32));
}

/**
 * Inserta manualmente una entrada de trazabilidad para acciones que NO
 * son un cambio de estado de la columna `estado` (ese caso ya lo cubre
 * el trigger trg_postulaciones_log_estado). Ejemplos: creacion de la
 * postulacion, envio de datos privados, exportacion de carga masiva.
 */
function registrarLog(PDO $pdo, int $postulacionId, ?int $usuarioId, string $accion): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO trazabilidad_logs (postulacion_id, usuario_id, accion, fecha_hora)
         VALUES (:pid, :uid, :accion, NOW())'
    );
    $stmt->execute([
        'pid'    => $postulacionId,
        'uid'    => $usuarioId,
        'accion' => $accion,
    ]);
}

/**
 * v3.1: estado que el Jefe Administrativo exige como requisito previo
 * para "Finalizar Contratación". 'Aprobado_admin' solo se alcanza
 * cuando Admin_Contrato autorizo Y el postulante completo su Etapa 2
 * -- ver intentarAvanzarAAprobadoAdmin().
 */
function estadoPrevioAContratado(): string
{
    if (MODULO_BODEGA_ACTIVO) {
        return 'EPP_listo';
    }
    if (MODULO_PREVENCION_ACTIVO) {
        return 'Induccion_ok';
    }
    return 'Aprobado_admin';
}

/**
 * v2/v3.1: linea de tiempo publica (frontend/seguimiento) ajustada al
 * orden real del pipeline y a los modulos realmente activos. Ya no
 * incluye 'Datos_completados' como paso propio porque en v3.1 esa
 * marca no es un estado (ver nota de arriba) -- el frontend la
 * enciende por separado usando el campo `etapa2_completada` que
 * devuelve seguimiento.php.
 */
function ordenEstadosActivos(): array
{
    $orden = ['Pendiente', 'Pre_aprobado_terreno', 'Aprobado_admin'];
    if (MODULO_PREVENCION_ACTIVO) {
        $orden[] = 'Induccion_ok';
    }
    if (MODULO_BODEGA_ACTIVO) {
        $orden[] = 'EPP_listo';
    }
    $orden[] = 'Contratado';
    return $orden;
}

/**
 * v6.5: se llama cuando el postulante termina su Etapa 2. Verifica que
 * Admin_Contrato ya haya autorizado (admin_autorizado_at) -- con el
 * flujo SECUENCIAL actual esto siempre es cierto, porque el postulante
 * no puede ni empezar la Etapa 2 sin que Admin_Contrato autorice
 * primero (ver admin_contrato/autorizar.php). Se deja esta doble
 * verificacion de todos modos como defensa: si algun dia se necesita
 * volver al flujo en paralelo, esta funcion ya sabe manejarlo sin
 * cambios.
 */
function intentarAvanzarAAprobadoAdmin(PDO $pdo, int $postulacionId): void
{
    $stmt = $pdo->prepare(
        'SELECT p.estado, p.admin_autorizado_at, p.nombre_completo, p.rut, c.nombre_cargo,
                d.talla_calzado, d.talla_overol,
                (SELECT COUNT(*) FROM datos_contratacion d2 WHERE d2.postulacion_id = p.id) AS tiene_datos
           FROM postulaciones p
           JOIN cargos c ON c.id = p.cargo_id
           LEFT JOIN datos_contratacion d ON d.postulacion_id = p.id
          WHERE p.id = :id
          FOR UPDATE'
    );
    $stmt->execute(['id' => $postulacionId]);
    $postulacion = $stmt->fetch();

    if (!$postulacion || $postulacion['estado'] !== 'Pre_aprobado_terreno') {
        return; // ya avanzo (u otro caso) -- nada que hacer.
    }
    $adminYaAutorizo = $postulacion['admin_autorizado_at'] !== null;
    $postulanteYaCompleto = (int)$postulacion['tiene_datos'] > 0;

    if (!$adminYaAutorizo || !$postulanteYaCompleto) {
        return; // falta uno de los dos caminos -- se espera sin bloquear al otro.
    }

    $stmtUpdate = $pdo->prepare('UPDATE postulaciones SET estado = "Aprobado_admin" WHERE id = :id AND estado = "Pre_aprobado_terreno"');
    $stmtUpdate->execute(['id' => $postulacionId]);

    notificarAprobacionAJao($pdo, $postulacion);
    // v4.1: en el mismo momento (ya con datos_contratacion garantizado
    // -- por eso las tallas de EPP ya estan disponibles), se avisa
    // tambien a Prevencion y Bodega.
    notificarPrevencionYBodega($pdo, $postulacion);
}

/**
 * v6.5: punto unico donde una postulacion recibe acceso a la Fase 2
 * (datos personales/bancarios + documentos). Genera el token, deja el
 * estado en 'Pre_aprobado_terreno' (ya lo estaba) y envia el correo con
 * el link privado. La llama admin_contrato/autorizar.php -- el flujo
 * volvio a ser SECUENCIAL: Terreno pre-aprueba primero (sin dar acceso
 * todavia), Admin_Contrato autoriza despues, y recien ahi el postulante
 * se entera que puede continuar. (Antes, en v3.1, esto lo llamaban
 * terreno/aprobar.php y terreno/banco_invitar.php para correr en
 * paralelo con la autorizacion del administrador; ver git history si
 * hace falta volver a ese modelo.)
 */
function otorgarAccesoEtapa2(PDO $pdo, int $postulacionId, int $usuarioId): void
{
    $stmt = $pdo->prepare('SELECT nombre_completo, correo FROM postulaciones WHERE id = :id');
    $stmt->execute(['id' => $postulacionId]);
    $postulacion = $stmt->fetch();
    if (!$postulacion) {
        throw new RuntimeException('Postulación no encontrada.|404');
    }

    $token = generarTokenPrivado();
    $expira = (new DateTime())->modify('+' . TOKEN_PRIVADO_HORAS_VALIDEZ . ' hours')->format('Y-m-d H:i:s');

    fijarUsuarioContextoBD($pdo, $usuarioId);
    $stmtUpdate = $pdo->prepare(
        'UPDATE postulaciones
            SET estado = "Pre_aprobado_terreno", token_privado = :token, token_expira_at = :expira
          WHERE id = :id'
    );
    $stmtUpdate->execute(['token' => $token, 'expira' => $expira, 'id' => $postulacionId]);

    require_once __DIR__ . '/../mailer/Mailer.php';
    $urlFormularioPrivado = BASE_URL . '/frontend/public/completar.html?token=' . $token;
    $nombreCompleto = $postulacion['nombre_completo'];
    $html = (function () use ($nombreCompleto, $urlFormularioPrivado) {
        return require __DIR__ . '/../mailer/templates/link_privado.php';
    })();
    Mailer::enviar($postulacion['correo'], $nombreCompleto, 'Completa tus datos de contratación - ICAFAL', $html);
}

/**
 * v3: al autorizar la contratacion (Admin_Contrato), notifica a todos
 * los Jefe_Administrativo con nombre, RUT y cargo de la persona recien
 * aprobada -- para que sepan que ya viene en camino sin tener que
 * revisar el dashboard a cada rato.
 */
function notificarAprobacionAJao(PDO $pdo, array $postulacion): void
{
    require_once __DIR__ . '/../mailer/Mailer.php';
    $stmt = $pdo->query("SELECT nombre, correo FROM usuarios WHERE rol = 'Jefe_Administrativo' AND activo = 1");
    $destinatarios = $stmt->fetchAll();
    if (!$destinatarios) {
        return;
    }

    $nombreCompleto = $postulacion['nombre_completo'];
    $rut = $postulacion['rut'];
    $cargo = $postulacion['nombre_cargo'];
    $html = (function () use ($nombreCompleto, $rut, $cargo) {
        return require __DIR__ . '/../mailer/templates/notificacion_jao.php';
    })();

    foreach ($destinatarios as $jao) {
        Mailer::enviar($jao['correo'], $jao['nombre'], 'Nueva contratación autorizada - ICAFAL', $html);
    }
}

/**
 * v4: cuenta cuantas aprobaciones (Pendiente/En_banco -> Pre_aprobado_terreno)
 * ha hecho un Jefe_Terreno especifico durante el dia calendario de HOY,
 * usando trazabilidad_logs (que ya registra usuario_id y fecha_hora para
 * cada cambio de estado via el trigger o via registrarLog en el caso del
 * banco). Se llama ANTES de otorgarAccesoEtapa2() en aprobar.php y
 * banco_invitar.php para exigir el tope de LIMITE_APROBACIONES_DIARIAS_TERRENO.
 */
function contarAprobacionesHoy(PDO $pdo, int $usuarioId): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM trazabilidad_logs
          WHERE usuario_id = :uid
            AND DATE(fecha_hora) = CURDATE()
            AND accion IN (
                'Cambio de estado: Pendiente -> Pre_aprobado_terreno',
                'Cambio de estado: En_banco -> Pre_aprobado_terreno'
            )"
    );
    $stmt->execute(['uid' => $usuarioId]);
    return (int)$stmt->fetchColumn();
}

/**
 * v4: corta la ejecucion con un error claro si el Jefe_Terreno ya llego
 * al tope diario de aprobaciones. Debe llamarse ANTES de tocar la BD
 * (antes de otorgarAccesoEtapa2()) para no dejar la postulacion a medio
 * camino si el limite ya se alcanzo.
 */
function exigirCupoDiarioAprobaciones(PDO $pdo, int $usuarioId): void
{
    $usadas = contarAprobacionesHoy($pdo, $usuarioId);
    if ($usadas >= LIMITE_APROBACIONES_DIARIAS_TERRENO) {
        responderError(
            'Alcanzaste el límite de ' . LIMITE_APROBACIONES_DIARIAS_TERRENO .
            ' aprobaciones para hoy (' . $usadas . '/' . LIMITE_APROBACIONES_DIARIAS_TERRENO . '). ' .
            'Podrás aprobar nuevamente mañana.',
            429
        );
    }
}

/**
 * v4.1: al mismo tiempo que se notifica al JAO (ver
 * notificarAprobacionAJao), se avisa a Prevencionista y Jefe_Bodega de
 * que hay una nueva contratación en camino. Prevención solo necesita
 * nombre/RUT/cargo para agendar la inducción; Bodega además recibe las
 * tallas de calzado y overol para preparar el kit de EPP. Se envía
 * aunque los módulos de Prevención/Bodega estén pausados (MODULO_*),
 * porque avisarles con anticipación es útil igual aunque su paso
 * formal en el pipeline no esté activo todavía.
 */
function notificarPrevencionYBodega(PDO $pdo, array $postulacion): void
{
    require_once __DIR__ . '/../mailer/Mailer.php';
    $stmt = $pdo->query("SELECT nombre, correo, rol FROM usuarios WHERE rol IN ('Prevencionista', 'Jefe_Bodega') AND activo = 1");
    $destinatarios = $stmt->fetchAll();
    if (!$destinatarios) {
        return;
    }

    $nombreCompleto = $postulacion['nombre_completo'];
    $rut = $postulacion['rut'];
    $cargo = $postulacion['nombre_cargo'];
    $tallaCalzado = $postulacion['talla_calzado'] ?? '—';
    $tallaOverol = $postulacion['talla_overol'] ?? '—';

    foreach ($destinatarios as $destinatario) {
        $esBodega = $destinatario['rol'] === 'Jefe_Bodega';
        $html = (function () use ($nombreCompleto, $rut, $cargo, $tallaCalzado, $tallaOverol, $esBodega) {
            return require __DIR__ . '/../mailer/templates/notificacion_prevencion_bodega.php';
        })();
        Mailer::enviar($destinatario['correo'], $destinatario['nombre'], 'Nueva contratación autorizada - ICAFAL', $html);
    }
}

/**
 * v5: nombres cortos y legibles de cada tipo de documento de la Etapa 2,
 * usados tanto en el correo de rechazo como en la pantalla de
 * subsanación pública. Centralizado aquí para no repetir el mapeo en
 * cada endpoint que lo necesita.
 */
function etiquetasDocumentos(): array
{
    return [
        'cedula_identidad'          => 'Cédula de Identidad (frente)',
        'cedula_identidad_reverso'  => 'Cédula de Identidad (reverso)',
        'certificado_afp'           => 'Certificado de AFP',
        'certificado_salud'         => 'Certificado de Fonasa/Isapre',
        'ultimo_finiquito'          => 'Último Finiquito',
        'certificado_residencia'    => 'Certificado de Residencia',
    ];
}

/**
 * v5: traduce una entrada cruda de trazabilidad_logs.accion a una frase
 * en lenguaje natural para mostrar en una bitácora de actividad legible
 * (ver bitacora.php). La mayoría de las acciones ya se guardan como
 * frases naturales (se devuelven tal cual); solo "Cambio de estado: X
 * -> Y", que es la única forma técnica/ENUM, se reescribe.
 */
function traducirAccionLog(string $accion): string
{
    if (preg_match('/^Cambio de estado: (\w+) -> (\w+)$/', $accion, $m)) {
        return match ($m[2]) {
            'Pre_aprobado_terreno' => 'Fue pre-aprobado por el Jefe de Terreno.',
            'Aprobado_admin' => 'Pasó a revisión del Jefe Administrativo (ya con datos completos y autorización del Administrador de Contrato).',
            'Induccion_ok' => 'Realizó la inducción de seguridad.',
            'EPP_listo' => 'Su kit de EPP quedó listo.',
            'Contratado' => '✔ Fue contratado.',
            'Rechazado' => 'La postulación fue rechazada.',
            'En_banco' => 'Quedó en el Banco de Postulantes.',
            default => "Cambió de estado a \"{$m[2]}\".",
        };
    }
    return $accion;
}

/**
 * v6.1 - Correo final al postulante cuando el JAO finaliza la
 * contratación: incluye el QR de acceso (mismo destino que usa
 * porteria/validar.php) embebido en el correo, para que lo presente en
 * Portería sin depender de que vuelva a entrar a su seguimiento.
 */
function notificarContratacionExitosa(PDO $pdo, array $postulacion): void
{
    require_once __DIR__ . '/../mailer/Mailer.php';
    $vendorAutoload = __DIR__ . '/../../vendor/autoload.php';
    if (!file_exists($vendorAutoload)) {
        return; // sin Composer no hay libreria de QR -- no se envia este correo
    }
    require_once $vendorAutoload;

    $urlValidacion = BASE_URL . '/frontend/public/porteria_resultado.html'
        . '?rut=' . urlencode($postulacion['rut'])
        . '&codigo=' . urlencode($postulacion['codigo_seguimiento']);

    $opciones = new \chillerlan\QRCode\QROptions([
        'outputInterface' => \chillerlan\QRCode\Output\QRGdImagePNG::class,
        'outputBase64'    => false,
        'scale'           => 6,
    ]);
    $qrPng = (new \chillerlan\QRCode\QRCode($opciones))->render($urlValidacion);

    // v6.3: se embebe como data URI en vez de CID -- funciona igual con
    // PHPMailer/SMTP que con la API HTTPS de Brevo (que no soporta
    // imagenes referenciadas por CID en un correo transaccional simple).
    $qrDataUri = 'data:image/png;base64,' . base64_encode($qrPng);

    $nombreCompleto = $postulacion['nombre_completo'];
    $cargo = $postulacion['nombre_cargo'];
    $html = (function () use ($nombreCompleto, $cargo, $qrDataUri) {
        return require __DIR__ . '/../mailer/templates/contratacion_exitosa_qr.php';
    })();

    Mailer::enviar($postulacion['correo'], $nombreCompleto, 'Proceso de contratación exitoso - ICAFAL', $html);
}

/**
 * v6.1 - Correo al postulante cuando Jefe_Terreno o Admin_Contrato
 * rechazan su postulación. Ver mailer/templates/postulacion_no_continua.php
 * para el detalle de por qué el texto está redactado como está.
 */
function notificarPostulacionNoContinua(array $postulacion): void
{
    require_once __DIR__ . '/../mailer/Mailer.php';
    $nombreCompleto = $postulacion['nombre_completo'];
    $cargo = $postulacion['nombre_cargo'];
    $html = (function () use ($nombreCompleto, $cargo) {
        return require __DIR__ . '/../mailer/templates/postulacion_no_continua.php';
    })();
    Mailer::enviar($postulacion['correo'], $nombreCompleto, 'Resultado de tu postulación - ICAFAL', $html);
}

/**
 * v6.1 - Carpeta local con los documentos del trabajador, para el
 * equipo de RRHH que trabaja con carpetas en el propio servidor/PC
 * ademas de la app. Vive fuera del webroot real (junto a uploads/),
 * protegida por su propio .htaccess -- es un espejo de conveniencia,
 * NUNCA la fuente de verdad (esa sigue siendo backend/uploads/ + la BD).
 *
 * Nombre de carpeta: <codigo_ficha o codigo_seguimiento>_<apellido>_<segundo_apellido>_<nombre>
 * (código de ficha si el JAO ya lo asignó; si no, se usa el código de
 * seguimiento como identificador provisorio y se renombra más tarde --
 * ver renombrarCarpetaConCodigoFicha()).
 */
function nombreCarpetaPostulante(array $p, string $prefijo): string
{
    $limpiar = function (string $s): string {
        $s = preg_replace('/[^A-Za-z0-9]+/u', '_', trim($s));
        return trim($s, '_');
    };
    $partes = array_filter([$limpiar($prefijo), $limpiar($p['apellido'] ?? ''), $limpiar($p['segundo_apellido'] ?? ''), $limpiar($p['nombre'] ?? '')]);
    return implode('_', $partes) ?: ('postulacion_' . $p['id']);
}

function carpetaBasePostulantes(): string
{
    $carpeta = __DIR__ . '/../carpetas_postulantes';
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0750, true);
        file_put_contents($carpeta . '/.htaccess', "Require all denied\n");
    }
    return $carpeta;
}

/**
 * v6.1 - Se llama justo después de guardar los documentos de Etapa 2:
 * crea "<carpeta_postulante>/Documentos Personales/" y copia ahí cada
 * documento recién subido (copia, no mueve -- el original en
 * backend/uploads/ sigue siendo la fuente de verdad para la app).
 */
function generarCarpetaDocumentosPersonales(PDO $pdo, int $postulacionId): void
{
    $stmt = $pdo->prepare('SELECT id, nombre, apellido, segundo_apellido FROM postulaciones WHERE id = :id');
    $stmt->execute(['id' => $postulacionId]);
    $p = $stmt->fetch();
    if (!$p) {
        return;
    }

    $stmtCodigo = $pdo->prepare('SELECT codigo_seguimiento FROM postulaciones WHERE id = :id');
    $stmtCodigo->execute(['id' => $postulacionId]);
    $codigoSeguimiento = $stmtCodigo->fetchColumn();

    $stmtJao = $pdo->prepare('SELECT codigo_ficha FROM datos_jao WHERE postulacion_id = :id');
    $stmtJao->execute(['id' => $postulacionId]);
    $codigoFicha = $stmtJao->fetchColumn();

    $prefijo = $codigoFicha ?: $codigoSeguimiento;
    $nombreCarpeta = nombreCarpetaPostulante($p, $prefijo);
    $rutaDocsPersonales = carpetaBasePostulantes() . '/' . $nombreCarpeta . '/Documentos Personales';
    if (!is_dir($rutaDocsPersonales)) {
        mkdir($rutaDocsPersonales, 0750, true);
    }

    $etiquetas = etiquetasDocumentos();
    $stmtDocs = $pdo->prepare('SELECT tipo, ruta_archivo FROM postulacion_documentos WHERE postulacion_id = :id');
    $stmtDocs->execute(['id' => $postulacionId]);
    foreach ($stmtDocs->fetchAll() as $doc) {
        $origen = __DIR__ . '/../uploads/' . $doc['ruta_archivo'];
        if (!is_file($origen)) {
            continue;
        }
        $extension = pathinfo($origen, PATHINFO_EXTENSION);
        $etiquetaArchivo = str_replace(['/', '\\'], '-', $etiquetas[$doc['tipo']] ?? $doc['tipo']);
        $nombreDestino = $etiquetaArchivo . '.' . $extension;
        copy($origen, $rutaDocsPersonales . '/' . $nombreDestino);
    }
}

/**
 * v6.1 - Se llama cuando el JAO guarda/actualiza el código de ficha
 * (guardar_datos_jao.php): si la carpeta del postulante todavía tiene
 * el nombre provisorio (con el código de seguimiento), la renombra para
 * usar el código de ficha real.
 */
function renombrarCarpetaConCodigoFicha(PDO $pdo, int $postulacionId, string $codigoFichaNuevo): void
{
    $stmt = $pdo->prepare('SELECT id, nombre, apellido, segundo_apellido, codigo_seguimiento FROM postulaciones WHERE id = :id');
    $stmt->execute(['id' => $postulacionId]);
    $p = $stmt->fetch();
    if (!$p) {
        return;
    }

    $base = carpetaBasePostulantes();
    $nombreViejoProvisorio = nombreCarpetaPostulante($p, $p['codigo_seguimiento']);
    $nombreNuevo = nombreCarpetaPostulante($p, $codigoFichaNuevo);

    if ($nombreViejoProvisorio === $nombreNuevo) {
        return; // nada que renombrar
    }
    $rutaVieja = $base . '/' . $nombreViejoProvisorio;
    $rutaNueva = $base . '/' . $nombreNuevo;
    if (is_dir($rutaVieja) && !is_dir($rutaNueva)) {
        rename($rutaVieja, $rutaNueva);
    }
}

/**
 * v2: fecha hasta la que se conservan los datos de alguien que quedo
 * "En_banco" (postulo con interes en un cargo sin cupos disponibles),
 * en linea con el deber de informar plazos de conservacion de la
 * Ley 19.628. Se calcula, no se guarda, para que cambiar
 * BANCO_RETENCION_MESES no requiera tocar filas existentes.
 */
function fechaRetencionBanco(string $creadoAt): string
{
    $fecha = new DateTime($creadoAt);
    $fecha->modify('+' . BANCO_RETENCION_MESES . ' months');
    return $fecha->format('Y-m-d');
}

/**
 * v2: corta la ejecucion si el modulo (Prevencion/Bodega) esta
 * pausado. Se llama justo despues de requireRol(), antes de tocar
 * cualquier tabla -- el rol y sus endpoints siguen existiendo, solo
 * quedan sin uso mientras el flag este en false.
 */
function exigirModuloActivo(bool $activo, string $nombreModulo): void
{
    if (!$activo) {
        responderError("El módulo de $nombreModulo no está disponible en esta versión de la demo.", 503);
    }
}

/** true si el cierre de remuneraciones (Buk u otro) esta activo hoy. */
function cierreRemuneracionesActivo(PDO $pdo): bool
{
    $stmt = $pdo->query('SELECT activo FROM cierre_remuneraciones WHERE id = 1');
    return (bool)$stmt->fetchColumn();
}

/**
 * v2: valida y guarda un archivo subido (ej. el CV en Fase 0) fuera
 * del webroot, con nombre generado aleatoriamente (nunca el nombre
 * original) para evitar colisiones y ataques de path traversal.
 * Devuelve la ruta relativa a guardar en la BD, o lanza RuntimeException
 * con un mensaje apto para mostrar al usuario si algo no es valido.
 */
function guardarArchivoSubido(array $archivo, string $subcarpeta, string $etiquetaCampo = 'el archivo'): string
{
    $permitidos = [
        'application/pdf' => 'pdf',
        'image/jpeg'       => 'jpg',
        'image/png'        => 'png',
    ];
    $maxBytes = 8 * 1024 * 1024; // 8 MB, suficiente para una foto de celular

    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException("Debes adjuntar $etiquetaCampo (PDF, JPG o PNG).");
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("No fue posible recibir $etiquetaCampo. Intenta nuevamente.");
    }
    if ($archivo['size'] > $maxBytes) {
        throw new RuntimeException("$etiquetaCampo supera el tamaño máximo permitido (8 MB).");
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($archivo['tmp_name']);
    if (!isset($permitidos[$mime])) {
        throw new RuntimeException('Formato no permitido. Sube tu CV en PDF, JPG o PNG.');
    }

    $carpetaBase = __DIR__ . '/../uploads/' . $subcarpeta;
    if (!is_dir($carpetaBase)) {
        mkdir($carpetaBase, 0750, true);
    }

    $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $permitidos[$mime];
    $rutaCompleta = $carpetaBase . '/' . $nombreArchivo;

    if (!move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
        throw new RuntimeException('No fue posible guardar el archivo. Intenta nuevamente.');
    }

    return $subcarpeta . '/' . $nombreArchivo;
}
