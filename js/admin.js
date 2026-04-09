/**
 * js/admin.js — Lógica para el Panel de Administrador (Bloqueo y Desbloqueo de 30 días, Edición de Precios)
 */
document.addEventListener('DOMContentLoaded', () => {

    const pistaSelect = document.getElementById('pista-select');
    const fechaSelect = document.getElementById('fecha-select');
    const scheduleGrid = document.getElementById('admin-schedule-grid');
    const btnBlockSlot = document.getElementById('btn-block-slot');
    const toast = document.getElementById('toast');
    
    // Vistas y Contenedores
    const viewBloqueos = document.getElementById('view-bloqueos-precios');
    const viewBuscador = document.getElementById('view-buscador');
    const actionBarBloqueos = document.getElementById('action-bar-bloqueos');

    // Tabs y Modos
    const tabBuscador = document.getElementById('tab-buscador');
    const tabConsultas = document.getElementById('tab-consultas');
    const tabAnuncios = document.getElementById('tab-anuncios');
    const tabBloqueos = document.getElementById('tab-bloqueos');
    const tabPrecios = document.getElementById('tab-precios');
    const tabActividad = document.getElementById('tab-actividad');
    const titlePage = document.getElementById('admin-page-title');
    let currentMode = 'bloqueos'; // 'bloqueos', 'precios', 'buscador', 'consultas', 'anuncios', 'admins', 'actividad', 'estadisticas'

    // Vista Actividad
    const viewActividad = document.getElementById('view-actividad');
    const badgeActividad = document.getElementById('badge-actividad');

    // Vista Estadisticas
    const tabEstadisticas = document.getElementById('tab-estadisticas');
    const viewEstadisticas = document.getElementById('view-estadisticas');

    // Elementos Consultas
    const viewConsultas = document.getElementById('view-consultas');
    const formConsultas = document.getElementById('form-consultas');
    const tablaConsultasBody = document.getElementById('tabla-consultas-body');
    let consultasActuales = [];

    // Elementos Anuncios
    const viewAnuncios = document.getElementById('view-anuncios');
    const formAnuncio = document.getElementById('form-nuevo-anuncio');
    const tablaAnunciosBody = document.getElementById('tabla-anuncios-body');
    let anunciosActuales = [];

    // Elementos del Modal de Precios
    const priceModal = document.getElementById('price-modal');
    const priceModalInfo = document.getElementById('price-modal-info');
    const modalPrecioBase = document.getElementById('modal-precio-base');
    const modalPrecioLuz = document.getElementById('modal-precio-luz');
    const btnCancelPrice = document.getElementById('btn-cancel-price');
    const btnSavePrice = document.getElementById('btn-save-price');
    let currentPriceSlot = null; // Guardar contexto del slot editado

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

    // Helper: ocultar todas las vistas y desactivar todos los tabs
    function hideAllViews() {
        viewBloqueos.style.display = 'none';
        viewBuscador.style.display = 'none';
        if (viewConsultas) viewConsultas.style.display = 'none';
        if (viewAnuncios) viewAnuncios.style.display = 'none';
        if (viewActividad) viewActividad.style.display = 'none';
        if (viewEstadisticas) viewEstadisticas.style.display = 'none';
        const va = document.getElementById('view-admins'); if (va) va.style.display = 'none';
        if (actionBarBloqueos) actionBarBloqueos.style.display = 'none';
    }
    function deactivateAllTabs() {
        [tabBuscador, tabConsultas, tabAnuncios, tabBloqueos, tabPrecios, tabActividad, tabEstadisticas,
         document.getElementById('tab-admins')].forEach(t => t?.classList.remove('active'));
    }

    // ── Logica Cambio de Tabs ──
    if (tabBloqueos && tabPrecios && tabBuscador) {
        if (tabEstadisticas) {
            tabEstadisticas.addEventListener('click', (e) => {
                e.preventDefault();
                console.log("Stats tab clicked");
                currentMode = 'estadisticas';
                deactivateAllTabs();
                tabEstadisticas.classList.add('active');
                titlePage.textContent = '📊 Resumen y Estadísticas';
                hideAllViews();
                if (viewEstadisticas) {
                    viewEstadisticas.style.display = 'block';
                    loadEstadisticas();
                } else {
                    console.error("viewEstadisticas element not found");
                }
            });
        }

        tabBuscador.addEventListener('click', (e) => {
            e.preventDefault();
            currentMode = 'buscador';
            deactivateAllTabs();
            tabBuscador.classList.add('active');
            titlePage.textContent = '🔍 Buscador e Historial';
            hideAllViews();
            viewBuscador.style.display = 'block';
        });

        if (tabConsultas) {
            tabConsultas.addEventListener('click', (e) => {
                e.preventDefault();
                currentMode = 'consultas';
                deactivateAllTabs();
                tabConsultas.classList.add('active');
                titlePage.textContent = '📬 Buzón de Consultas';
                hideAllViews();
                viewConsultas.style.display = 'block';
                if (formConsultas) formConsultas.dispatchEvent(new Event('submit'));
            });
        }

        if (tabAnuncios) {
            tabAnuncios.addEventListener('click', (e) => {
                e.preventDefault();
                currentMode = 'anuncios';
                deactivateAllTabs();
                tabAnuncios.classList.add('active');
                titlePage.textContent = '📢 Anuncios y Novedades';
                hideAllViews();
                viewAnuncios.style.display = 'block';
                loadAnuncios();
            });
        }

        if (tabActividad) {
            tabActividad.addEventListener('click', (e) => {
                e.preventDefault();
                currentMode = 'actividad';
                deactivateAllTabs();
                tabActividad.classList.add('active');
                titlePage.textContent = '🔔 Centro de Actividad';
                hideAllViews();
                viewActividad.style.display = 'block';
                loadActividad();
            });
        }

        tabBloqueos.addEventListener('click', (e) => {
            e.preventDefault();
            currentMode = 'bloqueos';
            deactivateAllTabs();
            tabBloqueos.classList.add('active');
            titlePage.textContent = '📅 Gestión de Bloqueos';
            hideAllViews();
            viewBloqueos.style.display = 'block';
            if (actionBarBloqueos) actionBarBloqueos.style.display = 'flex';
            loadSchedule();
        });
        
        tabPrecios.addEventListener('click', (e) => {
            e.preventDefault();
            currentMode = 'precios';
            deactivateAllTabs();
            tabPrecios.classList.add('active');
            titlePage.textContent = '💰 Edición de Precios';
            hideAllViews();
            viewBloqueos.style.display = 'block';
            loadSchedule();
        });
    }

    async function loadSchedule() {
        const pista = pistaSelect.value;
        const fecha = fechaSelect.value;
        const selectedOption = fechaSelect.options[fechaSelect.selectedIndex];
        const dayOfWeek = parseInt(selectedOption.dataset.dayoffweek, 10);
        
        scheduleGrid.style.opacity = '0.5';
        if (btnBlockSlot) {
            btnBlockSlot.disabled = true;
            btnBlockSlot.textContent = "Selecciona un horario";
            btnBlockSlot.className = 'btn-block';
        }
        
        try {
            const resp = await fetch(`../api/get-horarios.php?fecha=${fecha}&pista=${encodeURIComponent(pista)}`);
            if (!resp.ok) throw new Error("Network error");
            const resData = await resp.json();
            
            if (resData.status === 'success') {
                renderSchedule(dayOfWeek, resData.ocupados || [], resData.precios || []);
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

    function renderSchedule(dayOfWeek, ocupadosData, preciosData) {
        scheduleGrid.innerHTML = '';
        const schedule = getScheduleForDay(dayOfWeek);
        
        const now = new Date();
        const todayStr = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
        const selectedDateStr = fechaSelect.value;
        const isPastDay = selectedDateStr < todayStr;
        const isToday = selectedDateStr === todayStr;
        const currentTimeInt = now.getHours() * 60 + now.getMinutes();

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
                const precioObj = preciosData.find(p => p.hora_inicio === slot.start);
                
                const slotStartInt = parseInt(slot.start.split(':')[0]) * 60 + parseInt(slot.start.split(':')[1]);
                const isSlotPast = isPastDay || (isToday && slotStartInt <= currentTimeInt);

                let extraHtml = '';
                if (precioObj) {
                    let base = precioObj.precio_base !== null ? precioObj.precio_base.toFixed(2) : '-';
                    let luz = precioObj.precio_luz !== null ? precioObj.precio_luz.toFixed(2) : '-';
                    extraHtml = `<span class="price-tag">C: Base ${base}€ | Luz ${luz}€</span>`;
                }

                if (isSlotPast) {
                    btn.classList.add('disabled');
                    let displayStatus = 'Pasado/En Curso ⏳';
                    if (ocupadoObj) displayStatus = ocupadoObj.estado === 'bloqueada_admin' ? 'Pasado (Bloqueado)' : 'Pasado (Reservado)';
                    btn.innerHTML = `<span class="time" style="color:#999">${slot.start} - ${slot.end}</span><span class="status" style="color:#aaa">${displayStatus}</span>${extraHtml.replace('price-tag', 'price-tag" style="color:#aaa;"')}`;
                    btn.dataset.start = slot.start;
                    btn.dataset.end = slot.end;
                } else if (ocupadoObj) {
                    if (ocupadoObj.estado === 'bloqueada_admin') {
                         btn.classList.add('blocked_admin');
                         btn.innerHTML = `<span class="time">${slot.start} - ${slot.end}</span><span class="status">Bloqueo Admin</span>${extraHtml}`;
                         btn.dataset.action = 'unblock';
                         btn.dataset.start = slot.start;
                         btn.dataset.end = slot.end;
                    } else {
                         btn.classList.add('booked_user', 'disabled');
                         btn.innerHTML = `<span class="time">${slot.start} - ${slot.end}</span><span class="status">Reservada</span>${extraHtml}`;
                         btn.dataset.start = slot.start;
                         btn.dataset.end = slot.end;
                    }
                } else {
                    btn.classList.add('available');
                    btn.innerHTML = `<span class="time">${slot.start} - ${slot.end}</span><span class="status">Disponible</span>${extraHtml}`;
                    btn.dataset.start = slot.start;
                    btn.dataset.end = slot.end;
                    btn.dataset.action = 'block';
                }
                
                // En modo precios, permitimos click en TODO (incluso reservados)
                // En modo bloqueos, sólo en no disabled
                btn.addEventListener('click', function() {
                    if (currentMode === 'bloqueos') {
                        if (this.classList.contains('disabled')) return;
                        
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
                    } else if (currentMode === 'precios') {
                        if (this.classList.contains('disabled') || this.classList.contains('blocked_admin')) {
                            showToast('No se puede cambiar el precio de una hora que ya está reservada o bloqueada.', 'error');
                            return;
                        }

                        // Modal de editar precios
                        currentPriceSlot = {
                            start: this.dataset.start,
                            end: this.dataset.end,
                            pista: pistaSelect.value,
                            fecha: fechaSelect.value
                        };
                        
                        priceModalInfo.innerHTML = `Pista: <strong>${currentPriceSlot.pista}</strong><br>Fecha: <strong>${currentPriceSlot.fecha}</strong><br>Hora: <strong>${currentPriceSlot.start} - ${currentPriceSlot.end}</strong>`;
                        
                        if (precioObj) {
                            modalPrecioBase.value = precioObj.precio_base !== null ? precioObj.precio_base : '';
                            modalPrecioLuz.value = precioObj.precio_luz !== null ? precioObj.precio_luz : '';
                        } else {
                            modalPrecioBase.value = '';
                            modalPrecioLuz.value = '';
                        }
                        
                        priceModal.style.display = 'flex';
                    }
                });
                
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

    // Modal Events
    if (btnCancelPrice) {
        btnCancelPrice.addEventListener('click', () => {
            priceModal.style.display = 'none';
        });
    }

    if (btnSavePrice) {
        btnSavePrice.addEventListener('click', async () => {
            if (!currentPriceSlot) return;
            
            const pBase = modalPrecioBase.value;
            const pLuz = modalPrecioLuz.value;
            
            btnSavePrice.textContent = 'Guardando...';
            btnSavePrice.disabled = true;
            
            try {
                const resp = await fetch('../api/update-precio.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        pista: currentPriceSlot.pista,
                        fecha: currentPriceSlot.fecha,
                        hora_inicio: currentPriceSlot.start,
                        precio_base: pBase,
                        precio_luz: pLuz
                    })
                });
                const data = await resp.json();
                
                if (resp.ok && data.status === 'success') {
                    showToast(data.message, 'success');
                    priceModal.style.display = 'none';
                    loadSchedule();
                } else {
                    showToast(data.message || 'Error al guardar', 'error');
                }
            } catch (e) {
                showToast('Error de conexión', 'error');
            } finally {
                btnSavePrice.textContent = 'Guardar';
                btnSavePrice.disabled = false;
            }
        });
    }

    if (btnBlockSlot) {
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
    }

    pistaSelect.addEventListener('change', loadSchedule);
    fechaSelect.addEventListener('change', loadSchedule);
    
    populateDates();
    loadSchedule();

    // ── Logica Buscador e Historial ──
    const formBuscador = document.getElementById('form-buscador');
    const tablaReservasBody = document.getElementById('tabla-reservas-body');
    const btnExportCsv = document.getElementById('btn-export-csv');
    const detailsModal = document.getElementById('details-modal');
    const detailsModalContent = document.getElementById('details-modal-content');
    const btnCloseDetails = document.getElementById('btn-close-details');
    const btnCloseDetails2 = document.getElementById('btn-close-details2');
    
    let reservasActuales = [];

    if (formBuscador) {
        formBuscador.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const dni = document.getElementById('s-dni').value.trim();
            const nombre = document.getElementById('s-nombre').value.trim();
            const pista = document.getElementById('s-pista').value;
            const estado = document.getElementById('s-estado').value;
            const fechaInicio = document.getElementById('s-fecha-inicio').value;
            
            let query = `../api/admin-reservas.php?1=1`;
            if (dni) query += `&dni=${encodeURIComponent(dni)}`;
            if (nombre) query += `&nombre=${encodeURIComponent(nombre)}`;
            if (pista) query += `&pista=${encodeURIComponent(pista)}`;
            if (estado) query += `&estado=${encodeURIComponent(estado)}`;
            if (fechaInicio) query += `&fecha_inicio=${encodeURIComponent(fechaInicio)}`;
            
            tablaReservasBody.innerHTML = '<tr><td colspan="7" style="text-align:center;">Buscando...</td></tr>';
            
            try {
                const resp = await fetch(query);
                const data = await resp.json();
                
                if (resp.ok && data.status === 'success') {
                    reservasActuales = data.data;
                    renderTable(reservasActuales);
                } else {
                    tablaReservasBody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:red;">Error: ${data.message}</td></tr>`;
                }
            } catch (err) {
                tablaReservasBody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:red;">Error de conexión.</td></tr>`;
            }
        });
    }

    function getBadgeClass(estado) {
        if (estado === 'reservada') return 'reservada';
        if (estado === 'cancelada') return 'cancelada';
        return 'bloqueada_admin';
    }

    function renderTable(data) {
        if (data.length === 0) {
            tablaReservasBody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No se encontraron resultados.</td></tr>';
            return;
        }

        const now = new Date();
        const strDate = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
        const strTime = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');

        tablaReservasBody.innerHTML = data.map(r => {
            const isPast = r.fecha < strDate || (r.fecha === strDate && r.hora_inicio.substring(0,5) <= strTime);
            const canCancel = r.estado !== 'cancelada' && r.estado !== 'bloqueada_admin' && !isPast;
            
            return `
            <tr>
                <td><strong>${r.fecha.split('-').reverse().join('/')}</strong><br><span style="color:#666;font-size:0.85rem;">${r.hora_inicio.substring(0,5)} - ${r.hora_fin.substring(0,5)}</span></td>
                <td>${r.pista}</td>
                <td>${r.usuario_nombre || 'Admin P.'}</td>
                <td>${r.dni || '---'}</td>
                <td><span class="badge ${getBadgeClass(r.estado)}">${r.estado === 'bloqueada_admin' ? 'Bloqueo Admin' : (r.estado.charAt(0).toUpperCase() + r.estado.slice(1))}</span></td>
                <td><strong>${r.precio_total ? r.precio_total + '€' : '---'}</strong></td>
                <td>
                    <button class="btn-sm btn-view" onclick="verDetalles(${r.id})">🔍 Ver</button>
                    ${canCancel ? `<button class="btn-sm btn-cancel" onclick="cancelarReserva(${r.id})">❌ Anular</button>` : ''}
                </td>
            </tr>
        `}).join('');
    }

    window.verDetalles = (id) => {
        const r = reservasActuales.find(x => x.id == id);
        if (!r) return;
        
        detailsModalContent.innerHTML = `
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div><strong>ID Reserva:</strong> #${r.id}</div>
                <div><strong>Fecha Registro:</strong> ${r.fecha_registro || 'Desconocida'}</div>
                <div><strong>Estado:</strong> <span class="badge ${getBadgeClass(r.estado)}">${r.estado}</span></div>
                <div><strong>Código Acceso:</strong> ${r.codigo_acceso || '---'}</div>
            </div>
            <hr style="border:0; border-top:1px solid #eee; margin:1rem 0;">
            <p style="margin-bottom:0.5rem; color:#d15615; font-weight:bold;">Datos del Cliente</p>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div><strong>Nombre:</strong> ${r.usuario_nombre || '---'}</div>
                <div><strong>DNI:</strong> ${r.dni || '---'}</div>
                <div><strong>Email:</strong> ${r.usuario_email || '---'}</div>
                <div><strong>Teléfono:</strong> ${r.telefono || '---'}</div>
            </div>
            <hr style="border:0; border-top:1px solid #eee; margin:1rem 0;">
            <p style="margin-bottom:0.5rem; color:#d15615; font-weight:bold;">Datos de la Pista</p>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div><strong>Instalación:</strong> ${r.pista}</div>
                <div><strong>Precio Total:</strong> ${r.precio_total ? r.precio_total + ' €' : '---'}</div>
                <div><strong>Día:</strong> ${r.fecha.split('-').reverse().join('/')}</div>
                <div><strong>Hora:</strong> ${r.hora_inicio.substring(0,5)} a ${r.hora_fin.substring(0,5)}</div>
            </div>
        `;
        detailsModal.style.display = 'flex';
    };

    if (btnCloseDetails) btnCloseDetails.addEventListener('click', () => detailsModal.style.display = 'none');
    if (btnCloseDetails2) btnCloseDetails2.addEventListener('click', () => detailsModal.style.display = 'none');

    window.cancelarReserva = async (id) => {
        if (!confirm('¿Estás seguro de que deseas anular esta reserva del cliente? Esta acción no se puede deshacer.')) return;
        
        try {
            const resp = await fetch('../api/admin-reservas.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'cancelar', id: id })
            });
            const data = await resp.json();
            if (resp.ok && data.status === 'success') {
                showToast('Reserva anulada correctamente', 'success');
                // Refresh data by submitting form again
                document.getElementById('form-buscador').dispatchEvent(new Event('submit'));
                // Si la vista actual es bloqueos (modo de pista), también lo refrescamos
                loadSchedule();
            } else {
                showToast(data.message || 'Error al anular', 'error');
            }
        } catch (err) {
            showToast('Error de red', 'error');
        }
    };

    if (btnExportCsv) {
        btnExportCsv.addEventListener('click', () => {
            if (reservasActuales.length === 0) {
                alert("No hay datos para exportar. Haz una búsqueda primero.");
                return;
            }
            
            let csvContent = "ID,Instalacion,Fecha,Hora Inicio,Hora Fin,Estado,Total,Nombre Cliente,DNI,Email,Telefono,Codigo Acceso,Fecha Registro\n";
            reservasActuales.forEach(r => {
                const row = [
                    r.id, `"${r.pista}"`, r.fecha, r.hora_inicio, r.hora_fin, r.estado, 
                    r.precio_total || '', `"${r.usuario_nombre || ''}"`, r.dni || '', 
                    r.usuario_email || '', r.telefono || '', r.codigo_acceso || '', r.fecha_registro || ''
                ];
                csvContent += row.join(",") + "\n";
            });
            
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.setAttribute("href", url);
            link.setAttribute("download", `reservas_${new Date().toISOString().slice(0,10)}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    }

    // ── Logica Consultas ──
    if (formConsultas) {
        formConsultas.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const buscar = document.getElementById('c-buscar').value.trim();
            const tipo = document.getElementById('c-tipo').value;
            const estado = document.getElementById('c-estado').value;
            const fechaInicio = document.getElementById('c-fecha-inicio').value;
            
            let query = `../api/admin-consultas.php?action=get`;
            if (buscar) query += `&search=${encodeURIComponent(buscar)}`;
            if (tipo) query += `&tipo=${encodeURIComponent(tipo)}`;
            if (estado) query += `&estado=${encodeURIComponent(estado)}`;
            if (fechaInicio) query += `&fecha_inicio=${encodeURIComponent(fechaInicio)}`;
            
            tablaConsultasBody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Buscando...</td></tr>';
            
            try {
                const resp = await fetch(query);
                const data = await resp.json();
                
                if (resp.ok && data.success) {
                    consultasActuales = data.data;
                    renderTableConsultas(consultasActuales);
                } else {
                    tablaConsultasBody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:red;">Error: ${data.message}</td></tr>`;
                }
            } catch (err) {
                tablaConsultasBody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:red;">Error de conexión.</td></tr>`;
            }
        });
    }

    function renderTableConsultas(data) {
        if (data.length === 0) {
            tablaConsultasBody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No hay consultas.</td></tr>';
            return;
        }

        tablaConsultasBody.innerHTML = data.map(c => `
            <tr>
                <td><strong>${c.fecha_creacion.substring(0,10).split('-').reverse().join('/')}</strong></td>
                <td>${c.nombre} ${c.apellidos}</td>
                <td><small>${c.email}<br>${c.dni} - TEL: ${c.telefono}</small></td>
                <td><span class="badge" style="background-color:#e3f2fd; color:#1565c0;">${c.tipo_consulta.toUpperCase()}</span></td>
                <td>
                    <span class="badge ${c.estado === 'resuelta' ? 'reservada' : 'cancelada'}">${c.estado.toUpperCase()}</span>
                </td>
                <td>
                    <button class="btn-sm btn-view" onclick="verDetalleConsulta(${c.id})">🔍 Leer</button>
                </td>
            </tr>
        `).join('');
    }

    window.verDetalleConsulta = (id) => {
        const c = consultasActuales.find(x => x.id == id);
        if (!c) return;
        
        detailsModalContent.innerHTML = `
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div><strong>ID Consulta:</strong> #${c.id}</div>
                <div><strong>Fecha:</strong> ${c.fecha_creacion}</div>
                <div><strong>Remitente:</strong> ${c.nombre} ${c.apellidos}</div>
                <div><strong>DNI:</strong> ${c.dni}</div>
                <div><strong>Email:</strong> ${c.email}</div>
                <div><strong>Teléfono:</strong> ${c.telefono}</div>
                <div><strong>Tema:</strong> ${c.tipo_consulta}</div>
                <div><strong>Estado:</strong> <span class="badge ${c.estado === 'resuelta' ? 'reservada' : 'cancelada'}">${c.estado.toUpperCase()}</span></div>
            </div>
            <hr style="border:0; border-top:1px solid #eee; margin:1rem 0;">
            <p style="margin-bottom:0.5rem; color:#d15615; font-weight:bold;">Mensaje Original:</p>
            <div style="background-color: #f9f9f9; padding: 1rem; border-radius: 4px; white-space: pre-wrap;">${c.descripcion}</div>
        `;

        const modalActions = document.getElementById('details-modal-actions');
        modalActions.innerHTML = '';

        if (c.estado !== 'resuelta') {
            const btnResolve = document.createElement('button');
            btnResolve.className = 'btn-sm';
            btnResolve.style.backgroundColor = '#2e7d32';
            btnResolve.style.color = 'white';
            btnResolve.style.padding = '0.6rem 1.2rem';
            btnResolve.style.marginRight = '0.5rem';
            btnResolve.textContent = 'Marcar como Resuelta';
            btnResolve.onclick = () => resolverConsulta(c.id);
            modalActions.appendChild(btnResolve);
        }

        const btnClose = document.createElement('button');
        btnClose.className = 'btn-sm';
        btnClose.style.backgroundColor = '#ccc';
        btnClose.style.padding = '0.6rem 1.2rem';
        btnClose.textContent = 'Cerrar';
        btnClose.onclick = () => detailsModal.style.display = 'none';
        modalActions.appendChild(btnClose);

        detailsModal.style.display = 'flex';
    };

    window.resolverConsulta = async (id) => {
        if (!confirm('¿Marcar consulta como resuelta? (Esto enviará un email al usuario informándole).')) return;
        
        try {
            const fd = new FormData();
            fd.append('id', id);

            const resp = await fetch('../api/admin-consultas.php?action=resolve', {
                method: 'POST',
                body: fd
            });
            const data = await resp.json();
            if (resp.ok && data.success) {
                showToast('Consulta marcada como resuelta', 'success');
                detailsModal.style.display = 'none';
                formConsultas.dispatchEvent(new Event('submit'));
            } else {
                showToast(data.message || 'Error al resolver', 'error');
            }
        } catch (err) {
            showToast('Error de red', 'error');
        }
    };

    // ── Logica Anuncios ──
    async function loadAnuncios() {
        if (!tablaAnunciosBody) return;
        tablaAnunciosBody.innerHTML = '<tr><td colspan="5" style="text-align:center;">Cargando...</td></tr>';
        
        try {
            const resp = await fetch('../api/admin-anuncios.php?action=get');
            const data = await resp.json();
            if (resp.ok && data.success) {
                anunciosActuales = data.data;
                renderAnuncios();
            } else {
                tablaAnunciosBody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:red;">Error: ${data.message}</td></tr>`;
            }
        } catch (e) {
            tablaAnunciosBody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:red;">Error de conexión.</td></tr>`;
        }
    }

    function renderAnuncios() {
        if (anunciosActuales.length === 0) {
            tablaAnunciosBody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No hay anuncios creados.</td></tr>';
            return;
        }
        
        tablaAnunciosBody.innerHTML = anunciosActuales.map(a => `
            <tr>
                <td><strong>${a.fecha_creacion.split(' ')[0].split('-').reverse().join('/')}</strong></td>
                <td>${a.titulo}</td>
                <td>
                    <span class="badge" style="background-color:#eee; color:#333;">${a.tipo.toUpperCase()}</span>
                </td>
                <td>
                    <span class="badge ${a.activo == 1 ? 'reservada' : 'cancelada'}">${a.activo == 1 ? 'Activo' : 'Oculto'}</span>
                </td>
                <td>
                    <button class="btn-sm btn-view" onclick="toggleAnuncio(${a.id}, ${a.activo})">${a.activo == 1 ? '👁️ Ocultar' : '👁️ Mostrar'}</button>
                    <button class="btn-sm btn-cancel" onclick="deleteAnuncio(${a.id})">❌ Borrar</button>
                </td>
            </tr>
        `).join('');
    }

    if (formAnuncio) {
        formAnuncio.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btnObj = formAnuncio.querySelector('button[type="submit"]');
            btnObj.disabled = true;
            btnObj.textContent = 'Publicando...';
            
            try {
                const fd = new FormData();
                fd.append('action', 'create');
                fd.append('titulo', document.getElementById('a-titulo').value);
                fd.append('tipo', document.getElementById('a-tipo').value);
                fd.append('mensaje', document.getElementById('a-mensaje').value);

                const resp = await fetch('../api/admin-anuncios.php', { method: 'POST', body: fd });
                const data = await resp.json();

                if (resp.ok && data.success) {
                    showToast(data.message, 'success');
                    formAnuncio.reset();
                    loadAnuncios();
                } else {
                    showToast(data.message, 'error');
                }
            } catch (err) {
                showToast('Error de red', 'error');
            } finally {
                btnObj.disabled = false;
                btnObj.textContent = '📢 Publicar Anuncio';
            }
        });
    }

    window.toggleAnuncio = async (id, currentState) => {
        const newState = currentState == 1 ? 0 : 1;
        try {
            const fd = new FormData();
            fd.append('action', 'toggle');
            fd.append('id', id);
            fd.append('activo', newState);

            const resp = await fetch('../api/admin-anuncios.php', { method: 'POST', body: fd });
            const data = await resp.json();
            if (resp.ok && data.success) {
                loadAnuncios();
            } else {
                showToast(data.message, 'error');
            }
        } catch (e) {
            showToast('Error', 'error');
        }
    };

    window.deleteAnuncio = async (id) => {
        if(!confirm("¿Eliminar este anuncio permanentemente?")) return;
        try {
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);

            const resp = await fetch('../api/admin-anuncios.php', { method: 'POST', body: fd });
            const data = await resp.json();
            if (resp.ok && data.success) {
                loadAnuncios();
            } else {
                showToast(data.message, 'error');
            }
        } catch (e) {
            showToast('Error', 'error');
        }
    };

    // ── Logica Gestión de Admins ──
    const tabAdmins = document.getElementById('tab-admins');
    const viewAdmins = document.getElementById('view-admins');
    const tablaAdminsBody = document.getElementById('tabla-admins-body');
    const adminCountBadge = document.getElementById('admin-count-badge');
    const formCrearAdmin = document.getElementById('form-crear-admin');
    let adminsActuales = [];

    // Security modal elements
    const securityModal = document.getElementById('admin-security-modal');
    const securityModalTitle = document.getElementById('security-modal-title');
    const securityModalDesc = document.getElementById('security-modal-desc');
    const securityModalExtra = document.getElementById('security-modal-extra');
    const securityModalPassword = document.getElementById('security-modal-password');
    const btnSecurityCancel = document.getElementById('btn-security-cancel');
    const btnSecurityConfirm = document.getElementById('btn-security-confirm');
    let securityModalCallback = null; // Stores the function to call on confirm

    // Tab switch for Admins
    if (tabAdmins) {
        tabAdmins.addEventListener('click', (e) => {
            e.preventDefault();
            currentMode = 'admins';
            deactivateAllTabs();
            tabAdmins.classList.add('active');
            titlePage.textContent = '👥 Gestión de Administradores';
            hideAllViews();
            if (viewAdmins) viewAdmins.style.display = 'block';
            loadAdmins();
        });
    }

    async function loadAdmins() {
        if (!tablaAdminsBody) return;
        tablaAdminsBody.innerHTML = '<tr><td colspan="5" style="text-align:center;">Cargando administradores...</td></tr>';
        try {
            const resp = await fetch('../api/admin-users.php?action=get');
            const data = await resp.json();
            if (resp.ok && data.status === 'success') {
                adminsActuales = data.data;
                renderAdminsTable();
            } else {
                tablaAdminsBody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:red;">Error: ${data.message}</td></tr>`;
            }
        } catch (e) {
            tablaAdminsBody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:red;">Error de conexión.</td></tr>`;
        }
    }

    function renderAdminsTable() {
        if (adminCountBadge) adminCountBadge.textContent = `Total: ${adminsActuales.length}`;
        if (adminsActuales.length === 0) {
            tablaAdminsBody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No hay administradores.</td></tr>';
            return;
        }
        tablaAdminsBody.innerHTML = adminsActuales.map(a => {
            const ultimoAcceso = a.ultimo_acceso ? a.ultimo_acceso.substring(0,16).replace('T',' ') : 'Nunca';
            return `
            <tr>
                <td><strong>#${a.id}</strong></td>
                <td><strong>${a.usuario}</strong></td>
                <td>${ultimoAcceso}</td>
                <td>
                    <button class="btn-sm btn-view" onclick="resetPasswordAdmin(${a.id}, '${a.usuario}')">🔑 Cambiar Clave</button>
                    <button class="btn-sm btn-cancel" onclick="deleteAdmin(${a.id}, '${a.usuario}')">❌ Eliminar</button>
                </td>
            </tr>
            `;
        }).join('');
    }

    // ── Security Modal helpers ──
    function openSecurityModal(title, desc, extraHtml, callback) {
        securityModalTitle.textContent = title;
        securityModalDesc.innerHTML = desc;
        securityModalExtra.innerHTML = extraHtml || '';
        securityModalPassword.value = '';
        securityModalCallback = callback;
        securityModal.style.display = 'flex';
        securityModalPassword.focus();
    }

    if (btnSecurityCancel) {
        btnSecurityCancel.addEventListener('click', () => {
            securityModal.style.display = 'none';
            securityModalCallback = null;
        });
    }

    if (btnSecurityConfirm) {
        btnSecurityConfirm.addEventListener('click', () => {
            if (securityModalCallback) {
                securityModalCallback(securityModalPassword.value);
            }
        });
    }

    // ── Crear admin ──
    if (formCrearAdmin) {
        formCrearAdmin.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btnObj = formCrearAdmin.querySelector('button[type="submit"]');
            const nuevoUser = document.getElementById('new-admin-user').value.trim();
            const nuevaPass = document.getElementById('new-admin-pass').value;
            const authPass = document.getElementById('new-admin-auth').value;

            if (!nuevoUser || !nuevaPass || !authPass) {
                showToast('Completa todos los campos.', 'error');
                return;
            }
            if (nuevaPass.length < 8) {
                showToast('La contraseña debe tener al menos 8 caracteres.', 'error');
                return;
            }

            btnObj.disabled = true;
            btnObj.textContent = 'Creando...';

            try {
                const resp = await fetch('../api/admin-users.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'create',
                        nuevo_usuario: nuevoUser,
                        nueva_password: nuevaPass,
                        auth_password: authPass
                    })
                });
                const data = await resp.json();
                if (resp.ok && data.status === 'success') {
                    showToast(data.message, 'success');
                    formCrearAdmin.reset();
                    loadAdmins();
                } else {
                    showToast(data.message || 'Error al crear', 'error');
                }
            } catch (err) {
                showToast('Error de red.', 'error');
            } finally {
                btnObj.disabled = false;
                btnObj.textContent = '➕ Crear Administrador';
            }
        });
    }

    // ── Resetear contraseña vía modal ──
    window.resetPasswordAdmin = (id, username) => {
        openSecurityModal(
            'Cambiar Contraseña',
            `Vas a establecer una <strong>nueva contraseña</strong> para el usuario <strong>${username}</strong>.`,
            `<div class="control-group" style="margin-bottom:0;">
                <label for="reset-new-password">Nueva Contraseña</label>
                <input type="password" id="reset-new-password" placeholder="Mínimo 8 caracteres" style="padding:0.6rem; border:1px solid #ccc; border-radius:6px; width:100%; box-sizing:border-box;">
            </div>`,
            async (authPass) => {
                const newPass = document.getElementById('reset-new-password').value;
                if (!newPass || newPass.length < 8) {
                    showToast('La nueva contraseña debe tener al menos 8 caracteres.', 'error');
                    return;
                }
                if (!authPass) {
                    showToast('Introduce tu contraseña de confirmación.', 'error');
                    return;
                }

                btnSecurityConfirm.disabled = true;
                btnSecurityConfirm.textContent = 'Procesando...';

                try {
                    const resp = await fetch('../api/admin-users.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'reset',
                            target_id: id,
                            nueva_password: newPass,
                            auth_password: authPass
                        })
                    });
                    const data = await resp.json();
                    if (resp.ok && data.status === 'success') {
                        showToast(data.message, 'success');
                        securityModal.style.display = 'none';
                    } else {
                        showToast(data.message || 'Error', 'error');
                    }
                } catch (err) {
                    showToast('Error de red.', 'error');
                } finally {
                    btnSecurityConfirm.disabled = false;
                    btnSecurityConfirm.textContent = 'Confirmar';
                }
            }
        );
    };

    // ── Eliminar admin vía modal ──
    window.deleteAdmin = (id, username) => {
        openSecurityModal(
            'Eliminar Administrador',
            `⚠️ Vas a <strong>eliminar permanentemente</strong> al administrador <strong>${username}</strong>. Esta acción no se puede deshacer.`,
            '',
            async (authPass) => {
                if (!authPass) {
                    showToast('Introduce tu contraseña de confirmación.', 'error');
                    return;
                }

                btnSecurityConfirm.disabled = true;
                btnSecurityConfirm.textContent = 'Eliminando...';

                try {
                    const resp = await fetch('../api/admin-users.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'delete',
                            target_id: id,
                            auth_password: authPass
                        })
                    });
                    const data = await resp.json();
                    if (resp.ok && data.status === 'success') {
                        showToast(data.message, 'success');
                        securityModal.style.display = 'none';
                        loadAdmins();
                    } else {
                        showToast(data.message || 'Error', 'error');
                    }
                } catch (err) {
                    showToast('Error de red.', 'error');
                } finally {
                    btnSecurityConfirm.disabled = false;
                    btnSecurityConfirm.textContent = 'Confirmar';
                }
            }
        );
    };

    // ── CENTRO DE ACTIVIDAD ──
    let actividadData = [];

    // Cargar badge al inicio (reservas hoy)
    async function refreshBadgeActividad() {
        try {
            const resp = await fetch('../api/admin-actividad.php?action=get&solo_hoy=1&limite=1');
            const data = await resp.json();
            if (resp.ok && data.status === 'success' && badgeActividad) {
                const total = data.total_hoy || 0;
                badgeActividad.textContent = total;
                if (total > 0) badgeActividad.classList.add('visible');
                else badgeActividad.classList.remove('visible');
            }
        } catch(e) { /* silencioso */ }
    }
    refreshBadgeActividad();

    async function loadActividad() {
        const feed = document.getElementById('actividad-feed');
        if (!feed) return;
        feed.innerHTML = '<div class="actividad-loading">⏳ Cargando reservas...</div>';

        const filtroFecha = document.getElementById('act-filtro-dia')?.value || 'hoy';
        const filtroPista = document.getElementById('act-filtro-pista')?.value || '';
        const soloHoy = filtroFecha === 'hoy' ? '1' : '0';

        let url = `../api/admin-actividad.php?action=get&solo_hoy=${soloHoy}&limite=100`;
        if (filtroPista) url += `&pista=${encodeURIComponent(filtroPista)}`;

        try {
            const resp = await fetch(url);
            const data = await resp.json();

            if (resp.ok && data.status === 'success') {
                actividadData = data.data;
                const totalHoy = data.total_hoy || 0;

                // Update badge
                if (badgeActividad) {
                    badgeActividad.textContent = totalHoy;
                    if (totalHoy > 0) badgeActividad.classList.add('visible');
                    else badgeActividad.classList.remove('visible');
                }

                renderActividad(actividadData, totalHoy);
            } else {
                feed.innerHTML = `<div class="actividad-empty"><p style="color:red">Error al cargar datos.</p></div>`;
            }
        } catch(e) {
            feed.innerHTML = `<div class="actividad-empty"><p style="color:red">Error de conexión.</p></div>`;
        }
    }

    function renderActividad(data, totalHoy) {
        const feed = document.getElementById('actividad-feed');
        if (!feed) return;

        // Actualizar stats
        const verificadas = data.filter(r => r.verificado_admin == 1).length;
        const pendientes = data.filter(r => r.verificado_admin != 1).length;
        const statHoy = document.getElementById('stat-hoy');
        const statTotal = document.getElementById('stat-total');
        const statVerificadas = document.getElementById('stat-verificadas');
        const statPendientes = document.getElementById('stat-pendientes');
        if (statHoy) statHoy.textContent = totalHoy;
        if (statTotal) statTotal.textContent = data.length;
        if (statVerificadas) statVerificadas.textContent = verificadas;
        if (statPendientes) statPendientes.textContent = pendientes;

        if (data.length === 0) {
            feed.innerHTML = `
                <div class="actividad-empty">
                    <div class="empty-icon">🏟️</div>
                    <h3>No hay reservas para mostrar</h3>
                    <p>No se encontraron reservas activas con los filtros aplicados.</p>
                </div>
            `;
            return;
        }

        // Calcular fecha de hoy para comparar
        const now = new Date();
        const todayStr = now.getFullYear() + '-' +
            String(now.getMonth()+1).padStart(2,'0') + '-' +
            String(now.getDate()).padStart(2,'0');
        const currentTimeInt = now.getHours() * 60 + now.getMinutes();

        feed.innerHTML = '';

        data.forEach(r => {
            const esHoy = r.fecha === todayStr;
            const isVerificada = r.verificado_admin == 1;
            const [h, m] = r.hora_inicio.substring(0,5).split(':').map(Number);
            const slotInt = h * 60 + m;
            const isPast = r.fecha < todayStr || (esHoy && slotInt < currentTimeInt);
            const isActive = esHoy && slotInt <= currentTimeInt && (slotInt + 120) > currentTimeInt;

            let cardClass = '';
            if (isVerificada) cardClass = 'verificada';
            else if (esHoy) cardClass = 'es-hoy';
            else if (isPast) cardClass = 'pasada';

            const fechaDisplay = r.fecha.split('-').reverse().join('/');
            const horaInicio = r.hora_inicio.substring(0,5);
            const horaFin = r.hora_fin.substring(0,5);

            let diaLabel = '';
            if (esHoy) diaLabel = `<span class="aviso-hoy-badge">HOY</span>`;
            else if (isActive) diaLabel = `<span class="aviso-hoy-badge" style="background:#fff8e1;color:#f57f17">EN CURSO</span>`;
            else if (isPast) diaLabel = `<span class="aviso-hoy-badge" style="background:#f5f5f5;color:#9e9e9e">PASADA</span>`;

            const codigoAcceso = r.codigo_acceso || '---';
            const btnVerLabel = isVerificada ? '✔ Verificado' : '✓ Verificar Identidad';
            const btnVerClass = isVerificada ? 'btn-verificar verificado' : 'btn-verificar';
            const btnVerTitle = isVerificada ? 'Haz clic para retirar la verificación' : 'Marcar al cliente como verificado presencialmente';

            const fechaVerif = r.fecha_verificacion
                ? `<span class="aviso-timestamp">Verificado el ${r.fecha_verificacion.substring(0,16).replace('T',' ')}</span>`
                : '';

            const card = document.createElement('div');
            card.className = `aviso-card ${cardClass}`;
            card.id = `aviso-${r.id}`;
            card.innerHTML = `
                <div>
                    <div class="aviso-header">
                        <span class="aviso-pista-badge">${r.pista}</span>
                        <span class="aviso-time-badge">${horaInicio} – ${horaFin}</span>
                        ${diaLabel}
                        <span style="margin-left:auto; font-size:0.8rem; color:#aaa;">${fechaDisplay}</span>
                    </div>
                    <div class="aviso-body">
                        <div>
                            <span class="field-label">Cliente</span>
                            <span class="field-value">${r.usuario_nombre || r.cliente_nombre || '---'}</span>
                        </div>
                        <div>
                            <span class="field-label">DNI</span>
                            <span class="field-value">${r.dni || '---'}</span>
                        </div>
                        <div>
                            <span class="field-label">Email</span>
                            <span class="field-value">${r.usuario_email || '---'}</span>
                        </div>
                        <div>
                            <span class="field-label">Teléfono</span>
                            <span class="field-value">${r.telefono || '---'}</span>
                        </div>
                        <div class="codigo-acceso-display">
                            <span class="codigo-acceso-label">🔑 Código de acceso</span>
                            <span class="codigo-acceso-value" title="Haz clic para copiar" onclick="copiarCodigo('${codigoAcceso}', this)">${codigoAcceso}</span>
                            <button class="codigo-acceso-copy" onclick="copiarCodigo('${codigoAcceso}', this)">📋 Copiar</button>
                        </div>
                    </div>
                </div>
                <div class="aviso-actions">
                    <button class="${btnVerClass}" title="${btnVerTitle}" onclick="toggleVerificar(${r.id}, ${isVerificada ? 1 : 0})">${btnVerLabel}</button>
                    ${fechaVerif}
                    <span class="aviso-timestamp">Reserva #${r.id}</span>
                </div>
            `;
            feed.appendChild(card);
        });
    }

    window.copiarCodigo = (codigo, el) => {
        if (!codigo || codigo === '---') return;
        navigator.clipboard.writeText(codigo).then(() => {
            const original = el.textContent;
            el.textContent = '\u2714 Copiado!';
            setTimeout(() => { el.textContent = original; }, 1800);
        }).catch(() => {
            showToast('\u00a1C\u00f3digo copiado!', 'success');
        });
    };

    window.toggleVerificar = async (id, currentState) => {
        const accion = currentState == 1 ? 'desverificar' : 'verificar';
        try {
            const resp = await fetch('../api/admin-actividad.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: accion, id: id })
            });
            const data = await resp.json();
            if (resp.ok && data.status === 'success') {
                showToast(data.message, 'success');
                loadActividad();
            } else {
                showToast(data.message || 'Error', 'error');
            }
        } catch(e) {
            showToast('Error de conexi\u00f3n', 'error');
        }
    };

    // Bot\u00f3n actualizar actividad
    const btnRecargarActividad = document.getElementById('btn-recargar-actividad');
    if (btnRecargarActividad) {
        btnRecargarActividad.addEventListener('click', loadActividad);
    }
    // Cambio de filtros tambi\u00e9n recarga
    document.getElementById('act-filtro-dia')?.addEventListener('change', loadActividad);
    document.getElementById('act-filtro-pista')?.addEventListener('change', loadActividad);
    // ── CARGA DE ESTADÍSTICAS ──
    let chartPistas = null;
    let chartDias = null;

    async function loadEstadisticas() {
        try {
            const resp = await fetch('../api/admin-stats.php');
            const data = await resp.json();
            if (data.status === 'success') {
                const d = data.data;

                document.getElementById('stat-ingresos-actual').textContent = d.mes_actual.ingresos.toFixed(2) + ' €';
                document.getElementById('stat-reservas-actual').textContent = d.mes_actual.reservas;
                document.getElementById('stat-ingresos-pasado').textContent = d.mes_pasado.ingresos.toFixed(2) + ' €';

                renderChartPistas(d.pistas);
                renderChartDias(d.ultimos_dias);
            }
        } catch (e) {
            console.error("Error cargando stats", e);
        }
    }

    function renderChartPistas(pistasData) {
        const ctx = document.getElementById('chartPistas');
        if (!ctx) return;
        if (chartPistas) chartPistas.destroy();

        if (pistasData.length === 0) {
            // No destruimos el canvas, solo ponemos texto encima si acaso
            return;
        }

        const labels = pistasData.map(p => p.pista);
        const vals = pistasData.map(p => p.cantidad);

        chartPistas = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: vals,
                    backgroundColor: ['#0b1c3c', '#d15615', '#2e7d32', '#fbc02d', '#8e24aa', '#00acc1']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    function renderChartDias(diasData) {
        const ctx = document.getElementById('chartDias');
        if (!ctx) return;
        if (chartDias) chartDias.destroy();

        if (diasData.length === 0) {
            return;
        }

        const labels = diasData.map(d => {
            const date = new Date(d.fecha);
            return date.toLocaleDateString('es-ES', { weekday: 'short', day: '2-digit' });
        });
        const vals = diasData.map(d => d.cantidad);

        chartDias = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Reservas por día',
                    data: vals,
                    borderColor: '#d15615',
                    backgroundColor: 'rgba(209, 86, 21, 0.2)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    }

    console.log("Admin Dashboard JS v2.5.1 Loaded");
});
