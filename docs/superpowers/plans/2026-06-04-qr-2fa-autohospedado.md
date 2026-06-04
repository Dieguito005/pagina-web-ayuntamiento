# QR del 2FA autohospedado — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Generar el QR de configuración del 2FA en el servidor con PHP puro (sin dependencias ni llamadas a terceros), eliminando la fuga del secreto TOTP a quickchart.io.

**Architecture:** Un módulo nuevo `includes/qrcode.php` codifica una cadena en una matriz QR (modo byte, nivel de corrección M, versiones 1–10) y la serializa como SVG. `totp_qr_url()` pasa a devolver un `data:` URI con ese SVG, lo que es un cambio *drop-in* para la API (`api/admin-2fa.php`) y el frontend (`admin/admin-2fa-setup.php`), que no cambian. La CSP se endurece quitando `https://quickchart.io`.

**Tech Stack:** PHP 8.2 (CLI para tests), sin Composer ni frameworks. Tests = scripts PHP planos ejecutados con `php` (no hay PHPUnit en el proyecto). Verificación final de scan vía decodificador QR online a través del skill `/browse`.

**Spec:** `docs/superpowers/specs/2026-06-04-qr-2fa-autohospedado-design.md`

**Notas de riesgo (leer antes de empezar):**
- El área de **mayor riesgo** son las tablas del estándar (estructura de bloques EC, centros de alineación) y la **colocación de información de formato/versión**. Un error ahí produce un QR que parece válido pero **no escanea**. Por eso: (a) todo lo computable (campo de Galois, BCH de formato/versión) se calcula en código, no se transcribe; (b) cada capa se testea contra vectores publicados; (c) la Task 11 **bloquea** el cierre hasta confirmar un round-trip real de decodificación.
- Las rutinas de campo de Galois, BCH y colocación están basadas en el generador QR de Nayuki (dominio público), implementación de referencia conocida-correcta.

---

## File Structure

| Archivo | Responsabilidad | Acción |
|---|---|---|
| `includes/qrcode.php` | Encoder QR completo (tablas, GF(256), Reed-Solomon, matriz, máscaras, formato/versión, SVG) y API pública `qr_encode_svg()` | Crear |
| `includes/totp.php` | `totp_qr_url()` devuelve `data:` URI en vez de URL a quickchart | Modificar (`require_once` + cuerpo de `totp_qr_url` + docblock, líneas ~98-107) |
| `config.php` | Quitar `https://quickchart.io` del `img-src` del CSP | Modificar (línea 136) |
| `tests/qr/*.php` | Scripts de test por capa + integración | Crear |
| `docs/CHANGELOG.md` | Entrada del cambio | Modificar |
| `PLAN.md` | Marcar ítem P1 como hecho | Modificar |
| `html/politica-privacidad.php`, `html/politica-cookies.php` | Eliminar mención a quickchart.io si la hubiera | Modificar (condicional) |

Todo el encoder vive en un único archivo (`includes/qrcode.php`) con funciones internas de responsabilidad clara y una sola función pública. Es coherente con el estilo del proyecto (helpers por archivo, sin clases salvo donde aporta; aquí se usa una clase contenedora `QrMatrix` solo para la matriz).

---

## Convención de tests

No hay PHPUnit. Cada test es un script PHP que imprime `OK`/`FAIL` y sale con código ≠0 si falla. Helper compartido:

**Crear `tests/qr/_assert.php`:**

```php
<?php
$GLOBALS['__fails'] = 0;
function check(string $name, $got, $expected): void {
    if ($got === $expected) { echo "  ok  $name\n"; return; }
    $GLOBALS['__fails']++;
    echo "  FAIL $name\n";
    echo "       esperado: " . var_export($expected, true) . "\n";
    echo "       obtenido: " . var_export($got, true) . "\n";
}
function check_true(string $name, bool $cond): void { check($name, $cond, true); }
function done(): void {
    if ($GLOBALS['__fails'] > 0) { echo "FALLOS: {$GLOBALS['__fails']}\n"; exit(1); }
    echo "TODO OK\n"; exit(0);
}
```

Ejecución de un test: `php tests/qr/test_xxx.php`. Esperado al pasar: termina con `TODO OK` y código 0.

---

### Task 1: Campo de Galois GF(256) y aritmética

**Files:**
- Create: `includes/qrcode.php` (inicio del archivo)
- Test: `tests/qr/test_gf.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/qr/test_gf.php`:
```php
<?php
require __DIR__ . '/_assert.php';
require __DIR__ . '/../../includes/qrcode.php';

// Tablas exp/log conocidas del campo QR (poly 0x11D)
[$exp, $log] = qr_gf_tables();
check('exp[0]', $exp[0], 1);
check('exp[1]', $exp[1], 2);
check('exp[8]', $exp[8], 0x1D);   // x^8 mod 0x11D = 0x1D
check('log[1]', $log[1], 0);
check('log[2]', $log[2], 1);

// Multiplicación en GF(256)
check('mul 0*x', qr_gf_mul(0, 5), 0);
check('mul 1*x', qr_gf_mul(1, 7), 7);
check('mul 3*7', qr_gf_mul(3, 7), 9);     // alpha^25 * ... -> 9 (valor conocido)
check('mul 16*32', qr_gf_mul(16, 32), 116);
done();
```

- [ ] **Step 2: Ejecutar y ver que falla**

Run: `php tests/qr/test_gf.php`
Expected: error fatal (función no definida) o `FAIL`.

- [ ] **Step 3: Implementar GF(256)**

Inicio de `includes/qrcode.php`:
```php
<?php
/**
 * qrcode.php — Generador de códigos QR en PHP puro, sin dependencias.
 * Modo byte, nivel de corrección de errores M, versiones 1–10.
 * Suficiente para otpauth:// URIs (capacidad v10/M ≈ 213 bytes).
 *
 * API pública:
 *   qr_encode_svg(string $text, int $moduleSize = 6, int $margin = 4): string
 *
 * Rutinas de GF(256)/BCH/colocación basadas en el generador QR de Nayuki
 * (dominio público), implementación de referencia conocida-correcta.
 */

/** Tablas exponencial/logaritmo de GF(256) con polinomio primitivo 0x11D. */
function qr_gf_tables(): array
{
    static $exp = null, $log = null;
    if ($exp !== null) return [$exp, $log];
    $exp = array_fill(0, 512, 0);
    $log = array_fill(0, 256, 0);
    $x = 1;
    for ($i = 0; $i < 255; $i++) {
        $exp[$i] = $x;
        $log[$x] = $i;
        $x <<= 1;
        if ($x & 0x100) $x ^= 0x11D;
    }
    for ($i = 255; $i < 512; $i++) $exp[$i] = $exp[$i - 255];
    return [$exp, $log];
}

/** Multiplicación en GF(256). */
function qr_gf_mul(int $a, int $b): int
{
    if ($a === 0 || $b === 0) return 0;
    [$exp, $log] = qr_gf_tables();
    return $exp[$log[$a] + $log[$b]];
}
```

- [ ] **Step 4: Ejecutar y ver que pasa**

Run: `php tests/qr/test_gf.php`
Expected: `TODO OK`, código 0. (Si `mul 3*7` no diera 9, revisar el polinomio 0x11D.)

- [ ] **Step 5: Commit**

```bash
git add includes/qrcode.php tests/qr/_assert.php tests/qr/test_gf.php
git commit -m "feat(qr): campo de Galois GF(256) para encoder QR"
```

---

### Task 2: Reed-Solomon (generador y codewords de corrección)

**Files:**
- Modify: `includes/qrcode.php` (añadir tras GF)
- Test: `tests/qr/test_rs.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/qr/test_rs.php`:
```php
<?php
require __DIR__ . '/_assert.php';
require __DIR__ . '/../../includes/qrcode.php';

// Generador de grado 10: coeficientes conocidos (en valores, no alpha).
$gen = qr_rs_generator(10);
check('gen grado 10 longitud', count($gen), 11);
check('gen[0]', $gen[0], 1); // monico

// Vector de prueba estándar (Thonky, byte data 'HELLO WORLD' v1-M no aplica a byte;
// usamos un bloque de datos conocido y sus EC calculados por referencia).
// Datos de ejemplo: los 16 codewords de datos del mensaje byte "https://www.qrcode.com/"
// truncado no es estable; en su lugar verificamos propiedad: EC de bloque de ceros = ceros.
$ec0 = qr_rs_ec(array_fill(0, 16, 0), 10);
check('EC de datos nulos es nulo', $ec0, array_fill(0, 10, 0));

// Vector concreto: data = [32,91,11,120,209,114,220,77,67,64,236,17,236,17,236,17] (v1-M, 'HELLO WORLD' alfanumérico)
// EC esperados (10 codewords) según ISO/Thonky:
$data = [32,91,11,120,209,114,220,77,67,64,236,17,236,17,236,17];
$ec = qr_rs_ec($data, 10);
check('EC HELLO WORLD v1-M', $ec, [196,35,39,119,235,215,231,226,93,23]);
done();
```

- [ ] **Step 2: Ejecutar y ver que falla**

Run: `php tests/qr/test_rs.php`
Expected: función no definida.

- [ ] **Step 3: Implementar Reed-Solomon**

Añadir a `includes/qrcode.php`:
```php
/** Polinomio generador Reed-Solomon de grado $degree (coeficientes en valores). */
function qr_rs_generator(int $degree): array
{
    [$exp] = qr_gf_tables();
    $g = [1];
    for ($i = 0; $i < $degree; $i++) {
        $next = array_fill(0, count($g) + 1, 0);
        foreach ($g as $j => $coef) {
            $next[$j]     ^= $coef;                       // x * g
            $next[$j + 1] ^= qr_gf_mul($coef, $exp[$i]);  // alpha^i * g
        }
        $g = $next;
    }
    return $g; // longitud degree+1, monico
}

/** Codewords de corrección de errores para un bloque de datos. */
function qr_rs_ec(array $data, int $ecCount): array
{
    $gen = qr_rs_generator($ecCount);
    $res = array_merge($data, array_fill(0, $ecCount, 0));
    $dataLen = count($data);
    for ($i = 0; $i < $dataLen; $i++) {
        $coef = $res[$i];
        if ($coef !== 0) {
            foreach ($gen as $j => $g) {
                $res[$i + $j] ^= qr_gf_mul($g, $coef);
            }
        }
    }
    return array_slice($res, $dataLen); // últimos ecCount
}
```

- [ ] **Step 4: Ejecutar y ver que pasa**

Run: `php tests/qr/test_rs.php`
Expected: `TODO OK`. (Si `EC HELLO WORLD` falla, el bug está en GF o en la división RS.)

- [ ] **Step 5: Commit**

```bash
git add includes/qrcode.php tests/qr/test_rs.php
git commit -m "feat(qr): codificación Reed-Solomon"
```

---

### Task 3: Tablas del estándar y selección de versión

**Files:**
- Modify: `includes/qrcode.php`
- Test: `tests/qr/test_tables.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/qr/test_tables.php`:
```php
<?php
require __DIR__ . '/_assert.php';
require __DIR__ . '/../../includes/qrcode.php';

// Total de codewords de datos por versión (nivel M) — suma de grupos.
$totales = [1=>16,2=>28,3=>44,4=>64,5=>86,6=>108,7=>124,8=>154,9=>182,10=>216];
foreach ($totales as $v => $esperado) {
    [$ec, $groups] = qr_ec_table()[$v];
    $suma = 0; foreach ($groups as [$n, $cw]) $suma += $n * $cw;
    check("data codewords v$v", $suma, $esperado);
}

// Capacidad en bytes (modo byte) por versión.
check('capacidad v1', qr_byte_capacity(1), 14);
check('capacidad v7', qr_byte_capacity(7), 122);
check('capacidad v9', qr_byte_capacity(9), 180);
check('capacidad v10', qr_byte_capacity(10), 213);

// Selección de versión.
check('elige v1 para 10 bytes', qr_choose_version(10), 1);
check('elige v9 para 170 bytes', qr_choose_version(170), 9);
check('elige v10 para 200 bytes', qr_choose_version(200), 10);

// Tamaño de matriz.
check('size v1', qr_matrix_size(1), 21);
check('size v10', qr_matrix_size(10), 57);

// Centros de alineación.
check('align v7', qr_align_centers(7), [6,22,38]);
done();
```

- [ ] **Step 2: Ejecutar y ver que falla**

Run: `php tests/qr/test_tables.php`
Expected: función no definida.

- [ ] **Step 3: Implementar tablas y selección**

Añadir a `includes/qrcode.php`:
```php
/**
 * Características de corrección de errores, nivel M, versiones 1–10.
 * [version] => [ecCodewordsPorBloque, [[numBloques, dataCwPorBloque], ...]]
 * Valores de ISO/IEC 18004 (verificar contra la norma si algún test de matriz falla).
 */
function qr_ec_table(): array
{
    return [
        1  => [10, [[1, 16]]],
        2  => [16, [[1, 28]]],
        3  => [26, [[1, 44]]],
        4  => [18, [[2, 32]]],
        5  => [24, [[2, 43]]],
        6  => [16, [[4, 27]]],
        7  => [18, [[4, 31]]],
        8  => [22, [[2, 38], [2, 39]]],
        9  => [22, [[3, 36], [2, 37]]],
        10 => [24, [[4, 43], [1, 44]]],
    ];
}

/** Centros de patrones de alineación por versión (nivel-independiente). */
function qr_align_centers(int $version): array
{
    $t = [
        1 => [], 2 => [6,18], 3 => [6,22], 4 => [6,26], 5 => [6,30],
        6 => [6,34], 7 => [6,22,38], 8 => [6,24,42], 9 => [6,26,46], 10 => [6,28,50],
    ];
    return $t[$version];
}

/** Tamaño (módulos por lado) de la matriz de una versión. */
function qr_matrix_size(int $version): int
{
    return 17 + 4 * $version;
}

/** Codewords de datos totales de una versión (nivel M). */
function qr_data_codewords(int $version): int
{
    [, $groups] = qr_ec_table()[$version];
    $sum = 0;
    foreach ($groups as [$n, $cw]) $sum += $n * $cw;
    return $sum;
}

/** Capacidad en bytes (modo byte) de una versión, descontando cabecera. */
function qr_byte_capacity(int $version): int
{
    $ccBits = $version >= 10 ? 16 : 8;
    return intdiv(qr_data_codewords($version) * 8 - 4 - $ccBits, 8);
}

/** Menor versión (1–10) capaz de contener $byteLen bytes en modo byte. */
function qr_choose_version(int $byteLen): int
{
    for ($v = 1; $v <= 10; $v++) {
        if (qr_byte_capacity($v) >= $byteLen) return $v;
    }
    throw new RuntimeException('Texto demasiado largo para QR (máx v10/M)');
}
```

- [ ] **Step 4: Ejecutar y ver que pasa**

Run: `php tests/qr/test_tables.php`
Expected: `TODO OK`.

- [ ] **Step 5: Commit**

```bash
git add includes/qrcode.php tests/qr/test_tables.php
git commit -m "feat(qr): tablas del estándar y selección de versión"
```

---

### Task 4: Codificación de datos a codewords + interleaving de bloques

**Files:**
- Modify: `includes/qrcode.php`
- Test: `tests/qr/test_codewords.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/qr/test_codewords.php`:
```php
<?php
require __DIR__ . '/_assert.php';
require __DIR__ . '/../../includes/qrcode.php';

// "HELLO WORLD" en modo byte, v1-M: comprobamos longitud y cabecera.
$cw = qr_encode_data("HELLO WORLD", 1);
check('longitud = data codewords v1', count($cw), 16);
// Primer byte: modo byte (0100) + 4 bits altos de la longitud (11 = 0000 1011 -> alto nibble 0000)
// 0100 0000 = 0x40
check('primer codeword (modo+len alto)', $cw[0], 0x40);
// segundo: 4 bits bajos de longitud (1011) + 4 bits altos de 'H'(0x48=0100 1000) => 1011 0100 = 0xB4
check('segundo codeword', $cw[1], 0xB4);

// Interleaving en versión de un solo bloque = identidad data ++ ec.
$inter = qr_build_codewords("HELLO WORLD", 1);
check('total codewords v1 (16 data + 10 ec)', count($inter), 26);
check('primeros 16 = data', array_slice($inter, 0, 16), $cw);

// Versión multi-bloque (v8): longitud total correcta.
$txt = str_repeat("A", 120);
$inter8 = qr_build_codewords($txt, 8);
// total codewords v8 = 154 data + 4 bloques*22 ec = 154 + 88 = 242... 
// (v8: ec=22/bloque, 4 bloques) 
check('total codewords v8', count($inter8), 242);
done();
```

- [ ] **Step 2: Ejecutar y ver que falla**

Run: `php tests/qr/test_codewords.php`
Expected: función no definida.

- [ ] **Step 3: Implementar codificación + interleaving**

Añadir a `includes/qrcode.php`:
```php
/** Buffer de bits (MSB primero). */
final class QrBitBuffer
{
    public array $bits = [];
    public function put(int $value, int $len): void
    {
        for ($i = $len - 1; $i >= 0; $i--) {
            $this->bits[] = ($value >> $i) & 1;
        }
    }
    public function length(): int { return count($this->bits); }
}

/** Codifica $text (modo byte) a los codewords de datos de una versión. */
function qr_encode_data(string $text, int $version): array
{
    $dataCw = qr_data_codewords($version);
    $capacityBits = $dataCw * 8;
    $bb = new QrBitBuffer();
    $bb->put(0b0100, 4);                       // indicador de modo byte
    $ccBits = $version >= 10 ? 16 : 8;
    $bb->put(strlen($text), $ccBits);          // indicador de longitud
    $len = strlen($text);
    for ($i = 0; $i < $len; $i++) $bb->put(ord($text[$i]), 8);

    // Terminador (hasta 4 ceros, sin pasarse de capacidad)
    $remaining = $capacityBits - $bb->length();
    $bb->put(0, max(0, min(4, $remaining)));
    // Relleno hasta byte completo
    while ($bb->length() % 8 !== 0) $bb->bits[] = 0;
    // Bytes de relleno alternos 0xEC / 0x11
    $pad = [0xEC, 0x11]; $p = 0;
    while ($bb->length() < $capacityBits) { $bb->put($pad[$p % 2], 8); $p++; }

    // Empaquetar a codewords
    $cw = [];
    for ($i = 0; $i < $capacityBits; $i += 8) {
        $b = 0;
        for ($j = 0; $j < 8; $j++) $b = ($b << 1) | $bb->bits[$i + $j];
        $cw[] = $b;
    }
    return $cw;
}

/** Datos + EC, divididos en bloques e intercalados según el estándar. */
function qr_build_codewords(string $text, int $version): array
{
    $data = qr_encode_data($text, $version);
    [$ecPerBlock, $groups] = qr_ec_table()[$version];

    $blocks = [];
    $idx = 0;
    foreach ($groups as [$n, $cwPer]) {
        for ($b = 0; $b < $n; $b++) {
            $blockData = array_slice($data, $idx, $cwPer);
            $idx += $cwPer;
            $blocks[] = ['data' => $blockData, 'ec' => qr_rs_ec($blockData, $ecPerBlock)];
        }
    }

    $result = [];
    $maxData = 0;
    foreach ($blocks as $b) $maxData = max($maxData, count($b['data']));
    for ($i = 0; $i < $maxData; $i++) {
        foreach ($blocks as $b) {
            if ($i < count($b['data'])) $result[] = $b['data'][$i];
        }
    }
    for ($i = 0; $i < $ecPerBlock; $i++) {
        foreach ($blocks as $b) $result[] = $b['ec'][$i];
    }
    return $result;
}
```

- [ ] **Step 4: Ejecutar y ver que pasa**

Run: `php tests/qr/test_codewords.php`
Expected: `TODO OK`.

- [ ] **Step 5: Commit**

```bash
git add includes/qrcode.php tests/qr/test_codewords.php
git commit -m "feat(qr): codificación de datos modo byte e interleaving"
```

---

### Task 5: Matriz y patrones de función

**Files:**
- Modify: `includes/qrcode.php`
- Test: `tests/qr/test_matrix.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/qr/test_matrix.php`:
```php
<?php
require __DIR__ . '/_assert.php';
require __DIR__ . '/../../includes/qrcode.php';

$q = new QrMatrix(1);
qr_draw_function_patterns($q, 1);
$n = $q->n;

// Finder top-left: esquina (0,0)=1, anillo claro (1,1)=0, núcleo (3,3)=1.
check('finder TL borde', $q->m[0][0], 1);
check('finder TL anillo', $q->m[1][1], 0);
check('finder TL nucleo', $q->m[3][3], 1);

// Separadores: (7,0) debe ser 0 (separador), y función.
check('separador (7,0)', $q->m[7][0], 0);
check_true('separador es función', $q->f[7][0]);

// Timing: (6,8) alterna; col 8 par -> 1.
check('timing (6,8)', $q->m[6][8], 1);
check('timing (6,9)', $q->m[6][9], 0);

// Módulo oscuro: (4*1+9, 8) = (13,8) = 1.
check('modulo oscuro', $q->m[13][8], 1);

// Áreas de formato reservadas (función) pero aún sin valor de bit fijado por draw.
check_true('formato reservado (8,0)', $q->f[8][0]);

// Versión 1 no tiene alineación: (no hay centros) -> nada que comprobar salvo no romper.
check_true('matriz v1 tamaño', $n === 21);
done();
```

- [ ] **Step 2: Ejecutar y ver que falla**

Run: `php tests/qr/test_matrix.php`
Expected: clase/función no definida.

- [ ] **Step 3: Implementar matriz y patrones**

Añadir a `includes/qrcode.php`:
```php
/** Matriz QR: m = módulos (-1 vacío, 0/1), f = es módulo de función (reservado). */
final class QrMatrix
{
    public int $n;
    public array $m;
    public array $f;
    public function __construct(int $version)
    {
        $this->n = qr_matrix_size($version);
        $this->m = array_fill(0, $this->n, array_fill(0, $this->n, -1));
        $this->f = array_fill(0, $this->n, array_fill(0, $this->n, false));
    }
    public function set(int $r, int $c, int $v, bool $fn = true): void
    {
        if ($r < 0 || $c < 0 || $r >= $this->n || $c >= $this->n) return;
        $this->m[$r][$c] = $v;
        if ($fn) $this->f[$r][$c] = true;
    }
}

/** Coloca un patrón localizador 7x7 + separador en la esquina (row,col). */
function qr_place_finder(QrMatrix $q, int $row, int $col): void
{
    for ($r = -1; $r <= 7; $r++) {
        for ($c = -1; $c <= 7; $c++) {
            $border = ($r >= 0 && $r <= 6 && ($c === 0 || $c === 6))
                   || ($c >= 0 && $c <= 6 && ($r === 0 || $r === 6));
            $core = ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4);
            $q->set($row + $r, $col + $c, ($border || $core) ? 1 : 0);
        }
    }
}

/** Coloca un patrón de alineación 5x5 centrado en (cr,cc). */
function qr_place_alignment(QrMatrix $q, int $cr, int $cc): void
{
    for ($r = -2; $r <= 2; $r++) {
        for ($c = -2; $c <= 2; $c++) {
            $d = max(abs($r), abs($c));   // 0 centro, 1 anillo claro, 2 borde oscuro
            $q->set($cr + $r, $cc + $c, ($d === 1) ? 0 : 1);
        }
    }
}

/** Dibuja todos los patrones de función y reserva las áreas de formato/versión. */
function qr_draw_function_patterns(QrMatrix $q, int $version): void
{
    $n = $q->n;

    // Localizadores en 3 esquinas (incluye separadores).
    qr_place_finder($q, 0, 0);
    qr_place_finder($q, 0, $n - 7);
    qr_place_finder($q, $n - 7, 0);

    // Timing.
    for ($i = 8; $i < $n - 8; $i++) {
        $bit = ($i % 2 === 0) ? 1 : 0;
        if ($q->m[6][$i] === -1) $q->set(6, $i, $bit);
        if ($q->m[$i][6] === -1) $q->set($i, 6, $bit);
    }

    // Alineación (salta las 3 que solapan localizadores).
    $centers = qr_align_centers($version);
    $maxC = $centers ? end($centers) : 0;
    foreach ($centers as $r) {
        foreach ($centers as $c) {
            if (($r === 6 && $c === 6) || ($r === 6 && $c === $maxC) || ($r === $maxC && $c === 6)) {
                continue;
            }
            qr_place_alignment($q, $r, $c);
        }
    }

    // Módulo oscuro (siempre 1).
    $q->set(4 * $version + 9, 8, 1);

    // Reservar áreas de información de formato (15 bits, dos copias).
    for ($i = 0; $i <= 5; $i++) $q->set(8, $i, 0);
    $q->set(8, 7, 0); $q->set(8, 8, 0); $q->set(7, 8, 0);
    for ($i = 9; $i < 15; $i++) $q->set(14 - $i, 8, 0);
    for ($i = 0; $i < 8; $i++) $q->set($n - 1 - $i, 8, 0);
    for ($i = 8; $i < 15; $i++) $q->set(8, $n - 15 + $i, 0);

    // Reservar áreas de información de versión (v>=7): dos bloques 3x6.
    if ($version >= 7) {
        for ($i = 0; $i < 18; $i++) {
            $a = $n - 11 + $i % 3;
            $b = intdiv($i, 3);
            $q->set($a, $b, 0);
            $q->set($b, $a, 0);
        }
    }
}
```

- [ ] **Step 4: Ejecutar y ver que pasa**

Run: `php tests/qr/test_matrix.php`
Expected: `TODO OK`.

- [ ] **Step 5: Commit**

```bash
git add includes/qrcode.php tests/qr/test_matrix.php
git commit -m "feat(qr): matriz y patrones de función"
```

---

### Task 6: Colocación de datos (zigzag)

**Files:**
- Modify: `includes/qrcode.php`
- Test: `tests/qr/test_placement.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/qr/test_placement.php`:
```php
<?php
require __DIR__ . '/_assert.php';
require __DIR__ . '/../../includes/qrcode.php';

$version = 1;
$q = new QrMatrix($version);
qr_draw_function_patterns($q, $version);
$cw = qr_build_codewords("HELLO WORLD", $version); // 26 codewords
qr_place_data($q, $cw);

// Tras colocar datos, no debe quedar ningún módulo de NO-función sin asignar.
$vacios = 0;
for ($r = 0; $r < $q->n; $r++)
    for ($c = 0; $c < $q->n; $c++)
        if (!$q->f[$r][$c] && $q->m[$r][$c] === -1) $vacios++;
check('sin modulos de datos vacios', $vacios, 0);

// El primer bit de datos se coloca en la esquina inferior derecha (n-1,n-1).
// Primer codeword 0x40 = 0100 0000, bit MSB = 0.
check('primer modulo datos (n-1,n-1)', $q->m[$q->n - 1][$q->n - 1], 0);
done();
```

- [ ] **Step 2: Ejecutar y ver que falla**

Run: `php tests/qr/test_placement.php`
Expected: función no definida.

- [ ] **Step 3: Implementar colocación zigzag**

Añadir a `includes/qrcode.php`:
```php
/** Coloca el flujo de codewords (MSB primero) en la matriz, en zigzag. */
function qr_place_data(QrMatrix $q, array $codewords): void
{
    $n = $q->n;
    // Flujo de bits MSB primero.
    $bits = [];
    foreach ($codewords as $cw) {
        for ($i = 7; $i >= 0; $i--) $bits[] = ($cw >> $i) & 1;
    }
    $total = count($bits);
    $idx = 0;

    for ($right = $n - 1; $right >= 1; $right -= 2) {
        if ($right === 6) $right = 5; // saltar la columna de timing
        for ($vert = 0; $vert < $n; $vert++) {
            for ($j = 0; $j < 2; $j++) {
                $col = $right - $j;
                $upward = ((($right + 1) & 2) === 0);
                $row = $upward ? ($n - 1 - $vert) : $vert;
                if ($q->f[$row][$col]) continue;          // saltar función
                $q->m[$row][$col] = ($idx < $total) ? $bits[$idx] : 0; // bits sobrantes = 0
                $idx++;
            }
        }
    }
}
```

- [ ] **Step 4: Ejecutar y ver que pasa**

Run: `php tests/qr/test_placement.php`
Expected: `TODO OK`.

- [ ] **Step 5: Commit**

```bash
git add includes/qrcode.php tests/qr/test_placement.php
git commit -m "feat(qr): colocación de datos en zigzag"
```

---

### Task 7: Máscaras y selección de la óptima

**Files:**
- Modify: `includes/qrcode.php`
- Test: `tests/qr/test_mask.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/qr/test_mask.php`:
```php
<?php
require __DIR__ . '/_assert.php';
require __DIR__ . '/../../includes/qrcode.php';

// Fórmula de máscara 0: (r+c)%2==0.
check('mask0 (0,0)', qr_mask_condition(0, 0, 0), true);
check('mask0 (0,1)', qr_mask_condition(0, 0, 1), false);
// Máscara 1: r%2==0.
check('mask1 (0,5)', qr_mask_condition(1, 0, 5), true);
check('mask1 (1,5)', qr_mask_condition(1, 1, 5), false);
// Máscara 2: c%3==0.
check('mask2 (4,3)', qr_mask_condition(2, 4, 3), true);

// Selección: devuelve un índice 0..7.
$version = 1;
$q = new QrMatrix($version);
qr_draw_function_patterns($q, $version);
qr_place_data($q, qr_build_codewords("HELLO WORLD", $version));
$mask = qr_apply_best_mask($q);
check_true('mascara en rango', $mask >= 0 && $mask <= 7);
done();
```

- [ ] **Step 2: Ejecutar y ver que falla**

Run: `php tests/qr/test_mask.php`
Expected: función no definida.

- [ ] **Step 3: Implementar máscaras**

Añadir a `includes/qrcode.php`:
```php
/** Condición de máscara $mask en (r,c). */
function qr_mask_condition(int $mask, int $r, int $c): bool
{
    switch ($mask) {
        case 0: return (($r + $c) % 2) === 0;
        case 1: return ($r % 2) === 0;
        case 2: return ($c % 3) === 0;
        case 3: return (($r + $c) % 3) === 0;
        case 4: return ((intdiv($r, 2) + intdiv($c, 3)) % 2) === 0;
        case 5: return ((($r * $c) % 2) + (($r * $c) % 3)) === 0;
        case 6: return (((($r * $c) % 2) + (($r * $c) % 3)) % 2) === 0;
        case 7: return (((($r + $c) % 2) + (($r * $c) % 3)) % 2) === 0;
    }
    return false;
}

/** Penalización de una matriz ya enmascarada (4 reglas del estándar). */
function qr_penalty(QrMatrix $q): int
{
    $n = $q->n; $m = $q->m; $p = 0;

    // Regla 1: 5+ módulos iguales consecutivos (filas y columnas).
    for ($r = 0; $r < $n; $r++) {
        $run = 1;
        for ($c = 1; $c < $n; $c++) {
            if ($m[$r][$c] === $m[$r][$c - 1]) { $run++; if ($run === 5) $p += 3; elseif ($run > 5) $p++; }
            else $run = 1;
        }
    }
    for ($c = 0; $c < $n; $c++) {
        $run = 1;
        for ($r = 1; $r < $n; $r++) {
            if ($m[$r][$c] === $m[$r - 1][$c]) { $run++; if ($run === 5) $p += 3; elseif ($run > 5) $p++; }
            else $run = 1;
        }
    }

    // Regla 2: bloques 2x2 del mismo color.
    for ($r = 0; $r < $n - 1; $r++) {
        for ($c = 0; $c < $n - 1; $c++) {
            $v = $m[$r][$c];
            if ($v === $m[$r][$c + 1] && $v === $m[$r + 1][$c] && $v === $m[$r + 1][$c + 1]) $p += 3;
        }
    }

    // Regla 3: patrón 1:1:3:1:1 (10111010000 o su espejo) en filas y columnas.
    $pat1 = [1,0,1,1,1,0,1,0,0,0,0];
    $pat2 = [0,0,0,0,1,0,1,1,1,0,1];
    for ($r = 0; $r < $n; $r++) {
        for ($c = 0; $c <= $n - 11; $c++) {
            $ok1 = true; $ok2 = true;
            for ($k = 0; $k < 11; $k++) {
                if ($m[$r][$c + $k] !== $pat1[$k]) $ok1 = false;
                if ($m[$r][$c + $k] !== $pat2[$k]) $ok2 = false;
            }
            if ($ok1 || $ok2) $p += 40;
        }
    }
    for ($c = 0; $c < $n; $c++) {
        for ($r = 0; $r <= $n - 11; $r++) {
            $ok1 = true; $ok2 = true;
            for ($k = 0; $k < 11; $k++) {
                if ($m[$r + $k][$c] !== $pat1[$k]) $ok1 = false;
                if ($m[$r + $k][$c] !== $pat2[$k]) $ok2 = false;
            }
            if ($ok1 || $ok2) $p += 40;
        }
    }

    // Regla 4: balance de módulos oscuros.
    $dark = 0;
    for ($r = 0; $r < $n; $r++) for ($c = 0; $c < $n; $c++) if ($m[$r][$c] === 1) $dark++;
    $ratio = ($dark * 100) / ($n * $n);
    $p += (int) (abs($ratio - 50) / 5) * 10;

    return $p;
}

/** Aplica las 8 máscaras (solo a módulos de datos), elige la de menor penalización. */
function qr_apply_best_mask(QrMatrix $q): int
{
    $n = $q->n;
    $best = -1; $bestPenalty = PHP_INT_MAX;
    $original = $q->m;

    for ($mask = 0; $mask < 8; $mask++) {
        $q->m = $original;
        for ($r = 0; $r < $n; $r++) {
            for ($c = 0; $c < $n; $c++) {
                if (!$q->f[$r][$c] && qr_mask_condition($mask, $r, $c)) {
                    $q->m[$r][$c] ^= 1;
                }
            }
        }
        $pen = qr_penalty($q);
        if ($pen < $bestPenalty) { $bestPenalty = $pen; $best = $mask; }
    }

    // Reaplicar la mejor máscara definitivamente.
    $q->m = $original;
    for ($r = 0; $r < $n; $r++) {
        for ($c = 0; $c < $n; $c++) {
            if (!$q->f[$r][$c] && qr_mask_condition($best, $r, $c)) {
                $q->m[$r][$c] ^= 1;
            }
        }
    }
    return $best;
}
```

- [ ] **Step 4: Ejecutar y ver que pasa**

Run: `php tests/qr/test_mask.php`
Expected: `TODO OK`.

- [ ] **Step 5: Commit**

```bash
git add includes/qrcode.php tests/qr/test_mask.php
git commit -m "feat(qr): mascaras y seleccion de la optima"
```

---

### Task 8: Información de formato y versión (BCH)

**Files:**
- Modify: `includes/qrcode.php`
- Test: `tests/qr/test_format.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/qr/test_format.php`:
```php
<?php
require __DIR__ . '/_assert.php';
require __DIR__ . '/../../includes/qrcode.php';

// Cadenas de formato conocidas (nivel M), tras BCH + XOR 0x5412.
check('formato M mask0', qr_format_bits(0), 0x5412);
check('formato M mask1', qr_format_bits(1), 0x5125);
check('formato M mask7', qr_format_bits(7), 0x4AA0);

// Información de versión conocida.
check('version bits 7', qr_version_bits(7), 0x07C94);
check('version bits 10', qr_version_bits(10), 0x0A4D3);

// Colocación: tras escribir formato, (8,0) toma el bit 0 del valor.
$version = 1;
$q = new QrMatrix($version);
qr_draw_function_patterns($q, $version);
qr_place_data($q, qr_build_codewords("HELLO WORLD", $version));
$mask = qr_apply_best_mask($q);
qr_draw_format_version($q, $version, $mask);
$fmt = qr_format_bits($mask);
check('formato bit0 en (8,0)', $q->m[8][0], $fmt & 1);
done();
```

- [ ] **Step 2: Ejecutar y ver que falla**

Run: `php tests/qr/test_format.php`
Expected: función no definida.

- [ ] **Step 3: Implementar formato/versión**

Añadir a `includes/qrcode.php`:
```php
/** Cadena de 15 bits de información de formato (nivel M + máscara), con BCH y XOR. */
function qr_format_bits(int $mask): int
{
    $data = $mask;                 // nivel M = prefijo 00; data = 000 + 0b00 + mask = mask
    $rem = $data;
    for ($i = 0; $i < 10; $i++) {
        $rem = ($rem << 1) ^ ((($rem >> 9) & 1) * 0x537);
    }
    return ((($data << 10) | $rem) ^ 0x5412) & 0x7FFF;
}

/** Cadena de 18 bits de información de versión (v>=7), con BCH. */
function qr_version_bits(int $version): int
{
    $rem = $version;
    for ($i = 0; $i < 12; $i++) {
        $rem = ($rem << 1) ^ ((($rem >> 11) & 1) * 0x1F25);
    }
    return ($version << 12) | $rem;
}

/** Escribe la información de formato (dos copias) y de versión (v>=7). */
function qr_draw_format_version(QrMatrix $q, int $version, int $mask): void
{
    $n = $q->n;
    $fmt = qr_format_bits($mask);
    $bit = fn(int $x, int $i): int => ($x >> $i) & 1;

    // Copia 1 (alrededor del localizador superior izquierdo).
    for ($i = 0; $i <= 5; $i++) $q->m[8][$i] = $bit($fmt, $i);
    $q->m[8][7] = $bit($fmt, 6);
    $q->m[8][8] = $bit($fmt, 7);
    $q->m[7][8] = $bit($fmt, 8);
    for ($i = 9; $i < 15; $i++) $q->m[14 - $i][8] = $bit($fmt, $i);

    // Copia 2 (debajo del localizador superior derecho / a la derecha del inferior izquierdo).
    for ($i = 0; $i < 8; $i++) $q->m[$n - 1 - $i][8] = $bit($fmt, $i);
    for ($i = 8; $i < 15; $i++) $q->m[8][$n - 15 + $i] = $bit($fmt, $i);

    // Información de versión (v>=7).
    if ($version >= 7) {
        $vb = qr_version_bits($version);
        for ($i = 0; $i < 18; $i++) {
            $b = $bit($vb, $i);
            $a = $n - 11 + $i % 3;
            $d = intdiv($i, 3);
            $q->m[$a][$d] = $b;
            $q->m[$d][$a] = $b;
        }
    }
}
```

- [ ] **Step 4: Ejecutar y ver que pasa**

Run: `php tests/qr/test_format.php`
Expected: `TODO OK`. (Si `formato M mask0` no diera 0x5412, el bug está en el BCH o el XOR.)

- [ ] **Step 5: Commit**

```bash
git add includes/qrcode.php tests/qr/test_format.php
git commit -m "feat(qr): informacion de formato y version (BCH)"
```

---

### Task 9: API pública `qr_encode_svg()`

**Files:**
- Modify: `includes/qrcode.php`
- Test: `tests/qr/test_svg.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/qr/test_svg.php`:
```php
<?php
require __DIR__ . '/_assert.php';
require __DIR__ . '/../../includes/qrcode.php';

$svg = qr_encode_svg("HELLO WORLD");
check_true('es SVG', str_starts_with($svg, '<svg'));
check_true('tiene viewBox', str_contains($svg, 'viewBox'));
check_true('tiene path oscuro', str_contains($svg, '<path'));
check_true('cierra svg', str_ends_with(trim($svg), '</svg>'));

// otpauth largo cabe sin excepción.
$uri = 'otpauth://totp/Tarancon%20Admin:admin@tarancon.es?secret=' . str_repeat('A', 32)
     . '&issuer=Tarancon%20Admin&algorithm=SHA1&digits=6&period=30';
$svg2 = qr_encode_svg($uri);
check_true('otpauth genera SVG', str_starts_with($svg2, '<svg'));

// Texto absurdamente largo lanza excepción.
$ex = false;
try { qr_encode_svg(str_repeat('A', 5000)); } catch (RuntimeException $e) { $ex = true; }
check_true('texto enorme lanza excepcion', $ex);
done();
```

- [ ] **Step 2: Ejecutar y ver que falla**

Run: `php tests/qr/test_svg.php`
Expected: función no definida.

- [ ] **Step 3: Implementar API pública + SVG**

Añadir a `includes/qrcode.php`:
```php
/** Construye la matriz QR final (módulos 0/1) para un texto. */
function qr_build_matrix(string $text): QrMatrix
{
    $version = qr_choose_version(strlen($text));
    $q = new QrMatrix($version);
    qr_draw_function_patterns($q, $version);
    qr_place_data($q, qr_build_codewords($text, $version));
    $mask = qr_apply_best_mask($q);
    qr_draw_format_version($q, $version, $mask);
    return $q;
}

/**
 * Codifica $text en un QR y devuelve un documento SVG.
 * @param int $moduleSize px por módulo. @param int $margin módulos de "quiet zone".
 */
function qr_encode_svg(string $text, int $moduleSize = 6, int $margin = 4): string
{
    $q = qr_build_matrix($text);
    $n = $q->n;
    $dim = ($n + 2 * $margin) * $moduleSize;

    $rects = '';
    for ($r = 0; $r < $n; $r++) {
        for ($c = 0; $c < $n; $c++) {
            if ($q->m[$r][$c] === 1) {
                $x = ($c + $margin) * $moduleSize;
                $y = ($r + $margin) * $moduleSize;
                $rects .= "M{$x} {$y}h{$moduleSize}v{$moduleSize}h-{$moduleSize}z";
            }
        }
    }

    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $dim . '" height="' . $dim
        . '" viewBox="0 0 ' . $dim . ' ' . $dim . '" shape-rendering="crispEdges" role="img" '
        . 'aria-label="Código QR de configuración 2FA">'
        . '<rect width="100%" height="100%" fill="#ffffff"/>'
        . '<path d="' . $rects . '" fill="#000000"/>'
        . '</svg>';
}
```

- [ ] **Step 4: Ejecutar y ver que pasa**

Run: `php tests/qr/test_svg.php`
Expected: `TODO OK`.

- [ ] **Step 5: Verificar sintaxis y commit**

```bash
php -l includes/qrcode.php
git add includes/qrcode.php tests/qr/test_svg.php
git commit -m "feat(qr): API publica qr_encode_svg"
```

---

### Task 10: Integrar en `totp.php` y endurecer la CSP

**Files:**
- Modify: `includes/totp.php:98-107` (función `totp_qr_url` + `require_once`)
- Modify: `config.php:136` (`img-src`)
- Test: `tests/qr/test_totp_integration.php`

- [ ] **Step 1: Escribir el test que falla**

`tests/qr/test_totp_integration.php`:
```php
<?php
require __DIR__ . '/_assert.php';
require __DIR__ . '/../../includes/totp.php';

$uri = totp_provisioning_uri('Tarancon Admin', 'admin@tarancon.es', str_repeat('A', 32));
$qr = totp_qr_url($uri);
check_true('es data URI SVG', str_starts_with($qr, 'data:image/svg+xml;base64,'));
// No debe referenciar quickchart.
check_true('no menciona quickchart', !str_contains($qr, 'quickchart'));
// El SVG decodificado es válido.
$svg = base64_decode(substr($qr, strlen('data:image/svg+xml;base64,')));
check_true('contiene <svg', str_starts_with($svg, '<svg'));
done();
```

- [ ] **Step 2: Ejecutar y ver que falla**

Run: `php tests/qr/test_totp_integration.php`
Expected: `FAIL` (aún devuelve URL de quickchart).

- [ ] **Step 3: Modificar `totp.php`**

En `includes/totp.php`, añadir el require al principio (tras la cabecera de comentario, antes de la primera función):
```php
require_once __DIR__ . '/qrcode.php';
```

Reemplazar la función `totp_qr_url()` (líneas ~98-107) por:
```php
/**
 * Devuelve un data: URI con el QR del otpauth URI, generado en el servidor.
 * El secreto NO sale del servidor: el SVG se embebe directamente en la página.
 */
function totp_qr_url(string $otpauthUri): string
{
    return 'data:image/svg+xml;base64,' . base64_encode(qr_encode_svg($otpauthUri));
}
```

- [ ] **Step 4: Modificar la CSP en `config.php`**

`config.php:136`, reemplazar:
```php
        "img-src 'self' data: https://quickchart.io; " .
```
por:
```php
        "img-src 'self' data:; " .
```

- [ ] **Step 5: Ejecutar test y sintaxis**

Run: `php tests/qr/test_totp_integration.php`
Expected: `TODO OK`.

Run: `php -l includes/totp.php && php -l config.php`
Expected: `No syntax errors detected` en ambos.

- [ ] **Step 6: Commit**

```bash
git add includes/totp.php config.php tests/qr/test_totp_integration.php
git commit -m "feat(2fa): QR autohospedado en totp_qr_url + endurecer CSP (quitar quickchart.io)"
```

---

### Task 11: Verificación round-trip (GATE) y limpieza de cumplimiento

**Files:**
- Create (temporal): `tests/qr/_dump.php`
- Modify: `html/politica-privacidad.php`, `html/politica-cookies.php` (condicional)

**Esta task BLOQUEA el cierre. No marcar el plan como completo sin un round-trip de decodificación correcto.**

- [ ] **Step 1: Generar un SVG real para inspección**

`tests/qr/_dump.php`:
```php
<?php
require __DIR__ . '/../../includes/totp.php';
$uri = totp_provisioning_uri('Tarancon Admin', 'admin@tarancon.es', 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP');
echo "URI: $uri\n";
file_put_contents(__DIR__ . '/_qr.svg', qr_encode_svg($uri));
echo "SVG escrito en tests/qr/_qr.svg\n";
```

Run: `php tests/qr/_dump.php`
Expected: imprime el `otpauth://` URI y escribe `tests/qr/_qr.svg`.

- [ ] **Step 2: Decodificar el QR de forma independiente (round-trip)**

Opción A (preferida, si está disponible): convertir el SVG a PNG y decodificar con `zbarimg`/`qrencode` o cualquier lector instalado, comparando el resultado con el `otpauth://` URI impreso.

Opción B (sin herramientas locales): usar el skill **`/browse`** para abrir un decodificador QR online (p. ej. `https://zxing.org/w/decode` u otro lector), subir/abrir `tests/qr/_qr.svg` (convertido a PNG si el lector lo exige) y leer el texto decodificado.

**Criterio de aceptación:** el texto decodificado es **exactamente** el `otpauth://` URI generado. Si no coincide:
- Revisar primero Task 8 (formato/versión) y Task 6 (zigzag), las áreas más frágiles.
- Re-ejecutar la batería: `for f in tests/qr/test_*.php; do php "$f" || break; done` (en PowerShell: `Get-ChildItem tests/qr/test_*.php | % { php $_.FullName }`).
- No continuar hasta lograr el round-trip.

- [ ] **Step 3: Verificación visual del flujo real**

Con el skill `/browse` (o sirviendo el proyecto con `php -S localhost:8000`), abrir `admin/admin-2fa-setup.php` autenticado como admin, pulsar "Iniciar configuración" y confirmar:
- El `<img id="qr-img">` muestra un QR nítido.
- En DevTools (Network) **no hay** petición a `quickchart.io`.
- En la consola **no hay** violaciones de CSP.

- [ ] **Step 4: Limpieza de cumplimiento (mención a quickchart)**

Buscar referencias a quickchart en las políticas legales:
Run: `grep -rni quickchart html/ docs/ SECURITY.md 2>/dev/null`
- Si aparece en `html/politica-privacidad.php` o `html/politica-cookies.php` como transferencia/tercero, **eliminar esa mención** (ya no se usa). Es una mejora de cumplimiento.
- Si no aparece, no hacer nada.

- [ ] **Step 5: Borrar artefactos temporales de test**

```bash
rm -f tests/qr/_dump.php tests/qr/_qr.svg tests/qr/_qr.png
```

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "test(qr): verificacion round-trip + limpieza de mencion a quickchart"
```

---

### Task 12: Documentación

**Files:**
- Modify: `docs/CHANGELOG.md`
- Modify: `PLAN.md`

- [ ] **Step 1: CHANGELOG**

En `docs/CHANGELOG.md`, añadir una sección `## [Unreleased]` (o versión patch `3.1.1`) con:
```markdown
### Cambiado 🔄
- **QR del 2FA autohospedado**: `includes/qrcode.php` genera el código QR de
  configuración TOTP en el servidor (PHP puro, modo byte, nivel M, v1–10) como
  `data:` URI. Elimina la dependencia de `quickchart.io`, que recibía el secreto
  TOTP del administrador en la query (fuga de secreto + transferencia internacional
  no contemplada). Cierra el ítem P1 correspondiente.

### Seguridad 🔒
- **CSP endurecida**: eliminado `https://quickchart.io` de `img-src`.
```

- [ ] **Step 2: PLAN.md**

En `PLAN.md`, marcar el ítem P1:
```markdown
- [x] **Autohospedar el generador de QR del 2FA** (alternativa a quickchart.io) — `includes/qrcode.php`, PHP puro, sin dependencias.
```

- [ ] **Step 3: Commit**

```bash
git add docs/CHANGELOG.md PLAN.md
git commit -m "docs: changelog y PLAN para QR del 2FA autohospedado"
```

---

## Self-Review (completado al escribir el plan)

- **Cobertura del spec:** módulo `includes/qrcode.php` (Tasks 1–9) ✓; `totp_qr_url` data URI (Task 10) ✓; CSP sin quickchart (Task 10) ✓; manejo de error por exceso de longitud (Task 9, `qr_choose_version` lanza) ✓; verificación round-trip sin phone (Task 11) ✓; docs y política de privacidad (Tasks 11-12) ✓; "fuera de alcance" respetado (sin PNG, sin caché, sin tocar verificación TOTP, sin tocar `'unsafe-inline'`) ✓.
- **Consistencia de tipos/nombres:** `QrMatrix` (props `n`,`m`,`f`, método `set`) usada igual en Tasks 5–9; `qr_build_codewords`/`qr_place_data`/`qr_apply_best_mask`/`qr_draw_format_version`/`qr_build_matrix`/`qr_encode_svg` con firmas coherentes entre tasks ✓.
- **Placeholders:** ninguno; todo el código está completo. La única acción no-código es la decodificación externa de Task 11 (inevitable: es la verificación de scan real).
- **Riesgo residual:** correcto-por-tablas; mitigado con tests por capa contra vectores publicados + gate de round-trip en Task 11.
