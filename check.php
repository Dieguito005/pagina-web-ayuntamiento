<?php
require_once 'api/conexion.php';
$stmt = $pdo->query("SELECT * FROM consultas");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
