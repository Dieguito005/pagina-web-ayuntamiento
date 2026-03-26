<?php
require_once 'conexion.php';
header('Content-Type: application/json');

// Permitir solo peticiones POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$pista = $input['pista'] ?? '';
$fecha = $input['fecha'] ?? '';
$hora_inicio = $input['hora_inicio'] ?? '';
$precio_base = isset($input['precio_base']) && $input['precio_base'] !== '' ? (float) $input['precio_base'] : null;
$precio_luz = isset($input['precio_luz']) && $input['precio_luz'] !== '' ? (float) $input['precio_luz'] : null;

if (!$pista || !$fecha || !$hora_inicio) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Faltan datos obligatorios.']);
    exit;
}

try {
    // Si ambos son null, podríamos querer borrar el registro para volver al precio por defecto
    if ($precio_base === null && $precio_luz === null) {
        $stmt = $pdo->prepare("DELETE FROM precios_personalizados WHERE pista = ? AND fecha = ? AND hora_inicio = ?");
        $stmt->execute([$pista, $fecha, $hora_inicio]);
        echo json_encode(['status' => 'success', 'message' => 'Precios restablecidos al valor por defecto.']);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO precios_personalizados (pista, fecha, hora_inicio, precio_base, precio_luz)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE precio_base = VALUES(precio_base), precio_luz = VALUES(precio_luz)
        ");
        $stmt->execute([$pista, $fecha, $hora_inicio, $precio_base, $precio_luz]);
        echo json_encode(['status' => 'success', 'message' => 'Precios guardados correctamente.']);
    }

} catch (\PDOException $e) {
    http_response_code(500);
    error_log("Error de BBDD al guardar precio: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error al guardar los precios.']);
}
?>