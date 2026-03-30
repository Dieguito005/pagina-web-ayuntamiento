<?php
// api/enviar-correo.php
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

            // Insertar en la base de datos la reserva real
            $insertStmt = $pdo->prepare("INSERT INTO reservas (pista, fecha, hora_inicio, hora_fin, estado, usuario_nombre, usuario_email, dni, telefono, precio_total, codigo_acceso, fecha_registro) VALUES (?, ?, ?, ?, 'reservada', ?, ?, ?, ?, ?, ?, NOW())");
            $insertStmt->execute([$pista, $fecha, $hora_inicio, $hora_fin, $nombre, $to, $dni, $telefono, $precio_total, $codigo_acceso]);

            // Generar URL del QR dinámico
            $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($codigo_acceso);

            $subject = "Confirmación de Reserva y Código QR - Ayuntamiento de Tarancón";
            
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
                </table>
                
                <div style='background-color: #fff4e5; border: 2px dashed #c36a33; padding: 20px; text-align: center; border-radius: 8px;'>
                    <p style='margin: 0; color: #914f26; font-size: 14px; text-transform: uppercase;'><strong>TU CÓDIGO DE ACCESO AUTOMÁTICO</strong></p>
                    <h1 style='margin: 10px 0; font-size: 42px; letter-spacing: 5px; color: #522d16;'>$codigo_acceso</h1>
                    <img src='$qr_url' alt='Código QR de Acceso' style='margin-top: 15px; border-radius: 8px; border: 4px solid white; box-shadow: 0 4px 10px rgba(0,0,0,0.1); width: 200px; height: 200px;'/>
                </div>
                
                <h3 style='color: #333;'>¿Qué debo hacer ahora?</h3>
                <ul style='color: #555;'>
                    <li>Guarda este código alfanumérico o el Código QR.</li>
                    <li>Acude a las instalaciones elegidas unos minutos antes de la hora acordada.</li>
                    <li>Enséñale o escanea este código al conserje de la instalación para que te permita la entrada a la pista.</li>
                </ul>

                <p style='color: #d32f2f; font-size: 0.9em;'><em>⚠️ Regla de Cancelación: El límite máximo de cancelación es hasta 30 minutos antes de la hora de la reserva. En caso de sobrepasar este límite, el importe no será devuelto.</em></p>
                <br>
                <p style='font-size: 0.8em; color: #aaa;'>Este correo ha sido generado automáticamente. Por favor, no responda a este mensaje.</p>
            </body>
            </html>
            ";

            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: reservas@tarancon.es" . "\r\n"; 

            $sent = mail($to, $subject, $message, $headers);
            
            if ($sent) {
                http_response_code(200);
                echo json_encode(["status" => "success", "message" => "Reserva creada y correo enviado.", "codigo" => $codigo_acceso]);
            } else {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "Pista reservada en BBDD, pero tu entorno XAMPP no tiene configurado el servidor SMTP para enviar el correo real.", "codigo" => $codigo_acceso]);
            }
        } catch (\PDOException $e) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Error de BBDD al procesar la reserva. Inténtelo más tarde."]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "La dirección de correo electrónico ($to) no es válida."]);
    }
} else {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Método no permitido."]);
}
?>
