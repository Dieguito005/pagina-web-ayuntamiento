# PLAN — Hoja de ruta del proyecto

> Documento vivo. Última actualización: **2026-05-24** (tras v3.1.0).
> Marcar `[x]` al completar; mover a "Hecho recientemente" al cerrar release.

Convenciones de prioridad:
- 🔴 **P0** — bloqueante para producción / contratación con el Ayuntamiento.
- 🟠 **P1** — necesario antes de presentar / Go-live público.
- 🟡 **P2** — mejora importante a corto plazo.
- 🟢 **P3** — *nice to have* / futuro.

---

## 🚨 Acciones administrativas / no técnicas

> Estas no son código — dependen de gestiones externas del usuario o del Ayuntamiento.

- [ ] 🔴 **Rotar credenciales** comprometidas tras pase de seguridad:
  - [ ] Password MySQL en panel de InfinityFree → actualizar `env.php`.
  - [ ] Revocar API key Brevo (ya no se usa).
  - [ ] Crear nueva clave Resend en panel y poner en `env.php`.
  - [ ] Regenerar `BETA_SALT` y `SETUP_DB_KEY` con `php -r "echo bin2hex(random_bytes(32));"`.
- [ ] 🔴 **Decidir hosting de producción** (InfinityFree es inviable para AAPP). Opciones recomendadas:
  - Acens (Telefónica Tech) con declaración ENS, ~70 €/mes
  - Arsys Cloud VPS con ENS, ~60-90 €/mes
  - Aiven MySQL (UE) + hosting Acens, alternativa serverless
- [ ] 🔴 **Solicitar al Ayuntamiento subdominio institucional** `reservas.tarancon.es` (instancia al Concejal de Deportes + Servicios Informáticos).
- [ ] 🔴 **Firma del DPA con el Ayuntamiento** (Acuerdo de Encargo del Tratamiento, RGPD art. 28). Plantilla AEPD adaptable.
- [ ] 🔴 **Identificar al DPD del Ayuntamiento** (LOPDGDD art. 34) y sustituir los `[REVISAR DPO]` en políticas legales con datos reales (`dpd@tarancon.es` o equivalente).
- [ ] 🔴 **Decidir pasarela de pagos final**:
  - Stripe LIVE con cuenta a nombre del Ayuntamiento (CIF P1621500F, IBAN municipal), o
  - Migración a Redsys (banco municipal — ortodoxo en sector público español).
- [ ] 🟠 **Ordenanza fiscal municipal** aprobada en Pleno y publicada en BOP Cuenca con tarifas y suplemento de luz.
- [ ] 🟠 **Configurar DNS de tarancón.es** para `reservas@tarancon.es` (SPF + DKIM + DMARC) y verificar el dominio en Resend.
- [ ] 🟠 **Firmar DPA con encargados**: Stripe (Dashboard → Legal → DPA), Resend (panel → Compliance), hosting elegido.
- [ ] 🟠 **Habilitar buzones institucionales**: `dpd@tarancon.es`, `accesibilidad@tarancon.es`.
- [ ] 🟡 **Auditoría externa de accesibilidad** WCAG 2.1 AA + UNE-EN 301 549 (puede contratarse aparte o vía INCIBE).
- [ ] 🟡 **Sello ENS** categoría MEDIA (autoevaluación) — declaración + publicación en sede electrónica.
- [ ] 🟡 **Designar interlocutor técnico desde el Ayuntamiento** (Concejalía / Informática) para incidencias.

---

## 🛠️ Backlog técnico

### 🔴 P0 — pendiente cerrar antes de Go-Live

- [ ] **Configurar webhook Stripe**: registrar `https://reservas.tarancon.es/api/stripe-webhook.php` en Stripe Dashboard, copiar el `whsec_*` y ponerlo en `env.php` como `ENV_STRIPE_WEBHOOK_SECRET`.
- [ ] **Borrar de producción** `deploy_*.php`, `migrate_remove_rol.php`, `refactor.ps1` antes de presentar.
- [ ] **Ejecutar `api/setup-security-db.php?key=<SETUP_DB_KEY>`** en producción una vez tras desplegar v3.0.0.
- [ ] **Pen-test ligero** (OWASP ZAP automatizado o manual) tras migrar al hosting definitivo.

### 🟠 P1 — necesario para una versión pública digna

- [ ] **Polling tras Stripe Checkout**: en `pago-exitoso.php` / `bono-exitoso.php` esperar al webhook (3-5 segundos) antes del fallback de creación local.
- [ ] **Migración a `Aiven MySQL` o equivalente UE** (o a hosting Acens/Arsys con MySQL gestionado) — mantener stack PHP.
- [ ] **Backup automatizado**: cron diario que vuelque BD a `S3 EU` o `Hetzner Storage Box`, retención 30 días.
- [ ] **CI básico en GitHub Actions**: `php -l` en todos los `*.php`, scan de secretos (`gitleaks`), validación de sintaxis JS.
- [ ] **Retirar `'unsafe-inline'` del `script-src` del CSP**: mover los `<script>` inline restantes (admin-dashboard, home, bonos, forgot/reset/verify, 2fa-setup) a archivos `.js` externos o usar nonces. Los `onclick=` ya están eliminados.
- [x] **Autohospedar el generador de QR del 2FA** (alternativa a quickchart.io) — `includes/qrcode.php`, encoder QR en PHP puro sin dependencias (modo byte, nivel M, v1–10); `totp_qr_url()` devuelve un `data:` URI y se quitó quickchart.io de la CSP.
- [ ] **Limpiar `setup-*.php` y `migrate_*.php`** del directorio raíz al migrar a producción definitiva. **CRÍTICO**: los `deploy_*.php` con credenciales FTP en código siguen en Git (rotación pendiente).

### 🟡 P2 — mejoras importantes

- [ ] **Política de contraseñas reforzada para registro de usuario** (subir mínimo a 10 caracteres y comprobación contra HaveIBeenPwned vía k-anonymity API).
- [ ] **Tests automáticos** con PHPUnit para los flujos críticos: login, registro, CSRF, IDOR Stripe, idempotencia webhook.
- [ ] **Monitorización de uptime** (UptimeRobot gratuito) + alertas Telegram o email.
- [ ] **Cron de purga** de datos según política de retención: `login_attempts` >30 días, `audit_log` >12 meses, sesiones expiradas, consultas resueltas hace >12 meses.
- [ ] **Sello de tiempo (TSA) cualificado** en reservas con valor jurídico (FNMT-CERES o Izenpe), si el Ayuntamiento lo exige.
- [ ] **Integración con Cl@ve / certificado digital** para identificación oficial (Ley 39/2015 art. 9) cuando la reserva tenga efectos jurídicos.
- [ ] **PDF de justificante** de reserva descargable desde el perfil (cumple RD 1619/2012 facturación).
- [ ] **Cron job de recordatorio** 24h antes de la reserva por email.
- [ ] **Sistema de cancelación por el ciudadano** desde el perfil (hasta 30 min antes).
- [ ] **Notificación en panel admin** cuando hay nueva consulta en el buzón.
- [ ] **Exportar listados a Excel** (no solo CSV) en buscador de reservas.
- [ ] **Dashboard de métricas** mejorado: ocupación por día/hora, ingresos por instalación, top usuarios.

### 🟢 P3 — futuro

- [ ] **App móvil** o **PWA** instalable.
- [ ] **Integración con calendario municipal** (eventos, torneos).
- [ ] **Sistema de inscripción a torneos / ligas internas**.
- [ ] **API pública** (datos abiertos) con ocupación agregada — Ley 19/2013 transparencia.
- [ ] **Multitenancia** para cubrir otras concejalías (cultura, juventud, salud).
- [ ] **Integración con sede electrónica** y notificaciones electrónicas (Notific@).
- [ ] **Migración a Next.js / Vercel** con Neon Postgres (estudiada en análisis previo — sin urgencia mientras PHP cumpla).

---

## ✅ Hecho recientemente

### v3.1.0 (24-05-2026) — Segunda ola de hardening + auth completa

- [x] Auditoría de validación post-v3.0.0 (subagente especializado).
- [x] Fixes críticos/altos del audit: `enviar-correo` bloqueado en prod, `conexion` sin getMessage, setup-* uniformes, webhook livemode, SITE_URL obligatorio en prod, admin-stats con headers seguros, LIMIT parametrizado, LIKE wildcards escapados, password clamp 256 bytes, body size cap 64KB.
- [x] Verificación de email al registro (`includes/tokens.php`, `html/verify-email.php`, integrado en `user-register.php`).
- [x] Password reset completo (`forgot-password.php`, `reset-password.php` API + frontend, anti-enumeración, token de un solo uso).
- [x] 2FA TOTP para administradores (RFC 6238, sin dependencias, códigos de respaldo).
- [x] Refactor `onclick` inline → eventos + delegación (home.php, bonos.php).
- [x] `.well-known/security.txt` (RFC 9116).
- [x] `docs/SECURITY-CHECKLIST.md` — ~50 puntos de QA manual de seguridad.
- [x] Pago-exitoso/bono-exitoso resilientes a sesión caducada durante Stripe.

### v3.0.0 (23-05-2026) — Seguridad y cumplimiento

- [x] Middleware `SecurityHandler` reescrito (CSRF, rate limit IP, sesión, redirect whitelist).
- [x] CSRF aplicado a los 14 endpoints de mutación que no lo tenían.
- [x] `audit_log` + tablas `login_attempts`, `password_resets`, `email_verifications`, `consent_log`, `webhook_events`.
- [x] Webhook Stripe firmado + idempotente como fuente canónica.
- [x] Migración Brevo → Resend con `MAIL_OVERRIDE_TO` para desarrollo.
- [x] Banner de cookies conforme Guía AEPD 2023 con enforcement server-side.
- [x] Textos legales completos (privacidad, cookies, aviso, accesibilidad) con base jurídica correcta.
- [x] Accesibilidad: skip link, ARIA, reduced-motion, h1 único, DNI opcional en contacto.
- [x] HTTPS forzado en `.htaccess`, Permissions-Policy, COOP, bloqueo setup-*.
- [x] Plantilla `env.example.php` con todos los slots documentados.

### v2.7.0 (15-04-2026)
- [x] Pista de Fútbol 7.

### v2.6.0 (13-04-2026)
- [x] Sistema completo de bonos.

---

## 📜 Reglas de mantenimiento del plan

- Marcar `[x]` cuando se complete una tarea. **No borrar** las completadas: moverlas a "Hecho recientemente" al cerrar versión.
- Toda nueva idea entra como `[ ]` con prioridad asignada (P0–P3).
- Si una prioridad se eleva o baja, justificar brevemente en una línea bajo la tarea.
- Revisar este plan al iniciar cada sesión de trabajo (regla aplicada en `CLAUDE.md`).
