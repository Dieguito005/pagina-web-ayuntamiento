<?php
require_once __DIR__ . '/../config.php';
$policyVersion = defined('POLICY_VERSION') ? POLICY_VERSION : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Politica de cookies del portal de reservas deportivas del Ayuntamiento de Tarancon, conforme a la Guia AEPD 2023.">
    <link rel="preload" href="../assets/imagenes/LOGO-AYUNTAMIENTO-BLANCO.png" as="image">
    <link rel="stylesheet" href="../css/home.css">
    <link rel="stylesheet" href="../css/politica.css">
    <link rel="stylesheet" href="../css/cookies.css">
    <script src="../js/cookies.js" defer></script>
    <title>Politica de Cookies | Alquileres Tarancon</title>
    <style>
        .cookie-table { width: 100%; border-collapse: collapse; margin: 1.5rem 0; font-size: 0.9rem; }
        .cookie-table th, .cookie-table td { border: 1px solid #ddd; padding: 0.6rem; text-align: left; vertical-align: top; }
        .cookie-table th { background-color: #f4f6f8; font-weight: 600; }
        .cookie-table tbody tr:nth-child(even) { background-color: #fafafa; }
    </style>
</head>
<body>

    <?php include '../includes/header.php'; ?>

    <main id="contenido-principal" class="main-content legal-content">
        <section class="legal-section fade-in-up">
            <div class="legal-card cookie-card">
                <h2>Política de Cookies</h2>
                <p style="font-size:0.85rem; color:#777;">Versión <?= htmlspecialchars($policyVersion) ?> · Última revisión: <?= htmlspecialchars($policyVersion) ?></p>

                <div class="legal-text cookie-text">
                    <p>El portal <em>"Alquileres Tarancón"</em>, titularidad del Excelentísimo Ayuntamiento de Tarancón (NIF P1621500F), utiliza cookies propias estrictamente necesarias para el funcionamiento del servicio. Esta política se publica de conformidad con el artículo 22.2 de la Ley 34/2002 (LSSI-CE) y la Guía sobre el uso de las cookies de la Agencia Española de Protección de Datos (julio 2023).</p>

                    <h3>1. ¿Qué son las cookies?</h3>
                    <p>Las cookies son pequeños ficheros que se descargan en su dispositivo al acceder a determinadas páginas web. Permiten al sitio recordar información sobre la visita, como el idioma, las preferencias o la sesión iniciada.</p>

                    <h3>2. Cookies utilizadas en este portal</h3>
                    <table class="cookie-table" role="table" aria-label="Listado de cookies utilizadas">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Proveedor</th>
                                <th>Tipo</th>
                                <th>Finalidad</th>
                                <th>Duración</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>PHPSESSID</code></td>
                                <td>Propia (Ayuntamiento)</td>
                                <td>Técnica - esencial</td>
                                <td>Mantener la sesión iniciada del usuario o administrador.</td>
                                <td>Sesión (se elimina al cerrar el navegador o tras 30 min de inactividad).</td>
                            </tr>
                            <tr>
                                <td><code>cookie_consent</code></td>
                                <td>Propia (Ayuntamiento)</td>
                                <td>Técnica - esencial</td>
                                <td>Almacenar la decisión del usuario sobre el uso de cookies y la versión aceptada.</td>
                                <td>24 meses (renovación obligatoria por exigencia AEPD).</td>
                            </tr>
                            <tr>
                                <td><code>beta_access</code></td>
                                <td>Propia (Ayuntamiento)</td>
                                <td>Técnica - esencial</td>
                                <td>Acceso a la fase de pruebas internas durante el desarrollo.</td>
                                <td>30 días.</td>
                            </tr>
                            <tr>
                                <td>Cookies de Stripe (en el formulario de pago)</td>
                                <td>Stripe Payments Europe, Ltd.</td>
                                <td>Técnica - esencial para el pago</td>
                                <td>Procesar el pago de forma segura y prevenir el fraude. Cargadas únicamente al iniciar el checkout.</td>
                                <td>Variable, según política de Stripe.</td>
                            </tr>
                        </tbody>
                    </table>

                    <p>Actualmente <strong>NO</strong> se utilizan cookies de análisis (Google Analytics, Matomo, etc.), de publicidad ni de redes sociales. Si en el futuro se incorporan, se actualizará esta política y se solicitará un nuevo consentimiento.</p>

                    <h3>3. Base jurídica</h3>
                    <p>Las cookies técnicas estrictamente necesarias se instalan al amparo del art. 22.2 LSSI-CE, que las exime del deber de consentimiento previo. Cualquier cookie no esencial requerirá consentimiento previo, expreso y granular del usuario.</p>

                    <h3>4. Gestión y revocación del consentimiento</h3>
                    <p>Puede aceptar, rechazar o configurar las cookies en cualquier momento desde el banner de cookies que aparece al inicio o pulsando el botón siguiente:</p>
                    <div class="legal-action-container">
                        <button type="button" class="btn-legal btn-cookies">Configurar Cookies</button>
                    </div>
                    <p>Adicionalmente, puede gestionar el uso de cookies desde la configuración de su navegador:</p>
                    <ul class="cookie-browsers">
                        <li><strong>Google Chrome:</strong> Configuración &gt; Privacidad y seguridad &gt; Cookies.</li>
                        <li><strong>Mozilla Firefox:</strong> Opciones &gt; Privacidad y Seguridad &gt; Cookies.</li>
                        <li><strong>Safari:</strong> Preferencias &gt; Privacidad.</li>
                        <li><strong>Microsoft Edge:</strong> Configuración &gt; Cookies y permisos del sitio.</li>
                    </ul>

                    <h3>5. Conservación del consentimiento</h3>
                    <p>De acuerdo con la Guía AEPD, el consentimiento se conserva durante un máximo de 24 meses. Pasado este plazo, se solicita nuevamente.</p>

                    <h3>6. Cambios en la política de cookies</h3>
                    <p>Esta política puede actualizarse para adaptarse a cambios normativos o a la incorporación de nuevas cookies. Se notificará a los usuarios cualquier cambio sustancial.</p>

                    <h3>7. Contacto</h3>
                    <p>Cualquier duda sobre el tratamiento de datos puede dirigirse al Delegado de Protección de Datos: <strong>dpd@tarancon.es</strong>.</p>
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
