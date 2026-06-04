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

// Colocación correcta (layout Nayuki): bit 0 en (0,8), bit 14 en (8,0).
$version = 1;
$q = new QrMatrix($version);
qr_draw_function_patterns($q, $version);
qr_place_data($q, qr_build_codewords("HELLO WORLD", $version));
$mask = qr_apply_best_mask($q);
qr_draw_format_version($q, $version, $mask);
$fmt = qr_format_bits($mask);
check('formato bit0 en (0,8)', $q->m[0][8], $fmt & 1);
check('formato bit14 en (8,0)', $q->m[8][0], ($fmt >> 14) & 1);
done();
