/**
 * js/admin.js — Lógica para el Panel de Administrador (Bloqueo y Desbloqueo de 30 días)
 */
document.addEventListener('DOMContentLoaded', () => {

    const pistaSelect = document.getElementById('pista-select');
    const fechaSelect = document.getElementById('fecha-select');
    const scheduleGrid = document.getElementById('admin-schedule-grid');
    const btnBlockSlot = document.getElementById('btn-block-slot');
    const toast = document.getElementById('toast');

    // Mismo horario general que horarios.js
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

    function populateDates() {
        const today = new Date();
        const options = { weekday: 'short', year: 'numeric', month: '2-digit', day: '2-digit' };
        
        for (let i = 0; i < 30; i++) {
            const d = new Date(today);
            d.setDate(today.getDate() + i);
            
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            const iso = `${yyyy}-${mm}-${dd}`;
            const display = d.toLocaleDateString('es-ES', options);
            
            const opt = document.createElement('option');
            opt.value = iso;
            opt.dataset.dayoffweek = d.getDay();
            opt.textContent = i === 0 ? `Hoy - ${display}` : display;
            
            fechaSelect.appendChild(opt);
        }
    }

    function showToast(message, type = 'success') {
        toast.textContent = message;
        toast.className = `show ${type}`;
        setTimeout(() => { toast.className = toast.className.replace('show', ''); }, 3000);
    }

    async function loadSchedule() {
        const pista = pistaSelect.value;
        const fecha = fechaSelect.value;
        const selectedOption = fechaSelect.options[fechaSelect.selectedIndex];
        const dayOfWeek = parseInt(selectedOption.dataset.dayoffweek, 10);
        
        scheduleGrid.style.opacity = '0.5';
        btnBlockSlot.disabled = true;
        btnBlockSlot.textContent = "Selecciona un horario";
        btnBlockSlot.className = 'btn-block';
        
        try {
            const resp = await fetch(`../api/get-horarios.php?fecha=${fecha}&pista=${pista}`);
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

    function renderSchedule(dayOfWeek, ocupadosData) {
        scheduleGrid.innerHTML = '';
        const schedule = getScheduleForDay(dayOfWeek);
        
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
                
                const ocupadoObj = ocupadosData.find(o => o.hora_inicio === slot.start);
                
                if (ocupadoObj) {
                    if (ocupadoObj.estado === 'bloqueada_admin') {
                         btn.classList.add('blocked_admin');
                         btn.innerHTML = `<span class="time">${slot.start} - ${slot.end}</span><span class="status">Bloqueo Admin</span>`;
                         btn.dataset.action = 'unblock';
                         btn.dataset.start = slot.start;
                         btn.dataset.end = slot.end;
                    } else {
                         btn.classList.add('booked_user', 'disabled');
                         btn.innerHTML = `<span class="time">${slot.start} - ${slot.end}</span><span class="status">Reservada</span>`;
                    }
                } else {
                    btn.classList.add('available');
                    btn.innerHTML = `<span class="time">${slot.start} - ${slot.end}</span><span class="status">Disponible</span>`;
                    btn.dataset.start = slot.start;
                    btn.dataset.end = slot.end;
                    btn.dataset.action = 'block';
                }
                
                if (!btn.classList.contains('disabled')) {
                    btn.addEventListener('click', function() {
                        document.querySelectorAll('.time-slot').forEach(s => {
                            if (s !== this && !s.classList.contains('disabled')) {
                                s.classList.remove('selected');
                                if (s.dataset.action === 'unblock') s.querySelector('.status').textContent = 'Bloqueo Admin';
                                else s.querySelector('.status').textContent = 'Disponible';
                            }
                        });
                        
                        this.classList.toggle('selected');
                        
                        if (this.classList.contains('selected')) {
                            this.querySelector('.status').textContent = 'Seleccionado';
                            btnBlockSlot.disabled = false;
                            
                            if (this.dataset.action === 'unblock') {
                                btnBlockSlot.textContent = "Desbloquear Horario Seleccionado";
                                btnBlockSlot.className = 'btn-unblock';
                            } else {
                                btnBlockSlot.textContent = "Bloquear Horario Seleccionado";
                                btnBlockSlot.className = 'btn-block';
                            }
                        } else {
                            if (this.dataset.action === 'unblock') this.querySelector('.status').textContent = 'Bloqueo Admin';
                            else this.querySelector('.status').textContent = 'Disponible';
                            
                            btnBlockSlot.disabled = true;
                            btnBlockSlot.textContent = "Selecciona un horario";
                            btnBlockSlot.className = 'btn-block';
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

    btnBlockSlot.addEventListener('click', async () => {
        const selectedSlot = document.querySelector('.time-slot.selected');
        if (!selectedSlot) return;
        
        const action = selectedSlot.dataset.action;
        const isUnblock = action === 'unblock';
        
        const startTime = selectedSlot.dataset.start;
        const endTime = selectedSlot.dataset.end;
        const pista = pistaSelect.value;
        const fecha = fechaSelect.value;
        
        const confirmMsg = isUnblock ? '¿Confirmar DESBLOQUEO administrativo?' : '¿Confirmar bloqueo administrativo?';
        
        if (!confirm(`${confirmMsg}\nPista: ${pista}\nFecha: ${fecha}\nHora: ${startTime} - ${endTime}`)) {
            return;
        }
        
        btnBlockSlot.disabled = true;
        btnBlockSlot.textContent = isUnblock ? "Desbloqueando..." : "Bloqueando...";
        
        const endpoint = isUnblock ? '../api/desbloquear-hora.php' : '../api/bloquear-hora.php';
        
        try {
            const resp = await fetch(endpoint, {
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
                loadSchedule();
            } else {
                showToast(data.message || 'Error en la operación', 'error');
            }
        } catch (e) {
            showToast("Error de conexión", 'error');
        } finally {
            if (!btnBlockSlot.disabled) {
                btnBlockSlot.textContent = isUnblock ? "Desbloquear Horario Seleccionado" : "Bloquear Horario Seleccionado";
            }
        }
    });

    pistaSelect.addEventListener('change', loadSchedule);
    fechaSelect.addEventListener('change', loadSchedule);
    
    populateDates();
    loadSchedule();
});
