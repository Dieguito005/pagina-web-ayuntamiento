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

    // NUEVO: Obtener slots dinámicos del perfil activo
    $diaSemana = date('w', strtotime($fecha)); // 0 (domingo) a 6 (sábado)
    $slots = [];
    $perfilNombre = "";
    
    try {
        $stmtSlots = $pdo->prepare("
            SELECT hs.hora_inicio, hs.hora_fin, hs.periodo, hs.tiene_suplemento_luz, hp.nombre as perfil_nombre
            FROM horarios_slots hs
            JOIN horarios_perfiles hp ON hs.perfil_id = hp.id
            WHERE hp.activo = 1 AND hs.pista = ? AND hs.dia_semana = ?
            ORDER BY hs.hora_inicio ASC
        ");
        $stmtSlots->execute([$pista, $diaSemana]);
        $slotsRaw = $stmtSlots->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($slotsRaw as $s) {
            $perfilNombre = $s['perfil_nombre'];
            $slots[] = [
                'hora_inicio' => substr($s['hora_inicio'], 0, 5),
                'hora_fin' => substr($s['hora_fin'], 0, 5),
                'periodo' => $s['periodo'],
                'tiene_suplemento_luz' => (bool)$s['tiene_suplemento_luz']
            ];
        }
    } catch (Exception $e) {
        // Fallback silencioso si las tablas no existen todavía
        $slots = [];
    }
    
    echo json_encode([
        'status' => 'success', 
        'ocupados' => $ocupados, 
        'precios' => $precios,
        'slots' => $slots,
        'perfil_activo' => $perfilNombre
    ]);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error de BBDD al obtener horarios.']);
}
?>
