<?php
// api/admin-bonos.php — CRUD packs de bonos + listado/cancelación de bonos vendidos
require_once __DIR__ . '/../includes/SecurityHandler.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();
require_once 'conexion.php';
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

SecurityHandler::requireAdmin();

$json_data = [];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === '') {
    $json_data = read_json_body();
    $action = $json_data['action'] ?? '';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    SecurityHandler::requireCSRF(!empty($json_data) ? $json_data : $_POST);
}

if ($action === 'get_all') {
    $estado = $_GET['estado'] ?? '';
    $buscar = $_GET['buscar'] ?? '';
    $sql = "SELECT b.*, u.nombre AS user_nombre, u.apellidos AS user_apellidos, u.email AS user_email
            FROM bonos b LEFT JOIN usuarios u ON b.usuario_id = u.id WHERE 1=1";
    $params = [];
    if ($estado !== '') { $sql .= " AND b.estado = ?"; $params[] = $estado; }
    if ($buscar !== '') {
        $sql .= " AND (u.email LIKE ? OR u.nombre LIKE ? OR u.dni LIKE ?)";
        $b = '%' . like_escape($buscar) . '%';
        $params[] = $b; $params[] = $b; $params[] = $b;
    }
    $sql .= " ORDER BY b.fecha_compra DESC LIMIT 200";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $bonos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $statsStmt = $pdo->query("
            SELECT COUNT(*) AS total,
                   SUM(estado='activo') AS activos,
                   SUM(estado='agotado') AS agotados,
                   SUM(estado='caducado') AS caducados,
                   SUM(estado='cancelado') AS cancelados,
                   SUM(precio_pagado) AS ingresos_total
            FROM bonos");
        SecurityHandler::jsonOk(['data' => $bonos, 'stats' => $statsStmt->fetch()]);
    } catch (\PDOException $e) {
        error_log('admin_bonos_get_all: ' . $e->getMessage());
        SecurityHandler::jsonError(500, 'Error al obtener bonos.');
    }
}

if ($action === 'get_config') {
    try {
        $stmt = $pdo->query("SELECT * FROM bonos_config ORDER BY usos ASC");
        SecurityHandler::jsonOk(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (\PDOException $e) {
        error_log('admin_bonos_get_config: ' . $e->getMessage());
        SecurityHandler::jsonError(500, 'Error al obtener configuración.');
    }
}

if ($action === 'update_config') {
    $id = (int) ($json_data['id'] ?? 0);
    $usos = isset($json_data['usos']) ? (int) $json_data['usos'] : null;
    $precio = isset($json_data['precio']) ? (float) $json_data['precio'] : null;
    $caducidad = isset($json_data['caducidad_meses']) ? (int) $json_data['caducidad_meses'] : null;
    $activo = isset($json_data['activo']) ? (int) $json_data['activo'] : null;

    if ($id <= 0) SecurityHandler::jsonError(400, 'ID de pack requerido.');
    if ($usos !== null && ($usos <= 0 || $usos > 9999)) SecurityHandler::jsonError(400, 'Sesiones fuera de rango.');
    if ($precio !== null && ($precio < 0 || $precio > 99999)) SecurityHandler::jsonError(400, 'Precio fuera de rango.');
    if ($caducidad !== null && ($caducidad < 1 || $caducidad > 60)) SecurityHandler::jsonError(400, 'Caducidad fuera de rango.');

    try {
        if ($usos !== null) {
            $check = $pdo->prepare("SELECT id FROM bonos_config WHERE usos = ? AND id != ?");
            $check->execute([$usos, $id]);
            if ($check->fetch()) SecurityHandler::jsonError(400, "Ya existe un pack con $usos sesiones.");
        }
        $updates = [];
        $params = [];
        if ($usos !== null) {
            $updates[] = "usos = ?"; $updates[] = "tipo = ?"; $updates[] = "nombre = ?";
            $params[] = $usos; $params[] = "bono_$usos"; $params[] = "Bono $usos Sesiones";
        }
        if ($precio !== null) { $updates[] = "precio = ?"; $params[] = $precio; }
        if ($caducidad !== null) { $updates[] = "caducidad_meses = ?"; $params[] = $caducidad; }
        if ($activo !== null) { $updates[] = "activo = ?"; $params[] = $activo; }
        if (empty($updates)) SecurityHandler::jsonError(400, 'No hay campos para actualizar.');
        $params[] = $id;
        $sql = "UPDATE bonos_config SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        audit_log($pdo, 'bono_config_update', 'bonos_config', $id, compact('usos', 'precio', 'caducidad', 'activo'));
        SecurityHandler::jsonOk(['message' => 'Configuración actualizada.']);
    } catch (\PDOException $e) {
        error_log('admin_bonos_update_config: ' . $e->getMessage());
        SecurityHandler::jsonError(500, 'Error al actualizar.');
    }
}

if ($action === 'create_config') {
    $usos = isset($json_data['usos']) ? (int) $json_data['usos'] : null;
    $precio = isset($json_data['precio']) ? (float) $json_data['precio'] : null;
    $caducidad = isset($json_data['caducidad_meses']) ? (int) $json_data['caducidad_meses'] : null;
    if ($usos === null || $precio === null || $caducidad === null) SecurityHandler::jsonError(400, 'Faltan datos obligatorios.');
    if ($usos <= 0 || $usos > 9999) SecurityHandler::jsonError(400, 'Cantidad de sesiones inválida.');
    if ($precio < 0 || $precio > 99999) SecurityHandler::jsonError(400, 'Precio fuera de rango.');
    if ($caducidad < 1 || $caducidad > 60) SecurityHandler::jsonError(400, 'Caducidad fuera de rango.');

    try {
        $check = $pdo->prepare("SELECT id FROM bonos_config WHERE usos = ?");
        $check->execute([$usos]);
        if ($check->fetch()) SecurityHandler::jsonError(400, "Ya existe un pack con $usos sesiones.");
        $tipo = "bono_$usos";
        $nombre = "Bono $usos Sesiones";
        $stmt = $pdo->prepare("INSERT INTO bonos_config (tipo, nombre, usos, precio, caducidad_meses, activo) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->execute([$tipo, $nombre, $usos, $precio, $caducidad]);
        audit_log($pdo, 'bono_config_create', 'bonos_config', $pdo->lastInsertId(), compact('usos', 'precio', 'caducidad'));
        SecurityHandler::jsonOk(['message' => 'Nuevo pack creado.']);
    } catch (\PDOException $e) {
        error_log('admin_bonos_create_config: ' . $e->getMessage());
        SecurityHandler::jsonError(500, 'Error al crear el pack.');
    }
}

if ($action === 'cancel_bono') {
    $bonoId = (int) ($json_data['bono_id'] ?? 0);
    if ($bonoId <= 0) SecurityHandler::jsonError(400, 'ID de bono requerido.');
    try {
        $stmt = $pdo->prepare("UPDATE bonos SET estado = 'cancelado' WHERE id = ? AND estado = 'activo'");
        $stmt->execute([$bonoId]);
        if ($stmt->rowCount() > 0) {
            audit_log($pdo, 'bono_cancel', 'bono', $bonoId);
            SecurityHandler::jsonOk(['message' => 'Bono cancelado.']);
        }
        SecurityHandler::jsonError(404, 'Bono no encontrado o ya no está activo.');
    } catch (\PDOException $e) {
        error_log('admin_bonos_cancel: ' . $e->getMessage());
        SecurityHandler::jsonError(500, 'Error al cancelar bono.');
    }
}

SecurityHandler::jsonError(400, 'Acción no válida.');
