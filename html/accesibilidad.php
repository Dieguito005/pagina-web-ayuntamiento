<?php
require_once __DIR__ . '/../config.php';
$policyVersion = defined('POLICY_VERSION') ? POLICY_VERSION : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Declaracion de accesibilidad del portal de reservas deportivas del Ayuntamiento de Tarancon (RD 1112/2018, WCAG 2.1 AA).">
    <link rel="preload" href="../assets/imagenes/LOGO-AYUNTAMIENTO-BLANCO.png" as="image">
    <link rel="stylesheet" href="../css/home.css">
    <link rel="stylesheet" href="../css/politica.css">
    <link rel="stylesheet" href="../css/cookies.css">
    <script src="../js/cookies.js" defer></script>
    <title>Declaracion de Accesibilidad | Alquileres Tarancon</title>
</head>
<body>

    <?php include '../includes/header.php'; ?>

    <main id="contenido-principal" class="main-content legal-content">
        <section class="legal-section fade-in-up">
            <div class="legal-card accesibilidad-card">
                <h2>Declaración de Accesibilidad</h2>
                <p style="font-size:0.85rem; color:#777;">Versión <?= htmlspecialchars($policyVersion) ?> · Fecha de la declaración: <?= htmlspecialchars($policyVersion) ?> · Última revisión: <?= htmlspecialchars($policyVersion) ?></p>

                <div class="legal-text">
                    <p>El Ayuntamiento de Tarancón se ha comprometido a hacer accesible el portal <em>"Alquileres Tarancón"</em> de conformidad con el Real Decreto 1112/2018, de 7 de septiembre, sobre accesibilidad de los sitios web y aplicaciones para dispositivos móviles del sector público, que transpone la Directiva (UE) 2016/2102.</p>

                    <h3>1. Ámbito de aplicación</h3>
                    <p>Esta declaración se aplica al sitio web <strong>https://reservas.tarancon.es</strong> (y al actual entorno de pruebas <em>alquileres-deportivos-tarancon.infinityfree.me</em>). No incluye contenidos publicados en sitios de terceros enlazados (Stripe, redes sociales).</p>

                    <h3>2. Situación de cumplimiento</h3>
                    <p>Este portal es <strong>parcialmente conforme</strong> con el Real Decreto 1112/2018 y los criterios de nivel AA de las WCAG 2.1 (UNE-EN 301 549 v3.2.1) debido a la falta de conformidad y a las excepciones de los aspectos que se indican a continuación.</p>

                    <h3>3. Contenido no accesible</h3>
                    <p>El contenido que se recoge a continuación no es totalmente accesible por los siguientes motivos:</p>
                    <ul>
                        <li><strong>3.1 Falta de conformidad con el RD 1112/2018:</strong>
                            <ul>
                                <li>Algunos textos sobre fondo con gradiente pueden no alcanzar el ratio de contraste mínimo 4.5:1 (criterio WCAG 1.4.3) - en revisión.</li>
                                <li>Determinados componentes dinámicos (slots horarios) podrían no anunciar los cambios de estado a productos de apoyo (criterio WCAG 4.1.3) - corrección planificada.</li>
                                <li>Los iconos basados en emojis pueden ser leídos literalmente por algunos lectores de pantalla. Se proporciona texto alternativo equivalente, pero la experiencia puede variar.</li>
                            </ul>
                        </li>
                        <li><strong>3.2 Carga desproporcionada:</strong> no se invoca.</li>
                        <li><strong>3.3 Contenido fuera del ámbito de la legislación aplicable:</strong> no aplica.</li>
                    </ul>

                    <h3>4. Preparación de la declaración</h3>
                    <ul>
                        <li><strong>Fecha de la declaración:</strong> <?= htmlspecialchars($policyVersion) ?></li>
                        <li><strong>Fecha de la última revisión:</strong> <?= htmlspecialchars($policyVersion) ?></li>
                        <li><strong>Método de evaluación:</strong> autoevaluación realizada por el equipo responsable del portal, basada en los requisitos de la norma UNE-EN 301 549 v3.2.1 y en las pautas WCAG 2.1 nivel AA.</li>
                        <li><strong>Norma de referencia:</strong> UNE-EN 301 549 v3.2.1 (accesibilidad de productos y servicios TIC), Real Decreto 1112/2018 y Directiva (UE) 2016/2102.</li>
                        <li><strong>Próxima revisión prevista:</strong> revisión anual o tras cambios sustanciales en el portal.</li>
                    </ul>

                    <h3>5. Observaciones y datos de contacto</h3>

                    <h4>5.1 Mecanismo de comunicación</h4>
                    <p>Puede realizar comunicaciones sobre requisitos de accesibilidad (art. 10.2.a RD 1112/2018), tales como informar sobre posibles incumplimientos por parte de este sitio web, transmitir otras dificultades de acceso al contenido o formular cualquier consulta o sugerencia de mejora, a través de:</p>
                    <ul>
                        <li>Correo electrónico: <strong>accesibilidad@tarancon.es</strong> <span style="color:#777;font-size:0.85rem;">[REVISAR DPO: confirmar que el buzón está habilitado en producción]</span></li>
                        <li>Formulario de contacto: <a href="contacto.php">Formulario de contacto</a></li>
                        <li>Presencialmente: Plaza del Ayuntamiento, 1, 16400 Tarancón (Cuenca)</li>
                    </ul>
                    <p>El Ayuntamiento responderá en un plazo máximo de 20 días hábiles.</p>

                    <h4>5.2 Procedimiento de aplicación (art. 13 RD 1112/2018)</h4>
                    <p>Si tras realizar una solicitud de información accesible o queja, ésta hubiera sido desestimada, no se compartiera la decisión adoptada o la respuesta no cumpliera los requisitos del art. 12.5 del RD 1112/2018, puede iniciar una reclamación en el plazo de 20 días hábiles dirigida a la Unidad responsable de Accesibilidad del Ayuntamiento de Tarancón, a través de la sede electrónica.</p>

                    <h4>5.3 Reclamación ante el Observatorio de Accesibilidad Web</h4>
                    <p>Si la respuesta no es satisfactoria, puede presentar una reclamación ante el <strong>Observatorio de Accesibilidad Web</strong> del Portal de Administración Electrónica (PAe) del Ministerio para la Transformación Digital y de la Función Pública: <a href="https://administracionelectronica.gob.es/pae_Home/pae_Estrategias/pae_Accesibilidad/pae_Observatorio_de_Accesibilidad.html" target="_blank" rel="noopener">administracionelectronica.gob.es</a>.</p>

                    <h3>6. Contenido opcional - Compromiso de mejora continua</h3>
                    <p>El Ayuntamiento de Tarancón se compromete a:</p>
                    <ul>
                        <li>Revisar anualmente la accesibilidad del portal.</li>
                        <li>Atender en plazo las comunicaciones recibidas a través del buzón de accesibilidad.</li>
                        <li>Incluir cláusulas de accesibilidad en los contratos con desarrolladores y proveedores tecnológicos.</li>
                        <li>Formar al personal técnico en materia de accesibilidad universal.</li>
                    </ul>
                </div>

                <div class="legal-action-container">
                    <a href="contacto.php" class="btn-legal btn-contacto">Contactar Soporte</a>
                    <a href="home.php" class="btn-legal btn-volver">Volver al Inicio</a>
                </div>
            </div>
        </section>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
