<?php
require_once 'api/conexion.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS anuncios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(255) NOT NULL,
        mensaje TEXT NOT NULL,
        tipo ENUM('info', 'aviso', 'peligro', 'exito') NOT NULL DEFAULT 'info',
        activo TINYINT(1) DEFAULT 1,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $pdo->exec($sql);
    echo "Tabla 'anuncios' creada o verificada exitosamente.\n";
} catch (PDOException $e) {
    echo "Error al crear tabla anuncios: " . $e->getMessage() . "\n";
}
?>
