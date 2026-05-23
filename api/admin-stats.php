<?php
// api/admin-stats.php — Estadisticas del panel admin (GET solo).
require_once __DIR__ . '/../includes/SecurityHandler.php';
session_start();
require_once 'conexion.php';
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

SecurityHandler::requireAdmin();

try {
    date_default_timezone_set('Europe/Madrid');
    $currentMonth = date('Y-m');
    $lastMonth = date('Y-m', strtotime('-1 month'));

    $stmtCurrent = $pdo->prepare("SELECT COUNT(*) AS total_reservas, SUM(precio_total) AS ingresos FROM reservas WHERE DATE_FORMAT(fecha, '%Y-%m') = ? AND estado != 'cancelada'");
    $stmtCurrent->execute([$currentMonth]);
    $currentMData = $stmtCurrent->fetch(PDO::FETCH_ASSOC);

    $stmtLast = $pdo->prepare("SELECT COUNT(*) AS total_reservas, SUM(precio_total) AS ingresos FROM reservas WHERE DATE_FORMAT(fecha, '%Y-%m') = ? AND estado != 'cancelada'");
    $stmtLast->execute([$lastMonth]);
    $lastMData = $stmtLast->fetch(PDO::FETCH_ASSOC);

    $stmtPistas = $pdo->prepare("SELECT pista, COUNT(*) AS cantidad FROM reservas WHERE estado != 'cancelada' GROUP BY pista ORDER BY cantidad DESC");
    $stmtPistas->execute();
    $distribucionPistas = $stmtPistas->fetchAll(PDO::FETCH_ASSOC);

    $stmtDias = $pdo->prepare("SELECT fecha, COUNT(*) AS cantidad FROM reservas WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND estado != 'cancelada' GROUP BY fecha ORDER BY fecha ASC");
    $stmtDias->execute();
    $reservasUltimosDias = $stmtDias->fetchAll(PDO::FETCH_ASSOC);

    SecurityHandler::jsonOk(['data' => [
        'mes_actual' => [
            'reservas' => (int) $currentMData['total_reservas'],
            'ingresos' => (float) $currentMData['ingresos'],
        ],
        'mes_pasado' => [
            'reservas' => (int) $lastMData['total_reservas'],
            'ingresos' => (float) $lastMData['ingresos'],
        ],
        'pistas' => $distribucionPistas,
        'ultimos_dias' => $reservasUltimosDias,
    ]]);
} catch (\PDOException $e) {
    error_log('admin_stats: ' . $e->getMessage());
    SecurityHandler::jsonError(500, 'Error al obtener estadisticas.');
}
