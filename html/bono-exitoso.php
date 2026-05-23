<?php
// html/bono-exitoso.php — Pagina post-compra de bono.
// Como en pago-exitoso.php, la creacion CANONICA del bono ocurre en el webhook.
// Aqui mostramos estado y, como fallback, creamos si no existe — validando user_id.

// NOTA: no exigimos sesion activa por la misma razon que pago-exitoso.php
require_once '../config.php';
require_once '../api/conexion.php';
require_once '../api/mailer.php';
require_once '../includes/SecurityHandler.php';
require_once '../includes/audit.php';
require_once '../includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();
send_security_headers();

$error = null;
$bonoData = null;
$session_id = trim($_GET['session_id'] ?? '');
$simulado = $_GET['simulado'] ?? '';
$bono_id = (int) ($_GET['bono_id'] ?? 0);
$sessionUserId = (int) ($_SESSION['user_id'] ?? 0);

// FLUJO SIMULADO
if ($simulado === '1' && $bono_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM bonos WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$bono_id, $sessionUserId]);
        $bonoData = $stmt->fetch();
        if (!$bonoData) {
            $error = 'No se encontro el bono o no pertenece a tu cuenta.';
        }
    } catch (\PDOException $e) {
        error_log('bono_exitoso_simulado: ' . $e->getMessage());
        $error = 'Error al consultar el bono.';
    }
}
// FLUJO STRIPE
elseif ($session_id !== '' && preg_match('/^cs_[A-Za-z0-9_]{10,}$/', $session_id)) {
    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . urlencode($session_id));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => STRIPE_SECRET_KEY . ':',
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);

    $session = json_decode($response, true);
    if ($httpCode !== 200 || !$session) {
        $error = 'No se pudo verificar el pago.';
    } elseif (($session['payment_status'] ?? '') !== 'paid') {
        $error = 'El pago no se ha completado.';
    } else {
        $metaUserId = isset($session['metadata']['user_id']) ? (int) $session['metadata']['user_id'] : 0;
        if ($metaUserId <= 0) {
            audit_log($pdo, 'stripe_bono_no_user_in_metadata', 'bono', null, ['session_id' => $session_id]);
            $error = 'No se ha podido identificar el pago.';
        } elseif ($sessionUserId > 0 && $metaUserId !== $sessionUserId) {
            audit_log($pdo, 'stripe_bono_user_mismatch', 'bono', null, ['session_user' => $sessionUserId, 'meta_user' => $metaUserId, 'session_id' => $session_id]);
            $error = 'Esta confirmacion no corresponde a tu cuenta.';
        } else {
            try {
                $checkStmt = $pdo->prepare("SELECT * FROM bonos WHERE stripe_session_id = ?");
                $checkStmt->execute([$session_id]);
                $existing = $checkStmt->fetch();
                if ($existing) {
                    if ((int) $existing['usuario_id'] !== $metaUserId) {
                        $error = 'Este bono pertenece a otra cuenta.';
                    } else {
                        $bonoData = $existing;
                    }
                } else {
                    // FALLBACK creacion (si webhook no procesado)
                    $tipoBono = $session['metadata']['tipo_bono'] ?? '';
                    $cfg = $pdo->prepare("SELECT * FROM bonos_config WHERE tipo = ?");
                    $cfg->execute([$tipoBono]);
                    $pack = $cfg->fetch();
                    if (!$pack) {
                        $error = 'Pack de bono no encontrado.';
                    } else {
                        $precio = (float) ($session['amount_total'] ?? 0) / 100;
                        $fechaCaducidad = date('Y-m-d', strtotime("+{$pack['caducidad_meses']} months"));

                        $insert = $pdo->prepare(
                            "INSERT INTO bonos (usuario_id, tipo, nombre, usos_totales, usos_restantes, precio_pagado, estado, stripe_session_id, fecha_caducidad)
                             VALUES (?, ?, ?, ?, ?, ?, 'activo', ?, ?)"
                        );
                        $insert->execute([$metaUserId, $pack['tipo'], $pack['nombre'], $pack['usos'], $pack['usos'], $precio, $session_id, $fechaCaducidad]);
                        $newBonoId = $pdo->lastInsertId();
                        audit_log($pdo, 'stripe_bono_fallback_create', 'bono', $newBonoId, ['session_id' => $session_id]);

                        $getStmt = $pdo->prepare("SELECT * FROM bonos WHERE id = ?");
                        $getStmt->execute([$newBonoId]);
                        $bonoData = $getStmt->fetch();

                        // Email
                        $uStmt = $pdo->prepare("SELECT nombre, email FROM usuarios WHERE id = ?");
                        $uStmt->execute([$metaUserId]);
                        $user = $uStmt->fetch() ?: [];
                        $nH = esc_html($user['nombre'] ?? '');
                        $packH = esc_html($pack['nombre']);
                        $usosH = (int) $pack['usos'];
                        $precioH = number_format($precio, 2, ',', '.');
                        $fH = date('d/m/Y', strtotime($fechaCaducidad));
                        $emailHtml = "<html><body style='font-family: Arial, sans-serif; color:#333; line-height:1.6;'>
                            <h2 style='color:#914f26;'>Hola $nH, tu bono ha sido activado.</h2>
                            <table width='100%' cellpadding='10' style='background:#f4f6f8; border-radius:8px; margin:20px 0; border:1px solid #ddd;'>
                                <tr><td><strong>Bono:</strong></td><td>$packH</td></tr>
                                <tr><td><strong>Sesiones:</strong></td><td>$usosH usos</td></tr>
                                <tr><td><strong>Precio:</strong></td><td>$precioH &euro;</td></tr>
                                <tr><td><strong>Valido hasta:</strong></td><td>$fH</td></tr>
                            </table>
                        </body></html>";
                        send_email($user['email'] ?? '', $user['nombre'] ?? '', 'Bono Activado - Ayuntamiento de Tarancon', $emailHtml);
                        unset($_SESSION['stripe_bono']);
                    }
                }
            } catch (\PDOException $e) {
                error_log('bono_exitoso_fallback: ' . $e->getMessage());
                $error = 'Error al registrar el bono. Contacta con el Ayuntamiento.';
            }
        }
    }
} else {
    $error = 'Parametros de confirmacion no encontrados.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bono Adquirido - Alquileres Tarancon</title>
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
                    <div class="exitoso-icon">!</div>
                    <h2>Error</h2>
                    <p><?= esc_html($error) ?></p>
                    <a href="home.php" class="btn-primary" style="margin-top:1.5rem; display:inline-block;">Volver al Inicio</a>
                </div>
            </div>
        <?php elseif ($bonoData): ?>
            <div class="bono-exitoso-container">
                <div class="bono-exitoso-card">
                    <div class="exitoso-icon success-pulse">&check;</div>
                    <h2>Bono Adquirido con Exito</h2>
                    <p>Tu bono de sesiones ha sido activado y ya esta disponible en tu cuenta.</p>

                    <div class="bono-exitoso-details">
                        <div class="detail-item">
                            <span class="detail-label">Bono</span>
                            <span class="detail-value"><?= esc_html($bonoData['nombre']) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Sesiones</span>
                            <span class="detail-value"><?= (int) $bonoData['usos_totales'] ?> usos</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Precio</span>
                            <span class="detail-value"><?= number_format((float)$bonoData['precio_pagado'], 2, ',', '.') ?> &euro;</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Valido hasta</span>
                            <span class="detail-value"><?= $bonoData['fecha_caducidad'] ? date('d/m/Y', strtotime($bonoData['fecha_caducidad'])) : 'Sin caducidad' ?></span>
                        </div>
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
