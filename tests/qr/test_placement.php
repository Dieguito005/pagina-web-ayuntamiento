<?php
require __DIR__ . '/_assert.php';
require __DIR__ . '/../../includes/qrcode.php';

$version = 1;
$q = new QrMatrix($version);
qr_draw_function_patterns($q, $version);
$cw = qr_build_codewords("HELLO WORLD", $version);
qr_place_data($q, $cw);

$vacios = 0;
for ($r = 0; $r < $q->n; $r++)
    for ($c = 0; $c < $q->n; $c++)
        if (!$q->f[$r][$c] && $q->m[$r][$c] === -1) $vacios++;
check('sin modulos de datos vacios', $vacios, 0);

check('primer modulo datos (n-1,n-1)', $q->m[$q->n - 1][$q->n - 1], 0);
done();
