/**
 * cookies.js — Banner y configuración de consentimiento de cookies.
 *
 * Conforme a:
 *  - Art. 22.2 LSSI-CE (consentimiento previo para cookies no técnicas)
 *  - Guía AEPD sobre el uso de cookies, julio 2023
 *
 * Características:
 *  - Botones "Aceptar todas", "Rechazar todas" y "Configurar" con la MISMA jerarquía visual.
 *  - Granularidad: técnicas (siempre) y analíticas (opt-in).
 *  - La decisión se envía al servidor (api/cookie-consent.php) que la firma en cookie HTTP
 *    y la registra en consent_log para acreditar el consentimiento (RGPD art. 5.2).
 *  - Renovación cada 24 meses (la cookie firmada expira y vuelve a aparecer el banner).
 *  - No se carga NINGÚN script analítico sin consentimiento expreso.
 */
document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function readCookie(name) {
        const match = document.cookie.match(new RegExp('(^|; )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)'));
        return match ? decodeURIComponent(match[2]) : null;
    }

    function parseConsentCookie() {
        const raw = readCookie('cookie_consent');
        if (!raw) return null;
        const [decision, analytics, version] = raw.split('|');
        return {
            decision: decision || '',
            analytics: analytics === '1',
            version: version || '',
        };
    }

    const cookieHTML = `
        <div id="cookie-banner" class="cookie-banner hidden" role="dialog" aria-labelledby="cookie-banner-title" aria-describedby="cookie-banner-desc">
            <div class="cookie-banner-content">
                <p id="cookie-banner-title" class="sr-only">Aviso de cookies</p>
                <p id="cookie-banner-desc">Este portal utiliza cookies propias estrictamente necesarias para su funcionamiento. Puede consultar el detalle en la <a href="politica-cookies.php">Política de Cookies</a>. Actualmente no se utilizan cookies de análisis ni publicidad. Si más adelante se incorporan, se solicitará su consentimiento.</p>
                <div class="cookie-banner-actions">
                    <button id="btn-accept-cookies" class="btn-cookie btn-cookie-primary">Aceptar</button>
                    <button id="btn-reject-cookies" class="btn-cookie btn-cookie-primary">Rechazar</button>
                    <button id="btn-config-cookies" class="btn-cookie btn-cookie-outline">Configurar</button>
                </div>
            </div>
        </div>

        <div id="cookie-modal-overlay" class="cookie-modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="cookie-modal-title">
            <div class="cookie-modal">
                <div class="cookie-modal-header">
                    <h3 id="cookie-modal-title">Configuración de Cookies</h3>
                    <button id="btn-close-cookie-modal" class="btn-close-modal" aria-label="Cerrar">&times;</button>
                </div>
                <div class="cookie-modal-body">
                    <p>Seleccione las categorías de cookies que desea aceptar. Las cookies técnicas son estrictamente necesarias y no pueden desactivarse.</p>
                    <div class="cookie-option">
                        <div class="cookie-option-info">
                            <h4>Cookies Técnicas (estrictamente necesarias)</h4>
                            <p>Sesión, preferencias y consentimiento. Sin ellas el portal no funciona.</p>
                        </div>
                        <div class="cookie-option-switch">
                            <input type="checkbox" id="cookie-tech" checked disabled aria-checked="true">
                            <label for="cookie-tech" class="switch-label" aria-hidden="true"></label>
                        </div>
                    </div>
                    <div class="cookie-option">
                        <div class="cookie-option-info">
                            <h4>Cookies de Análisis (no instaladas actualmente)</h4>
                            <p>Permitirían medir el uso del portal de forma agregada. <strong>Actualmente NO se instalan</strong>; este interruptor reserva su preferencia por si se incorporan en el futuro.</p>
                        </div>
                        <div class="cookie-option-switch">
                            <input type="checkbox" id="cookie-analytics">
                            <label for="cookie-analytics" class="switch-label" aria-hidden="true"></label>
                        </div>
                    </div>
                </div>
                <div class="cookie-modal-footer">
                    <button id="btn-save-cookie-config" class="btn-cookie btn-cookie-primary">Guardar Configuración</button>
                </div>
            </div>
        </div>
    `;

    if (!document.getElementById('cookie-banner')) {
        document.body.insertAdjacentHTML('beforeend', cookieHTML);
    }

    const cookieBanner = document.getElementById('cookie-banner');
    const cookieModalOverlay = document.getElementById('cookie-modal-overlay');
    const btnAccept = document.getElementById('btn-accept-cookies');
    const btnReject = document.getElementById('btn-reject-cookies');
    const btnConfig = document.getElementById('btn-config-cookies');
    const btnCloseModal = document.getElementById('btn-close-cookie-modal');
    const btnSaveConfig = document.getElementById('btn-save-cookie-config');
    const cbAnalytics = document.getElementById('cookie-analytics');
    const btnCookiesLinks = document.querySelectorAll('.btn-cookies');

    const existingConsent = parseConsentCookie();
    if (!existingConsent) {
        cookieBanner.classList.remove('hidden');
        setTimeout(() => cookieBanner.classList.add('show'), 100);
    }

    function hideBanner() {
        cookieBanner.classList.remove('show');
        setTimeout(() => cookieBanner.classList.add('hidden'), 500);
    }

    function showModal(e) {
        if (e) e.preventDefault();
        const consent = parseConsentCookie();
        cbAnalytics.checked = consent ? consent.analytics : false;
        cookieModalOverlay.classList.remove('hidden');
        void cookieModalOverlay.offsetWidth;
        cookieModalOverlay.classList.add('show');
    }

    function hideModal() {
        cookieModalOverlay.classList.remove('show');
        setTimeout(() => cookieModalOverlay.classList.add('hidden'), 300);
    }

    async function postConsent(decision, analytics) {
        try {
            await fetch('../api/cookie-consent.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                },
                credentials: 'same-origin',
                body: JSON.stringify({ decision, analytics, csrf_token: csrfToken }),
            });
        } catch (err) {
            console.warn('No se pudo registrar el consentimiento en el servidor', err);
        }
        // Fallback: si la API falla (404, CORS, etc.) guardamos la decisión solo en LS.
        try {
            localStorage.setItem('cookieConsent', decision);
            localStorage.setItem('cookiePreferences', JSON.stringify({ analytics }));
        } catch (_) { /* ignore */ }
    }

    btnAccept.addEventListener('click', () => {
        postConsent('all', true);
        hideBanner();
    });

    btnReject.addEventListener('click', () => {
        postConsent('rejected', false);
        hideBanner();
    });

    btnConfig.addEventListener('click', showModal);
    btnCloseModal.addEventListener('click', hideModal);

    btnSaveConfig.addEventListener('click', () => {
        postConsent('custom', !!cbAnalytics.checked);
        hideModal();
        hideBanner();
    });

    cookieModalOverlay.addEventListener('click', (e) => {
        if (e.target === cookieModalOverlay) hideModal();
    });
    cookieModalOverlay.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && cookieModalOverlay.classList.contains('show')) hideModal();
    });

    btnCookiesLinks.forEach(btn => btn.addEventListener('click', showModal));

    // Expone API para que otros scripts comprueben si pueden activar analytics
    window.cookieConsent = {
        get: parseConsentCookie,
        analyticsAllowed: () => {
            const c = parseConsentCookie();
            return !!(c && c.analytics);
        },
    };
});
