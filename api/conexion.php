<?php
// conexion.php
// Archivo de conexión a la base de datos

$host = '127.0.0.1';
$db   = 'ayuntamiento_reservas';
$user = 'root'; // Usuario por defecto en XAMPP
$pass = '';     // Contraseña por defecto en XAMPP
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Si la base de datos no existe, mostramos un error amigable
    die("Error de conexión a la base de datos. Por favor, asegúrate de haber ejecutado setup_db.php primero. Detalles: " . $e->getMessage());
}
?>
