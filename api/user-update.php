<?php
// api/user-update.php — Actualiza credenciales del usuario público
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'conexion.php';
header("Content-Type: application/json; charset=UTF-8");

// Solo usuarios logueados
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "No autenticado."]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$userId = $_SESSION['user_id'];

// ── ACTUALIZAR TELÉFONO (sin cierre de sesión) ──────────────────────────────
if ($action === 'update_phone') {
    $telefono = trim($data['telefono'] ?? '');

    if (!empty($telefono) && !preg_match('/^[0-9]{9}$/', $telefono)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Teléfono inválido. Debe tener 9 dígitos."]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE usuarios SET telefono = ? WHERE id = ?");
        $stmt->execute([$telefono ?: null, $userId]);
        $_SESSION['user_telefono'] = $telefono ?: null;
        echo json_encode(["status" => "success", "message" => "Teléfono actualizado correctamente."]);
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error al actualizar el teléfono."]);
    }

// ── ACTUALIZAR EMAIL (cierra sesión) ────────────────────────────────────────
} elseif ($action === 'update_email') {
    $newEmail = trim(strtolower($data['email'] ?? ''));

    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "El email introducido no es válido."]);
        exit;
    }

    if ($newEmail === strtolower($_SESSION['user_email'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "El nuevo email es igual al actual."]);
        exit;
    }

    try {
        // Comprobar que el email no esté en uso
        $check = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
        $check->execute([$newEmail, $userId]);
        if ($check->fetch()) {
            http_response_code(409);
            echo json_encode(["status" => "error", "message" => "Este email ya está registrado por otra cuenta."]);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE usuarios SET email = ? WHERE id = ?");
        $stmt->execute([$newEmail, $userId]);

        // Destruir sesión — el usuario deberá re-loguearse con el nuevo email
        session_unset();
        session_destroy();

        echo json_encode(["status" => "success", "message" => "Email actualizado. Por seguridad, inicia sesión de nuevo con tu nuevo email.", "logout" => true]);
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error al actualizar el email."]);
    }

// ── ACTUALIZAR CONTRASEÑA (cierra sesión) ───────────────────────────────────
} elseif ($action === 'update_password') {
    $currentPass  = $data['current_password'] ?? '';
    $newPass      = $data['new_password']     ?? '';
    $confirmPass  = $data['confirm_password'] ?? '';

    if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Completa todos los campos de contraseña."]);
        exit;
    }

    if ($newPass !== $confirmPass) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "La nueva contraseña y su confirmación no coinciden."]);
        exit;
    }

    if (strlen($newPass) < 8) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "La contraseña debe tener al menos 8 caracteres."]);
        exit;
    }

    try {
        // Verificar contraseña actual
        $stmt = $pdo->prepare("SELECT password_hash FROM usuarios WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($currentPass, $row['password_hash'])) {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "La contraseña actual no es correcta."]);
            exit;
        }

        // Actualizar con nuevo hash
        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
        $upd->execute([$newHash, $userId]);

        // Destruir sesión
        session_unset();
        session_destroy();

        echo json_encode(["status" => "success", "message" => "Contraseña actualizada. Por seguridad, inicia sesión de nuevo.", "logout" => true]);
    } catch (\PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error al actualizar la contraseña."]);
    }

} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Acción no válida."]);
}
