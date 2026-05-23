<?php
// api/admin-2fa.php — Gestion de 2FA TOTP para administradores.
// Acciones:
//   - 'init'      -> genera secreto temporal y devuelve QR + secret (NO activa)
//   - 'enable'    -> verifica codigo + activa 2FA en BD (consume secret temporal)
//   - 'disable'   -> desactiva 2FA (requiere codigo TOTP actual)
//   - 'regen_recovery' -> regenera codigos de respaldo

require_once __DIR__ . '/../includes/SecurityHandler.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/totp.php';
session_start();
require_once 'conexion.php';
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$adminId = SecurityHandler::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    SecurityHandler::jsonError(405, 'Metodo no permitido.');
}

$data = read_json_body();
if (empty($data)) $data = $_POST;
SecurityHandler::requireCSRF($data);

$action = $data['action'] ?? '';

function generate_recovery_codes(int $n = 8): array
{
    $codes = [];
    for ($i = 0; $i < $n; $i++) {
        $codes[] = strtoupper(bin2hex(random_bytes(4))); // 8 hex chars
    }
    return $codes;
}

if ($action === 'init') {
    // Solo si NO esta ya activado
    $stmt = $pdo->prepare("SELECT totp_enabled FROM usuarios_admin WHERE id = ?");
    $stmt->execute([$adminId]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    if ($row && (int) $row['totp_enabled'] === 1) {
        SecurityHandler::jsonError(400, '2FA ya esta activado. Desactivalo primero si quieres regenerarlo.');
    }
    $secret = totp_generate_secret();
    $_SESSION['_2fa_pending_secret'] = $secret;
    $usuario = $_SESSION['admin_usuario'] ?? 'admin';
    $uri = totp_provisioning_uri('Ayuntamiento Tarancon', $usuario, $secret);
    $qrUrl = totp_qr_url($uri);
    SecurityHandler::jsonOk(['secret' => $secret, 'qr_url' => $qrUrl, 'uri' => $uri]);
}

if ($action === 'enable') {
    $code = preg_replace('/\s+/', '', (string) ($data['code'] ?? ''));
    $secret = $_SESSION['_2fa_pending_secret'] ?? '';
    if ($secret === '') {
        SecurityHandler::jsonError(400, 'No hay configuracion 2FA pendiente. Vuelve a iniciar.');
    }
    if (!totp_verify($secret, $code, 1)) {
        audit_log($pdo, 'admin_2fa_enable_fail', 'admin', $adminId);
        SecurityHandler::jsonError(403, 'Codigo incorrecto. Comprueba que la hora del telefono este sincronizada.');
    }
    try {
        $recovery = generate_recovery_codes(8);
        // Guardamos hashes (no los codigos en claro)
        $hashed = array_map(fn($c) => password_hash($c, PASSWORD_DEFAULT), $recovery);
        $upd = $pdo->prepare("UPDATE usuarios_admin SET totp_secret = ?, totp_enabled = 1, totp_recovery_codes = ? WHERE id = ?");
        $upd->execute([$secret, json_encode($hashed), $adminId]);
        unset($_SESSION['_2fa_pending_secret']);
        audit_log($pdo, 'admin_2fa_enabled', 'admin', $adminId);
        SecurityHandler::jsonOk(['recovery_codes' => $recovery, 'message' => '2FA activado. Guarda los codigos de respaldo en un lugar seguro.']);
    } catch (\PDOException $e) {
        error_log('admin_2fa_enable: ' . $e->getMessage());
        SecurityHandler::jsonError(500, 'Error al activar 2FA.');
    }
}

if ($action === 'disable') {
    $code = preg_replace('/\s+/', '', (string) ($data['code'] ?? ''));
    $stmt = $pdo->prepare("SELECT totp_secret, totp_enabled FROM usuarios_admin WHERE id = ?");
    $stmt->execute([$adminId]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (!$row || (int) $row['totp_enabled'] !== 1) {
        SecurityHandler::jsonError(400, '2FA no esta activado.');
    }
    if (!totp_verify($row['totp_secret'], $code, 1)) {
        audit_log($pdo, 'admin_2fa_disable_fail', 'admin', $adminId);
        SecurityHandler::jsonError(403, 'Codigo incorrecto.');
    }
    try {
        $upd = $pdo->prepare("UPDATE usuarios_admin SET totp_secret = NULL, totp_enabled = 0, totp_recovery_codes = NULL WHERE id = ?");
        $upd->execute([$adminId]);
        audit_log($pdo, 'admin_2fa_disabled', 'admin', $adminId);
        SecurityHandler::jsonOk(['message' => '2FA desactivado.']);
    } catch (\PDOException $e) {
        error_log('admin_2fa_disable: ' . $e->getMessage());
        SecurityHandler::jsonError(500, 'Error al desactivar 2FA.');
    }
}

if ($action === 'regen_recovery') {
    $code = preg_replace('/\s+/', '', (string) ($data['code'] ?? ''));
    $stmt = $pdo->prepare("SELECT totp_secret, totp_enabled FROM usuarios_admin WHERE id = ?");
    $stmt->execute([$adminId]);
    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
    if (!$row || (int) $row['totp_enabled'] !== 1) {
        SecurityHandler::jsonError(400, '2FA no esta activado.');
    }
    if (!totp_verify($row['totp_secret'], $code, 1)) {
        SecurityHandler::jsonError(403, 'Codigo incorrecto.');
    }
    try {
        $recovery = generate_recovery_codes(8);
        $hashed = array_map(fn($c) => password_hash($c, PASSWORD_DEFAULT), $recovery);
        $upd = $pdo->prepare("UPDATE usuarios_admin SET totp_recovery_codes = ? WHERE id = ?");
        $upd->execute([json_encode($hashed), $adminId]);
        audit_log($pdo, 'admin_2fa_recovery_regenerated', 'admin', $adminId);
        SecurityHandler::jsonOk(['recovery_codes' => $recovery, 'message' => 'Nuevos codigos de respaldo. Los anteriores ya no son validos.']);
    } catch (\PDOException $e) {
        error_log('admin_2fa_regen: ' . $e->getMessage());
        SecurityHandler::jsonError(500, 'Error al regenerar codigos.');
    }
}

SecurityHandler::jsonError(400, 'Accion no valida.');
