<?php
// api/user-auth.php — Procesa el login de usuarios públicos
session_start();
require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        header("Location: ../html/user-login.php?error=empty");
        exit;
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
            $redirect = $_POST['redirect'] ?? '../html/home.php';
            // Sanitizar redirect para evitar open redirect
            if (strpos($redirect, '://') !== false || strpos($redirect, '//') === 0) {
                $redirect = '../html/home.php';
            }
            header("Location: " . $redirect);
            exit;
        } else {
            header("Location: ../html/user-login.php?error=invalid");
            exit;
        }
    } catch (\PDOException $e) {
        header("Location: ../html/user-login.php?error=db");
        exit;
    }
} else {
    header("Location: ../html/user-login.php");
    exit;
}
?>
