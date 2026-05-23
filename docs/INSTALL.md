# Manual de Instalación

Guía paso a paso para levantar el portal **Alquileres Tarancón** en un entorno local de desarrollo.

> Para operaciones (rotar credenciales, deploy, backup) ver [`RUNBOOK.md`](RUNBOOK.md).
> Para la hoja de ruta vigente ver [`../PLAN.md`](../PLAN.md).

---

## 1. Requisitos previos

- **PHP 8.0+** con extensiones `pdo_mysql`, `curl`, `mbstring`, `openssl`, `session`.
- **MySQL 8.0** o **MariaDB 10.5+** con soporte `utf8mb4`.
- **Apache 2.4+** con `mod_rewrite`, `mod_headers`, `mod_expires`, `mod_deflate`, `mod_authz_core`.
- Editor de código (VS Code recomendado).
- Cuenta en [Resend](https://resend.com) (gratuita: 3000 emails/mes).
- Cuenta en [Stripe](https://dashboard.stripe.com) en modo TEST.

**Recomendado para Windows:** [XAMPP](https://www.apachefriends.org/es/index.html). En macOS/Linux también vale MAMP, Laragon o un stack manual.

---

## 2. Clonar el repositorio

```bash
# Dentro de la carpeta htdocs de XAMPP (Windows) o /var/www/html (Linux)
git clone <url-del-repo> pagina-web-ayuntamiento
cd pagina-web-ayuntamiento
```

---

## 3. Configurar variables de entorno

```bash
# Copiar la plantilla y editar con valores reales
cp env.example.php env.php
```

Edita `env.php` con tus valores. **Nunca commitees este archivo** (ya está en `.gitignore`).

Valores mínimos para desarrollo local:

```php
define('ENV_APP_ENV', 'local');
define('ENV_DB_HOST', 'localhost');
define('ENV_DB_NAME', 'ayuntamiento_reservas');
define('ENV_DB_USER', 'root');
define('ENV_DB_PASS', '');                         // XAMPP por defecto vacío

// Resend (crear API key en https://resend.com/api-keys)
define('ENV_RESEND_API_KEY', 're_xxxxxxxxxxxxxxxx');
define('ENV_MAIL_FROM_EMAIL', 'onboarding@resend.dev');  // dominio sandbox para desarrollo
define('ENV_MAIL_FROM_NAME', 'Ayuntamiento de Tarancon - Dev');

// Durante desarrollo, TODOS los emails se redirigen a tu correo personal.
// Borrar en producción.
define('ENV_MAIL_OVERRIDE_TO', 'tu-correo@gmail.com');

// Stripe en modo TEST
define('ENV_STRIPE_SECRET_KEY', 'sk_test_xxxxxxxxxxxxxxxxxxxxx');
define('ENV_STRIPE_PUBLISHABLE_KEY', 'pk_test_xxxxxxxxxxxxxxxxxxxxx');
define('ENV_STRIPE_WEBHOOK_SECRET', '');  // opcional en local

// Secretos generados (ejecutar el comando bajo cada uno)
define('ENV_BETA_KEY', '');     // php -r "echo bin2hex(random_bytes(16));"
define('ENV_BETA_SALT', '');    // php -r "echo bin2hex(random_bytes(32));"
define('ENV_SETUP_DB_KEY', ''); // php -r "echo bin2hex(random_bytes(32));"

// URL canónica (en local opcional)
define('ENV_SITE_URL', 'http://localhost/pagina-web-ayuntamiento');
```

---

## 4. Crear la base de datos

```sql
-- Desde phpMyAdmin (http://localhost/phpmyadmin) o mysql CLI
CREATE DATABASE ayuntamiento_reservas
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

---

## 5. Iniciar Apache y MySQL

Abre el panel XAMPP → **Start** en Apache y MySQL.

Confirma que se ven en verde.

---

## 6. Inicializar tablas

Visita los siguientes scripts **una sola vez** desde el navegador (las tablas son idempotentes):

```
http://localhost/pagina-web-ayuntamiento/api/setup-bonos-db.php
http://localhost/pagina-web-ayuntamiento/api/setup-horarios-db.php
http://localhost/pagina-web-ayuntamiento/api/setup-precios-db.php?key=<SETUP_DB_KEY>
http://localhost/pagina-web-ayuntamiento/api/setup-security-db.php?key=<SETUP_DB_KEY>
```

> Los dos primeros requieren admin logueado. El tercero y cuarto aceptan también el `SETUP_DB_KEY` por query string.

**Sustituye `<SETUP_DB_KEY>` por el valor real** de `ENV_SETUP_DB_KEY` en `env.php`.

---

## 7. Crear el primer administrador

Como aún no hay administradores, créalo manualmente:

```bash
# Generar un hash bcrypt para la contraseña deseada (ej: AdminLocal2026!)
/c/xampp/php/php.exe -r "echo password_hash('AdminLocal2026!', PASSWORD_DEFAULT) . PHP_EOL;"
```

Copia el hash y ejecuta en MySQL:

```sql
INSERT INTO usuarios_admin (usuario, password_hash, rol)
VALUES ('admin_local', '<el-hash-bcrypt>', 'admin');
```

Después accede en:

```
http://localhost/pagina-web-ayuntamiento/admin/admin-login.php
```

Crear administradores adicionales desde el panel → "Gestión de Admins".

---

## 8. Acceder al portal

```
http://localhost/pagina-web-ayuntamiento/
```

Redirige automáticamente a `html/home.php`.

---

## 9. Comprobaciones rápidas

| Comprobación | Resultado esperado |
|---|---|
| `http://localhost/pagina-web-ayuntamiento/` | Página de inicio con tarjetas de Pádel/Tenis/Bonos/Fútbol 7 |
| Banner de cookies aparece la primera vez | Sí (3 botones: Aceptar / Rechazar / Configurar) |
| Registrar usuario nuevo | Checkbox "He leído la Política de Privacidad" obligatorio |
| Crear reserva → "Pagar con Stripe" | Redirige a `checkout.stripe.com` (modo test) |
| Tras pago test (`4242 4242 4242 4242`) | Vuelve a `pago-exitoso.php` con código de acceso |
| Email del código | Llega a `MAIL_OVERRIDE_TO` con banner indicando destinatario real |
| Admin login con `admin_local` | Llega al dashboard |

---

## 10. Estructura de directorios

```
pagina-web-ayuntamiento/
├── admin/                Panel de administración
├── api/                  Endpoints REST (.php)
├── assets/               Imágenes, iconos, logos
├── css/                  Hojas de estilo
├── docs/                 Documentación (este archivo)
├── html/                 Páginas públicas y legales
├── includes/             header, footer, SecurityHandler, audit, helpers
├── js/                   Scripts del cliente
├── config.php            Configuración (no contiene secretos)
├── env.php               Secretos (gitignored)
├── env.example.php       Plantilla documentada
├── .htaccess             Hardening Apache
├── README.md             Visión general del proyecto
├── PLAN.md               Hoja de ruta priorizada
├── CLAUDE.md             Instrucciones para Claude Code
└── SECURITY.md           Política de reporte de vulnerabilidades
```

---

## 11. Problemas comunes

### "Acceso denegado" al ejecutar `api/setup-security-db.php`
- Comprueba que `ENV_SETUP_DB_KEY` está definido en `env.php` y coincide con el `?key=` que pasas.
- O bien, loguéate como admin antes de visitarlo.

### Emails no llegan
- Verifica `ENV_RESEND_API_KEY` (debe empezar por `re_`).
- Mira `error_log` de PHP (`/c/xampp/php/logs/php_error_log` o equivalente).
- En producción real, el dominio de `MAIL_FROM_EMAIL` debe estar verificado en Resend con SPF/DKIM/DMARC.

### "Token de seguridad inválido"
- La sesión PHP ha caducado o el `<meta csrf-token>` no se está cargando. Recarga la página.
- En el panel admin, todas las peticiones AJAX deben incluir cabecera `X-CSRF-Token`.

### `htaccess` no se aplica
- Comprueba que `AllowOverride All` está en la config de Apache para tu directorio.
- En XAMPP esto suele estar por defecto, pero confirma editando `httpd.conf`.

---

## 12. Próximos pasos

Para llevar este entorno a producción, ver:

- [`PLAN.md`](../PLAN.md) — tareas administrativas y técnicas pendientes (P0–P3).
- [`RUNBOOK.md`](RUNBOOK.md) — procedimientos de operación (rotar credenciales, deploy, backup).
- [`RAT.md`](RAT.md) — Registro de Actividades de Tratamiento (RGPD art. 30).
- [`INCIDENT-RESPONSE.md`](INCIDENT-RESPONSE.md) — protocolo 72 h ante brechas.

---

Última actualización: 2026-05-23 (v3.0.0).
