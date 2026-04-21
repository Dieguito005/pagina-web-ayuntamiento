<?php
// api/bloquear-hora.php
session_start();
require_once 'conexion.php';
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../includes/SecurityHandler.php';
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !SecurityHandler::validateCSRFToken($csrfToken)) {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Token de seguridad inválido.']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    if (!$data) $data = $_POST;

    $pista = $data['pista'] ?? '';
    $fecha = $data['fecha'] ?? '';
    $hora_inicio = $data['hora_inicio'] ?? '';
    $hora_fin = $data['hora_fin'] ?? '';

    if (empty($pista) || empty($fecha) || empty($hora_inicio) || empty($hora_fin)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Faltan datos requeridos (pista, fecha, horas)']);
        exit;
    }

    try {
        // Verificar disponibilidad
        $checkStmt = $pdo->prepare("SELECT id FROM reservas WHERE pista = ? AND fecha = ? AND hora_inicio = ? AND estado != 'cancelada'");
        $checkStmt->execute([$pista, $fecha, $hora_inicio]);
        
        if ($checkStmt->fetch()) {
             http_response_code(409);
             echo json_encode(['status' => 'error', 'message' => 'El horario seleccionado ya está ocupado o bloqueado.']);
             exit;
        }

        // Insertar bloqueo
        $stmt = $pdo->prepare("INSERT INTO reservas (pista, fecha, hora_inicio, hora_fin, estado, creado_por) VALUES (?, ?, ?, ?, 'bloqueada_admin', ?)");
        $stmt->execute([$pista, $fecha, $hora_inicio, $hora_fin, $_SESSION['admin_id']]);
        
        echo json_encode(['status' => 'success', 'message' => 'Hora bloqueada correctamente.']);
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error de BBDD al guardar el bloqueo.']);
    }
}
?>
