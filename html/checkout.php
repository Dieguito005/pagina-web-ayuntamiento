<?php require_once '../includes/user-auth-check.php'; ?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Finaliza tu reserva de instalaciones deportivas del Ayuntamiento de Tarancón.">
    <link rel="preload" href="../assets/imagenes/LOGO-AYUNTAMIENTO-BLANCO.png" as="image">
    <link rel="stylesheet" href="../css/home.css">
    <link rel="stylesheet" href="../css/checkout.css">
    <link rel="stylesheet" href="../css/cookies.css">
    <script src="../js/cookies.js" defer></script>
    <script>
        // Config inyectada desde PHP — el JS decide qué flujo usar
        window.USE_STRIPE = <?= defined('USE_STRIPE') && USE_STRIPE ? 'true' : 'false' ?>;
    </script>
    <script src="../js/checkout.js?v=2.3.0" defer></script>
    <title>Reserva y Pago - Alquileres Tarancón</title>
    <style>
        /* Hint de copiar código */
        .code-copy-hint {
            margin-top: 0.5rem;
            font-size: 0.82rem;
            color: #888;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            transition: color 0.2s;
            user-select: none;
        }
        #code-box-copy {
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s, background 0.2s;
        }
        #code-box-copy:hover {
            transform: scale(1.02);
            box-shadow: 0 6px 24px rgba(209, 86, 21, 0.18);
        }
        #code-box-copy:hover .code-copy-hint {
            color: #d15615;
        }
        #code-box-copy.copied {
            background: #f0fff4;
            border-color: #48bb78;
        }
        #code-box-copy.copied .code-copy-hint {
            color: #276749;
        }
        /* El texto del código sigue siendo seleccionable manualmente */
        .access-code {
            user-select: text;
            cursor: text;
        }
    </style>
</head>

<body>

    <?php include '../includes/header.php'; ?>

    <main class="main-content checkout-content">
        <section class="checkout-layout">

            <!-- CONTENIDO PRINCIPAL: FORMULARIO Y PAGO -->
            <div class="checkout-form-container fade-in-up" id="checkout-form-view">
                <h2>Finalizar Reserva</h2>
                <p class="checkout-subtitle">Por favor, completa tus datos para generar el código de acceso a la pista.
                </p>

                <a href="home.php" class="btn-secondary"
                    style="margin-bottom: 2rem; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none;">
                    Volver al Inicio
                </a>

                <!-- AVISO LEGAL DE CANCELACIÓN -->
                <div class="cancellation-alert">
                    <div class="alert-icon">⚠️</div>
                    <div class="alert-text">
                        <strong>Atención:</strong> El límite de cancelación de la reserva es hasta <strong>media hora
                            antes</strong> de la misma. Si se rebasa dicho límite, la reserva se bloquea y el importe no
                        será devuelto.
                    </div>
                </div>

                <form id="booking-form" class="booking-form">

                    <!-- DATOS PERSONALES -->
                    <fieldset class="form-section">
                        <legend>Datos Personales</legend>
                        <div class="form-row">
                            <div class="form-group half">
                                <label for="nombre">Nombre *</label>
                                <input type="text" id="nombre" name="nombre" required placeholder="Ej: Juan"
                                    value="<?= htmlspecialchars($_SESSION['user_nombre'] ?? '') ?>">
                            </div>
                            <div class="form-group half">
                                <label for="apellidos">Apellidos *</label>
                                <input type="text" id="apellidos" name="apellidos" required
                                    placeholder="Ej: Pérez García"
                                    value="<?= htmlspecialchars($_SESSION['user_apellidos'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group half">
                                <label for="dni">DNI / NIE *</label>
                                <input type="text" id="dni" name="dni" required placeholder="12345678A"
                                    pattern="^[0-9]{8}[TRWAGMYFPDXBNJZSQVHLCKE]$|^[XYZ][0-9]{7}[TRWAGMYFPDXBNJZSQVHLCKE]$"
                                    title="Introduce un DNI o NIE válido"
                                    value="<?= htmlspecialchars($_SESSION['user_dni'] ?? '') ?>">
                            </div>
                            <div class="form-group half">
                                <label for="telefono">Teléfono *</label>
                                <input type="tel" id="telefono" name="telefono" required placeholder="Ej: 600123456"
                                    pattern="[0-9]{9}" title="Introduce un número de 9 cifras"
                                    value="<?= htmlspecialchars($_SESSION['user_telefono'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="email">Correo Electrónico *</label>
                            <input type="email" id="email" name="email" required placeholder="juan.perez@email.com"
                                value="<?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>">
                            <small class="help-text">Te enviaremos a este correo tu código de entrada
                                automático.</small>
                        </div>
                    </fieldset>

                    <!-- DATOS DE TARJETA (solo si NO hay Stripe) -->
                    <?php if (!defined('USE_STRIPE') || !USE_STRIPE): ?>
                    <fieldset class="form-section payment-section">
                        <legend>Datos de Pago</legend>
                        <p class="payment-disclaimer">Cobro seguro. Formulario simulado para pruebas.</p>
                        <div class="form-group">
                            <label for="cardName">Titular de la tarjeta *</label>
                            <input type="text" id="cardName" name="cardName" required placeholder="Juan Pérez">
                        </div>
                        <div class="form-group">
                            <label for="cardNumber">Número de tarjeta *</label>
                            <div class="card-input-wrapper">
                                <input type="text" id="cardNumber" name="cardNumber" required
                                    placeholder="0000 0000 0000 0000" maxlength="19"
                                    pattern="\d{4}\s?\d{4}\s?\d{4}\s?\d{4}">
                                <span class="card-icon">💳</span>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group half">
                                <label for="expiry">Caducidad (MM/AA) *</label>
                                <input type="text" id="expiry" name="expiry" required placeholder="MM/AA" maxlength="5"
                                    pattern="(0[1-9]|1[0-2])\/\d{2}">
                            </div>
                            <div class="form-group half">
                                <label for="cvv">CVC/CVV *</label>
                                <input type="password" id="cvv" name="cvv" required placeholder="123" maxlength="3"
                                    pattern="\d{3}">
                            </div>
                        </div>
                    </fieldset>
                    <?php else: ?>
                    <fieldset class="form-section payment-section">
                        <legend>💳 Pago Seguro</legend>
                        <div style="text-align:center; padding: 1rem; background: #f0f7ff; border-radius: 8px; border: 1px solid #bdd4f0;">
                            <p style="margin:0; font-size:1rem; color:#1e3a5f;">🔒 Al pulsar el botón serás redirigido a la <strong>pasarela segura de Stripe</strong> para completar el pago con tarjeta.</p>
                            <p style="margin:0.5rem 0 0; font-size:0.85rem; color:#666;">Tus datos de tarjeta nunca pasan por nuestro servidor.</p>
                        </div>
                    </fieldset>
                    <?php endif; ?>

                    <!-- Políticas -->
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="accept-policies" name="accept-policies" required>
                        <label for="accept-policies">He leído y acepto la <a href="politica-privacidad.php"
                                target="_blank">Política de Privacidad</a> y las condiciones de alquiler del
                            Ayuntamiento de Tarancón.</label>
                    </div>

                    <button type="submit" class="btn-primary btn-submit-form" id="submit-btn">
                        <span class="btn-text"><?= (defined('USE_STRIPE') && USE_STRIPE) ? '💳 Pagar con Stripe' : 'Pagar y Generar Reserva' ?></span>
                        <div class="spinner hidden" id="loading-spinner"></div>
                    </button>
                    <p class="error-msg hidden" id="form-error-msg">Por favor, rellena los campos correctamente.</p>
                </form>
            </div>

            <!-- VISTA DE CONFIRMACIÓN / CÓDIGO (Oculta por defecto) -->
            <div class="checkout-success-container hidden" id="checkout-success-view">
                <div class="success-card">
                    <div class="success-icon-wrap">
                        <div class="success-icon">✓</div>
                    </div>
                    <h2>¡Reserva Confirmada!</h2>
                    <p>El pago se ha realizado con éxito.</p>
                    <p>Acabamos de enviarle a su correo electrónico <strong><span id="display-email"></span></strong>
                        los detalles de la reserva junto con este código de entrada.</p>

                    <div class="code-box" id="code-box-copy" title="Haz clic para copiar el código">
                        <p class="code-label">Código de Acceso</p>
                        <p class="access-code" id="display-code">------</p>
                        <p class="code-copy-hint" id="code-copy-hint">
                            <span id="copy-icon">📋</span>
                            <span id="copy-text">Haz clic para copiar</span>
                        </p>
                    </div>

                    <div class="success-instructions">
                        <p><strong>¿Qué debo hacer ahora?</strong></p>
                        <ul>
                            <li>Guarde este código alfanumérico.</li>
                            <li>Acuda a las instalaciones elegidas unos minutos antes de la reserva.</li>
                            <li>Enseñe o dicte este código al conserje de la instalación para que le permita entrar a la
                                pista.</li>
                        </ul>
                    </div>

                    <a href="home.php" class="btn-primary btn-back-home">Volver al Inicio</a>
                </div>
            </div>

            <!-- BARRA LATERAL: RESUMEN DE RESERVA -->
            <aside class="checkout-sidebar fade-in-up delay-1">
                <div class="summary-card">
                    <h3>Resumen de Reserva</h3>
                    <div class="summary-details">
                        <div class="detail-row">
                            <span class="detail-label">Instalación:</span>
                            <span class="detail-value" id="summary-sport">--</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Fecha:</span>
                            <span class="detail-value" id="summary-date">--/--</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Horario:</span>
                            <span class="detail-value" id="summary-time">--:-- - --:--</span>
                        </div>
                        <div class="detail-divider"></div>
                        <div class="detail-row" id="summary-base-price-row">
                            <span class="detail-label">Base Pista:</span>
                            <span class="detail-value" id="summary-base-price">4,00 €</span>
                        </div>
                        <div class="detail-row hidden" id="summary-light-row">
                            <span class="detail-label" style="color:#d32f2f;">Suplemento Luz (Noche):</span>
                            <span class="detail-value">1,00 €</span>
                        </div>
                        <div class="detail-row total-row"
                            style="margin-top: 8px; border-top: 1px dashed #ccc; padding-top: 8px;">
                            <span class="detail-label">Total a Pagar:</span>
                            <span class="detail-value total-price" id="summary-total-price">4,00 €</span>
                        </div>
                    </div>
                </div>
            </aside>

        </section>
    </main>

    <!-- FOOTER PRINCIPAL -->
    <?php include '../includes/footer.php'; ?>

</body>

</html>