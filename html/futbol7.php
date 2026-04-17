<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Reserva la pista de Fútbol 7 en las instalaciones deportivas del Ayuntamiento de Tarancón. Elige tu horario y reserva online.">
    <link rel="preload" href="../assets/imagenes/LOGO-AYUNTAMIENTO-BLANCO.png" as="image">
    <link rel="stylesheet" href="../css/home.css">
    <link rel="stylesheet" href="../css/tenis.css">
    <title>Reservas Fútbol 7 - Alquileres Tarancón</title>
    <link rel="stylesheet" href="../css/cookies.css">
    <script src="../js/cookies.js" defer></script>
</head>
<body>

    <?php include '../includes/header.php'; ?>

    <main class="main-content">
        <!-- SECCIÓN DE CABECERA ESPECÍFICA FÚTBOL 7 -->
        <section class="futbol7-hero">
            <div class="tennis-hero-content scroll-animate" data-animation="fade-in-up">
                <h2>Reserva la Pista de Fútbol 7</h2>
                <p>Instalación municipal al aire libre. Ideal para partidos de 7 a 7 con tu equipo.</p>
                <a href="home.php" class="btn-back">Volver al Inicio</a>
            </div>
        </section>

        <!-- ZONA DE RESERVA / HORARIOS -->
        <section class="booking-section">
            <div class="booking-container scroll-animate" data-animation="fade-in-up" data-sport="Fútbol 7">

                <div class="booking-header">
                    <h3>Horarios Disponibles</h3>
                    <p class="booking-subtitle">Elige un tramo de 2 horas. Pista de césped artificial.</p>
                </div>

                <!-- Selección de Días -->
                <div class="day-selector-container scroll-animate" data-animation="fade-in-up">
                    <div class="local-time-badge">
                        Hora local Tarancón: <span id="local-time-display">--:--:--</span>
                    </div>
                    <div class="day-selector">
                        <button class="day-btn active" data-day-offset="0">
                            <span class="day-name">Hoy</span>
                            <span class="day-weekname">--</span>
                            <span class="day-date" id="date-today">--/--</span>
                        </button>
                        <button class="day-btn" data-day-offset="1">
                            <span class="day-name">Mañana</span>
                            <span class="day-weekname">--</span>
                            <span class="day-date" id="date-tomorrow">--/--</span>
                        </button>
                        <button class="day-btn" data-day-offset="2">
                            <span class="day-name">Pasado</span>
                            <span class="day-weekname">--</span>
                            <span class="day-date" id="date-dayafter">--/--</span>
                        </button>
                    </div>
                </div>

                <!-- Los slots se generan dinámicamente por horarios.js -->
                <div class="schedule-grid"></div>

                <div class="booking-actions">
                    <button class="btn-confirm-booking" disabled>Confirmar Reserva</button>
                </div>

            </div>
        </section>
    </main>

    <!-- FOOTER PRINCIPAL -->
    <?php include '../includes/footer.php'; ?>

    <script src="../js/horarios.js?v=1.5.0" defer></script>
</body>
</html>
