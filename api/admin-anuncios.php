<?php
session_start();
header('Content-Type: application/json');
require_once 'conexion.php';

// Solo el admin puede usar esta API
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'get';

try {
    if ($action === 'get') {
        $stmt = $pdo->query("SELECT * FROM anuncios ORDER BY fecha_creacion DESC");
        $anuncios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $anuncios]);
        exit;
    }

    if ($action === 'create') {
        $titulo = trim($_POST['titulo'] ?? '');
        $mensaje = trim($_POST['mensaje'] ?? '');
        $tipo = trim($_POST['tipo'] ?? 'info');

        if (empty($titulo) || empty($mensaje)) {
            echo json_encode(['success' => false, 'message' => 'Título y mensaje son obligatorios.']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO anuncios (titulo, mensaje, tipo, activo) VALUES (?, ?, ?, 1)");
        $stmt->execute([$titulo, $mensaje, $tipo]);
        echo json_encode(['success' => true, 'message' => 'Anuncio publicado correctamente.']);
        exit;
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $activo = (int)($_POST['activo'] ?? 0);

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE anuncios SET activo = ? WHERE id = ?");
        $stmt->execute([$activo, $id]);
        echo json_encode(['success' => true, 'message' => 'Estado del anuncio actualizado.']);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM anuncios WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Anuncio eliminado permanentemente.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
} catch (\PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error DB: ' . $e->getMessage()]);
}
?>
