document.addEventListener('DOMContentLoaded', () => {

    const csrfToken = window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.content || '';

    // ── Hidratar resumen con datos de la reserva ──
    const storedBooking = localStorage.getItem('bookingData');

    const summarySport  = document.getElementById('summary-sport');
    const summaryDate   = document.getElementById('summary-date');
    const summaryTime   = document.getElementById('summary-time');
    const summaryBasePr = document.getElementById('summary-base-price');
    const feeLightRow   = document.getElementById('summary-light-row');
    const totalPriceEl  = document.getElementById('summary-total-price');

    if (storedBooking) {
        try {
            const data = JSON.parse(storedBooking);
            summarySport.textContent = data.sport || '--';
            summaryDate.textContent  = data.date  || '--/--';
            summaryTime.textContent  = data.time  || '--:-- - --:--';

            const supplement = data.supplement ? parseFloat(data.supplement) : 0;
            const basePrice  = data.basePrice ? parseFloat(data.basePrice) : 4.00;

            if (summaryBasePr) summaryBasePr.textContent = basePrice.toFixed(2).replace('.', ',') + ' €';

            if (supplement > 0) {
                if (feeLightRow) {
                    feeLightRow.classList.remove('hidden');
                    const valEl = feeLightRow.querySelector('.detail-value');
                    if (valEl) valEl.textContent = supplement.toFixed(2).replace('.', ',') + ' €';
                }
                if (totalPriceEl) totalPriceEl.textContent = (basePrice + supplement).toFixed(2).replace('.', ',') + ' €';
            } else {
                if (feeLightRow) feeLightRow.classList.add('hidden');
                if (totalPriceEl) totalPriceEl.textContent = basePrice.toFixed(2).replace('.', ',') + ' €';
            }

        } catch (e) { console.error('Error parseando bookingData'); }
    } else {
        console.warn('No hay datos de reserva en localStorage.');
    }

    // ── Formateo y Validación de Datos Personales ──
    const dniInput = document.getElementById('dni');
    if (dniInput) {
        dniInput.addEventListener('input', (e) => {
            e.target.value = e.target.value.toUpperCase().replace(/[^0-9A-Z]/g, '');
        });
    }

    const telInput = document.getElementById('telefono');
    if (telInput) {
        telInput.addEventListener('input', (e) => {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0, 9);
        });
    }

    // ── Formateo visual de inputs de tarjeta (solo para modo simulado) ──
    const cardNumber = document.getElementById('cardNumber');
    const expiry     = document.getElementById('expiry');

    if (cardNumber) {
        cardNumber.addEventListener('input', (e) => {
            const val = e.target.value.replace(/\D/g, '');
            e.target.value = val.replace(/(.{4})/g, '$1 ').trim();
        });
    }

    if (expiry) {
        expiry.addEventListener('input', (e) => {
            const val = e.target.value.replace(/\D/g, '');
            e.target.value = val.length > 2
                ? val.substring(0, 2) + '/' + val.substring(2, 4)
                : val;
        });
    }

    // ══════════════════════════════════════════════
    // ═══ SISTEMA DE BONOS EN CHECKOUT ═════════════
    // ══════════════════════════════════════════════
    const bonoWidget   = document.getElementById('bono-widget');
    const bonoToggle   = document.getElementById('use-bono-toggle');
    const bonoBadge    = document.getElementById('bono-count-badge');
    const submitBtn    = document.getElementById('submit-btn');
    const btnText      = submitBtn?.querySelector('.btn-text');
    let usarBono       = false;
    let bonosDisponibles = 0;

    // Texto original del botón
    const originalBtnText = btnText ? btnText.textContent : '';

    // Cargar bonos del usuario
    async function loadUserBonos() {
        try {
            const resp = await fetch('../api/bonos.php?action=get_user_bonos');
            const result = await resp.json();
            if (result.status === 'success' && result.total_usos_disponibles > 0) {
                bonosDisponibles = result.total_usos_disponibles;
                window.BONOS_DISPONIBLES = bonosDisponibles;
                if (bonoWidget) bonoWidget.style.display = 'block';
                if (bonoBadge) bonoBadge.textContent = bonosDisponibles + ' uso(s)';
            }
        } catch (err) {
            console.warn('No se pudieron cargar bonos:', err);
        }
    }
    loadUserBonos();

    // Toggle de bono
    if (bonoToggle) {
        bonoToggle.addEventListener('change', () => {
            usarBono = bonoToggle.checked;
            const widget = document.getElementById('bono-widget');
            
            if (usarBono) {
                widget.classList.add('active');
                if (btnText) btnText.textContent = '🎟️ Reservar con Bono (Gratis)';
                if (totalPriceEl) {
                    totalPriceEl.dataset.originalPrice = totalPriceEl.textContent;
                    totalPriceEl.textContent = '0,00 € (Bono)';
                    totalPriceEl.style.color = '#2e7d32';
                }
            } else {
                widget.classList.remove('active');
                if (btnText) btnText.textContent = originalBtnText;
                if (totalPriceEl && totalPriceEl.dataset.originalPrice) {
                    totalPriceEl.textContent = totalPriceEl.dataset.originalPrice;
                    totalPriceEl.style.color = '';
                }
            }
        });
    }

    // ── Envío de formulario ──
    const bookingForm  = document.getElementById('booking-form');
    const formView     = document.getElementById('checkout-form-view');
    const successView  = document.getElementById('checkout-success-view');
    const spinner      = document.getElementById('loading-spinner');
    const displayEmail = document.getElementById('display-email');
    const displayCode  = document.getElementById('display-code');

    if (bookingForm) {
        bookingForm.addEventListener('submit', (e) => {
            e.preventDefault();

            submitBtn.disabled = true;
            if (btnText) btnText.classList.add('hidden');
            if (spinner) spinner.classList.remove('hidden');

            const userEmail = document.getElementById('email').value;

            // Decidir flujo
            if (usarBono && bonosDisponibles > 0) {
                handleBonoReservation(userEmail);
            } else if (window.USE_STRIPE) {
                handleStripeCheckout(userEmail);
            } else {
                handleSimulatedPayment(userEmail);
            }
        });
    }

    // ── FLUJO BONO: Reserva sin pago ──
    async function handleBonoReservation(userEmail) {
        try {
            const bookingInfo = storedBooking ? JSON.parse(storedBooking) : {};

            const payload = {
                action: 'use_bono',
                email: userEmail,
                nombre: document.getElementById('nombre').value,
                dni: document.getElementById('dni').value,
                telefono: document.getElementById('telefono').value,
                pista: bookingInfo.sport || 'Pista Deportiva',
                fecha: bookingInfo.date || '',
                hora: bookingInfo.time || '',
            };

            payload.csrf_token = csrfToken;
            const response = await fetch('../api/bonos.php?action=use_bono', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (result.status === 'success' && result.codigo) {
                displayEmail.textContent = userEmail;
                displayCode.textContent = result.codigo;

                formView.classList.add('hidden');
                successView.classList.remove('hidden');

                localStorage.removeItem('bookingData');

                // Copiar al portapapeles
                setupCodeCopy(result.codigo);
            } else {
                alert("Error: " + (result.message || "No se pudo procesar la reserva con bono."));
                resetButton();
            }
        } catch (error) {
            console.error('Error bono:', error);
            alert('Error de conexión. Inténtalo de nuevo.');
            resetButton();
        }
    }

    // ── FLUJO STRIPE: Redirige a pasarela externa ──
    async function handleStripeCheckout(userEmail) {
        try {
            const bookingInfo = storedBooking ? JSON.parse(storedBooking) : {};
            const basePrice = bookingInfo.basePrice ? parseFloat(bookingInfo.basePrice) : 4.00;
            const supplement = bookingInfo.supplement ? parseFloat(bookingInfo.supplement) : 0;

            const payload = {
                email: userEmail,
                nombre: document.getElementById('nombre').value,
                dni: document.getElementById('dni').value,
                telefono: document.getElementById('telefono').value,
                pista: bookingInfo.sport || 'Pista Deportiva',
                fecha: bookingInfo.date || '',
                hora: bookingInfo.time || '',
                precio_total: (basePrice + supplement).toFixed(2)
            };

            payload.csrf_token = csrfToken;
            const response = await fetch('../api/stripe-checkout.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (result.status === 'success' && result.checkout_url) {
                // Redirigir a Stripe Checkout
                window.location.href = result.checkout_url;
            } else {
                alert("Error al iniciar el pago: " + (result.message || "Error desconocido."));
                resetButton();
            }
        } catch (error) {
            console.error('Error Stripe:', error);
            alert('Error de conexión con la pasarela de pago. Comprueba tu conexión e inténtalo de nuevo.');
            resetButton();
        }
    }

    // ── FLUJO SIMULADO: Pago ficticio + reserva directa (original) ──
    function handleSimulatedPayment(userEmail) {
        setTimeout(async () => {
            try {
                const bookingInfo = storedBooking ? JSON.parse(storedBooking) : {};
                const formData = new FormData();
                formData.append('email', userEmail);
                formData.append('nombre', document.getElementById('nombre').value);
                formData.append('dni', document.getElementById('dni').value);
                formData.append('telefono', document.getElementById('telefono').value);
                formData.append('pista', bookingInfo.sport || 'Pista Deportiva');
                formData.append('fecha', bookingInfo.date || 'Día seleccionado');
                formData.append('hora', bookingInfo.time || 'Hora seleccionada');

                const basePrice = bookingInfo.basePrice ? parseFloat(bookingInfo.basePrice) : 4.00;
                const supplement = bookingInfo.supplement ? parseFloat(bookingInfo.supplement) : 0;
                formData.append('precio_total', (basePrice + supplement).toFixed(2));
                formData.append('csrf_token', csrfToken);

                const response = await fetch('../api/enviar-correo.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrfToken },
                    credentials: 'same-origin',
                    body: formData
                });
                const result = await response.json();

                if (result.status === 'success' && result.codigo) {
                    displayEmail.textContent = userEmail;
                    displayCode.textContent = result.codigo;

                    formView.classList.add('hidden');
                    successView.classList.remove('hidden');

                    localStorage.removeItem('bookingData');

                    // Copiar al portapapeles
                    setupCodeCopy(result.codigo);
                } else {
                    alert("Error al procesar la reserva: " + (result.message || "La pista ya no está disponible."));
                    resetButton();
                }
            } catch (error) {
                console.error('Error detallado:', error);
                alert('Error de conexión con el servidor. Comprueba que el servidor esté encendido.');
                resetButton();
            }
        }, 1000);
    }

    // ── Copiar código al portapapeles (reutilizable) ──
    function setupCodeCopy(code) {
        const codeBox  = document.getElementById('code-box-copy');
        const copyIcon = document.getElementById('copy-icon');
        const copyText = document.getElementById('copy-text');

        if (codeBox) {
            codeBox.style.cursor = 'pointer';
            codeBox.addEventListener('click', () => {
                navigator.clipboard.writeText(code).then(() => {
                    if (copyIcon)  copyIcon.textContent  = '✅';
                    if (copyText)  copyText.textContent  = '¡Copiado!';
                    codeBox.classList.add('copied');
                    setTimeout(() => {
                        if (copyIcon) copyIcon.textContent = '📋';
                        if (copyText) copyText.textContent = 'Haz clic para copiar';
                        codeBox.classList.remove('copied');
                    }, 2000);
                }).catch(() => {
                    const range = document.createRange();
                    range.selectNodeContents(displayCode);
                    const sel = window.getSelection();
                    sel.removeAllRanges();
                    sel.addRange(range);
                });
            });
        }
    }

    // ── Restaurar botón tras error ──
    function resetButton() {
        submitBtn.disabled = false;
        if (btnText) {
            btnText.classList.remove('hidden');
            btnText.textContent = usarBono ? '🎟️ Reservar con Bono (Gratis)' : originalBtnText;
        }
        if (spinner) spinner.classList.add('hidden');
    }

    // ── Comprobar si viene de un pago cancelado ──
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('pago') === 'cancelado') {
        const alert = document.createElement('div');
        alert.style.cssText = 'background:#fff3e0;border:1px solid #ff9800;color:#e65100;padding:1rem;border-radius:8px;margin-bottom:1.5rem;text-align:center;font-weight:500;';
        alert.textContent = '⚠️ Has cancelado el proceso de pago. Puedes volver a intentarlo.';
        const form = document.getElementById('booking-form');
        if (form) form.parentNode.insertBefore(alert, form);
    }
});
