<?php
require __DIR__ . '/_assert.php';
require __DIR__ . '/../../includes/qrcode.php';

$svg = qr_encode_svg("HELLO WORLD");
check_true('es SVG', str_starts_with($svg, '<svg'));
check_true('tiene viewBox', str_contains($svg, 'viewBox'));
check_true('tiene path oscuro', str_contains($svg, '<path'));
check_true('cierra svg', str_ends_with(trim($svg), '</svg>'));

$uri = 'otpauth://totp/Tarancon%20Admin:admin@tarancon.es?secret=' . str_repeat('A', 32)
     . '&issuer=Tarancon%20Admin&algorithm=SHA1&digits=6&period=30';
$svg2 = qr_encode_svg($uri);
check_true('otpauth genera SVG', str_starts_with($svg2, '<svg'));

$ex = false;
try { qr_encode_svg(str_repeat('A', 5000)); } catch (RuntimeException $e) { $ex = true; }
check_true('texto enorme lanza excepcion', $ex);
done();
