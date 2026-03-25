/**
 * horarios.js — Lógica compartida de horarios dinámicos con integración de Base de Datos
 * Genera los slots de reserva según el día de la semana (hora de Tarancón) consultando PHP API.
 */
document.addEventListener('DOMContentLoaded', () => {
    const TIME_ZONE = 'Europe/Madrid';
    const SUPLEMENTO_LUZ = 1.00;

    const bookingContainer = document.querySelector('.booking-container');
    const sport = bookingContainer ? bookingContainer.getAttribute('data-sport') || 'Deporte' : 'Deporte';
    const confirmBtn = document.querySelector('.btn-confirm-booking');
    const scheduleGrid = document.querySelector('.schedule-grid');

    const DAY_NAMES_SHORT = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

    const WEEKDAY = {
        morning: [{ start: '09:00', end: '11:00' }, { start: '11:00', end: '13:00' }],
        afternoon: [{ start: '16:00', end: '18:00' }, { start: '18:00', end: '20:00' }, { start: '20:00', end: '22:00', supplement: SUPLEMENTO_LUZ }]
    };

    const SCHEDULE = {
        0: { morning: [{ start: '09:00', end: '11:00' }, { start: '11:00', end: '13:00' }], afternoon: [] },
        1: WEEKDAY, 2: WEEKDAY, 3: WEEKDAY, 4: WEEKDAY, 5: WEEKDAY,
        6: { morning: [{ start: '09:00', end: '11:00' }, { start: '11:00', end: '13:00' }], afternoon: [{ start: '16:30', end: '18:30' }] }
    };

    function getNow() {
        return new Date(new Date().toLocaleString('en-US', { timeZone: TIME_ZONE }));
    }

    function getDayOfWeek(offset) {
        const d = getNow();
        d.setDate(d.getDate() + offset);
        return d.getDay();
    }

    function getISODateForAPI(offset) {
        const d = getNow();
        d.setDate(d.getDate() + offset);
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    }

    function createSlotButton(slot, ocupadosList) {
        const btn = document.createElement('button');
        
        // Comprobar ocupación en API
        const ocupadoObj = ocupadosList.find(o => o.hora_inicio === slot.start);
        
        const badge = slot.supplement && !ocupadoObj
            ? `<span class="supplement-badge">💡 +${slot.supplement.toFixed(2).replace('.', ',')} € luz</span>`
            : '';
            
        if (ocupadoObj) {
            btn.className = 'time-slot disabled';
            const extraClass = ocupadoObj.estado === 'bloqueada_admin' ? 'bloqueado-admin' : '';
            const statusText = ocupadoObj.estado === 'bloqueada_admin' ? 'No Disponible' : 'Ocupada';
            
            btn.innerHTML = `
                <span class="time">${slot.start} - ${slot.end}</span>
                <span class="status ${extraClass}">${statusText}</span>
            `;
        } else {
            btn.className = 'time-slot available';
            btn.innerHTML = `
                <span class="time">${slot.start} - ${slot.end}</span>
                ${badge}
                <span class="status">Disponible</span>
            `;
            if (slot.supplement) btn.dataset.supplement = slot.supplement;
        }
        
        return btn;
    }

    function createTimeBlock(className, title, slots, ocupadosList) {
        const block = document.createElement('div');
        block.className = `time-block ${className}`;
        block.innerHTML = `<h4 class="block-title">${title}</h4>`;
        const container = document.createElement('div');
        container.className = 'slots-container';
        slots.forEach(slot => container.appendChild(createSlotButton(slot, ocupadosList)));
        block.appendChild(container);
        return block;
    }

    async function generateSlots(offset) {
        const dayOfWeek = getDayOfWeek(offset);
        const schedule = SCHEDULE[dayOfWeek];
        if (!schedule) return;

        scheduleGrid.style.opacity = '0.5';
        scheduleGrid.innerHTML = '<p class="loading-schedule">Cargando disponibilidad...</p>';
        confirmBtn.disabled = true;

        const dateISO = getISODateForAPI(offset);
        let ocupadosList = [];

        try {
            const resp = await fetch(`../api/get-horarios.php?fecha=${dateISO}&pista=${encodeURIComponent(sport)}`);
            if (resp.ok) {
                const data = await resp.json();
                if (data.status === 'success') {
                    ocupadosList = data.ocupados;
                }
            }
        } catch (e) {
            console.error("No se pudo conectar con la BBDD, asumiendo disponibilidad.");
        }

        scheduleGrid.innerHTML = '';

        if (schedule.morning.length > 0) scheduleGrid.appendChild(createTimeBlock('morning', 'Mañana', schedule.morning, ocupadosList));
        if (schedule.afternoon.length > 0) scheduleGrid.appendChild(createTimeBlock('afternoon', 'Tarde', schedule.afternoon, ocupadosList));
        if (schedule.morning.length === 0 && schedule.afternoon.length === 0) {
            scheduleGrid.innerHTML = '<p class="no-schedule-msg">No hay horarios disponibles para este día.</p>';
        }

        bindSlotEvents();
        refreshSlotsState(offset); // Bloquea si ya ha pasado la hora real
        scheduleGrid.style.opacity = '1';
    }

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

    function refreshSlotsState(offset) {
        const slots = document.querySelectorAll('.time-slot');
        if (offset > 0) return; // Si no es hoy, no comprobamos hora pasada.

        const now = getNow();
        const currentHour = now.getHours();
        const currentMin = now.getMinutes();

        slots.forEach(slot => {
            if (!slot.classList.contains('available') && !slot.classList.contains('selected')) return; 
            
            const timeSpan = slot.querySelector('.time').textContent;
            const [h, m] = timeSpan.split(' - ')[0].split(':').map(Number);
            const passed = h < currentHour || (h === currentHour && m <= currentMin);

            if (passed) {
                slot.classList.remove('available', 'selected');
                slot.classList.add('disabled');
                slot.querySelector('.status').textContent = 'Pasada';
            }
        });
    }

    function updateDatesAndClock() {
        const now = getNow();
        
        const timeDisplay = document.getElementById('local-time-display');
        if (timeDisplay) timeDisplay.textContent = now.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

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

    document.querySelectorAll('.day-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            if (this.classList.contains('active')) return;
            
            document.querySelectorAll('.day-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const offset = parseInt(this.getAttribute('data-day-offset'), 10);
            generateSlots(offset);
        });
    });

    confirmBtn.addEventListener('click', () => {
        const selectedEl = document.querySelector('.time-slot.selected');
        const timeEl = selectedEl ? selectedEl.querySelector('.time') : null;
        const dayName = document.querySelector('.day-btn.active .day-name');
        const dayDate = document.querySelector('.day-btn.active .day-date');

        if (timeEl && dayName && dayDate) {
            const supplement = selectedEl.dataset.supplement ? parseFloat(selectedEl.dataset.supplement) : 0;
            localStorage.setItem('bookingData', JSON.stringify({
                sport,
                date: `${getISODateForAPI(parseInt(document.querySelector('.day-btn.active').getAttribute('data-day-offset'), 10))}`,
                dateVisual: `${dayName.textContent} (${dayDate.textContent})`,
                time: timeEl.textContent,
                supplement
            }));
            window.location.href = 'checkout.php';
        }
    });

    const yearSpan = document.getElementById('current-year');
    if (yearSpan) yearSpan.textContent = new Date().getFullYear();

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

    window.addEventListener('scroll', () => {
        const header = document.querySelector('.main-header');
        if (header) header.classList.toggle('scrolled', window.scrollY > 50);
    });

    updateDatesAndClock();
    setInterval(updateDatesAndClock, 1000);
    // Iniciar con "Hoy"
    generateSlots(0);
});


