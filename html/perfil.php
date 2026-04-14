<?php
require_once '../includes/user-auth-check.php';
require_once '../api/conexion.php';

// ── Datos del usuario desde la BBDD (para fecha_registro / miembro desde) ──
$userRow = [];
try {
    $uStmt = $pdo->prepare("SELECT fecha_registro FROM usuarios WHERE id = ?");
    $uStmt->execute([$_SESSION['user_id']]);
    $userRow = $uStmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (\PDOException $e) { $userRow = []; }

$miembroDesde = !empty($userRow['fecha_registro'])
    ? date('d/m/Y', strtotime($userRow['fecha_registro']))
    : null;

// ── Reservas del usuario ────────────────────────────────────────────────────
$reservas = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM reservas WHERE usuario_id = ? ORDER BY fecha DESC, hora_inicio DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) { $reservas = []; }

$hoy = date('Y-m-d');
$horaActual = date('H:i');

$proximasReservas = array_filter($reservas, function($r) use ($hoy, $horaActual) {
    if ($r['estado'] !== 'reservada') return false;
    if ($r['fecha'] > $hoy) return true;
    if ($r['fecha'] === $hoy) {
        $horaInicioR = substr($r['hora_inicio'], 0, 5);
        return $horaActual < $horaInicioR;
    }
    return false;
});

$historialReservas = array_filter($reservas, function($r) use ($hoy, $horaActual) {
    if ($r['estado'] !== 'reservada') return true;
    if ($r['fecha'] < $hoy) return true;
    if ($r['fecha'] === $hoy) {
        $horaInicioR = substr($r['hora_inicio'], 0, 5);
        return $horaActual >= $horaInicioR;
    }
    return false;
});

$totalReservas      = count($reservas);
$reservasActivas    = count(array_filter($reservas, fn($r) => $r['estado'] === 'reservada'));
$reservasCanceladas = count(array_filter($reservas, fn($r) => $r['estado'] === 'cancelada'));
$totalGastado       = array_sum(array_map(fn($r) => (float)($r['precio_total'] ?? 0),
    array_filter($reservas, fn($r) => $r['estado'] === 'reservada')));

// ── Bonos del usuario ────────────────────────────────────────────────────
$userBonos = [];
try {
    // Caducar bonos expirados
    $pdo->prepare("UPDATE bonos SET estado = 'caducado' WHERE usuario_id = ? AND estado = 'activo' AND fecha_caducidad IS NOT NULL AND fecha_caducidad < CURDATE()")
        ->execute([$_SESSION['user_id']]);
    $bonoStmt = $pdo->prepare("SELECT * FROM bonos WHERE usuario_id = ? ORDER BY FIELD(estado, 'activo', 'agotado', 'caducado', 'cancelado'), fecha_compra DESC");
    $bonoStmt->execute([$_SESSION['user_id']]);
    $userBonos = $bonoStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) { $userBonos = []; }

$bonosActivos = count(array_filter($userBonos, fn($b) => $b['estado'] === 'activo'));
$totalUsosDisponibles = array_sum(array_map(fn($b) => (int)$b['usos_restantes'],
    array_filter($userBonos, fn($b) => $b['estado'] === 'activo')));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Alquileres Tarancón</title>
    <meta name="description" content="Gestiona tu cuenta, consulta tus reservas y edita tus datos de contacto.">
    <link rel="preload" href="../assets/imagenes/LOGO-AYUNTAMIENTO-BLANCO.png" as="image">
    <link rel="stylesheet" href="../css/home.css">
    <link rel="stylesheet" href="../css/bonos.css?v=1.0.0">
    <link rel="stylesheet" href="../css/cookies.css">
    <script src="../js/cookies.js" defer></script>
    <style>
        /* ── Layout ── */
        .profile-wrapper {
            background: linear-gradient(135deg, #f4f6f8 0%, #e8ecf1 100%);
            min-height: calc(100vh - 100px);
            padding: 2rem;
        }
        .profile-container { max-width: 1050px; margin: 0 auto; }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Cabecera de perfil ── */
        .profile-header-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            padding: 2rem 2.5rem;
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
            animation: slideUp 0.45s ease-out;
        }
        .profile-avatar {
            width: 80px; height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #d15615, #a44310);
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; color: white; font-weight: 700; flex-shrink: 0;
        }
        .profile-info { flex: 1; }
        .profile-info h2 { color: #0b1c3c; font-size: 1.5rem; margin-bottom: 0.3rem; }
        .profile-info p  { color: #666; font-size: 0.93rem; margin-bottom: 0.2rem; }
        .profile-info .tag {
            display: inline-block;
            background: #e8f5e9; color: #2e7d32;
            padding: 0.2rem 0.6rem; border-radius: 12px;
            font-size: 0.78rem; font-weight: 600; margin-top: 0.3rem;
        }
        .profile-info .since {
            display: inline-block;
            background: #f0f4ff; color: #3949ab;
            padding: 0.2rem 0.6rem; border-radius: 12px;
            font-size: 0.78rem; font-weight: 600; margin-top: 0.3rem; margin-left: 0.4rem;
        }
        .profile-actions { display: flex; flex-direction: column; gap: 0.6rem; align-items: flex-end; }
        .btn-home-profile {
            background: white; color: #0b1c3c; border: 1px solid #ccc;
            padding: 0.55rem 1.1rem; border-radius: 8px; text-decoration: none;
            font-size: 0.88rem; font-weight: 600; cursor: pointer;
            transition: all 0.25s; text-align: center; white-space: nowrap;
        }
        .btn-home-profile:hover { background: #f4f6f8; border-color: #0b1c3c; }
        .btn-edit-profile {
            background: linear-gradient(135deg, #d15615, #a44310);
            color: white; border: none;
            padding: 0.55rem 1.1rem; border-radius: 8px;
            font-size: 0.88rem; font-weight: 600; cursor: pointer;
            transition: all 0.25s; white-space: nowrap;
        }
        .btn-edit-profile:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(209,86,21,0.3); }
        .btn-logout-profile {
            background: transparent; color: #d32f2f; border: 1px solid #d32f2f;
            padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none;
            font-weight: 600; font-size: 0.88rem; transition: all 0.25s; text-align: center;
        }
        .btn-logout-profile:hover { background: #d32f2f; color: white; }

        /* ── Stats ── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem; margin-bottom: 2rem;
        }
        .stat-card {
            background: white; border-radius: 12px;
            padding: 1.4rem; text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            animation: slideUp 0.55s ease-out;
        }
        .stat-card .num  { font-size: 2rem; font-weight: 700; color: #0b1c3c; }
        .stat-card .label { font-size: 0.82rem; color: #888; margin-top: 0.3rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card.orange .num { color: #d15615; }
        .stat-card.red    .num { color: #c62828; }
        .stat-card.green  .num { color: #2e7d32; }

        /* ── Section title ── */
        .section-title {
            color: #0b1c3c; font-size: 1.2rem;
            margin-bottom: 1rem; padding-bottom: 0.5rem;
            border-bottom: 2px solid #f0f0f0;
        }

        /* ── Próximas reservas ── */
        .proximas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem; margin-bottom: 2rem;
            animation: slideUp 0.6s ease-out;
        }
        .proxima-card {
            background: white; border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.06);
            padding: 1.3rem 1.5rem;
            border-left: 4px solid #d15615;
            position: relative; overflow: hidden;
        }
        .proxima-card::before {
            content: ''; position: absolute; top: 0; right: 0;
            width: 80px; height: 80px;
            background: linear-gradient(135deg, rgba(209,86,21,0.06), transparent);
            border-radius: 0 12px 0 80px;
        }
        .proxima-card .pista-name { font-weight: 700; color: #0b1c3c; font-size: 1rem; margin-bottom: 0.4rem; }
        .proxima-card .pista-meta { color: #555; font-size: 0.88rem; margin-bottom: 0.3rem; }
        .proxima-card .countdown {
            display: inline-block;
            background: linear-gradient(135deg, #d15615, #a44310);
            color: white; padding: 0.25rem 0.7rem; border-radius: 20px;
            font-size: 0.78rem; font-weight: 700; margin-top: 0.5rem;
        }
        .proxima-card .code-inline {
            font-family: monospace; font-size: 0.85rem; color: #d15615;
            font-weight: 700; margin-top: 0.4rem; display: block;
            cursor: pointer; transition: color 0.2s;
            user-select: text;
        }
        .proxima-card .code-inline:hover { color: #a44310; }

        /* ── Tabla historial ── */
        .history-table-container {
            background: white; border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            overflow-x: auto; animation: slideUp 0.65s ease-out;
            margin-bottom: 2rem;
        }
        .history-table {
            width: 100%; border-collapse: collapse; min-width: 720px;
        }
        .history-table th, .history-table td {
            padding: 0.85rem 1rem; text-align: left;
            border-bottom: 1px solid #f0f0f0; font-size: 0.92rem;
        }
        .history-table th {
            background: #fafafa; font-weight: 600; color: #555;
            font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .history-table tr:last-child td { border-bottom: none; }
        .history-table tr:hover td { background: #fafafa; }

        /* Código copiable (sutil) */
        .code-copy {
            font-family: monospace; font-weight: 600; color: #d15615;
            cursor: pointer; user-select: text;
            transition: background 0.3s, border-radius 0.3s, padding 0.3s;
            border-radius: 4px; padding: 2px 4px;
            display: inline-block;
        }
        .code-copy:hover { background: rgba(209,86,21,0.07); }
        .code-copy.just-copied {
            background: rgba(46,125,50,0.12);
            color: #2e7d32;
        }

        .badge {
            padding: 0.22rem 0.55rem; border-radius: 20px;
            font-size: 0.78rem; font-weight: 600; display: inline-block;
        }
        .badge.reservada       { background: #e8f5e9; color: #2e7d32; }
        .badge.en_curso        { background: #e3f2fd; color: #1565c0; }
        .badge.terminada       { background: #f5f5f5; color: #757575; }
        .badge.cancelada       { background: #ffebee; color: #c62828; }
        .badge.bloqueada_admin { background: #fff3e0; color: #ef6c00; }

        .empty-state { text-align: center; padding: 2.5rem 1rem; color: #999; }
        .empty-state .icon { font-size: 2.5rem; margin-bottom: 0.8rem; }
        .empty-state a { color: #d15615; text-decoration: none; font-weight: 600; }

        .date-reserved { font-size: 0.8rem; color: #aaa; display: block; margin-top: 1px; }

        /* ── Modal edición ── */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.45);
            display: flex; align-items: center; justify-content: center;
            z-index: 9999; opacity: 0; pointer-events: none;
            transition: opacity 0.25s;
        }
        .modal-overlay.open { opacity: 1; pointer-events: auto; }
        .modal-box {
            background: white; border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.18);
            width: 100%; max-width: 480px; margin: 1rem;
            transform: translateY(20px); transition: transform 0.25s;
            overflow: hidden;
        }
        .modal-overlay.open .modal-box { transform: translateY(0); }
        .modal-header {
            background: linear-gradient(135deg, #0b1c3c, #1a336b);
            color: white; padding: 1.4rem 1.8rem;
            display: flex; align-items: center; justify-content: space-between;
        }
        .modal-header h3 { font-size: 1.1rem; margin: 0; }
        .modal-close {
            background: none; border: none; color: white;
            font-size: 1.4rem; cursor: pointer; line-height: 1;
            opacity: 0.7; transition: opacity 0.2s;
        }
        .modal-close:hover { opacity: 1; }
        .modal-body { padding: 1.8rem; }

        /* Tabs del modal */
        .modal-tabs {
            display: flex; gap: 0; border-bottom: 2px solid #eee;
            margin-bottom: 1.5rem;
        }
        .modal-tab {
            flex: 1; padding: 0.7rem 0.5rem; text-align: center;
            border: none; background: none; cursor: pointer;
            font-size: 0.88rem; color: #888; font-weight: 600;
            transition: color 0.2s; border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }
        .modal-tab.active { color: #d15615; border-bottom-color: #d15615; }
        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        .modal-field { margin-bottom: 1.1rem; }
        .modal-field label {
            display: block; font-size: 0.85rem; font-weight: 600;
            color: #444; margin-bottom: 0.4rem;
        }
        .modal-field input {
            width: 100%; padding: 0.75rem 1rem;
            border: 1px solid #ddd; border-radius: 8px;
            font-size: 0.95rem; box-sizing: border-box;
            transition: border-color 0.25s, box-shadow 0.25s;
            background: #fafafa;
        }
        .modal-field input:focus {
            outline: none; border-color: #d15615;
            box-shadow: 0 0 0 3px rgba(209,86,21,0.1);
            background: #fff;
        }
        .modal-field input:disabled {
            background: #f3f3f3; color: #aaa; cursor: not-allowed;
        }
        .modal-lock-info {
            background: #fafafa; border: 1px solid #eee;
            border-radius: 8px; padding: 0.8rem 1rem;
            font-size: 0.83rem; color: #888;
            display: flex; align-items: flex-start; gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .modal-warning {
            background: #fff8e1; border: 1px solid #ffe082;
            border-radius: 8px; padding: 0.75rem 1rem;
            font-size: 0.83rem; color: #7c5c00; margin-bottom: 1rem;
        }
        .btn-modal-save {
            background: linear-gradient(135deg, #d15615, #a44310);
            color: white; border: none; width: 100%;
            padding: 0.85rem; border-radius: 8px;
            font-size: 1rem; font-weight: 600; cursor: pointer;
            transition: all 0.25s; margin-top: 0.5rem;
        }
        .btn-modal-save:hover { transform: translateY(-1px); box-shadow: 0 5px 16px rgba(209,86,21,0.3); }
        .btn-modal-save:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .modal-msg {
            padding: 0.65rem 0.9rem; border-radius: 8px;
            font-size: 0.87rem; margin-bottom: 1rem; display: none;
        }
        .modal-msg.success { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .modal-msg.error   { background: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .profile-wrapper { padding: 1.5rem 1rem; }
            .profile-header-card { padding: 1.5rem 1.25rem; gap: 1.25rem; }
            .profile-avatar { width: 65px; height: 65px; font-size: 1.6rem; }
            .profile-info h2 { font-size: 1.3rem; }
            .profile-info p { font-size: 0.88rem; }

            /* Stats */
            .stats-row { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
            .stat-card { padding: 1.1rem; }
            .stat-card .num { font-size: 1.6rem; }
            .stat-card .label { font-size: 0.75rem; }

            /* Reservas grid */
            .proximas-grid { grid-template-columns: 1fr; }
            .proxima-card { padding: 1.1rem; }

            /* Tabla */
            .history-table th, .history-table td { padding: 0.7rem 0.8rem; font-size: 0.88rem; }

            /* Modal */
            .modal-box { margin: 1rem; }
            .modal-header { padding: 1.2rem 1.4rem; }
            .modal-body { padding: 1.4rem; }
        }

        @media (max-width: 640px) {
            .profile-wrapper { padding: 1rem 0.75rem; }
            .profile-header-card { flex-direction: column; text-align: center; padding: 1.25rem 1rem; }
            .profile-avatar { width: 60px; height: 60px; font-size: 1.4rem; }
            .profile-actions { flex-direction: column; width: 100%; gap: 0.5rem; }
            .profile-actions .btn-home-profile,
            .profile-actions .btn-edit-profile,
            .profile-actions .btn-logout-profile { width: 100%; }

            /* Stats */
            .stats-row { grid-template-columns: repeat(2, 1fr); gap: 0.6rem; margin-bottom: 1.5rem; }
            .stat-card { padding: 1rem; }
            .stat-card .num { font-size: 1.4rem; }

            /* Tabla scroll */
            .history-table-container { margin: 0 -0.75rem 1.5rem; border-radius: 0; }
            .history-table { min-width: 650px; }

            /* Modal */
            .modal-tabs { flex-wrap: wrap; }
            .modal-tab { padding: 0.6rem 0.4rem; font-size: 0.85rem; }
            .modal-box { margin: 0.75rem; border-radius: 12px; }
            .modal-field input { font-size: 16px; /* Previene zoom iOS */ }
        }

        @media (max-width: 480px) {
            .profile-info .tag, .profile-info .since {
                display: block;
                margin: 0.25rem 0;
            }
            .section-title { font-size: 1.05rem; }
            .badge { font-size: 0.75rem; padding: 0.2rem 0.5rem; }
            .empty-state { padding: 2rem 0.75rem; }
            .empty-state .icon { font-size: 2rem; }
        }
    </style>
</head>
<body>

    <?php include '../includes/header.php'; ?>

    <main class="profile-wrapper">
        <div class="profile-container">

            <!-- ── Cabecera del Perfil ── -->
            <div class="profile-header-card">
                <div class="profile-avatar">
                    <?= strtoupper(substr($_SESSION['user_nombre'], 0, 1)) ?><?= strtoupper(substr($_SESSION['user_apellidos'] ?? '', 0, 1)) ?>
                </div>
                <div class="profile-info">
                    <h2><?= htmlspecialchars($_SESSION['user_nombre'] . ' ' . ($_SESSION['user_apellidos'] ?? '')) ?></h2>
                    <p>📧 <?= htmlspecialchars($_SESSION['user_email']) ?></p>
                    <?php if (!empty($_SESSION['user_dni'])): ?>
                        <p>🪪 <?= htmlspecialchars($_SESSION['user_dni']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($_SESSION['user_telefono'])): ?>
                        <p>📞 <?= htmlspecialchars($_SESSION['user_telefono']) ?></p>
                    <?php endif; ?>
                    <span class="tag">✓ Cuenta verificada</span>
                    <?php if ($miembroDesde): ?>
                        <span class="since">🗓 Miembro desde <?= $miembroDesde ?></span>
                    <?php endif; ?>
                </div>
                <div class="profile-actions">
                    <a href="home.php" class="btn-home-profile">🏠 Ir al inicio</a>
                    <button class="btn-edit-profile" id="btn-open-edit">✏️ Editar datos</button>
                    <a href="../api/user-logout.php" class="btn-logout-profile">Cerrar sesión</a>
                </div>
            </div>

            <!-- ── Estadísticas ── -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="num"><?= $totalReservas ?></div>
                    <div class="label">Total Reservas</div>
                </div>
                <div class="stat-card orange">
                    <div class="num"><?= $reservasActivas ?></div>
                    <div class="label">Activas</div>
                </div>
                <div class="stat-card red">
                    <div class="num"><?= $reservasCanceladas ?></div>
                    <div class="label">Canceladas</div>
                </div>
                <div class="stat-card green">
                    <div class="num"><?= number_format($totalGastado, 2, ',', '.') ?> €</div>
                    <div class="label">Total Gastado</div>
                </div>
                <div class="stat-card" style="border-left: 3px solid #d15615;">
                    <div class="num" style="color: #d15615;"><?= $bonosActivos ?></div>
                    <div class="label">Bonos Activos</div>
                </div>
            </div>

            <!-- ── Mis Bonos ── -->
            <?php if (!empty($userBonos)): ?>
                <h3 class="section-title">🎟️ Mis Bonos</h3>
                <div class="bonos-grid mis-bonos-section">
                    <?php foreach ($userBonos as $bono):
                        $pct = $bono['usos_totales'] > 0 ? ($bono['usos_restantes'] / $bono['usos_totales']) * 100 : 0;
                        $barClass = $pct > 40 ? '' : ($pct > 15 ? 'low' : 'critical');
                        $estadoBono = ucfirst($bono['estado']);
                    ?>
                    <div class="bono-card-perfil" style="<?= $bono['estado'] !== 'activo' ? 'opacity:0.6; border-left-color:#aaa;' : '' ?>">
                        <div class="bono-nombre"><?= htmlspecialchars($bono['nombre']) ?></div>
                        <div class="bono-meta">💰 Pagado: <?= number_format((float)$bono['precio_pagado'], 2, ',', '.') ?> €</div>
                        <div class="bono-meta">📊 Estado: <strong><?= $estadoBono ?></strong></div>
                        <div class="bono-progress-bar">
                            <div class="bono-progress-fill <?= $barClass ?>" style="width: <?= $pct ?>%"></div>
                        </div>
                        <div class="bono-usos-text">
                            <span><?= $bono['usos_restantes'] ?> / <?= $bono['usos_totales'] ?> usos</span>
                            <span><?= round($pct) ?>%</span>
                        </div>
                        <?php if ($bono['fecha_caducidad']): ?>
                            <div class="bono-caducidad-text">📅 Válido hasta: <?= date('d/m/Y', strtotime($bono['fecha_caducidad'])) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <h3 class="section-title">🎟️ Mis Bonos</h3>
                <div class="history-table-container" style="margin-bottom: 2rem;">
                    <div class="empty-state">
                        <div class="icon">🎟️</div>
                        <p>No tienes bonos.</p>
                        <p><a href="bonos.php">¡Compra tu primer bono y ahorra!</a></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ── Próximas Reservas ── -->
            <?php if (!empty($proximasReservas)): ?>
                <h3 class="section-title">🗓 Próximas Reservas</h3>
                <div class="proximas-grid">
                    <?php foreach ($proximasReservas as $pr):
                        $fechaPista  = new DateTime($pr['fecha']);
                        $hoyDt       = new DateTime($hoy);
                        $diffDias    = (int)$hoyDt->diff($fechaPista)->days;
                        $countdownTxt = $diffDias === 0 ? '¡Hoy!' : ($diffDias === 1 ? 'Mañana' : "En $diffDias días");
                        $hiStart = substr($pr['hora_inicio'], 0, 5);
                        $hiEnd   = substr($pr['hora_fin'],   0, 5);
                    ?>
                    <div class="proxima-card">
                        <div class="pista-name"><?= htmlspecialchars($pr['pista']) ?></div>
                        <div class="pista-meta">📅 <?= date('d/m/Y', strtotime($pr['fecha'])) ?></div>
                        <div class="pista-meta">🕐 <?= $hiStart ?> – <?= $hiEnd ?></div>
                        <?php if (!empty($pr['codigo_acceso'])): ?>
                            <span class="code-inline js-copy-code" data-code="<?= htmlspecialchars($pr['codigo_acceso']) ?>">
                                🔑 <?= htmlspecialchars($pr['codigo_acceso']) ?>
                            </span>
                        <?php endif; ?>
                        <div><span class="countdown"><?= $countdownTxt ?></span></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- ── Historial de Reservas ── -->
            <h3 class="section-title">📋 Historial de Reservas</h3>

            <?php if (empty($historialReservas)): ?>
                <div class="history-table-container">
                    <div class="empty-state">
                        <div class="icon">🏸</div>
                        <p>Aún no hay reservas en tu historial.</p>
                        <p><a href="home.php">¡Reserva tu primera pista ahora!</a></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="history-table-container">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Fecha Pista</th>
                                <th>Reservado el</th>
                                <th>Instalación</th>
                                <th>Horario</th>
                                <th>Estado</th>
                                <th>Código</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historialReservas as $r):
                                $fechaPistaFmt  = date('d/m/Y', strtotime($r['fecha']));
                                $fechaReservaFmt = !empty($r['fecha_registro'])
                                    ? date('d/m/Y', strtotime($r['fecha_registro']))
                                    : '—';
                                $horaInicio  = substr($r['hora_inicio'], 0, 5);
                                $horaFin     = substr($r['hora_fin'],   0, 5);
                                
                                $esHoy = ($r['fecha'] === $hoy);
                                $estadoLabel = ucfirst($r['estado']);
                                $badgeClass = $r['estado'];
                                
                                if ($r['estado'] === 'reservada') {
                                    if ($esHoy && $horaActual >= $horaInicio && $horaActual < $horaFin) {
                                        $estadoLabel = 'En curso';
                                        $badgeClass = 'en_curso';
                                    } elseif ($r['fecha'] < $hoy || ($esHoy && $horaActual >= $horaFin)) {
                                        $estadoLabel = 'Terminada';
                                        $badgeClass = 'terminada';
                                    }
                                } elseif ($r['estado'] === 'bloqueada_admin') {
                                    $estadoLabel = 'Bloqueo Admin';
                                }
                            ?>
                            <tr>
                                <td><strong><?= $fechaPistaFmt ?></strong></td>
                                <td><span style="color:#888; font-size:0.88rem;"><?= $fechaReservaFmt ?></span></td>
                                <td><?= htmlspecialchars($r['pista']) ?></td>
                                <td><?= $horaInicio ?> – <?= $horaFin ?></td>
                                <td><span class="badge <?= $badgeClass ?>"><?= $estadoLabel ?></span></td>
                                <td>
                                    <?php if (!empty($r['codigo_acceso'])): ?>
                                        <span class="code-copy js-copy-code" data-code="<?= htmlspecialchars($r['codigo_acceso']) ?>" title="Haz clic para copiar">
                                            <?= htmlspecialchars($r['codigo_acceso']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:#ccc">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= $r['precio_total'] ? number_format((float)$r['precio_total'], 2, ',', '.') . ' €' : '—' ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- ══════════════════════════════════════════════
         MODAL EDICIÓN DE DATOS
    ══════════════════════════════════════════════ -->
    <div class="modal-overlay" id="edit-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="modal-title">✏️ Editar mis datos</h3>
                <button class="modal-close" id="btn-close-modal" aria-label="Cerrar">✕</button>
            </div>
            <div class="modal-body">
                <!-- Tabs -->
                <div class="modal-tabs" role="tablist">
                    <button class="modal-tab active" data-tab="tab-phone" role="tab">📞 Teléfono</button>
                    <button class="modal-tab" data-tab="tab-email" role="tab">📧 Email</button>
                    <button class="modal-tab" data-tab="tab-password" role="tab">🔑 Contraseña</button>
                </div>

                <!-- Mensaje de feedback global -->
                <div class="modal-msg" id="modal-feedback"></div>

                <!-- Tab: Teléfono -->
                <div class="tab-panel active" id="tab-phone">
                    <div class="modal-lock-info">
                        🔒 <span>Nombre, apellidos y DNI son datos de identidad. Para modificarlos, contacta con el Ayuntamiento.</span>
                    </div>
                    <div class="modal-field">
                        <label for="edit-telefono">Nuevo teléfono (9 dígitos)</label>
                        <input type="tel" id="edit-telefono" placeholder="Ej: 600123456" maxlength="9"
                               value="<?= htmlspecialchars($_SESSION['user_telefono'] ?? '') ?>">
                    </div>
                    <button class="btn-modal-save" id="btn-save-phone">Guardar teléfono</button>
                </div>

                <!-- Tab: Email -->
                <div class="tab-panel" id="tab-email">
                    <div class="modal-warning">
                        ⚠️ Al cambiar tu email, <strong>se cerrará tu sesión automáticamente</strong> y deberás iniciar sesión con el nuevo correo.
                    </div>
                    <div class="modal-field">
                        <label for="edit-email">Nuevo email</label>
                        <input type="email" id="edit-email" placeholder="nuevo@email.com">
                    </div>
                    <div class="modal-field">
                        <label for="edit-email-confirm">Confirmar nuevo email</label>
                        <input type="email" id="edit-email-confirm" placeholder="nuevo@email.com">
                    </div>
                    <button class="btn-modal-save" id="btn-save-email">Cambiar email y salir</button>
                </div>

                <!-- Tab: Contraseña -->
                <div class="tab-panel" id="tab-password">
                    <div class="modal-warning">
                        ⚠️ Al cambiar tu contraseña, <strong>se cerrará tu sesión automáticamente</strong> y deberás volver a entrar.
                    </div>
                    <div class="modal-field">
                        <label for="edit-pass-current">Contraseña actual</label>
                        <input type="password" id="edit-pass-current" placeholder="••••••••">
                    </div>
                    <div class="modal-field">
                        <label for="edit-pass-new">Nueva contraseña (mín. 8 caracteres)</label>
                        <input type="password" id="edit-pass-new" placeholder="••••••••" minlength="8">
                    </div>
                    <div class="modal-field">
                        <label for="edit-pass-confirm">Confirmar nueva contraseña</label>
                        <input type="password" id="edit-pass-confirm" placeholder="••••••••">
                    </div>
                    <button class="btn-modal-save" id="btn-save-password">Cambiar contraseña y salir</button>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
    // ── Copiar código al clic (sutil) ──────────────────────────────────────
    document.querySelectorAll('.js-copy-code').forEach(el => {
        el.addEventListener('click', () => {
            const code = el.dataset.code;
            navigator.clipboard.writeText(code).then(() => {
                el.classList.add('just-copied');
                setTimeout(() => el.classList.remove('just-copied'), 1300);
            }).catch(() => {
                // Fallback: seleccionar texto
                const range = document.createRange();
                range.selectNodeContents(el);
                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(range);
            });
        });
    });

    // ── Modal ───────────────────────────────────────────────────────────────
    const modal    = document.getElementById('edit-modal');
    const openBtn  = document.getElementById('btn-open-edit');
    const closeBtn = document.getElementById('btn-close-modal');
    const feedback = document.getElementById('modal-feedback');

    function openModal() { modal.classList.add('open'); }
    function closeModal() {
        modal.classList.remove('open');
        hideFeedback();
    }

    openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

    // ── Tabs ────────────────────────────────────────────────────────────────
    document.querySelectorAll('.modal-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.modal-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            tab.classList.add('active');
            document.getElementById(tab.dataset.tab).classList.add('active');
            hideFeedback();
        });
    });

    // ── Feedback ────────────────────────────────────────────────────────────
    function showFeedback(msg, type) {
        feedback.textContent = msg;
        feedback.className = 'modal-msg ' + type;
        feedback.style.display = 'block';
    }
    function hideFeedback() {
        feedback.style.display = 'none';
        feedback.textContent = '';
    }

    // ── API call helper ──────────────────────────────────────────────────────
    async function callUpdate(payload, btn) {
        btn.disabled = true;
        hideFeedback();
        try {
            const res  = await fetch('../api/user-update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.status === 'success') {
                showFeedback(data.message, 'success');
                if (data.logout) {
                    setTimeout(() => { window.location.href = 'user-login.php'; }, 1800);
                } else {
                    setTimeout(() => { location.reload(); }, 1200);
                }
            } else {
                showFeedback(data.message || 'Ha ocurrido un error.', 'error');
                btn.disabled = false;
            }
        } catch (err) {
            showFeedback('Error de conexión. Inténtalo de nuevo.', 'error');
            btn.disabled = false;
        }
    }

    // ── Guardar teléfono ─────────────────────────────────────────────────────
    document.getElementById('btn-save-phone').addEventListener('click', () => {
        const tel = document.getElementById('edit-telefono').value.trim();
        if (tel && !/^[0-9]{9}$/.test(tel)) {
            showFeedback('El teléfono debe tener exactamente 9 dígitos.', 'error'); return;
        }
        callUpdate({ action: 'update_phone', telefono: tel },
                   document.getElementById('btn-save-phone'));
    });

    // ── Cambiar email ────────────────────────────────────────────────────────
    document.getElementById('btn-save-email').addEventListener('click', () => {
        const email   = document.getElementById('edit-email').value.trim();
        const confirm = document.getElementById('edit-email-confirm').value.trim();
        if (!email) { showFeedback('Introduce el nuevo email.', 'error'); return; }
        if (email !== confirm) { showFeedback('Los dos emails no coinciden.', 'error'); return; }
        callUpdate({ action: 'update_email', email },
                   document.getElementById('btn-save-email'));
    });

    // ── Cambiar contraseña ───────────────────────────────────────────────────
    document.getElementById('btn-save-password').addEventListener('click', () => {
        const cur  = document.getElementById('edit-pass-current').value;
        const nw   = document.getElementById('edit-pass-new').value;
        const conf = document.getElementById('edit-pass-confirm').value;
        if (!cur || !nw || !conf) { showFeedback('Completa todos los campos.', 'error'); return; }
        if (nw !== conf) { showFeedback('Las contraseñas nuevas no coinciden.', 'error'); return; }
        if (nw.length < 8) { showFeedback('La nueva contraseña debe tener al menos 8 caracteres.', 'error'); return; }
        callUpdate({ action: 'update_password', current_password: cur, new_password: nw, confirm_password: conf },
                   document.getElementById('btn-save-password'));
    });

    // ── Formateo input teléfono ──────────────────────────────────────────────
    document.getElementById('edit-telefono').addEventListener('input', e => {
        e.target.value = e.target.value.replace(/\D/g, '').substring(0, 9);
    });
    </script>

</body>
</html>
