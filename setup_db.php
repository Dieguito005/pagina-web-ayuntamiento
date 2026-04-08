<?php
require_once __DIR__ . '/config.php';
// setup_db.php

$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$dbname = DB_NAME;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Base de datos '$dbname' seleccionada.<br>";

    // 4. Crear la tabla de usuarios administradores
    $sql_usuarios = "CREATE TABLE IF NOT EXISTS usuarios_admin (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        rol VARCHAR(20) DEFAULT 'admin',
        ultimo_acceso DATETIME NULL
    )";
    $pdo->exec($sql_usuarios);
    echo "Tabla 'usuarios_admin' creada.<br>";

    // 5. Crear la tabla de reservas
    $sql_reservas = "CREATE TABLE IF NOT EXISTS reservas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pista VARCHAR(100) NOT NULL,
        fecha DATE NOT NULL,
        hora_inicio TIME NOT NULL,
        hora_fin TIME NOT NULL,
        estado ENUM('reservada', 'bloqueada_admin') DEFAULT 'reservada',
        creado_por INT NULL,
        usuario_nombre VARCHAR(100) NULL,
        usuario_email VARCHAR(100) NULL,
        codigo_acceso VARCHAR(20) NULL,
        FOREIGN KEY (creado_por) REFERENCES usuarios_admin(id) ON DELETE SET NULL
    )";
    $pdo->exec($sql_reservas);
    echo "Tabla 'reservas' creada.<br>";

    // 6. Insertar las credenciales de prueba pre-establecidas
    $credenciales = [
        ['usuario' => 'admin_juan', 'pass' => 'Tarancon2026!'],
        ['usuario' => 'admin_maria', 'pass' => 'Tarancon2026!'],
        ['usuario' => 'admin_carlos', 'pass' => 'Tarancon2026!'],
        ['usuario' => 'admin_laura', 'pass' => 'Tarancon2026!']
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO usuarios_admin (usuario, password_hash, rol) VALUES (?, ?, ?)");
    
    foreach ($credenciales as $cred) {
        $hash = password_hash($cred['pass'], PASSWORD_DEFAULT);
        $stmt->execute([$cred['usuario'], $hash, 'admin']);
    }
    
    echo "Usuarios de prueba creados (si no existían).<br>";
    echo "<strong>¡Instalación completada!</strong><br>";

} catch (\PDOException $e) {
    die("Error durante la instalación: " . $e->getMessage());
}
