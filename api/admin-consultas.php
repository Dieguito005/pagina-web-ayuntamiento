<?php
// api/admin-consultas.php — Buzón de consultas (listado + marcar resuelta + email respuesta)
require_once __DIR__ . '/../includes/SecurityHandler.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();
require_once 'conexion.php';
require_once 'mailer.php';
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

SecurityHandler::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    SecurityHandler::requireCSRF(!empty($_POST) ? $_POST : read_json_body());
}

$action = $_GET['action'] ?? 'get';

if ($action === 'get') {
    $busqueda = trim($_GET['search'] ?? '');
    $tipo = trim($_GET['tipo'] ?? '');
    $estado = trim($_GET['estado'] ?? '');
    $fechaInicio = trim($_GET['fecha_inicio'] ?? '');

    $query = "SELECT c.*, u.nombre AS user_nombre, u.apellidos AS user_apellidos
              FROM consultas c LEFT JOIN usuarios u ON c.usuario_id = u.id WHERE 1=1";
    $params = [];
    if ($busqueda !== '') {
        $query .= " AND (c.dni LIKE ? OR c.email LIKE ? OR c.nombre LIKE ? OR c.apellidos LIKE ?)";
        $likeB = '%' . like_escape($busqueda) . '%';
        $params = array_merge($params, [$likeB, $likeB, $likeB, $likeB]);
    }
    if ($tipo !== '') { $query .= " AND c.tipo_consulta = ?"; $params[] = $tipo; }
    if ($estado !== '') { $query .= " AND c.estado = ?"; $params[] = $estado; }
    if ($fechaInicio !== '') { $query .= " AND DATE(c.fecha_creacion) >= ?"; $params[] = $fechaInicio; }
    $query .= " ORDER BY c.fecha_creacion DESC LIMIT 100";

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (\PDOException $e) {
        error_log('admin_consultas_get: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error al obtener consultas.']);
    }
    exit;
}

if ($action === 'resolve') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        exit;
    }
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
        exit;
    }
    try {
        $stmt_get = $pdo->prepare("SELECT email, nombre, tipo_consulta, descripcion FROM consultas WHERE id = ?");
        $stmt_get->execute([$id]);
        $consulta = $stmt_get->fetch(PDO::FETCH_ASSOC);
        if (!$consulta) {
            echo json_encode(['success' => false, 'message' => 'Consulta no encontrada']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE consultas SET estado = 'resuelta' WHERE id = ?");
        $stmt->execute([$id]);

        $nombre = esc_html($consulta['nombre']);
        $tipo = esc_html($consulta['tipo_consulta']);
        $descripcion = esc_html($consulta['descripcion']);
        $asunto = "Su consulta sobre '" . $consulta['tipo_consulta'] . "' ha sido resuelta";
        $mensajeHTML = "<h2>Hola $nombre,</h2>
                        <p>Le informamos que su consulta reciente ha sido marcada como <strong>resuelta</strong> por nuestro equipo.</p>
                        <p><i>Su mensaje original:</i><br>&quot;$descripcion&quot;</p>
                        <p>Si tiene más dudas, no dude en contactarnos de nuevo.</p>
                        <p>Gracias,<br>El equipo del Ayuntamiento de Tarancón.</p>";

        if (function_exists('send_email')) {
            send_email($consulta['email'], $consulta['nombre'], $asunto, $mensajeHTML);
        }
        audit_log($pdo, 'consulta_resolve', 'consulta', $id);
        echo json_encode(['success' => true]);
    } catch (\PDOException $e) {
        error_log('admin_consultas_resolve: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error de base de datos.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción no válida']);
