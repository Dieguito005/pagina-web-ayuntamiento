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
    const tabBloqueos = document.getElementById('tab-bloqueos');
    const tabPrecios = document.getElementById('tab-precios');
    const titlePage = document.getElementById('admin-page-title');
    let currentMode = 'bloqueos'; // 'bloqueos', 'precios', 'buscador' o 'consultas'

    // Elementos Consultas
    const viewConsultas = document.getElementById('view-consultas');
    const formConsultas = document.getElementById('form-consultas');
    const tablaConsultasBody = document.getElementById('tabla-consultas-body');
    let consultasActuales = [];

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

    // ── Logica Cambio de Tabs ──
    if (tabBloqueos && tabPrecios && tabBuscador) {
        tabBuscador.addEventListener('click', (e) => {
            e.preventDefault();
            currentMode = 'buscador';
            tabBuscador.classList.add('active');
            tabConsultas?.classList.remove('active');
            tabBloqueos.classList.remove('active');
            tabPrecios.classList.remove('active');
            titlePage.textContent = 'Buscador e Historial';
            viewBuscador.style.display = 'block';
            viewBloqueos.style.display = 'none';
            if (viewConsultas) viewConsultas.style.display = 'none';
        });

        if (tabConsultas) {
            tabConsultas.addEventListener('click', (e) => {
                e.preventDefault();
                currentMode = 'consultas';
                tabConsultas.classList.add('active');
                tabBuscador.classList.remove('active');
                tabBloqueos.classList.remove('active');
                tabPrecios.classList.remove('active');
                titlePage.textContent = 'Buzón de Consultas';
                viewConsultas.style.display = 'block';
                viewBuscador.style.display = 'none';
                viewBloqueos.style.display = 'none';
                if (actionBarBloqueos) actionBarBloqueos.style.display = 'none';
                // Trigger form submission
                if (formConsultas) formConsultas.dispatchEvent(new Event('submit'));
            });
        }

        tabBloqueos.addEventListener('click', (e) => {
            e.preventDefault();
            currentMode = 'bloqueos';
            tabBloqueos.classList.add('active');
            tabConsultas?.classList.remove('active');
            tabPrecios.classList.remove('active');
            tabBuscador.classList.remove('active');
            titlePage.textContent = 'Gestión de Bloqueos';
            viewBloqueos.style.display = 'block';
            viewBuscador.style.display = 'none';
            if (viewConsultas) viewConsultas.style.display = 'none';
            if (actionBarBloqueos) actionBarBloqueos.style.display = 'flex';
            loadSchedule();
        });
        
        tabPrecios.addEventListener('click', (e) => {
            e.preventDefault();
            currentMode = 'precios';
            tabPrecios.classList.add('active');
            tabConsultas?.classList.remove('active');
            tabBloqueos.classList.remove('active');
            tabBuscador.classList.remove('active');
            titlePage.textContent = 'Edición de Precios';
            viewBloqueos.style.display = 'block';
            viewBuscador.style.display = 'none';
            if (viewConsultas) viewConsultas.style.display = 'none';
            if (actionBarBloqueos) actionBarBloqueos.style.display = 'none';
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
                
                let extraHtml = '';
                if (precioObj) {
                    let base = precioObj.precio_base !== null ? precioObj.precio_base.toFixed(2) : '-';
                    let luz = precioObj.precio_luz !== null ? precioObj.precio_luz.toFixed(2) : '-';
                    extraHtml = `<span class="price-tag">C: Base ${base}€ | Luz ${luz}€</span>`;
                }

                if (ocupadoObj) {
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

        tablaReservasBody.innerHTML = data.map(r => `
            <tr>
                <td><strong>${r.fecha.split('-').reverse().join('/')}</strong><br><span style="color:#666;font-size:0.85rem;">${r.hora_inicio.substring(0,5)} - ${r.hora_fin.substring(0,5)}</span></td>
                <td>${r.pista}</td>
                <td>${r.usuario_nombre || 'Admin P.'}</td>
                <td>${r.dni || '---'}</td>
                <td><span class="badge ${getBadgeClass(r.estado)}">${r.estado === 'bloqueada_admin' ? 'Bloqueo Admin' : (r.estado.charAt(0).toUpperCase() + r.estado.slice(1))}</span></td>
                <td><strong>${r.precio_total ? r.precio_total + '€' : '---'}</strong></td>
                <td>
                    <button class="btn-sm btn-view" onclick="verDetalles(${r.id})">🔍 Ver</button>
                    ${r.estado !== 'cancelada' && r.estado !== 'bloqueada_admin' ? `<button class="btn-sm btn-cancel" onclick="cancelarReserva(${r.id})">❌ Anular</button>` : ''}
                </td>
            </tr>
        `).join('');
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
});
