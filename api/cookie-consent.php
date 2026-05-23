<?php
// api/cookie-consent.php — Registra la decisión del usuario sobre cookies
// y la guarda en cookie HTTP firmada + en BD (consent_log).
//
// Body JSON esperado:
//   { "decision": "all" | "rejected" | "custom", "analytics": true|false }

require_once __DIR__ . '/../includes/SecurityHandler.php';
require_once __DIR__ . '/../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    SecurityHandler::jsonError(405, 'Metodo no permitido.');
}

$data = read_json_body();
SecurityHandler::requireCSRF($data);

$decision = $data['decision'] ?? '';
$analytics = !empty($data['analytics']);

$allowed = ['all', 'rejected', 'custom'];
if (!in_array($decision, $allowed, true)) {
    SecurityHandler::jsonError(400, 'Decisión no válida.');
}

$policyVersion = defined('POLICY_VERSION') ? POLICY_VERSION : 'unknown';
$consentId = $_COOKIE['cookie_consent_id'] ?? ($_SESSION['cookie_consent_id'] ?? null);
if (!$consentId || !preg_match('/^[a-f0-9]{32}$/', $consentId)) {
    $consentId = bin2hex(random_bytes(16));
}
$_SESSION['cookie_consent_id'] = $consentId;

// Cookie firmada con el consent (24 meses)
$payload = $decision . '|' . ($analytics ? '1' : '0') . '|' . $policyVersion;
$cookieParams = [
    'expires' => time() + 86400 * 30 * 24, // 24 meses
    'path' => '/',
    'secure' => defined('IS_PRODUCTION') && IS_PRODUCTION,
    'httponly' => false, // JS necesita leerla para no recargar el banner
    'samesite' => 'Lax',
];
setcookie('cookie_consent', $payload, $cookieParams);
setcookie('cookie_consent_id', $consentId, array_merge($cookieParams, ['httponly' => true]));

try {
    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    $stmt = $pdo->prepare(
        "INSERT INTO consent_log (user_id, consent_id, decision, analytics, policy_version, ip_anonymized, user_agent, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([
        $userId,
        $consentId,
        $decision,
        $analytics ? 1 : 0,
        $policyVersion,
        SecurityHandler::clientIpAnonymized(),
        substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
} catch (\PDOException $e) {
    // No bloqueamos al usuario por un fallo en el log
    error_log('cookie_consent_log: ' . $e->getMessage());
}

SecurityHandler::jsonOk(['consent_id' => $consentId, 'policy_version' => $policyVersion]);
