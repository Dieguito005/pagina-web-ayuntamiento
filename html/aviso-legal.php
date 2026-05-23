<?php
require_once __DIR__ . '/../config.php';
$policyVersion = defined('POLICY_VERSION') ? POLICY_VERSION : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Aviso legal del portal de reservas deportivas del Ayuntamiento de Tarancon, conforme a la Ley 34/2002 (LSSI-CE).">
    <link rel="preload" href="../assets/imagenes/LOGO-AYUNTAMIENTO-BLANCO.png" as="image">
    <link rel="stylesheet" href="../css/home.css">
    <link rel="stylesheet" href="../css/politica.css">
    <link rel="stylesheet" href="../css/cookies.css">
    <script src="../js/cookies.js" defer></script>
    <title>Aviso Legal | Alquileres Tarancon</title>
</head>
<body>

    <?php include '../includes/header.php'; ?>

    <main id="contenido-principal" class="main-content legal-content">
        <section class="legal-section fade-in-up">
            <div class="legal-card aviso-card">
                <h2>Aviso Legal y Condiciones de Uso</h2>
                <p style="font-size:0.85rem; color:#777;">Versión <?= htmlspecialchars($policyVersion) ?> · Última revisión: <?= htmlspecialchars($policyVersion) ?></p>

                <div class="legal-text">
                    <p>En cumplimiento del artículo 10 de la Ley 34/2002, de 11 de julio, de Servicios de la Sociedad de la Información y Comercio Electrónico (LSSI-CE), se facilita la siguiente información sobre el prestador de servicios y las condiciones de uso del portal <em>"Alquileres Tarancón"</em>.</p>

                    <h3>1. Titularidad del portal</h3>
                    <ul>
                        <li><strong>Titular:</strong> Excelentísimo Ayuntamiento de Tarancón</li>
                        <li><strong>NIF:</strong> P1621500F</li>
                        <li><strong>Domicilio:</strong> Plaza del Ayuntamiento, 1, 16400 Tarancón (Cuenca), España</li>
                        <li><strong>Teléfono:</strong> +34 969 32 10 16</li>
                        <li><strong>Correo electrónico:</strong> info@tarancon.es</li>
                        <li><strong>Sede electrónica:</strong> <a href="https://sede.tarancon.es" target="_blank" rel="noopener">sede.tarancon.es</a></li>
                        <li><strong>Delegado de Protección de Datos:</strong> dpd@tarancon.es</li>
                    </ul>

                    <h3>2. Objeto del portal</h3>
                    <p>El portal tiene por objeto facilitar a la ciudadanía la reserva y pago de las instalaciones deportivas municipales (pistas de tenis y pádel cubierto/descubierto, fútbol 7 y bonos de sesiones), así como ofrecer información sobre tarifas, horarios y novedades del servicio.</p>

                    <h3>3. Condiciones de uso</h3>
                    <p>El acceso al portal atribuye la condición de usuario/a y supone la aceptación plena de este Aviso Legal y de la <a href="politica-privacidad.php">Política de Privacidad</a>. El usuario se compromete a hacer un uso diligente del servicio, conforme a la ley, la moral y el orden público, absteniéndose de:</p>
                    <ul>
                        <li>Realizar reservas fraudulentas o suplantar la identidad de terceros.</li>
                        <li>Acceder o intentar acceder a recursos restringidos o áreas privadas sin autorización.</li>
                        <li>Introducir virus, código malicioso o realizar acciones que comprometan la seguridad del portal o de otros usuarios.</li>
                        <li>Reproducir, distribuir o transformar los contenidos sin autorización del titular.</li>
                    </ul>

                    <h3>4. Reserva de instalaciones</h3>
                    <p>Las condiciones específicas de cada reserva (tarifa, suplemento por iluminación, horarios disponibles) se rigen por la ordenanza fiscal municipal aplicable, aprobada por el Pleno y publicada en el Boletín Oficial de la Provincia de Cuenca <span style="color:#777;font-size:0.85rem;">[REVISAR DPO: enlazar al BOP correspondiente cuando la ordenanza esté publicada]</span>.</p>
                    <p>Política de cancelación: el usuario podrá cancelar su reserva hasta 30 minutos antes de la hora de inicio. Las cancelaciones fuera de plazo no dan derecho a devolución. El procedimiento de devolución, en su caso, se rige por los artículos 31-34 de la Ley 58/2003 General Tributaria.</p>

                    <h3>5. Propiedad intelectual e industrial</h3>
                    <p>Los contenidos del portal (textos, gráficos, fotografías, código fuente, logotipos, escudo municipal y marcas) son titularidad del Ayuntamiento de Tarancón o de terceros que han autorizado su uso. Queda prohibida su reproducción, distribución, comunicación pública, transformación o cualquier otra forma de explotación sin autorización expresa del titular.</p>

                    <h3>6. Exclusión de responsabilidad</h3>
                    <p>El Ayuntamiento de Tarancón no garantiza la disponibilidad ininterrumpida del portal y no será responsable de:</p>
                    <ul>
                        <li>Interrupciones del servicio, errores en las comunicaciones o pérdidas de información derivadas de causas ajenas a su control.</li>
                        <li>Daños causados por la introducción de virus o programas maliciosos imputables a terceros.</li>
                        <li>El uso indebido del portal por parte de los usuarios.</li>
                    </ul>

                    <h3>7. Enlaces a terceros</h3>
                    <p>El portal puede incluir enlaces a sitios web de terceros (Stripe, redes sociales del Ayuntamiento). El Ayuntamiento no asume responsabilidad sobre el contenido o las prácticas de privacidad de dichos sitios.</p>

                    <h3>8. Protección de datos</h3>
                    <p>El tratamiento de datos personales se rige por la <a href="politica-privacidad.php">Política de Privacidad</a>, conforme al RGPD y la LOPDGDD.</p>

                    <h3>9. Atención al ciudadano - Canales alternativos</h3>
                    <p>De conformidad con el art. 14.1 de la Ley 39/2015, las personas físicas pueden optar por no relacionarse electrónicamente con la Administración. Para ello, pueden realizar reservas de forma presencial en la Concejalía de Deportes (Plaza del Ayuntamiento, 1) en horario de atención al público, o por teléfono en el +34 969 32 10 16.</p>

                    <h3>10. Modificaciones</h3>
                    <p>El Ayuntamiento se reserva el derecho de modificar este Aviso Legal y las condiciones de uso del portal en cualquier momento, sin necesidad de previo aviso. Las modificaciones se publicarán en esta página.</p>

                    <h3>11. Legislación aplicable y jurisdicción</h3>
                    <p>Las relaciones derivadas del uso de este portal se regirán por la legislación española. Las controversias se someterán a los Juzgados y Tribunales del partido judicial de Tarancón, salvo que la legislación procesal aplicable disponga otra cosa.</p>
                </div>

                <div class="legal-action-container">
                    <a href="home.php" class="btn-legal btn-volver">Volver al Inicio</a>
                </div>
            </div>
        </section>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
