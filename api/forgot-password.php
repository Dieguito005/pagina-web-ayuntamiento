<?php
// api/forgot-password.php — Recibe POST con email y envia un enlace de reset.
// Anti-enumeracion: responde siempre con exito independientemente de si el email existe.

require_once __DIR__ . '/../includes/SecurityHandler.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/tokens.php';
require_once __DIR__ . '/mailer.php';
session_start();
require_once 'conexion.php';
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    SecurityHandler::jsonError(405, 'Metodo no permitido.');
}

SecurityHandler::requireCSRF($_POST);

$ip = SecurityHandler::clientIp();
if (SecurityHandler::isRateLimited($pdo, 'forgot_password', $ip, 5, 900)) {
    SecurityHandler::jsonError(429, 'Demasiadas peticiones. Espera 15 minutos.');
}

$email = strtolower(trim($_POST['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // No revelar nada al cliente — respuesta genérica
    SecurityHandler::jsonOk(['message' => 'Si la cuenta existe, recibirás un correo con instrucciones.']);
}

SecurityHandler::recordAttempt($pdo, 'forgot_password', $ip, true);

try {
    $stmt = $pdo->prepare("SELECT id, nombre FROM usuarios WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = create_password_reset_token($pdo, (int) $user['id']);
        $resetUrl = (defined('SITE_URL') && SITE_URL !== '' ? rtrim(SITE_URL, '/') : '')
            . '/html/reset-password.php?token=' . urlencode($token);

        $nH = esc_html($user['nombre']);
        $uH = esc_html($resetUrl);
        $html = "<html><body style='font-family:Arial,sans-serif;color:#333;line-height:1.6;'>
            <h2 style='color:#0b1c3c;'>Hola $nH,</h2>
            <p>Hemos recibido una solicitud para restablecer la contrase&ntilde;a de tu cuenta en el portal de reservas del Ayuntamiento de Taranc&oacute;n.</p>
            <p>Si fuiste t&uacute;, haz clic en el siguiente enlace (v&aacute;lido durante <strong>15 minutos</strong>):</p>
            <p style='margin:1.5rem 0;'>
                <a href='$uH' style='background:#d15615;color:white;padding:0.8rem 1.5rem;border-radius:6px;text-decoration:none;font-weight:600;'>Restablecer mi contrase&ntilde;a</a>
            </p>
            <p style='font-size:0.85em;color:#666;'>Si el bot&oacute;n no funciona, copia y pega esta direcci&oacute;n en tu navegador:<br><code style='word-break:break-all;'>$uH</code></p>
            <p>Si no has solicitado este cambio, ignora este correo: nadie podr&aacute; cambiar tu contrase&ntilde;a sin acceso a este enlace.</p>
            <p style='font-size:0.75em;color:#888;margin-top:2rem;'>Este correo lo env&iacute;a autom&aacute;ticamente el portal de reservas del Ayuntamiento de Taranc&oacute;n. Para cualquier consulta, escribe a <strong>dpd@tarancon.es</strong>.</p>
        </body></html>";

        send_email($email, $user['nombre'], 'Restablecer contrase' . chr(241) . 'a - Ayuntamiento de Tarancon', $html);
        audit_log($pdo, 'password_reset_requested', 'usuario', $user['id']);
    } else {
        audit_log($pdo, 'password_reset_unknown_email', 'usuario', null, ['email' => $email]);
    }
} catch (\PDOException $e) {
    error_log('forgot_password: ' . $e->getMessage());
    // No revelar al cliente, respuesta genérica igualmente
}

SecurityHandler::jsonOk(['message' => 'Si la cuenta existe, recibirás un correo con instrucciones.']);
