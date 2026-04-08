<?php
// html/pago-exitoso.php — Página de confirmación tras pago con Stripe
// 1. Verifica que la sesión de Stripe fue pagada
// 2. Crea la reserva en BBDD
// 3. Envía el email de confirmación
// 4. Muestra el código de acceso

require_once '../includes/user-auth-check.php';
require_once '../config.php';
require_once '../api/conexion.php';
require_once '../api/mailer.php';

if (session_status() === PHP_SESSION_NONE) session_start();
send_security_headers();

$error = null;
$codigo_acceso = null;
$reservaData = null;

$session_id = $_GET['session_id'] ?? '';

if (empty($session_id)) {
    $error = "Sesión de pago no encontrada.";
} else {
    // Verificar la sesión con Stripe
    $ch = curl_init("https://api.stripe.com/v1/checkout/sessions/$session_id");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => STRIPE_SECRET_KEY . ':',
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    @curl_close($ch);

    $session = json_decode($response, true);

    if ($httpCode !== 200 || !$session) {
        $error = "No se pudo verificar el pago.";
    } elseif ($session['payment_status'] !== 'paid') {
        $error = "El pago no se ha completado.";
    } else {
        // Pago verificado — recuperar datos de la sesión PHP
        $reservaData = $_SESSION['stripe_reserva'] ?? null;

        if (!$reservaData) {
            // Fallback: intentar usar metadata de Stripe
            $meta = $session['metadata'] ?? [];
            $reservaData = [
                'email'       => $session['customer_email'] ?? '',
                'nombre'      => $meta['nombre'] ?? 'Cliente',
                'pista'       => $meta['pista'] ?? '',
                'fecha'       => $meta['fecha'] ?? '',
                'hora'        => $meta['hora'] ?? '',
                'dni'         => $meta['dni'] ?? '',
                'telefono'    => '',
                'precio_total'=> ($session['amount_total'] ?? 0) / 100,
            ];
        }

        // Verificar que no se haya procesado ya (idempotencia)
        $checkStmt = $pdo->prepare("SELECT codigo_acceso FROM reservas WHERE pista = ? AND fecha = ? AND hora_inicio = ? AND estado != 'cancelada'");
        $partes = explode(' - ', $reservaData['hora']);
        $hora_inicio = $partes[0] ?? '';
        $hora_fin = $partes[1] ?? '';
        $checkStmt->execute([$reservaData['pista'], $reservaData['fecha'], $hora_inicio]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            // Ya existe → usar el código existente (doble-click o recarga)
            $codigo_acceso = $existing['codigo_acceso'];
        } else {
            // Crear la reserva
            try {
                date_default_timezone_set('Europe/Madrid');
                $codigo_acceso = 'TRC-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

                // Detectar columnas disponibles
                $columnsResult = $pdo->query("SHOW COLUMNS FROM reservas");
                $existingColumns = [];
                while ($col = $columnsResult->fetch()) {
                    $existingColumns[] = $col['Field'];
                }

                $insertCols = ['pista', 'fecha', 'hora_inicio', 'hora_fin', 'estado', 'usuario_nombre', 'usuario_email', 'codigo_acceso'];
                $insertVals = [$reservaData['pista'], $reservaData['fecha'], $hora_inicio, $hora_fin, 'reservada', $reservaData['nombre'], $reservaData['email'], $codigo_acceso];

                if (in_array('dni', $existingColumns)) { $insertCols[] = 'dni'; $insertVals[] = $reservaData['dni']; }
                if (in_array('telefono', $existingColumns)) { $insertCols[] = 'telefono'; $insertVals[] = $reservaData['telefono']; }
                if (in_array('precio_total', $existingColumns)) { $insertCols[] = 'precio_total'; $insertVals[] = $reservaData['precio_total']; }
                if (in_array('usuario_id', $existingColumns)) { $insertCols[] = 'usuario_id'; $insertVals[] = $_SESSION['user_id'] ?? null; }
                if (in_array('fecha_registro', $existingColumns)) { $insertCols[] = 'fecha_registro'; $insertVals[] = date('Y-m-d H:i:s'); }

                $placeholders = implode(', ', array_fill(0, count($insertCols), '?'));
                $colsStr = implode(', ', $insertCols);
                $insertStmt = $pdo->prepare("INSERT INTO reservas ($colsStr) VALUES ($placeholders)");
                $insertStmt->execute($insertVals);

                // Enviar email de confirmación
                $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($codigo_acceso);
                $emailHtml = "
                <html><head><title>Confirmación de Reserva</title></head>
                <body style='font-family: Arial, sans-serif; color: #333; line-height: 1.6;'>
                    <h2 style='color: #914f26;'>¡Hola {$reservaData['nombre']}! Tu reserva está confirmada.</h2>
                    <p>Gracias por alquilar las instalaciones deportivas del Ayuntamiento de Tarancón.</p>
                    <table width='100%' cellpadding='10' style='background-color: #f4f6f8; border-radius: 8px; margin: 20px 0; border: 1px solid #ddd;'>
                        <tr><td><strong>Instalación:</strong></td><td>{$reservaData['pista']}</td></tr>
                        <tr><td><strong>Fecha:</strong></td><td>{$reservaData['fecha']}</td></tr>
                        <tr><td><strong>Hora:</strong></td><td>{$reservaData['hora']}</td></tr>
                    </table>
                    <div style='background-color: #fff4e5; border: 2px dashed #c36a33; padding: 20px; text-align: center; border-radius: 8px;'>
                        <p style='margin: 0; color: #914f26; font-size: 14px;'><strong>TU CÓDIGO DE ACCESO</strong></p>
                        <h1 style='margin: 10px 0; font-size: 42px; letter-spacing: 5px; color: #522d16;'>$codigo_acceso</h1>
                        <img src='$qr_url' alt='QR' style='margin-top: 15px; border-radius: 8px; width: 200px; height: 200px;'/>
                    </div>
                    <p style='color: #d32f2f; font-size: 0.9em;'><em>⚠️ Cancelación hasta 30 minutos antes. Después, no se devuelve el importe.</em></p>
                </body></html>";

                send_email($reservaData['email'], $reservaData['nombre'], 'Confirmación de Reserva - Ayuntamiento de Tarancón', $emailHtml);

                // Limpiar datos de sesión de Stripe
                unset($_SESSION['stripe_reserva']);

            } catch (\PDOException $e) {
                error_log("Stripe success page DB error: " . $e->getMessage());
                $error = "Error al registrar la reserva. Contacta con el Ayuntamiento.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Completado - Alquileres Tarancón</title>
    <link rel="stylesheet" href="../css/home.css">
    <link rel="stylesheet" href="../css/checkout.css">
    <link rel="stylesheet" href="../css/cookies.css">
    <script src="../js/cookies.js" defer></script>
    <style>
        .code-box { background: #fff4e5; border: 2px dashed #c36a33; padding: 1.5rem; text-align: center; border-radius: 12px; margin: 1.5rem 0; cursor: pointer; transition: all 0.2s; }
        .code-box:hover { transform: scale(1.02); box-shadow: 0 6px 24px rgba(209,86,21,.18); }
        .code-box .access-code { font-size: 2rem; font-weight: 700; letter-spacing: 5px; color: #522d16; font-family: 'Courier New', monospace; margin: 0.5rem 0; }
        .code-box .code-label { color: #914f26; font-size: 0.85rem; text-transform: uppercase; font-weight: 600; margin: 0; }
        .code-copy-hint { font-size: 0.82rem; color: #888; margin-top: 0.5rem; }
        .error-container { max-width: 500px; margin: 4rem auto; text-align: center; padding: 2rem; background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
        .error-container h2 { color: #c62828; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main class="main-content checkout-content">
        <section class="checkout-layout">
            <?php if ($error): ?>
                <div class="error-container">
                    <h2>⚠️ Error</h2>
                    <p><?= htmlspecialchars($error) ?></p>
                    <a href="home.php" class="btn-primary" style="display:inline-block;margin-top:1rem;">Volver al Inicio</a>
                </div>
            <?php elseif ($codigo_acceso): ?>
                <div class="checkout-success-container">
                    <div class="success-card">
                        <div class="success-icon-wrap"><div class="success-icon">✓</div></div>
                        <h2>¡Pago Confirmado y Reserva Creada!</h2>
                        <p>El pago se ha procesado correctamente con Stripe.</p>
                        <p>Hemos enviado los detalles a <strong><?= htmlspecialchars($reservaData['email'] ?? '') ?></strong>.</p>

                        <div class="code-box" id="code-box-copy" title="Haz clic para copiar">
                            <p class="code-label">Código de Acceso</p>
                            <p class="access-code" id="display-code"><?= htmlspecialchars($codigo_acceso) ?></p>
                            <p class="code-copy-hint"><span id="copy-icon">📋</span> <span id="copy-text">Haz clic para copiar</span></p>
                        </div>

                        <div class="success-instructions">
                            <p><strong>¿Qué debo hacer ahora?</strong></p>
                            <ul>
                                <li>Guarde este código alfanumérico.</li>
                                <li>Acuda a las instalaciones unos minutos antes.</li>
                                <li>Enseñe o dicte este código al conserje.</li>
                            </ul>
                        </div>
                        <a href="home.php" class="btn-primary btn-back-home">Volver al Inicio</a>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php include '../includes/footer.php'; ?>

    <script>
        // Copiar código al portapapeles
        const codeBox = document.getElementById('code-box-copy');
        if (codeBox) {
            codeBox.addEventListener('click', () => {
                const code = document.getElementById('display-code')?.textContent;
                if (code) {
                    navigator.clipboard.writeText(code).then(() => {
                        document.getElementById('copy-icon').textContent = '✅';
                        document.getElementById('copy-text').textContent = '¡Copiado!';
                        setTimeout(() => {
                            document.getElementById('copy-icon').textContent = '📋';
                            document.getElementById('copy-text').textContent = 'Haz clic para copiar';
                        }, 2000);
                    });
                }
            });
        }
    </script>
</body>
</html>
