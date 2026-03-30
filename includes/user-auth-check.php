<?php
// includes/user-auth-check.php
// Incluir en páginas que requieren login de usuario (checkout, etc.)
// Redirige al login si el usuario no está autenticado.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    // Guardar la URL actual para redirigir después del login
    $currentPage = basename($_SERVER['PHP_SELF']);
    header("Location: user-login.php?redirect=" . urlencode($currentPage));
    exit;
}
?>
