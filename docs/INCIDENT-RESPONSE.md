# Plan de respuesta a incidentes y solicitudes de derechos

> Procedimientos operativos para:
> 1. Notificación de brecha de seguridad a la AEPD en 72 horas (RGPD art. 33).
> 2. Comunicación al interesado si la brecha implica alto riesgo (RGPD art. 34).
> 3. Atención de solicitudes de derechos ARCO+ (RGPD art. 15-22).

---

## 1. Notificación de brecha — Protocolo 72 horas

### Fase 0 — Triaje (≤ 1 hora desde la detección)

1. Quien detecta el incidente avisa al **Delegado de Protección de Datos** (`dpd@tarancon.es`) y al **responsable técnico del portal** (mantener teléfonos de guardia actualizados en panel interno).
2. Anotar:
   - Fecha y hora de detección.
   - Cómo se detectó (log, aviso externo, prensa, etc.).
   - Sistemas afectados.

### Fase 1 — Contención (≤ 4 horas)

1. Aislar el sistema comprometido si es posible (cerrar acceso a admin, deshabilitar endpoint vulnerable, suspender cuenta atacante).
2. Cambiar credenciales potencialmente expuestas — ver `docs/RUNBOOK.md` § 1.
3. Conservar evidencias: copia de logs `audit_log`, `login_attempts`, `webhook_events`, logs Apache.
4. **No** reiniciar ni borrar sistemas sin haber preservado evidencias.

### Fase 2 — Análisis (≤ 24 horas)

Documentar en un informe interno:

- ¿Qué datos personales se han comprometido (categorías, número aproximado de afectados)?
- ¿Cuál fue la vía de entrada (CSRF, inyección, fuga de credenciales, error humano)?
- ¿Sigue activa la vulnerabilidad?
- ¿Qué riesgo concreto supone para los interesados (suplantación, fraude económico, daño reputacional)?

### Fase 3 — Notificación a la AEPD (≤ 72 horas)

Si el incidente afecta a datos personales y **no es manifiestamente improbable** que cause riesgo:

1. Notificar a la AEPD a través de **https://sedeagpd.gob.es** → "Comunicación de quiebras de seguridad".
2. Información mínima a incluir (art. 33.3 RGPD):
   - Naturaleza de la brecha + categorías y número aproximado de afectados.
   - Datos de contacto del DPD.
   - Consecuencias probables.
   - Medidas adoptadas o propuestas.
3. Si no se dispone de toda la información, presentar notificación inicial y completar después.

### Fase 4 — Comunicación a los afectados (sin demora indebida, RGPD art. 34)

Si la brecha **entraña alto riesgo** para los derechos de los interesados:

- Comunicación individualizada por email (Resend) o postal.
- Lenguaje claro, indicando:
  - Qué ha pasado.
  - Qué datos suyos están implicados.
  - Qué hacer (cambiar contraseña, vigilar cuenta bancaria, etc.).
  - Cómo contactar con el DPD para más información.

Si no es viable la comunicación individual, comunicación pública en sede electrónica y nota de prensa.

### Fase 5 — Post-mortem

En un plazo máximo de 14 días:

- Análisis de causa raíz documentado.
- Acciones correctivas con responsables y plazos (añadir a `PLAN.md` con prioridad P0).
- Actualizar `SECURITY.md` § "Histórico de incidentes" con resumen público.
- Backportar la lección a `CLAUDE.md` si introduce nueva regla.

---

## 2. Solicitudes de derechos ARCO+ (RGPD arts. 15-22)

| Derecho | Plazo de respuesta |
|---|---|
| Acceso (art. 15) | 1 mes (ampliable 2 meses con justificación) |
| Rectificación (art. 16) | 1 mes |
| Supresión (art. 17) | 1 mes |
| Limitación (art. 18) | 1 mes |
| Portabilidad (art. 20) | 1 mes |
| Oposición (art. 21) | 1 mes |
| Decisiones automatizadas (art. 22) | No aplica (no se realizan) |

### Canales aceptados

1. Sede electrónica del Ayuntamiento (preferente).
2. Email a `dpd@tarancon.es` con copia del DNI.
3. Presencialmente en el Registro General.

### Proceso interno

1. **Verificación de identidad** — comprobar DNI/NIE.
2. **Localización de datos** en BD:
   ```sql
   SELECT * FROM usuarios WHERE email = ? OR dni = ?;
   SELECT * FROM reservas WHERE usuario_id = ? OR dni = ?;
   SELECT * FROM bonos WHERE usuario_id = ?;
   SELECT * FROM consultas WHERE email = ? OR dni = ?;
   SELECT * FROM consent_log WHERE consent_id = (SELECT consent_id FROM usuarios WHERE id = ?);
   SELECT fecha, accion, entidad FROM audit_log WHERE actor_tipo='user' AND actor_id = ?;
   ```
3. **Preparar respuesta** — fichero estructurado (PDF o CSV) con todos los datos del interesado.
4. **Atender la solicitud**:
   - Acceso → enviar el fichero.
   - Rectificación → actualizar en BD + log auditoría (`update_phone/email/...`).
   - Supresión → ver § "Borrado seguro" abajo.
   - Limitación → marcar cuenta con flag interno + bloquear procesos automáticos.
   - Portabilidad → JSON estructurado de los datos del usuario.
5. **Registrar en `audit_log`** la atención (`accion = 'arco_acceso' | 'arco_supresion' | ...`).
6. **Confirmar al interesado** dentro del plazo.

### Borrado seguro

Cuando una solicitud de supresión es legítima:

```sql
-- 1) Anonimizar datos identificativos en reservas (las reservas con valor fiscal NO se borran, solo se anonimizan)
UPDATE reservas
SET usuario_nombre = '[USUARIO ELIMINADO]', usuario_email = NULL, dni = NULL, telefono = NULL
WHERE usuario_id = ?;

-- 2) Borrar bonos no usados; anonimizar bonos con histórico
DELETE FROM bonos WHERE usuario_id = ? AND usos_restantes = usos_totales;
UPDATE bonos SET usuario_id = NULL WHERE usuario_id = ?;

-- 3) Borrar consultas
DELETE FROM consultas WHERE usuario_id = ?;

-- 4) Borrar password_resets y email_verifications
DELETE FROM password_resets WHERE user_id = ?;
DELETE FROM email_verifications WHERE user_id = ?;

-- 5) Anonimizar consent_log (consent_id se conserva por accountability pero sin enlace al usuario)
UPDATE consent_log SET user_id = NULL WHERE user_id = ?;

-- 6) Borrar la cuenta
DELETE FROM usuarios WHERE id = ?;
```

> Documentar en `audit_log` con `accion = 'arco_supresion'` y el `entidad_id = <user_id_original>`.

---

## 3. Plantilla de notificación inicial a la AEPD

```
ASUNTO: Notificación de violación de seguridad — Portal de reservas Ayuntamiento de Tarancón

DATOS DEL RESPONSABLE
- Entidad: Ayuntamiento de Tarancón (NIF P1621500F)
- DPD: [nombre], dpd@tarancon.es, +34 969 32 10 16

NATURALEZA DE LA BRECHA
- Tipo: [confidencialidad / integridad / disponibilidad]
- Fecha y hora de detección: [...]
- Fecha estimada de la brecha: [...]
- Sistema afectado: portal de reservas (URL)
- Cómo se detectó: [...]

DATOS Y CATEGORÍAS AFECTADAS
- Tipos de datos: identificativos, contacto, [DNI/NIE], [datos económicos].
- Número aproximado de interesados: [...]
- Categorías de interesados: ciudadanía/usuarios registrados.

CONSECUENCIAS PROBABLES
[Descripción del riesgo concreto: suplantación, phishing dirigido, etc.]

MEDIDAS ADOPTADAS / PROPUESTAS
- [...]

¿Se ha comunicado a los afectados?  Sí / No / Pendiente
```

---

## 4. Plantilla de comunicación al interesado

```
Asunto: Información importante sobre la seguridad de su cuenta en Alquileres Tarancón

Estimado/a [nombre]:

El [fecha] hemos detectado un incidente de seguridad que ha podido afectar a determinados datos personales asociados a su cuenta en el portal de reservas deportivas del Ayuntamiento de Tarancón. En concreto, se ha podido acceder sin autorización a los siguientes datos: [LISTAR].

¿Qué hemos hecho?
[Acciones]

¿Qué le recomendamos hacer a usted?
- Cambiar su contraseña en https://reservas.tarancon.es
- [Resto de medidas, p. ej. vigilar movimientos bancarios]

Si tiene dudas o necesita ayuda, puede contactar con nuestro Delegado de Protección de Datos:
dpd@tarancon.es | +34 969 32 10 16

Tiene derecho a presentar una reclamación ante la Agencia Española de Protección de Datos (www.aepd.es).

Reciba un cordial saludo.
Ayuntamiento de Tarancón
```

---

Última actualización: 2026-05-23.
