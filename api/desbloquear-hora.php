<?php
// api/desbloquear-hora.php — Quita un bloqueo administrativo de un slot.
// Requiere admin + CSRF.

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

$data = read_json_body();
if (empty($data)) $data = $_POST;
SecurityHandler::requireCSRF($data);

$pista = $data['pista'] ?? '';
$fecha = $data['fecha'] ?? '';
$hora_inicio = $data['hora_inicio'] ?? '';

if ($pista === '' || $fecha === '' || $hora_inicio === '') {
    SecurityHandler::jsonError(400, 'Faltan datos requeridos.');
}

try {
    $stmt = $pdo->prepare("DELETE FROM reservas WHERE pista = ? AND fecha = ? AND hora_inicio = ? AND estado = 'bloqueada_admin'");
    $stmt->execute([$pista, $fecha, $hora_inicio]);
    if ($stmt->rowCount() > 0) {
        audit_log($pdo, 'unblock_slot', 'reserva', null, compact('pista', 'fecha', 'hora_inicio'));
        SecurityHandler::jsonOk(['message' => 'Hora desbloqueada correctamente.']);
    }
    SecurityHandler::jsonError(404, 'No se encontró un bloqueo administrativo en ese horario.');
} catch (\PDOException $e) {
    error_log('desbloquear_hora_error: ' . $e->getMessage());
    SecurityHandler::jsonError(500, 'Error al eliminar el bloqueo.');
}
