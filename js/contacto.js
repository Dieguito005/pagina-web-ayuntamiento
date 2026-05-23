document.addEventListener('DOMContentLoaded', () => {
    // 1. Referencias a elementos
    const contactForm = document.getElementById('contactForm');
    
    // Si no estamos en la página de contacto (o no existe el formulario), salir para no dar error
    if (!contactForm) return;

    const btnReset = document.getElementById('btnResetContact');
    const inputs = contactForm.querySelectorAll('input, select, textarea');

    // 2. Lógica de Validaciones
    const showError = (input, show) => {
        const errorMsg = input.nextElementSibling;
        if (!errorMsg || !errorMsg.classList.contains('error-msg')) return;

        if (show) {
            input.classList.add('input-error');
            errorMsg.classList.add('visible');
        } else {
            input.classList.remove('input-error');
            errorMsg.classList.remove('visible');
        }
    };

    const validateField = (input) => {
        let isError = false;

        // Skip honeypot
        if (input.id === 'honeypot') return true;

        const value = input.value.trim();
        const isRequired = input.hasAttribute('required');

        // Campos opcionales en blanco: válido
        if (!isRequired && value === '') {
            showError(input, false);
            return true;
        }

        if (isRequired && value === '') {
            isError = true;
        } else if (input.type === 'email') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) isError = true;
        } else if (input.type === 'tel') {
            const telRegex = /^[0-9+\s-]{9,}$/;
            if (!telRegex.test(value)) isError = true;
        } else if (input.name === 'dni') {
            const dniRegex = /^[0-9]{8}[A-Z]$/i;
            const nieRegex = /^[XYZ][0-9]{7}[A-Z]$/i;
            if (!dniRegex.test(value) && !nieRegex.test(value)) isError = true;
        }

        showError(input, isError);
        input.setAttribute('aria-invalid', isError ? 'true' : 'false');
        return !isError;
    };

    // Validar al perder el foco (onblur)
    inputs.forEach(input => {
        if (input.id === 'honeypot') return;
        input.addEventListener('blur', () => {
            validateField(input);
        });
        
        // Limpiar error al empezar a escribir
        input.addEventListener('input', () => {
            if (input.classList.contains('input-error')) {
                showError(input, false);
            }
        });
    });

    // Limpiar todos los errores al restablecer
    const clearAllErrors = () => {
        inputs.forEach(input => {
            if (input.id === 'honeypot') return;
            showError(input, false);
        });
    };

    if (btnReset) {
        btnReset.addEventListener('click', () => {
            // Un pequeño timeout para asegurar que el input se vacía antes de quitar las clases
            setTimeout(clearAllErrors, 10);
        });
    }

    // Validar al enviar
    contactForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        let isFormValid = true;

        inputs.forEach(input => {
            if (!validateField(input)) {
                isFormValid = false;
            }
        });

        // Honeypot check prevent submission entirely if filled out
        const honeypot = document.getElementById('honeypot');
        if (honeypot && honeypot.value.trim() !== '') {
            // Silently pretend it worked for bots
            contactForm.reset();
            return;
        }

        if (isFormValid) {
            const btnSubmit = document.getElementById('btnSubmitContact');
            const originalText = btnSubmit.textContent;
            btnSubmit.textContent = 'Enviando...';
            btnSubmit.disabled = true;
            
            try {
                const csrfToken = window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.content || '';
                const formData = new FormData(contactForm);
                if (!formData.has('csrf_token')) formData.append('csrf_token', csrfToken);
                const response = await fetch('../api/guardar-consulta.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrfToken },
                    credentials: 'same-origin',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Mensaje enviado correctamente. Nos pondremos en contacto contigo pronto.');
                    contactForm.reset();
                    clearAllErrors();
                } else {
                    alert('Error: ' + (result.message || 'Ha ocurrido un problema al enviar el mensaje.'));
                }
                
            } catch (error) {
                console.error("Error submitting contact form:", error);
                alert('Error de conexión al enviar el mensaje. Inténtelo más tarde.');
            } finally {
                btnSubmit.textContent = originalText;
                btnSubmit.disabled = false;
            }
        }
    });
});

