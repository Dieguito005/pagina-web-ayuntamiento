<?php
// api/admin-horarios.php — Gestión de perfiles de horario
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
    $data = read_json_body();
    SecurityHandler::requireCSRF($data);
} else {
    $data = [];
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_perfiles':
            $stmt = $pdo->query("SELECT * FROM horarios_perfiles ORDER BY fecha_creacion DESC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'get_perfil_slots':
            $perfil_id = (int) ($_GET['perfil_id'] ?? 0);
            $pista = $_GET['pista'] ?? '';
            if ($perfil_id <= 0) throw new Exception('Perfil ID requerido');
            $query = "SELECT * FROM horarios_slots WHERE perfil_id = ?";
            $params = [$perfil_id];
            if ($pista !== '') { $query .= " AND pista = ?"; $params[] = $pista; }
            $stmt = $pdo->prepare($query . " ORDER BY dia_semana ASC, hora_inicio ASC");
            $stmt->execute($params);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'create_perfil':
            $nombre = trim($data['nombre'] ?? 'Nuevo Perfil');
            $nombre = mb_substr(strip_tags($nombre), 0, 80);
            $stmt = $pdo->prepare("INSERT INTO horarios_perfiles (nombre) VALUES (?)");
            $stmt->execute([$nombre]);
            $newId = $pdo->lastInsertId();
            audit_log($pdo, 'horario_perfil_create', 'horarios_perfiles', $newId, ['nombre' => $nombre]);
            echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Perfil creado']);
            break;

        case 'rename_perfil':
            $id = (int) ($data['id'] ?? 0);
            $nombre = trim($data['nombre'] ?? '');
            $nombre = mb_substr(strip_tags($nombre), 0, 80);
            if ($id <= 0 || $nombre === '') throw new Exception('ID y nombre requeridos');
            $stmt = $pdo->prepare("UPDATE horarios_perfiles SET nombre = ? WHERE id = ?");
            $stmt->execute([$nombre, $id]);
            audit_log($pdo, 'horario_perfil_rename', 'horarios_perfiles', $id, ['nombre' => $nombre]);
            echo json_encode(['success' => true, 'message' => 'Perfil renombrado']);
            break;

        case 'delete_perfil':
            $id = (int) ($data['id'] ?? 0);
            $stmtCheck = $pdo->prepare("SELECT activo FROM horarios_perfiles WHERE id = ?");
            $stmtCheck->execute([$id]);
            $perfil = $stmtCheck->fetch();
            if (!$perfil) throw new Exception('Perfil no encontrado');
            if ($perfil['activo']) throw new Exception('No se puede eliminar el perfil activo');
            $stmt = $pdo->prepare("DELETE FROM horarios_perfiles WHERE id = ?");
            $stmt->execute([$id]);
            audit_log($pdo, 'horario_perfil_delete', 'horarios_perfiles', $id);
            echo json_encode(['success' => true, 'message' => 'Perfil eliminado']);
            break;

        case 'activar_perfil':
            $id = (int) ($data['id'] ?? 0);
            if ($id <= 0) throw new Exception('ID requerido');
            $pdo->beginTransaction();
            $pdo->query("UPDATE horarios_perfiles SET activo = 0");
            $stmt = $pdo->prepare("UPDATE horarios_perfiles SET activo = 1 WHERE id = ?");
            $stmt->execute([$id]);
            $pdo->commit();
            audit_log($pdo, 'horario_perfil_activate', 'horarios_perfiles', $id);
            echo json_encode(['success' => true, 'message' => 'Perfil activado.']);
            break;

        case 'save_slots':
            $perfil_id = (int) ($data['perfil_id'] ?? 0);
            $pista = $data['pista'] ?? '';
            $dia = $data['dia_semana'] ?? null;
            $slots = $data['slots'] ?? [];
            if ($perfil_id <= 0 || $pista === '' || $dia === null) throw new Exception('Faltan parámetros');

            $pdo->beginTransaction();
            $stmtDel = $pdo->prepare("DELETE FROM horarios_slots WHERE perfil_id = ? AND pista = ? AND dia_semana = ?");
            $stmtDel->execute([$perfil_id, $pista, $dia]);
            $stmtIns = $pdo->prepare("INSERT INTO horarios_slots (perfil_id, pista, dia_semana, hora_inicio, hora_fin, periodo, tiene_suplemento_luz) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($slots as $slot) {
                $stmtIns->execute([
                    $perfil_id, $pista, $dia,
                    $slot['hora_inicio'] ?? null,
                    $slot['hora_fin'] ?? null,
                    $slot['periodo'] ?? null,
                    isset($slot['tiene_suplemento_luz']) ? (int) $slot['tiene_suplemento_luz'] : 0,
                ]);
            }
            $pdo->commit();
            audit_log($pdo, 'horario_slots_save', 'horarios_slots', $perfil_id, ['pista' => $pista, 'dia' => $dia, 'count' => count($slots)]);
            echo json_encode(['success' => true, 'message' => 'Horarios guardados correctamente']);
            break;

        default:
            throw new Exception('Acción no reconocida');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('admin_horarios: ' . $e->getMessage());
    http_response_code(400);
    // Devuelve sólo el mensaje propio, NUNCA detalles PDO
    $msg = $e instanceof \PDOException ? 'Error de base de datos.' : $e->getMessage();
    echo json_encode(['success' => false, 'message' => $msg]);
}
