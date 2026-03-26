<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Finaliza tu reserva de instalaciones deportivas del Ayuntamiento de TarancÃ³n.">
    <link rel="preload" href="../assets/imagenes/LOGO-AYUNTAMIENTO-BLANCO.png" as="image">
    <link rel="stylesheet" href="../css/home.css?v=1.5.0">
    <link rel="stylesheet" href="../css/checkout.css?v=1.5.0">
    <link rel="stylesheet" href="../css/cookies.css?v=1.5.0">
    <script src="../js/cookies.js" defer></script>
    <script src="../js/checkout.js?v=1.5.0" defer></script>
    <title>Reserva y Pago - Alquileres TarancÃ³n</title>
</head>
<body>

    <?php include '../includes/header.php'; ?>

    <main class="main-content checkout-content">
        <section class="checkout-layout">
            
            <!-- CONTENIDO PRINCIPAL: FORMULARIO Y PAGO -->
            <div class="checkout-form-container fade-in-up" id="checkout-form-view">
                <h2>Finalizar Reserva</h2>
                <p class="checkout-subtitle">Por favor, completa tus datos para generar el cÃ³digo de acceso a la pista.</p>
                
                <a href="home.php" class="btn-secondary" style="margin-bottom: 2rem; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none;">
                    Volver al Inicio
                </a>

                <!-- AVISO LEGAL DE CANCELACIÃ“N -->
                <div class="cancellation-alert">
                    <div class="alert-icon">âš ï¸</div>
                    <div class="alert-text">
                        <strong>AtenciÃ³n:</strong> El lÃ­mite de cancelaciÃ³n de la reserva es hasta <strong>media hora antes</strong> de la misma. Si se rebasa dicho lÃ­mite, la reserva se bloquea y el importe no serÃ¡ devuelto.
                    </div>
                </div>

                <form id="booking-form" class="booking-form">
                    
                    <!-- DATOS PERSONALES -->
                    <fieldset class="form-section">
                        <legend>Datos Personales</legend>
                        <div class="form-row">
                            <div class="form-group half">
                                <label for="nombre">Nombre *</label>
                                <input type="text" id="nombre" name="nombre" required placeholder="Ej: Juan">
                            </div>
                            <div class="form-group half">
                                <label for="apellidos">Apellidos *</label>
                                <input type="text" id="apellidos" name="apellidos" required placeholder="Ej: PÃ©rez GarcÃ­a">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group half">
                                <label for="dni">DNI / NIE *</label>
                                <input type="text" id="dni" name="dni" required placeholder="12345678A" pattern="^[0-9]{8}[TRWAGMYFPDXBNJZSQVHLCKE]$|^[XYZ][0-9]{7}[TRWAGMYFPDXBNJZSQVHLCKE]$" title="Introduce un DNI o NIE vÃ¡lido">
                            </div>
                            <div class="form-group half">
                                <label for="telefono">TelÃ©fono *</label>
                                <input type="tel" id="telefono" name="telefono" required placeholder="Ej: 600123456" pattern="[0-9]{9}" title="Introduce un nÃºmero de 9 cifras">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="email">Correo ElectrÃ³nico *</label>
                            <input type="email" id="email" name="email" required placeholder="juan.perez@email.com">
                            <small class="help-text">Te enviaremos a este correo tu cÃ³digo de entrada automÃ¡tico.</small>
                        </div>
                    </fieldset>

                    <!-- DATOS DE TARJETA -->
                    <fieldset class="form-section payment-section">
                        <legend>Datos de Pago</legend>
                        <p class="payment-disclaimer">Cobro seguro. Formulario simulado para pruebas.</p>
                        <div class="form-group">
                            <label for="cardName">Titular de la tarjeta *</label>
                            <input type="text" id="cardName" name="cardName" required placeholder="Juan PÃ©rez">
                        </div>
                        <div class="form-group">
                            <label for="cardNumber">NÃºmero de tarjeta *</label>
                            <div class="card-input-wrapper">
                                <input type="text" id="cardNumber" name="cardNumber" required placeholder="0000 0000 0000 0000" maxlength="19" pattern="\d{4}\s?\d{4}\s?\d{4}\s?\d{4}">
                                <span class="card-icon">ðŸ’³</span>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group half">
                                <label for="expiry">Caducidad (MM/AA) *</label>
                                <input type="text" id="expiry" name="expiry" required placeholder="MM/AA" maxlength="5" pattern="(0[1-9]|1[0-2])\/\d{2}">
                            </div>
                            <div class="form-group half">
                                <label for="cvv">CVC/CVV *</label>
                                <input type="password" id="cvv" name="cvv" required placeholder="123" maxlength="3" pattern="\d{3}">
                            </div>
                        </div>
                    </fieldset>
                    
                    <!-- PolÃ­ticas -->
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="accept-policies" name="accept-policies" required>
                        <label for="accept-policies">He leÃ­do y acepto la <a href="politica-privacidad.php" target="_blank">PolÃ­tica de Privacidad</a> y las condiciones de alquiler del Ayuntamiento de TarancÃ³n.</label>
                    </div>

                    <button type="submit" class="btn-primary btn-submit-form" id="submit-btn">
                        <span class="btn-text">Pagar y Generar Reserva</span>
                        <div class="spinner hidden" id="loading-spinner"></div>
                    </button>
                    <p class="error-msg hidden" id="form-error-msg">Por favor, rellena los campos correctamente.</p>
                </form>
            </div>

            <!-- VISTA DE CONFIRMACIÃ“N / CÃ“DIGO (Oculta por defecto) -->
            <div class="checkout-success-container hidden" id="checkout-success-view">
                <div class="success-card">
                    <div class="success-icon-wrap">
                        <div class="success-icon">âœ“</div>
                    </div>
                    <h2>Â¡Reserva Confirmada!</h2>
                    <p>El pago se ha realizado con Ã©xito.</p>
                    <p>Acabamos de enviarle a su correo electrÃ³nico <strong><span id="display-email"></span></strong> los detalles de la reserva junto con este cÃ³digo de entrada.</p>
                    
                    <div class="code-box">
                        <p class="code-label">CÃ³digo de Acceso del Conserje</p>
                        <p class="access-code" id="display-code">------</p>
                    </div>

                    <div class="success-instructions">
                        <p><strong>Â¿QuÃ© debo hacer ahora?</strong></p>
                        <ul>
                            <li>Guarde este cÃ³digo alfanumÃ©rico.</li>
                            <li>Acuda a las instalaciones elegidas unos minutos antes de la reserva.</li>
                            <li>EnseÃ±e o dicte este cÃ³digo al conserje de la instalaciÃ³n para que le permita entrar a la pista.</li>
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
                            <span class="detail-label">InstalaciÃ³n:</span>
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
                            <span class="detail-value" id="summary-base-price">4,00 â‚¬</span>
                        </div>
                        <div class="detail-row hidden" id="summary-light-row">
                            <span class="detail-label" style="color:#d32f2f;">Suplemento Luz (Noche):</span>
                            <span class="detail-value">1,00 â‚¬</span>
                        </div>
                        <div class="detail-row total-row" style="margin-top: 8px; border-top: 1px dashed #ccc; padding-top: 8px;">
                            <span class="detail-label">Total a Pagar:</span>
                            <span class="detail-value total-price" id="summary-total-price">4,00 â‚¬</span>
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



