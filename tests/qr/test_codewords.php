<?php
require __DIR__ . '/_assert.php';
require __DIR__ . '/../../includes/qrcode.php';

$cw = qr_encode_data("HELLO WORLD", 1);
check('longitud = data codewords v1', count($cw), 16);
check('primer codeword (modo+len alto)', $cw[0], 0x40);
check('segundo codeword', $cw[1], 0xB4);

$inter = qr_build_codewords("HELLO WORLD", 1);
check('total codewords v1 (16 data + 10 ec)', count($inter), 26);
check('primeros 16 = data', array_slice($inter, 0, 16), $cw);

$txt = str_repeat("A", 120);
$inter8 = qr_build_codewords($txt, 8);
check('total codewords v8', count($inter8), 242);
done();
