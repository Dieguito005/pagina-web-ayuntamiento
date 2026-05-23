<?php
// api/admin-users.php — CRUD de administradores
// - admin obligatorio + CSRF
// - reconfirmar con contraseña actual en cada acción de escritura
// - audit log

require_once __DIR__ . '/../includes/SecurityHandler.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();
require_once 'conexion.php';
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$currentAdminId = SecurityHandler::requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function verifyCurrentAdminPassword(\PDO $pdo, int $adminId, string $authPassword): bool
{
    $stmt = $pdo->prepare("SELECT password_hash FROM usuarios_admin WHERE id = ? LIMIT 1");
    $stmt->execute([$adminId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row && password_verify($authPassword, $row['password_hash']);
}

if ($method === 'GET' && $action === 'get') {
    try {
        $stmt = $pdo->query("SELECT id, usuario, rol, ultimo_acceso FROM usuarios_admin ORDER BY id ASC");
        SecurityHandler::jsonOk(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (\PDOException $e) {
        error_log('admin_users_get: ' . $e->getMessage());
        SecurityHandler::jsonError(500, 'Error al obtener administradores.');
    }
}

if ($method !== 'POST') {
    SecurityHandler::jsonError(405, 'Método no permitido.');
}

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$data = strpos($contentType, 'application/json') !== false ? read_json_body() : $_POST;
SecurityHandler::requireCSRF($data);

$action = $data['action'] ?? '';
$authPassword = clamp_password((string) ($data['auth_password'] ?? ''));

if ($authPassword === '') {
    SecurityHandler::jsonError(400, 'Debes introducir tu contraseña actual para confirmar esta acción.');
}
if (!verifyCurrentAdminPassword($pdo, $currentAdminId, $authPassword)) {
    audit_log($pdo, 'admin_users_auth_fail', 'admin', $currentAdminId, ['action' => $action]);
    SecurityHandler::jsonError(403, 'Tu contraseña de confirmación es incorrecta.');
}

if ($action === 'create') {
    $nuevoUsuario = trim($data['nuevo_usuario'] ?? '');
    $nuevaPassword = clamp_password((string) ($data['nueva_password'] ?? ''));

    if ($nuevoUsuario === '' || $nuevaPassword === '') {
        SecurityHandler::jsonError(400, 'El nombre de usuario y la contraseña son obligatorios.');
    }
    if (!preg_match('/^[A-Za-z0-9_.\-]{3,40}$/', $nuevoUsuario)) {
        SecurityHandler::jsonError(400, 'Usuario inválido (3-40 caracteres alfanuméricos).');
    }
    if (strlen($nuevaPassword) < 12 || !preg_match('/[A-Z]/', $nuevaPassword) || !preg_match('/[a-z]/', $nuevaPassword) || !preg_match('/[0-9]/', $nuevaPassword)) {
        SecurityHandler::jsonError(400, 'La contraseña debe tener al menos 12 caracteres con mayúsculas, minúsculas y números.');
    }

    try {
        $check = $pdo->prepare("SELECT id FROM usuarios_admin WHERE usuario = ? LIMIT 1");
        $check->execute([$nuevoUsuario]);
        if ($check->fetch()) {
            SecurityHandler::jsonError(409, 'Ya existe un administrador con ese nombre de usuario.');
        }
        $hash = password_hash($nuevaPassword, PASSWORD_DEFAULT);
        $insert = $pdo->prepare("INSERT INTO usuarios_admin (usuario, password_hash, rol) VALUES (?, ?, 'admin')");
        $insert->execute([$nuevoUsuario, $hash]);
        audit_log($pdo, 'admin_create', 'admin', $pdo->lastInsertId(), ['usuario' => $nuevoUsuario]);
        SecurityHandler::jsonOk(['message' => "Administrador '{$nuevoUsuario}' creado correctamente."]);
    } catch (\PDOException $e) {
        error_log('admin_users_create: ' . $e->getMessage());
        SecurityHandler::jsonError(500, 'Error al crear el administrador.');
    }
}

if ($action === 'reset') {
    $targetId = (int) ($data['target_id'] ?? 0);
    $nuevaPassword = clamp_password((string) ($data['nueva_password'] ?? ''));
    if ($targetId <= 0) SecurityHandler::jsonError(400, 'ID de administrador no válido.');
    if (strlen($nuevaPassword) < 12 || !preg_match('/[A-Z]/', $nuevaPassword) || !preg_match('/[a-z]/', $nuevaPassword) || !preg_match('/[0-9]/', $nuevaPassword)) {
        SecurityHandler::jsonError(400, 'La nueva contraseña debe tener al menos 12 caracteres con mayúsculas, minúsculas y números.');
    }
    try {
        $hash = password_hash($nuevaPassword, PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE usuarios_admin SET password_hash = ? WHERE id = ?");
        $update->execute([$hash, $targetId]);
        if ($update->rowCount() > 0) {
            audit_log($pdo, 'admin_reset_password', 'admin', $targetId);
            SecurityHandler::jsonOk(['message' => 'Contraseña actualizada correctamente.']);
        }
        SecurityHandler::jsonError(404, 'No se encontró el administrador indicado.');
    } catch (\PDOException $e) {
        error_log('admin_users_reset: ' . $e->getMessage());
        SecurityHandler::jsonError(500, 'Error al resetear la contraseña.');
    }
}

if ($action === 'delete') {
    $targetId = (int) ($data['target_id'] ?? 0);
    if ($targetId <= 0) SecurityHandler::jsonError(400, 'ID de administrador no válido.');
    if ($targetId === $currentAdminId) SecurityHandler::jsonError(400, 'No puedes eliminar tu propia cuenta de administrador.');
    try {
        $countStmt = $pdo->query("SELECT COUNT(*) AS total FROM usuarios_admin");
        $total = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        if ($total <= 1) {
            SecurityHandler::jsonError(400, 'Debe existir al menos un administrador.');
        }
        $delete = $pdo->prepare("DELETE FROM usuarios_admin WHERE id = ?");
        $delete->execute([$targetId]);
        if ($delete->rowCount() > 0) {
            audit_log($pdo, 'admin_delete', 'admin', $targetId);
            SecurityHandler::jsonOk(['message' => 'Administrador eliminado correctamente.']);
        }
        SecurityHandler::jsonError(404, 'No se encontró el administrador indicado.');
    } catch (\PDOException $e) {
        error_log('admin_users_delete: ' . $e->getMessage());
        SecurityHandler::jsonError(500, 'Error al eliminar.');
    }
}

SecurityHandler::jsonError(400, 'Acción no reconocida.');
