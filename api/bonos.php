<?php
// api/bonos.php — API REST para gestión de bonos de usuario
// Acciones: get_config, get_user_bonos, use_bono

ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'conexion.php';
header("Content-Type: application/json; charset=UTF-8");

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ════════════════════════════════════════════════
// GET CONFIG — Packs disponibles (público)
// ════════════════════════════════════════════════
if ($action === 'get_config') {
    try {
        $stmt = $pdo->query("SELECT tipo, nombre, usos, precio, caducidad_meses FROM bonos_config WHERE activo = 1 ORDER BY usos ASC");
        $packs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["status" => "success", "data" => $packs]);
    } catch (\PDOException $e) {
        error_log("bonos.php get_config error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error al obtener la configuración de bonos."]);
    }
    exit;
}

// ════════════════════════════════════════════════
// GET USER BONOS — Bonos activos del usuario logueado
// ════════════════════════════════════════════════
if ($action === 'get_user_bonos') {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Debes iniciar sesión."]);
        exit;
    }

    try {
        $userId = $_SESSION['user_id'];

        // Primero: marcar como caducados los bonos cuya fecha de caducidad haya pasado
        $pdo->prepare("UPDATE bonos SET estado = 'caducado' WHERE usuario_id = ? AND estado = 'activo' AND fecha_caducidad IS NOT NULL AND fecha_caducidad < CURDATE()")
            ->execute([$userId]);

        // Obtener bonos activos
        $stmt = $pdo->prepare("
            SELECT id, tipo, nombre, usos_totales, usos_restantes, precio_pagado, estado, fecha_compra, fecha_caducidad
            FROM bonos
            WHERE usuario_id = ? AND estado = 'activo'
            ORDER BY fecha_compra ASC
        ");
        $stmt->execute([$userId]);
        $bonos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Total de usos disponibles
        $totalUsos = array_sum(array_column($bonos, 'usos_restantes'));

        echo json_encode([
            "status" => "success",
            "bonos" => $bonos,
            "total_usos_disponibles" => $totalUsos
        ]);
    } catch (\PDOException $e) {
        error_log("bonos.php get_user_bonos error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error al obtener tus bonos."]);
    }
    exit;
}

// ════════════════════════════════════════════════
// USE BONO — Canjear un uso de bono para una reserva
// ════════════════════════════════════════════════
if ($action === 'use_bono') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Método no permitido."]);
        exit;
    }

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Debes iniciar sesión."]);
        exit;
    }

    $userId = $_SESSION['user_id'];
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) $data = $_POST;

    $pista = $data['pista'] ?? '';
    $fecha = $data['fecha'] ?? '';
    $hora  = $data['hora'] ?? '';
    $nombre = $data['nombre'] ?? '';
    $email = $data['email'] ?? '';
    $dni = $data['dni'] ?? '';
    $telefono = $data['telefono'] ?? '';

    if (empty($pista) || empty($fecha) || empty($hora)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Datos de reserva incompletos."]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Caducar bonos expirados
        $pdo->prepare("UPDATE bonos SET estado = 'caducado' WHERE usuario_id = ? AND estado = 'activo' AND fecha_caducidad IS NOT NULL AND fecha_caducidad < CURDATE()")
            ->execute([$userId]);

        // Obtener el bono activo más antiguo con usos restantes (FIFO)
        $bonoStmt = $pdo->prepare("
            SELECT id, usos_restantes
            FROM bonos
            WHERE usuario_id = ? AND estado = 'activo' AND usos_restantes > 0
            ORDER BY fecha_compra ASC
            LIMIT 1
            FOR UPDATE
        ");
        $bonoStmt->execute([$userId]);
        $bono = $bonoStmt->fetch();

        if (!$bono) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "No tienes bonos disponibles."]);
            exit;
        }

        // Verificar disponibilidad de la pista
        $partes = explode(' - ', $hora);
        if (count($partes) !== 2) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Formato de hora inválido."]);
            exit;
        }
        $hora_inicio = $partes[0];
        $hora_fin = $partes[1];

        $checkStmt = $pdo->prepare("SELECT id FROM reservas WHERE pista = ? AND fecha = ? AND hora_inicio = ? AND estado != 'cancelada'");
        $checkStmt->execute([$pista, $fecha, $hora_inicio]);
        if ($checkStmt->fetch()) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(["status" => "error", "message" => "Esta pista ya fue reservada por otro usuario."]);
            exit;
        }

        // Decrementar bono
        $newUsos = $bono['usos_restantes'] - 1;
        $updateBono = $pdo->prepare("UPDATE bonos SET usos_restantes = ?, estado = IF(? = 0, 'agotado', 'activo'), fecha_agotado = IF(? = 0, NOW(), NULL) WHERE id = ?");
        $updateBono->execute([$newUsos, $newUsos, $newUsos, $bono['id']]);

        // Crear la reserva
        date_default_timezone_set('Europe/Madrid');
        $codigo_acceso = 'TRC-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

        // Detectar columnas existentes
        $columnsResult = $pdo->query("SHOW COLUMNS FROM reservas");
        $existingColumns = [];
        while ($col = $columnsResult->fetch()) {
            $existingColumns[] = $col['Field'];
        }

        $insertCols = ['pista', 'fecha', 'hora_inicio', 'hora_fin', 'estado', 'usuario_nombre', 'usuario_email', 'codigo_acceso'];
        $insertVals = [$pista, $fecha, $hora_inicio, $hora_fin, 'reservada', $nombre, $email, $codigo_acceso];

        if (in_array('dni', $existingColumns)) { $insertCols[] = 'dni'; $insertVals[] = $dni; }
        if (in_array('telefono', $existingColumns)) { $insertCols[] = 'telefono'; $insertVals[] = $telefono; }
        if (in_array('precio_total', $existingColumns)) { $insertCols[] = 'precio_total'; $insertVals[] = 0; }
        if (in_array('usuario_id', $existingColumns)) { $insertCols[] = 'usuario_id'; $insertVals[] = $userId; }
        if (in_array('fecha_registro', $existingColumns)) { $insertCols[] = 'fecha_registro'; $insertVals[] = date('Y-m-d H:i:s'); }
        if (in_array('bono_id', $existingColumns)) { $insertCols[] = 'bono_id'; $insertVals[] = $bono['id']; }

        $placeholders = implode(', ', array_fill(0, count($insertCols), '?'));
        $colsStr = implode(', ', $insertCols);
        $insertStmt = $pdo->prepare("INSERT INTO reservas ($colsStr) VALUES ($placeholders)");
        $insertStmt->execute($insertVals);

        $pdo->commit();

        // Enviar email de confirmación
        require_once __DIR__ . '/mailer.php';
        $emailHtml = "
        <html><head><title>Confirmación de Reserva (Bono)</title></head>
        <body style='font-family: Arial, sans-serif; color: #333; line-height: 1.6;'>
            <h2 style='color: #914f26;'>¡Hola {$nombre}! Tu reserva con bono está confirmada.</h2>
            <p>Has canjeado <strong>1 uso de tu bono</strong>. No se ha realizado ningún cargo adicional.</p>
            <table width='100%' cellpadding='10' style='background-color: #f4f6f8; border-radius: 8px; margin: 20px 0; border: 1px solid #ddd;'>
                <tr><td><strong>Instalación:</strong></td><td>{$pista}</td></tr>
                <tr><td><strong>Fecha:</strong></td><td>{$fecha}</td></tr>
                <tr><td><strong>Hora:</strong></td><td>{$hora}</td></tr>
                <tr><td><strong>Titular:</strong></td><td>{$nombre}</td></tr>
                <tr><td><strong>Método:</strong></td><td>🎟️ Bono (usos restantes: {$newUsos})</td></tr>
            </table>
            <div style='background-color: #fff4e5; border: 2px dashed #c36a33; padding: 20px; text-align: center; border-radius: 8px;'>
                <p style='margin: 0; color: #914f26; font-size: 14px;'><strong>TU CÓDIGO DE ACCESO (MUESTRA ESTO AL CONSERJE)</strong></p>
                <h1 style='margin: 10px 0; font-size: 42px; letter-spacing: 5px; color: #522d16;'>{$codigo_acceso}</h1>
            </div>
            <p style='color: #d32f2f; font-size: 0.9em; margin-top:20px;'><em>⚠️ Cancelación hasta 30 minutos antes de la reserva. Después no se devuelve el uso del bono.</em></p>
        </body></html>";

        send_email($email, $nombre, 'Confirmación de Reserva (Bono) - Ayuntamiento de Tarancón', $emailHtml);

        echo json_encode([
            "status" => "success",
            "message" => "Reserva creada con bono. Te queda(n) {$newUsos} uso(s) restante(s).",
            "codigo" => $codigo_acceso,
            "usos_restantes" => $newUsos,
            "bono_id" => $bono['id']
        ]);

    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("bonos.php use_bono error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error al procesar la reserva con bono."]);
    }
    exit;
}

// Acción no reconocida
http_response_code(400);
echo json_encode(["status" => "error", "message" => "Acción no válida."]);
