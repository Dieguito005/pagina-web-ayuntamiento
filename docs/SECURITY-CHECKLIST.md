# Checklist de QA de seguridad

> Lista de pruebas manuales para validar que las medidas de seguridad funcionan en el entorno real.
> Pasar por todas antes de considerar una versión "lista para producción".

Marca `[x]` lo que verifiques. Si algo falla, abre una entrada en `PLAN.md` con prioridad P0.

---

## 1. Autenticación

### 1.1 Registro de usuario
- [ ] Crear cuenta sin marcar "He leído la Política de Privacidad" → muestra error (debe ser obligatorio).
- [ ] Crear cuenta con email mal formado → muestra error específico.
- [ ] Crear cuenta con contraseña < 8 caracteres → muestra error.
- [ ] Crear cuenta con email ya registrado → muestra error (sin filtrar si existe o no — actualmente revela; pendiente).
- [ ] Tras registro exitoso, llega email de verificación a `MAIL_OVERRIDE_TO` con banner indicando destinatario real.
- [ ] Clic en enlace del email → llega a `verify-email.php`, mensaje OK, BD `usuarios.email_verificado = 1`.
- [ ] Reusar el enlace de verificación → mensaje "ha caducado o ya ha sido usado".
- [ ] Token caducado (>24h) → mismo mensaje.

### 1.2 Login de usuario
- [ ] 6 intentos fallidos seguidos → rate limit por 15 min (mensaje específico).
- [ ] Rate limit se mantiene aunque borres cookies y abras ventana incógnito (es por IP en BD).
- [ ] Tras login exitoso, `PHPSESSID` cambia (verificable en DevTools → Application → Cookies).
- [ ] Cookies `PHPSESSID` con flags `HttpOnly`, `SameSite=Lax`, `Secure` (en producción).
- [ ] Sesión caduca tras 30 min de inactividad → redirige a login.

### 1.3 Password reset
- [ ] `forgot-password.php` con email inexistente → respuesta "si existe, recibirás email" (sin revelar).
- [ ] `forgot-password.php` con email existente → llega email a `MAIL_OVERRIDE_TO`.
- [ ] Clic en enlace → llega a `reset-password.php` con formulario.
- [ ] Enviar contraseña débil (<12 chars o sin números) → muestra error.
- [ ] Cambio exitoso → sesión actual se cierra, redirige a login.
- [ ] Reusar el enlace de reset → "ha caducado o ya ha sido usado".
- [ ] Solicitar dos resets seguidos → solo el último token funciona (los anteriores quedan invalidados).

### 1.4 Login de admin
- [ ] 6 intentos fallidos → rate limit IP por 15 min.
- [ ] Tras login exitoso (sin 2FA), redirige a dashboard.
- [ ] Si admin tiene 2FA activo → tras password muestra paso 2 (input 6 dígitos).
- [ ] Código incorrecto → mensaje error; sigue en paso 2.
- [ ] Código correcto → entra al dashboard.
- [ ] Códigos de respaldo (8 hex) también funcionan; cada uno solo una vez.

### 1.5 2FA admin
- [ ] Activar 2FA desde `/admin/admin-2fa-setup.php`:
  - [ ] Aparece QR + secreto.
  - [ ] Escanear con Google Authenticator → muestra códigos rotando cada 30s.
  - [ ] Introducir código correcto → activación + muestra 8 códigos de respaldo.
- [ ] Logout y volver a entrar → exige TOTP.
- [ ] Desactivar 2FA → exige código TOTP actual.

---

## 2. CSRF

- [ ] Desde DevTools, crear `<form action="https://.../api/admin-users.php" method="POST">` con `action=delete&target_id=1`. Sin token CSRF → 403 "Token de seguridad inválido".
- [ ] Cambiar la URL del action a `api/user-update.php` → mismo 403.
- [ ] Inspeccionar el `meta[name="csrf-token"]` en cada página que hace POST: existe y tiene un hex de 64 chars.
- [ ] `window.CSRF_TOKEN` está definido en consola en cualquier página con header.

---

## 3. Validación de identidad (IDOR / spoof)

- [ ] Usuario A logueado: pagar reserva en Stripe TEST con tarjeta 4242 4242 4242 4242.
- [ ] Copiar el `session_id` (URL en `pago-exitoso.php`).
- [ ] Cerrar sesión A y entrar como usuario B.
- [ ] Abrir manualmente `pago-exitoso.php?session_id=<el-de-A>` → muestra error "no corresponde a tu cuenta" (NO crea reserva a B).
- [ ] Mismo test con `bono-exitoso.php` → mismo error.
- [ ] Como usuario A logueado, ir a `/api/guardar-consulta.php` con POST manipulado: `email=otro@usuario.com&dni=...` → la consulta queda registrada con el email/DNI del **usuario logueado** (A), no con el del POST.

---

## 4. Stripe

- [ ] `success_url` y `cancel_url` apuntan al SITE_URL configurado, NUNCA a un dominio externo.
- [ ] `metadata.user_id` aparece en la sesión Stripe creada (verificable en Dashboard → eventos).
- [ ] Webhook firmado correctamente (Stripe Dashboard → Webhooks → últimas entregas: status 200).
- [ ] Si llega un evento con `livemode` distinto al entorno → 400 + log "environment_mismatch".
- [ ] Repetir un mismo evento de webhook (Replay desde Stripe Dashboard) → 200 con `duplicate: true`, NO crea reserva duplicada.
- [ ] Precio del Stripe Checkout = precio en BD (no manipulable desde el cliente).

---

## 5. XSS y plantillas

- [ ] Registrar usuario con nombre `<script>alert(1)</script> Tester` → al loguearse y ver perfil/checkout, el nombre aparece como texto literal, no se ejecuta el script.
- [ ] Hacer una reserva con ese nombre → el email recibido muestra el texto literal en el `<h2>` (sin script).
- [ ] Crear un anuncio desde admin con `<img src=x onerror=alert(1)>` en mensaje → al verlo en `/html/home.php` se renderiza como texto sin ejecutar el `onerror`.
- [ ] Enviar una consulta con descripción `<script>...</script>` → al verla en buzón admin, no se ejecuta.

---

## 6. Cookies y RGPD

- [ ] Primera visita: aparece banner de cookies con 3 botones (Aceptar / Rechazar / Configurar) de igual prominencia visual.
- [ ] Rechazar → cookie `cookie_consent=rejected|0|<version>` (HttpOnly false) + cookie `cookie_consent_id` (HttpOnly true).
- [ ] BD `consent_log` tiene una entrada nueva con IP anonimizada (/24).
- [ ] Recargar → banner ya no aparece.
- [ ] Borrar `cookie_consent` desde DevTools → banner vuelve a aparecer.
- [ ] Aceptar → entrada nueva en `consent_log` con `analytics = 1`.
- [ ] La cookie expira en 24 meses (verificar `Expires` en DevTools).

---

## 7. Cabeceras de seguridad

Inspecciona con DevTools → Network → cualquier petición:

- [ ] `X-Content-Type-Options: nosniff`
- [ ] `X-Frame-Options: SAMEORIGIN`
- [ ] `Referrer-Policy: strict-origin-when-cross-origin`
- [ ] `Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(self ...), interest-cohort=()`
- [ ] `Cross-Origin-Opener-Policy: same-origin`
- [ ] `Strict-Transport-Security: max-age=31536000; includeSubDomains; preload` (en producción HTTPS)
- [ ] `Content-Security-Policy` con `default-src 'self'`, `frame-ancestors 'self'`, `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`, `upgrade-insecure-requests`
- [ ] `Cache-Control: no-store` en respuestas JSON

Visita `https://reservas.tarancon.es/.well-known/security.txt` → carga correctamente.

---

## 8. HTTPS

- [ ] Visitar `http://reservas.tarancon.es/` → 301 a `https://`
- [ ] [SSL Labs Test](https://www.ssllabs.com/ssltest/) → grado A o A+, TLS 1.2+ obligatorio.
- [ ] [Mozilla Observatory](https://observatory.mozilla.org/) → grado B o superior.
- [ ] [securityheaders.com](https://securityheaders.com/) → A o A+.

---

## 9. Acceso a archivos sensibles (debe ser 403)

- [ ] `https://reservas.tarancon.es/env.php` → 403
- [ ] `https://reservas.tarancon.es/config.php` → 403
- [ ] `https://reservas.tarancon.es/.gitignore` → 403
- [ ] `https://reservas.tarancon.es/.git/config` → 403
- [ ] `https://reservas.tarancon.es/README.md` → 403
- [ ] `https://reservas.tarancon.es/api/setup-security-db.php` (sin admin ni key) → 403
- [ ] `https://reservas.tarancon.es/api/setup-bonos-db.php` (sin admin ni key) → 403
- [ ] `https://reservas.tarancon.es/api/conexion.php` → 403
- [ ] `https://reservas.tarancon.es/api/mailer.php` → 403
- [ ] `https://reservas.tarancon.es/deploy_ftp.php` → 403 o 404 (mejor borrar de producción)
- [ ] `https://reservas.tarancon.es/migrate_remove_rol.php` → 403 o 404

---

## 10. Información que no debe filtrarse

- [ ] Cualquier endpoint API tras error: mensaje genérico, NUNCA `$e->getMessage()`.
- [ ] PHP `error_reporting = 0` y `display_errors = Off` en producción (revisar phpinfo si es necesario).
- [ ] Páginas 404/500 NO muestran rutas internas ni stack traces.

---

## 11. Endpoints sin autenticación que deberían tenerla

```bash
# Lista todos los api/*.php y comprueba que cada uno declara requireUser / requireAdmin / firma webhook
for f in api/*.php; do
  if ! grep -q "requireUser\|requireAdmin\|verify_stripe_signature\|cookie-consent\|forgot-password" "$f"; then
    echo "Revisar: $f"
  fi
done
```

Salida esperada: solo endpoints públicos legítimos (get-anuncios, get-horarios, conexion, mailer, setup-*).

---

## 12. Otros

- [ ] El admin no puede borrarse a sí mismo (`admin-users.php` action=delete con `target_id = self`).
- [ ] El último admin no se puede borrar.
- [ ] Cancelar una reserva queda registrado con `cancelado_por_admin_id` y `fecha_cancelacion`.
- [ ] La tabla `audit_log` tiene entradas tras: login user, login admin, crear reserva, cancelar reserva, cambiar password, activar 2FA, etc.
- [ ] Una sesión de admin abierta en una pestaña no puede simultaneamente ser una sesión de usuario en otra (mismo cookie).

---

## 13. Pruebas automatizadas externas

- [ ] OWASP ZAP automated scan contra el sitio en producción (modo "passive only" para no atacar).
- [ ] [Nikto](https://github.com/sullo/nikto): `nikto -h https://reservas.tarancon.es`
- [ ] [testssl.sh](https://testssl.sh/): `testssl reservas.tarancon.es`
- [ ] Lighthouse Best Practices ≥ 95.
- [ ] WAVE accessibility evaluation (https://wave.webaim.org/).

---

## Resultado

| Fecha | Versión | Quién | Notas |
|---|---|---|---|
| 2026-MM-DD | v3.1.0 | (pendiente) | |
