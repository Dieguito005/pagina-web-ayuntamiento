# Política de Seguridad

## Reporte de vulnerabilidades

Si crees haber encontrado una vulnerabilidad de seguridad en el portal "Alquileres Tarancón", **no la publiques en GitHub Issues ni en redes sociales**. En su lugar:

1. Envía un correo a `dpd@tarancon.es` (o `seguridad@tarancon.es` cuando esté habilitado) describiendo:
   - El componente o URL afectada.
   - Pasos para reproducir.
   - Impacto estimado.
   - Tu nombre y forma de contacto si quieres atribución.
2. Si la vulnerabilidad expone datos personales: el Ayuntamiento activará el **protocolo de notificación de brechas en 72 h a la AEPD** (RGPD art. 33). Ver `docs/INCIDENT-RESPONSE.md`.

Tiempo de respuesta objetivo:

| Severidad | Acuse de recibo | Mitigación |
|---|---|---|
| Crítica (RCE, robo masivo de datos, bypass de pago) | < 24 h | < 7 días |
| Alta (XSS, IDOR, CSRF en endpoint sensible) | < 48 h | < 30 días |
| Media | < 7 días | < 90 días |
| Baja | < 14 días | siguiente release |

## Alcance

Está dentro del alcance todo lo que pertenezca al dominio del portal de reservas deportivas del Ayuntamiento:

- `*.tarancon.es` (en producción)
- Subdominio temporal `alquileres-deportivos-tarancon.infinityfree.me`
- Endpoints `/api/*`, panel `/admin/*`, páginas `/html/*`

**Fuera de alcance:**

- Infraestructura de Stripe, Resend, InfinityFree, Cloudflare (reportar directamente al proveedor).
- Ingeniería social contra empleados del Ayuntamiento.
- Pruebas de denegación de servicio (DDoS) contra el portal en producción.
- Reportes basados en escáneres automáticos sin validación manual del impacto real.

## Compromiso

El Ayuntamiento de Tarancón se compromete a:

- Responder en los plazos indicados.
- Mantener al reportante informado del avance.
- **Reconocer públicamente** la contribución cuando la persona lo autorice.
- No emprender acciones legales contra investigadores que actúen de buena fe y dentro del alcance descrito.

## Buenas prácticas internas (resumen)

- Sesiones con `HttpOnly`, `SameSite=Lax`, `Secure` en producción, regeneración tras login.
- CSRF obligatorio en todos los endpoints de mutación (`SecurityHandler::requireCSRF`).
- Rate limit por IP en autenticación (5 intentos / 15 min, persistente en BD).
- Webhook Stripe firmado con HMAC y verificado en cada petición.
- Cifrado en tránsito (HTTPS forzado, HSTS, TLS 1.2+).
- Contraseñas con `password_hash(PASSWORD_DEFAULT)` (bcrypt).
- Logs de auditoría con IP anonimizada.
- Cumplimiento RGPD/LOPDGDD: retención limitada, derechos ARCO+, DPD designado.

## Histórico de incidentes públicos

Ninguno declarado a la fecha. Las brechas se publicarán en este archivo conforme se cierre la investigación.

---

Última actualización: 2026-05-23.
