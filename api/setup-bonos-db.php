<?php
// api/setup-bonos-db.php — Migración: Sistema de Bonos
// Ejecutar una sola vez para crear las tablas necesarias.
// Protegido por .htaccess en producción.

require_once 'conexion.php';

$messages = [];

try {
    // ── Tabla bonos_config (packs disponibles) ──
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bonos_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tipo VARCHAR(20) UNIQUE NOT NULL,
            nombre VARCHAR(100) NOT NULL,
            usos INT NOT NULL,
            precio DECIMAL(8,2) NOT NULL,
            caducidad_meses INT NOT NULL DEFAULT 6,
            activo TINYINT(1) DEFAULT 1,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Tabla bonos_config creada/verificada.";

    // Insertar packs por defecto si no existen
    $checkPacks = $pdo->query("SELECT COUNT(*) FROM bonos_config")->fetchColumn();
    if ($checkPacks == 0) {
        $pdo->exec("
            INSERT INTO bonos_config (tipo, nombre, usos, precio, caducidad_meses) VALUES
            ('bono_15', 'Bono 15 Sesiones', 15, 50.00, 6),
            ('bono_20', 'Bono 20 Sesiones', 20, 50.00, 6)
        ");
        $messages[] = "✅ Packs por defecto insertados (Bono 15 y Bono 20 a 50€, caducidad 6 meses).";
    } else {
        $messages[] = "ℹ️ Packs ya existentes, no se insertan duplicados.";
    }

    // ── Tabla bonos (bonos comprados por usuarios) ──
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bonos (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $messages[] = "✅ Tabla bonos creada/verificada.";

    // ── Añadir columna bono_id a reservas si no existe ──
    $columnsResult = $pdo->query("SHOW COLUMNS FROM reservas");
    $existingColumns = [];
    while ($col = $columnsResult->fetch()) {
        $existingColumns[] = $col['Field'];
    }

    if (!in_array('bono_id', $existingColumns)) {
        $pdo->exec("ALTER TABLE reservas ADD COLUMN bono_id INT DEFAULT NULL");
        $messages[] = "✅ Columna bono_id añadida a tabla reservas.";
    } else {
        $messages[] = "ℹ️ Columna bono_id ya existe en reservas.";
    }

    echo "<h2>Migración de Bonos completada</h2>";
    foreach ($messages as $msg) {
        echo "<p>$msg</p>";
    }

} catch (\PDOException $e) {
    echo "<h2>Error en la migración</h2>";
    echo "<p>❌ " . htmlspecialchars($e->getMessage()) . "</p>";
}
