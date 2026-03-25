<?php
// enviar-correo.php

// Asegurar que las peticiones se procesen correctamente
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $to = $_POST['email'] ?? '';
    $nombre = $_POST['nombre'] ?? 'Ciudadano/a';
    $codigo = $_POST['codigo'] ?? '';
    $pista = $_POST['pista'] ?? '';
    $fecha = $_POST['fecha'] ?? '';
    $hora = $_POST['hora'] ?? '';

    // Si el email es válido
    if (!empty($to) && filter_var($to, FILTER_VALIDATE_EMAIL)) {
        
        $subject = "Confirmación de Reserva y Código de Acceso - Ayuntamiento de Tarancón";
        
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
                <tr><td><strong>Hora:</strong></td><td>$hora</td></tr>
            </table>
            
            <div style='background-color: #fff4e5; border: 2px dashed #c36a33; padding: 20px; text-align: center; border-radius: 8px;'>
                <p style='margin: 0; color: #914f26; font-size: 14px; text-transform: uppercase;'><strong>TU CÓDIGO DE ACCESO AUTOMÁTICO</strong></p>
                <h1 style='margin: 10px 0 0; font-size: 36px; letter-spacing: 5px; color: #522d16;'>$codigo</h1>
            </div>
            
            <h3 style='color: #333;'>¿Qué debo hacer ahora?</h3>
            <ul style='color: #555;'>
                <li>Guarda este código alfanumérico.</li>
                <li>Acude a las instalaciones elegidas unos minutos antes de la hora acordada.</li>
                <li>Enséñale o díctale este código al conserje de la instalación para que te permita la entrada a la pista.</li>
            </ul>

            <p style='color: #d32f2f; font-size: 0.9em;'><em>⚠️ Regla de Cancelación: El límite máximo de cancelación es hasta 30 minutos antes de la hora de la reserva. En caso de sobrepasar este límite, el importe no será devuelto.</em></p>
            <br>
            <p style='font-size: 0.8em; color: #aaa;'>Este correo ha sido generado automáticamente. Por favor, no responda a este mensaje.</p>
        </body>
        </html>
        ";

        // Cabeceras para enviar correo en formato HTML
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: reservas@tarancon.es" . "\r\n"; // <- Ajustar por el correo corporativo del ayuntamiento real

        // Usar la función mail de PHP (Requiere que el servidor tenga configurado SMTP)
        $sent = mail($to, $subject, $message, $headers);
        
        if ($sent) {
            http_response_code(200);
            echo json_encode(["status" => "success", "message" => "Correo electrónico enviado correctamente."]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "El servidor falló al enviar el correo. Revise la confguración SMTP de PHP."]);
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
