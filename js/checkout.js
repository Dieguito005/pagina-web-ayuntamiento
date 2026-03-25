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
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        let code = '';
        for (let i = 0; i < 6; i++) {
            code += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return `TRC-${code}`;
    }

    if (bookingForm) {
        bookingForm.addEventListener('submit', (e) => {
            e.preventDefault();

            submitBtn.disabled = true;
            btnText.classList.add('hidden');
            spinner.classList.remove('hidden');

            const userEmail = document.getElementById('email').value;

            setTimeout(async () => {
                const accessCode = generateAccessCode();

                // Envío de correo vía PHP
                try {
                    const bookingInfo = storedBooking ? JSON.parse(storedBooking) : {};
                    const formData = new FormData();
                    formData.append('email', userEmail);
                    formData.append('nombre', document.getElementById('nombre').value);
                    formData.append('codigo', accessCode);
                    formData.append('pista', bookingInfo.sport || 'Pista Deportiva');
                    formData.append('fecha', bookingInfo.date || 'Día seleccionado');
                    formData.append('hora', bookingInfo.time || 'Hora seleccionada');

                    await fetch('../api/enviar-correo.php', { method: 'POST', body: formData });
                } catch (error) {
                    console.warn('El correo no pudo enviarse (requiere servidor PHP).', error);
                }

                displayEmail.textContent = userEmail;
                displayCode.textContent = accessCode;

                formView.classList.add('hidden');
                successView.classList.remove('hidden');

                localStorage.removeItem('bookingData');
            }, 2000);
        });
    }
});

