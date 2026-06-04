<?php
require __DIR__ . '/_assert.php';
require __DIR__ . '/../../includes/qrcode.php';

check('mask0 (0,0)', qr_mask_condition(0, 0, 0), true);
check('mask0 (0,1)', qr_mask_condition(0, 0, 1), false);
check('mask1 (0,5)', qr_mask_condition(1, 0, 5), true);
check('mask1 (1,5)', qr_mask_condition(1, 1, 5), false);
check('mask2 (4,3)', qr_mask_condition(2, 4, 3), true);

$version = 1;
$q = new QrMatrix($version);
qr_draw_function_patterns($q, $version);
qr_place_data($q, qr_build_codewords("HELLO WORLD", $version));
$mask = qr_apply_best_mask($q);
check_true('mascara en rango', $mask >= 0 && $mask <= 7);
done();
