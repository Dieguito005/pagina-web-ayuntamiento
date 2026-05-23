<?php
/**
 * tokens.php — Generacion y validacion de tokens de un solo uso
 * (verificacion de email + password reset).
 *
 * Patron:
 *   - Se genera un token crudo de 32 bytes (256 bits) → 64 hex → 64 chars URL-safe.
 *   - Se guarda en BD SOLO el SHA-256 del token (nunca el crudo).
 *   - El enlace al usuario contiene el token crudo en la URL.
 *   - Para validar: hacer SHA-256 del input y buscar el hash en BD (con expires_at > NOW, used_at IS NULL).
 *
 * Beneficios: si la BD se filtra, los tokens en vuelo no son utilizables.
 */

require_once __DIR__ . '/SecurityHandler.php';

/** Genera un par (token_plano, token_hash). */
function generate_token(): array
{
    $plain = bin2hex(random_bytes(32));
    return ['plain' => $plain, 'hash' => hash('sha256', $plain)];
}

/** Hashea un token tal cual lo recibimos del usuario. */
function hash_token(string $plain): string
{
    return hash('sha256', $plain);
}

/**
 * Crea un token de verificacion de email para $userId, expira en $ttlSeconds.
 * Devuelve el token PLANO (para meter en el enlace del email).
 */
function create_email_verification_token(\PDO $pdo, int $userId, int $ttlSeconds = 86400): string
{
    $t = generate_token();
    $expires = date('Y-m-d H:i:s', time() + $ttlSeconds);
    $stmt = $pdo->prepare(
        "INSERT INTO email_verifications (user_id, token_hash, expires_at, created_at)
         VALUES (?, ?, ?, NOW())"
    );
    $stmt->execute([$userId, $t['hash'], $expires]);
    return $t['plain'];
}

/** Verifica + marca como usado. Devuelve el user_id si OK, null si no. */
function consume_email_verification_token(\PDO $pdo, string $plain): ?int
{
    if (!preg_match('/^[a-f0-9]{64}$/', $plain)) return null;
    $hash = hash_token($plain);
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            "SELECT id, user_id FROM email_verifications
             WHERE token_hash = ? AND verified_at IS NULL AND expires_at > NOW()
             LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->rollBack();
            return null;
        }
        $pdo->prepare("UPDATE email_verifications SET verified_at = NOW() WHERE id = ?")
            ->execute([$row['id']]);
        $pdo->prepare("UPDATE usuarios SET email_verificado = 1, fecha_verificacion_email = NOW() WHERE id = ?")
            ->execute([$row['user_id']]);
        $pdo->commit();
        return (int) $row['user_id'];
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('consume_email_verification: ' . $e->getMessage());
        return null;
    }
}

/**
 * Crea un token de password reset para $userId, expira en 15 min por defecto.
 * Devuelve el token PLANO. Invalida cualquier token anterior no usado del mismo usuario.
 */
function create_password_reset_token(\PDO $pdo, int $userId, int $ttlSeconds = 900): string
{
    try {
        $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")
            ->execute([$userId]);
    } catch (\PDOException $e) {
        error_log('create_password_reset_invalidate: ' . $e->getMessage());
    }
    $t = generate_token();
    $expires = date('Y-m-d H:i:s', time() + $ttlSeconds);
    $stmt = $pdo->prepare(
        "INSERT INTO password_resets (user_id, token_hash, expires_at, ip_anonymized, created_at)
         VALUES (?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$userId, $t['hash'], $expires, SecurityHandler::clientIpAnonymized()]);
    return $t['plain'];
}

/** Comprueba que un token de reset es valido. Devuelve [id, user_id] o null. */
function lookup_password_reset_token(\PDO $pdo, string $plain): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $plain)) return null;
    $hash = hash_token($plain);
    try {
        $stmt = $pdo->prepare(
            "SELECT id, user_id FROM password_resets
             WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1"
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (\PDOException $e) {
        error_log('lookup_password_reset: ' . $e->getMessage());
        return null;
    }
}

/** Marca como usado y actualiza la contraseña dentro de una transaccion. */
function consume_password_reset_token(\PDO $pdo, string $plain, string $newPasswordHash): ?int
{
    if (!preg_match('/^[a-f0-9]{64}$/', $plain)) return null;
    $hash = hash_token($plain);
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            "SELECT id, user_id FROM password_resets
             WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1 FOR UPDATE"
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->rollBack();
            return null;
        }
        $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?")
            ->execute([$row['id']]);
        $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?")
            ->execute([$newPasswordHash, $row['user_id']]);
        $pdo->commit();
        return (int) $row['user_id'];
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('consume_password_reset: ' . $e->getMessage());
        return null;
    }
}
