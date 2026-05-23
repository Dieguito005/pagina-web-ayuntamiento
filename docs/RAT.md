# Registro de Actividades de Tratamiento (RAT)

> Documento exigido por el art. 30 RGPD. Debe ser **publicable en la sede electrónica** del Ayuntamiento (art. 31 LOPDGDD).
>
> Esta es la **plantilla técnica** asociada al portal "Alquileres Tarancón". El RAT general del Ayuntamiento la mantiene el DPD; este archivo describe únicamente los tratamientos que ocurren en este portal y sirve como insumo para que el DPD lo integre en el RAT institucional.

---

## Datos del Responsable

- **Identidad:** Excelentísimo Ayuntamiento de Tarancón
- **NIF:** P1621500F
- **Dirección postal:** Plaza del Ayuntamiento, 1, 16400 Tarancón (Cuenca)
- **Teléfono:** +34 969 32 10 16
- **Correo:** info@tarancon.es
- **Sede electrónica:** https://sede.tarancon.es
- **DPD:** dpd@tarancon.es *(REVISAR DPO: confirmar buzón habilitado e inscripción ante AEPD)*

---

## Tratamiento 1 — Gestión de cuentas de usuario

| Campo | Valor |
|---|---|
| **Finalidad** | Permitir alta y autogestión de cuenta para reservar instalaciones deportivas. |
| **Base jurídica** | Art. 6.1.b RGPD (ejecución de relación jurídica con el interesado). |
| **Colectivos afectados** | Ciudadanía residente o usuaria de las instalaciones municipales. |
| **Categorías de datos** | Identificativos (nombre, apellidos), contacto (email, teléfono opcional), identificación oficial (DNI/NIE opcional), credenciales (contraseña cifrada con bcrypt). |
| **Categorías de destinatarios** | Personal autorizado del Ayuntamiento (Concejalía de Deportes); proveedor de hosting (encargado del tratamiento). |
| **Transferencias internacionales** | No aplica (datos en BD UE una vez migrado). |
| **Plazo de conservación** | Mientras la cuenta esté activa + 12 meses tras la última actividad. Después: anonimización o supresión. |
| **Medidas de seguridad** | Cifrado en tránsito (HTTPS/TLS 1.2+), `password_hash(PASSWORD_DEFAULT)`, sesiones `HttpOnly`+`SameSite`, rate limit en login, CSRF, audit log. |
| **Tablas BD implicadas** | `usuarios`, `email_verifications`, `password_resets`, `consent_log`. |

---

## Tratamiento 2 — Reserva y pago de instalaciones deportivas

| Campo | Valor |
|---|---|
| **Finalidad** | Gestión de reservas, generación de código de acceso, cobro de la tasa/precio público y emisión de justificante. |
| **Base jurídica** | Art. 6.1.e RGPD (misión en interés público — competencia local sobre instalaciones deportivas art. 25.2.l Ley 7/1985 LBRL) + art. 6.1.c RGPD (obligación legal: facturación/contabilidad municipal — Ley 58/2003 LGT, RD 1619/2012). |
| **Colectivos afectados** | Personas físicas que reservan pistas; personas jurídicas si corresponde (futuros torneos). |
| **Categorías de datos** | Identificativos, contacto, identificación oficial (DNI/NIE para identificar al titular), económicos (importe, identificador de transacción Stripe). NO se almacenan datos completos de tarjeta — Stripe los procesa y custodia. |
| **Categorías de destinatarios** | Personal autorizado del Ayuntamiento; conserjes (acceden únicamente al código de acceso y datos mínimos); Stripe Payments Europe Ltd. (encargado de pagos, Irlanda); proveedor de email transaccional (Resend); Administración Tributaria si corresponde. |
| **Transferencias internacionales** | Sí — Stripe puede realizar operaciones en EE. UU. al amparo de las cláusulas tipo de la Comisión Europea y del Marco UE-EE. UU. de Privacidad de Datos. Resend (EE. UU.) adherido al Data Privacy Framework. |
| **Plazo de conservación** | 4 años desde la reserva (prescripción tributaria, art. 66 Ley 58/2003). Datos contables asociados 6 años (art. 30 Cco). |
| **Medidas de seguridad** | Webhook Stripe firmado con HMAC, validación `metadata.user_id` anti-IDOR, idempotencia por `event_id`, precio recalculado en servidor, audit log de creación/cancelación. |
| **Tablas BD implicadas** | `reservas`, `bonos`, `bonos_config`, `precios_personalizados`, `webhook_events`. |

---

## Tratamiento 3 — Atención de consultas

| Campo | Valor |
|---|---|
| **Finalidad** | Resolver dudas, sugerencias o quejas remitidas a través del formulario de contacto. |
| **Base jurídica** | Art. 6.1.a RGPD (consentimiento manifestado al pulsar "Enviar"). |
| **Colectivos afectados** | Ciudadanía en general. |
| **Categorías de datos** | Identificativos, contacto, descripción de la consulta. DNI **opcional** (minimización art. 5.1.c). |
| **Categorías de destinatarios** | Personal autorizado del Ayuntamiento (Concejalía correspondiente). |
| **Transferencias internacionales** | No aplica para el almacenamiento; sí para el email de respuesta (Resend). |
| **Plazo de conservación** | 12 meses tras resolución. Si genera expediente administrativo, calendario municipal aplicable. |
| **Medidas de seguridad** | CSRF, honeypot anti-spam, rate limit IP, datos identificativos sobreescritos desde sesión si el usuario está logueado (anti-spoof). |
| **Tablas BD implicadas** | `consultas`. |

---

## Tratamiento 4 — Cookies y registro de consentimiento

| Campo | Valor |
|---|---|
| **Finalidad** | Recoger y acreditar la decisión del usuario sobre cookies (art. 22 LSSI-CE) y otorgar trazabilidad (RGPD art. 5.2 accountability). |
| **Base jurídica** | Interés legítimo para cookies técnicas estrictamente necesarias (exentas de consentimiento por art. 22.2 LSSI). Consentimiento expreso (art. 6.1.a RGPD) para cookies no esenciales. |
| **Colectivos afectados** | Visitantes del portal. |
| **Categorías de datos** | Identificador opaco de consentimiento (UUID), decisión, versión de política aceptada, IP anonimizada (truncada al /24 o /64), User-Agent. |
| **Categorías de destinatarios** | Personal autorizado del Ayuntamiento (auditoría interna o requerimiento AEPD). |
| **Plazo de conservación** | 24 meses (caducidad del consentimiento + acreditación). |
| **Medidas de seguridad** | Cookie firmada server-side, IP anonimizada, no se almacenan datos identificativos directos. |
| **Tablas BD implicadas** | `consent_log`. |

---

## Tratamiento 5 — Auditoría y registros de seguridad

| Campo | Valor |
|---|---|
| **Finalidad** | Detectar y prevenir accesos no autorizados, fraude o uso indebido del portal. Cumplir requisitos del ENS (op.exp.8 registro de actividad). |
| **Base jurídica** | Art. 6.1.e RGPD (misión en interés público — seguridad de los sistemas que tratan datos personales). |
| **Categorías de datos** | Acción ejecutada, ID del actor (admin/usuario), IP anonimizada, User-Agent, marca temporal, datos relevantes en JSON (sin campos sensibles — `password`, `csrf_token`, `auth_password` se redactan). |
| **Plazo de conservación** | 12 meses (`audit_log`), 30 días (`login_attempts`). |
| **Medidas de seguridad** | IP anonimizada, blacklist automática de campos sensibles, sin acceso desde la web (sólo BD). |
| **Tablas BD implicadas** | `audit_log`, `login_attempts`. |

---

## Anexo — Encargados del tratamiento

| Encargado | Servicio | Ubicación | Garantías |
|---|---|---|---|
| Stripe Payments Europe, Ltd. | Pasarela de pagos | Irlanda (UE) + EE. UU. (operaciones) | DPA estándar Stripe + cláusulas tipo UE + Data Privacy Framework |
| Resend, Inc. | Email transaccional | EE. UU. | Adhesión a Data Privacy Framework + DPA Resend |
| *Proveedor de hosting* | Almacenamiento web/BD | *[REVISAR cuando se migre fuera de InfinityFree]* | DPA + preferentemente certificación ENS y datacenters UE |

---

## Anexo — Cambios en el RAT

| Fecha | Versión | Cambio |
|---|---|---|
| 2026-05-23 | 1.0 | Creación inicial junto con v3.0.0. |

---

## Mantenimiento

Este RAT debe revisarse y actualizarse:

- **Cada vez que se cree un nuevo tratamiento** (nuevo formulario que recoja datos, nueva integración con tercero).
- **Anualmente** como mínimo, aunque no haya cambios.
- **Inmediatamente** tras una brecha o cambio normativo relevante.

La actualización debe registrarse en el "Anexo — Cambios" y comunicarse al DPD del Ayuntamiento.
