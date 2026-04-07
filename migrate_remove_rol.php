<?php
// migrate_remove_rol.php
// Ejecuta este archivo UNA SOLA VEZ en el navegador para limpiar la columna 'rol'
// de la tabla usuarios_admin en la base de datos existente.
// URL: http://localhost/pagina-web-ayuntamiento/migrate_remove_rol.php

$host = '127.0.0.1';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=ayuntamiento_reservas;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Cambiar el tipo de columna 'rol' de ENUM a VARCHAR simple
    $pdo->exec("ALTER TABLE usuarios_admin MODIFY rol VARCHAR(20) DEFAULT 'admin'");
    echo "✅ Columna 'rol' actualizada: ya no usa ENUM.<br>";

    // 2. Actualizar cualquier fila que tenga rol = 'concejal' para que pase a ser 'admin'
    $stmt = $pdo->exec("UPDATE usuarios_admin SET rol = 'admin' WHERE rol = 'concejal'");
    echo "✅ Filas actualizadas (rol 'concejal' → 'admin'): <strong>{$stmt}</strong><br>";

    // 3. (Opcional) Eliminar el usuario concejal_deportes si existe
    $del = $pdo->exec("DELETE FROM usuarios_admin WHERE usuario = 'concejal_deportes'");
    echo "✅ Usuario 'concejal_deportes' eliminado (si existía): <strong>{$del}</strong> fila(s) afectadas.<br>";

    echo "<br><strong style='color:green;'>¡Migración completada con éxito!</strong> Puedes eliminar este archivo.";

} catch (\PDOException $e) {
    die("❌ Error durante la migración: " . $e->getMessage());
}
?>
