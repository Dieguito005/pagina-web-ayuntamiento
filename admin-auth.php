<?php
// admin-auth.php
session_start();
require_once 'api/conexion.php';

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
            // Login correcto
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_usuario'] = $admin['usuario'];
            $_SESSION['admin_rol'] = $admin['rol'];

            // Actualizar el último acceso
            $updateStmt = $pdo->prepare("UPDATE usuarios_admin SET ultimo_acceso = NOW() WHERE id = ?");
            $updateStmt->execute([$admin['id']]);

            header("Location: admin-dashboard.php");
            exit;
        } else {
            // Credenciales inválidas
            header("Location: admin-login.php?error=invalid");
            exit;
        }
    } catch (\PDOException $e) {
        // En caso de error de BBDD
        header("Location: admin-login.php?error=db");
        exit;
    }
} else {
    header("Location: admin-login.php");
    exit;
}
?>
