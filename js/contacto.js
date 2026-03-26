const { maxHeaderSize } = require("node:http");

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

        // Validaciones específicas
        if (input.value.trim() === '') {
            isError = true;
        } else if (input.type === 'email') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(input.value)) isError = true;
        } else if (input.type === 'tel') {
            const telRegex = /^[0-9+\s-]{9,}$/; // Al menos 9 caracteres, permitiendo '+', espacios y '-'
            if (!telRegex.test(input.value)) isError = true;
        }

        showError(input, isError);
        return !isError;
    };

    // Validar al perder el foco (onblur)
    inputs.forEach(input => {
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
    contactForm.addEventListener('submit', (e) => {
        e.preventDefault();
        let isFormValid = true;

        inputs.forEach(input => {
            if (!validateField(input)) {
                isFormValid = false;
            }
        });

        if (isFormValid) {
            // Simulamos el envío
            const btnSubmit = document.getElementById('btnSubmitContact');
            const originalText = btnSubmit.textContent;
            btnSubmit.textContent = 'Enviando...';
            btnSubmit.disabled = true;
            setTimeout(() => {
                alert('Mensaje enviado correctamente. Nos pondremos en contacto contigo pronto.');
                btnSubmit.textContent = originalText;
                btnSubmit.disabled = false;
                contactForm.reset();
                clearAllErrors();
            }, 1000);
        }
    });
});

