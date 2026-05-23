<?php
// api/stripe-checkout.php — Crea sesion Stripe Checkout para una reserva de pista.
// - CSRF + auth usuario
// - Precio recalculado SIEMPRE en servidor (BD)
// - metadata.user_id incluido para validar el dueno al confirmar

require_once __DIR__ . '/../includes/SecurityHandler.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'conexion.php';
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$userId = SecurityHandler::requireUser();

if (!USE_STRIPE) {
    SecurityHandler::jsonError(400, 'Stripe no esta configurado.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    SecurityHandler::jsonError(405, 'Metodo no permitido.');
}

$data = read_json_body();
if (empty($data)) $data = $_POST;
SecurityHandler::requireCSRF($data);

$email = trim($_SESSION['user_email'] ?? '');
$nombre = trim($_SESSION['user_nombre'] ?? '');
$dni = trim($_SESSION['user_dni'] ?? '');
$telefono = trim($_SESSION['user_telefono'] ?? '');

$pista = trim($data['pista'] ?? '');
$fecha = trim($data['fecha'] ?? '');
$hora = trim($data['hora'] ?? '');

if ($email === '' || $pista === '' || $fecha === '' || $hora === '') {
    SecurityHandler::jsonError(400, 'Datos de la reserva incompletos.');
}

$horaParts = explode(' - ', $hora);
$horaInicio = $horaParts[0] ?? '';

$precio = 4.00;
if ($horaInicio !== '') {
    try {
        $stmtPrecio = $pdo->prepare("SELECT precio_base, precio_luz FROM precios_personalizados WHERE pista = ? AND fecha = ? AND hora_inicio = ? LIMIT 1");
        $stmtPrecio->execute([$pista, $fecha, $horaInicio]);
        $row = $stmtPrecio->fetch();
        if ($row) {
            $precio = ((float) ($row['precio_base'] ?? 4.00)) + ((float) ($row['precio_luz'] ?? 0));
        }
    } catch (\PDOException $e) {
        // fallback tarifa base
    }
}

$priceInCents = (int) round($precio * 100);
if ($priceInCents <= 0) {
    SecurityHandler::jsonError(400, 'Precio invalido.');
}

if (defined('SITE_URL') && SITE_URL !== '') {
    $baseUrl = rtrim(SITE_URL, '/');
} else {
    if (defined('IS_PRODUCTION') && IS_PRODUCTION) {
        error_log('stripe_checkout: SITE_URL no configurada en produccion');
        SecurityHandler::jsonError(500, 'Configuracion del servidor incompleta.');
    }
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF']));
    $baseUrl = rtrim($baseUrl, '/');
}

$_SESSION['stripe_reserva'] = [
    'user_id' => $userId,
    'email' => $email,
    'nombre' => $nombre,
    'pista' => $pista,
    'fecha' => $fecha,
    'hora' => $hora,
    'precio_total' => $precio,
    'dni' => $dni,
    'telefono' => $telefono,
];

$stripeData = http_build_query([
    'payment_method_types[]' => 'card',
    'mode' => 'payment',
    'customer_email' => $email,
    'line_items[0][price_data][currency]' => 'eur',
    'line_items[0][price_data][unit_amount]' => $priceInCents,
    'line_items[0][price_data][product_data][name]' => "Reserva $pista",
    'line_items[0][price_data][product_data][description]' => "Fecha: $fecha | Hora: $hora",
    'line_items[0][quantity]' => 1,
    'success_url' => "$baseUrl/html/pago-exitoso.php?session_id={CHECKOUT_SESSION_ID}",
    'cancel_url' => "$baseUrl/html/checkout.php?pago=cancelado",
    'metadata[user_id]' => $userId,
    'metadata[pista]' => $pista,
    'metadata[fecha]' => $fecha,
    'metadata[hora]' => $hora,
    'metadata[hora_inicio]' => $horaInicio,
    'metadata[tipo]' => 'reserva',
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
    error_log('stripe_checkout_curl: ' . $curlError);
    SecurityHandler::jsonError(500, 'Error de conexion con la pasarela de pago.');
}

$session = json_decode($response, true);
if ($httpCode >= 200 && $httpCode < 300 && isset($session['url'])) {
    audit_log($pdo, 'stripe_checkout_create', 'reserva', null, ['pista' => $pista, 'fecha' => $fecha, 'hora' => $hora, 'session_id' => $session['id'] ?? null]);
    SecurityHandler::jsonOk([
        'checkout_url' => $session['url'],
        'session_id' => $session['id'] ?? null,
    ]);
}

$errorMsg = $session['error']['message'] ?? "Error HTTP $httpCode";
error_log('stripe_checkout_error: ' . $errorMsg);
SecurityHandler::jsonError(500, 'Error al crear sesion de pago.');
