<?php
require __DIR__ . '/_assert.php';
require __DIR__ . '/../../includes/qrcode.php';

$totales = [1=>16,2=>28,3=>44,4=>64,5=>86,6=>108,7=>124,8=>154,9=>182,10=>216];
foreach ($totales as $v => $esperado) {
    [$ec, $groups] = qr_ec_table()[$v];
    $suma = 0; foreach ($groups as [$n, $cw]) $suma += $n * $cw;
    check("data codewords v$v", $suma, $esperado);
}

// Total de codewords (datos + EC) por versión — debe coincidir con ISO 18004.
$totalesCw = [1=>26,2=>44,3=>70,4=>100,5=>134,6=>172,7=>196,8=>242,9=>292,10=>346];
foreach ($totalesCw as $v => $esperado) {
    [$ec, $groups] = qr_ec_table()[$v];
    $total = 0; foreach ($groups as [$n, $cw]) $total += $n * ($cw + $ec);
    check("total codewords v$v", $total, $esperado);
}

check('capacidad v1', qr_byte_capacity(1), 14);
check('capacidad v7', qr_byte_capacity(7), 122);
check('capacidad v9', qr_byte_capacity(9), 180);
check('capacidad v10', qr_byte_capacity(10), 213);

check('elige v1 para 10 bytes', qr_choose_version(10), 1);
check('elige v9 para 170 bytes', qr_choose_version(170), 9);
check('elige v10 para 200 bytes', qr_choose_version(200), 10);

check('size v1', qr_matrix_size(1), 21);
check('size v10', qr_matrix_size(10), 57);

check('align v7', qr_align_centers(7), [6,22,38]);
done();
