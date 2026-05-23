<?php
// api/user-logout.php — Cierra la sesión del usuario público.
// - Elimina variables de usuario (preserva admin si coexiste)
// - Regenera el ID de sesión para invalidar el cookie anterior

ob_start();
require_once __DIR__ . '/../includes/SecurityHandler.php';
require_once __DIR__ . '/../includes/audit.php';
session_start();
require_once 'conexion.php';

$userId = $_SESSION['user_id'] ?? null;

unset(
    $_SESSION['user_id'],
    $_SESSION['user_nombre'],
    $_SESSION['user_apellidos'],
    $_SESSION['user_email'],
    $_SESSION['user_dni'],
    $_SESSION['user_telefono']
);

if ($userId) {
    try {
        audit_log($pdo, 'logout', 'usuario', $userId);
    } catch (\Throwable $e) { /* no-op */
    }
}

// Regenerar para invalidar el cookie anterior
session_regenerate_id(true);

if (!headers_sent()) {
    header('Location: ../html/home.php');
    ob_end_flush();
    exit;
}
ob_end_clean();
echo '<!DOCTYPE html><html><head>';
echo '<meta http-equiv="refresh" content="0;url=../html/home.php">';
echo '<script>window.location.href="../html/home.php";</script>';
echo '</head><body><p>Cerrando sesion...</p></body></html>';
exit;
