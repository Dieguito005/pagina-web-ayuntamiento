document.addEventListener('DOMContentLoaded', () => {

    // ── Hidratar resumen con datos de la reserva ──
    const storedBooking = localStorage.getItem('bookingData');

    const summarySport  = document.getElementById('summary-sport');
    const summaryDate   = document.getElementById('summary-date');
    const summaryTime   = document.getElementById('summary-time');
    const feeLightRow   = document.getElementById('summary-light-row');
    const totalPriceEl  = document.getElementById('summary-total-price');

    if (storedBooking) {
        try {
            const data = JSON.parse(storedBooking);
            summarySport.textContent = data.sport || '--';
            summaryDate.textContent  = data.date  || '--/--';
            summaryTime.textContent  = data.time  || '--:-- - --:--';

            const supplement = data.supplement ? parseFloat(data.supplement) : 0;
            const basePrice  = 4.00;

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
            // Convertir a mayúsculas y remover caracteres no válidos
            e.target.value = e.target.value.toUpperCase().replace(/[^0-9A-Z]/g, '');
        });
    }

    const telInput = document.getElementById('telefono');
    if (telInput) {
        telInput.addEventListener('input', (e) => {
            // Solo dígitos, máximo 9
            e.target.value = e.target.value.replace(/\D/g, '').substring(0, 9);
        });
    }

    // ── Formateo visual de inputs de tarjeta ──
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

    // ── Envío de formulario (pago simulado) ──
    const bookingForm  = document.getElementById('booking-form');
    const formView     = document.getElementById('checkout-form-view');
    const successView  = document.getElementById('checkout-success-view');
    const submitBtn    = document.getElementById('submit-btn');
    const btnText      = submitBtn.querySelector('.btn-text');
    const spinner      = document.getElementById('loading-spinner');
    const displayEmail = document.getElementById('display-email');
    const displayCode  = document.getElementById('display-code');

    function generateAccessCode() {
        return "Cargando...";
    }

    if (bookingForm) {
        bookingForm.addEventListener('submit', (e) => {
            e.preventDefault();

            submitBtn.disabled = true;
            btnText.classList.add('hidden');
            spinner.classList.remove('hidden');

            const userEmail = document.getElementById('email').value;

            setTimeout(async () => {
                // Envío de correo y reserva vía PHP
                try {
                    const bookingInfo = storedBooking ? JSON.parse(storedBooking) : {};
                    const formData = new FormData();
                    formData.append('email', userEmail);
                    formData.append('nombre', document.getElementById('nombre').value);
                    formData.append('pista', bookingInfo.sport || 'Pista Deportiva');
                    formData.append('fecha', bookingInfo.date || 'Día seleccionado');
                    formData.append('hora', bookingInfo.time || 'Hora seleccionada');

                    const response = await fetch('../api/enviar-correo.php', { method: 'POST', body: formData });
                    const result = await response.json();

                    if (response.ok && result.status === 'success') {
                        displayEmail.textContent = userEmail;
                        displayCode.textContent = result.codigo; // El código alfanumérico generado en PHP

                        formView.classList.add('hidden');
                        successView.classList.remove('hidden');

                        localStorage.removeItem('bookingData');
                    } else if (response.status === 500 && result.codigo) {
                        // Respondió 500 por fallo de SMTP pero guardó la reserva
                        displayEmail.textContent = "Error SMTP (Correo no enviado pero Reserva Confirmada)";
                        displayCode.textContent = result.codigo;

                        formView.classList.add('hidden');
                        successView.classList.remove('hidden');

                        localStorage.removeItem('bookingData');
                    } else {
                        alert("Error al procesar la reserva: " + (result.message || "La pista ya no está disponible."));
                        submitBtn.disabled = false;
                        btnText.classList.remove('hidden');
                        spinner.classList.add('hidden');
                    }
                } catch (error) {
                    alert('Error de conexión con el servidor.');
                    console.error(error);
                    submitBtn.disabled = false;
                    btnText.classList.remove('hidden');
                    spinner.classList.add('hidden');
                }
            }, 1000);
        });
    }
});

