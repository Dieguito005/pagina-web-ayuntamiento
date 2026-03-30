<?php
// setup_db_update.php
// Script para actualizar la base de datos con las nuevas columnas necesarias para el historial y buscador avanzado.

$host = '127.0.0.1';
$user = 'root'; // Usuario por defecto en XAMPP
$pass = '';     // Contraseña por defecto en XAMPP
$dbname = 'ayuntamiento_reservas';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Conectado a la base de datos '$dbname'.<br><br>";

    // Array de consultas para alterar la tabla 'reservas'
    $consultas = [
        "ALTER TABLE reservas ADD COLUMN IF NOT EXISTS dni VARCHAR(20) NULL AFTER usuario_nombre",
        "ALTER TABLE reservas ADD COLUMN IF NOT EXISTS telefono VARCHAR(20) NULL AFTER dni",
        "ALTER TABLE reservas ADD COLUMN IF NOT EXISTS precio_total DECIMAL(10,2) NULL AFTER telefono",
        "ALTER TABLE reservas ADD COLUMN IF NOT EXISTS fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP AFTER precio_total",
        "ALTER TABLE reservas MODIFY COLUMN estado ENUM('reservada', 'bloqueada_admin', 'cancelada') DEFAULT 'reservada'"
    ];

    foreach ($consultas as $sql) {
        try {
            $pdo->exec($sql);
            echo "<span style='color:green;'>Éxito:</span> $sql <br>";
        } catch (\PDOException $e) {
            // Ignorar errores de "Duplicate column name"
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "<span style='color:orange;'>Nota:</span> La columna ya existe. ($sql)<br>";
            } else {
                echo "<span style='color:red;'>Error al ejecutar:</span> $sql <br> Detalle: " . $e->getMessage() . "<br>";
            }
        }
    }

    echo "<br><strong>¡Actualización completada!</strong> La base de datos está lista para guardar los DNI, Teléfonos y Precios de ahora en adelante, y permitir cancelaciones.";

} catch (\PDOException $e) {
    die("<span style='color:red;'>Error de conexión:</span> " . $e->getMessage());
}
?>
