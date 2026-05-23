<?php
// includes/header.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/SecurityHandler.php';
require_once __DIR__ . '/helpers.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Regenerar ID de sesión en primer acceso (anti-fixation)
if (!isset($_SESSION['_initiated'])) {
    session_regenerate_id(true);
    $_SESSION['_initiated'] = true;
}
// Caducar la sesión por inactividad (30 min)
SecurityHandler::enforceSessionTimeout(1800);
send_security_headers();
check_maintenance();
$isUserLoggedIn = isset($_SESSION['user_id']);
$userName = $_SESSION['user_nombre'] ?? '';
$csrfToken = SecurityHandler::generateCSRFToken();
?>
<!-- Skip link accesible para usuarios de teclado / lector de pantalla (WCAG 2.4.1) -->
<a class="skip-link" href="#contenido-principal">Saltar al contenido principal</a>

<!-- Token CSRF para fetch del cliente (insertado por header.php) -->
<script>window.CSRF_TOKEN = <?= json_encode($csrfToken) ?>;</script>

<header class="main-header" role="banner">
    <div class="header-container">
        <div class="header-logo">
            <a href="https://nuevaweb.tarancon.es/" class="logo-home" aria-label="Ir al portal del Ayuntamiento de Tarancón">
                <div class="logo-image-box">
                    <img class="logo-ayuntamiento" src="../assets/imagenes/LOGO-AYUNTAMIENTO-BLANCO.png"
                        alt="Ayuntamiento de Tarancón" decoding="async">
                </div>
            </a>
        </div>
        <div class="header-title">
            <p class="header-title-text">Alquileres Tarancón</p>
        </div>

        <nav class="header-socials" aria-label="Cuenta de usuario y redes sociales del Ayuntamiento">
            <?php if ($isUserLoggedIn): ?>
                <a href="perfil.php" class="social-icon" aria-label="Mi perfil" title="Mi perfil" style="position:relative;">
                    <div class="icon-placeholder fade-in-delay-1" style="font-size:1.2rem; display:flex; align-items:center; gap:0.3rem;">
                        <span aria-hidden="true" style="font-size:1.3rem;">&#128100;</span>
                    </div>
                </a>
                <a href="bonos.php" class="social-icon" aria-label="Mis bonos" title="Mis bonos">
                    <div class="icon-placeholder fade-in-delay-1" style="font-size:1.1rem;">
                        <span aria-hidden="true">&#127903;</span>
                    </div>
                </a>
                <a href="../api/user-logout.php" class="social-icon" aria-label="Cerrar sesión" title="Cerrar sesión">
                    <div class="icon-placeholder fade-in-delay-2" style="font-size:1.1rem;">
                        <span aria-hidden="true">&#128682;</span>
                    </div>
                </a>
            <?php else: ?>
                <a href="user-login.php" class="social-icon" aria-label="Iniciar sesión" title="Iniciar sesión">
                    <div class="icon-placeholder fade-in-delay-1" style="font-size:1.1rem;">
                        <span aria-hidden="true">&#128273;</span>
                    </div>
                </a>
            <?php endif; ?>
            <a href="https://www.facebook.com/Ayuntamiento.tarancon/?locale=es_ES" class="social-icon" target="_blank" rel="noopener" aria-label="Facebook del Ayuntamiento (abre en nueva pestaña)">
                <div class="icon-placeholder fade-in-delay-2"><img src="../assets/iconos/facebook.png" alt="" loading="lazy" decoding="async"></div>
            </a>
            <a href="https://x.com/aytotarancon" class="social-icon" target="_blank" rel="noopener" aria-label="Cuenta del Ayuntamiento en X (abre en nueva pestaña)">
                <div class="icon-placeholder fade-in-delay-3"><img src="../assets/iconos/X.png" alt="" loading="lazy" decoding="async"></div>
            </a>
            <a href="https://www.instagram.com/taranconayto/" class="social-icon" target="_blank" rel="noopener" aria-label="Instagram del Ayuntamiento (abre en nueva pestaña)">
                <div class="icon-placeholder fade-in-delay-3"><img src="../assets/iconos/logotipo-de-instagram.png" alt="" loading="lazy" decoding="async"></div>
            </a>
        </nav>
    </div>
</header>
