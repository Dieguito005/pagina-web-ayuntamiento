<?php
// api/admin-actividad.php
// Centro de Actividad: avisos de reservas recientes para el conserje/admin
session_start();
require_once 'conexion.php';
header("Content-Type: application/json; charset=UTF-8");

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Acceso no autorizado"]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'get';

    if ($action === 'get') {
        // Obtener reservas recientes (últimas 72h por defecto o las del día filtrado)
        $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 50;
        $solo_hoy = isset($_GET['solo_hoy']) && $_GET['solo_hoy'] === '1';
        $pista = $_GET['pista'] ?? '';

        date_default_timezone_set('Europe/Madrid');

        $sql = "SELECT r.*, 
                       COALESCE(r.usuario_nombre, 'Desconocido') as cliente_nombre
                FROM reservas r
                WHERE r.estado = 'reservada'";

        $params = [];

        if ($solo_hoy) {
            $sql .= " AND r.fecha = CURDATE()";
        } else {
            $sql .= " AND r.fecha >= DATE_SUB(CURDATE(), INTERVAL 3 DAY)";
        }

        if (!empty($pista)) {
            $sql .= " AND r.pista = ?";
            $params[] = $pista;
        }

        $sql .= " ORDER BY r.fecha ASC, r.hora_inicio ASC";

        if ($limite > 0) {
            $sql .= " LIMIT " . $limite;
        }

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Contar reservas SIN verificar del día (para el badge — desaparece al verificar todas)
            $stmtHoy = $pdo->prepare("SELECT COUNT(*) as total FROM reservas WHERE estado = 'reservada' AND fecha = CURDATE() AND (verificado_admin IS NULL OR verificado_admin = 0)");
            $stmtHoy->execute();
            $totalHoy = $stmtHoy->fetch()['total'];

            echo json_encode([
                "status" => "success",
                "data" => $reservas,
                "total_hoy" => (int)$totalHoy
            ]);
        } catch (\PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Error al obtener actividad"]);
        }
    }

} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    if ($action === 'verificar') {
        // Marcar una reserva como verificada por el conserje (campo verificado_admin)
        $id = $data['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ID requerido"]);
            exit;
        }

        try {
            // Añadir columna si no existe (safe migration)
            $pdo->exec("ALTER TABLE reservas ADD COLUMN IF NOT EXISTS verificado_admin TINYINT(1) DEFAULT 0");
            $pdo->exec("ALTER TABLE reservas ADD COLUMN IF NOT EXISTS fecha_verificacion DATETIME DEFAULT NULL");

            date_default_timezone_set('Europe/Madrid');
            $stmt = $pdo->prepare("UPDATE reservas SET verificado_admin = 1, fecha_verificacion = NOW() WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(["status" => "success", "message" => "Reserva verificada correctamente"]);
        } catch (\PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Error al verificar"]);
        }

    } elseif ($action === 'desverificar') {
        $id = $data['id'] ?? null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ID requerido"]);
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE reservas SET verificado_admin = 0, fecha_verificacion = NULL WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(["status" => "success", "message" => "Verificación retirada"]);
        } catch (\PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Error al desverificar"]);
        }

    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Acción no válida"]);
    }

} else {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Método no permitido"]);
}
?>
