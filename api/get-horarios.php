<?php
// api/get-horarios.php
require_once 'conexion.php';
header('Content-Type: application/json');

$fecha = $_GET['fecha'] ?? date('Y-m-d');
$pista = $_GET['pista'] ?? '';

    try {
        $stmt = $pdo->prepare("SELECT hora_inicio, hora_fin, estado FROM reservas WHERE fecha = ? AND pista = ? AND estado != 'cancelada'");
        $stmt->execute([$fecha, $pista]);
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Simplificar el formato para el frontend (ej. '09:00:00' -> '09:00')
    $ocupados = [];
    foreach ($reservas as $r) {
        $inicio = substr($r['hora_inicio'], 0, 5);
        $ocupados[] = [
            'hora_inicio' => $inicio,
            'estado' => $r['estado'] // 'reservada' o 'bloqueada_admin'
        ];
    }
    
    // Obtener precios personalizados
    $stmtPrecios = $pdo->prepare("SELECT hora_inicio, precio_base, precio_luz FROM precios_personalizados WHERE fecha = ? AND pista = ?");
    $stmtPrecios->execute([$fecha, $pista]);
    $preciosRaw = $stmtPrecios->fetchAll(PDO::FETCH_ASSOC);
    
    $precios = [];
    foreach ($preciosRaw as $p) {
        $inicio = substr($p['hora_inicio'], 0, 5);
        $precios[] = [
            'hora_inicio' => $inicio,
            'precio_base' => $p['precio_base'] !== null ? (float)$p['precio_base'] : null,
            'precio_luz' => $p['precio_luz'] !== null ? (float)$p['precio_luz'] : null
        ];
    }
    
    echo json_encode(['status' => 'success', 'ocupados' => $ocupados, 'precios' => $precios]);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error de BBDD al obtener horarios.']);
}
?>
