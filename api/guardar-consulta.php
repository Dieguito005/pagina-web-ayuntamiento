<?php
// api/guardar-consulta.php
session_start();
header('Content-Type: application/json');
require_once 'conexion.php';

// Verificación anti-spam básica
if (!empty($_POST['honeypot'])) {
    // Si el honeypot está lleno, es un bot. Responder con éxito para no dar pistas.
    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $dni = trim($_POST['dni'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $tipo = trim($_POST['tipo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $usuario_id = $_SESSION['user_id'] ?? null;

    if (empty($nombre) || empty($apellidos) || empty($dni) || empty($email) || empty($telefono) || empty($tipo) || empty($descripcion)) {
        echo json_encode(['success' => false, 'message' => 'Por favor, rellene todos los campos obligatorios.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
         echo json_encode(['success' => false, 'message' => 'El correo electrónico no es válido.']);
         exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO consultas (usuario_id, nombre, apellidos, dni, email, telefono, tipo_consulta, descripcion, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')");
        $stmt->execute([$usuario_id, $nombre, $apellidos, $dni, $email, $telefono, $tipo, $descripcion]);

        echo json_encode(['success' => true]);
    } catch (\PDOException $e) {
        error_log("Error guardando consulta: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error de base de datos. Por favor, inténtelo de nuevo más tarde.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
}
?>
