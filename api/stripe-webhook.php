<?php
// api/stripe-webhook.php — Endpoint para webhooks de Stripe (firma verificada).
//
// Configurar en Stripe Dashboard > Webhooks:
//   URL:    https://reservas.tarancon.es/api/stripe-webhook.php
//   Events: checkout.session.completed
//   Secret: copiar en env.php como ENV_STRIPE_WEBHOOK_SECRET
//
// Esta es la fuente DE VERDAD para crear reservas y bonos: pago-exitoso.php y
// bono-exitoso.php solo muestran estado al usuario; la creacion ocurre aqui.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/mailer.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (!defined('STRIPE_WEBHOOK_SECRET') || STRIPE_WEBHOOK_SECRET === '') {
    error_log('stripe_webhook: STRIPE_WEBHOOK_SECRET no configurado');
    http_response_code(500);
    echo json_encode(['error' => 'webhook_not_configured']);
    exit;
}

// Verificacion manual de la firma de Stripe (formato: t=timestamp,v1=signature)
function verify_stripe_signature(string $payload, string $sigHeader, string $secret, int $toleranceSeconds = 300): bool
{
    if ($sigHeader === '') return false;
    $parts = [];
    foreach (explode(',', $sigHeader) as $pair) {
        $kv = explode('=', $pair, 2);
        if (count($kv) === 2) $parts[trim($kv[0])][] = trim($kv[1]);
    }
    if (empty($parts['t'][0]) || empty($parts['v1'])) return false;
    $timestamp = (int) $parts['t'][0];
    if (abs(time() - $timestamp) > $toleranceSeconds) return false;
    $signedPayload = $timestamp . '.' . $payload;
    $expected = hash_hmac('sha256', $signedPayload, $secret);
    foreach ($parts['v1'] as $sig) {
        if (hash_equals($expected, $sig)) return true;
    }
    return false;
}

if (!verify_stripe_signature($payload, $sigHeader, STRIPE_WEBHOOK_SECRET)) {
    error_log('stripe_webhook: firma invalida');
    http_response_code(400);
    echo json_encode(['error' => 'invalid_signature']);
    exit;
}

$event = json_decode($payload, true);
if (!is_array($event) || empty($event['id']) || empty($event['type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'malformed_event']);
    exit;
}

// Coherencia entorno: rechazar eventos de TEST en PROD y viceversa
$eventLivemode = !empty($event['livemode']);
$envLivemode = defined('IS_PRODUCTION') && IS_PRODUCTION && defined('STRIPE_SECRET_KEY')
    && str_starts_with(STRIPE_SECRET_KEY, 'sk_live_');
if ($eventLivemode !== $envLivemode) {
    error_log('stripe_webhook_environment_mismatch: event_live=' . ($eventLivemode ? '1' : '0') . ', env_live=' . ($envLivemode ? '1' : '0'));
    http_response_code(400);
    echo json_encode(['error' => 'environment_mismatch']);
    exit;
}

$eventId = $event['id'];
$eventType = $event['type'];

// Idempotencia
$inserted = false;
try {
    $stmt = $pdo->prepare("INSERT INTO webhook_events (source, event_id, event_type, payload_json, created_at) VALUES ('stripe', ?, ?, ?, NOW())");
    $stmt->execute([$eventId, $eventType, $payload]);
    $inserted = true;
} catch (\PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), '1062')) {
        echo json_encode(['received' => true, 'duplicate' => true]);
        exit;
    }
    error_log('stripe_webhook_insert: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'persist_error']);
    exit;
}

if ($eventType !== 'checkout.session.completed') {
    echo json_encode(['received' => true, 'ignored' => true]);
    exit;
}

$session = $event['data']['object'] ?? [];
$paymentStatus = $session['payment_status'] ?? '';
if ($paymentStatus !== 'paid') {
    echo json_encode(['received' => true, 'unpaid' => true]);
    exit;
}

$meta = $session['metadata'] ?? [];
$tipo = $meta['tipo'] ?? '';
$userId = isset($meta['user_id']) ? (int) $meta['user_id'] : 0;

if ($userId <= 0) {
    error_log('stripe_webhook: user_id ausente en metadata');
    http_response_code(400);
    echo json_encode(['error' => 'missing_user_id']);
    exit;
}

try {
    $uStmt = $pdo->prepare("SELECT id, nombre, apellidos, email, dni, telefono FROM usuarios WHERE id = ?");
    $uStmt->execute([$userId]);
    $user = $uStmt->fetch();
    if (!$user) {
        error_log('stripe_webhook: usuario no encontrado');
        http_response_code(400);
        echo json_encode(['error' => 'user_not_found']);
        exit;
    }
} catch (\PDOException $e) {
    error_log('stripe_webhook_user: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'user_lookup_failed']);
    exit;
}

date_default_timezone_set('Europe/Madrid');

if ($tipo === 'reserva') {
    $pista = $meta['pista'] ?? '';
    $fecha = $meta['fecha'] ?? '';
    $hora = $meta['hora'] ?? '';
    $horaInicio = $meta['hora_inicio'] ?? '';
    $horaParts = explode(' - ', $hora);
    $horaFin = $horaParts[1] ?? '';
    $precio = (float) ($session['amount_total'] ?? 0) / 100;

    if ($pista === '' || $fecha === '' || $horaInicio === '') {
        http_response_code(400);
        echo json_encode(['error' => 'metadata_incompleta']);
        exit;
    }

    try {
        $check = $pdo->prepare("SELECT id, codigo_acceso FROM reservas WHERE pista = ? AND fecha = ? AND hora_inicio = ? AND estado != 'cancelada' LIMIT 1");
        $check->execute([$pista, $fecha, $horaInicio]);
        $existing = $check->fetch();
        if ($existing) {
            audit_log($pdo, 'stripe_webhook_reserva_duplicate', 'reserva', $existing['id'], ['session_id' => $session['id'] ?? null]);
            echo json_encode(['received' => true, 'duplicate_reserva' => true]);
            exit;
        }

        $codigo = 'TRC-' . strtoupper(bin2hex(random_bytes(3)));
        $insert = $pdo->prepare(
            "INSERT INTO reservas (pista, fecha, hora_inicio, hora_fin, estado, usuario_nombre, usuario_email, dni, telefono, precio_total, codigo_acceso, usuario_id, fecha_registro)
             VALUES (?, ?, ?, ?, 'reservada', ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $insert->execute([
            $pista, $fecha, $horaInicio, $horaFin,
            $user['nombre'], $user['email'], $user['dni'], $user['telefono'],
            $precio, $codigo, $userId,
        ]);
        $reservaId = $pdo->lastInsertId();

        audit_log($pdo, 'stripe_webhook_reserva_create', 'reserva', $reservaId, ['session_id' => $session['id'] ?? null, 'precio' => $precio]);

        $nH = esc_html($user['nombre']);
        $pH = esc_html($pista);
        $fH = esc_html($fecha);
        $hH = esc_html($hora);
        $cH = esc_html($codigo);
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
            <p style='font-size:0.75em; color:#888;'>Sus datos son tratados por el Ayuntamiento de Tarancon. Puede ejercer sus derechos en <strong>dpd@tarancon.es</strong>.</p>
        </body></html>";
        send_email($user['email'], $user['nombre'], 'Confirmacion de Reserva - Ayuntamiento de Tarancon', $emailHtml);

        $pdo->prepare("UPDATE webhook_events SET processed_at = NOW() WHERE source = 'stripe' AND event_id = ?")->execute([$eventId]);

        echo json_encode(['received' => true, 'reserva_id' => $reservaId, 'codigo' => $codigo]);
        exit;
    } catch (\PDOException $e) {
        error_log('stripe_webhook_reserva: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'reserva_create_failed']);
        exit;
    }
}

if ($tipo === 'bono') {
    $tipoBono = $meta['tipo_bono'] ?? '';
    if ($tipoBono === '') {
        http_response_code(400);
        echo json_encode(['error' => 'metadata_incompleta']);
        exit;
    }
    try {
        $check = $pdo->prepare("SELECT id FROM bonos WHERE stripe_session_id = ?");
        $check->execute([$session['id'] ?? '']);
        if ($check->fetch()) {
            echo json_encode(['received' => true, 'duplicate_bono' => true]);
            exit;
        }

        $cfg = $pdo->prepare("SELECT * FROM bonos_config WHERE tipo = ?");
        $cfg->execute([$tipoBono]);
        $pack = $cfg->fetch();
        if (!$pack) {
            http_response_code(400);
            echo json_encode(['error' => 'pack_no_encontrado']);
            exit;
        }
        $precioPagado = (float) ($session['amount_total'] ?? 0) / 100;
        $fechaCaducidad = date('Y-m-d', strtotime("+{$pack['caducidad_meses']} months"));

        $insert = $pdo->prepare(
            "INSERT INTO bonos (usuario_id, tipo, nombre, usos_totales, usos_restantes, precio_pagado, estado, stripe_session_id, fecha_caducidad)
             VALUES (?, ?, ?, ?, ?, ?, 'activo', ?, ?)"
        );
        $insert->execute([$userId, $pack['tipo'], $pack['nombre'], $pack['usos'], $pack['usos'], $precioPagado, $session['id'] ?? null, $fechaCaducidad]);
        $bonoId = $pdo->lastInsertId();
        audit_log($pdo, 'stripe_webhook_bono_create', 'bono', $bonoId, ['tipo' => $pack['tipo']]);

        $nH = esc_html($user['nombre']);
        $packH = esc_html($pack['nombre']);
        $usosH = (int) $pack['usos'];
        $precioH = number_format($precioPagado, 2, ',', '.');
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
        send_email($user['email'], $user['nombre'], 'Bono Activado - Ayuntamiento de Tarancon', $emailHtml);

        $pdo->prepare("UPDATE webhook_events SET processed_at = NOW() WHERE source = 'stripe' AND event_id = ?")->execute([$eventId]);
        echo json_encode(['received' => true, 'bono_id' => $bonoId]);
        exit;
    } catch (\PDOException $e) {
        error_log('stripe_webhook_bono: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'bono_create_failed']);
        exit;
    }
}

echo json_encode(['received' => true, 'unknown_tipo' => $tipo]);
