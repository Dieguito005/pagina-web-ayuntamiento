<?php
// api/stripe-checkout.php — Crea una sesión de Stripe Checkout
// Recibe los datos de la reserva y redirige al usuario a la pasarela de Stripe.
// No necesita librería: usa cURL directamente contra la API REST de Stripe.

ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_once 'conexion.php';
header("Content-Type: application/json; charset=UTF-8");

// Verificar que Stripe está configurado
if (!USE_STRIPE) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Stripe no está configurado."]);
    exit;
}

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Método no permitido."]);
    exit;
}

// Recibir datos
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) $data = $_POST;

$email       = $data['email'] ?? '';
$nombre      = $data['nombre'] ?? '';
$pista       = $data['pista'] ?? '';
$fecha       = $data['fecha'] ?? '';
$hora        = $data['hora'] ?? '';
$precio      = floatval($data['precio_total'] ?? 0);
$dni         = $data['dni'] ?? '';
$telefono    = $data['telefono'] ?? '';

if (empty($email) || empty($pista) || empty($fecha) || empty($hora) || $precio <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Datos incompletos."]);
    exit;
}

// Stripe trabaja en céntimos
$priceInCents = intval(round($precio * 100));

// Construir la URL base del sitio
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF']));
$baseUrl = rtrim($baseUrl, '/');

// Guardar los datos en sesión para recuperarlos tras el pago (más seguro que query params)
$_SESSION['stripe_reserva'] = [
    'email'       => $email,
    'nombre'      => $nombre,
    'pista'       => $pista,
    'fecha'       => $fecha,
    'hora'        => $hora,
    'precio_total'=> $precio,
    'dni'         => $dni,
    'telefono'    => $telefono,
];

// Crear Checkout Session via API REST de Stripe
$stripeData = http_build_query([
    'payment_method_types[]' => 'card',
    'mode'                   => 'payment',
    'customer_email'         => $email,
    'line_items[0][price_data][currency]'     => 'eur',
    'line_items[0][price_data][unit_amount]'  => $priceInCents,
    'line_items[0][price_data][product_data][name]'        => "Reserva $pista",
    'line_items[0][price_data][product_data][description]' => "Fecha: $fecha | Hora: $hora",
    'line_items[0][quantity]' => 1,
    'success_url' => "$baseUrl/html/pago-exitoso.php?session_id={CHECKOUT_SESSION_ID}",
    'cancel_url'  => "$baseUrl/html/checkout.php?pago=cancelado",
    'metadata[pista]'  => $pista,
    'metadata[fecha]'  => $fecha,
    'metadata[hora]'   => $hora,
    'metadata[nombre]' => $nombre,
    'metadata[dni]'    => $dni,
]);

$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $stripeData,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD        => STRIPE_SECRET_KEY . ':',
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    error_log("Stripe cURL error: $curlError");
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error de conexión con la pasarela de pago."]);
    exit;
}

$session = json_decode($response, true);

if ($httpCode >= 200 && $httpCode < 300 && isset($session['url'])) {
    echo json_encode([
        "status"       => "success",
        "checkout_url" => $session['url'],
        "session_id"   => $session['id']
    ]);
} else {
    $errorMsg = $session['error']['message'] ?? "Error HTTP $httpCode";
    error_log("Stripe error: $errorMsg");
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error al crear sesión de pago: $errorMsg"]);
}
