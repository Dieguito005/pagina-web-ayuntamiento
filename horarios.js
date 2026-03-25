/**
 * horarios.js — Lógica compartida de horarios dinámicos
 * Genera los slots de reserva según el día de la semana (hora de Tarancón).
 */
document.addEventListener('DOMContentLoaded', () => {
    const TIME_ZONE = 'Europe/Madrid';

    // ── Suplemento de luz (€) — modificar aquí cuando se sepa el importe exacto ──
    const SUPLEMENTO_LUZ = 1.00;

    const bookingContainer = document.querySelector('.booking-container');
    const sport = bookingContainer ? bookingContainer.getAttribute('data-sport') || 'Deporte' : 'Deporte';
    const confirmBtn = document.querySelector('.btn-confirm-booking');
    const scheduleGrid = document.querySelector('.schedule-grid');

    const DAY_NAMES_SHORT = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

    // ── Definición de horarios ──
    // Lunes-Viernes comparten el mismo horario
    const WEEKDAY = {
        morning: [{ start: '09:00', end: '11:00' }, { start: '11:00', end: '13:00' }],
        afternoon: [{ start: '16:00', end: '18:00' }, { start: '18:00', end: '20:00' }, { start: '20:00', end: '22:00', supplement: SUPLEMENTO_LUZ }]
    };

    const SCHEDULE = {
        0: { morning: [{ start: '09:00', end: '11:00' }, { start: '11:00', end: '13:00' }], afternoon: [] },           // Domingo
        1: WEEKDAY, 2: WEEKDAY, 3: WEEKDAY, 4: WEEKDAY, 5: WEEKDAY,                                                   // Lunes–Viernes
        6: { morning: [{ start: '09:00', end: '11:00' }, { start: '11:00', end: '13:00' }], afternoon: [{ start: '16:30', end: '18:30' }] } // Sábado
    };

    // ── Utilidades ──
    function getNow() {
        return new Date(new Date().toLocaleString('en-US', { timeZone: TIME_ZONE }));
    }

    function getDayOfWeek(offset) {
        const d = getNow();
        d.setDate(d.getDate() + offset);
        return d.getDay();
    }

    // ── Crear un botón de slot ──
    function createSlotButton(slot) {
        const btn = document.createElement('button');
        btn.className = 'time-slot available';
        const badge = slot.supplement
            ? `<span class="supplement-badge">💡 +${slot.supplement.toFixed(2).replace('.', ',')} € luz</span>`
            : '';
        btn.innerHTML = `
            <span class="time">${slot.start} - ${slot.end}</span>
            ${badge}
            <span class="status">Disponible</span>
        `;
        if (slot.supplement) btn.dataset.supplement = slot.supplement;
        return btn;
    }

    // ── Crear un bloque de turno (Mañana / Tarde) ──
    function createTimeBlock(className, title, slots) {
        const block = document.createElement('div');
        block.className = `time-block ${className}`;
        block.innerHTML = `<h4 class="block-title">${title}</h4>`;
        const container = document.createElement('div');
        container.className = 'slots-container';
        slots.forEach(slot => container.appendChild(createSlotButton(slot)));
        block.appendChild(container);
        return block;
    }

    // ── Generar slots en el DOM ──
    function generateSlots(dayOfWeek) {
        const schedule = SCHEDULE[dayOfWeek];
        if (!schedule) return;

        scheduleGrid.innerHTML = '';

        if (schedule.morning.length > 0) {
            scheduleGrid.appendChild(createTimeBlock('morning', 'Mañana', schedule.morning));
        }
        if (schedule.afternoon.length > 0) {
            scheduleGrid.appendChild(createTimeBlock('afternoon', 'Tarde', schedule.afternoon));
        }
        if (schedule.morning.length === 0 && schedule.afternoon.length === 0) {
            scheduleGrid.innerHTML = '<p class="no-schedule-msg">No hay horarios disponibles para este día.</p>';
        }

        bindSlotEvents();
        refreshSlotsState();
    }

    // ── Eventos de click en slots ──
    function bindSlotEvents() {
        const slots = document.querySelectorAll('.time-slot');
        slots.forEach(slot => {
            slot.addEventListener('click', function () {
                if (this.classList.contains('disabled')) return;

                if (this.classList.contains('selected')) {
                    this.classList.remove('selected');
                    this.querySelector('.status').textContent = 'Disponible';
                    confirmBtn.disabled = true;
                } else {
                    slots.forEach(s => {
                        if (!s.classList.contains('disabled')) {
                            s.classList.remove('selected');
                            s.querySelector('.status').textContent = 'Disponible';
                        }
                    });
                    this.classList.add('selected');
                    this.querySelector('.status').textContent = 'Seleccionado';
                    confirmBtn.disabled = false;
                }
            });
        });
    }

    // ── Bloquear horas pasadas (solo "Hoy") ──
    function refreshSlotsState() {
        const activeBtn = document.querySelector('.day-btn.active');
        if (!activeBtn) return;
        const offset = parseInt(activeBtn.getAttribute('data-day-offset'), 10);
        const slots = document.querySelectorAll('.time-slot');

        if (offset > 0) {
            slots.forEach(slot => {
                slot.classList.remove('disabled');
                slot.classList.add('available');
                if (!slot.classList.contains('selected')) {
                    slot.querySelector('.status').textContent = 'Disponible';
                }
            });
            return;
        }

        const now = getNow();
        const currentHour = now.getHours();
        const currentMin = now.getMinutes();

        slots.forEach(slot => {
            const [h, m] = slot.querySelector('.time').textContent.split(' - ')[0].split(':').map(Number);
            const passed = h < currentHour || (h === currentHour && m <= currentMin);

            if (passed) {
                slot.classList.remove('available', 'selected');
                slot.classList.add('disabled');
                slot.querySelector('.status').textContent = 'Pasada';
            } else {
                slot.classList.remove('disabled');
                slot.classList.add('available');
                if (!slot.classList.contains('selected')) {
                    slot.querySelector('.status').textContent = 'Disponible';
                }
            }
        });
    }

    // ── Reloj y fechas ──
    function updateDatesAndClock() {
        const now = getNow();

        const timeDisplay = document.getElementById('local-time-display');
        if (timeDisplay) {
            timeDisplay.textContent = now.toLocaleTimeString('es-ES', {
                hour: '2-digit', minute: '2-digit', second: '2-digit'
            });
        }

        const dateOptions = { month: '2-digit', day: '2-digit' };
        document.querySelectorAll('.day-btn').forEach(btn => {
            const off = parseInt(btn.getAttribute('data-day-offset'), 10);
            const d = new Date(now);
            d.setDate(now.getDate() + off);

            const dateEl = btn.querySelector('.day-date');
            if (dateEl) dateEl.textContent = d.toLocaleDateString('es-ES', dateOptions);

            const weekEl = btn.querySelector('.day-weekname');
            if (weekEl) weekEl.textContent = DAY_NAMES_SHORT[d.getDay()];
        });
    }

    // ── Selección de día ──
    document.querySelectorAll('.day-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.day-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            generateSlots(getDayOfWeek(parseInt(this.getAttribute('data-day-offset'), 10)));
            confirmBtn.disabled = true;

            scheduleGrid.style.opacity = '0.5';
            setTimeout(() => { scheduleGrid.style.opacity = '1'; }, 150);
        });
    });

    // ── Confirmar reserva ──
    confirmBtn.addEventListener('click', () => {
        const selectedEl = document.querySelector('.time-slot.selected');
        const timeEl = selectedEl ? selectedEl.querySelector('.time') : null;
        const dayName = document.querySelector('.day-btn.active .day-name');
        const dayDate = document.querySelector('.day-btn.active .day-date');

        if (timeEl && dayName && dayDate) {
            const supplement = selectedEl.dataset.supplement ? parseFloat(selectedEl.dataset.supplement) : 0;
            localStorage.setItem('bookingData', JSON.stringify({
                sport,
                date: `${dayName.textContent} (${dayDate.textContent})`,
                time: timeEl.textContent,
                supplement
            }));
            window.location.href = 'checkout.html';
        }
    });

    // ── Footer: año actual ──
    const yearSpan = document.getElementById('current-year');
    if (yearSpan) yearSpan.textContent = new Date().getFullYear();

    // ── Animaciones scroll ──
    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const cls = entry.target.dataset.animation;
                if (cls) entry.target.classList.add(cls);
                obs.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
    document.querySelectorAll('.scroll-animate').forEach(el => observer.observe(el));

    // ── Header scroll ──
    window.addEventListener('scroll', () => {
        document.querySelector('.main-header').classList.toggle('scrolled', window.scrollY > 50);
    });

    // ── Inicialización ──
    updateDatesAndClock();
    setInterval(updateDatesAndClock, 1000);
    generateSlots(getDayOfWeek(0));
    setInterval(refreshSlotsState, 60000);
});
