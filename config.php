<?php
// config.php — Configuración centralizada del proyecto
if (file_exists(__DIR__ . '/env.php')) {
    require_once __DIR__ . '/env.php';
}
// Este archivo detecta el entorno (local vs producción) y carga las variables adecuadas.
// NUNCA subir credenciales de producción a Git.

function get_env_var($key, $default = '') {
    $constant = 'ENV_' . $key;
    if (defined($constant)) return constant($constant);
    $val = getenv($key);
    return $val !== false ? $val : $default;
}

// ──────────────────────────────────────────
// 1. DETECCIÓN DE ENTORNO
// ──────────────────────────────────────────
$_APP_ENV = get_env_var('APP_ENV', 'local');

if ($_APP_ENV === 'local' && isset($_SERVER['HTTP_HOST'])) {
    $host = $_SERVER['HTTP_HOST'];
    if (strpos($host, 'localhost') === false && strpos($host, '127.0.0.1') === false) {
        $_APP_ENV = 'production';
    }
}

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
// Cuando MAINTENANCE_MODE = true, solo pueden acceder quienes tengan la cookie de beta.
// Para entrar al modo beta: visitar cualquier página con ?beta_key=TaranconTest2026
// Eso crea una cookie que permite navegar normalmente.
// En local (XAMPP) el modo mantenimiento está DESACTIVADO siempre.
define('MAINTENANCE_MODE', IS_PRODUCTION ? true : false);
define('MAINTENANCE_BETA_KEY', get_env_var('BETA_KEY', 'TaranconTest2026'));

// ──────────────────────────────────────────
// 6. BREVO (ex-Sendinblue) — Servicio SMTP
// ──────────────────────────────────────────
// Registro gratuito en https://app.brevo.com → 300 emails/día gratis
// Obtener API Key en: Configuración → Claves API → Generar nueva clave
define('BREVO_API_KEY', get_env_var('BREVO_API_KEY', ''));
define('MAIL_FROM_EMAIL', get_env_var('MAIL_FROM_EMAIL', 'reservas@tarancon.es'));
define('MAIL_FROM_NAME', get_env_var('MAIL_FROM_NAME', 'Ayuntamiento de Tarancón - Reservas'));
// Si la API key está vacía, se usa mail() como fallback (solo funciona en local)
define('USE_BREVO', !empty(BREVO_API_KEY));

// ──────────────────────────────────────────
// 7. STRIPE — Pasarela de Pagos
// ──────────────────────────────────────────
// Registro en https://dashboard.stripe.com → Modo Test activado por defecto
// Las claves de TEST empiezan por sk_test_ y pk_test_
// Las claves de PRODUCCIÓN empiezan por sk_live_ y pk_live_
define('STRIPE_SECRET_KEY', get_env_var('STRIPE_SECRET_KEY', ''));
define('STRIPE_PUBLISHABLE_KEY', get_env_var('STRIPE_PUBLISHABLE_KEY', ''));
// Si las claves están vacías, se usa el flujo de pago simulado original
define('USE_STRIPE', !empty(STRIPE_SECRET_KEY) && !empty(STRIPE_PUBLISHABLE_KEY));

// ──────────────────────────────────────────
// 8. CABECERAS DE SEGURIDAD HTTP
// ──────────────────────────────────────────
function send_security_headers()
{
    if (headers_sent())
        return;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    if (IS_PRODUCTION) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// ──────────────────────────────────────────
// 9. MIDDLEWARE DE MANTENIMIENTO
// ──────────────────────────────────────────
// Llamar a check_maintenance() en las páginas públicas (header.php ya lo hace).
// Las APIs y el admin panel NO pasan por esto.
function check_maintenance()
{
    if (!MAINTENANCE_MODE)
        return; // Desactivado

    // Permitir acceso a admin panel siempre
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/admin/') !== false)
        return;
    if (strpos($uri, '/api/') !== false)
        return;

    // Comprobar si el usuario está entrando con la beta key
    if (isset($_GET['beta_key']) && $_GET['beta_key'] === MAINTENANCE_BETA_KEY) {
        setcookie('beta_access', hash('sha256', MAINTENANCE_BETA_KEY . 'salt_tarancon'), time() + 86400 * 30, '/');
        $_COOKIE['beta_access'] = hash('sha256', MAINTENANCE_BETA_KEY . 'salt_tarancon');
        return; // Dejar pasar
    }

    // Comprobar si tiene la cookie de beta
    $expectedHash = hash('sha256', MAINTENANCE_BETA_KEY . 'salt_tarancon');
    if (isset($_COOKIE['beta_access']) && $_COOKIE['beta_access'] === $expectedHash) {
        return; // Tiene acceso beta
    }

    // Mostrar página de mantenimiento
    http_response_code(503);
    header('Retry-After: 3600');
    // Intentar cargar la página de mantenimiento personalizada
    $maintenancePage = __DIR__ . '/html/mantenimiento.html';
    if (file_exists($maintenancePage)) {
        readfile($maintenancePage);
    } else {
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>Mantenimiento - Alquileres Tarancón</title>';
        echo '<style>body{font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#f0f4f8;color:#334155}';
        echo '.card{text-align:center;padding:3rem;max-width:500px;background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.08)}';
        echo 'h1{color:#1e3a5f;font-size:1.5rem}p{color:#64748b}a{color:#d15615}</style></head>';
        echo '<body><div class="card"><h1>🔧 Sitio en mantenimiento</h1>';
        echo '<p>Estamos realizando pruebas internas. El servicio estará disponible próximamente.</p>';
        echo '<p style="font-size:0.85rem;margin-top:2rem">Ayuntamiento de Tarancón</p></div></body></html>';
    }
    exit;
}
