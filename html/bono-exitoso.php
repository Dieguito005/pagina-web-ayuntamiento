<?php
// html/bono-exitoso.php — Página de confirmación tras compra de bono
// 1. Si viene de Stripe: verifica pago y crea el bono
// 2. Si viene de pago simulado: muestra confirmación
// 3. Muestra resumen del bono adquirido

require_once '../includes/user-auth-check.php';
require_once '../config.php';
require_once '../api/conexion.php';

if (session_status() === PHP_SESSION_NONE) session_start();
send_security_headers();

$error = null;
$bonoData = null;

$session_id = $_GET['session_id'] ?? '';
$simulado = $_GET['simulado'] ?? '';
$bono_id = $_GET['bono_id'] ?? '';

// ── FLUJO SIMULADO ──
if ($simulado === '1' && !empty($bono_id)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM bonos WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$bono_id, $_SESSION['user_id']]);
        $bonoData = $stmt->fetch();
        if (!$bonoData) {
            $error = "No se encontró el bono.";
        }
    } catch (\PDOException $e) {
        error_log("bono-exitoso.php simulado error: " . $e->getMessage());
        $error = "Error al consultar el bono.";
    }
}

// ── FLUJO STRIPE ──
elseif (!empty($session_id)) {
    $ch = curl_init("https://api.stripe.com/v1/checkout/sessions/$session_id");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => STRIPE_SECRET_KEY . ':',
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);

    $session = json_decode($response, true);

    if ($httpCode !== 200 || !$session) {
        $error = "No se pudo verificar el pago.";
    } elseif ($session['payment_status'] !== 'paid') {
        $error = "El pago no se ha completado.";
    } else {
        // Verificar idempotencia: ¿ya existe un bono con este session_id?
        $checkStmt = $pdo->prepare("SELECT * FROM bonos WHERE stripe_session_id = ?");
        $checkStmt->execute([$session_id]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            $bonoData = $existing;
        } else {
            // Crear el bono
            $sessionData = $_SESSION['stripe_bono'] ?? null;
            if (!$sessionData) {
                // Fallback: usar metadata de Stripe
                $meta = $session['metadata'] ?? [];
                $tipo = $meta['tipo_bono'] ?? 'bono_15';
                $usos = intval($meta['usos'] ?? 15);

                // Buscar config
                $cfgStmt = $pdo->prepare("SELECT * FROM bonos_config WHERE tipo = ?");
                $cfgStmt->execute([$tipo]);
                $pack = $cfgStmt->fetch();

                $sessionData = [
                    'tipo' => $tipo,
                    'nombre' => $pack['nombre'] ?? "Bono $usos Sesiones",
                    'usos' => $usos,
                    'precio' => ($session['amount_total'] ?? 0) / 100,
                    'caducidad_meses' => $pack['caducidad_meses'] ?? 6,
                    'usuario_id' => $_SESSION['user_id'],
                    'usuario_email' => $session['customer_email'] ?? $_SESSION['user_email'],
                    'usuario_nombre' => $_SESSION['user_nombre'] ?? 'Usuario',
                ];
            }

            try {
                $fechaCaducidad = date('Y-m-d', strtotime("+{$sessionData['caducidad_meses']} months"));

                $insertStmt = $pdo->prepare("
                    INSERT INTO bonos (usuario_id, tipo, nombre, usos_totales, usos_restantes, precio_pagado, estado, stripe_session_id, fecha_caducidad)
                    VALUES (?, ?, ?, ?, ?, ?, 'activo', ?, ?)
                ");
                $insertStmt->execute([
                    $sessionData['usuario_id'],
                    $sessionData['tipo'],
                    $sessionData['nombre'],
                    $sessionData['usos'],
                    $sessionData['usos'],
                    $sessionData['precio'],
                    $session_id,
                    $fechaCaducidad
                ]);

                $newBonoId = $pdo->lastInsertId();

                // Obtener el bono creado
                $getStmt = $pdo->prepare("SELECT * FROM bonos WHERE id = ?");
                $getStmt->execute([$newBonoId]);
                $bonoData = $getStmt->fetch();

                // Enviar email
                require_once '../api/mailer.php';
                $emailHtml = "
                <html><head><title>Bono Adquirido</title></head>
                <body style='font-family: Arial, sans-serif; color: #333; line-height: 1.6;'>
                    <h2 style='color: #914f26;'>¡Hola {$sessionData['usuario_nombre']}! Tu bono ha sido activado.</h2>
                    <table width='100%' cellpadding='10' style='background-color: #f4f6f8; border-radius: 8px; margin: 20px 0; border: 1px solid #ddd;'>
                        <tr><td><strong>Bono:</strong></td><td>{$sessionData['nombre']}</td></tr>
                        <tr><td><strong>Sesiones:</strong></td><td>{$sessionData['usos']} usos</td></tr>
                        <tr><td><strong>Precio:</strong></td><td>" . number_format($sessionData['precio'], 2, ',', '.') . " €</td></tr>
                        <tr><td><strong>Válido hasta:</strong></td><td>" . date('d/m/Y', strtotime($fechaCaducidad)) . "</td></tr>
                    </table>
                    <div style='background-color: #e8f5e9; border: 2px solid #4caf50; padding: 20px; text-align: center; border-radius: 8px;'>
                        <p style='margin: 0; color: #2e7d32; font-size: 16px;'><strong>🎟️ Ya puedes usar tu bono al reservar cualquier pista</strong></p>
                    </div>
                </body></html>";

                send_email($sessionData['usuario_email'], $sessionData['usuario_nombre'], 'Bono Activado - Ayuntamiento de Tarancón', $emailHtml);

                unset($_SESSION['stripe_bono']);

            } catch (\PDOException $e) {
                error_log("bono-exitoso.php DB error: " . $e->getMessage());
                $error = "Error al registrar el bono. Contacta con el Ayuntamiento.";
            }
        }
    }
} else {
    $error = "Parámetros de confirmación no encontrados.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bono Adquirido - Alquileres Tarancón</title>
    <link rel="stylesheet" href="../css/home.css">
    <link rel="stylesheet" href="../css/bonos.css">
    <link rel="stylesheet" href="../css/cookies.css">
    <script src="../js/cookies.js" defer></script>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="main-content" style="padding: 2rem;">
        <?php if ($error): ?>
            <div class="bono-exitoso-container">
                <div class="bono-exitoso-card error">
                    <div class="exitoso-icon">⚠️</div>
                    <h2>Error</h2>
                    <p><?= htmlspecialchars($error) ?></p>
                    <a href="home.php" class="btn-primary" style="margin-top:1.5rem; display:inline-block;">Volver al Inicio</a>
                </div>
            </div>
        <?php elseif ($bonoData): ?>
            <div class="bono-exitoso-container">
                <div class="bono-exitoso-card">
                    <div class="exitoso-icon success-pulse">✅</div>
                    <h2>¡Bono Adquirido con Éxito!</h2>
                    <p>Tu bono de sesiones ha sido activado y ya está disponible en tu cuenta.</p>

                    <div class="bono-exitoso-details">
                        <div class="detail-item">
                            <span class="detail-label">🎟️ Bono</span>
                            <span class="detail-value"><?= htmlspecialchars($bonoData['nombre']) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">🏃 Sesiones</span>
                            <span class="detail-value"><?= $bonoData['usos_totales'] ?> usos</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">💰 Precio</span>
                            <span class="detail-value"><?= number_format((float)$bonoData['precio_pagado'], 2, ',', '.') ?> €</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">📅 Válido hasta</span>
                            <span class="detail-value"><?= $bonoData['fecha_caducidad'] ? date('d/m/Y', strtotime($bonoData['fecha_caducidad'])) : 'Sin caducidad' ?></span>
                        </div>
                    </div>

                    <div class="bono-exitoso-info">
                        <p><strong>¿Cómo usar tu bono?</strong></p>
                        <ul>
                            <li>Ve a cualquier pista y selecciona tu horario.</li>
                            <li>En el checkout, marca la opción <strong>"Usar bono"</strong>.</li>
                            <li>Tu reserva se confirmará sin coste adicional.</li>
                        </ul>
                    </div>

                    <div class="bono-exitoso-actions">
                        <a href="perfil.php" class="btn-primary">Ver Mis Bonos</a>
                        <a href="home.php" class="btn-secondary">Volver al Inicio</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
