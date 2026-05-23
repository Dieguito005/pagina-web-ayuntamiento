<?php
/**
 * SecurityHandler.php — Middleware central de seguridad.
 *
 * Cubre:
 *  - CSRF (generación, validación, helpers que abortan la petición)
 *  - Autorización (require_user, require_admin con respuesta JSON o redirect)
 *  - Rate limit por IP (login, formularios sensibles)
 *  - Sanitización de redirects (whitelist)
 *  - Cabeceras de seguridad para respuestas JSON
 *  - Helpers para devolver errores JSON sin filtrar internals
 *
 * Pensado para ser incluido al principio de cualquier endpoint de mutación.
 */

require_once __DIR__ . '/../config.php';

class SecurityHandler
{
    /** Páginas internas permitidas como destino de redirect post-login. */
    private const REDIRECT_WHITELIST = [
        'home.php',
        'perfil.php',
        'checkout.php',
        'bonos.php',
        'tenis.php',
        'padelCubierta.php',
        'padelDescubierta.php',
        'futbol7.php',
        'contacto.php',
        'pago-exitoso.php',
        'bono-exitoso.php',
        'user-login.php',
        'user-registro.php',
        'politica-privacidad.php',
        'politica-cookies.php',
        'aviso-legal.php',
        'accesibilidad.php',
    ];

    // ──────────────────────────────────────────
    // CSRF
    // ──────────────────────────────────────────

    /** Genera el token CSRF de la sesión o devuelve el existente. */
    public static function generateCSRFToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** Devuelve true si el token coincide; usa comparación de tiempo constante. */
    public static function validateCSRFToken(?string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['csrf_token']) || !is_string($token) || $token === '') return false;
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Aborta la petición con 403 JSON si no hay token válido en la cabecera
     * X-CSRF-Token o en el campo POST/JSON `csrf_token`.
     * Solo activo en peticiones de mutación (POST/PUT/DELETE/PATCH).
     */
    public static function requireCSRF(?array $jsonBody = null): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) return;

        $token = $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? ($_POST['csrf_token'] ?? null)
            ?? ($jsonBody['csrf_token'] ?? null);

        if (!self::validateCSRFToken($token)) {
            self::jsonError(403, 'Token de seguridad inválido o caducado. Recarga la página e inténtalo de nuevo.');
        }
    }

    /** Como requireCSRF pero redirige a una URL si falla (para flujos no-AJAX). */
    public static function requireCSRFOrRedirect(string $redirectUrl): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) return;
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? null);
        if (!self::validateCSRFToken($token)) {
            header('Location: ' . $redirectUrl);
            exit;
        }
    }

    // ──────────────────────────────────────────
    // Autorización
    // ──────────────────────────────────────────

    /** Aborta con 401 si no hay user_id en sesión. */
    public static function requireUser(): int
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['user_id'])) {
            self::jsonError(401, 'Debes iniciar sesión para realizar esta acción.');
        }
        return (int) $_SESSION['user_id'];
    }

    /** Aborta con 403 si no hay admin_id en sesión. */
    public static function requireAdmin(): int
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['admin_id'])) {
            self::jsonError(403, 'Acceso restringido al panel de administración.');
        }
        return (int) $_SESSION['admin_id'];
    }

    // ──────────────────────────────────────────
    // Sesión
    // ──────────────────────────────────────────

    /**
     * Regenera el ID de sesión preservando los datos (anti-fixation).
     * Llamar SIEMPRE después de un login exitoso o cambio de privilegio.
     */
    public static function regenerateSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        session_regenerate_id(true);
        $_SESSION['_last_regen'] = time();
    }

    /**
     * Comprueba inactividad y caduca la sesión si han pasado más de
     * $maxIdleSeconds segundos desde la última actividad.
     */
    public static function enforceSessionTimeout(int $maxIdleSeconds = 1800): bool
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $now = time();
        $last = $_SESSION['_last_activity'] ?? null;
        if ($last !== null && ($now - (int) $last) > $maxIdleSeconds) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', $now - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            return false; // sesión caducada
        }
        $_SESSION['_last_activity'] = $now;
        return true;
    }

    // ──────────────────────────────────────────
    // Redirects seguros
    // ──────────────────────────────────────────

    /**
     * Sanitiza un redirect post-login. Devuelve una ruta segura siempre interna.
     * @param string|null $candidate destino solicitado por el cliente
     * @param string      $defaultPath path por defecto si el candidato no es seguro
     */
    public static function sanitizeRedirect(?string $candidate, string $defaultPath = '../html/home.php'): string
    {
        if (!is_string($candidate) || $candidate === '') return $defaultPath;
        // Rechazar URLs absolutas o con esquema
        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*:#', $candidate)) return $defaultPath;
        if (str_starts_with($candidate, '//')) return $defaultPath;
        if (str_starts_with($candidate, '\\\\')) return $defaultPath;
        // Decodificar y rechazar travesía explícita
        $decoded = rawurldecode($candidate);
        if (str_contains($decoded, '..')) return $defaultPath;
        if (str_contains($decoded, "\0")) return $defaultPath;
        $base = basename($decoded);
        if ($base === '' || !in_array($base, self::REDIRECT_WHITELIST, true)) return $defaultPath;
        return '../html/' . $base;
    }

    // ──────────────────────────────────────────
    // Rate limit por IP (login y formularios sensibles)
    // ──────────────────────────────────────────

    /**
     * Comprueba si una IP está actualmente bloqueada por exceso de intentos.
     * Requiere tabla login_attempts (ver api/setup-security-db.php).
     */
    public static function isRateLimited(\PDO $pdo, string $action, string $identifier, int $maxAttempts = 5, int $windowSeconds = 900): bool
    {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) AS n FROM login_attempts WHERE action = ? AND identifier = ? AND created_at > (NOW() - INTERVAL ? SECOND)");
            $stmt->execute([$action, $identifier, $windowSeconds]);
            $n = (int) ($stmt->fetch()['n'] ?? 0);
            return $n >= $maxAttempts;
        } catch (\PDOException $e) {
            // Si la tabla no existe aún, no bloqueamos (fail-open en bootstrap)
            error_log('rate_limit_check_error: ' . $e->getMessage());
            return false;
        }
    }

    public static function recordAttempt(\PDO $pdo, string $action, string $identifier, bool $success): void
    {
        try {
            $stmt = $pdo->prepare("INSERT INTO login_attempts (action, identifier, success, ip, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $action,
                $identifier,
                $success ? 1 : 0,
                self::clientIp(),
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]);
        } catch (\PDOException $e) {
            error_log('rate_limit_record_error: ' . $e->getMessage());
        }
    }

    public static function clearAttempts(\PDO $pdo, string $action, string $identifier): void
    {
        try {
            $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE action = ? AND identifier = ? AND success = 0");
            $stmt->execute([$action, $identifier]);
        } catch (\PDOException $e) {
            // ignorar
        }
    }

    /** Devuelve la IP del cliente confiando solo en REMOTE_ADDR (cabeceras X-Forwarded-For son falsificables sin proxy de confianza). */
    public static function clientIp(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /** IP truncada (anonimizada) para almacenar en logs sin retener PII bruta. */
    public static function clientIpAnonymized(): string
    {
        $ip = self::clientIp();
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0';
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            return implode(':', array_slice($parts, 0, 4)) . '::';
        }
        return '0.0.0.0';
    }

    // ──────────────────────────────────────────
    // Respuestas JSON
    // ──────────────────────────────────────────

    /** Devuelve un error JSON estandarizado y termina la ejecución. No filtra internals. */
    public static function jsonError(int $code, string $message): void
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=UTF-8');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-store');
        }
        echo json_encode(['status' => 'error', 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function jsonOk(array $payload = []): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-store');
        }
        echo json_encode(array_merge(['status' => 'success'], $payload), JSON_UNESCAPED_UNICODE);
        exit;
    }
}
