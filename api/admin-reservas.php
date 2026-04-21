<?php
// api/admin-reservas.php
session_start();
require_once 'conexion.php';
header("Content-Type: application/json; charset=UTF-8");

// Verificación básica de que es un admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Acceso no autorizado"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../includes/SecurityHandler.php';
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!SecurityHandler::validateCSRFToken($csrfToken)) {
        http_response_code(403);
        die(json_encode(["status" => "error", "message" => "Token de seguridad inválido."]));
    }
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Buscar reservas
    $filters = [];
    $params = [];
    $sql = "SELECT * FROM reservas WHERE 1=1";

    if (!empty($_GET['dni'])) {
        $sql .= " AND dni LIKE ?";
        $params[] = '%' . $_GET['dni'] . '%';
    }

    if (!empty($_GET['nombre'])) {
        $sql .= " AND usuario_nombre LIKE ?";
        $params[] = '%' . $_GET['nombre'] . '%';
    }

    if (!empty($_GET['pista'])) {
        $sql .= " AND pista = ?";
        $params[] = $_GET['pista'];
    }

    if (!empty($_GET['estado'])) {
        $sql .= " AND estado = ?";
        $params[] = $_GET['estado'];
    }

    if (!empty($_GET['fecha_inicio'])) {
        $sql .= " AND fecha >= ?";
        $params[] = $_GET['fecha_inicio'];
    }

    if (!empty($_GET['fecha_fin'])) {
        $sql .= " AND fecha <= ?";
        $params[] = $_GET['fecha_fin'];
    }

    $sql .= " ORDER BY fecha DESC, hora_inicio DESC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["status" => "success", "data" => $reservas]);
    } catch (\PDOException $e) {
        http_response_code(500);
        error_log("admin-reservas GET error: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Error al buscar reservas"]);
    }
} elseif ($method === 'POST') {
    // Para cancelar u otras acciones
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['action']) && $data['action'] === 'cancelar') {
        $id = $data['id'] ?? null;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ID de reserva requerido"]);
            exit;
        }

        try {
            $stmtCheck = $pdo->prepare("SELECT fecha, hora_inicio FROM reservas WHERE id = ?");
            $stmtCheck->execute([$id]);
            $res = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($res) {
                date_default_timezone_set('Europe/Madrid');
                $fechaActual = date('Y-m-d');
                $horaActual = date('H:i');
                $horaInicio = substr($res['hora_inicio'], 0, 5);

                if ($res['fecha'] < $fechaActual || ($res['fecha'] === $fechaActual && $horaInicio <= $horaActual)) {
                    http_response_code(400);
                    echo json_encode(["status" => "error", "message" => "No se puede anular una reserva pasada o en curso."]);
                    exit;
                }
            }

            $stmt = $pdo->prepare("UPDATE reservas SET estado = 'cancelada' WHERE id = ?");
            if ($stmt->execute([$id])) {
                echo json_encode(["status" => "success", "message" => "Reserva cancelada exitosamente"]);
            } else {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "No se pudo cancelar la reserva"]);
            }
        } catch (\PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Error de base de datos", "error" => $e->getMessage()]);
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
