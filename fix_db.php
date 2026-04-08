<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
require_once 'env.php';
require_once 'config.php';

$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$dbname = DB_NAME;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h3>Arreglando la Base de Datos...</h3>";

    // 1. Añadir columna verificado_admin
    $sql = "ALTER TABLE reservas ADD COLUMN IF NOT EXISTS verificado_admin BOOLEAN DEFAULT 0";
    try {
        $pdo->exec($sql);
        echo "<span style='color:green;'>✅ Columna verificado_admin añadida correctamente.</span><br>";
    } catch (\PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<span style='color:orange;'>/!\\ La columna verificado_admin ya existía.</span><br>";
        } else {
            echo "<span style='color:red;'>❌ Error añadiendo verificado_admin: " . $e->getMessage() . "</span><br>";
        }
    }

    // 2. Crear tabla precios_personalizados si no existe
    $sql = "CREATE TABLE IF NOT EXISTS precios_personalizados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pista VARCHAR(100) NOT NULL,
        fecha DATE NOT NULL,
        hora_inicio TIME NOT NULL,
        precio_base DECIMAL(10,2) DEFAULT NULL,
        precio_luz DECIMAL(10,2) DEFAULT NULL,
        UNIQUE KEY uq_pista_fecha_hora (pista, fecha, hora_inicio)
    )";
    try {
        $pdo->exec($sql);
        echo "<span style='color:green;'>✅ Tabla precios_personalizados creada correctamente.</span><br>";
    } catch (\PDOException $e) {
        echo "<span style='color:red;'>❌ Error creando tabla precios: " . $e->getMessage() . "</span><br>";
    }

    echo "<h4>¡Listo! Ya puedes borrar este archivo.</h4>";
    @unlink(__FILE__);
} catch (\PDOException $e) {
    die("Error crítico conectando a BBDD: " . $e->getMessage());
}
