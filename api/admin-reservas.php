<?php
// api/admin-reservas.php — Búsqueda + cancelación de reservas
require_once __DIR__ . '/../includes/SecurityHandler.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();
require_once 'conexion.php';
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$adminId = SecurityHandler::requireAdmin();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $sql = "SELECT * FROM reservas WHERE 1=1";
    $params = [];
    if (!empty($_GET['dni'])) { $sql .= " AND dni LIKE ?"; $params[] = '%' . like_escape($_GET['dni']) . '%'; }
    if (!empty($_GET['nombre'])) { $sql .= " AND usuario_nombre LIKE ?"; $params[] = '%' . like_escape($_GET['nombre']) . '%'; }
    if (!empty($_GET['pista'])) { $sql .= " AND pista = ?"; $params[] = $_GET['pista']; }
    if (!empty($_GET['estado'])) { $sql .= " AND estado = ?"; $params[] = $_GET['estado']; }
    if (!empty($_GET['fecha_inicio'])) { $sql .= " AND fecha >= ?"; $params[] = $_GET['fecha_inicio']; }
    if (!empty($_GET['fecha_fin'])) { $sql .= " AND fecha <= ?"; $params[] = $_GET['fecha_fin']; }
    $sql .= " ORDER BY fecha DESC, hora_inicio DESC LIMIT 500";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        SecurityHandler::jsonOk(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (\PDOException $e) {
        error_log('admin_reservas_get: ' . $e->getMessage());
        SecurityHandler::jsonError(500, 'Error al buscar reservas.');
    }
}

if ($method !== 'POST') {
    SecurityHandler::jsonError(405, 'Método no permitido.');
}

$data = read_json_body();
SecurityHandler::requireCSRF($data);

if (($data['action'] ?? '') !== 'cancelar') {
    SecurityHandler::jsonError(400, 'Acción no válida.');
}

$id = (int) ($data['id'] ?? 0);
if ($id <= 0) SecurityHandler::jsonError(400, 'ID de reserva requerido.');

try {
    $check = $pdo->prepare("SELECT fecha, hora_inicio, estado FROM reservas WHERE id = ?");
    $check->execute([$id]);
    $res = $check->fetch(PDO::FETCH_ASSOC);
    if (!$res) SecurityHandler::jsonError(404, 'Reserva no encontrada.');
    if ($res['estado'] === 'cancelada') SecurityHandler::jsonError(400, 'La reserva ya está cancelada.');

    date_default_timezone_set('Europe/Madrid');
    $fechaActual = date('Y-m-d');
    $horaActual = date('H:i');
    $horaInicio = substr($res['hora_inicio'], 0, 5);
    if ($res['fecha'] < $fechaActual || ($res['fecha'] === $fechaActual && $horaInicio <= $horaActual)) {
        SecurityHandler::jsonError(400, 'No se puede anular una reserva pasada o en curso.');
    }

    $stmt = $pdo->prepare("UPDATE reservas SET estado = 'cancelada', cancelado_por_admin_id = ?, fecha_cancelacion = NOW() WHERE id = ?");
    $stmt->execute([$adminId, $id]);

    audit_log($pdo, 'cancel_reserva', 'reserva', $id, ['fecha' => $res['fecha'], 'hora' => $horaInicio]);
    SecurityHandler::jsonOk(['message' => 'Reserva cancelada exitosamente.']);
} catch (\PDOException $e) {
    error_log('admin_reservas_cancel: ' . $e->getMessage());
    SecurityHandler::jsonError(500, 'Error de base de datos.');
}
