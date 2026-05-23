<?php
// api/mailer.php — Envio de emails transaccionales via Resend API.
//
// Caracteristicas:
//   - Provider: Resend (https://resend.com) — 3000 emails/mes gratis, jurisdiccion UE disponible.
//   - Configurar RESEND_API_KEY en env.php (define('ENV_RESEND_API_KEY', 're_...');).
//   - Override desarrollo: si MAIL_OVERRIDE_TO esta definido, TODOS los emails
//     se redirigen a esa direccion (con un banner que indica el destinatario real).
//   - Fallback: si Resend no esta configurado, intenta mail() nativo (solo local).
//
// Para activar definitivamente al ayuntamiento:
//   1. En env.php definir ENV_RESEND_API_KEY con la clave de produccion
//   2. Eliminar la linea ENV_MAIL_OVERRIDE_TO
//   3. Verificar dominio reservas@tarancon.es en Resend (SPF+DKIM+DMARC en DNS)

require_once __DIR__ . '/../config.php';

/**
 * Envio principal. Devuelve [sent, method, error].
 */
function send_email(string $toEmail, string $toName, string $subject, string $htmlBody): array
{
    if (defined('MAIL_OVERRIDE_TO') && MAIL_OVERRIDE_TO !== '') {
        $banner = "<div style='background:#fff8e1; border:1px solid #ffb300; padding:10px; margin-bottom:16px; font-family:Arial,sans-serif; font-size:13px; color:#5d4037;'>"
            . "<strong>[DESARROLLO]</strong> Destinatario real: <code>" . htmlspecialchars($toEmail) . "</code> (" . htmlspecialchars($toName) . ")"
            . "</div>";
        $htmlBody = $banner . $htmlBody;
        $toEmail = MAIL_OVERRIDE_TO;
        $toName = 'DEV - ' . $toName;
    }

    if (defined('USE_RESEND') && USE_RESEND) {
        return send_via_resend($toEmail, $toName, $subject, $htmlBody);
    }
    return send_via_mail($toEmail, $toName, $subject, $htmlBody);
}

/**
 * Envio via Resend API (https://resend.com/docs/api-reference/emails/send-email).
 */
function send_via_resend(string $toEmail, string $toName, string $subject, string $htmlBody): array
{
    $fromHeader = sprintf('%s <%s>', MAIL_FROM_NAME, MAIL_FROM_EMAIL);

    $payload = [
        'from' => $fromHeader,
        'to' => [$toEmail],
        'subject' => $subject,
        'html' => $htmlBody,
    ];

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . RESEND_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    unset($ch);

    if ($curlError) {
        error_log('resend_curl_error: ' . $curlError);
        return ['sent' => false, 'method' => 'resend', 'error' => 'Error de conexion con el servicio de email.'];
    }
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['sent' => true, 'method' => 'resend', 'error' => null];
    }
    $decoded = json_decode($response, true);
    $msg = $decoded['message'] ?? $decoded['error'] ?? "HTTP $httpCode";
    error_log('resend_api_error (' . $httpCode . '): ' . (is_string($msg) ? $msg : json_encode($msg)));
    return ['sent' => false, 'method' => 'resend', 'error' => 'El servicio de email rechazo la peticion.'];
}

/**
 * Fallback mail() nativo (solo local con SMTP configurado).
 */
function send_via_mail(string $toEmail, string $toName, string $subject, string $htmlBody): array
{
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_EMAIL . ">\r\n";
    $sent = @mail($toEmail, $subject, $htmlBody, $headers);
    if ($sent) {
        return ['sent' => true, 'method' => 'mail', 'error' => null];
    }
    return ['sent' => false, 'method' => 'mail', 'error' => 'SMTP no disponible en este entorno.'];
}
