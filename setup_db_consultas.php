<?php
// setup_db_consultas.php
// Script para crear la tabla de consultas

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbname = 'ayuntamiento_reservas';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Conectado a '$dbname'.\n";

    $sql = "CREATE TABLE IF NOT EXISTS consultas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NULL,
        nombre VARCHAR(100) NOT NULL,
        apellidos VARCHAR(100) NOT NULL,
        dni VARCHAR(20) NOT NULL,
        email VARCHAR(150) NOT NULL,
        telefono VARCHAR(20) NOT NULL,
        tipo_consulta VARCHAR(50) NOT NULL,
        descripcion TEXT NOT NULL,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        estado VARCHAR(20) DEFAULT 'pendiente',
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql);
    echo "Tabla 'consultas' creada o verificada correctamente.\n";

} catch (\PDOException $e) {
    die("Error de conexión o creación: " . $e->getMessage());
}
?>
