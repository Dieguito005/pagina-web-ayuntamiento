<?php
// env.example.php — Plantilla de variables de entorno.
//
// COPIA este archivo a `env.php` (que esta en .gitignore) y rellena con
// las credenciales reales. NUNCA commitear env.php.
//
// Se usan define() porque putenv() suele estar deshabilitado en hostings.

// ──────────────────────────────────────────
// Entorno
// ──────────────────────────────────────────
// 'production' o 'local'
define('ENV_APP_ENV', 'local');

// ──────────────────────────────────────────
// Base de datos
// ──────────────────────────────────────────
define('ENV_DB_HOST', 'localhost');
define('ENV_DB_NAME', 'ayuntamiento_reservas');
define('ENV_DB_USER', 'root');
define('ENV_DB_PASS', '');

// ──────────────────────────────────────────
// Email — Resend (https://resend.com)
// ──────────────────────────────────────────
// Crear cuenta gratis. 3000 emails/mes incluidos. Jurisdiccion UE disponible.
// Crear API key en https://resend.com/api-keys → empieza por re_*
define('ENV_RESEND_API_KEY', '');

// Direccion remitente (debe ser un dominio verificado en Resend con SPF+DKIM+DMARC)
define('ENV_MAIL_FROM_EMAIL', 'reservas@tarancon.es');
define('ENV_MAIL_FROM_NAME', 'Ayuntamiento de Tarancon - Reservas');

// Override de destino para desarrollo: TODOS los emails se redirigen a esta
// direccion (con un banner que indica el destinatario real).
// IMPORTANTE: dejar VACIO en produccion para que los emails lleguen al ciudadano.
define('ENV_MAIL_OVERRIDE_TO', 'tu-correo-personal@gmail.com');

// ──────────────────────────────────────────
// Stripe
// ──────────────────────────────────────────
// TEST: sk_test_... / pk_test_...   |   LIVE: sk_live_... / pk_live_...
// IMPORTANTE: la cuenta Stripe debe estar a nombre del AYUNTAMIENTO,
// no del subcontratista. El IBAN destinatario debe ser el del consistorio.
define('ENV_STRIPE_SECRET_KEY', '');
define('ENV_STRIPE_PUBLISHABLE_KEY', '');

// Webhook secret: obtenerlo desde Stripe Dashboard > Webhooks tras crear
// el endpoint https://reservas.tarancon.es/api/stripe-webhook.php
define('ENV_STRIPE_WEBHOOK_SECRET', '');

// ──────────────────────────────────────────
// Modo Mantenimiento
// ──────────────────────────────────────────
// Cuando MAINTENANCE_MODE = true en config.php (production), solo entran
// quienes accedan con ?beta_key=XXX y reciban la cookie firmada con SALT.
// La SALT debe ser cadena aleatoria de >=32 caracteres y mantenerse en secreto.
define('ENV_BETA_KEY', '');
define('ENV_BETA_SALT', ''); // generar con: php -r "echo bin2hex(random_bytes(32));"

// ──────────────────────────────────────────
// Setup DB Key (para ejecutar api/setup-security-db.php sin ser admin)
// ──────────────────────────────────────────
// Generar con: php -r "echo bin2hex(random_bytes(32));"
define('ENV_SETUP_DB_KEY', '');

// ──────────────────────────────────────────
// URL canonica del sitio (opcional pero recomendada)
// ──────────────────────────────────────────
// Si se rellena, se usa para construir success_url/cancel_url de Stripe
// en lugar de HTTP_HOST (cabecera falsificable).
define('ENV_SITE_URL', 'https://reservas.tarancon.es');
