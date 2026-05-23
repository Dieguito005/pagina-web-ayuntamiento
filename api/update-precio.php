<?php
// api/update-precio.php — Editar precios personalizados de un slot (admin)
require_once __DIR__ . '/../includes/SecurityHandler.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();
require_once 'conexion.php';
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

SecurityHandler::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    SecurityHandler::jsonError(405, 'Método no permitido.');
}

$input = read_json_body();
SecurityHandler::requireCSRF($input);

$pista = $input['pista'] ?? '';
$fecha = $input['fecha'] ?? '';
$hora_inicio = $input['hora_inicio'] ?? '';
$precio_base = isset($input['precio_base']) && $input['precio_base'] !== '' ? (float) $input['precio_base'] : null;
$precio_luz = isset($input['precio_luz']) && $input['precio_luz'] !== '' ? (float) $input['precio_luz'] : null;

if ($pista === '' || $fecha === '' || $hora_inicio === '') {
    SecurityHandler::jsonError(400, 'Faltan datos obligatorios.');
}
if ($precio_base !== null && ($precio_base < 0 || $precio_base > 9999)) {
    SecurityHandler::jsonError(400, 'Precio base fuera de rango.');
}
if ($precio_luz !== null && ($precio_luz < 0 || $precio_luz > 9999)) {
    SecurityHandler::jsonError(400, 'Suplemento luz fuera de rango.');
}

try {
    if ($precio_base === null && $precio_luz === null) {
        $stmt = $pdo->prepare("DELETE FROM precios_personalizados WHERE pista = ? AND fecha = ? AND hora_inicio = ?");
        $stmt->execute([$pista, $fecha, $hora_inicio]);
        audit_log($pdo, 'price_reset', 'precio', null, compact('pista', 'fecha', 'hora_inicio'));
        SecurityHandler::jsonOk(['message' => 'Precios restablecidos al valor por defecto.']);
    }
    $stmt = $pdo->prepare(
        "INSERT INTO precios_personalizados (pista, fecha, hora_inicio, precio_base, precio_luz)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE precio_base = VALUES(precio_base), precio_luz = VALUES(precio_luz)"
    );
    $stmt->execute([$pista, $fecha, $hora_inicio, $precio_base, $precio_luz]);
    audit_log($pdo, 'price_update', 'precio', null, compact('pista', 'fecha', 'hora_inicio', 'precio_base', 'precio_luz'));
    SecurityHandler::jsonOk(['message' => 'Precios guardados correctamente.']);
} catch (\PDOException $e) {
    error_log('update_precio_error: ' . $e->getMessage());
    SecurityHandler::jsonError(500, 'Error al guardar los precios.');
}
