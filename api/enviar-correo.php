<?php
// api/enviar-correo.php — Crea reserva + envía email de confirmación.
// Sólo se usa en el flujo SIMULADO (sin Stripe LIVE).
// En producción la creación de reserva ocurre en pago-exitoso.php tras verificar pago.

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
    SecurityHandler::jsonError(405, 'Método no permitido.');
}

SecurityHandler::requireCSRF($_POST);

// En PRODUCCIÓN este endpoint NUNCA debe usarse (crearía reservas sin pago).
// El flujo canónico es Stripe Checkout + webhook. Esta ruta se conserva sólo
// para desarrollo local sin Stripe configurado.
if (defined('IS_PRODUCTION') && IS_PRODUCTION) {
    SecurityHandler::jsonError(503, 'El servicio de reservas directas no está disponible. Use el flujo de pago.');
}

// Datos identificativos del usuario: SIEMPRE de sesión (no del POST) para evitar spoof
$nombre = trim($_SESSION['user_nombre'] ?? 'Ciudadano/a');
$apellidos = trim($_SESSION['user_apellidos'] ?? '');
$to = trim($_SESSION['user_email'] ?? '');
$dni = trim($_SESSION['user_dni'] ?? '');
$telefono = trim($_SESSION['user_telefono'] ?? '');

$pista = trim($_POST['pista'] ?? '');
$fecha = trim($_POST['fecha'] ?? '');
$hora_string = trim($_POST['hora'] ?? '');

if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    SecurityHandler::jsonError(400, 'Email de la cuenta no válido.');
}
if ($pista === '' || $fecha === '' || $hora_string === '') {
    SecurityHandler::jsonError(400, 'Faltan datos de la reserva.');
}

$partes = explode(' - ', $hora_string);
if (count($partes) !== 2) {
    SecurityHandler::jsonError(400, 'Formato de hora inválido.');
}
$hora_inicio = $partes[0];
$hora_fin = $partes[1];

// Precio: SIEMPRE recalculado en servidor desde precios_personalizados o tarifa base 4€.
try {
    $stmtPrecio = $pdo->prepare("SELECT precio_base, precio_luz FROM precios_personalizados WHERE pista = ? AND fecha = ? AND hora_inicio = ? LIMIT 1");
    $stmtPrecio->execute([$pista, $fecha, $hora_inicio]);
    $row = $stmtPrecio->fetch();
    $precio_total = $row
        ? (((float) ($row['precio_base'] ?? 4.00)) + ((float) ($row['precio_luz'] ?? 0)))
        : 4.00;
} catch (\PDOException $e) {
    $precio_total = 4.00;
}

try {
    $checkStmt = $pdo->prepare("SELECT id FROM reservas WHERE pista = ? AND fecha = ? AND hora_inicio = ? AND estado != 'cancelada'");
    $checkStmt->execute([$pista, $fecha, $hora_inicio]);
    if ($checkStmt->fetch()) {
        SecurityHandler::jsonError(409, 'Esta pista ya fue reservada o bloqueada instantes antes por otro usuario.');
    }

    $codigo_acceso = 'TRC-' . strtoupper(bin2hex(random_bytes(3)));

    $insertStmt = $pdo->prepare(
        "INSERT INTO reservas (pista, fecha, hora_inicio, hora_fin, estado, usuario_nombre, usuario_email, dni, telefono, precio_total, codigo_acceso, usuario_id, fecha_registro)
         VALUES (?, ?, ?, ?, 'reservada', ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $insertStmt->execute([
        $pista, $fecha, $hora_inicio, $hora_fin,
        $nombre, $to, $dni ?: null, $telefono ?: null, $precio_total, $codigo_acceso, $userId,
    ]);

    $reservaId = $pdo->lastInsertId();
    audit_log($pdo, 'reserva_create_simulated', 'reserva', $reservaId, ['pista' => $pista, 'fecha' => $fecha, 'hora' => $hora_inicio]);

    // Email — sanitizar TODAS las interpolaciones
    $nombreH = esc_html($nombre);
    $pistaH = esc_html($pista);
    $fechaH = esc_html($fecha);
    $horaH = esc_html($hora_string);
    $dniH = esc_html($dni);
    $codigoH = esc_html($codigo_acceso);

    $subject = 'Confirmación de Reserva - Ayuntamiento de Tarancón';
    $message = "<html><body style='font-family: Arial, sans-serif; color: #333; line-height: 1.6;'>
        <h2 style='color: #914f26;'>¡Hola $nombreH! Tu reserva está confirmada.</h2>
        <p>Gracias por alquilar las instalaciones deportivas del Ayuntamiento de Tarancón.</p>
        <table width='100%' cellpadding='10' cellspacing='0' style='background-color: #f4f6f8; border-radius: 8px; margin: 20px 0; border: 1px solid #ddd;'>
            <tr><td><strong>Instalación:</strong></td><td>$pistaH</td></tr>
            <tr><td><strong>Fecha:</strong></td><td>$fechaH</td></tr>
            <tr><td><strong>Hora:</strong></td><td>$horaH</td></tr>
            <tr><td><strong>Titular:</strong></td><td>$nombreH</td></tr>" .
            ($dni !== '' ? "<tr><td><strong>DNI/NIE:</strong></td><td>$dniH</td></tr>" : "") . "
        </table>
        <div style='background-color: #fff4e5; border: 2px dashed #c36a33; padding: 20px; text-align: center; border-radius: 8px;'>
            <p style='margin: 0; color: #914f26; font-size: 14px; text-transform: uppercase;'><strong>TU CÓDIGO DE ACCESO</strong></p>
            <h1 style='margin: 10px 0; font-size: 42px; letter-spacing: 5px; color: #522d16;'>$codigoH</h1>
        </div>
        <p style='color: #d32f2f; font-size: 0.9em;'><em>Regla de Cancelación: hasta 30 minutos antes de la reserva. Pasado ese plazo, el importe no se devuelve.</em></p>
        <p style='font-size: 0.75em; color: #888;'>Los datos personales facilitados son tratados por el Ayuntamiento de Tarancón. Más información en la <a href='https://tarancon.es'>Política de Privacidad</a>. Puede ejercer sus derechos escribiendo a <strong>dpd@tarancon.es</strong>.</p>
    </body></html>";

    require_once __DIR__ . '/mailer.php';
    $emailResult = send_email($to, $nombre, $subject, $message);

    SecurityHandler::jsonOk([
        'message' => 'Reserva creada' . ($emailResult['sent'] ? ' y correo enviado.' : ', aunque el correo no se pudo enviar.'),
        'codigo' => $codigo_acceso,
    ]);
} catch (\PDOException $e) {
    error_log('reserva_create_simulated: ' . $e->getMessage());
    SecurityHandler::jsonError(500, 'Error al procesar la reserva. Inténtelo de nuevo.');
}
