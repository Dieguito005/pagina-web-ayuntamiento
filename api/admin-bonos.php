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

$json_data = [];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($action)) {
    $raw_input = file_get_contents('php://input');
    $json_data = json_decode($raw_input, true) ?? [];
    $action = $json_data['action'] ?? '';
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
    $id = $json_data['id'] ?? 0;
    $usos = $json_data['usos'] ?? null;
    $precio = $json_data['precio'] ?? null;
    $caducidad = $json_data['caducidad_meses'] ?? null;
    $activo = $json_data['activo'] ?? null;

    if (empty($id)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "ID de pack requerido."]);
        exit;
    }

    try {
        if ($usos !== null) {
            $check = $pdo->prepare("SELECT id FROM bonos_config WHERE usos = ? AND id != ?");
            $check->execute([$usos, $id]);
            if ($check->fetch()) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Ya existe un pack con $usos sesiones."]);
                exit;
            }
        }

        $updates = [];
        $params = [];

        if ($usos !== null) {
            $updates[] = "usos = ?";
            $updates[] = "tipo = ?";
            $updates[] = "nombre = ?";
            $params[] = intval($usos);
            $params[] = "bono_" . intval($usos);
            $params[] = "Bono " . intval($usos) . " Sesiones";
        }
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
        echo json_encode(["status" => "error", "message" => "Error al actualizar. Posible conflicto de datos."]);
    }
    exit;
}

// ════════════════════════════════════════════════
// CREATE CONFIG — Crear un nuevo pack de bono
// ════════════════════════════════════════════════
if ($action === 'create_config') {
    $usos = $json_data['usos'] ?? null;
    $precio = $json_data['precio'] ?? null;
    $caducidad = $json_data['caducidad_meses'] ?? null;

    if ($usos === null || $precio === null || $caducidad === null) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Faltan datos obligatorios (usos, precio o caducidad)."]);
        exit;
    }
    
    if (intval($usos) <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "La cantidad de sesiones debe ser mayor a 0."]);
        exit;
    }

    try {
        $check = $pdo->prepare("SELECT id FROM bonos_config WHERE usos = ?");
        $check->execute([$usos]);
        if ($check->fetch()) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Ya existe un pack con $usos sesiones."]);
            exit;
        }

        $tipo = "bono_" . intval($usos);
        $nombre = "Bono " . intval($usos) . " Sesiones";

        $stmt = $pdo->prepare("INSERT INTO bonos_config (tipo, nombre, usos, precio, caducidad_meses, activo) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$tipo, $nombre, intval($usos), floatval($precio), intval($caducidad)]);

        echo json_encode(["status" => "success", "message" => "Nuevo pack creado exitosamente."]);
    } catch (\PDOException $e) {
        error_log("admin-bonos create_config error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error al crear el pack en BD: " . $e->getMessage()]);
    }
    exit;
}

// ════════════════════════════════════════════════
// CANCEL BONO — Cancelar un bono activo
// ════════════════════════════════════════════════
if ($action === 'cancel_bono') {
    $bonoId = $json_data['bono_id'] ?? 0;

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
