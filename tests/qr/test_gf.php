<?php
require __DIR__ . '/_assert.php';
require __DIR__ . '/../../includes/qrcode.php';

[$exp, $log] = qr_gf_tables();
check('exp[0]', $exp[0], 1);
check('exp[1]', $exp[1], 2);
check('exp[8]', $exp[8], 0x1D);
check('log[1]', $log[1], 0);
check('log[2]', $log[2], 1);

check('mul 0*x', qr_gf_mul(0, 5), 0);
check('mul 1*x', qr_gf_mul(1, 7), 7);
check('mul 3*7', qr_gf_mul(3, 7), 9);
check('mul 16*32', qr_gf_mul(16, 32), 58);   // 2^4 * 2^5 = 2^9 = 58 en GF(256)/0x11D
done();
