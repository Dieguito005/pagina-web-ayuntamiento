<?php
// api/get-anuncios.php
header('Content-Type: application/json');
require_once 'conexion.php';

try {
    // Solo extraer los activos
    $stmt = $pdo->query("SELECT id, titulo, mensaje, tipo FROM anuncios WHERE activo = 1 ORDER BY fecha_creacion DESC");
    $anuncios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $anuncios]);
} catch (\PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error al cargar anuncios.']);
}
?>
