<?php
// html/pago-exitoso.php — Pagina mostrada al usuario tras volver de Stripe Checkout.
//
// IMPORTANTE: la creacion de reserva es responsabilidad del webhook
// api/stripe-webhook.php. Esta pagina solo MUESTRA estado. Como fallback (entornos
// sin webhook configurado todavia), si la reserva no existe aun y los datos
// metadata son consistentes con el usuario logueado, se intenta crearla aqui.

// NOTA: no exigimos sesion activa: si el pago tarda mas que el timeout (1800s) la sesion
// puede haber expirado. La autorizacion del pago se valida via metadata.user_id Stripe.
require_once '../config.php';
require_once '../api/conexion.php';
require_once '../api/mailer.php';
require_once '../includes/SecurityHandler.php';
require_once '../includes/audit.php';
require_once '../includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();
send_security_headers();

$error = null;
$codigo_acceso = null;
$reservaData = null;
$session_id = trim($_GET['session_id'] ?? '');

if ($session_id === '' || !preg_match('/^cs_[A-Za-z0-9_]{10,}$/', $session_id)) {
    $error = 'Sesion de pago no encontrada.';
} else {
    // 1) Comprobar si el webhook ya creo la reserva (por session_id si guardamos algo, o por slot)
    //    Aqui nos apoyamos en $_SESSION['stripe_reserva'] para localizar el slot esperado.
    $expected = $_SESSION['stripe_reserva'] ?? null;

    // Pedir la sesion a Stripe para verificar pago
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
        // Validar que el user_id en metadata coincide con la sesion actual (anti-IDOR)
        $metaUserId = isset($session['metadata']['user_id']) ? (int) $session['metadata']['user_id'] : 0;
        $sessionUserId = (int) ($_SESSION['user_id'] ?? 0);

        if ($metaUserId <= 0) {
            audit_log($pdo, 'stripe_success_no_user_in_metadata', 'reserva', null, ['session_id' => $session_id]);
            $error = 'No se ha podido identificar el pago.';
        } elseif ($sessionUserId > 0 && $metaUserId !== $sessionUserId) {
            audit_log($pdo, 'stripe_success_user_mismatch', 'reserva', null, ['session_user' => $sessionUserId, 'meta_user' => $metaUserId, 'session_id' => $session_id]);
            $error = 'Esta confirmacion de pago no corresponde a tu cuenta.';
        } else {
            // $sessionUserId puede ser 0 (sesion expirada durante Stripe Checkout). Confiamos
            // en metadata.user_id (lo escribimos al crear la sesion) y mostramos el codigo.
            $meta = $session['metadata'] ?? [];
            $pista = $meta['pista'] ?? '';
            $fecha = $meta['fecha'] ?? '';
            $hora = $meta['hora'] ?? '';
            $horaInicio = $meta['hora_inicio'] ?? '';
            $horaParts = explode(' - ', $hora);
            $horaFin = $horaParts[1] ?? '';

            // Recargar datos canonicos del usuario
            $uStmt = $pdo->prepare("SELECT nombre, email, dni, telefono FROM usuarios WHERE id = ?");
            $uStmt->execute([$metaUserId]);
            $user = $uStmt->fetch() ?: [];

            // Comprobar si la reserva ya existe (webhook adelantado o doble click)
            $checkStmt = $pdo->prepare("SELECT codigo_acceso, usuario_id FROM reservas WHERE pista = ? AND fecha = ? AND hora_inicio = ? AND estado != 'cancelada' LIMIT 1");
            $checkStmt->execute([$pista, $fecha, $horaInicio]);
            $existing = $checkStmt->fetch();

            if ($existing) {
                if ((int) $existing['usuario_id'] !== $metaUserId) {
                    $error = 'Esta reserva pertenece a otra cuenta.';
                } else {
                    $codigo_acceso = $existing['codigo_acceso'];
                    $reservaData = ['email' => $user['email'] ?? '', 'nombre' => $user['nombre'] ?? ''];
                }
            } else {
                // FALLBACK: el webhook no ha procesado aun. Creamos la reserva aqui.
                try {
                    date_default_timezone_set('Europe/Madrid');
                    $codigo_acceso = 'TRC-' . strtoupper(bin2hex(random_bytes(3)));
                    $precio = (float) ($session['amount_total'] ?? 0) / 100;

                    $insert = $pdo->prepare(
                        "INSERT INTO reservas (pista, fecha, hora_inicio, hora_fin, estado, usuario_nombre, usuario_email, dni, telefono, precio_total, codigo_acceso, usuario_id, fecha_registro)
                         VALUES (?, ?, ?, ?, 'reservada', ?, ?, ?, ?, ?, ?, ?, NOW())"
                    );
                    $insert->execute([
                        $pista, $fecha, $horaInicio, $horaFin,
                        $user['nombre'] ?? '', $user['email'] ?? '',
                        $user['dni'] ?? null, $user['telefono'] ?? null,
                        $precio, $codigo_acceso, $metaUserId,
                    ]);

                    $reservaId = $pdo->lastInsertId();
                    audit_log($pdo, 'stripe_success_reserva_fallback', 'reserva', $reservaId, ['session_id' => $session_id]);

                    // Email
                    $nH = esc_html($user['nombre'] ?? '');
                    $pH = esc_html($pista);
                    $fH = esc_html($fecha);
                    $hH = esc_html($hora);
                    $cH = esc_html($codigo_acceso);
                    $emailHtml = "<html><body style='font-family: Arial, sans-serif; color:#333; line-height:1.6;'>
                        <h2 style='color:#914f26;'>Hola $nH, tu reserva esta confirmada.</h2>
                        <table width='100%' cellpadding='10' style='background:#f4f6f8; border-radius:8px; margin:20px 0; border:1px solid #ddd;'>
                            <tr><td><strong>Instalacion:</strong></td><td>$pH</td></tr>
                            <tr><td><strong>Fecha:</strong></td><td>$fH</td></tr>
                            <tr><td><strong>Hora:</strong></td><td>$hH</td></tr>
                            <tr><td><strong>Titular:</strong></td><td>$nH</td></tr>
                        </table>
                        <div style='background:#fff4e5; border:2px dashed #c36a33; padding:20px; text-align:center; border-radius:8px;'>
                            <p style='margin:0; color:#914f26; font-size:14px;'><strong>TU CODIGO DE ACCESO</strong></p>
                            <h1 style='margin:10px 0; font-size:42px; letter-spacing:5px; color:#522d16;'>$cH</h1>
                        </div>
                        <p style='color:#d32f2f; font-size:0.9em;'><em>Cancelacion hasta 30 minutos antes. Despues no se devuelve el importe.</em></p>
                    </body></html>";
                    send_email($user['email'] ?? '', $user['nombre'] ?? '', 'Confirmacion de Reserva - Ayuntamiento de Tarancon', $emailHtml);

                    unset($_SESSION['stripe_reserva']);
                    $reservaData = ['email' => $user['email'] ?? '', 'nombre' => $user['nombre'] ?? ''];
                } catch (\PDOException $e) {
                    error_log('pago_exitoso_fallback: ' . $e->getMessage());
                    $error = 'Error al registrar la reserva. Contacta con el Ayuntamiento.';
                }
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
    <title>Pago Completado - Alquileres Tarancon</title>
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
                    <h2>Error</h2>
                    <p><?= esc_html($error) ?></p>
                    <a href="home.php" class="btn-primary" style="display:inline-block;margin-top:1rem;">Volver al Inicio</a>
                </div>
            <?php elseif ($codigo_acceso): ?>
                <div class="checkout-success-container">
                    <div class="success-card">
                        <div class="success-icon-wrap"><div class="success-icon">&check;</div></div>
                        <h2>Pago Confirmado y Reserva Creada</h2>
                        <p>El pago se ha procesado correctamente con Stripe.</p>
                        <p>Hemos enviado los detalles a <strong><?= esc_html($reservaData['email'] ?? '') ?></strong>.</p>

                        <div class="code-box" id="code-box-copy" title="Haz clic para copiar">
                            <p class="code-label">Codigo de Acceso</p>
                            <p class="access-code" id="display-code"><?= esc_html($codigo_acceso) ?></p>
                            <p class="code-copy-hint"><span id="copy-icon">&#128203;</span> <span id="copy-text">Haz clic para copiar</span></p>
                        </div>

                        <div class="success-instructions">
                            <p><strong>Que debo hacer ahora?</strong></p>
                            <ul>
                                <li>Guarde este codigo alfanumerico.</li>
                                <li>Acuda a las instalaciones unos minutos antes.</li>
                                <li>Enseñe o dicte este codigo al conserje.</li>
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
        const codeBox = document.getElementById('code-box-copy');
        if (codeBox) {
            codeBox.addEventListener('click', () => {
                const code = document.getElementById('display-code')?.textContent;
                if (code) {
                    navigator.clipboard.writeText(code).then(() => {
                        document.getElementById('copy-icon').textContent = '✅';
                        document.getElementById('copy-text').textContent = 'Copiado';
                        setTimeout(() => {
                            document.getElementById('copy-icon').textContent = '\u{1F4CB}';
                            document.getElementById('copy-text').textContent = 'Haz clic para copiar';
                        }, 2000);
                    });
                }
            });
        }
    </script>
</body>
</html>
