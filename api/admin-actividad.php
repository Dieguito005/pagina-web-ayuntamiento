<?php
// api/admin-actividad.php — Centro de actividad (verificación de reservas por conserje)
require_once __DIR__ . '/../includes/SecurityHandler.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();
require_once 'conexion.php';
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

SecurityHandler::requireAdmin();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $limite = isset($_GET['limite']) ? (int) $_GET['limite'] : 50;
    if ($limite < 1 || $limite > 500) $limite = 50;
    $solo_hoy = isset($_GET['solo_hoy']) && $_GET['solo_hoy'] === '1';
    $pista = $_GET['pista'] ?? '';

    date_default_timezone_set('Europe/Madrid');
    $sql = "SELECT r.*, COALESCE(r.usuario_nombre, 'Desconocido') AS cliente_nombre
            FROM reservas r WHERE r.estado = 'reservada'";
    $params = [];
    if ($solo_hoy) {
        $sql .= " AND r.fecha = CURDATE()";
    } else {
        $sql .= " AND r.fecha >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)";
    }
    if ($pista !== '') {
        $sql .= " AND r.pista = ?";
        $params[] = $pista;
    }
    $sql .= " ORDER BY r.fecha ASC, r.hora_inicio ASC LIMIT :lim";

    try {
        $stmt = $pdo->prepare($sql);
        foreach ($params as $i => $val) {
            $stmt->bindValue($i + 1, $val);
        }
        $stmt->bindValue(':lim', $limite, PDO::PARAM_INT);
        $stmt->execute();
        $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmtHoy = $pdo->prepare("SELECT COUNT(*) AS total FROM reservas WHERE estado = 'reservada' AND fecha = CURDATE() AND (verificado_admin IS NULL OR verificado_admin = 0)");
        $stmtHoy->execute();
        $totalHoy = (int) ($stmtHoy->fetch()['total'] ?? 0);

        SecurityHandler::jsonOk(['data' => $reservas, 'total_hoy' => $totalHoy]);
    } catch (\PDOException $e) {
        error_log('admin_actividad_get: ' . $e->getMessage());
        SecurityHandler::jsonError(500, 'Error al obtener actividad.');
    }
}

if ($method !== 'POST') {
    SecurityHandler::jsonError(405, 'Método no permitido.');
}

$data = read_json_body();
SecurityHandler::requireCSRF($data);

$action = $data['action'] ?? '';
$id = (int) ($data['id'] ?? 0);
if ($id <= 0) {
    SecurityHandler::jsonError(400, 'ID requerido.');
}

try {
    if ($action === 'verificar') {
        date_default_timezone_set('Europe/Madrid');
        $stmt = $pdo->prepare("UPDATE reservas SET verificado_admin = 1, fecha_verificacion = NOW() WHERE id = ?");
        $stmt->execute([$id]);
        audit_log($pdo, 'verificar_reserva', 'reserva', $id);
        SecurityHandler::jsonOk(['message' => 'Reserva verificada correctamente.']);
    }
    if ($action === 'desverificar') {
        $stmt = $pdo->prepare("UPDATE reservas SET verificado_admin = 0, fecha_verificacion = NULL WHERE id = ?");
        $stmt->execute([$id]);
        audit_log($pdo, 'desverificar_reserva', 'reserva', $id);
        SecurityHandler::jsonOk(['message' => 'Verificación retirada.']);
    }
    SecurityHandler::jsonError(400, 'Acción no válida.');
} catch (\PDOException $e) {
    error_log('admin_actividad_post: ' . $e->getMessage());
    SecurityHandler::jsonError(500, 'Error de base de datos.');
}
