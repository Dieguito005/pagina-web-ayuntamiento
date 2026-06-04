# Diseño — QR del 2FA autohospedado (sin quickchart.io)

> Fecha: 2026-06-04
> Estado: aprobado para implementación (enfoque A)
> Relacionado: PLAN.md P1 "Autohospedar el generador de QR del 2FA"

## Problema

`includes/totp.php::totp_qr_url()` construye una URL a `https://quickchart.io/qr?...&text=<otpauth-uri>`.
El `otpauth://` URI contiene el **secreto TOTP del administrador** en texto plano. Al renderizar el QR,
el navegador del admin solicita esa imagen a quickchart.io, **filtrando el secreto a un tercero en EEUU**
en cada alta de 2FA. Implicaciones:

- Fuga de un secreto de autenticación a un proveedor no contratado (sin DPA).
- Transferencia internacional de dato sensible no contemplada en la política de privacidad.
- Incumple la regla 7 de `CLAUDE.md` (dependencias/CDN externas sin justificación ni SRI).

## Objetivo

Generar el QR íntegramente en el servidor, sin dependencias externas ni llamadas a terceros,
con el menor cambio posible en la superficie pública y endureciendo la CSP.

## Enfoque (A): generación en servidor → `data:` URI

### Componentes

1. **`includes/qrcode.php`** (nuevo) — módulo autónomo, sin dependencias, que codifica una cadena
   en una matriz QR y la serializa como SVG.
   - API pública mínima:
     - `qr_encode_svg(string $text, int $moduleSize = 6, int $margin = 4): string`
       → devuelve un documento SVG (string) con el QR.
   - Internamente: modo **byte** (el `otpauth://` URI contiene `:/?&=` y minúsculas, no cabe en
     modo alfanumérico), selección automática de versión, nivel de corrección de errores **M**,
     codificación Reed-Solomon, colocación en matriz, patrones de función (finders, timing,
     alignment), información de formato y selección de la **máscara óptima** (penalty score, las 8
     máscaras del estándar).
   - Sin estado global, sin I/O, sin red. Funciones puras.

2. **`includes/totp.php::totp_qr_url()`** (modificado) — pasa a devolver un `data:` URI:
   - `return 'data:image/svg+xml;base64,' . base64_encode(qr_encode_svg($otpauthUri));`
   - Se actualiza el docblock (ya no viaja el secreto a ningún tercero).
   - `require_once` de `includes/qrcode.php` al principio del archivo.

3. **`config.php`** (modificado) — quitar `https://quickchart.io` de la directiva `img-src` del CSP.
   Queda: `img-src 'self' data:;` (el `data:` ya está y es lo que necesita el QR embebido).

### Flujo de datos (sin cambios en cliente)

```
admin-2fa-setup.php  →  POST api/admin-2fa.php {action:init}
        ↑ qr-img.src = data.qr_url                 │
        │                                          ▼
        └──────────  {qr_url: "data:image/svg+xml;base64,..."} ◄── totp_qr_url() ◄── qr_encode_svg()
```

`api/admin-2fa.php` **no cambia**: sigue devolviendo `qr_url`, solo que ahora su valor es un
`data:` URI en vez de una URL a quickchart. El frontend (`admin-2fa-setup.php`) **no cambia**:
`document.getElementById('qr-img').src = data.qr_url` funciona igual con un `data:` URI.

### Manejo de errores

- `qr_encode_svg()` lanza `RuntimeException` si el texto excede la capacidad de la mayor versión
  soportada (no debería ocurrir: un `otpauth` URI ronda los 120–180 bytes, muy por debajo del límite).
  El módulo soportará hasta versión 10 (ECC M ≈ 271 bytes), holgura suficiente.
- `totp_qr_url()` no captura: si fallara, es un error de programación, no una condición de usuario;
  se propaga y se loguea por el manejador global. No se expone detalle al cliente.

### Pruebas / verificación

- **Corrección del encoder:** verificar que el SVG generado decodifica al `otpauth` URI original.
  Como no hay phone en CI, validar con un decodificador (p. ej. `zbarimg` si está disponible, o un
  decodificador QR en script de prueba puntual). Como mínimo: vectores de prueba conocidos
  (cadenas → matriz esperada para una versión/máscara fija) y un test de "round-trip" si hay
  herramienta de decodificación disponible.
- **Sintaxis PHP:** `php -l includes/qrcode.php includes/totp.php config.php`.
- **Integración manual:** abrir `admin/admin-2fa-setup.php`, pulsar "Iniciar configuración",
  comprobar que el `<img>` muestra un QR y que una app TOTP (Google Authenticator) lo escanea y
  genera códigos que `totp_verify()` acepta.
- **CSP:** confirmar en DevTools que no hay petición a quickchart.io y ninguna violación de CSP.

## Fuera de alcance (YAGNI)

- No se genera PNG (SVG embebido como `data:` URI basta y es nítido a cualquier tamaño).
- No se cachea el QR (es de un solo uso durante el alta).
- No se toca la lógica TOTP de verificación, secretos ni códigos de respaldo.
- No se aborda aquí el `'unsafe-inline'` del `script-src` (ítem P1 independiente).

## Documentación a actualizar tras implementar

- `docs/CHANGELOG.md` — entrada bajo `[Unreleased]` o nueva versión patch.
- `PLAN.md` — marcar `[x]` "Autohospedar el generador de QR del 2FA".
- `html/politica-privacidad.php` / `politica-cookies.php` — revisar si mencionaban quickchart.io
  como transferencia/tercero; si es así, eliminarlo (mejora de cumplimiento).
