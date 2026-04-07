<?php
// api/admin-users.php — CRUD de usuarios administradores
session_start();
require_once 'conexion.php';
header("Content-Type: application/json; charset=UTF-8");

// Verificar sesión de admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Acceso no autorizado"]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

/**
 * Verifica la contraseña del admin que está logueado actualmente.
 * Todas las acciones de escritura la requieren como capa de seguridad.
 */
function verifyCurrentAdminPassword($pdo, $adminId, $authPassword) {
    $stmt = $pdo->prepare("SELECT password_hash FROM usuarios_admin WHERE id = ? LIMIT 1");
    $stmt->execute([$adminId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return false;
    return password_verify($authPassword, $row['password_hash']);
}

// ── GET: Listar administradores ──
if ($method === 'GET' && $action === 'get') {
    try {
        $stmt = $pdo->query("SELECT id, usuario, rol, ultimo_acceso FROM usuarios_admin ORDER BY id ASC");
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["status" => "success", "data" => $admins]);
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error al obtener administradores"]);
    }
    exit;
}

// ── POST: Acciones de escritura ──
if ($method === 'POST') {
    // Aceptar tanto FormData como JSON
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
    } else {
        $data = $_POST;
    }

    $action = $data['action'] ?? '';
    $authPassword = $data['auth_password'] ?? '';

    // Todas las acciones POST requieren auth_password
    if (empty($authPassword)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Debes introducir tu contraseña actual para confirmar esta acción."]);
        exit;
    }

    // Verificar contraseña del admin actual
    if (!verifyCurrentAdminPassword($pdo, $_SESSION['admin_id'], $authPassword)) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Tu contraseña de confirmación es incorrecta."]);
        exit;
    }

    // ── Crear nuevo administrador ──
    if ($action === 'create') {
        $nuevoUsuario = trim($data['nuevo_usuario'] ?? '');
        $nuevaPassword = $data['nueva_password'] ?? '';
        $nuevoRol = 'admin';

        if (empty($nuevoUsuario) || empty($nuevaPassword)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "El nombre de usuario y la contraseña son obligatorios."]);
            exit;
        }

        if (strlen($nuevaPassword) < 8) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "La contraseña debe tener al menos 8 caracteres."]);
            exit;
        }

        try {
            // Comprobar si el usuario ya existe
            $check = $pdo->prepare("SELECT id FROM usuarios_admin WHERE usuario = ? LIMIT 1");
            $check->execute([$nuevoUsuario]);
            if ($check->fetch()) {
                http_response_code(409);
                echo json_encode(["status" => "error", "message" => "Ya existe un administrador con ese nombre de usuario."]);
                exit;
            }

            $hash = password_hash($nuevaPassword, PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO usuarios_admin (usuario, password_hash, rol) VALUES (?, ?, ?)");
            $insert->execute([$nuevoUsuario, $hash, $nuevoRol]);

            echo json_encode(["status" => "success", "message" => "Administrador '{$nuevoUsuario}' creado correctamente."]);
        } catch (\PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Error de base de datos al crear el usuario."]);
        }
        exit;
    }

    // ── Resetear contraseña de un admin ──
    if ($action === 'reset') {
        $targetId = intval($data['target_id'] ?? 0);
        $nuevaPassword = $data['nueva_password'] ?? '';

        if ($targetId <= 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ID de administrador no válido."]);
            exit;
        }

        if (strlen($nuevaPassword) < 8) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "La nueva contraseña debe tener al menos 8 caracteres."]);
            exit;
        }

        try {
            $hash = password_hash($nuevaPassword, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE usuarios_admin SET password_hash = ? WHERE id = ?");
            $update->execute([$hash, $targetId]);

            if ($update->rowCount() > 0) {
                echo json_encode(["status" => "success", "message" => "Contraseña actualizada correctamente."]);
            } else {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "No se encontró el administrador indicado."]);
            }
        } catch (\PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Error de base de datos al resetear la contraseña."]);
        }
        exit;
    }

    // ── Eliminar un administrador ──
    if ($action === 'delete') {
        $targetId = intval($data['target_id'] ?? 0);

        if ($targetId <= 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "ID de administrador no válido."]);
            exit;
        }

        // Prohibir borrarse a sí mismo
        if ($targetId == $_SESSION['admin_id']) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "No puedes eliminar tu propia cuenta de administrador."]);
            exit;
        }

        try {
            // Comprobar que no es el último admin
            $countStmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios_admin");
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            if ($total <= 1) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "No se puede eliminar. Debe existir al menos un administrador."]);
                exit;
            }

            $delete = $pdo->prepare("DELETE FROM usuarios_admin WHERE id = ?");
            $delete->execute([$targetId]);

            if ($delete->rowCount() > 0) {
                echo json_encode(["status" => "success", "message" => "Administrador eliminado correctamente."]);
            } else {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "No se encontró el administrador indicado."]);
            }
        } catch (\PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Error de base de datos al eliminar."]);
        }
        exit;
    }

    // Acción desconocida
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Acción no reconocida."]);
    exit;
}

// Método no permitido
http_response_code(405);
echo json_encode(["status" => "error", "message" => "Método no permitido."]);
