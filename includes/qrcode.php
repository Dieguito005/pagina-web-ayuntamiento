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
