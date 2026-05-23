<div align="center">
  <img src="assets/imagenes/LOGO-AYUNTAMIENTO-BLANCO.png" alt="Logo Ayuntamiento Tarancón" width="180" style="background-color: #0b1c3c; padding: 20px; border-radius: 10px;"/>
  <h1>Alquileres Deportivos — Ayuntamiento de Tarancón</h1>
  <p><strong>Plataforma integral para la reserva y gestión de instalaciones deportivas municipales.</strong></p>
  <p>
    <a href="docs/CHANGELOG.md">Changelog</a> ·
    <a href="PLAN.md">Plan</a> ·
    <a href="docs/RUNBOOK.md">Runbook</a> ·
    <a href="SECURITY.md">Seguridad</a> ·
    <a href="CLAUDE.md">Guía para Claude</a>
  </p>
  <p>
    <img alt="Version" src="https://img.shields.io/badge/version-3.0.0-blue">
    <img alt="Stack" src="https://img.shields.io/badge/stack-PHP%208%20%2B%20MySQL%20%2B%20JS%20vanilla-orange">
    <img alt="RGPD" src="https://img.shields.io/badge/RGPD-conforme%20(en%20revisi%C3%B3n)-green">
    <img alt="ENS" src="https://img.shields.io/badge/ENS-pendiente%20de%20hosting-yellow">
  </p>
</div>

---

## ¿Qué es este proyecto?

Portal web del **Ayuntamiento de Tarancón (Cuenca)** para la reserva y pago de instalaciones deportivas municipales: pistas de **tenis**, **pádel** (cubierto y descubierto), **fútbol 7**, y un sistema de **bonos** de sesiones prepagadas.

Doble interfaz:

| Cara pública | Cara administrativa |
|---|---|
| Reserva 30 días vista | Calendario y bloqueo de pistas |
| Pago seguro vía Stripe Checkout | Edición de precios y suplemento de luz |
| Cuenta de usuario, perfil y bonos | Buzón de consultas |
| Formulario de contacto | Anuncios y novedades |
| Email transaccional con código de acceso | Gestión de bonos vendidos |
| Página de mantenimiento + acceso por *beta key* | Centro de actividad / verificación de reservas por conserje |

---

## Estado actual

- **Versión:** `v3.0.0` (2026-05-23) — pase mayor de seguridad y cumplimiento.
- **Entorno productivo:** alojado provisionalmente en InfinityFree (`alquileres-deportivos-tarancon.infinityfree.me`) tras *beta key*.
- **Email:** Resend (jurisdicción UE disponible), con override de destinatario para desarrollo.
- **Pagos:** Stripe en modo **TEST** (claves `sk_test_*`). El cambio a LIVE depende de decisión del Ayuntamiento.
- **Conformidad legal:** textos legales y mecanismo de cookies adecuados a RGPD / LSSI / RD 1112/2018; pendientes acciones administrativas (DPD, DPA con encargados, ordenanza fiscal, subdominio institucional, hosting con certificación ENS).

> Hay un listado priorizado de tareas pendientes y futuras en **[PLAN.md](PLAN.md)**.

---

## Stack tecnológico

- **Backend:** PHP 8+ sin framework. PDO con sentencias preparadas. Estructura por archivos en `api/`.
- **Frontend:** HTML5 semántico, CSS3 puro, JavaScript vanilla. Componentes reutilizables vía `include` PHP (`includes/header.php`, `includes/footer.php`).
- **BD:** MySQL (charset `utf8mb4`).
- **Pagos:** Stripe Checkout + webhook firmado (`api/stripe-webhook.php`).
- **Email:** Resend API con plantillas HTML sanitizadas.
- **Sesiones:** PHP nativas (`HttpOnly`, `SameSite=Lax`, `Secure` en producción, regeneración tras login, timeout 30 min de inactividad).
- **Seguridad transversal:** `includes/SecurityHandler.php` (CSRF, rate limit IP, audit log).

---

## Arquitectura de directorios

```
.
├── admin/                Panel de administración (login + dashboard)
├── api/                  Endpoints REST PHP
│   ├── stripe-webhook.php       ← fuente de verdad para reservas/bonos pagadas
│   ├── setup-security-db.php    ← inicializa tablas auxiliares
│   └── cookie-consent.php       ← registra consentimiento server-side
├── assets/               Imágenes, iconos, logos
├── css/                  Hojas de estilo
├── docs/
│   ├── CHANGELOG.md             ← histórico completo (Keep a Changelog)
│   ├── INSTALL.md               ← instalación
│   ├── RUNBOOK.md               ← operaciones (rotar credenciales, deploy, backup)
│   ├── RAT.md                   ← Registro de Actividades de Tratamiento (RGPD art. 30)
│   └── INCIDENT-RESPONSE.md     ← protocolo notificación brecha AEPD 72h
├── html/                 Páginas públicas y legales
├── includes/
│   ├── SecurityHandler.php      ← middleware CSRF/rate/sesión
│   ├── audit.php                ← log de auditoría
│   ├── helpers.php              ← esc_html, csrf_input, etc.
│   ├── header.php  + footer.php
│   └── user-auth-check.php
├── js/                   Scripts del cliente
├── config.php            Configuración centralizada (no contiene secretos)
├── env.php               Secretos — EN .gitignore, NUNCA commit
├── env.example.php       Plantilla documentada de env.php
├── .htaccess             Hardening Apache (HTTPS forzado, bloqueos, headers)
├── README.md             ← este archivo
├── PLAN.md               ← roadmap priorizado
├── CLAUDE.md             ← instrucciones para Claude Code en futuras sesiones
└── SECURITY.md           ← política de reporte de vulnerabilidades
```

---

## Instalación y puesta en marcha

Resumen rápido (detalle en **[docs/INSTALL.md](docs/INSTALL.md)**):

```bash
# 1. Clonar
git clone <repo> pagina-web-ayuntamiento
cd pagina-web-ayuntamiento

# 2. Copiar plantilla de secretos y editar con valores reales
cp env.example.php env.php
# Editar env.php: DB, Resend, Stripe, BETA_SALT, SETUP_DB_KEY, SITE_URL

# 3. Arrancar XAMPP/MAMP/Apache+MySQL local
# Crear BD: ayuntamiento_reservas (utf8mb4)

# 4. Inicializar tablas (la primera vez)
#   Tablas base de negocio:
curl http://localhost/api/setup-bonos-db.php       # tras loguearse como admin
curl http://localhost/api/setup-horarios-db.php
curl http://localhost/api/setup-precios-db.php?key=<SETUP_DB_KEY>
#   Tablas de seguridad/auditoría/RGPD (v3.0.0+):
curl http://localhost/api/setup-security-db.php?key=<SETUP_DB_KEY>
```

---

## Comandos útiles

```bash
# Verificar sintaxis PHP de todos los archivos
find . -name "*.php" -not -path "./vendor/*" -exec php -l {} \; 2>&1 | grep -v "No syntax errors"

# Deploy a InfinityFree (FTP — pendiente migrar a GitHub Actions)
php deploy_ftp.php           # local-only, env.php necesario

# Generar secretos
php -r "echo bin2hex(random_bytes(32));"
```

---

## Mantenimiento de la documentación

Para mantener todo coherente, **cada cambio sustancial debe actualizar**:

1. **`docs/CHANGELOG.md`** — entrada nueva con fecha (Keep a Changelog).
2. **`PLAN.md`** — marcar tareas completadas y replanificar pendientes.
3. **`README.md`** — sólo si cambia el estado general (versión, stack, estado de pago, etc.).
4. **`CLAUDE.md`** — actualizar cuando aparece un patrón nuevo que debe respetarse en futuro.

> Ver `CLAUDE.md` para la regla automática que aplica Claude tras cada cambio significativo.

---

## Licencia y titularidad

Código en desarrollo bajo encargo del **Excelentísimo Ayuntamiento de Tarancón** (NIF P1621500F). Los derechos de explotación se cederán al Ayuntamiento mediante contrato.

---

*Desarrollado para los ciudadanos de Tarancón.*
