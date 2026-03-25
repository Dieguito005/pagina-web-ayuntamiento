<?php
// api/desbloquear-hora.php
session_start();
require_once 'conexion.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    if (!$data) $data = $_POST;

    $pista = $data['pista'] ?? '';
    $fecha = $data['fecha'] ?? '';
    $hora_inicio = $data['hora_inicio'] ?? '';

    if (empty($pista) || empty($fecha) || empty($hora_inicio)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Faltan datos requeridos (pista, fecha, hora)']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM reservas WHERE pista = ? AND fecha = ? AND hora_inicio = ? AND estado = 'bloqueada_admin'");
        $stmt->execute([$pista, $fecha, $hora_inicio]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Hora desbloqueada correctamente.']);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'No se encontró un bloqueo administrativo en ese horario.']);
        }
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error de BBDD al eliminar el bloqueo.']);
    }
}
?>
