# RUNBOOK — Operaciones comunes

> Pasos repetibles para las tareas operativas más habituales del portal.
> Mantener este archivo al día cuando se cambien procedimientos.

---

## 1. Rotar credenciales

### 1.1 Password de BD MySQL (InfinityFree)
1. Entrar en panel InfinityFree → Control Panel → MySQL Databases.
2. Pulsar "Edit" en la BD `if0_*_web` → cambiar contraseña.
3. Editar `env.php` local: actualizar `ENV_DB_PASS`.
4. Subir `env.php` por FTP (NUNCA por Git).
5. Probar login admin y reserva para confirmar.

### 1.2 API Key Resend
1. Entrar en `https://resend.com/api-keys`.
2. Crear nueva key (`re_...`).
3. Editar `env.php`: actualizar `ENV_RESEND_API_KEY`.
4. Subir `env.php`.
5. Probar enviando un email (formulario de contacto → debería llegar al `MAIL_OVERRIDE_TO`).
6. Revocar la key antigua.

### 1.3 Claves Stripe
- Test → Live: ir a Stripe Dashboard, copiar `sk_live_*` y `pk_live_*`, ponerlas en `env.php`.
- Configurar webhook en Stripe → `https://reservas.tarancon.es/api/stripe-webhook.php` → eventos: `checkout.session.completed`.
- Copiar el `whsec_*` resultante a `env.php` como `ENV_STRIPE_WEBHOOK_SECRET`.

### 1.4 BETA_KEY y BETA_SALT
```bash
/c/xampp/php/php.exe -r "echo bin2hex(random_bytes(16)) . PHP_EOL;"  # para BETA_KEY (basta corta)
/c/xampp/php/php.exe -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"  # para BETA_SALT
```
Actualizar `env.php` y comunicar la `BETA_KEY` al beta-tester.

### 1.5 SETUP_DB_KEY
```bash
/c/xampp/php/php.exe -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```
Actualizar `env.php`. Borrar URLs de acceso anteriores de bookmarks.

---

## 2. Desplegar nueva versión

### Mientras estemos en InfinityFree (provisional)
1. Verificar localmente: `find . -name "*.php" -exec /c/xampp/php/php.exe -l {} \; 2>&1 | grep -v "No syntax errors"` — debe estar limpio.
2. Confirmar que `env.php` NO está en git: `git ls-files | grep env.php` (debe estar vacío).
3. Crear commit en `main`.
4. Subir por FTP los archivos modificados con el script `deploy_ftp.php` ejecutado **localmente**:
   ```bash
   /c/xampp/php/php.exe deploy_ftp.php
   ```
5. Si la versión incluye cambios de BD: ejecutar el setup desde el navegador:
   ```
   https://reservas.tarancon.es/api/setup-security-db.php?key=<SETUP_DB_KEY>
   ```
6. Probar:
   - Login user + login admin.
   - Crear reserva (modo test).
   - Banner de cookies (debería aparecer en navegación nueva).

### Tras migrar a Acens/Arsys (objetivo)
1. Push a `main`.
2. GitHub Actions ejecuta lint + deploy SFTP (cuando se configure CI).
3. Migraciones idempotentes vía script automatizado.

---

## 3. Crear nuevo administrador

1. Login admin → pestaña "Gestión de Admins".
2. Rellenar usuario y contraseña (mínimo 12 caracteres con may/min/núm).
3. Introducir TU contraseña actual en "Confirmar".
4. Crear.

(El nuevo admin entrará por `/admin/admin-login.php`.)

---

## 4. Backup manual de BD

```bash
# Desde XAMPP local (BD de desarrollo)
/c/xampp/mysql/bin/mysqldump -u root ayuntamiento_reservas > backup-$(date +%F).sql

# Desde InfinityFree (productivo)
#   No hay shell. Usar phpMyAdmin: panel → MySQL Databases → phpMyAdmin →
#   seleccionar BD → Exportar → SQL → Descargar.
#   Guardar la copia en disco cifrado o S3 EU con retención mínima 30 días.
```

> Backup automático: pendiente (ver `PLAN.md` § P1).

---

## 5. Restaurar BD desde backup

```bash
# Local
/c/xampp/mysql/bin/mysql -u root ayuntamiento_reservas < backup.sql

# InfinityFree: importar el `.sql` desde phpMyAdmin (panel → Import).
```

---

## 6. Activar / desactivar modo mantenimiento

Por defecto `MAINTENANCE_MODE = true` cuando `IS_PRODUCTION`. Para abrir al público:
- Editar `config.php`: cambiar `define('MAINTENANCE_MODE', false);`, o
- Hacerlo configurable vía env: añadir `ENV_MAINTENANCE_MODE` en `env.php`.

Para que un beta-tester acceda durante mantenimiento:
- Compartirle: `https://reservas.tarancon.es/?beta_key=<BETA_KEY>` (la URL pega la cookie 30 días).

---

## 7. Conceder acceso temporal a una página interna

Si necesitas que un tester acceda a, por ejemplo, `/html/perfil.php` sin cuenta:
- Crear cuenta de prueba vía registro normal.
- O ejecutar (en consola MySQL):
  ```sql
  INSERT INTO usuarios (nombre, apellidos, email, password_hash, email_verificado)
  VALUES ('Tester', 'QA', 'tester@example.com', '<hash bcrypt>', 1);
  ```

---

## 8. Auditar quién hizo qué

```sql
-- Últimas 100 acciones administrativas
SELECT fecha, actor_tipo, actor_id, accion, entidad, entidad_id
FROM audit_log
WHERE actor_tipo = 'admin'
ORDER BY fecha DESC
LIMIT 100;

-- Intentos fallidos de login en las últimas 24 h
SELECT created_at, action, identifier, ip
FROM login_attempts
WHERE success = 0 AND created_at > NOW() - INTERVAL 24 HOUR
ORDER BY created_at DESC;
```

---

## 9. Atender una solicitud de ejercicio de derechos RGPD

Ver `docs/INCIDENT-RESPONSE.md` § "Solicitudes ARCO+".

---

## 10. Notificar una brecha de seguridad

Ver `docs/INCIDENT-RESPONSE.md` § "Notificación 72 h a la AEPD".

---

## 11. Limpiar datos antiguos (purga manual)

> El cron de purga automática es P1 en `PLAN.md`. Mientras tanto:

```sql
-- Borrar login_attempts > 30 días
DELETE FROM login_attempts WHERE created_at < NOW() - INTERVAL 30 DAY;

-- Borrar audit_log > 12 meses
DELETE FROM audit_log WHERE fecha < NOW() - INTERVAL 12 MONTH;

-- Borrar password_resets caducados sin usar
DELETE FROM password_resets WHERE expires_at < NOW() AND used_at IS NULL;

-- Borrar consultas resueltas hace más de 12 meses
DELETE FROM consultas WHERE estado = 'resuelta' AND fecha_creacion < NOW() - INTERVAL 12 MONTH;
```

---

## 12. Comprobar conformidad rápida

```bash
# 1) Endpoints sin CSRF
grep -L "requireCSRF\|validateCSRFToken" api/*.php

# 2) Endpoints que devuelven getMessage al cliente (filtración)
grep -n 'echo.*getMessage' api/*.php html/*.php

# 3) onclick inline en HTML (impiden retirar 'unsafe-inline' del CSP)
grep -rn 'onclick=' html/ admin/ includes/

# 4) Archivos que NO deberían existir en producción
ls -la deploy_*.php migrate_*.php refactor.ps1 2>/dev/null
```

---

Última actualización: 2026-05-23.
