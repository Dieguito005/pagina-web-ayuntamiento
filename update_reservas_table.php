<?php
// update_reservas_table.php
// Ejecuta UNA VEZ para añadir las columnas que faltan a la tabla 'reservas'
// URL: http://localhost/pagina-web-ayuntamiento/update_reservas_table.php

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$dbname = 'ayuntamiento_reservas';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h2>Actualizando tabla 'reservas'...</h2>";

    // Columnas que deberían existir
    $nuevas_columnas = [
        "ALTER TABLE reservas ADD COLUMN dni VARCHAR(20) NULL AFTER usuario_email",
        "ALTER TABLE reservas ADD COLUMN telefono VARCHAR(20) NULL AFTER dni",
        "ALTER TABLE reservas ADD COLUMN precio_total DECIMAL(6,2) NULL AFTER telefono",
        "ALTER TABLE reservas ADD COLUMN usuario_id INT NULL AFTER creado_por",
        "ALTER TABLE reservas ADD COLUMN fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP AFTER codigo_acceso",
    ];

    // Actualizar ENUM de estado para incluir 'cancelada'
    $nuevas_columnas[] = "ALTER TABLE reservas MODIFY COLUMN estado ENUM('reservada', 'bloqueada_admin', 'cancelada') DEFAULT 'reservada'";

    foreach ($nuevas_columnas as $sql) {
        try {
            $pdo->exec($sql);
            echo "<p style='color:green;'>✅ Ejecutado: " . htmlspecialchars($sql) . "</p>";
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "<p style='color:orange;'>⚠️ Ya existe: " . htmlspecialchars($sql) . "</p>";
            } else {
                echo "<p style='color:red;'>❌ Error: " . $e->getMessage() . "</p>";
            }
        }
    }

    echo "<br><strong>¡Actualización completada!</strong>";
    echo "<br><em>Por seguridad, elimina este archivo después de usarlo.</em>";

} catch (\PDOException $e) {
    die("<span style='color:red;'>Error de conexión:</span> " . $e->getMessage());
}
?>
