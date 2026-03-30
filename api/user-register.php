<?php
// api/user-register.php — Procesa el registro de usuarios públicos
session_start();
require_once 'conexion.php';

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
        header("Location: ../html/user-registro.php?error=" . implode(',', $errors));
        exit;
    }

    try {
        // Verificar que el email no exista ya
        $checkStmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            header("Location: ../html/user-registro.php?error=email_existe");
            exit;
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
        $redirect = $_POST['redirect'] ?? '../html/home.php';
        if (strpos($redirect, '://') !== false || strpos($redirect, '//') === 0) {
            $redirect = '../html/home.php';
        }
        header("Location: " . $redirect);
        exit;

    } catch (\PDOException $e) {
        header("Location: ../html/user-registro.php?error=db");
        exit;
    }
} else {
    header("Location: ../html/user-registro.php");
    exit;
}
?>
