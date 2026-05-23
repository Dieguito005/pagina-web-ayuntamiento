<?php
// api/bloquear-hora.php — Bloqueo administrativo de un slot.
// Requiere admin + CSRF.

require_once __DIR__ . '/../includes/SecurityHandler.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();
require_once 'conexion.php';
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$adminId = SecurityHandler::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    SecurityHandler::jsonError(405, 'Método no permitido.');
}

$data = read_json_body();
if (empty($data)) $data = $_POST;
SecurityHandler::requireCSRF($data);

$pista = $data['pista'] ?? '';
$fecha = $data['fecha'] ?? '';
$hora_inicio = $data['hora_inicio'] ?? '';
$hora_fin = $data['hora_fin'] ?? '';

if ($pista === '' || $fecha === '' || $hora_inicio === '' || $hora_fin === '') {
    SecurityHandler::jsonError(400, 'Faltan datos requeridos (pista, fecha, horas).');
}

try {
    $checkStmt = $pdo->prepare("SELECT id FROM reservas WHERE pista = ? AND fecha = ? AND hora_inicio = ? AND estado != 'cancelada'");
    $checkStmt->execute([$pista, $fecha, $hora_inicio]);
    if ($checkStmt->fetch()) {
        SecurityHandler::jsonError(409, 'El horario seleccionado ya está ocupado o bloqueado.');
    }

    $stmt = $pdo->prepare("INSERT INTO reservas (pista, fecha, hora_inicio, hora_fin, estado, creado_por) VALUES (?, ?, ?, ?, 'bloqueada_admin', ?)");
    $stmt->execute([$pista, $fecha, $hora_inicio, $hora_fin, $adminId]);

    audit_log($pdo, 'block_slot', 'reserva', $pdo->lastInsertId(), compact('pista', 'fecha', 'hora_inicio', 'hora_fin'));
    SecurityHandler::jsonOk(['message' => 'Hora bloqueada correctamente.']);
} catch (\PDOException $e) {
    error_log('bloquear_hora_error: ' . $e->getMessage());
    SecurityHandler::jsonError(500, 'Error al guardar el bloqueo.');
}
