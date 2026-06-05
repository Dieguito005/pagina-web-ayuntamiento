<?php
/**
 * totp.php — Implementación TOTP (RFC 6238) sin dependencias externas.
 *
 * Compatible con Google Authenticator, Authy, Microsoft Authenticator, 1Password, etc.
 * Defaults: SHA-1, 6 dígitos, periodo 30 segundos, ventana de tolerancia ±1.
 *
 * Uso:
 *   $secret = totp_generate_secret();                       // base32, 32 chars
 *   $uri = totp_provisioning_uri('Tarancon Admin', $usuario, $secret);
 *   $ok  = totp_verify($secret, $codigoIntroducidoPorUsuario);
 */

require_once __DIR__ . '/qrcode.php';

/** Genera un secreto Base32 de 32 caracteres (160 bits, recomendado por RFC). */
function totp_generate_secret(int $length = 32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $alphabet[random_int(0, 31)];
    }
    return $secret;
}

/** Decodifica Base32 (RFC 4648) a binario. Devuelve null si la cadena es inválida. */
function totp_base32_decode(string $input): ?string
{
    $input = strtoupper(rtrim($input, '='));
    if ($input === '') return '';
    if (!preg_match('/^[A-Z2-7]+$/', $input)) return null;

    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($input) as $c) {
        $bits .= str_pad(decbin(strpos($alphabet, $c)), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    for ($i = 0, $n = strlen($bits) - (strlen($bits) % 8); $i < $n; $i += 8) {
        $bytes .= chr(bindec(substr($bits, $i, 8)));
    }
    return $bytes;
}

/**
 * Calcula el código TOTP para un timestamp dado.
 */
function totp_at(string $secretBase32, int $timestamp, int $period = 30, int $digits = 6, string $algo = 'sha1'): ?string
{
    $key = totp_base32_decode($secretBase32);
    if ($key === null || $key === '') return null;

    $counter = intdiv($timestamp, $period);
    // Counter en formato 64-bit big-endian
    $counterBin = pack('N*', 0, $counter);
    $hmac = hash_hmac($algo, $counterBin, $key, true);
    $offset = ord($hmac[strlen($hmac) - 1]) & 0x0F;
    $code = (
        ((ord($hmac[$offset]) & 0x7F) << 24) |
        ((ord($hmac[$offset + 1]) & 0xFF) << 16) |
        ((ord($hmac[$offset + 2]) & 0xFF) << 8) |
        (ord($hmac[$offset + 3]) & 0xFF)
    ) % (10 ** $digits);
    return str_pad((string) $code, $digits, '0', STR_PAD_LEFT);
}

/**
 * Verifica un código contra el secreto, con tolerancia de ventana.
 * `$window` = 1 acepta el código actual y los dos vecinos (±30s).
 */
function totp_verify(string $secretBase32, string $code, int $window = 1, int $period = 30, int $digits = 6): bool
{
    $code = preg_replace('/\s+/', '', $code);
    if (!preg_match('/^\d{' . $digits . '}$/', $code)) return false;
    $now = time();
    for ($i = -$window; $i <= $window; $i++) {
        $expected = totp_at($secretBase32, $now + ($i * $period), $period, $digits);
        if ($expected !== null && hash_equals($expected, $code)) return true;
    }
    return false;
}

/**
 * Construye el otpauth:// URI para que el usuario lo escanee como QR.
 */
function totp_provisioning_uri(string $issuer, string $account, string $secretBase32): string
{
    $label = rawurlencode($issuer) . ':' . rawurlencode($account);
    $params = http_build_query([
        'secret' => $secretBase32,
        'issuer' => $issuer,
        'algorithm' => 'SHA1',
        'digits' => 6,
        'period' => 30,
    ]);
    return "otpauth://totp/$label?$params";
}

/**
 * Devuelve un data: URI con el QR del otpauth URI, generado en el servidor.
 * El secreto NO sale del servidor: el SVG se embebe directamente en la página.
 */
function totp_qr_url(string $otpauthUri): string
{
    return 'data:image/svg+xml;base64,' . base64_encode(qr_encode_svg($otpauthUri));
}
