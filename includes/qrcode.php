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
