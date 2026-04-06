<?php
require_once 'api/conexion.php';
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'consultas'");
    $res = $stmt->fetchAll();
    if (count($res) > 0) {
        echo "TABLE EXISTS\n";
    } else {
        echo "TABLE DOES NOT EXIST\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
