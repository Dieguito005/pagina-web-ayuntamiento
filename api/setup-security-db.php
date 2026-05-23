<?php
/**
 * api/setup-security-db.php — Inicializa las tablas auxiliares de seguridad y cumplimiento.
 *
 * Ejecutar UNA VEZ tras desplegar. Idempotente: usa CREATE TABLE IF NOT EXISTS.
 * Protegido por: admin logueado O clave en query string `?key=<SETUP_DB_KEY>`.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/conexion.php';
session_start();

header('Content-Type: text/plain; charset=UTF-8');

$adminOk = !empty($_SESSION['admin_id']);
$keyOk = defined('SETUP_DB_KEY') && SETUP_DB_KEY !== '' && hash_equals(SETUP_DB_KEY, (string) ($_GET['key'] ?? ''));
if (!$adminOk && !$keyOk) {
    http_response_code(403);
    echo "Acceso denegado. Necesitas estar logueado como admin o pasar ?key=<SETUP_DB_KEY>.\n";
    exit;
}

$queries = [
    "CREATE TABLE IF NOT EXISTS audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fecha DATETIME NOT NULL,
        actor_tipo VARCHAR(20) NOT NULL,
        actor_id INT NULL,
        accion VARCHAR(60) NOT NULL,
        entidad VARCHAR(60) NOT NULL,
        entidad_id VARCHAR(60) NULL,
        datos_json TEXT NULL,
        ip_anonymized VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        INDEX idx_fecha (fecha),
        INDEX idx_actor (actor_tipo, actor_id),
        INDEX idx_entidad (entidad, entidad_id),
        INDEX idx_accion (accion)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        action VARCHAR(40) NOT NULL,
        identifier VARCHAR(120) NOT NULL,
        success TINYINT(1) NOT NULL DEFAULT 0,
        ip VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        created_at DATETIME NOT NULL,
        INDEX idx_action_id (action, identifier, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        ip_anonymized VARCHAR(45) NULL,
        created_at DATETIME NOT NULL,
        UNIQUE KEY uq_token (token_hash),
        INDEX idx_user (user_id),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS email_verifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        verified_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        UNIQUE KEY uq_email_token (token_hash),
        INDEX idx_user_email (user_id),
        INDEX idx_expires_email (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS consent_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        consent_id CHAR(36) NOT NULL,
        decision VARCHAR(20) NOT NULL,
        analytics TINYINT(1) NOT NULL DEFAULT 0,
        policy_version VARCHAR(20) NOT NULL,
        ip_anonymized VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        created_at DATETIME NOT NULL,
        INDEX idx_consent (consent_id),
        INDEX idx_user_consent (user_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS webhook_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        source VARCHAR(40) NOT NULL,
        event_id VARCHAR(120) NOT NULL,
        event_type VARCHAR(80) NOT NULL,
        payload_json LONGTEXT NULL,
        processed_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        UNIQUE KEY uq_event (source, event_id),
        INDEX idx_processed (processed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS email_verificado TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS fecha_verificacion_email DATETIME NULL",
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS consent_id CHAR(36) NULL",
    "ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS politica_aceptada_version VARCHAR(20) NULL",

    "ALTER TABLE reservas ADD COLUMN IF NOT EXISTS cancelado_por_admin_id INT NULL",
    "ALTER TABLE reservas ADD COLUMN IF NOT EXISTS fecha_cancelacion DATETIME NULL",
    "ALTER TABLE reservas ADD COLUMN IF NOT EXISTS verificado_admin TINYINT(1) DEFAULT 0",
    "ALTER TABLE reservas ADD COLUMN IF NOT EXISTS fecha_verificacion DATETIME NULL",

    // 2FA TOTP para administradores
    "ALTER TABLE usuarios_admin ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(64) NULL",
    "ALTER TABLE usuarios_admin ADD COLUMN IF NOT EXISTS totp_enabled TINYINT(1) NOT NULL DEFAULT 0",
    "ALTER TABLE usuarios_admin ADD COLUMN IF NOT EXISTS totp_recovery_codes TEXT NULL",
];

$ok = 0;
$errors = [];
foreach ($queries as $sql) {
    try {
        $pdo->query($sql);
        echo "[OK] " . substr($sql, 0, 80) . "...\n";
        $ok++;
    } catch (\PDOException $e) {
        $errors[] = $e->getMessage();
        echo "[ERR] " . substr($sql, 0, 80) . "...\n      " . $e->getMessage() . "\n";
    }
}

echo "\n----------------------------------------\n";
echo "Total OK: $ok / " . count($queries) . "\n";
if ($errors) {
    echo "Errores: " . count($errors) . "\n";
}
echo "\nRECUERDA borrar (o restringir vía .htaccess) este archivo en produccion tras ejecutarlo.\n";
