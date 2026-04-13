<?php
// api/bonos-checkout.php — Crea sesión de Stripe Checkout para comprar un bono
// Similar a stripe-checkout.php pero para la compra de packs de bonos.

ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_once 'conexion.php';
header("Content-Type: application/json; charset=UTF-8");

// Requiere login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Debes iniciar sesión."]);
    exit;
}

// Verificar que Stripe está configurado
if (!USE_STRIPE) {
    // Flujo simulado: crear bono directamente
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Método no permitido."]);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) $data = $_POST;
    $tipo = $data['tipo'] ?? '';

    if (empty($tipo)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Tipo de bono no especificado."]);
        exit;
    }

    try {
        // Obtener configuración del pack
        $configStmt = $pdo->prepare("SELECT * FROM bonos_config WHERE tipo = ? AND activo = 1");
        $configStmt->execute([$tipo]);
        $pack = $configStmt->fetch();

        if (!$pack) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Pack de bono no encontrado."]);
            exit;
        }

        // Calcular fecha de caducidad
        $fechaCaducidad = date('Y-m-d', strtotime("+{$pack['caducidad_meses']} months"));

        // Crear el bono
        $insertStmt = $pdo->prepare("
            INSERT INTO bonos (usuario_id, tipo, nombre, usos_totales, usos_restantes, precio_pagado, estado, fecha_caducidad)
            VALUES (?, ?, ?, ?, ?, ?, 'activo', ?)
        ");
        $insertStmt->execute([
            $_SESSION['user_id'],
            $pack['tipo'],
            $pack['nombre'],
            $pack['usos'],
            $pack['usos'],
            $pack['precio'],
            $fechaCaducidad
        ]);

        $bonoId = $pdo->lastInsertId();

        // Enviar email de confirmación
        require_once __DIR__ . '/mailer.php';
        $nombre = $_SESSION['user_nombre'] ?? 'Usuario';
        $email = $_SESSION['user_email'] ?? '';

        $emailHtml = "
        <html><head><title>Bono Adquirido</title></head>
        <body style='font-family: Arial, sans-serif; color: #333; line-height: 1.6;'>
            <h2 style='color: #914f26;'>¡Hola {$nombre}! Tu bono ha sido activado.</h2>
            <p>Gracias por adquirir un bono de sesiones del Ayuntamiento de Tarancón.</p>
            <table width='100%' cellpadding='10' style='background-color: #f4f6f8; border-radius: 8px; margin: 20px 0; border: 1px solid #ddd;'>
                <tr><td><strong>Bono:</strong></td><td>{$pack['nombre']}</td></tr>
                <tr><td><strong>Sesiones:</strong></td><td>{$pack['usos']} usos</td></tr>
                <tr><td><strong>Precio:</strong></td><td>" . number_format($pack['precio'], 2, ',', '.') . " €</td></tr>
                <tr><td><strong>Válido hasta:</strong></td><td>" . date('d/m/Y', strtotime($fechaCaducidad)) . "</td></tr>
            </table>
            <div style='background-color: #e8f5e9; border: 2px solid #4caf50; padding: 20px; text-align: center; border-radius: 8px;'>
                <p style='margin: 0; color: #2e7d32; font-size: 16px;'><strong>🎟️ Ya puedes usar tu bono al reservar cualquier pista</strong></p>
                <p style='margin: 5px 0 0; color: #555;'>Marca la opción \"Usar bono\" en el checkout.</p>
            </div>
        </body></html>";

        send_email($email, $nombre, 'Bono Activado - Ayuntamiento de Tarancón', $emailHtml);

        echo json_encode([
            "status" => "success",
            "message" => "Bono creado correctamente (pago simulado).",
            "bono_id" => $bonoId,
            "redirect" => "bono-exitoso.php?bono_id=" . $bonoId . "&simulado=1"
        ]);
    } catch (\PDOException $e) {
        error_log("bonos-checkout.php simulated error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error al crear el bono."]);
    }
    exit;
}

// ── FLUJO STRIPE ──
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Método no permitido."]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) $data = $_POST;
$tipo = $data['tipo'] ?? '';

if (empty($tipo)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Tipo de bono no especificado."]);
    exit;
}

try {
    // Obtener configuración del pack
    $configStmt = $pdo->prepare("SELECT * FROM bonos_config WHERE tipo = ? AND activo = 1");
    $configStmt->execute([$tipo]);
    $pack = $configStmt->fetch();

    if (!$pack) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Pack de bono no encontrado."]);
        exit;
    }

    $priceInCents = intval(round($pack['precio'] * 100));

    // URL base
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF']));
    $baseUrl = rtrim($baseUrl, '/');

    // Guardar datos en sesión
    $_SESSION['stripe_bono'] = [
        'tipo' => $pack['tipo'],
        'nombre' => $pack['nombre'],
        'usos' => $pack['usos'],
        'precio' => $pack['precio'],
        'caducidad_meses' => $pack['caducidad_meses'],
        'usuario_id' => $_SESSION['user_id'],
        'usuario_email' => $_SESSION['user_email'],
        'usuario_nombre' => $_SESSION['user_nombre'] ?? 'Usuario',
    ];

    // Crear Checkout Session
    $stripeData = http_build_query([
        'payment_method_types[]' => 'card',
        'mode'                   => 'payment',
        'customer_email'         => $_SESSION['user_email'],
        'line_items[0][price_data][currency]'     => 'eur',
        'line_items[0][price_data][unit_amount]'  => $priceInCents,
        'line_items[0][price_data][product_data][name]'        => $pack['nombre'],
        'line_items[0][price_data][product_data][description]' => "{$pack['usos']} sesiones para cualquier pista deportiva",
        'line_items[0][quantity]' => 1,
        'success_url' => "$baseUrl/html/bono-exitoso.php?session_id={CHECKOUT_SESSION_ID}",
        'cancel_url'  => "$baseUrl/html/bonos.php?pago=cancelado",
        'metadata[tipo_bono]' => $pack['tipo'],
        'metadata[usos]'      => $pack['usos'],
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
    unset($ch);

    if ($curlError) {
        error_log("Stripe bono cURL error: $curlError");
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
        error_log("Stripe bono error: $errorMsg");
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error al crear sesión de pago: $errorMsg"]);
    }

} catch (\PDOException $e) {
    error_log("bonos-checkout.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Error interno."]);
}
