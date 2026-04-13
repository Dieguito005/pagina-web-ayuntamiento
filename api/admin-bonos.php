<?php
// api/admin-bonos.php — API Admin para gestión de bonos
// Acciones: get_all, get_config, update_config, cancel_bono

ob_start();
session_start();
require_once 'conexion.php';
header("Content-Type: application/json; charset=UTF-8");

// Auth admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Acceso no autorizado."]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($action)) {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';
}

// ════════════════════════════════════════════════
// GET ALL — Listar todos los bonos (con filtros)
// ════════════════════════════════════════════════
if ($action === 'get_all') {
    $estado = $_GET['estado'] ?? '';
    $buscar = $_GET['buscar'] ?? '';

    $sql = "SELECT b.*, u.nombre AS user_nombre, u.apellidos AS user_apellidos, u.email AS user_email 
            FROM bonos b 
            LEFT JOIN usuarios u ON b.usuario_id = u.id 
            WHERE 1=1";
    $params = [];

    if (!empty($estado)) {
        $sql .= " AND b.estado = ?";
        $params[] = $estado;
    }
    if (!empty($buscar)) {
        $sql .= " AND (u.email LIKE ? OR u.nombre LIKE ? OR u.dni LIKE ?)";
        $params[] = "%$buscar%";
        $params[] = "%$buscar%";
        $params[] = "%$buscar%";
    }

    $sql .= " ORDER BY b.fecha_compra DESC LIMIT 200";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $bonos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Stats
        $statsStmt = $pdo->query("
            SELECT 
                COUNT(*) AS total,
                SUM(estado = 'activo') AS activos,
                SUM(estado = 'agotado') AS agotados,
                SUM(estado = 'caducado') AS caducados,
                SUM(estado = 'cancelado') AS cancelados,
                SUM(precio_pagado) AS ingresos_total
            FROM bonos
        ");
        $stats = $statsStmt->fetch();

        echo json_encode(["status" => "success", "data" => $bonos, "stats" => $stats]);
    } catch (\PDOException $e) {
        error_log("admin-bonos get_all error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error al obtener bonos."]);
    }
    exit;
}

// ════════════════════════════════════════════════
// GET CONFIG — Obtener configuración de packs
// ════════════════════════════════════════════════
if ($action === 'get_config') {
    try {
        $stmt = $pdo->query("SELECT * FROM bonos_config ORDER BY usos ASC");
        $packs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["status" => "success", "data" => $packs]);
    } catch (\PDOException $e) {
        error_log("admin-bonos get_config error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error al obtener configuración."]);
    }
    exit;
}

// ════════════════════════════════════════════════
// UPDATE CONFIG — Actualizar precio/caducidad de un pack
// ════════════════════════════════════════════════
if ($action === 'update_config') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? 0;
    $precio = $data['precio'] ?? null;
    $caducidad = $data['caducidad_meses'] ?? null;
    $activo = $data['activo'] ?? null;

    if (empty($id)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ID de pack requerido."]);
        exit;
    }

    try {
        $updates = [];
        $params = [];

        if ($precio !== null) {
            $updates[] = "precio = ?";
            $params[] = floatval($precio);
        }
        if ($caducidad !== null) {
            $updates[] = "caducidad_meses = ?";
            $params[] = intval($caducidad);
        }
        if ($activo !== null) {
            $updates[] = "activo = ?";
            $params[] = intval($activo);
        }

        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "No hay campos para actualizar."]);
            exit;
        }

        $params[] = $id;
        $sql = "UPDATE bonos_config SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode(["status" => "success", "message" => "Configuración actualizada."]);
    } catch (\PDOException $e) {
        error_log("admin-bonos update_config error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error al actualizar."]);
    }
    exit;
}

// ════════════════════════════════════════════════
// CANCEL BONO — Cancelar un bono activo
// ════════════════════════════════════════════════
if ($action === 'cancel_bono') {
    $data = json_decode(file_get_contents('php://input'), true);
    $bonoId = $data['bono_id'] ?? 0;

    if (empty($bonoId)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ID de bono requerido."]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE bonos SET estado = 'cancelado' WHERE id = ? AND estado = 'activo'");
        $stmt->execute([$bonoId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(["status" => "success", "message" => "Bono cancelado."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Bono no encontrado o ya no está activo."]);
        }
    } catch (\PDOException $e) {
        error_log("admin-bonos cancel error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error al cancelar bono."]);
    }
    exit;
}

http_response_code(400);
echo json_encode(["status" => "error", "message" => "Acción no válida."]);
