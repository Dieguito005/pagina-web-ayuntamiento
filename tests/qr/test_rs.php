<?php
require __DIR__ . '/_assert.php';
require __DIR__ . '/../../includes/qrcode.php';

$gen = qr_rs_generator(10);
check('gen grado 10 longitud', count($gen), 11);
check('gen[0]', $gen[0], 1);

$ec0 = qr_rs_ec(array_fill(0, 16, 0), 10);
check('EC de datos nulos es nulo', $ec0, array_fill(0, 10, 0));

// Vector canónico 'HELLO WORLD' v1-M (Thonky/ISO 18004).
$data = [32,91,11,120,209,114,220,77,67,64,236,17,236,17,236,17];
$ec = qr_rs_ec($data, 10);
check('EC HELLO WORLD v1-M', $ec, [196,35,39,119,235,215,231,226,93,23]);
done();
