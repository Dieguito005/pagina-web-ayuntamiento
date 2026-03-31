<?php
// api/user-logout.php — Cierra la sesión del usuario público
ob_start();
session_start();

// Eliminar solo las variables de sesión del usuario público (no del admin)
unset($_SESSION['user_id']);
unset($_SESSION['user_nombre']);
unset($_SESSION['user_apellidos']);
unset($_SESSION['user_email']);
unset($_SESSION['user_dni']);
unset($_SESSION['user_telefono']);

if (!headers_sent()) {
    header("Location: ../html/home.php");
    ob_end_flush();
    exit;
}
ob_end_clean();
echo '<!DOCTYPE html><html><head>';
echo '<meta http-equiv="refresh" content="0;url=../html/home.php">';
echo '<script>window.location.href="../html/home.php";</script>';
echo '</head><body><p>Cerrando sesión...</p></body></html>';
exit;
