<?php
// api/admin-horarios.php
session_start();
require_once 'conexion.php';
header('Content-Type: application/json');

// Verificar sesión admin
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../includes/SecurityHandler.php';
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!SecurityHandler::validateCSRFToken($csrfToken)) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Token de seguridad inválido.']));
    }
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_perfiles':
            $stmt = $pdo->query("SELECT * FROM horarios_perfiles ORDER BY fecha_creacion DESC");
            $perfiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $perfiles]);
            break;

        case 'get_perfil_slots':
            $perfil_id = $_GET['perfil_id'] ?? 0;
            $pista = $_GET['pista'] ?? '';
            
            if (!$perfil_id) throw new Exception("Perfil ID requerido");

            $query = "SELECT * FROM horarios_slots WHERE perfil_id = ?";
            $params = [$perfil_id];
            
            if ($pista) {
                $query .= " AND pista = ?";
                $params[] = $pista;
            }
            
            $stmt = $pdo->prepare($query . " ORDER BY dia_semana ASC, hora_inicio ASC");
            $stmt->execute($params);
            $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $slots]);
            break;

        case 'create_perfil':
            $data = json_decode(file_get_contents('php://input'), true);
            $nombre = $data['nombre'] ?? 'Nuevo Perfil';
            
            $stmt = $pdo->prepare("INSERT INTO horarios_perfiles (nombre) VALUES (?)");
            $stmt->execute([$nombre]);
            
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Perfil creado']);
            break;

        case 'rename_perfil':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? 0;
            $nombre = $data['nombre'] ?? '';
            
            if (!$id || !$nombre) throw new Exception("ID y nombre requeridos");
            
            $stmt = $pdo->prepare("UPDATE horarios_perfiles SET nombre = ? WHERE id = ?");
            $stmt->execute([$nombre, $id]);
            
            echo json_encode(['success' => true, 'message' => 'Perfil renombrado']);
            break;

        case 'delete_perfil':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? 0;
            
            // No permitir borrar el perfil activo
            $stmtCheck = $pdo->prepare("SELECT activo FROM horarios_perfiles WHERE id = ?");
            $stmtCheck->execute([$id]);
            $perfil = $stmtCheck->fetch();
            
            if (!$perfil) throw new Exception("Perfil no encontrado");
            if ($perfil['activo']) throw new Exception("No se puede eliminar el perfil activo");
            
            $stmt = $pdo->prepare("DELETE FROM horarios_perfiles WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Perfil eliminado']);
            break;

        case 'activar_perfil':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? 0;
            
            if (!$id) throw new Exception("ID requerido");
            
            $pdo->beginTransaction();
            // Desactivar todos
            $pdo->exec("UPDATE horarios_perfiles SET activo = 0");
            // Activar uno
            $stmt = $pdo->prepare("UPDATE horarios_perfiles SET activo = 1 WHERE id = ?");
            $stmt->execute([$id]);
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Perfil activado con éxito']);
            break;

        case 'save_slots':
            $data = json_decode(file_get_contents('php://input'), true);
            $perfil_id = $data['perfil_id'] ?? 0;
            $pista = $data['pista'] ?? '';
            $dia = $data['dia_semana'] ?? null; // Si es null, guardamos para varios días? No, mejor por día.
            $slots = $data['slots'] ?? []; // Array de slots {hora_inicio, hora_fin, periodo, tiene_suplemento_luz}
            
            if (!$perfil_id || !$pista || $dia === null) throw new Exception("Faltan parámetros");
            
            $pdo->beginTransaction();
            
            // Borrar slots actuales para ese perfil, pista y día
            $stmtDel = $pdo->prepare("DELETE FROM horarios_slots WHERE perfil_id = ? AND pista = ? AND dia_semana = ?");
            $stmtDel->execute([$perfil_id, $pista, $dia]);
            
            // Insertar nuevos slots
            $stmtIns = $pdo->prepare("INSERT INTO horarios_slots (perfil_id, pista, dia_semana, hora_inicio, hora_fin, periodo, tiene_suplemento_luz) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($slots as $slot) {
                $stmtIns->execute([
                    $perfil_id, 
                    $pista, 
                    $dia, 
                    $slot['hora_inicio'], 
                    $slot['hora_fin'], 
                    $slot['periodo'], 
                    isset($slot['tiene_suplemento_luz']) ? (int)$slot['tiene_suplemento_luz'] : 0
                ]);
            }
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Horarios guardados correctamente']);
            break;

        default:
            throw new Exception("Acción no reconocida");
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
