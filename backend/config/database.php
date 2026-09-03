<?php
/**
 * Conexion PDO unica (singleton) a la base de datos.
 * EMULATE_PREPARES=false obliga a MySQL a preparar de verdad las
 * consultas -> refuerza la proteccion real contra inyeccion SQL.
 */

require_once __DIR__ . '/config.php';

function obtenerConexion(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            // v9.1: refuerzo explícito -- el parámetro "charset=" del DSN
            // debería bastar, pero en el ambiente de Render se detectaron
            // nombres con tildes/"ñ" llegando corruptos a la base. Un
            // SET NAMES explícito deja la codificación de la sesión sin
            // ambigüedad, sin importar cómo el driver haya interpretado
            // el DSN.
            $pdo->exec('SET NAMES ' . DB_CHARSET . ' COLLATE utf8mb4_unicode_ci');
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'    => false,
                'error' => APP_DEBUG
                    ? 'Error de conexion a BD: ' . $e->getMessage()
                    : 'No fue posible conectar a la base de datos.',
            ]);
            exit;
        }
    }

    return $pdo;
}

/**
 * Deja registrado en la variable de sesion de MySQL que usuario esta
 * autenticado, para que los triggers de auditoria (trazabilidad_logs)
 * lo capturen automaticamente en cada UPDATE de estado.
 * Pasar null cuando la accion la origina el propio postulante (sin login).
 */
function fijarUsuarioContextoBD(PDO $pdo, ?int $usuarioId): void
{
    $stmt = $pdo->prepare('SET @current_user_id = :uid');
    $stmt->execute(['uid' => $usuarioId]);
}
