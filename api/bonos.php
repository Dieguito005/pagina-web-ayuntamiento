<?php
// api/bonos.php — API REST de bonos (público + usuario logueado)
// Acciones: get_config (público), get_user_bonos (auth), use_bono (auth+CSRF)

require_once __DIR__ . '/../includes/SecurityHandler.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'conexion.php';
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'get_config') {
    try {
        $stmt = $pdo->query("SELECT tipo, nombre, usos, precio, caducidad_meses FROM bonos_config WHERE activo = 1 ORDER BY usos ASC");
        SecurityHandler::jsonOk(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (\PDOException $e) {
        error_log('bonos_get_config: ' . $e->getMessage());
        SecurityHandler::jsonError(500, 'Error al obtener la configuración de bonos.');
    }
}

if ($action === 'get_user_bonos') {
    $userId = SecurityHandler::requireUser();
    try {
        $pdo->prepare("UPDATE bonos SET estado = 'caducado' WHERE usuario_id = ? AND estado = 'activo' AND fecha_caducidad IS NOT NULL AND fecha_caducidad < CURDATE()")
            ->execute([$userId]);
        $stmt = $pdo->prepare(
            "SELECT id, tipo, nombre, usos_totales, usos_restantes, precio_pagado, estado, fecha_compra, fecha_caducidad
             FROM bonos WHERE usuario_id = ? AND estado = 'activo' ORDER BY fecha_compra ASC"
        );
        $stmt->execute([$userId]);
        $bonos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totalUsos = array_sum(array_column($bonos, 'usos_restantes'));
        SecurityHandler::jsonOk(['bonos' => $bonos, 'total_usos_disponibles' => $totalUsos]);
    } catch (\PDOException $e) {
        error_log('bonos_get_user_bonos: ' . $e->getMessage());
        SecurityHandler::jsonError(500, 'Error al obtener tus bonos.');
    }
}

if ($action === 'use_bono') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        SecurityHandler::jsonError(405, 'Método no permitido.');
    }
    $userId = SecurityHandler::requireUser();
    $data = read_json_body();
    if (empty($data)) $data = $_POST;
    SecurityHandler::requireCSRF($data);

    // Datos de la pista vienen del cliente, pero los identificativos del usuario salen de sesión
    $pista = trim($data['pista'] ?? '');
    $fecha = trim($data['fecha'] ?? '');
    $hora = trim($data['hora'] ?? '');
    $nombre = trim($_SESSION['user_nombre'] ?? '');
    $email = trim($_SESSION['user_email'] ?? '');
    $dni = trim($_SESSION['user_dni'] ?? '');
    $telefono = trim($_SESSION['user_telefono'] ?? '');

    if ($pista === '' || $fecha === '' || $hora === '') {
        SecurityHandler::jsonError(400, 'Datos de reserva incompletos.');
    }
    $partes = explode(' - ', $hora);
    if (count($partes) !== 2) {
        SecurityHandler::jsonError(400, 'Formato de hora inválido.');
    }
    $hora_inicio = $partes[0];
    $hora_fin = $partes[1];

    try {
        $pdo->beginTransaction();

        $pdo->prepare("UPDATE bonos SET estado = 'caducado' WHERE usuario_id = ? AND estado = 'activo' AND fecha_caducidad IS NOT NULL AND fecha_caducidad < CURDATE()")
            ->execute([$userId]);

        $bonoStmt = $pdo->prepare(
            "SELECT id, usos_restantes FROM bonos
             WHERE usuario_id = ? AND estado = 'activo' AND usos_restantes > 0
             ORDER BY fecha_compra ASC LIMIT 1 FOR UPDATE"
        );
        $bonoStmt->execute([$userId]);
        $bono = $bonoStmt->fetch();
        if (!$bono) {
            $pdo->rollBack();
            SecurityHandler::jsonError(400, 'No tienes bonos disponibles.');
        }

        $checkStmt = $pdo->prepare("SELECT id FROM reservas WHERE pista = ? AND fecha = ? AND hora_inicio = ? AND estado != 'cancelada'");
        $checkStmt->execute([$pista, $fecha, $hora_inicio]);
        if ($checkStmt->fetch()) {
            $pdo->rollBack();
            SecurityHandler::jsonError(409, 'Esta pista ya fue reservada por otro usuario.');
        }

        $newUsos = (int) $bono['usos_restantes'] - 1;
        $updateBono = $pdo->prepare("UPDATE bonos SET usos_restantes = ?, estado = IF(? = 0, 'agotado', 'activo'), fecha_agotado = IF(? = 0, NOW(), NULL) WHERE id = ?");
        $updateBono->execute([$newUsos, $newUsos, $newUsos, $bono['id']]);

        date_default_timezone_set('Europe/Madrid');
        $codigo_acceso = 'TRC-' . strtoupper(bin2hex(random_bytes(3)));

        $insertStmt = $pdo->prepare(
            "INSERT INTO reservas (pista, fecha, hora_inicio, hora_fin, estado, usuario_nombre, usuario_email, dni, telefono, precio_total, codigo_acceso, usuario_id, bono_id, fecha_registro)
             VALUES (?, ?, ?, ?, 'reservada', ?, ?, ?, ?, 0, ?, ?, ?, NOW())"
        );
        $insertStmt->execute([
            $pista, $fecha, $hora_inicio, $hora_fin,
            $nombre, $email, $dni ?: null, $telefono ?: null,
            $codigo_acceso, $userId, $bono['id'],
        ]);
        $reservaId = $pdo->lastInsertId();

        $pdo->commit();

        audit_log($pdo, 'reserva_bono_use', 'reserva', $reservaId, ['bono_id' => $bono['id'], 'usos_restantes' => $newUsos]);

        // Email
        require_once __DIR__ . '/mailer.php';
        $nH = esc_html($nombre);
        $pH = esc_html($pista);
        $fH = esc_html($fecha);
        $hH = esc_html($hora);
        $cH = esc_html($codigo_acceso);
        $emailHtml = "<html><body style='font-family: Arial, sans-serif; color: #333; line-height: 1.6;'>
            <h2 style='color: #914f26;'>¡Hola $nH! Tu reserva con bono está confirmada.</h2>
            <p>Has canjeado <strong>1 uso de tu bono</strong>. No se ha realizado ningún cargo adicional.</p>
            <table width='100%' cellpadding='10' style='background-color: #f4f6f8; border-radius: 8px; margin: 20px 0; border: 1px solid #ddd;'>
                <tr><td><strong>Instalación:</strong></td><td>$pH</td></tr>
                <tr><td><strong>Fecha:</strong></td><td>$fH</td></tr>
                <tr><td><strong>Hora:</strong></td><td>$hH</td></tr>
                <tr><td><strong>Titular:</strong></td><td>$nH</td></tr>
                <tr><td><strong>Método:</strong></td><td>Bono (usos restantes: $newUsos)</td></tr>
            </table>
            <div style='background-color: #fff4e5; border: 2px dashed #c36a33; padding: 20px; text-align: center; border-radius: 8px;'>
                <p style='margin: 0; color: #914f26; font-size: 14px;'><strong>TU CÓDIGO DE ACCESO</strong></p>
                <h1 style='margin: 10px 0; font-size: 42px; letter-spacing: 5px; color: #522d16;'>$cH</h1>
            </div>
            <p style='color: #d32f2f; font-size: 0.9em; margin-top:20px;'><em>Cancelación hasta 30 minutos antes de la reserva. Después no se devuelve el uso del bono.</em></p>
        </body></html>";
        send_email($email, $nombre, 'Confirmación de Reserva (Bono) - Ayuntamiento de Tarancón', $emailHtml);

        SecurityHandler::jsonOk([
            'message' => "Reserva creada con bono. Te queda(n) $newUsos uso(s) restante(s).",
            'codigo' => $codigo_acceso,
            'usos_restantes' => $newUsos,
            'bono_id' => $bono['id'],
        ]);
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('bonos_use_bono: ' . $e->getMessage());
        SecurityHandler::jsonError(500, 'Error al procesar la reserva con bono.');
    }
}

SecurityHandler::jsonError(400, 'Acción no válida.');
