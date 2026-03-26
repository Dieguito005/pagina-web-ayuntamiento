<?php
require_once 'conexion.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS precios_personalizados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pista VARCHAR(100) NOT NULL,
        fecha DATE NOT NULL,
        hora_inicio TIME NOT NULL,
        precio_base DECIMAL(10,2) DEFAULT NULL,
        precio_luz DECIMAL(10,2) DEFAULT NULL,
        UNIQUE KEY uq_pista_fecha_hora (pista, fecha, hora_inicio)
    )";
    $pdo->exec($sql);
    echo "Tabla 'precios_personalizados' verificada/creada correctamente.\n";
} catch (\PDOException $e) {
    die("Error al crear la tabla: " . $e->getMessage() . "\n");
}
?>