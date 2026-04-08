<?php
// admin-auth.php — Autenticación de administradores (endurecido para producción)
require_once __DIR__ . '/../config.php';
session_start();
require_once '../api/conexion.php';

// ── Rate limiting básico por sesión ──
// Máximo 5 intentos cada 15 minutos
$maxAttempts = 5;
$windowSeconds = 900; // 15 min

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = [];
}

// Limpiar intentos expirados
$_SESSION['login_attempts'] = array_filter($_SESSION['login_attempts'], function($t) use ($windowSeconds) {
    return $t > (time() - $windowSeconds);
});

if (count($_SESSION['login_attempts']) >= $maxAttempts) {
    header("Location: admin-login.php?error=ratelimit");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($usuario) || empty($password)) {
        header("Location: admin-login.php?error=empty");
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM usuarios_admin WHERE usuario = ? LIMIT 1");
        $stmt->execute([$usuario]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            // Login correcto — limpiar intentos y regenerar sesión
            $_SESSION['login_attempts'] = [];
            session_regenerate_id(true);

            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_usuario'] = $admin['usuario'];
            $_SESSION['admin_rol'] = $admin['rol'];

            // Actualizar el último acceso
            $updateStmt = $pdo->prepare("UPDATE usuarios_admin SET ultimo_acceso = NOW() WHERE id = ?");
            $updateStmt->execute([$admin['id']]);

            header("Location: admin-dashboard.php");
            exit;
        } else {
            // Credenciales inválidas — registrar intento
            $_SESSION['login_attempts'][] = time();

            // Delay artificial para ralentizar brute-force (200-500ms)
            usleep(random_int(200000, 500000));

            header("Location: admin-login.php?error=invalid");
            exit;
        }
    } catch (\PDOException $e) {
        error_log("Admin auth DB error: " . $e->getMessage());
        header("Location: admin-login.php?error=db");
        exit;
    }
} else {
    header("Location: admin-login.php");
    exit;
}
