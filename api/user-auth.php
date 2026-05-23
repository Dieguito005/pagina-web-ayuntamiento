<?php
// api/user-auth.php — Login de usuarios públicos
// - CSRF obligatorio
// - Rate limit por email + IP (5 intentos / 15 min)
// - session_regenerate_id tras login
// - Redirect saneado contra whitelist

ob_start();
require_once __DIR__ . '/../includes/SecurityHandler.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();
require_once 'conexion.php';

function safe_redirect_login($url)
{
    if (!headers_sent()) {
        header('Location: ' . $url);
        ob_end_flush();
        exit;
    }
    ob_end_clean();
    echo '<!DOCTYPE html><html><head>';
    echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '">';
    echo '<script>window.location.href=' . json_encode($url) . ';</script>';
    echo '</head><body><p>Redirigiendo...</p></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    safe_redirect_login('../html/user-login.php');
}

SecurityHandler::requireCSRFOrRedirect('../html/user-login.php?error=csrf');

$email = strtolower(trim($_POST['email'] ?? ''));
$password = clamp_password((string) ($_POST['password'] ?? ''));

if ($email === '' || $password === '') {
    safe_redirect_login('../html/user-login.php?error=empty');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    safe_redirect_login('../html/user-login.php?error=invalid');
}

$ip = SecurityHandler::clientIp();
$identifier = $email . '|' . $ip;

if (SecurityHandler::isRateLimited($pdo, 'user_login', $identifier, 5, 900)) {
    safe_redirect_login('../html/user-login.php?error=ratelimit');
}

try {
    $stmt = $pdo->prepare("SELECT id, nombre, apellidos, email, dni, telefono, password_hash FROM usuarios WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        SecurityHandler::clearAttempts($pdo, 'user_login', $identifier);
        SecurityHandler::regenerateSession();

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_nombre'] = $user['nombre'];
        $_SESSION['user_apellidos'] = $user['apellidos'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_dni'] = $user['dni'];
        $_SESSION['user_telefono'] = $user['telefono'];
        $_SESSION['_last_activity'] = time();

        try {
            $updateStmt = $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
        } catch (\PDOException $e) {
            // no-op
        }

        audit_log($pdo, 'login', 'usuario', $user['id'], ['email' => $email]);

        $redirect = SecurityHandler::sanitizeRedirect($_POST['redirect'] ?? null);
        safe_redirect_login($redirect);
    }

    // Credencial inválida: registrar intento, delay, redirigir mensaje genérico
    SecurityHandler::recordAttempt($pdo, 'user_login', $identifier, false);
    audit_log($pdo, 'login_fail', 'usuario', null, ['email' => $email]);
    usleep(random_int(200000, 500000));
    safe_redirect_login('../html/user-login.php?error=invalid');
} catch (\PDOException $e) {
    error_log('user_auth_error: ' . $e->getMessage());
    safe_redirect_login('../html/user-login.php?error=db');
}
