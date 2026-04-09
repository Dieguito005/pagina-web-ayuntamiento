/**
 * ARCHIVO: js/horarios-accesibles.js
 * Descripción: Lógica de cuadrícula de horarios con soporte WAI-ARIA [12, 13]
 */

document.addEventListener('DOMContentLoaded', () => {
    // Buscamos el grid de horarios (debe tener role="grid" en el HTML)
    const grid = document.querySelector('[role="grid"]');
    if (!grid) return;

    const slots = grid.querySelectorAll('[role="gridcell"]');
    if (slots.length === 0) return;

    // Gestión de navegación por teclado (Arrows) [14, 15]
    grid.addEventListener('keydown', (e) => {
        let currentIdx = Array.from(slots).indexOf(document.activeElement);
        if (currentIdx === -1) return;

        let nextIdx;
        const columns = 4; // Asumimos 4 columnas según diseño responsive de la cuadrícula

        switch(e.key) {
            case 'ArrowRight': nextIdx = currentIdx + 1; break;
            case 'ArrowLeft': nextIdx = currentIdx - 1; break;
            case 'ArrowDown': nextIdx = currentIdx + columns; break;
            case 'ArrowUp': nextIdx = currentIdx - columns; break;
            default: return; // No hacer nada para otras teclas
        }

        if (slots[nextIdx]) {
            slots[nextIdx].focus();
            e.preventDefault();
        }
    });

    // Notificaciones accesibles para cambios de estado [14]
    window.announceStatus = (message) => {
        const liveRegion = document.getElementById('aria-live-announcer');
        if (liveRegion) {
            liveRegion.textContent = message;
        } else {
            console.log("ARIA Live Region missing. Status:", message);
        }
    };
});
