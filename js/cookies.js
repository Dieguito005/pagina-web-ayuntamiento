document.addEventListener('DOMContentLoaded', () => {
    // 1. Inyectar HTML del Banner y del Modal de Cookies
    const cookieHTML = `
        <!-- Banner inferior de Cookies -->
        <div id="cookie-banner" class="cookie-banner hidden">
            <div class="cookie-banner-content">
                <p>Utilizamos cookies propias y de terceros para mejorar nuestros servicios y mostrarle publicidad relacionada con sus preferencias mediante el análisis de sus hábitos de navegación. Puede obtener más información, o bien conocer cómo cambiar la configuración, en nuestra <a href="politica-cookies.html">Política de Cookies</a>.</p>
                <div class="cookie-banner-actions">
                    <button id="btn-accept-cookies" class="btn-cookie btn-cookie-primary">Aceptar todas</button>
                    <button id="btn-reject-cookies" class="btn-cookie btn-cookie-secondary">Rechazar</button>
                    <button id="btn-config-cookies" class="btn-cookie btn-cookie-outline">Configurar</button>
                </div>
            </div>
        </div>

        <!-- Modal de Configuración de Cookies -->
        <div id="cookie-modal-overlay" class="cookie-modal-overlay hidden">
            <div class="cookie-modal">
                <div class="cookie-modal-header">
                    <h3>Configuración de Cookies</h3>
                    <button id="btn-close-cookie-modal" class="btn-close-modal" aria-label="Cerrar">&times;</button>
                </div>
                <div class="cookie-modal-body">
                    <p>Seleccione las cookies que desea aceptar. Las cookies técnicas son estrictamente necesarias para el funcionamiento del portal y no pueden desactivarse.</p>
                    <div class="cookie-option">
                        <div class="cookie-option-info">
                            <h4>Cookies Técnicas (Estrictamente necesarias)</h4>
                            <p>Permiten al usuario la navegación a través de la web y el uso de las diferentes opciones como el sistema de reservas y pagos.</p>
                        </div>
                        <div class="cookie-option-switch">
                            <input type="checkbox" id="cookie-tech" checked disabled>
                            <label for="cookie-tech" class="switch-label"></label>
                        </div>
                    </div>
                    <div class="cookie-option">
                        <div class="cookie-option-info">
                            <h4>Cookies de Análisis o Medición</h4>
                            <p>Nos permiten medir la audiencia y realizar el análisis del comportamiento de los usuarios para introducir mejoras.</p>
                        </div>
                        <div class="cookie-option-switch">
                            <input type="checkbox" id="cookie-analytics">
                            <label for="cookie-analytics" class="switch-label"></label>
                        </div>
                    </div>
                </div>
                <div class="cookie-modal-footer">
                    <button id="btn-save-cookie-config" class="btn-cookie btn-cookie-primary">Guardar Configuración</button>
                </div>
            </div>
        </div>
    `;

    // Añadir al body si no existen
    if (!document.getElementById('cookie-banner')) {
        document.body.insertAdjacentHTML('beforeend', cookieHTML);
    }

    // 2. Referencias a Elementos del DOM
    const cookieBanner = document.getElementById('cookie-banner');
    const cookieModalOverlay = document.getElementById('cookie-modal-overlay');
    
    const btnAccept = document.getElementById('btn-accept-cookies');
    const btnReject = document.getElementById('btn-reject-cookies');
    const btnConfig = document.getElementById('btn-config-cookies');
    
    const btnCloseModal = document.getElementById('btn-close-cookie-modal');
    const btnSaveConfig = document.getElementById('btn-save-cookie-config');

    const cbAnalytics = document.getElementById('cookie-analytics');

    // Botones con clase .btn-cookies en el resto de la web para abrir la config (ej. en Política de Cookies)
    const btnCookiesLinks = document.querySelectorAll('.btn-cookies');

    // 3. Lógica para mostrar Banner inicial
    const cookieConsent = localStorage.getItem('cookieConsent');
    
    if (!cookieConsent) {
        // Si no hay consentimiento, mostrar banner (con un pequeño retardo para que actúe la transición CSS)
        cookieBanner.classList.remove('hidden');
        setTimeout(() => {
            cookieBanner.classList.add('show');
        }, 100);
    }

    // --- Funciones de Mostrar Ocultar ---
    function hideBanner() {
        cookieBanner.classList.remove('show');
        setTimeout(() => {
            cookieBanner.classList.add('hidden');
        }, 500); // Esperar que termine la transición
    }

    function showModal(e) {
        if (e) e.preventDefault();
        
        // Cargar preferencias guardadas en los switches
        const consent = localStorage.getItem('cookieConsent');
        if (consent === 'custom' || consent === 'all') {
            const preferences = JSON.parse(localStorage.getItem('cookiePreferences') || '{}');
            cbAnalytics.checked = preferences.analytics === true;
        } else if (consent === 'rejected') {
            cbAnalytics.checked = false;
        } else {
            // Predeterminado si no han elegido: desactivado
            cbAnalytics.checked = false;
        }

        // Mostrar Modal
        cookieModalOverlay.classList.remove('hidden');
        // Forzar reflow para aplicar transición
        void cookieModalOverlay.offsetWidth;
        cookieModalOverlay.classList.add('show');
    }

    function hideModal() {
        cookieModalOverlay.classList.remove('show');
        setTimeout(() => {
            cookieModalOverlay.classList.add('hidden');
        }, 300);
    }

    // 4. Event Listeners
    btnAccept.addEventListener('click', () => {
        localStorage.setItem('cookieConsent', 'all');
        localStorage.setItem('cookiePreferences', JSON.stringify({ analytics: true }));
        hideBanner();
    });

    btnReject.addEventListener('click', () => {
        localStorage.setItem('cookieConsent', 'rejected');
        localStorage.setItem('cookiePreferences', JSON.stringify({ analytics: false }));
        hideBanner();
    });

    btnConfig.addEventListener('click', showModal);
    
    btnCloseModal.addEventListener('click', hideModal);
    
    btnSaveConfig.addEventListener('click', () => {
        localStorage.setItem('cookieConsent', 'custom');
        localStorage.setItem('cookiePreferences', JSON.stringify({
            analytics: cbAnalytics.checked
        }));
        hideModal();
        hideBanner();
    });

    // Cerrar modal al hacer click fuera
    cookieModalOverlay.addEventListener('click', (e) => {
        if (e.target === cookieModalOverlay) {
            hideModal();
        }
    });

    cookieModalOverlay.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && cookieModalOverlay.classList.contains('show')) {
            hideModal();
        }
    });

    // Asignar apertura de modal a botones existentes en la web (ej. Política de Cookies)
    btnCookiesLinks.forEach(btn => {
        btn.addEventListener('click', showModal);
    });
});

