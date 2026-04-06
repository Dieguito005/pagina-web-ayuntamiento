<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isUserLoggedIn = isset($_SESSION['user_id']);
$formNombre = $isUserLoggedIn ? htmlspecialchars($_SESSION['user_nombre'] ?? '') : '';
$formApellidos = $isUserLoggedIn ? htmlspecialchars($_SESSION['user_apellidos'] ?? '') : '';
$formEmail = $isUserLoggedIn ? htmlspecialchars($_SESSION['user_email'] ?? '') : '';
$formTelefono = $isUserLoggedIn ? htmlspecialchars($_SESSION['user_telefono'] ?? '') : '';
$formDni = $isUserLoggedIn ? htmlspecialchars($_SESSION['user_dni'] ?? '') : '';

// Optional readonly attribute if we want to lock it
$readonlyAttr = ''; // $isUserLoggedIn ? 'readonly style="background-color: #f5f5f5;"' : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Contacta con el Ayuntamiento de Tarancón para cualquier duda sobre los alquileres de pistas deportivas.">
    <link rel="preload" href="../assets/imagenes/LOGO-AYUNTAMIENTO-BLANCO.png" as="image">
    <link rel="stylesheet" href="../css/home.css">
    <link rel="stylesheet" href="../css/contacto.css">
    <title>Contacto | Alquileres Tarancón</title>
    <script src="../js/contacto.js?v=1.9.0" defer></script>
    <link rel="stylesheet" href="../css/cookies.css">
    <script src="../js/cookies.js" defer></script>
</head>
<body>

    <?php include '../includes/header.php'; ?>

    <main class="main-content contact-page-wrapper">
        <section class="contact-section scroll-animate" data-animation="fade-in-up">
            <div class="contact-page-container">
                <h2 class="contact-modal-title">Contactar Soporte</h2>
                <p class="contact-modal-desc" style="margin-bottom: 5px;">Ayuntamiento de Tarancón</p>
                <p style="text-align: center; font-size: 0.9rem; margin-bottom: 20px; color: #555;">
                    ¿Prefieres usar tu correo? <a href="mailto:deportes@tarancon.es" style="color: var(--color-primario, #914f26); text-decoration: underline; font-weight: bold;">Escríbenos directamente</a>.
                </p>
                
                <form id="contactForm" class="contact-form" novalidate>
                    <div class="form-group" style="display:none;" aria-hidden="true">
                        <label for="honeypot">No rellenar si eres humano</label>
                        <input type="text" id="honeypot" name="honeypot" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label for="contactNombre">Nombre *</label>
                        <input type="text" id="contactNombre" name="nombre" placeholder="Ej. Juan" value="<?= $formNombre ?>" <?= $readonlyAttr ?> required>
                        <span class="error-msg" id="errorNombre">El nombre es obligatorio.</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="contactApellidos">Apellidos *</label>
                        <input type="text" id="contactApellidos" name="apellidos" placeholder="Ej. Pérez García" value="<?= $formApellidos ?>" <?= $readonlyAttr ?> required>
                        <span class="error-msg" id="errorApellidos">Los apellidos son obligatorios.</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="contactDni">DNI / NIE *</label>
                        <input type="text" id="contactDni" name="dni" placeholder="Ej. 12345678Z" value="<?= $formDni ?>" <?= $readonlyAttr ?> required>
                        <span class="error-msg" id="errorDni">El DNI es obligatorio y debe tener formato válido.</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="contactEmail">Correo Electrónico *</label>
                        <input type="email" id="contactEmail" name="email" placeholder="ejemplo@correo.com" value="<?= $formEmail ?>" <?= $readonlyAttr ?> required>
                        <span class="error-msg" id="errorEmail">Ingrese un correo electrónico válido.</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="contactTelefono">Número de Teléfono *</label>
                        <input type="tel" id="contactTelefono" name="telefono" placeholder="Ej. 600123456" value="<?= $formTelefono ?>" <?= $readonlyAttr ?> required>
                        <span class="error-msg" id="errorTelefono">Ingrese un teléfono válido (al menos 9 dígitos).</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="contactTipo">Tipo de Consulta *</label>
                        <select id="contactTipo" name="tipo" required>
                            <option value="" disabled selected>Seleccione una opción...</option>
                            <option value="reserva">Reserva de Pistas</option>
                            <option value="tarifas">Dudas sobre Tarifas</option>
                            <option value="quejas">Quejas o Sugerencias</option>
                            <option value="otros">Otras Consultas</option>
                        </select>
                        <span class="error-msg" id="errorTipo">Debe seleccionar un tipo de consulta.</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="contactDescripcion">Descripción *</label>
                        <textarea id="contactDescripcion" name="descripcion" rows="4" placeholder="Detalle el motivo de su contacto aquí..." required></textarea>
                        <span class="error-msg" id="errorDescripcion">La descripción es obligatoria.</span>
                    </div>
                    
                    <div class="contact-modal-actions">
                        <a href="home.php" class="btn-modal btn-cancelar" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">Volver Inicio</a>
                        <button type="reset" class="btn-modal btn-restablecer" id="btnResetContact">Restablecer</button>
                        <button type="submit" class="btn-modal btn-enviar" id="btnSubmitContact">Enviar</button>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <!-- FOOTER PRINCIPAL -->
    <?php include '../includes/footer.php'; ?>
    
    <script>
        // Obtener el año actual para el footer dinámicamente
        document.addEventListener('DOMContentLoaded', () => {
            const yearSpan = document.getElementById('current-year');
            if (yearSpan) {
                yearSpan.textContent = new Date().getFullYear();
            }
        });

        // Efecto Navbar más compacto al hacer scroll
        window.addEventListener('scroll', () => {
            const header = document.querySelector('.main-header');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Animaciones con Intersection Observer
        const observeElements = document.querySelectorAll('.scroll-animate');
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const animationClass = entry.target.dataset.animation;
                    if (animationClass) {
                        entry.target.classList.add(animationClass);
                    }
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        observeElements.forEach(el => observer.observe(el));
    </script>
</body>
</html>



