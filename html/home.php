<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Reserva tus pistas de pádel y tenis en el Ayuntamiento de Tarancón. Instalaciones modernas y accesibles.">
    <link rel="preload" href="../assets/imagenes/LOGO-AYUNTAMIENTO-BLANCO.png" as="image">
    <link rel="stylesheet" href="../css/home.css?v=2.0.0">
    <link rel="stylesheet" href="../css/bonos.css?v=1.0.0">
    <title>Alquileres Tarancón</title>
    <link rel="stylesheet" href="../css/cookies.css">
    <script src="../js/cookies.js" defer></script>
</head>

<body>

    <?php include '../includes/header.php'; ?>
    <div id="anuncios-container"></div>

    <main id="contenido-principal" class="main-content">
        <!-- SECCIÓN BANNER HERO -->
        <section class="hero-banner">
            <div class="hero-content scroll-animate" data-animation="fade-in-up">
                <h2>Alquila ya tu pista de Tenis, Pádel o Fútbol 7 en Tarancón</h2>
                <p>Nuevas instalaciones deportivas municipales a tu disposición. ¡Reserva hoy mismo!</p>
            </div>
        </section>

        <!-- SECCIÓN TARJETAS DE DEPORTES -->
        <section class="sports-cards-section">
            <div class="cards-container">
                <!-- Tarjeta Pádel -->
                <article class="sport-card scroll-animate" data-animation="slide-in-left">
                    <div class="card-image-box">
                        <img src="../assets/imagenes/raqueta-de-padel.png" alt="Pádel" class="sport-icon" loading="lazy"
                            decoding="async">
                    </div>
                    <div class="card-info">
                        <h3>Pádel</h3>
                        <p class="court-context">Pistas descubiertas y cubiertas de cristal a tu disposición.</p>
                        <a href="#" class="btn-primary" id="btn-padel-main">Ver pistas
                            disponibles</a>
                        <div id="padel-options"
                            style="display: none; margin-top: 15px; animation: fadeInUp 0.4s ease-out;">
                            <p style="color: #666; font-size: 0.95rem; margin-bottom: 10px; font-weight: 600;">¿Qué
                                pista te gustaría elegir?</p>
                            <div style="display: flex; gap: 10px; justify-content: center;">
                                <a href="padelCubierta.php" class="btn-secondary">Cubierta</a>
                                <a href="padelDescubierta.php" class="btn-secondary">Descubierta</a>
                            </div>
                        </div>
                    </div>
                </article>

                <!-- Tarjeta Tenis -->
                <article class="sport-card scroll-animate" data-animation="slide-in-right">
                    <div class="card-image-box">
                        <img src="../assets/imagenes/raqueta-de-tenis.png" alt="Tenis" class="sport-icon" loading="lazy"
                            decoding="async">
                    </div>
                    <div class="card-info">
                        <h3>Tenis</h3>
                        <p class="court-context">Pista de superficie rápida para tu mejor juego.</p>
                        <a href="tenis.php" class="btn-primary">Ver pistas disponibles</a>
                    </div>
                </article>

                <!-- Tarjeta Bonos -->
                <div class="bonos-home-card scroll-animate" data-animation="fade-in-up">
                    <div class="bonos-icon">🎟️</div>
                    <h3>Bonos de Sesiones</h3>
                    <p>Ahorra comprando packs de sesiones. Usa tu bono en cualquier pista sin pagar la reserva individual.</p>
                    <a href="bonos.php" class="btn-primary" style="background: linear-gradient(135deg, #d15615, #a44310); box-shadow: 0 4px 10px rgba(209, 86, 21, 0.3);">Comprar Bonos</a>
                </div>

                <!-- Tarjeta Fútbol 7 -->
                <article class="sport-card scroll-animate" data-animation="fade-in-up">
                    <div class="card-image-box">
                        <img src="../assets/imagenes/balon-futbol.png" alt="Fútbol 7" class="sport-icon" loading="lazy" decoding="async">
                    </div>
                    <div class="card-info">
                        <h3>Fútbol 7</h3>
                        <p class="court-context">Pista de césped artificial para partidos de 7 jugadores.</p>
                        <a href="futbol7.php" class="btn-primary" style="background: linear-gradient(135deg, #1a7a35, #0f5225);">Ver pista disponible</a>
                    </div>
                </article>

            </div>
        </section>
    </main>

    <!-- FOOTER PRINCIPAL -->
    <?php include '../includes/footer.php'; ?>

    <script>
        // 1. Mostrar opciones de Pádel
        document.getElementById('btn-padel-main')?.addEventListener('click', (event) => {
            event.preventDefault();
            document.getElementById('btn-padel-main').style.display = 'none';
            document.getElementById('padel-options').style.display = 'block';
        });

        // Sistema dinámico de anuncios — todo construido con DOM API (sin innerHTML)
        async function fetchAnuncios() {
            const container = document.getElementById('anuncios-container');
            if (!container) return;
            try {
                const resp = await fetch('../api/get-anuncios.php', { credentials: 'same-origin' });
                const result = await resp.json();
                if (!result.success || result.data.length === 0) return;

                const iconFor = (t) => t === 'aviso' ? '⚠️' : t === 'peligro' ? '⛔' : t === 'exito' ? '✅' : 'ℹ️';

                result.data.forEach((a) => {
                    const id = parseInt(a.id, 10);
                    const tipo = String(a.tipo || '').replace(/[^a-z0-9_-]/gi, '');
                    const banner = document.createElement('div');
                    banner.className = 'anuncio-banner anuncio-' + tipo;
                    banner.id = 'anuncio-' + id;

                    const content = document.createElement('div');
                    const h4 = document.createElement('h4');
                    h4.textContent = iconFor(tipo) + ' ' + (a.titulo || '');
                    const p = document.createElement('p');
                    p.textContent = a.mensaje || '';
                    content.append(h4, p);
                    banner.appendChild(content);

                    const closeBtn = document.createElement('button');
                    closeBtn.className = 'btn-close-anuncio';
                    closeBtn.type = 'button';
                    closeBtn.title = 'Ocultar';
                    closeBtn.setAttribute('aria-label', 'Cerrar anuncio');
                    closeBtn.textContent = '×';
                    closeBtn.addEventListener('click', () => { banner.style.display = 'none'; });
                    banner.appendChild(closeBtn);

                    container.appendChild(banner);
                });
            } catch (err) {
                console.error('Error cargando anuncios', err);
            }
        }

        // 2. Obtener el año actual para el footer dinámicamente
        document.addEventListener('DOMContentLoaded', () => {
            const yearSpan = document.getElementById('current-year');
            if (yearSpan) {
                yearSpan.textContent = new Date().getFullYear();
            }
            fetchAnuncios();
        });

        // 4. Efecto Navbar más compacto al hacer scroll
        window.addEventListener('scroll', () => {
            const header = document.querySelector('.main-header');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // 5. Animaciones con Intersection Observer (aparecen al hacer scroll)
        const observeElements = document.querySelectorAll('.scroll-animate');
        const observerOptions = {
            threshold: 0.1, // Se ejecuta cuando al menos el 10% del elemento es visible
            rootMargin: '0px 0px -50px 0px' // Un pequeño margen antes de que entre
        };

        const observer = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const animationClass = entry.target.dataset.animation;
                    if (animationClass) {
                        entry.target.classList.add(animationClass);
                    }
                    observer.unobserve(entry.target); // Dejar de observar para que solo se anime una vez
                }
            });
        }, observerOptions);

        observeElements.forEach(el => {
            observer.observe(el);
        });
    </script>
</body>

</html>


