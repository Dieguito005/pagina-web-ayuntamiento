<?php
// api/user-register.php — Alta de usuarios públicos
// - CSRF obligatorio
// - Aceptación expresa de la política de privacidad (art. 13 RGPD)
// - DNI/teléfono opcionales (minimización art. 5.1.c RGPD)
// - Genera token de verificación de email (no auto-loguea: el alta queda pendiente
//   de verificación cuando el flujo de email esté activo en producción)

ob_start();
require_once __DIR__ . '/../includes/SecurityHandler.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/tokens.php';
require_once __DIR__ . '/mailer.php';
session_start();
require_once 'conexion.php';

function safe_redirect_register($url)
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
    safe_redirect_register('../html/user-registro.php');
}

SecurityHandler::requireCSRFOrRedirect('../html/user-registro.php?error=csrf');

$ip = SecurityHandler::clientIp();
if (SecurityHandler::isRateLimited($pdo, 'user_register', $ip, 10, 900)) {
    safe_redirect_register('../html/user-registro.php?error=ratelimit');
}

$nombre = trim($_POST['nombre'] ?? '');
$apellidos = trim($_POST['apellidos'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$dni = strtoupper(trim($_POST['dni'] ?? ''));
$telefono = trim($_POST['telefono'] ?? '');
$password = clamp_password((string) ($_POST['password'] ?? ''));
$password2 = clamp_password((string) ($_POST['password2'] ?? ''));
$acceptPolicy = isset($_POST['accept_policy']) && $_POST['accept_policy'] === '1';
$acceptedVersion = $_POST['policy_version'] ?? '';

$errors = [];
if ($nombre === '' || $apellidos === '' || $email === '' || $password === '') {
    $errors[] = 'campos';
}
if (mb_strlen($nombre) > 60 || mb_strlen($apellidos) > 80) {
    $errors[] = 'campos';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'email';
}
if (strlen($password) < 8) {
    $errors[] = 'password_corta';
}
if ($password !== $password2) {
    $errors[] = 'password_mismatch';
}
if ($dni !== '' && !preg_match('/^[0-9]{8}[A-Z]$/', $dni) && !preg_match('/^[XYZ][0-9]{7}[A-Z]$/', $dni)) {
    $errors[] = 'dni';
}
if ($telefono !== '' && !preg_match('/^[0-9]{9}$/', $telefono)) {
    $errors[] = 'telefono';
}
if (!$acceptPolicy) {
    $errors[] = 'policy';
}

if (!empty($errors)) {
    SecurityHandler::recordAttempt($pdo, 'user_register', $ip, false);
    safe_redirect_register('../html/user-registro.php?error=' . implode(',', $errors));
}

try {
    $checkStmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
    $checkStmt->execute([$email]);
    if ($checkStmt->fetch()) {
        // No enumerar: redirigir con mensaje genérico (al usuario le decimos "te hemos enviado un email")
        SecurityHandler::recordAttempt($pdo, 'user_register', $ip, false);
        audit_log($pdo, 'register_duplicate_email', 'usuario', null, ['email' => $email]);
        safe_redirect_register('../html/user-registro.php?error=email_existe');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $consentId = bin2hex(random_bytes(8)) . '-' . bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(6));
    $policyVersion = defined('POLICY_VERSION') ? POLICY_VERSION : 'unknown';

    // Insertar usuario. Mass assignment seguro: campos cerrados.
    $insertStmt = $pdo->prepare(
        "INSERT INTO usuarios (nombre, apellidos, email, password_hash, dni, telefono, email_verificado, consent_id, politica_aceptada_version)
         VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)"
    );
    $insertStmt->execute([$nombre, $apellidos, $email, $hash, $dni ?: null, $telefono ?: null, $consentId, $policyVersion]);
    $newId = (int) $pdo->lastInsertId();

    // Registro del consentimiento (RGPD art. 5.2 — accountability)
    try {
        $consentStmt = $pdo->prepare(
            "INSERT INTO consent_log (user_id, consent_id, decision, analytics, policy_version, ip_anonymized, user_agent, created_at)
             VALUES (?, ?, 'register', 0, ?, ?, ?, NOW())"
        );
        $consentStmt->execute([
            $newId,
            $consentId,
            $policyVersion,
            SecurityHandler::clientIpAnonymized(),
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (\PDOException $e) {
        // Tabla puede no existir aún si setup no se ha ejecutado
        error_log('consent_log_insert: ' . $e->getMessage());
    }

    SecurityHandler::clearAttempts($pdo, 'user_register', $ip);
    audit_log($pdo, 'register', 'usuario', $newId, ['email' => $email]);

    // Enviar email de verificación (best-effort: no bloquear el registro si falla el envío)
    try {
        $verifyToken = create_email_verification_token($pdo, $newId);
        $verifyUrl = (defined('SITE_URL') && SITE_URL !== '' ? rtrim(SITE_URL, '/') : '')
            . '/html/verify-email.php?token=' . urlencode($verifyToken);
        $nH = esc_html($nombre);
        $uH = esc_html($verifyUrl);
        $html = "<html><body style='font-family:Arial,sans-serif;color:#333;line-height:1.6;'>
            <h2 style='color:#0b1c3c;'>Bienvenido/a, $nH</h2>
            <p>Gracias por registrarte en el portal de reservas deportivas del Ayuntamiento de Taranc&oacute;n.</p>
            <p>Para confirmar tu correo electr&oacute;nico, haz clic en el siguiente enlace (v&aacute;lido durante 24 horas):</p>
            <p style='margin:1.5rem 0;'><a href='$uH' style='background:#2e7d32;color:white;padding:0.8rem 1.5rem;border-radius:6px;text-decoration:none;font-weight:600;'>Confirmar mi email</a></p>
            <p style='font-size:0.85em;color:#666;'>Si el bot&oacute;n no funciona, copia esta direcci&oacute;n:<br><code style='word-break:break-all;'>$uH</code></p>
            <p style='font-size:0.75em;color:#888;margin-top:2rem;'>Si no has creado esta cuenta, ignora este correo.</p>
        </body></html>";
        send_email($email, $nombre, 'Confirma tu email - Ayuntamiento de Tarancon', $html);
    } catch (\Throwable $e) {
        error_log('user_register_verification_email: ' . $e->getMessage());
    }

    // Auto-login transitorio (mientras email_verificado no sea exigido en otros flujos)
    SecurityHandler::regenerateSession();
    $_SESSION['user_id'] = $newId;
    $_SESSION['user_nombre'] = $nombre;
    $_SESSION['user_apellidos'] = $apellidos;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_dni'] = $dni ?: null;
    $_SESSION['user_telefono'] = $telefono ?: null;
    $_SESSION['_last_activity'] = time();

    $redirect = SecurityHandler::sanitizeRedirect($_POST['redirect'] ?? null);
    safe_redirect_register($redirect);
} catch (\PDOException $e) {
    error_log('user_register_error: ' . $e->getMessage());
    safe_redirect_register('../html/user-registro.php?error=db');
}
