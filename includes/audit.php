<?php
/**
 * audit.php — Registro de auditoría para acciones sensibles.
 *
 * Tabla `audit_log`:
 *   id, fecha (DATETIME), actor_tipo (user|admin|guest|system), actor_id (INT|null),
 *   accion (varchar), entidad (varchar), entidad_id (varchar|null),
 *   datos_json (TEXT), ip_anonymized, user_agent
 *
 * Crear con api/setup-security-db.php.
 *
 * Política de retención: 12 meses (la rotación se hace fuera, vía cron o purga manual).
 */

require_once __DIR__ . '/SecurityHandler.php';

function audit_log(\PDO $pdo, string $accion, string $entidad, $entidadId = null, array $datos = []): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();

    $actorTipo = 'guest';
    $actorId = null;
    if (!empty($_SESSION['admin_id'])) {
        $actorTipo = 'admin';
        $actorId = (int) $_SESSION['admin_id'];
    } elseif (!empty($_SESSION['user_id'])) {
        $actorTipo = 'user';
        $actorId = (int) $_SESSION['user_id'];
    }

    // Datos sensibles a omitir del JSON del log
    $blacklist = ['password', 'password_hash', 'csrf_token', 'auth_password', 'current_password', 'new_password', 'confirm_password'];
    foreach ($blacklist as $k) {
        if (array_key_exists($k, $datos)) {
            $datos[$k] = '[REDACTED]';
        }
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO audit_log (fecha, actor_tipo, actor_id, accion, entidad, entidad_id, datos_json, ip_anonymized, user_agent)
             VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $actorTipo,
            $actorId,
            substr($accion, 0, 60),
            substr($entidad, 0, 60),
            $entidadId !== null ? substr((string) $entidadId, 0, 60) : null,
            json_encode($datos, JSON_UNESCAPED_UNICODE),
            SecurityHandler::clientIpAnonymized(),
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
    } catch (\PDOException $e) {
        // No abortar la operación principal por fallo del log
        error_log('audit_log_error: ' . $e->getMessage());
    }
}
