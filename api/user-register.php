<?php
// api/user-register.php — Procesa el registro de usuarios públicos
ob_start();
session_start();
require_once 'conexion.php';

/**
 * Función de redirección robusta con fallback HTML/JS
 */
function safe_redirect($url) {
    if (!headers_sent()) {
        header("Location: " . $url);
        ob_end_flush();
        exit;
    }
    ob_end_clean();
    echo '<!DOCTYPE html><html><head>';
    echo '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url) . '">';
    echo '<script>window.location.href="' . htmlspecialchars($url, ENT_QUOTES) . '";</script>';
    echo '</head><body><p>Redirigiendo...</p></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $dni = strtoupper(trim($_POST['dni'] ?? ''));
    $telefono = trim($_POST['telefono'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    // Validaciones del servidor
    $errors = [];

    if (empty($nombre) || empty($apellidos) || empty($email) || empty($password)) {
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

    // Validar formato DNI (8 dígitos + letra) o NIE (X/Y/Z + 7 dígitos + letra)
    if (!empty($dni) && !preg_match('/^[0-9]{8}[A-Z]$/', $dni) && !preg_match('/^[XYZ][0-9]{7}[A-Z]$/', $dni)) {
        $errors[] = 'dni';
    }

    // Validar teléfono (9 dígitos)
    if (!empty($telefono) && !preg_match('/^[0-9]{9}$/', $telefono)) {
        $errors[] = 'telefono';
    }

    if (!empty($errors)) {
        safe_redirect('../html/user-registro.php?error=' . implode(',', $errors));
    }

    try {
        // Verificar que el email no exista ya
        $checkStmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            safe_redirect('../html/user-registro.php?error=email_existe');
        }

        // Hashear contraseña e insertar
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $insertStmt = $pdo->prepare("INSERT INTO usuarios (nombre, apellidos, email, password_hash, dni, telefono) VALUES (?, ?, ?, ?, ?, ?)");
        $insertStmt->execute([$nombre, $apellidos, $email, $hash, $dni ?: null, $telefono ?: null]);

        $newId = $pdo->lastInsertId();

        // Auto-login tras registro exitoso
        $_SESSION['user_id'] = $newId;
        $_SESSION['user_nombre'] = $nombre;
        $_SESSION['user_apellidos'] = $apellidos;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_dni'] = $dni ?: null;
        $_SESSION['user_telefono'] = $telefono ?: null;

        // Redirigir al destino original o a home
        $redirect = !empty($_POST['redirect']) ? $_POST['redirect'] : '../html/home.php';
        if (strpos($redirect, '://') !== false || strpos($redirect, '//') === 0) {
            $redirect = '../html/home.php';
        } elseif (!empty($_POST['redirect']) && strpos($redirect, '../html/') !== 0) {
            $redirect = '../html/' . ltrim($redirect, '/');
        }
        safe_redirect($redirect);

    } catch (\PDOException $e) {
        safe_redirect('../html/user-registro.php?error=db');
    }
} else {
    safe_redirect('../html/user-registro.php');
}
