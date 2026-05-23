# CLAUDE.md

> Instrucciones específicas para Claude Code (y similares) cuando trabaje en este repositorio.
> Este archivo se carga automáticamente al iniciar sesión. Mantenerlo conciso y útil.

---

## El proyecto en una frase

Portal PHP+MySQL del **Ayuntamiento de Tarancón (Cuenca, España)** para reserva de instalaciones deportivas. Como es una administración pública, **el cumplimiento legal (RGPD, LOPDGDD, LSSI-CE, RD 1112/2018, ENS) es tan importante como la funcionalidad**.

## Stack y restricciones

- **Backend:** PHP 8+ (sin Composer ni framework). PDO con prepared statements.
- **Frontend:** HTML5 + CSS3 puro + JavaScript vanilla. **No** introducir frameworks (React, Vue, Tailwind, etc.) sin discutirlo antes.
- **BD:** MySQL `utf8mb4`. No migrar a PostgreSQL ahora (rompería todo).
- **Hosting actual:** InfinityFree (provisional). Pendiente migrar a Acens/Arsys con declaración ENS.
- **Pagos:** Stripe Checkout (modo TEST). Cuenta debe estar a nombre del Ayuntamiento si pasa a LIVE.
- **Email:** Resend API. Variable `MAIL_OVERRIDE_TO` redirige todos los emails al correo del desarrollador en desarrollo.

## Reglas de oro

### Seguridad
1. **Cualquier endpoint que muta estado requiere CSRF**. Usar `SecurityHandler::requireCSRF()` o `requireCSRFOrRedirect()`. Nunca confiar en el método HTTP.
2. **Cualquier dato identificativo en POST debe contrastarse con sesión**. Si el usuario está logueado, los datos identificativos salen de `$_SESSION`, no del cliente.
3. **Precios y montos siempre se recalculan en servidor**. El valor enviado por el cliente nunca se usa para cobrar.
4. **Nunca exponer mensajes internos de PDO** al cliente. Loggear con `error_log()` y devolver mensaje genérico.
5. **Nunca interpolar texto del usuario en HTML/email sin `esc_html()`**. Vale tanto para páginas web como para plantillas de email.
6. **Validar `metadata.user_id` Stripe** contra `$_SESSION['user_id']` en cualquier callback de pago.
7. **No introducir nuevas dependencias externas** (CDNs, librerías JS) sin SRI y sin justificación.
8. **Funciones de ejecución de comandos del SO están prohibidas** en este proyecto (no son necesarias).
9. **Auditar acciones sensibles** con `audit_log($pdo, accion, entidad, id, datos)`.
10. **Subida de archivos: no implementar** sin discutir validación MIME, ruta fuera del webroot, escaneo.

### Cumplimiento legal
- **Base jurídica** del tratamiento: art. 6.1.e RGPD (interés público) + art. 6.1.c (obligación legal fiscal) — **NUNCA "consentimiento"** como base general (el ayuntamiento ejerce competencias).
- Cualquier cambio que **recoja nuevos datos personales** o **cambie finalidad** obliga a actualizar `html/politica-privacidad.php` y subir `POLICY_VERSION` en `config.php`.
- Cualquier cookie nueva → añadirla a la tabla de `html/politica-cookies.php`.
- **Minimización de datos**: pedir el DNI sólo cuando sea estrictamente necesario.
- Toda **transferencia internacional** (Stripe a EEUU, etc.) debe estar mencionada en política de privacidad.

### Calidad de código
- Comentarios solo cuando el *por qué* no es obvio. Identificadores claros mejor que docstrings.
- Errores en endpoints API: usar `SecurityHandler::jsonError(code, mensaje)`.
- Respuestas OK: usar `SecurityHandler::jsonOk(['data' => ...])`.
- Plantillas de email: definir variables `$nH = esc_html($nombre)` etc. y usar interpolación de doble comilla.

## Patrones de archivos clave

| Necesidad | Cómo |
|---|---|
| Validar CSRF en endpoint | `SecurityHandler::requireCSRF($data)` |
| Validar admin | `SecurityHandler::requireAdmin()` |
| Validar usuario | `SecurityHandler::requireUser()` |
| Rate limit IP | `SecurityHandler::isRateLimited($pdo, accion, ip, max, ventana)` |
| Sanitizar redirect | `SecurityHandler::sanitizeRedirect($candidate)` |
| Log de auditoría | `audit_log($pdo, accion, entidad, id, datos)` |
| Escape HTML | `esc_html($texto)` |
| Token CSRF en formulario | `<?= csrf_input() ?>` |
| Token CSRF en `<head>` | `<?= csrf_meta_tag() ?>` |
| Token CSRF en JS | `window.CSRF_TOKEN` (inyectado por `header.php`) |
| Enviar email | `send_email($to, $name, $subject, $html)` desde `api/mailer.php` |

## Tras cada cambio sustancial

**Obligatorio actualizar:**

1. **`docs/CHANGELOG.md`** — añadir línea bajo `[Unreleased]` o crear nueva versión (Keep a Changelog).
2. **`PLAN.md`** — marcar `[x]` completadas o añadir nuevas.

**Solo si cambia el estado general:**

3. **`README.md`** — versión, estado de pago, stack, etc.
4. **`CLAUDE.md`** — si aparece un patrón nuevo que debe respetarse en futuro.

> Si el cambio toca seguridad: actualizar también `SECURITY.md` si afecta a procedimientos.
> Si el cambio toca BD: añadir migración idempotente en `api/setup-security-db.php` (o un setup nuevo) y documentar en `docs/INSTALL.md`.

## Antes de empezar a tocar código

- Leer **`PLAN.md`** para entender prioridades vivas.
- Si la tarea no está en `PLAN.md`, añadirla antes de empezar.
- Si vas a tocar el flujo de pago, leer `api/stripe-webhook.php` para entender el flujo canónico.
- Si vas a tocar autenticación, leer `includes/SecurityHandler.php`.

## Cosas que NO hacer

- Commitear `env.php`, `deploy_*.php` con credenciales, archivos `.log`, `brevo_log.txt`, dumps `.sql`.
- Usar `$_SERVER['HTTP_HOST']` en redirects o construcciones que afecten a la seguridad (cabecera falsificable). Usar `SITE_URL`.
- Llamar a funciones que invoquen comandos del sistema operativo desde PHP.
- Renderizar HTML del usuario con `innerHTML` en JavaScript sin escape; usar `textContent` o helpers tipo `escHtml()`.
- Devolver detalle interno de errores PDO al cliente.
- Aceptar `user_id` o `email` del POST cuando ya hay sesión activa.
- Usar `SHOW COLUMNS` en runtime para construir queries dinámicas.
- Crear nuevos endpoints sin `Content-Type: application/json` + `X-Content-Type-Options: nosniff` + `Cache-Control: no-store`.

## Comandos útiles

```bash
# Sintaxis PHP de los archivos tocados
/c/xampp/php/php.exe -l <ruta>

# Buscar endpoints sin CSRF (en futuras revisiones)
grep -L "requireCSRF\|validateCSRFToken" api/*.php

# Generar un secreto (BETA_SALT, SETUP_DB_KEY, etc.)
/c/xampp/php/php.exe -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"

# Listar TODO/FIXME pendientes
grep -rn "TODO\|FIXME\|REVISAR DPO\|REVISAR" --include="*.php" .
```

## Contacto

Desarrollador: usuario propietario del repositorio. Email personal: configurado en `MAIL_OVERRIDE_TO` durante desarrollo.

Cliente final: **Ayuntamiento de Tarancón** (Cuenca). Interlocutor pendiente de designar formalmente.
