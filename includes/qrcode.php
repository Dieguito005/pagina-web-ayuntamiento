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
        10 => [26, [[4, 43], [1, 44]]],
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

    qr_place_finder($q, 0, 0);
    qr_place_finder($q, 0, $n - 7);
    qr_place_finder($q, $n - 7, 0);

    for ($i = 8; $i < $n - 8; $i++) {
        $bit = ($i % 2 === 0) ? 1 : 0;
        if ($q->m[6][$i] === -1) $q->set(6, $i, $bit);
        if ($q->m[$i][6] === -1) $q->set($i, 6, $bit);
    }

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

    // Reservar área de información de formato (L alrededor del localizador superior izq.).
    for ($i = 0; $i <= 5; $i++) $q->set($i, 8, 0);           // col 8, filas 0-5
    $q->set(7, 8, 0); $q->set(8, 8, 0); $q->set(8, 7, 0);
    for ($i = 9; $i < 15; $i++) $q->set(8, 14 - $i, 0);      // fila 8, cols 5-0
    // Segunda copia.
    for ($i = 0; $i < 8; $i++) $q->set(8, $n - 1 - $i, 0);   // fila 8, cols n-1..n-8
    for ($i = 8; $i < 15; $i++) $q->set($n - 15 + $i, 8, 0); // col 8, filas n-7..n-1

    // Módulo oscuro: siempre 1, en (n-8, 8).
    $q->set($n - 8, 8, 1);

    if ($version >= 7) {
        for ($i = 0; $i < 18; $i++) {
            $a = $n - 11 + $i % 3;
            $b = intdiv($i, 3);
            $q->set($a, $b, 0);
            $q->set($b, $a, 0);
        }
    }
}

/** Coloca el flujo de codewords (MSB primero) en la matriz, en zigzag. */
function qr_place_data(QrMatrix $q, array $codewords): void
{
    $n = $q->n;
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

    // Regla 3: patrón 1:1:3:1:1 (y su espejo) en filas y columnas.
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

/** Cadena de 15 bits de información de formato (nivel M + máscara), con BCH y XOR. */
function qr_format_bits(int $mask): int
{
    $data = $mask;                 // nivel M = prefijo 00; data = 0b00 + mask = mask
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
    for ($i = 0; $i <= 5; $i++) $q->m[$i][8] = $bit($fmt, $i);       // col 8, filas 0-5
    $q->m[7][8] = $bit($fmt, 6);
    $q->m[8][8] = $bit($fmt, 7);
    $q->m[8][7] = $bit($fmt, 8);
    for ($i = 9; $i < 15; $i++) $q->m[8][14 - $i] = $bit($fmt, $i);  // fila 8, cols 5-0

    // Copia 2.
    for ($i = 0; $i < 8; $i++) $q->m[8][$n - 1 - $i] = $bit($fmt, $i);    // fila 8, cols n-1..n-8
    for ($i = 8; $i < 15; $i++) $q->m[$n - 15 + $i][8] = $bit($fmt, $i);  // col 8, filas n-7..n-1
    // El módulo oscuro (n-8,8) ya está fijado por qr_draw_function_patterns.

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
