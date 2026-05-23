<?php
// api/admin-anuncios.php — CRUD de anuncios.
// Anti-XSS: sanitiza titulo/mensaje a HTML plano (sin tags) antes de guardar.

require_once __DIR__ . '/../includes/SecurityHandler.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();
require_once 'conexion.php';
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

SecurityHandler::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = !empty($_POST) ? $_POST : read_json_body();
    SecurityHandler::requireCSRF($data);
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'get';
$allowedTipos = ['info', 'aviso', 'peligro', 'exito'];

try {
    if ($action === 'get') {
        $stmt = $pdo->query("SELECT * FROM anuncios ORDER BY fecha_creacion DESC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'create') {
        $titulo = trim($_POST['titulo'] ?? '');
        $mensaje = trim($_POST['mensaje'] ?? '');
        $tipo = trim($_POST['tipo'] ?? 'info');

        // Sanitización: tira tags y limita longitud
        $titulo = strip_tags($titulo);
        $mensaje = strip_tags($mensaje);
        if (mb_strlen($titulo) > 120) $titulo = mb_substr($titulo, 0, 120);
        if (mb_strlen($mensaje) > 2000) $mensaje = mb_substr($mensaje, 0, 2000);

        if ($titulo === '' || $mensaje === '') {
            echo json_encode(['success' => false, 'message' => 'Título y mensaje son obligatorios.']);
            exit;
        }
        if (!in_array($tipo, $allowedTipos, true)) $tipo = 'info';

        $stmt = $pdo->prepare("INSERT INTO anuncios (titulo, mensaje, tipo, activo) VALUES (?, ?, ?, 1)");
        $stmt->execute([$titulo, $mensaje, $tipo]);
        audit_log($pdo, 'anuncio_create', 'anuncio', $pdo->lastInsertId(), ['tipo' => $tipo]);
        echo json_encode(['success' => true, 'message' => 'Anuncio publicado correctamente.']);
        exit;
    }

    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $activo = (int) ($_POST['activo'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }
        $stmt = $pdo->prepare("UPDATE anuncios SET activo = ? WHERE id = ?");
        $stmt->execute([$activo ? 1 : 0, $id]);
        audit_log($pdo, 'anuncio_toggle', 'anuncio', $id, ['activo' => $activo]);
        echo json_encode(['success' => true, 'message' => 'Estado del anuncio actualizado.']);
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM anuncios WHERE id = ?");
        $stmt->execute([$id]);
        audit_log($pdo, 'anuncio_delete', 'anuncio', $id);
        echo json_encode(['success' => true, 'message' => 'Anuncio eliminado.']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
} catch (\PDOException $e) {
    error_log('admin_anuncios_error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de base de datos.']);
}
