<?php
// api/conexion.php
// Conexion a la base de datos via PDO. NUNCA expone detalles de error al cliente.

require_once __DIR__ . '/../config.php';

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    error_log('DB Connection Error: ' . $e->getMessage());
    http_response_code(503);
    // Mensaje genérico siempre — independiente del entorno.
    die('Servicio temporalmente no disponible. Inténtelo más tarde.');
}
