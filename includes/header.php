<?php
// includes/header.php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Regenerar ID de sesión en primer acceso (anti-fixation)
if (!isset($_SESSION['_initiated'])) {
    session_regenerate_id(true);
    $_SESSION['_initiated'] = true;
}
send_security_headers();
check_maintenance();
$isUserLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_nombre'] ?? '';
?>
<!-- includes/header.php -->

<header class="main-header">
    <div class="header-container">
        <div class="header-logo">
            <a href="https://nuevaweb.tarancon.es/" class="logo-home">
                <div class="logo-image-box">
                    <img class="logo-ayuntamiento" src="../assets/imagenes/LOGO-AYUNTAMIENTO-BLANCO.png"
                        alt="Logo Ayuntamiento" decoding="async">
                </div>
            </a>
        </div>
        <!-- Centro: Título -->
        <div class="header-title">
            <h1> Alquileres Tarancón </h1>
        </div>

        <!-- Derecha: Redes Sociales + Auth -->
        <div class="header-socials">
            <?php if ($isUserLoggedIn): ?>
                <a href="perfil.php" class="social-icon" aria-label="Mi Perfil" title="Mi Perfil" style="position:relative;">
                    <div class="icon-placeholder fade-in-delay-1" style="font-size:1.2rem; display:flex; align-items:center; gap:0.3rem;">
                        <span style="font-size:1.3rem;">👤</span>
                    </div>
                </a>
                <a href="bonos.php" class="social-icon" aria-label="Bonos" title="Mis Bonos">
                    <div class="icon-placeholder fade-in-delay-1" style="font-size:1.1rem;">
                        🎟️
                    </div>
                </a>
                <a href="../api/user-logout.php" class="social-icon" aria-label="Cerrar sesión" title="Cerrar sesión">
                    <div class="icon-placeholder fade-in-delay-2" style="font-size:1.1rem;">
                        🚪
                    </div>
                </a>
            <?php else: ?>
                <a href="user-login.php" class="social-icon" aria-label="Iniciar sesión" title="Iniciar sesión">
                    <div class="icon-placeholder fade-in-delay-1" style="font-size:1.1rem;">
                        🔑
                    </div>
                </a>
            <?php endif; ?>
            <a href="https://www.facebook.com/Ayuntamiento.tarancon/?locale=es_ES" class="social-icon" target="_blank" aria-label="Facebook">
                <div class="icon-placeholder fade-in-delay-2"><img src="../assets/iconos/facebook.png"
                        alt="logotipo de Facebook" loading="lazy" decoding="async"></div>
            </a>
            <a href="https://x.com/aytotarancon" class="social-icon" target="_blank" aria-label="Twitter">
                <div class="icon-placeholder fade-in-delay-3"><img src="../assets/iconos/X.png"
                        alt="logotipo de Twitter" loading="lazy" decoding="async"></div>
            </a>
            <a href="https://www.instagram.com/taranconayto/" class="social-icon" target="_blank"
                aria-label="Instagram">
                <div class="icon-placeholder fade-in-delay-3"><img src="../assets/iconos/logotipo-de-instagram.png"
                        alt="logotipo de Instagram" loading="lazy" decoding="async"></div>
            </a>
        </div>
    </div>
</header>
