<?php
/**
 * v3 - Estadísticas para el gráfico de anillo del JAO: cuántos
 * terminaron 'Contratado' vs 'Rechazado' en el rango de fechas
 * elegido (el JAO decide si lo mira a diario, semanal o completo --
 * el parámetro `dias` resuelve eso sin necesitar un job programado).
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

iniciarSesionSegura();
requireRol(['Jefe_Administrativo', 'Gerencia']);
exigirMetodo('GET');

$dias = (int)($_GET['dias'] ?? 30);

$pdo = obtenerConexion();

$sql = "SELECT estado, COUNT(*) AS total
          FROM postulaciones
         WHERE estado IN ('Contratado', 'Rechazado')";
if ($dias > 0) {
    $sql .= " AND actualizado_at >= DATE_SUB(NOW(), INTERVAL :dias DAY)";
}
$sql .= " GROUP BY estado";

$stmt = $pdo->prepare($sql);
if ($dias > 0) {
    $stmt->bindValue('dias', $dias, PDO::PARAM_INT);
}
$stmt->execute();

$resultado = ['Contratado' => 0, 'Rechazado' => 0];
foreach ($stmt->fetchAll() as $fila) {
    $resultado[$fila['estado']] = (int)$fila['total'];
}

responderOk(['conteo' => $resultado]);
