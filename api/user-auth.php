<?php
// api/user-auth.php — Procesa el login de usuarios públicos
ob_start(); // Capturar cualquier output accidental para que header() funcione siempre
session_start();
require_once 'conexion.php';

/**
 * Función de redirección robusta con fallback HTML/JS
 * Garantiza la redirección incluso si header() falla
 */
function safe_redirect($url) {
    // Intentar redirección por header HTTP
    if (!headers_sent()) {
        header("Location: " . $url);
        ob_end_flush();
        exit;
    }
    // Fallback: redirección por HTML + JavaScript
    ob_end_clean();
    echo '<!DOCTYPE html><html><head>';
    echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url) . '">';
    echo '<script>window.location.href="' . htmlspecialchars($url, ENT_QUOTES) . '";</script>';
    echo '</head><body><p>Redirigiendo...</p></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        safe_redirect('../html/user-login.php?error=empty');
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Login correcto — crear sesión
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nombre'] = $user['nombre'];
            $_SESSION['user_apellidos'] = $user['apellidos'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_dni'] = $user['dni'];
            $_SESSION['user_telefono'] = $user['telefono'];

            // Actualizar último acceso
            $updateStmt = $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);

            // Redirigir al destino original o a home
            $redirect = !empty($_POST['redirect']) ? $_POST['redirect'] : '../html/home.php';
            // Sanitizar redirect para evitar open redirect
            if (strpos($redirect, '://') !== false || strpos($redirect, '//') === 0) {
                $redirect = '../html/home.php';
            } elseif (!empty($_POST['redirect']) && strpos($redirect, '../html/') !== 0) {
                $redirect = '../html/' . ltrim($redirect, '/');
            }
            safe_redirect($redirect);
        } else {
            safe_redirect('../html/user-login.php?error=invalid');
        }
    } catch (\PDOException $e) {
        safe_redirect('../html/user-login.php?error=db');
    }
} else {
    safe_redirect('../html/user-login.php');
}
