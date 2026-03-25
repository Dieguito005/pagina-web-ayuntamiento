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
    <link rel="stylesheet" href="../css/home.css">
    <style>
        .admin-layout {
            display: flex;
            min-height: calc(100vh - 100px);
            background-color: #f4f6f8;
        }
        .admin-sidebar {
            width: 280px;
            background-color: #0b1c3c;
            color: white;
            padding: 2rem 1.5rem;
            display: flex;
            flex-direction: column;
        }
        .admin-sidebar h3 {
            margin-bottom: 2rem;
            font-size: 1.25rem;
            color: #d15615;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 0.8rem;
        }
        .admin-sidebar p { margin-bottom: 0.5rem; font-size: 0.95rem; color: #ccc;}
        .admin-nav { margin-top: 2rem; flex-grow: 1; }
        .admin-nav a {
            display: block;
            color: white;
            padding: 0.8rem 1rem;
            text-decoration: none;
            border-radius: 6px;
            margin-bottom: 0.5rem;
            background-color: rgba(255,255,255,0.05);
            transition: background 0.3s;
        }
        .admin-nav a:hover, .admin-nav a.active {
            background-color: #d15615;
            color: white;
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
        .time-slot.blocked_admin { background-color: #ffebee; border-color: #ffcdd2; cursor: not-allowed; }
        .time-slot.booked_user { background-color: #e8f5e9; border-color: #c8e6c9; cursor: not-allowed; }
        
        .time-slot .time { display: block; font-size: 1.15rem; font-weight: 700; color: #333; margin-bottom: 0.3rem;}
        .time-slot .status { font-size: 0.85rem; font-weight: 600; color: #666; }
        
        .time-slot.blocked_admin .status { color: #c62828; }
        .time-slot.booked_user .status { color: #2e7d32; }
        .time-slot.selected .status { color: #d15615; }
        
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
            <h3>Mi Perfil</h3>
            <p><strong>Usuario:</strong> <?= htmlspecialchars($_SESSION['admin_usuario'] ?? 'Admin') ?></p>
            <p><strong>Rol:</strong> <?= htmlspecialchars(ucfirst($_SESSION['admin_rol'] ?? 'Admin')) ?></p>
            
            <nav class="admin-nav">
                <a href="admin-dashboard.php" class="active">📅 Calendario 30 días</a>
                <!-- Future links could go here if roles expand -->
            </nav>
        </aside>

        <!-- Contenido principal -->
        <main class="admin-main">
            <div class="admin-header">
                <h2>Gestión de Pistas</h2>
                <a href="admin-logout.php" class="btn-logout">Cerrar Sesión</a>
            </div>

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
            
            <div class="action-bar">
                <button class="btn-block" id="btn-block-slot" disabled>Bloquear Horario Seleccionado</button>
            </div>
        </main>
    </div>
    
    <div id="toast">Notificación</div>

    <script src="../js/admin.js"></script>
</body>
</html>


