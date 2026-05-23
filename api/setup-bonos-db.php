<?php
// api/setup-bonos-db.php — Migracion del sistema de Bonos.
// Idempotente. Protegido por admin logueado O ?key=<SETUP_DB_KEY>.

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

$messages = [];
$errors = [];

$queries = [
    "CREATE TABLE IF NOT EXISTS bonos_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tipo VARCHAR(20) UNIQUE NOT NULL,
        nombre VARCHAR(100) NOT NULL,
        usos INT NOT NULL,
        precio DECIMAL(8,2) NOT NULL,
        caducidad_meses INT NOT NULL DEFAULT 6,
        activo TINYINT(1) DEFAULT 1,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS bonos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        tipo VARCHAR(20) NOT NULL,
        nombre VARCHAR(100) NOT NULL,
        usos_totales INT NOT NULL,
        usos_restantes INT NOT NULL,
        precio_pagado DECIMAL(8,2) NOT NULL,
        estado ENUM('activo','agotado','caducado','cancelado') DEFAULT 'activo',
        stripe_session_id VARCHAR(255) DEFAULT NULL,
        fecha_compra DATETIME DEFAULT CURRENT_TIMESTAMP,
        fecha_caducidad DATE DEFAULT NULL,
        fecha_agotado DATETIME DEFAULT NULL,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "ALTER TABLE reservas ADD COLUMN IF NOT EXISTS bono_id INT DEFAULT NULL",
];

foreach ($queries as $sql) {
    try {
        $pdo->query($sql);
        $messages[] = '[OK] ' . substr($sql, 0, 70) . '...';
    } catch (\PDOException $e) {
        error_log('setup_bonos_db: ' . $e->getMessage());
        $errors[] = '[ERR] ' . substr($sql, 0, 70) . '... (ver error_log)';
    }
}

try {
    $checkPacks = (int) $pdo->query('SELECT COUNT(*) FROM bonos_config')->fetchColumn();
    if ($checkPacks === 0) {
        $pdo->query("INSERT INTO bonos_config (tipo, nombre, usos, precio, caducidad_meses) VALUES
            ('bono_15', 'Bono 15 Sesiones', 15, 50.00, 6),
            ('bono_20', 'Bono 20 Sesiones', 20, 50.00, 6)");
        $messages[] = '[OK] Packs por defecto insertados (15 y 20 sesiones a 50 EUR, caducidad 6 meses).';
    } else {
        $messages[] = '[OK] Packs ya existentes (no se duplican).';
    }
} catch (\PDOException $e) {
    error_log('setup_bonos_db_packs: ' . $e->getMessage());
    $errors[] = '[ERR] No se pudieron insertar packs por defecto (ver error_log).';
}

echo implode("\n", $messages);
if ($errors) {
    echo "\n\nErrores:\n" . implode("\n", $errors);
}
echo "\n";
