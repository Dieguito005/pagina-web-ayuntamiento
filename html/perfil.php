<?php
require_once '../includes/user-auth-check.php';
require_once '../api/conexion.php';

// Obtener las reservas del usuario
$reservas = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM reservas WHERE usuario_id = ? ORDER BY fecha DESC, hora_inicio DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    $reservas = [];
}

$totalReservas = count($reservas);
$reservasActivas = count(array_filter($reservas, fn($r) => $r['estado'] === 'reservada'));
$reservasCanceladas = count(array_filter($reservas, fn($r) => $r['estado'] === 'cancelada'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Alquileres Tarancón</title>
    <link rel="preload" href="../assets/imagenes/LOGO-AYUNTAMIENTO-BLANCO.png" as="image">
    <link rel="stylesheet" href="../css/home.css">
    <link rel="stylesheet" href="../css/cookies.css">
    <script src="../js/cookies.js" defer></script>
    <style>
        .profile-wrapper {
            background: linear-gradient(135deg, #f4f6f8 0%, #e8ecf1 100%);
            min-height: calc(100vh - 100px);
            padding: 2rem;
        }
        .profile-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .profile-header-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            padding: 2rem 2.5rem;
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #d15615, #a44310);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            font-weight: 700;
            flex-shrink: 0;
        }
        .profile-info h2 {
            color: #0b1c3c;
            font-size: 1.5rem;
            margin-bottom: 0.3rem;
        }
        .profile-info p {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 0.2rem;
        }
        .profile-info .tag {
            display: inline-block;
            background: #e8f5e9;
            color: #2e7d32;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 0.3rem;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            animation: slideUp 0.6s ease-out;
        }
        .stat-card .num {
            font-size: 2rem;
            font-weight: 700;
            color: #0b1c3c;
        }
        .stat-card .label {
            font-size: 0.85rem;
            color: #888;
            margin-top: 0.3rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-card.orange .num { color: #d15615; }
        .stat-card.red .num { color: #c62828; }

        .section-title {
            color: #0b1c3c;
            font-size: 1.3rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .history-table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            overflow-x: auto;
            animation: slideUp 0.7s ease-out;
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }
        .history-table th, .history-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.95rem;
        }
        .history-table th {
            background: #fafafa;
            font-weight: 600;
            color: #555;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .history-table tr:last-child td { border-bottom: none; }
        .history-table tr:hover td { background: #fafafa; }

        .badge {
            padding: 0.25rem 0.6rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
        .badge.reservada { background: #e8f5e9; color: #2e7d32; }
        .badge.cancelada { background: #ffebee; color: #c62828; }
        .badge.bloqueada_admin { background: #fff3e0; color: #ef6c00; }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #999;
        }
        .empty-state .icon { font-size: 3rem; margin-bottom: 1rem; }
        .empty-state p { font-size: 1rem; }
        .empty-state a {
            color: #d15615;
            text-decoration: none;
            font-weight: 600;
        }

        .btn-logout-profile {
            background: transparent;
            color: #d32f2f;
            border: 1px solid #d32f2f;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s;
            margin-left: auto;
        }
        .btn-logout-profile:hover { background: #d32f2f; color: white; }

        @media (max-width: 600px) {
            .profile-header-card { flex-direction: column; text-align: center; }
            .btn-logout-profile { margin: 1rem auto 0; }
        }
    </style>
</head>
<body>

    <?php include '../includes/header.php'; ?>

    <main class="profile-wrapper">
        <div class="profile-container">

            <!-- Cabecera del Perfil -->
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
                </div>
                <a href="../api/user-logout.php" class="btn-logout-profile">Cerrar Sesión</a>
            </div>

            <!-- Estadísticas -->
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
            </div>

            <!-- Historial de Reservas -->
            <h3 class="section-title">📋 Historial de Reservas</h3>

            <?php if (empty($reservas)): ?>
                <div class="history-table-container">
                    <div class="empty-state">
                        <div class="icon">🏸</div>
                        <p>Aún no has realizado ninguna reserva.</p>
                        <p><a href="home.php">¡Reserva tu primera pista ahora!</a></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="history-table-container">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Instalación</th>
                                <th>Horario</th>
                                <th>Estado</th>
                                <th>Código</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservas as $r): ?>
                                <?php
                                    $fechaFmt = date('d/m/Y', strtotime($r['fecha']));
                                    $horaInicio = substr($r['hora_inicio'], 0, 5);
                                    $horaFin = substr($r['hora_fin'], 0, 5);
                                    $estadoLabel = $r['estado'] === 'bloqueada_admin' ? 'Bloqueo Admin' : ucfirst($r['estado']);
                                ?>
                                <tr>
                                    <td><strong><?= $fechaFmt ?></strong></td>
                                    <td><?= htmlspecialchars($r['pista']) ?></td>
                                    <td><?= $horaInicio ?> - <?= $horaFin ?></td>
                                    <td><span class="badge <?= $r['estado'] ?>"><?= $estadoLabel ?></span></td>
                                    <td style="font-family: monospace; font-weight:600; color:#d15615;"><?= htmlspecialchars($r['codigo_acceso'] ?? '---') ?></td>
                                    <td><strong><?= $r['precio_total'] ? $r['precio_total'] . ' €' : '---' ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <?php include '../includes/footer.php'; ?>

</body>
</html>
