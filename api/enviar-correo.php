<?php
// api/enviar-correo.php
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'conexion.php';
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $to = $_POST['email'] ?? '';
    $nombre = $_POST['nombre'] ?? 'Ciudadano/a';
    $dni = $_POST['dni'] ?? null;
    $telefono = $_POST['telefono'] ?? null;
    $precio_total = $_POST['precio_total'] ?? null;
    $pista = $_POST['pista'] ?? '';
    $fecha = $_POST['fecha'] ?? '';
    $hora_string = $_POST['hora'] ?? '';

    if (!empty($to) && filter_var($to, FILTER_VALIDATE_EMAIL)) {
        
        // Dividir hora "09:00 - 11:00"
        $partes = explode(' - ', $hora_string);
        if (count($partes) !== 2) {
             http_response_code(400);
             echo json_encode(["status" => "error", "message" => "Formato de hora inválido."]);
             exit;
        }
        $hora_inicio = $partes[0];
        $hora_fin = $partes[1];

        try {
            // Verificar disponibilidad real (Evitar colisión o overbooking)
            $checkStmt = $pdo->prepare("SELECT id FROM reservas WHERE pista = ? AND fecha = ? AND hora_inicio = ? AND estado != 'cancelada'");
            $checkStmt->execute([$pista, $fecha, $hora_inicio]);
            if ($checkStmt->fetch()) {
                 http_response_code(409);
                 echo json_encode(["status" => "error", "message" => "Esta pista ya fue reservada o bloqueada instantes antes por otro usuario."]);
                 exit;
            }

            // Generar Código Alfanumérico
            $codigo_acceso = 'TRC-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

            // Detectar qué columnas existen en la tabla reservas para máxima compatibilidad
            $columnsResult = $pdo->query("SHOW COLUMNS FROM reservas");
            $existingColumns = [];
            while ($col = $columnsResult->fetch()) {
                $existingColumns[] = $col['Field'];
            }

            // Construir INSERT dinámico según las columnas que existan
            $insertCols = ['pista', 'fecha', 'hora_inicio', 'hora_fin', 'estado', 'usuario_nombre', 'usuario_email', 'codigo_acceso'];
            $insertVals = [$pista, $fecha, $hora_inicio, $hora_fin, 'reservada', $nombre, $to, $codigo_acceso];

            if (in_array('dni', $existingColumns)) {
                $insertCols[] = 'dni';
                $insertVals[] = $dni;
            }
            if (in_array('telefono', $existingColumns)) {
                $insertCols[] = 'telefono';
                $insertVals[] = $telefono;
            }
            if (in_array('precio_total', $existingColumns)) {
                $insertCols[] = 'precio_total';
                $insertVals[] = $precio_total;
            }
            if (in_array('usuario_id', $existingColumns)) {
                $insertCols[] = 'usuario_id';
                $insertVals[] = $_SESSION['user_id'] ?? null;
            }
            if (in_array('fecha_registro', $existingColumns)) {
                $insertCols[] = 'fecha_registro';
                $insertVals[] = date('Y-m-d H:i:s');
            }

            $placeholders = implode(', ', array_fill(0, count($insertCols), '?'));
            $colsStr = implode(', ', $insertCols);
            $insertStmt = $pdo->prepare("INSERT INTO reservas ($colsStr) VALUES ($placeholders)");
            $insertStmt->execute($insertVals);

            $subject = "Confirmación de Reserva y Código - Ayuntamiento de Tarancón";
            
            $message = "
            <html>
            <head>
                <title>Confirmación de Reserva</title>
            </head>
            <body style='font-family: Arial, sans-serif; color: #333; line-height: 1.6;'>
                <h2 style='color: #914f26;'>¡Hola $nombre! Tu reserva está confirmada.</h2>
                <p>Gracias por alquilar las instalaciones deportivas del Ayuntamiento de Tarancón. Aquí tienes los detalles físicos de tu reserva:</p>
                
                <table width='100%' cellpadding='10' cellspacing='0' style='background-color: #f4f6f8; border-radius: 8px; margin: 20px 0; border: 1px solid #ddd;'>
                    <tr><td><strong>Instalación:</strong></td><td>$pista</td></tr>
                    <tr><td><strong>Fecha:</strong></td><td>$fecha</td></tr>
                    <tr><td><strong>Hora:</strong></td><td>$hora_string</td></tr>
                    <tr><td><strong>Titular:</strong></td><td>$nombre</td></tr>
                    " . (!empty($dni) ? "<tr><td><strong>DNI/NIE:</strong></td><td>$dni</td></tr>" : "") . "
                </table>
                
                <div style='background-color: #fff4e5; border: 2px dashed #c36a33; padding: 20px; text-align: center; border-radius: 8px;'>
                    <p style='margin: 0; color: #914f26; font-size: 14px; text-transform: uppercase;'><strong>TU CÓDIGO DE ACCESO AUTOMÁTICO</strong></p>
                    <h1 style='margin: 10px 0; font-size: 42px; letter-spacing: 5px; color: #522d16;'>$codigo_acceso</h1>
                </div>
                
                <h3 style='color: #333;'>¿Qué debo hacer ahora?</h3>
                <ul style='color: #555;'>
                    <li>Guarda este código alfanumérico.</li>
                    <li>Acude a las instalaciones elegidas unos minutos antes de la hora acordada.</li>
                    <li>Enséñale/díctale este código al conserje de la instalación para que te permita la entrada a la pista.</li>
                </ul>

                <p style='color: #d32f2f; font-size: 0.9em;'><em>⚠️ Regla de Cancelación: El límite máximo de cancelación es hasta 30 minutos antes de la hora de la reserva. En caso de sobrepasar este límite, el importe no será devuelto.</em></p>
                <br>
                <p style='font-size: 0.8em; color: #aaa;'>Este correo ha sido generado automáticamente. Por favor, no responda a este mensaje.</p>
            </body>
            </html>
            ";

            // Enviar email via Brevo API o mail() fallback
            require_once __DIR__ . '/mailer.php';
            $emailResult = send_email($to, $nombre, $subject, $message);

            if ($emailResult['sent']) {
                http_response_code(200);
                echo json_encode(["status" => "success", "message" => "Reserva creada y correo enviado via " . $emailResult['method'] . ".", "codigo" => $codigo_acceso]);
            } else {
                // La reserva SÍ se guardó en BBDD, solo falla el envío de correo
                http_response_code(200);
                echo json_encode(["status" => "success", "message" => "Reserva creada correctamente. " . ($emailResult['error'] ?? 'El correo no se pudo enviar.'), "codigo" => $codigo_acceso]);
            }
        } catch (\PDOException $e) {
            error_log("Reserva DB error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Error al procesar la reserva. Inténtelo de nuevo."]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "La dirección de correo electrónico ($to) no es válida."]);
    }
} else {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Método no permitido."]);
}
