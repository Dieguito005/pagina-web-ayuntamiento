<?php
// api/reset-password.php — Recibe POST con token + nueva password y la aplica.

require_once __DIR__ . '/../includes/SecurityHandler.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/tokens.php';
session_start();
require_once 'conexion.php';
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    SecurityHandler::jsonError(405, 'Metodo no permitido.');
}

SecurityHandler::requireCSRF($_POST);

$token = (string) ($_POST['token'] ?? '');
$newPass = clamp_password((string) ($_POST['password'] ?? ''));
$confirmPass = clamp_password((string) ($_POST['password2'] ?? ''));

if ($token === '' || $newPass === '' || $confirmPass === '') {
    SecurityHandler::jsonError(400, 'Faltan datos.');
}
if ($newPass !== $confirmPass) {
    SecurityHandler::jsonError(400, 'Las contraseñas no coinciden.');
}
if (strlen($newPass) < 12 || !preg_match('/[A-Z]/', $newPass) || !preg_match('/[a-z]/', $newPass) || !preg_match('/[0-9]/', $newPass)) {
    SecurityHandler::jsonError(400, 'La contraseña debe tener al menos 12 caracteres con mayúsculas, minúsculas y números.');
}

$hash = password_hash($newPass, PASSWORD_DEFAULT);
$userId = consume_password_reset_token($pdo, $token, $hash);

if ($userId === null) {
    audit_log($pdo, 'password_reset_invalid_token', 'usuario', null);
    SecurityHandler::jsonError(400, 'El enlace ha caducado o ya ha sido usado. Solicita uno nuevo.');
}

audit_log($pdo, 'password_reset_success', 'usuario', $userId);

// Por seguridad, destruir sesión actual si existiera
session_unset();
session_destroy();

SecurityHandler::jsonOk(['message' => 'Contraseña actualizada. Inicia sesión con la nueva contraseña.', 'redirect' => '../html/user-login.php']);
