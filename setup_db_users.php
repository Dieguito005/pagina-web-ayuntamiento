<?php
require_once __DIR__ . '/config.php';
// setup_db_users.php
// Ejecuta una sola vez: http://localhost/pagina-web-ayuntamiento/setup_db_users.php

$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$dbname = DB_NAME;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Conectado a '$dbname'.<br><br>";

    // 1. Crear tabla de usuarios públicos
    $sql_usuarios = "CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        apellidos VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        dni VARCHAR(20) NULL,
        telefono VARCHAR(20) NULL,
        fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
        ultimo_acceso DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql_usuarios);
    echo "<span style='color:green;'>✅ Tabla 'usuarios' creada correctamente.</span><br>";

    // 2. Añadir columna usuario_id a la tabla reservas (para vincular reservas con cuentas)
    $columnas_reservas = [
        "ALTER TABLE reservas ADD COLUMN IF NOT EXISTS usuario_id INT NULL AFTER creado_por",
    ];

    foreach ($columnas_reservas as $sql) {
        try {
            $pdo->exec($sql);
            echo "<span style='color:green;'>✅ Columna usuario_id añadida a 'reservas'.</span><br>";
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "<span style='color:orange;'>⚠️ La columna usuario_id ya existe en 'reservas'.</span><br>";
            } else {
                echo "<span style='color:red;'>❌ Error: " . $e->getMessage() . "</span><br>";
            }
        }
    }

    echo "<br><strong>¡Actualización completada!</strong> Ya puedes registrar usuarios en la web.";
    echo "<br><em>Por seguridad, elimina o renombra este archivo después de usarlo.</em>";

} catch (\PDOException $e) {
    die("<span style='color:red;'>Error de conexión:</span> " . $e->getMessage());
}
?>
