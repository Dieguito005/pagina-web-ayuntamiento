<?php
// api/guardar-consulta.php — Recibe el formulario de contacto.
// - CSRF obligatorio
// - DNI opcional (proporcionalidad RGPD art. 5.1.c)
// - Si hay sesión, los datos identificativos se cogen de sesión (no del POST), evitando spoof
// - Rate limit por IP

require_once __DIR__ . '/../includes/SecurityHandler.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();
require_once 'conexion.php';
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

// Honeypot — respondemos OK para no dar pistas al bot
if (!empty($_POST['honeypot'])) {
    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    SecurityHandler::jsonError(405, 'Método no permitido.');
}

SecurityHandler::requireCSRF($_POST);

$ip = SecurityHandler::clientIp();
if (SecurityHandler::isRateLimited($pdo, 'consulta', $ip, 10, 600)) {
    SecurityHandler::jsonError(429, 'Has enviado demasiadas consultas. Espera unos minutos antes de volver a intentarlo.');
}

$usuario_id = $_SESSION['user_id'] ?? null;

// Si hay sesión, ignoramos los campos identificativos del POST y usamos los oficiales.
if ($usuario_id) {
    $nombre = trim($_SESSION['user_nombre'] ?? '');
    $apellidos = trim($_SESSION['user_apellidos'] ?? '');
    $email = trim($_SESSION['user_email'] ?? '');
    $dni = trim($_SESSION['user_dni'] ?? '');
    $telefono = trim($_SESSION['user_telefono'] ?? '');
} else {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellidos = trim($_POST['apellidos'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $dni = strtoupper(trim($_POST['dni'] ?? ''));
    $telefono = trim($_POST['telefono'] ?? '');
}

$tipo = trim($_POST['tipo'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');

// Recortes y validación
$nombre = mb_substr($nombre, 0, 60);
$apellidos = mb_substr($apellidos, 0, 80);
$descripcion = mb_substr($descripcion, 0, 2000);

if ($nombre === '' || $apellidos === '' || $email === '' || $tipo === '' || $descripcion === '') {
    SecurityHandler::jsonError(400, 'Por favor, rellene todos los campos obligatorios.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    SecurityHandler::jsonError(400, 'El correo electrónico no es válido.');
}
if ($telefono !== '' && !preg_match('/^[0-9]{9}$/', $telefono)) {
    SecurityHandler::jsonError(400, 'El teléfono debe tener 9 dígitos.');
}
if ($dni !== '' && !preg_match('/^[0-9]{8}[A-Z]$/', $dni) && !preg_match('/^[XYZ][0-9]{7}[A-Z]$/', $dni)) {
    SecurityHandler::jsonError(400, 'Formato de DNI/NIE no válido.');
}
$allowedTipos = ['reserva', 'tarifas', 'quejas', 'otros'];
if (!in_array($tipo, $allowedTipos, true)) {
    SecurityHandler::jsonError(400, 'Tipo de consulta no válido.');
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO consultas (usuario_id, nombre, apellidos, dni, email, telefono, tipo_consulta, descripcion, estado)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')"
    );
    $stmt->execute([$usuario_id, $nombre, $apellidos, $dni ?: null, $email, $telefono ?: null, $tipo, $descripcion]);

    SecurityHandler::recordAttempt($pdo, 'consulta', $ip, true);
    audit_log($pdo, 'consulta_create', 'consulta', $pdo->lastInsertId(), ['tipo' => $tipo]);
    echo json_encode(['success' => true]);
} catch (\PDOException $e) {
    error_log('guardar_consulta: ' . $e->getMessage());
    SecurityHandler::jsonError(500, 'Error al guardar la consulta. Inténtelo de nuevo más tarde.');
}
