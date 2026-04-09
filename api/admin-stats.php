<?php
// api/admin-stats.php
session_start();
require_once 'conexion.php';
header("Content-Type: application/json; charset=UTF-8");

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "No autorizado"]);
    exit;
}

try {
    date_default_timezone_set('Europe/Madrid');
    $currentMonth = date('Y-m');
    $lastMonth = date('Y-m', strtotime('-1 month'));

    // 1. Ingresos y Cantidad este mes
    $stmtCurrent = $pdo->prepare("SELECT COUNT(*) as total_reservas, SUM(precio_total) as ingresos FROM reservas WHERE DATE_FORMAT(fecha, '%Y-%m') = ? AND estado != 'cancelada'");
    $stmtCurrent->execute([$currentMonth]);
    $currentMData = $stmtCurrent->fetch(PDO::FETCH_ASSOC);

    // 2. Ingresos y Cantidad mes pasado
    $stmtLast = $pdo->prepare("SELECT COUNT(*) as total_reservas, SUM(precio_total) as ingresos FROM reservas WHERE DATE_FORMAT(fecha, '%Y-%m') = ? AND estado != 'cancelada'");
    $stmtLast->execute([$lastMonth]);
    $lastMData = $stmtLast->fetch(PDO::FETCH_ASSOC);

    // 3. Distribución por pistas (Total histórico)
    $stmtPistas = $pdo->prepare("SELECT pista, COUNT(*) as cantidad FROM reservas WHERE estado != 'cancelada' GROUP BY pista ORDER BY cantidad DESC");
    $stmtPistas->execute();
    $distribucionPistas = $stmtPistas->fetchAll(PDO::FETCH_ASSOC);

    // 4. Reservas por día en los últimos 7 días
    $stmtDias = $pdo->prepare("SELECT fecha, COUNT(*) as cantidad FROM reservas WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND estado != 'cancelada' GROUP BY fecha ORDER BY fecha ASC");
    $stmtDias->execute();
    $reservasUltimosDias = $stmtDias->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "data" => [
            "mes_actual" => [
                "reservas" => (int)$currentMData['total_reservas'],
                "ingresos" => (float)$currentMData['ingresos']
            ],
            "mes_pasado" => [
                "reservas" => (int)$lastMData['total_reservas'],
                "ingresos" => (float)$lastMData['ingresos']
            ],
            "pistas" => $distribucionPistas,
            "ultimos_dias" => $reservasUltimosDias
        ]
    ]);

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error de BBDD al obtener estadísticas."]);
}
