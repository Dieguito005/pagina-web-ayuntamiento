<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Reserva tu pista de tenis en las instalaciones del Ayuntamiento de TarancÃ³n.">
    <link rel="preload" href="../assets/imagenes/LOGO-AYUNTAMIENTO-BLANCO.png" as="image">
    <link rel="stylesheet" href="../css/home.css?v=1.5.0">
    <link rel="stylesheet" href="../css/tenis.css?v=1.5.0">
    <title>Reservas Tenis - Alquileres TarancÃ³n</title>
    <link rel="stylesheet" href="../css/cookies.css?v=1.5.0">
    <script src="../js/cookies.js" defer></script>
</head>
<body>

    <?php include '../includes/header.php'; ?>

    <main class="main-content">
        <!-- SECCIÃ“N DE CABECERA ESPECÃFICA TENIS -->
        <section class="tennis-hero">
            <div class="tennis-hero-content scroll-animate" data-animation="fade-in-up">
                <h2>Reserva tu Pista de Tenis</h2>
                <p>Selecciona el horario que prefieras para tu prÃ³ximo partido.</p>
                <a href="home.php" class="btn-back">Volver al Inicio</a>
            </div>
        </section>

        <!-- ZONA DE RESERVA / HORARIOS -->
        <section class="booking-section">
            <div class="booking-container scroll-animate" data-animation="fade-in-up" data-sport="Tenis">
                
                <div class="booking-header">
                    <h3>Horarios Disponibles</h3>
                    <p class="booking-subtitle">Elige un tramo de 2 horas. Pistas de superficie rÃ¡pida.</p>
                </div>

                <!-- SelecciÃ³n de DÃ­as -->
                <div class="day-selector-container scroll-animate" data-animation="fade-in-up">
                    <div class="local-time-badge">
                        Hora local TarancÃ³n: <span id="local-time-display">--:--:--</span>
                    </div>
                    <div class="day-selector">
                        <button class="day-btn active" data-day-offset="0">
                            <span class="day-name">Hoy</span>
                            <span class="day-weekname">--</span>
                            <span class="day-date" id="date-today">--/--</span>
                        </button>
                        <button class="day-btn" data-day-offset="1">
                            <span class="day-name">MaÃ±ana</span>
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

                <!-- Los slots se generan dinÃ¡micamente por horarios.js -->
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



