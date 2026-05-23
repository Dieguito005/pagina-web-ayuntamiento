<?php
// api/setup-horarios-db.php — Migracion de tablas de horarios.
// Idempotente. Protegido por admin logueado O ?key=<SETUP_DB_KEY>.

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/conexion.php';
session_start();
header('Content-Type: text/plain; charset=UTF-8');

$adminOk = !empty($_SESSION['admin_id']);
$keyOk = defined('SETUP_DB_KEY') && SETUP_DB_KEY !== '' && hash_equals(SETUP_DB_KEY, (string) ($_GET['key'] ?? ''));
if (!$adminOk && !$keyOk) {
    http_response_code(403);
    echo "Acceso denegado.\n";
    exit;
}

try {
    $pdo->query("CREATE TABLE IF NOT EXISTS horarios_perfiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        activo TINYINT(1) DEFAULT 0,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        fecha_modificacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->query("CREATE TABLE IF NOT EXISTS horarios_slots (
        id INT AUTO_INCREMENT PRIMARY KEY,
        perfil_id INT NOT NULL,
        pista VARCHAR(50) NOT NULL,
        dia_semana TINYINT NOT NULL,
        hora_inicio TIME NOT NULL,
        hora_fin TIME NOT NULL,
        periodo ENUM('morning','afternoon') NOT NULL,
        tiene_suplemento_luz TINYINT(1) DEFAULT 0,
        FOREIGN KEY (perfil_id) REFERENCES horarios_perfiles(id) ON DELETE CASCADE,
        INDEX idx_perfil_pista (perfil_id, pista),
        INDEX idx_perfil_activo (perfil_id, dia_semana)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->beginTransaction();
    $stmt = $pdo->query("SELECT id FROM horarios_perfiles WHERE activo = 1 LIMIT 1");
    if (!$stmt->fetch()) {
        $pdo->query("INSERT INTO horarios_perfiles (nombre, activo) VALUES ('Horario Actual', 1)");
        $perfilId = $pdo->lastInsertId();

        $pistas = ['Tenis', 'Padel Cubierta', 'Padel Descubierta', 'Futbol 7'];
        $weekdaySlots = [
            ['09:00', '11:00', 'morning', 0],
            ['11:00', '13:00', 'morning', 0],
            ['16:00', '18:00', 'afternoon', 0],
            ['18:00', '20:00', 'afternoon', 0],
            ['20:00', '22:00', 'afternoon', 1],
        ];
        $saturdaySlots = [
            ['09:00', '11:00', 'morning', 0],
            ['11:00', '13:00', 'morning', 0],
            ['16:30', '18:30', 'afternoon', 0],
        ];
        $sundaySlots = [
            ['09:00', '11:00', 'morning', 0],
            ['11:00', '13:00', 'morning', 0],
        ];

        $stmtInsert = $pdo->prepare(
            "INSERT INTO horarios_slots (perfil_id, pista, dia_semana, hora_inicio, hora_fin, periodo, tiene_suplemento_luz)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        foreach ($pistas as $pista) {
            for ($dia = 1; $dia <= 5; $dia++) {
                foreach ($weekdaySlots as $slot) {
                    $stmtInsert->execute([$perfilId, $pista, $dia, $slot[0], $slot[1], $slot[2], $slot[3]]);
                }
            }
            foreach ($saturdaySlots as $slot) {
                $stmtInsert->execute([$perfilId, $pista, 6, $slot[0], $slot[1], $slot[2], $slot[3]]);
            }
            foreach ($sundaySlots as $slot) {
                $stmtInsert->execute([$perfilId, $pista, 0, $slot[0], $slot[1], $slot[2], $slot[3]]);
            }
        }
    }

    $pdo->commit();
    echo "Migracion de horarios completada con exito.\n";
} catch (\Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('setup_horarios_db: ' . $e->getMessage());
    http_response_code(500);
    echo "Error en la migracion (ver error_log).\n";
}
