<?php
// admin/admin-auth.php — Autenticacion de administradores con soporte 2FA TOTP.
// Paso 1: usuario + password (CSRF + rate limit IP).
// Paso 2: si totp_enabled, exigir codigo TOTP o codigo de respaldo.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/SecurityHandler.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/totp.php';
session_start();
require_once __DIR__ . '/../api/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin-login.php');
    exit;
}

SecurityHandler::requireCSRFOrRedirect('admin-login.php?error=csrf');

$ip = SecurityHandler::clientIp();
if (SecurityHandler::isRateLimited($pdo, 'admin_login', $ip, 5, 900)) {
    header('Location: admin-login.php?error=ratelimit');
    exit;
}

$mode = $_POST['mode'] ?? 'login';

// =====================
// Paso 2: verificacion de codigo TOTP
// =====================
if ($mode === 'totp') {
    if (empty($_SESSION['_2fa_pending']['admin_id'])) {
        header('Location: admin-login.php?error=invalid');
        exit;
    }
    $pendingId = (int) $_SESSION['_2fa_pending']['admin_id'];
    $code = preg_replace('/\s+/', '', (string) ($_POST['totp_code'] ?? ''));

    try {
        $stmt = $pdo->prepare("SELECT id, usuario, rol, totp_secret, totp_recovery_codes FROM usuarios_admin WHERE id = ? AND totp_enabled = 1 LIMIT 1");
        $stmt->execute([$pendingId]);
        $admin = $stmt->fetch();
        if (!$admin) {
            unset($_SESSION['_2fa_pending']);
            header('Location: admin-login.php?error=invalid');
            exit;
        }

        $ok = false;

        // Intentar codigo TOTP primero
        if (totp_verify($admin['totp_secret'], $code, 1)) {
            $ok = true;
        } else {
            // Intentar como codigo de respaldo (8 hex chars). Consumir si coincide.
            $codeUp = strtoupper($code);
            if (preg_match('/^[A-F0-9]{8}$/', $codeUp) && !empty($admin['totp_recovery_codes'])) {
                $codes = json_decode($admin['totp_recovery_codes'], true) ?: [];
                foreach ($codes as $i => $hashed) {
                    if (password_verify($codeUp, $hashed)) {
                        $ok = true;
                        // Consumir: eliminar de la lista
                        unset($codes[$i]);
                        $upd = $pdo->prepare("UPDATE usuarios_admin SET totp_recovery_codes = ? WHERE id = ?");
                        $upd->execute([json_encode(array_values($codes)), $admin['id']]);
                        audit_log($pdo, 'admin_2fa_recovery_used', 'admin', $admin['id']);
                        break;
                    }
                }
            }
        }

        if (!$ok) {
            SecurityHandler::recordAttempt($pdo, 'admin_login', $ip, false);
            audit_log($pdo, 'admin_2fa_fail', 'admin', $admin['id']);
            usleep(random_int(200000, 500000));
            header('Location: admin-login.php?error=totp&pending=1');
            exit;
        }

        SecurityHandler::clearAttempts($pdo, 'admin_login', $ip);
        SecurityHandler::regenerateSession();
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_usuario'] = $admin['usuario'];
        $_SESSION['admin_rol'] = $admin['rol'];
        $_SESSION['_last_activity'] = time();
        unset($_SESSION['_2fa_pending']);

        try {
            $pdo->prepare("UPDATE usuarios_admin SET ultimo_acceso = NOW() WHERE id = ?")->execute([$admin['id']]);
        } catch (\PDOException $e) { /* no-op */
        }

        audit_log($pdo, 'admin_login', 'admin', $admin['id'], ['usuario' => $admin['usuario'], '2fa' => 'totp']);
        header('Location: admin-dashboard.php');
        exit;
    } catch (\PDOException $e) {
        error_log('admin_auth_totp: ' . $e->getMessage());
        header('Location: admin-login.php?error=db');
        exit;
    }
}

// =====================
// Paso 1: usuario + password
// =====================
$usuario = trim($_POST['usuario'] ?? '');
$password = clamp_password((string) ($_POST['password'] ?? ''));

if ($usuario === '' || $password === '') {
    header('Location: admin-login.php?error=empty');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, usuario, password_hash, rol, totp_enabled FROM usuarios_admin WHERE usuario = ? LIMIT 1");
    $stmt->execute([$usuario]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        // Si tiene 2FA, no completamos sesion todavia
        if ((int) $admin['totp_enabled'] === 1) {
            $_SESSION['_2fa_pending'] = ['admin_id' => (int) $admin['id'], 'ts' => time()];
            audit_log($pdo, 'admin_password_ok_2fa_pending', 'admin', $admin['id']);
            header('Location: admin-login.php?step=totp');
            exit;
        }

        SecurityHandler::clearAttempts($pdo, 'admin_login', $ip);
        SecurityHandler::regenerateSession();
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_usuario'] = $admin['usuario'];
        $_SESSION['admin_rol'] = $admin['rol'];
        $_SESSION['_last_activity'] = time();

        try {
            $pdo->prepare("UPDATE usuarios_admin SET ultimo_acceso = NOW() WHERE id = ?")->execute([$admin['id']]);
        } catch (\PDOException $e) { /* no-op */
        }

        audit_log($pdo, 'admin_login', 'admin', $admin['id'], ['usuario' => $admin['usuario'], '2fa' => 'none']);
        header('Location: admin-dashboard.php');
        exit;
    }

    SecurityHandler::recordAttempt($pdo, 'admin_login', $ip, false);
    audit_log($pdo, 'admin_login_fail', 'admin', null, ['usuario' => $usuario]);
    usleep(random_int(200000, 500000));
    header('Location: admin-login.php?error=invalid');
    exit;
} catch (\PDOException $e) {
    error_log('admin_auth: ' . $e->getMessage());
    header('Location: admin-login.php?error=db');
    exit;
}
