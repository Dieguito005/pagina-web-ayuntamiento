<?php
// api/mailer.php — Helper centralizado para envío de emails
// Usa la API HTTP de Brevo (gratis, 300 emails/día) con fallback a mail() en local.
// Requiere cURL habilitado en PHP.

require_once __DIR__ . '/../config.php';

/**
 * Envía un email usando Brevo API o mail() como fallback.
 * 
 * @param string $toEmail   Dirección destino
 * @param string $toName    Nombre del destinatario
 * @param string $subject   Asunto del correo
 * @param string $htmlBody  Contenido HTML del correo
 * @return array ['sent' => bool, 'method' => 'brevo'|'mail'|'none', 'error' => string|null]
 */
function send_email($toEmail, $toName, $subject, $htmlBody) {
    // Intentar Brevo primero
    if (USE_BREVO) {
        return send_via_brevo($toEmail, $toName, $subject, $htmlBody);
    }

    // Fallback a mail() (solo funciona en servidores con SMTP configurado)
    return send_via_mail($toEmail, $toName, $subject, $htmlBody);
}

/**
 * Envío vía API REST de Brevo (ex-Sendinblue)
 * Documentación: https://developers.brevo.com/reference/sendtransacemail
 */
function send_via_brevo($toEmail, $toName, $subject, $htmlBody) {
    $payload = [
        'sender'      => ['email' => MAIL_FROM_EMAIL, 'name' => MAIL_FROM_NAME],
        'to'          => [['email' => $toEmail, 'name' => $toName]],
        'subject'     => $subject,
        'htmlContent' => $htmlBody
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'api-key: ' . BREVO_API_KEY,
            'content-type: application/json'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    // curl_close() omitido: desde PHP 8.5 el handle se libera automáticamente
    unset($ch);

    if ($curlError) {
        error_log("Brevo cURL error: " . $curlError);
        file_put_contents(__DIR__ . '/brevo_log.txt', date('Y-m-d H:i:s') . " - CURL ERROR: $curlError\n", FILE_APPEND);
        return ['sent' => false, 'method' => 'brevo', 'error' => 'Error de conexión con el servicio de email.'];
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['sent' => true, 'method' => 'brevo', 'error' => null];
    }

    // Brevo devolvió un error
    $decoded = json_decode($response, true);
    $msg = $decoded['message'] ?? "HTTP $httpCode";
    error_log("Brevo API error ($httpCode): $msg");
    file_put_contents(__DIR__ . '/brevo_log.txt', date('Y-m-d H:i:s') . " - API ERROR: " . print_r($decoded, true) . "\n", FILE_APPEND);
    return ['sent' => false, 'method' => 'brevo', 'error' => "Error del servicio de email: $msg"];
}

/**
 * Envío vía función mail() nativa de PHP (fallback)
 */
function send_via_mail($toEmail, $toName, $subject, $htmlBody) {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_EMAIL . ">\r\n";

    $sent = @mail($toEmail, $subject, $htmlBody, $headers);

    if ($sent) {
        return ['sent' => true, 'method' => 'mail', 'error' => null];
    }

    return ['sent' => false, 'method' => 'mail', 'error' => 'SMTP no disponible en este entorno.'];
}
