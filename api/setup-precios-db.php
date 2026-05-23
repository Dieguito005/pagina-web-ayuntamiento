<?php
// api/setup-precios-db.php — Crea la tabla de precios personalizados.
// Protegido por: admin logueado O ?key=<SETUP_DB_KEY>.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/conexion.php';
session_start();
header('Content-Type: text/plain; charset=UTF-8');

$adminOk = !empty($_SESSION['admin_id']);
$keyOk = defined('SETUP_DB_KEY') && SETUP_DB_KEY !== '' && hash_equals(SETUP_DB_KEY, (string) ($_GET['key'] ?? ''));
if (!$adminOk && !$keyOk) {
    http_response_code(403);
    echo "Acceso denegado.\n";
    exit;
}

try {
    $pdo->query("CREATE TABLE IF NOT EXISTS precios_personalizados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pista VARCHAR(100) NOT NULL,
        fecha DATE NOT NULL,
        hora_inicio TIME NOT NULL,
        precio_base DECIMAL(10,2) DEFAULT NULL,
        precio_luz DECIMAL(10,2) DEFAULT NULL,
        UNIQUE KEY uq_pista_fecha_hora (pista, fecha, hora_inicio)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Tabla precios_personalizados verificada/creada correctamente.\n";
} catch (\PDOException $e) {
    error_log('setup_precios_db: ' . $e->getMessage());
    http_response_code(500);
    echo "Error interno.\n";
}
