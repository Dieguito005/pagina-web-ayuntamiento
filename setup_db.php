<?php
// setup_db.php
// Ejecuta este archivo en el navegador (http://localhost/tu-carpeta/setup_db.php) una sola vez para crear la BBDD y usuarios.

$host = '127.0.0.1';
$user = 'root'; // Usuario por defecto en XAMPP
$pass = '';     // Contraseña por defecto en XAMPP

try {
    // 1. Conectamos a MySQL (sin seleccionar base de datos aún)
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. Crear la base de datos si no existe
    $pdo->exec("CREATE DATABASE IF NOT EXISTS ayuntamiento_reservas DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Base de datos 'ayuntamiento_reservas' creada o ya existía.<br>";

    // 3. Seleccionar la base de datos
    $pdo->exec("USE ayuntamiento_reservas");

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
        $rol = $cred['rol'] ?? 'admin';
        $stmt->execute([$cred['usuario'], $hash, $rol]);
    }
    echo "Usuarios administradores creados (si no existían previamente).<br>";
    echo "<br><strong>¡Instalación completada con éxito!</strong> Por seguridad, te recomendamos eliminar o renombrar este archivo 'setup_db.php' después de usarlo.";

} catch (\PDOException $e) {
    die("Error durante la instalación: " . $e->getMessage());
}
?>
