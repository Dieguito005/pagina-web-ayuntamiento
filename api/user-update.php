<?php
// api/user-update.php — Actualiza datos del usuario público (teléfono, email, contraseña)
// Requiere CSRF + sesión de usuario.

ob_start();
require_once __DIR__ . '/../includes/SecurityHandler.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'conexion.php';
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$userId = SecurityHandler::requireUser();
$data = read_json_body();
SecurityHandler::requireCSRF($data);

$action = $data['action'] ?? '';

if ($action === 'update_phone') {
    $telefono = trim($data['telefono'] ?? '');
    if ($telefono !== '' && !preg_match('/^[0-9]{9}$/', $telefono)) {
        SecurityHandler::jsonError(400, 'Teléfono inválido. Debe tener 9 dígitos.');
    }
    try {
        $stmt = $pdo->prepare("UPDATE usuarios SET telefono = ? WHERE id = ?");
        $stmt->execute([$telefono ?: null, $userId]);
        $_SESSION['user_telefono'] = $telefono ?: null;
        audit_log($pdo, 'update_phone', 'usuario', $userId);
        SecurityHandler::jsonOk(['message' => 'Teléfono actualizado correctamente.']);
    } catch (\PDOException $e) {
        error_log('user_update_phone: ' . $e->getMessage());
        SecurityHandler::jsonError(500, 'Error al actualizar el teléfono.');
    }
}

if ($action === 'update_email') {
    $newEmail = strtolower(trim($data['email'] ?? ''));
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        SecurityHandler::jsonError(400, 'El email introducido no es válido.');
    }
    if ($newEmail === strtolower($_SESSION['user_email'] ?? '')) {
        SecurityHandler::jsonError(400, 'El nuevo email es igual al actual.');
    }
    try {
        $check = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
        $check->execute([$newEmail, $userId]);
        if ($check->fetch()) {
            SecurityHandler::jsonError(409, 'Este email ya está registrado por otra cuenta.');
        }
        $stmt = $pdo->prepare("UPDATE usuarios SET email = ?, email_verificado = 0 WHERE id = ?");
        $stmt->execute([$newEmail, $userId]);

        audit_log($pdo, 'update_email', 'usuario', $userId);

        session_unset();
        session_destroy();
        SecurityHandler::jsonOk(['message' => 'Email actualizado. Por seguridad, inicia sesión de nuevo con tu nuevo email.', 'logout' => true]);
    } catch (\PDOException $e) {
        error_log('user_update_email: ' . $e->getMessage());
        SecurityHandler::jsonError(500, 'Error al actualizar el email.');
    }
}

if ($action === 'update_password') {
    $currentPass = clamp_password((string) ($data['current_password'] ?? ''));
    $newPass = clamp_password((string) ($data['new_password'] ?? ''));
    $confirmPass = clamp_password((string) ($data['confirm_password'] ?? ''));

    if ($currentPass === '' || $newPass === '' || $confirmPass === '') {
        SecurityHandler::jsonError(400, 'Completa todos los campos de contraseña.');
    }
    if ($newPass !== $confirmPass) {
        SecurityHandler::jsonError(400, 'La nueva contraseña y su confirmación no coinciden.');
    }
    if (strlen($newPass) < 12) {
        SecurityHandler::jsonError(400, 'La contraseña debe tener al menos 12 caracteres.');
    }
    if (!preg_match('/[A-Z]/', $newPass) || !preg_match('/[a-z]/', $newPass) || !preg_match('/[0-9]/', $newPass)) {
        SecurityHandler::jsonError(400, 'La contraseña debe incluir mayúsculas, minúsculas y números.');
    }
    try {
        $stmt = $pdo->prepare("SELECT password_hash FROM usuarios WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($currentPass, $row['password_hash'])) {
            SecurityHandler::jsonError(403, 'La contraseña actual no es correcta.');
        }
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
        $upd->execute([$newHash, $userId]);
        audit_log($pdo, 'update_password', 'usuario', $userId);

        session_unset();
        session_destroy();
        SecurityHandler::jsonOk(['message' => 'Contraseña actualizada. Por seguridad, inicia sesión de nuevo.', 'logout' => true]);
    } catch (\PDOException $e) {
        error_log('user_update_password: ' . $e->getMessage());
        SecurityHandler::jsonError(500, 'Error al actualizar la contraseña.');
    }
}

SecurityHandler::jsonError(400, 'Acción no válida.');
