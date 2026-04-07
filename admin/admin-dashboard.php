<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Alquileres Tarancón</title>
    <link rel="stylesheet" href="../css/home.css?v=2.0.0">
    <style>
        .admin-layout {
            display: flex;
            min-height: calc(100vh - 100px);
            background-color: #f4f6f8;
        }
        .admin-sidebar {
            width: 290px;
            min-width: 290px;
            background-color: #0b1c3c;
            color: white;
            padding: 2rem 1.2rem;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        .admin-sidebar h3 {
            margin-bottom: 1.5rem;
            font-size: 1.15rem;
            color: #d15615;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 0.8rem;
        }
        .admin-sidebar p { margin-bottom: 0.3rem; font-size: 0.9rem; color: #ccc;}
        .admin-nav { margin-top: 1.5rem; flex-grow: 1; }
        /* Sección del nav */
        .nav-group {
            margin-bottom: 1.2rem;
        }
        .nav-group-label {
            font-size: 0.68rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.35);
            padding: 0 0.6rem;
            margin-bottom: 0.45rem;
            display: block;
        }
        .admin-nav a {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            color: rgba(255,255,255,0.82);
            padding: 0.65rem 0.9rem;
            text-decoration: none;
            border-radius: 7px;
            margin-bottom: 0.2rem;
            background-color: transparent;
            transition: background 0.2s, color 0.2s;
            font-size: 0.92rem;
            position: relative;
        }
        .admin-nav a:hover {
            background-color: rgba(255,255,255,0.08);
            color: white;
        }
        .admin-nav a.active {
            background-color: #d15615;
            color: white;
            font-weight: 600;
        }
        .nav-badge {
            margin-left: auto;
            background-color: #d15615;
            color: white;
            font-size: 0.7rem;
            font-weight: 700;
            border-radius: 999px;
            padding: 1px 7px;
            display: none;
        }
        .nav-badge.visible { display: inline-block; }
        .admin-nav a.active .nav-badge { background-color: rgba(255,255,255,0.3); }
        .nav-divider {
            border: none;
            border-top: 1px solid rgba(255,255,255,0.08);
            margin: 0.5rem 0 1rem;
        }
        
        .time-slot .price-tag {
            display: block;
            margin-top: 0.3rem;
            font-size: 0.75rem;
            color: #d15615;
            font-weight: bold;
        }
        
        .admin-main {
            flex-grow: 1;
            padding: 2.5rem;
            overflow-y: auto;
        }
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        .admin-header h2 { color: #0b1c3c; font-size: 2rem; }
        .btn-logout {
            background-color: transparent;
            color: #d32f2f;
            border: 1px solid #d32f2f;
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-logout:hover { background-color: #d32f2f; color: white; }
        
        .dashboard-controls {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            display: flex;
            gap: 1.5rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .control-group { display: flex; flex-direction: column; gap: 0.4rem; }
        .control-group label { font-weight: 600; color: #333; font-size: 0.9rem; }
        .control-group select {
            padding: 0.6rem 1rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
            min-width: 200px;
        }
        .control-group select:focus { outline: none; border-color: #d15615; }
        
        .schedule-grid-admin {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            min-height: 400px;
        }
        
        /* Reusing some visual styles from tenisc.css roughly */
        .time-block { margin-bottom: 2rem; }
        .block-title { font-size: 1.1rem; color: #0b1c3c; margin-bottom: 1rem; border-bottom: 2px solid #f0f0f0; padding-bottom: 0.5rem; }
        .slots-container { display: flex; flex-wrap: wrap; gap: 1rem; }
        .time-slot {
            width: calc(25% - 0.75rem);
            min-width: 140px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        .time-slot.available:hover { border-color: #d15615; box-shadow: 0 4px 10px rgba(209, 86, 21, 0.15); }
        .time-slot.selected { background-color: #fff4e5; border-color: #d15615; }
        .time-slot.disabled { background-color: #f5f5f5; border-color: #eee; cursor: not-allowed; opacity: 0.7;}
        .time-slot.blocked_admin { background-color: #ffebee; border-color: #ffcdd2; cursor: pointer; }
        .time-slot.blocked_admin:hover { border-color: #d32f2f; box-shadow: 0 4px 10px rgba(211, 47, 47, 0.15); }
        .time-slot.booked_user { background-color: #e8f5e9; border-color: #c8e6c9; cursor: not-allowed; }
        
        .time-slot .time { display: block; font-size: 1.15rem; font-weight: 700; color: #333; margin-bottom: 0.3rem;}
        .time-slot .status { font-size: 0.85rem; font-weight: 600; color: #666; }
        
        .time-slot.blocked_admin .status { color: #c62828; }
        .time-slot.booked_user .status { color: #2e7d32; }
        .time-slot.selected .status { color: #d15615; }
        
        /* Estilos Buscador e Historial */
        .search-filters {
            background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 2rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: end;
        }
        .search-filters .control-group { margin-bottom: 0; }
        .search-filters input, .search-filters select {
            width: 100%; box-sizing: border-box;
            padding: 0.6rem 1rem; border: 1px solid #ccc; border-radius: 6px;
            font-size: 0.95rem; background-color: #fff; transition: all 0.3s;
        }
        .search-filters input:focus, .search-filters select:focus {
            outline: none; border-color: #d15615; box-shadow: 0 0 0 3px rgba(209, 86, 21, 0.1);
        }
        .search-filters label {
            font-weight: 600; color: #555; font-size: 0.85rem; margin-bottom: 0.4rem; display: block; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .btn-search { background-color: #0b1c3c; color: white; border: none; padding: 0.6rem 1.5rem; border-radius: 6px; font-weight: 600; cursor: pointer; height: 42px; }
        .btn-search:hover { background-color: #1a3673; }
        .btn-export { background-color: #2e7d32; color: white; border: none; padding: 0.6rem 1.5rem; border-radius: 6px; font-weight: 600; cursor: pointer; height: 42px; }
        .btn-export:hover { background-color: #1b5e20; }
        
        .table-container { background: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow-x: auto; margin-bottom: 2rem; }
        .admin-table { width: 100%; border-collapse: collapse; min-width: 800px; }
        .admin-table th, .admin-table td { padding: 1rem; text-align: left; border-bottom: 1px solid #eee; font-size: 0.95rem; }
        .admin-table th { background-color: #f8f9fa; font-weight: 600; color: #333; }
        .badge { padding: 0.3rem 0.6rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; display: inline-block; }
        .badge.reservada { background-color: #e8f5e9; color: #2e7d32; }
        .badge.cancelada { background-color: #ffebee; color: #c62828; }
        .badge.bloqueada_admin { background-color: #fff3e0; color: #ef6c00; }
        
        .btn-sm { padding: 0.4rem 0.8rem; font-size: 0.85rem; border-radius: 4px; cursor: pointer; border: none; font-weight: 600; }
        .btn-view { background-color: #e3f2fd; color: #1565c0; margin-right: 0.5rem; }
        .btn-cancel { background-color: #fbe9e7; color: #d32f2f; }
        
        .action-bar {
            margin-top: 2rem;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        .btn-block {
            background-color: #d32f2f;
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-block:hover { background-color: #b71c1c; }
        .btn-block:disabled { background-color: #ccc; cursor: not-allowed; }
        
        .btn-unblock {
            background-color: #f57c00;
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-unblock:hover { background-color: #ef6c00; }
        .btn-unblock:disabled { background-color: #ccc; cursor: not-allowed; }
        
        /* Notificaciones toast */
        #toast {
            visibility: hidden;
            min-width: 250px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 1rem;
            position: fixed;
            z-index: 1000;
            right: 30px;
            bottom: 30px;
            font-weight: 600;
        }
        #toast.show {
            visibility: visible;
            animation: fadein 0.5s, fadeout 0.5s 2.5s;
        }
        #toast.success { background-color: #2e7d32; }
        #toast.error { background-color: #c62828; }
        
        @keyframes fadein { from {bottom: 0; opacity: 0;} to {bottom: 30px; opacity: 1;} }
        @keyframes fadeout { from {bottom: 30px; opacity: 1;} to {bottom: 0; opacity: 0;} }

        /* ── Centro de Actividad ── */
        .actividad-controls {
            background: white;
            padding: 1.2rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .actividad-controls .control-group { margin-bottom: 0; }
        .actividad-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.2rem 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }
        .stat-card .stat-label { font-size: 0.8rem; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .stat-value { font-size: 2rem; font-weight: 800; color: #0b1c3c; line-height: 1; }
        .stat-card .stat-value.accent { color: #d15615; }
        .actividad-feed {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .aviso-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            padding: 1.3rem 1.5rem;
            border-left: 4px solid #2e7d32;
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 1rem;
            align-items: start;
            transition: box-shadow 0.2s;
        }
        .aviso-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .aviso-card.es-hoy { border-left-color: #1565c0; }
        .aviso-card.verificada { border-left-color: #9e9e9e; opacity: 0.7; }
        .aviso-card.pasada { border-left-color: #aaa; }
        .aviso-header {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 0.8rem;
            flex-wrap: wrap;
        }
        .aviso-pista-badge {
            background: #0b1c3c;
            color: white;
            font-size: 0.78rem;
            font-weight: 700;
            padding: 0.28rem 0.7rem;
            border-radius: 20px;
            white-space: nowrap;
        }
        .aviso-time-badge {
            background: #fff4e5;
            color: #d15615;
            font-size: 0.85rem;
            font-weight: 700;
            padding: 0.28rem 0.7rem;
            border-radius: 20px;
        }
        .aviso-hoy-badge {
            background: #e3f2fd;
            color: #1565c0;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.28rem 0.7rem;
            border-radius: 20px;
        }
        .aviso-body {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.6rem 1.5rem;
            font-size: 0.9rem;
        }
        .aviso-body .field-label { font-weight: 600; color: #555; font-size: 0.78rem; display: block; margin-bottom: 0.1rem; }
        .aviso-body .field-value { color: #222; font-weight: 500; }
        .codigo-acceso-display {
            background: linear-gradient(135deg, #0b1c3c 0%, #1a3673 100%);
            border-radius: 8px;
            padding: 0.7rem 1rem;
            margin-top: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.8rem;
            grid-column: 1 / -1;
        }
        .codigo-acceso-label { color: rgba(255,255,255,0.7); font-size: 0.78rem; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; }
        .codigo-acceso-value {
            color: white;
            font-size: 1.4rem;
            font-weight: 800;
            letter-spacing: 3px;
            font-family: 'Courier New', monospace;
            cursor: pointer;
        }
        .codigo-acceso-copy {
            background: rgba(255,255,255,0.15);
            border: none;
            color: white;
            padding: 0.35rem 0.7rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: background 0.2s;
            white-space: nowrap;
        }
        .codigo-acceso-copy:hover { background: rgba(255,255,255,0.25); }
        .aviso-actions { display: flex; flex-direction: column; gap: 0.5rem; align-items: flex-end; min-width: 130px; }
        .btn-verificar {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1.5px solid #c8e6c9;
            padding: 0.5rem 1rem;
            border-radius: 7px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 700;
            transition: all 0.2s;
            white-space: nowrap;
        }
        .btn-verificar:hover { background: #2e7d32; color: white; }
        .btn-verificar.verificado {
            background: #f5f5f5;
            color: #9e9e9e;
            border-color: #e0e0e0;
        }
        .btn-verificar.verificado:hover { background: #ffebee; color: #c62828; border-color: #ffcdd2; }
        .aviso-timestamp { font-size: 0.78rem; color: #aaa; text-align: right; }
        .actividad-empty {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            color: #888;
        }
        .actividad-empty .empty-icon { font-size: 3rem; margin-bottom: 1rem; }
        .actividad-empty h3 { color: #555; margin-bottom: 0.5rem; }
        .actividad-loading {
            text-align: center;
            padding: 2rem;
            color: #666;
        }
    </style>
</head>
<body>

    <header class="main-header" style="position: sticky; z-index: 100;">
        <div class="header-container">
            <div class="header-logo">
                <a href="../html/home.php" class="logo-home">
                    <div class="logo-image-box">
                        <img class="logo-ayuntamiento" src="../assets/imagenes/LOGO-AYUNTAMIENTO-BLANCO.png" alt="Logo">
                    </div>
                </a>
            </div>
            <div class="header-title">
                <h1> Portal Administrador </h1>
            </div>
            <div style="width: 120px;"></div>
        </div>
    </header>

    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <h3>⚙️ Panel Admin</h3>
            <p style="font-size:0.8rem; color:#aaa;">Sesión iniciada como:</p>
            <p><strong style="color:white;"><?= htmlspecialchars($_SESSION['admin_usuario'] ?? 'Admin') ?></strong></p>
            
            <nav class="admin-nav">

                <!-- GRUPO: PISTAS -->
                <div class="nav-group">
                    <span class="nav-group-label">🏟️ Pistas</span>
                    <a href="#" id="tab-bloqueos" class="active">
                        <span>📅</span> Calendario de Bloqueos
                    </a>
                    <a href="#" id="tab-precios">
                        <span>💰</span> Editar Precios
                    </a>
                </div>

                <hr class="nav-divider">

                <!-- GRUPO: USUARIOS -->
                <div class="nav-group">
                    <span class="nav-group-label">👤 Usuarios y Reservas</span>
                    <a href="#" id="tab-actividad">
                        <span>🔔</span> Centro de Actividad
                        <span class="nav-badge" id="badge-actividad">0</span>
                    </a>
                    <a href="#" id="tab-buscador">
                        <span>🔍</span> Buscador e Historial
                    </a>
                </div>

                <hr class="nav-divider">

                <!-- GRUPO: COMUNICACIÓN -->
                <div class="nav-group">
                    <span class="nav-group-label">💬 Comunicación</span>
                    <a href="#" id="tab-consultas">
                        <span>📬</span> Buzón de Consultas
                    </a>
                    <a href="#" id="tab-anuncios">
                        <span>📢</span> Anuncios y Novedades
                    </a>
                </div>

                <hr class="nav-divider">

                <!-- GRUPO: SISTEMA -->
                <div class="nav-group">
                    <span class="nav-group-label">🛠️ Sistema</span>
                    <a href="#" id="tab-admins">
                        <span>👥</span> Gestión de Admins
                    </a>
                </div>

            </nav>
        </aside>

        <!-- Contenido principal -->
        <main class="admin-main">
            <div class="admin-header">
                <h2 id="admin-page-title">Gestión de Bloqueos</h2>
                <a href="admin-logout.php" class="btn-logout">Cerrar Sesión</a>
            </div>

            <div id="view-bloqueos-precios">
                <div class="dashboard-controls">
                    <div class="control-group">
                        <label for="pista-select">Selecciona la Pista</label>
                        <select id="pista-select">
                            <option value="Tenis">Pista de Tenis</option>
                            <option value="Pádel Cubierta">Pádel Cubierta</option>
                            <option value="Pádel Descubierta">Pádel Descubierta</option>
                        </select>
                    </div>
                    
                    <div class="control-group">
                        <label for="fecha-select">Selecciona la Fecha</label>
                        <select id="fecha-select">
                            <!-- Generado por JS: próximos 30 días -->
                        </select>
                    </div>
                </div>

                <div class="schedule-grid-admin" id="admin-schedule-grid">
                    <p>Cargando horarios...</p>
                </div>
                
                <div class="action-bar" id="action-bar-bloqueos">
                    <button class="btn-block" id="btn-block-slot" disabled>Bloquear Horario Seleccionado</button>
                </div>
            </div>

            <!-- VIEW: Centro de Actividad -->
            <div id="view-actividad" style="display:none;">

                <!-- Controles de filtro -->
                <div class="actividad-controls">
                    <div class="control-group">
                        <label for="act-filtro-dia">Mostrar</label>
                        <select id="act-filtro-dia">
                            <option value="hoy">Solo hoy</option>
                            <option value="proximas">Próximas 72 horas</option>
                        </select>
                    </div>
                    <div class="control-group">
                        <label for="act-filtro-pista">Pista</label>
                        <select id="act-filtro-pista">
                            <option value="">Todas las pistas</option>
                            <option value="Tenis">Pista de Tenis</option>
                            <option value="Pádel Cubierta">Pádel Cubierta</option>
                            <option value="Pádel Descubierta">Pádel Descubierta</option>
                        </select>
                    </div>
                    <button class="btn-search" id="btn-recargar-actividad" style="margin-top:1.4rem;">🔄 Actualizar</button>
                </div>

                <!-- Stats del día -->
                <div class="actividad-stats">
                    <div class="stat-card">
                        <span class="stat-label">Reservas hoy</span>
                        <span class="stat-value accent" id="stat-hoy">--</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Mostrando</span>
                        <span class="stat-value" id="stat-total">--</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Verificadas</span>
                        <span class="stat-value" id="stat-verificadas">--</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Pendientes</span>
                        <span class="stat-value accent" id="stat-pendientes">--</span>
                    </div>
                </div>

                <!-- Feed de avisos -->
                <div class="actividad-feed" id="actividad-feed">
                    <div class="actividad-loading">Cargando actividad...</div>
                </div>
            </div>

            <!-- VIEW: Buscador e Historial -->
            <div id="view-buscador" style="display:none;">
                <form id="form-buscador" class="search-filters">
                    <div class="control-group">
                        <label>DNI Cliente</label>
                        <input type="text" id="s-dni" placeholder="Ej: 12345678Z">
                    </div>
                    <div class="control-group">
                        <label>Nombre</label>
                        <input type="text" id="s-nombre" placeholder="Nombre completo">
                    </div>
                    <div class="control-group">
                        <label>Pista</label>
                        <select id="s-pista">
                            <option value="">Todas</option>
                            <option value="Tenis">Pista de Tenis</option>
                            <option value="Pádel Cubierta">Pádel Cubierta</option>
                            <option value="Pádel Descubierta">Pádel Descubierta</option>
                        </select>
                    </div>
                    <div class="control-group">
                        <label>Fecha Desde</label>
                        <input type="date" id="s-fecha-inicio">
                    </div>
                    <div class="control-group">
                        <label>Estado</label>
                        <select id="s-estado">
                            <option value="">Todos</option>
                            <option value="reservada">Reservada</option>
                            <option value="bloqueada_admin">Bloqueo Admin</option>
                            <option value="cancelada">Cancelada</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-search">Buscar</button>
                    <button type="button" class="btn-export" id="btn-export-csv" title="Exportar resultados a CSV">⬇️ CSV</button>
                </form>

                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Fecha y Hora</th>
                                <th>Pista</th>
                                <th>Cliente</th>
                                <th>DNI</th>
                                <th>Estado</th>
                                <th>Total</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-reservas-body">
                            <tr><td colspan="7" style="text-align:center;">Realiza una búsqueda para ver resultados.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- VIEW: Buzón de Consultas -->
            <div id="view-consultas" style="display:none;">
                <form id="form-consultas" class="search-filters">
                    <div class="control-group">
                        <label>DNI / Correo</label>
                        <input type="text" id="c-buscar" placeholder="Buscar usuario...">
                    </div>
                    <div class="control-group">
                        <label>Tema</label>
                        <select id="c-tipo">
                            <option value="">Todos</option>
                            <option value="reserva">Reservas</option>
                            <option value="tarifas">Tarifas</option>
                            <option value="quejas">Quejas</option>
                            <option value="otros">Otros</option>
                        </select>
                    </div>
                    <div class="control-group">
                        <label>Estado</label>
                        <select id="c-estado">
                            <option value="pendiente">Pendiente</option>
                            <option value="resuelta">Resuelta</option>
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div class="control-group">
                        <label>Fecha Desde</label>
                        <input type="date" id="c-fecha-inicio">
                    </div>
                    <button type="submit" class="btn-search">Buscar Consultas</button>
                </form>

                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Remitente</th>
                                <th>DNI / Datos</th>
                                <th>Tema</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-consultas-body">
                            <tr><td colspan="6" style="text-align:center;">Cargando consultas...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- VIEW: Gestión de Administradores -->
            <div id="view-admins" style="display:none;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                    <h3 style="margin:0; color:#0b1c3c;">Administradores Registrados</h3>
                    <span class="badge" style="background-color:#e3f2fd; color:#1565c0; font-size:0.9rem; padding:0.4rem 1rem;" id="admin-count-badge">Total: --</span>
                </div>

                <div class="table-container" style="margin-bottom:2rem;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Usuario</th>
                                <th>Último Acceso</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-admins-body">
                            <tr><td colspan="5" style="text-align:center;">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>

                <h3 style="color:#0b1c3c; border-bottom:2px solid #f0f0f0; padding-bottom:0.5rem;">Añadir Nuevo Administrador</h3>
                <form id="form-crear-admin" class="search-filters" style="margin-top:1rem;">
                    <div class="control-group">
                        <label>Nombre de Usuario</label>
                        <input type="text" id="new-admin-user" placeholder="Ej: admin_pedro" required minlength="3">
                    </div>
                    <div class="control-group">
                        <label>Contraseña Inicial</label>
                        <input type="password" id="new-admin-pass" placeholder="Mínimo 8 caracteres" required minlength="8">
                    </div>
                    <div class="control-group">
                        <label>Tu Contraseña (confirmar)</label>
                        <input type="password" id="new-admin-auth" placeholder="Tu clave actual" required>
                    </div>
                    <button type="submit" class="btn-search">➕ Crear Administrador</button>
                </form>
            </div>

            <!-- VIEW: Anuncios y Novedades -->
            <div id="view-anuncios" style="display:none;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                    <h3>Publicar Nuevo Anuncio</h3>
                </div>
                <form id="form-nuevo-anuncio" class="search-filters" style="margin-bottom:2rem;">
                    <div class="control-group">
                        <label>Título del Anuncio</label>
                        <input type="text" id="a-titulo" placeholder="Ej. Pistas cerradas por lluvia" required>
                    </div>
                    <div class="control-group">
                        <label>Tipo / Estilo</label>
                        <select id="a-tipo" required>
                            <option value="info">🔵 Información / Noticia</option>
                            <option value="aviso">🟠 Aviso / Recordatorio</option>
                            <option value="peligro">🔴 Emergencia / Daño</option>
                            <option value="exito">🟢 Éxito / Promoción</option>
                        </select>
                    </div>
                    <div class="control-group" style="grid-column: 1 / -1;">
                        <label>Mensaje o Descripción</label>
                        <textarea id="a-mensaje" rows="2" style="width:100%; border:1px solid #ccc; border-radius:6px; padding:0.6rem 1rem;" placeholder="Escribe el cuerpo del anuncio..." required></textarea>
                    </div>
                    <button type="submit" class="btn-search">📢 Publicar Anuncio</button>
                </form>

                <h3>Anuncios Publicados</h2>
                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Título</th>
                                <th>Estilo</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-anuncios-body">
                            <tr><td colspan="5" style="text-align:center;">Cargando anuncios...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <div id="toast">Notificación</div>

    <!-- Modal para editar precio -->
    <div id="price-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
        <div style="background:white; padding:2rem; border-radius:8px; width:400px; max-width:90%;">
            <h3 style="margin-top:0; color:#0b1c3c;">Editar Precio</h3>
            <p id="price-modal-info" style="color:#666; font-size:0.9rem; margin-bottom:1.5rem;"></p>
            
            <div class="control-group" style="margin-bottom:1rem;">
                <label for="modal-precio-base">Precio Base (€)</label>
                <input type="number" step="0.01" id="modal-precio-base" placeholder="Dejar en blanco para default" style="padding:0.6rem; border:1px solid #ccc; border-radius:6px; width:100%;">
            </div>
            
            <div class="control-group" style="margin-bottom:1.5rem;">
                <label for="modal-precio-luz">Suplemento Luz (€)</label>
                <input type="number" step="0.01" id="modal-precio-luz" placeholder="Dejar en blanco para default" style="padding:0.6rem; border:1px solid #ccc; border-radius:6px; width:100%;">
            </div>
            
            <div style="display:flex; justify-content:flex-end; gap:1rem;">
                <button id="btn-cancel-price" style="background:#ccc; color:#333; border:none; padding:0.6rem 1.2rem; border-radius:6px; cursor:pointer;">Cancelar</button>
                <button id="btn-save-price" style="background:#d15615; color:white; border:none; padding:0.6rem 1.2rem; border-radius:6px; cursor:pointer;">Guardar</button>
            </div>
        </div>
    </div>

    <!-- Modal para ver detalles de reserva -->
    <div id="details-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
        <div style="background:white; padding:2rem; border-radius:8px; width:500px; max-width:90%;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; border-bottom:1px solid #eee; padding-bottom:1rem;">
                <h3 style="margin:0; color:#0b1c3c;">Detalles de la Reserva</h3>
                <button id="btn-close-details" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#666;">&times;</button>
            </div>
            <div id="details-modal-content" style="line-height:1.6; color:#333; font-size:0.95rem;">
                <!-- Contenido generado dinámicamente -->
            </div>
            <div style="margin-top:1.5rem; text-align:right;" id="details-modal-actions">
                <button id="btn-close-details2" class="btn-sm" style="background:#ccc; padding:0.6rem 1.2rem;">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- Modal de seguridad para acciones sensibles de Admins -->
    <div id="admin-security-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; justify-content:center; align-items:center;">
        <div style="background:white; padding:2rem; border-radius:8px; width:420px; max-width:90%;">
            <h3 style="margin-top:0; color:#0b1c3c;" id="security-modal-title">Confirmar Acción</h3>
            <p id="security-modal-desc" style="color:#666; font-size:0.9rem; margin-bottom:1rem;"></p>
            
            <div id="security-modal-extra" style="margin-bottom:1rem;"></div>

            <div class="control-group" style="margin-bottom:1.5rem;">
                <label for="security-modal-password">Tu Contraseña Actual</label>
                <input type="password" id="security-modal-password" placeholder="Introduce tu contraseña para confirmar" style="padding:0.6rem; border:1px solid #ccc; border-radius:6px; width:100%; box-sizing:border-box;">
            </div>
            
            <div style="display:flex; justify-content:flex-end; gap:1rem;">
                <button id="btn-security-cancel" style="background:#ccc; color:#333; border:none; padding:0.6rem 1.2rem; border-radius:6px; cursor:pointer;">Cancelar</button>
                <button id="btn-security-confirm" style="background:#d15615; color:white; border:none; padding:0.6rem 1.2rem; border-radius:6px; cursor:pointer; font-weight:600;">Confirmar</button>
            </div>
        </div>
    </div>

    <script>
        // Pasar variables desde PHP u ocultarlo en variables globales si hace falta
        const defaultBasePrice = 4.00;
    </script>
    <script src="../js/admin.js?v=2.2.0"></script>
</body>
</html>


