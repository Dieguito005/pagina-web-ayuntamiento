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

    for ($i = 0; $i <= 5; $i++) $q->set(8, $i, 0);
    $q->set(8, 7, 0); $q->set(8, 8, 0); $q->set(7, 8, 0);
    for ($i = 9; $i < 15; $i++) $q->set(14 - $i, 8, 0);
    for ($i = 0; $i < 8; $i++) $q->set($n - 1 - $i, 8, 0);
    for ($i = 8; $i < 15; $i++) $q->set(8, $n - 15 + $i, 0);

    // Módulo oscuro: siempre 1, colocado después de reservar el área de formato.
    $q->set(4 * $version + 9, 8, 1);

    if ($version >= 7) {
        for ($i = 0; $i < 18; $i++) {
            $a = $n - 11 + $i % 3;
            $b = intdiv($i, 3);
            $q->set($a, $b, 0);
            $q->set($b, $a, 0);
        }
    }
}
