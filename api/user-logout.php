<?php
// api/user-logout.php — Cierra la sesión del usuario público
session_start();

// Eliminar solo las variables de sesión del usuario público (no del admin)
unset($_SESSION['user_id']);
unset($_SESSION['user_nombre']);
unset($_SESSION['user_apellidos']);
unset($_SESSION['user_email']);
unset($_SESSION['user_dni']);
unset($_SESSION['user_telefono']);

header("Location: ../html/home.php");
exit;
?>
