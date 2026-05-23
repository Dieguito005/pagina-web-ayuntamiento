<?php
// config.php — Configuración centralizada del proyecto
if (file_exists(__DIR__ . '/env.php')) {
    require_once __DIR__ . '/env.php';
}
// Detecta entorno y carga variables.
// NUNCA subir credenciales de producción a Git (env.php está en .gitignore).

function get_env_var($key, $default = '')
{
    $constant = 'ENV_' . $key;
    if (defined($constant)) return constant($constant);
    $val = getenv($key);
    return $val !== false ? $val : $default;
}

// ──────────────────────────────────────────
// 1. DETECCIÓN DE ENTORNO
// ──────────────────────────────────────────
// APP_ENV=local en desarrollo (XAMPP). Default 'production' por seguridad.
// NUNCA usar HTTP_HOST para detectar entorno (cabecera falsificable).
$_APP_ENV = get_env_var('APP_ENV', 'production');
define('APP_ENV', $_APP_ENV);
define('IS_PRODUCTION', APP_ENV === 'production');

// ──────────────────────────────────────────
// 2. REPORTING DE ERRORES
// ──────────────────────────────────────────
if (IS_PRODUCTION) {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// ──────────────────────────────────────────
// 3. CONFIGURACIÓN DE SESIONES (Seguridad)
// ──────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', '1800'); // 30 min
    if (IS_PRODUCTION) {
        ini_set('session.cookie_secure', '1');
    }
}

// ──────────────────────────────────────────
// 4. CREDENCIALES DE BASE DE DATOS
// ──────────────────────────────────────────
define('DB_HOST', IS_PRODUCTION ? get_env_var('DB_HOST', '127.0.0.1') : '127.0.0.1');
define('DB_NAME', IS_PRODUCTION ? get_env_var('DB_NAME', 'ayuntamiento_reservas') : 'ayuntamiento_reservas');
define('DB_USER', IS_PRODUCTION ? get_env_var('DB_USER', 'root') : 'root');
define('DB_PASS', IS_PRODUCTION ? get_env_var('DB_PASS', '') : '');
define('DB_CHARSET', 'utf8mb4');

// ──────────────────────────────────────────
// 5. MODO MANTENIMIENTO / BETA INTERNA
// ──────────────────────────────────────────
// Cuando MAINTENANCE_MODE = true, sólo pasan quienes tengan la cookie de beta.
// La beta key se configura en env.php mediante ENV_BETA_KEY.
// En local (XAMPP) el modo mantenimiento está DESACTIVADO siempre.
define('MAINTENANCE_MODE', IS_PRODUCTION ? true : false);
define('MAINTENANCE_BETA_KEY', get_env_var('BETA_KEY', ''));
// Salt para el hash del cookie beta — debe configurarse en env.php
define('MAINTENANCE_BETA_SALT', get_env_var('BETA_SALT', ''));

// ──────────────────────────────────────────
// 6. EMAIL — Resend (sustituye a Brevo)
// ──────────────────────────────────────────
// Registro gratuito en https://resend.com — 3000 emails/mes, jurisdicción UE disponible.
// Obtener API key en https://resend.com/api-keys
define('RESEND_API_KEY', get_env_var('RESEND_API_KEY', ''));
define('MAIL_FROM_EMAIL', get_env_var('MAIL_FROM_EMAIL', 'reservas@tarancon.es'));
define('MAIL_FROM_NAME', get_env_var('MAIL_FROM_NAME', 'Ayuntamiento de Tarancón - Reservas'));
// Override de destinatario durante desarrollo: TODOS los emails se envían aquí.
// Dejar vacío en producción real.
define('MAIL_OVERRIDE_TO', get_env_var('MAIL_OVERRIDE_TO', ''));
define('USE_RESEND', !empty(RESEND_API_KEY));

// ──────────────────────────────────────────
// 7. STRIPE — Pasarela de Pagos
// ──────────────────────────────────────────
// Test: sk_test_*, pk_test_*  | Producción: sk_live_*, pk_live_*
define('STRIPE_SECRET_KEY', get_env_var('STRIPE_SECRET_KEY', ''));
define('STRIPE_PUBLISHABLE_KEY', get_env_var('STRIPE_PUBLISHABLE_KEY', ''));
define('STRIPE_WEBHOOK_SECRET', get_env_var('STRIPE_WEBHOOK_SECRET', ''));
define('USE_STRIPE', !empty(STRIPE_SECRET_KEY) && !empty(STRIPE_PUBLISHABLE_KEY));

// Guarda contra usar claves de TEST en producción
if (IS_PRODUCTION && USE_STRIPE && str_starts_with(STRIPE_SECRET_KEY, 'sk_test_')) {
    error_log('AVISO: STRIPE_SECRET_KEY de TEST configurada en entorno de PRODUCCIÓN — los pagos no son reales.');
}

// ──────────────────────────────────────────
// 8. SETUP / DEPLOY KEYS
// ──────────────────────────────────────────
// Clave para ejecutar api/setup-*.php sin estar logueado como admin.
// Configurar en env.php. Si está vacío, sólo admins pueden ejecutar setup.
define('SETUP_DB_KEY', get_env_var('SETUP_DB_KEY', ''));

// ──────────────────────────────────────────
// 9. POLÍTICAS LEGALES — VERSIONADO
// ──────────────────────────────────────────
// Si cambias texto importante de la política, sube la versión para forzar
// renovación de consentimientos cuando convenga.
define('POLICY_VERSION', '2026-05-23');

// URL canónica del sitio (evita usar HTTP_HOST en redirects sensibles)
define('SITE_URL', get_env_var('SITE_URL', ''));

// ──────────────────────────────────────────
// 10. CABECERAS DE SEGURIDAD HTTP
// ──────────────────────────────────────────
function send_security_headers()
{
    if (headers_sent()) return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(self "https://js.stripe.com"), interest-cohort=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header(
        "Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' 'unsafe-inline' https://js.stripe.com https://cdn.jsdelivr.net; " .
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
        "font-src 'self' https://fonts.gstatic.com; " .
        "img-src 'self' data: https://quickchart.io; " .
        "connect-src 'self' https://api.stripe.com; " .
        "frame-src https://js.stripe.com; " .
        "frame-ancestors 'self'; " .
        "form-action 'self'; " .
        "base-uri 'self'; " .
        "object-src 'none'; " .
        "upgrade-insecure-requests"
    );
    if (IS_PRODUCTION) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}

// ──────────────────────────────────────────
// 11. MIDDLEWARE DE MANTENIMIENTO
// ──────────────────────────────────────────
function check_maintenance()
{
    if (!MAINTENANCE_MODE) return;

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/admin/') !== false) return;
    if (strpos($uri, '/api/') !== false) return;

    $expectedHash = MAINTENANCE_BETA_KEY === '' || MAINTENANCE_BETA_SALT === ''
        ? null
        : hash('sha256', MAINTENANCE_BETA_KEY . '|' . MAINTENANCE_BETA_SALT);

    // Permitir entrada con la beta key
    if ($expectedHash !== null && isset($_GET['beta_key']) && hash_equals(MAINTENANCE_BETA_KEY, (string) $_GET['beta_key'])) {
        // Cookie con flags seguros y duración 30 días
        $cookieParams = [
            'expires'  => time() + 86400 * 30,
            'path'     => '/',
            'secure'   => IS_PRODUCTION,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        setcookie('beta_access', $expectedHash, $cookieParams);
        $_COOKIE['beta_access'] = $expectedHash;
        return;
    }

    if ($expectedHash !== null && isset($_COOKIE['beta_access']) && hash_equals($expectedHash, (string) $_COOKIE['beta_access'])) {
        return;
    }

    http_response_code(503);
    header('Retry-After: 3600');
    $maintenancePage = __DIR__ . '/html/mantenimiento.html';
    if (file_exists($maintenancePage)) {
        readfile($maintenancePage);
    } else {
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>Mantenimiento - Alquileres Tarancón</title>';
        echo '<style>body{font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f0f4f8;color:#334155}';
        echo '.card{text-align:center;padding:3rem;max-width:500px;background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08)}';
        echo 'h1{color:#1e3a5f;font-size:1.5rem}p{color:#64748b}a{color:#d15615}</style></head>';
        echo '<body><div class="card"><h1>Sitio en mantenimiento</h1>';
        echo '<p>Estamos realizando pruebas internas. El servicio estará disponible próximamente.</p>';
        echo '<p style="font-size:0.85rem;margin-top:2rem">Ayuntamiento de Tarancón</p></div></body></html>';
    }
    exit;
}
