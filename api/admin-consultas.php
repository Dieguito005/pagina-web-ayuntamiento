<?php
// api/admin-consultas.php
session_start();
header('Content-Type: application/json');
require_once 'conexion.php';
require_once 'mailer.php'; // Cargamos el helper de correos centralizado

// Verificar si es administrador
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$action = $_GET['action'] ?? 'get';

if ($action === 'get') {
    // Listar consultas
    $busqueda = trim($_GET['search'] ?? '');
    $tipo = trim($_GET['tipo'] ?? '');
    $estado = trim($_GET['estado'] ?? '');
    $fechaInicio = trim($_GET['fecha_inicio'] ?? '');

    $query = "SELECT c.*, u.nombre as user_nombre, u.apellidos as user_apellidos FROM consultas c LEFT JOIN usuarios u ON c.usuario_id = u.id WHERE 1=1";
    $params = [];

    if ($busqueda !== '') {
        $query .= " AND (c.dni LIKE ? OR c.email LIKE ? OR c.nombre LIKE ? OR c.apellidos LIKE ?)";
        $likeB = "%$busqueda%";
        $params = array_merge($params, [$likeB, $likeB, $likeB, $likeB]);
    }
    if ($tipo !== '') {
        $query .= " AND c.tipo_consulta = ?";
        $params[] = $tipo;
    }
    if ($estado !== '') {
        $query .= " AND c.estado = ?";
        $params[] = $estado;
    }
    if ($fechaInicio !== '') {
        $query .= " AND DATE(c.fecha_creacion) >= ?";
        $params[] = $fechaInicio;
    }

    $query .= " ORDER BY c.fecha_creacion DESC LIMIT 50"; // Limit to 50 for safety

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $consultas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $consultas]);
    } catch (\PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error al obtener consultas: ' . $e->getMessage()]);
    }

} elseif ($action === 'resolve') {
    // Marcar como resuelta
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        exit;
    }

    $id = $_POST['id'] ?? null;
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
        exit;
    }

    try {
        // Obtener la consulta para enviar correo al email correcto
        $stmt_get = $pdo->prepare("SELECT email, nombre, tipo_consulta, descripcion FROM consultas WHERE id = ?");
        $stmt_get->execute([$id]);
        $consulta = $stmt_get->fetch(PDO::FETCH_ASSOC);

        if ($consulta) {
            $stmt = $pdo->prepare("UPDATE consultas SET estado = 'resuelta' WHERE id = ?");
            $stmt->execute([$id]);

            // Enviar correo (Opcional)
            $emailDestino = $consulta['email'];
            $nombreUsuario = $consulta['nombre'];
            
            // Requerimos la funcion de enviar correo si existe
            if (file_exists('enviar-correo-func.php')) {
                require_once 'enviar-correo-func.php';
                $asunto = "Su consulta sobre '{$consulta['tipo_consulta']}' ha sido resuelta";
                $mensajeHTML = "<h2>Hola $nombreUsuario,</h2>
                                <p>Le informamos que su consulta reciente ha sido marcada como <strong>resuelta</strong> por nuestro equipo.</p>
                                <p><i>Su mensaje original:</i><br> \"{$consulta['descripcion']}\"</p>
                                <p>Si tiene más dudas, no dude en contactarnos de nuevo.</p>
                                <p>Gracias,<br>El equipo del Ayuntamiento de Tarancón.</p>";
                // Ignoramos el error si falla en local y asumimos que se llamará a PHPMailer si el entorno lo permite
                // Usamos la función send_email definida en mailer.php (vía Brevo o mail)
                if (function_exists('send_email')) {
                    send_email($emailDestino, $nombreUsuario, $asunto, $mensajeHTML);
                } else {
                    // Fallback si por alguna razón no se cargó mailer.php
                    @mail($emailDestino, $asunto, $mensajeHTML, "From: no-reply@tarancon.es\r\nContent-Type: text/html; charset=UTF-8");
                }
            } else {
                // Alternativa simple con mail() fallback, aunque estemos en local
                $subject = "Su consulta ha sido resuelta - Ayuntamiento de Tarancón";
                $headers = "From: no-reply@tarancon.es\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                $message = "<h2>Hola $nombreUsuario,</h2><p>Le informamos que su consulta ha sido resuelta.</p>";
                @mail($emailDestino, $subject, $message, $headers);
            }

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Consulta no encontrada']);
        }
    } catch (\PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error de base de datos']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}
?>
