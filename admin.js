/**
 * admin.js — Lógica para el Panel de Administrador (Bloqueo de 30 días)
 */
document.addEventListener('DOMContentLoaded', () => {

    const pistaSelect = document.getElementById('pista-select');
    const fechaSelect = document.getElementById('fecha-select');
    const scheduleGrid = document.getElementById('admin-schedule-grid');
    const btnBlockSlot = document.getElementById('btn-block-slot');
    const toast = document.getElementById('toast');

    // Mismo horario general que horarios.js (L-V / Fin de semana opcional)
    // Para simplificar y asumiendo Tarancón: 
    // L-V y Sábados tienen un patrón fijo.
    const WEEKDAY = {
        morning: [{ start: '09:00', end: '11:00' }, { start: '11:00', end: '13:00' }],
        afternoon: [{ start: '16:00', end: '18:00' }, { start: '18:00', end: '20:00' }, { start: '20:00', end: '22:00' }]
    };
    const SATURDAY = {
        morning: [{ start: '09:00', end: '11:00' }, { start: '11:00', end: '13:00' }],
        afternoon: [{ start: '16:30', end: '18:30' }]
    };
    const SUNDAY = {
        morning: [{ start: '09:00', end: '11:00' }, { start: '11:00', end: '13:00' }],
        afternoon: []
    };

    function getScheduleForDay(dayOfWeek) {
        if (dayOfWeek === 0) return SUNDAY;
        if (dayOfWeek === 6) return SATURDAY;
        return WEEKDAY;
    }

    // ── Llenar el selector de fechas (30 días) ──
    function populateDates() {
        const today = new Date();
        const options = { weekday: 'short', year: 'numeric', month: '2-digit', day: '2-digit' };
        
        for (let i = 0; i < 30; i++) {
            const d = new Date(today);
            d.setDate(today.getDate() + i);
            
            // Format YYYY-MM-DD for value
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            const iso = `${yyyy}-${mm}-${dd}`;
            
            // Format for display
            const display = d.toLocaleDateString('es-ES', options);
            
            const opt = document.createElement('option');
            opt.value = iso;
            opt.dataset.dayoffweek = d.getDay();
            opt.textContent = i === 0 ? `Hoy - ${display}` : display;
            
            fechaSelect.appendChild(opt);
        }
    }

    // ── Mostrar notificaciones ──
    function showToast(message, type = 'success') {
        toast.textContent = message;
        toast.className = `show ${type}`;
        setTimeout(() => { toast.className = toast.className.replace('show', ''); }, 3000);
    }

    // ── Cargar Horarios desde la BBDD ──
    async function loadSchedule() {
        const pista = pistaSelect.value;
        const fecha = fechaSelect.value;
        const selectedOption = fechaSelect.options[fechaSelect.selectedIndex];
        const dayOfWeek = parseInt(selectedOption.dataset.dayoffweek, 10);
        
        scheduleGrid.style.opacity = '0.5';
        btnBlockSlot.disabled = true;
        
        try {
            const resp = await fetch(`api/get-horarios.php?fecha=${fecha}&pista=${pista}`);
            if (!resp.ok) throw new Error("Network error");
            const resData = await resp.json();
            
            if (resData.status === 'success') {
                renderSchedule(dayOfWeek, resData.ocupados || []);
            } else {
                showToast("Error al cargar datos", "error");
            }
        } catch (e) {
            console.error(e);
            showToast("Error de conexión con el servidor", "error");
        } finally {
            scheduleGrid.style.opacity = '1';
        }
    }

    // ── Pintar los bloques de hora ──
    function renderSchedule(dayOfWeek, ocupadosData) {
        scheduleGrid.innerHTML = '';
        const schedule = getScheduleForDay(dayOfWeek);
        
        // Función helper para crear contenedores de turnos
        const renderBlock = (title, slots, timeOfDayClass) => {
            if (slots.length === 0) return;
            
            const block = document.createElement('div');
            block.className = `time-block ${timeOfDayClass}`;
            block.innerHTML = `<h4 class="block-title">${title}</h4>`;
            
            const container = document.createElement('div');
            container.className = 'slots-container';
            
            slots.forEach(slot => {
                const btn = document.createElement('button');
                btn.className = 'time-slot';
                
                // Verificar si está ocupado
                const ocupadoObj = ocupadosData.find(o => o.hora_inicio === slot.start);
                
                if (ocupadoObj) {
                    if (ocupadoObj.estado === 'bloqueada_admin') {
                         btn.classList.add('blocked_admin', 'disabled');
                         btn.innerHTML = `<span class="time">${slot.start} - ${slot.end}</span><span class="status">Bloqueo Admin</span>`;
                    } else {
                         btn.classList.add('booked_user', 'disabled');
                         btn.innerHTML = `<span class="time">${slot.start} - ${slot.end}</span><span class="status">Reservada</span>`;
                    }
                } else {
                    btn.classList.add('available');
                    btn.innerHTML = `<span class="time">${slot.start} - ${slot.end}</span><span class="status">Disponible</span>`;
                    btn.dataset.start = slot.start;
                    btn.dataset.end = slot.end;
                    
                    btn.addEventListener('click', function() {
                        document.querySelectorAll('.time-slot.available').forEach(s => {
                            if (s !== this) {
                                s.classList.remove('selected');
                                s.querySelector('.status').textContent = 'Disponible';
                            }
                        });
                        
                        this.classList.toggle('selected');
                        
                        if (this.classList.contains('selected')) {
                            this.querySelector('.status').textContent = 'Seleccionado';
                            btnBlockSlot.disabled = false;
                        } else {
                            this.querySelector('.status').textContent = 'Disponible';
                            btnBlockSlot.disabled = true;
                        }
                    });
                }
                
                container.appendChild(btn);
            });
            block.appendChild(container);
            scheduleGrid.appendChild(block);
        };
        
        renderBlock('Mañana', schedule.morning, 'morning');
        renderBlock('Tarde', schedule.afternoon, 'afternoon');
        
        if (schedule.morning.length === 0 && schedule.afternoon.length === 0) {
            scheduleGrid.innerHTML = '<p>No hay instalaciones operativas para este día.</p>';
        }
    }

    // ── Ejecutar el bloqueo a través de la API ──
    btnBlockSlot.addEventListener('click', async () => {
        const selectedSlot = document.querySelector('.time-slot.selected');
        if (!selectedSlot) return;
        
        const startTime = selectedSlot.dataset.start;
        const endTime = selectedSlot.dataset.end;
        const pista = pistaSelect.value;
        const fecha = fechaSelect.value;
        
        if (!confirm(`¿Confirmar bloqueo administrativo?\nPista: ${pista}\nFecha: ${fecha}\nHora: ${startTime} - ${endTime}`)) {
            return;
        }
        
        btnBlockSlot.disabled = true;
        btnBlockSlot.textContent = "Bloqueando...";
        
        try {
            const resp = await fetch('api/bloquear-hora.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    pista: pista,
                    fecha: fecha,
                    hora_inicio: startTime,
                    hora_fin: endTime
                })
            });
            
            const data = await resp.json();
            if (resp.ok && data.status === 'success') {
                showToast(data.message, 'success');
                loadSchedule(); // Recargar la vista!
            } else {
                showToast(data.message || 'Error al intentar bloquear', 'error');
            }
        } catch (e) {
            showToast("Error de conexión", 'error');
        } finally {
            btnBlockSlot.textContent = "Bloquear Horario Seleccionado";
        }
    });

    // ── Listeners e Inicialización ──
    pistaSelect.addEventListener('change', loadSchedule);
    fechaSelect.addEventListener('change', loadSchedule);
    
    populateDates();
    loadSchedule();
});
