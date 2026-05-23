<?php
// api/bonos-checkout.php — Crea sesion Stripe Checkout para comprar un bono.
// - CSRF + auth usuario
// - metadata.user_id + tipo para validar al confirmar
// - En PRODUCCION con Stripe configurado, NO existe flujo simulado: si !USE_STRIPE devolvemos error

require_once __DIR__ . '/../includes/SecurityHandler.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'conexion.php';
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$userId = SecurityHandler::requireUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    SecurityHandler::jsonError(405, 'Metodo no permitido.');
}

$data = read_json_body();
if (empty($data)) $data = $_POST;
SecurityHandler::requireCSRF($data);

$tipo = trim($data['tipo'] ?? '');
if ($tipo === '') {
    SecurityHandler::jsonError(400, 'Tipo de bono no especificado.');
}

try {
    $configStmt = $pdo->prepare("SELECT * FROM bonos_config WHERE tipo = ? AND activo = 1");
    $configStmt->execute([$tipo]);
    $pack = $configStmt->fetch();
    if (!$pack) {
        SecurityHandler::jsonError(404, 'Pack de bono no encontrado.');
    }
} catch (\PDOException $e) {
    error_log('bonos_checkout_pack: ' . $e->getMessage());
    SecurityHandler::jsonError(500, 'Error interno.');
}

// FLUJO SIMULADO solo en desarrollo
if (!USE_STRIPE) {
    if (defined('IS_PRODUCTION') && IS_PRODUCTION) {
        SecurityHandler::jsonError(503, 'Pagos no disponibles temporalmente.');
    }
    try {
        $fechaCaducidad = date('Y-m-d', strtotime("+{$pack['caducidad_meses']} months"));
        $insertStmt = $pdo->prepare(
            "INSERT INTO bonos (usuario_id, tipo, nombre, usos_totales, usos_restantes, precio_pagado, estado, fecha_caducidad)
             VALUES (?, ?, ?, ?, ?, ?, 'activo', ?)"
        );
        $insertStmt->execute([
            $userId, $pack['tipo'], $pack['nombre'],
            $pack['usos'], $pack['usos'], $pack['precio'], $fechaCaducidad,
        ]);
        $bonoId = $pdo->lastInsertId();
        audit_log($pdo, 'bono_create_simulated', 'bono', $bonoId, ['tipo' => $pack['tipo']]);

        require_once __DIR__ . '/mailer.php';
        $nH = esc_html($_SESSION['user_nombre'] ?? 'Usuario');
        $packNombreH = esc_html($pack['nombre']);
        $usosH = (int) $pack['usos'];
        $precioH = number_format((float) $pack['precio'], 2, ',', '.');
        $fechaCadH = date('d/m/Y', strtotime($fechaCaducidad));
        $emailHtml = "<html><body style='font-family: Arial, sans-serif; color: #333; line-height: 1.6;'>
            <h2 style='color: #914f26;'>Hola $nH, tu bono ha sido activado.</h2>
            <table width='100%' cellpadding='10' style='background-color: #f4f6f8; border-radius: 8px; margin: 20px 0; border: 1px solid #ddd;'>
                <tr><td><strong>Bono:</strong></td><td>$packNombreH</td></tr>
                <tr><td><strong>Sesiones:</strong></td><td>$usosH usos</td></tr>
                <tr><td><strong>Precio:</strong></td><td>$precioH &euro;</td></tr>
                <tr><td><strong>Valido hasta:</strong></td><td>$fechaCadH</td></tr>
            </table>
        </body></html>";
        send_email($_SESSION['user_email'] ?? '', $_SESSION['user_nombre'] ?? 'Usuario', 'Bono Activado - Ayuntamiento de Tarancon', $emailHtml);

        SecurityHandler::jsonOk([
            'message' => 'Bono creado (pago simulado).',
            'bono_id' => $bonoId,
            'redirect' => 'bono-exitoso.php?bono_id=' . $bonoId . '&simulado=1',
        ]);
    } catch (\PDOException $e) {
        error_log('bonos_checkout_simulated: ' . $e->getMessage());
        SecurityHandler::jsonError(500, 'Error al crear el bono.');
    }
}

// FLUJO STRIPE LIVE/TEST
$priceInCents = (int) round((float) $pack['precio'] * 100);
if ($priceInCents <= 0) {
    SecurityHandler::jsonError(400, 'Precio invalido.');
}

if (defined('SITE_URL') && SITE_URL !== '') {
    $baseUrl = rtrim(SITE_URL, '/');
} else {
    if (defined('IS_PRODUCTION') && IS_PRODUCTION) {
        error_log('bonos_checkout: SITE_URL no configurada en produccion');
        SecurityHandler::jsonError(500, 'Configuracion del servidor incompleta.');
    }
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF']));
    $baseUrl = rtrim($baseUrl, '/');
}

$_SESSION['stripe_bono'] = [
    'user_id' => $userId,
    'tipo' => $pack['tipo'],
    'nombre' => $pack['nombre'],
    'usos' => $pack['usos'],
    'precio' => $pack['precio'],
    'caducidad_meses' => $pack['caducidad_meses'],
    'usuario_email' => $_SESSION['user_email'] ?? '',
    'usuario_nombre' => $_SESSION['user_nombre'] ?? 'Usuario',
];

$stripeData = http_build_query([
    'payment_method_types[]' => 'card',
    'mode' => 'payment',
    'customer_email' => $_SESSION['user_email'] ?? '',
    'line_items[0][price_data][currency]' => 'eur',
    'line_items[0][price_data][unit_amount]' => $priceInCents,
    'line_items[0][price_data][product_data][name]' => $pack['nombre'],
    'line_items[0][price_data][product_data][description]' => "{$pack['usos']} sesiones para cualquier pista",
    'line_items[0][quantity]' => 1,
    'success_url' => "$baseUrl/html/bono-exitoso.php?session_id={CHECKOUT_SESSION_ID}",
    'cancel_url' => "$baseUrl/html/bonos.php?pago=cancelado",
    'metadata[user_id]' => $userId,
    'metadata[tipo_bono]' => $pack['tipo'],
    'metadata[usos]' => $pack['usos'],
    'metadata[tipo]' => 'bono',
]);

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $stripeData,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => STRIPE_SECRET_KEY . ':',
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
unset($ch);

if ($curlError) {
    error_log('bonos_checkout_curl: ' . $curlError);
    SecurityHandler::jsonError(500, 'Error de conexion con la pasarela de pago.');
}

$session = json_decode($response, true);
if ($httpCode >= 200 && $httpCode < 300 && isset($session['url'])) {
    audit_log($pdo, 'stripe_bono_checkout_create', 'bono', null, ['tipo' => $pack['tipo'], 'session_id' => $session['id'] ?? null]);
    SecurityHandler::jsonOk([
        'checkout_url' => $session['url'],
        'session_id' => $session['id'] ?? null,
    ]);
}

$errorMsg = $session['error']['message'] ?? "Error HTTP $httpCode";
error_log('bonos_checkout_error: ' . $errorMsg);
SecurityHandler::jsonError(500, 'Error al crear sesion de pago.');
