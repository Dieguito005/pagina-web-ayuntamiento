<?php
require __DIR__ . '/_assert.php';
require __DIR__ . '/../../includes/qrcode.php';

$q = new QrMatrix(1);
qr_draw_function_patterns($q, 1);
$n = $q->n;

check('finder TL borde', $q->m[0][0], 1);
check('finder TL anillo', $q->m[1][1], 0);
check('finder TL nucleo', $q->m[3][3], 1);

check('separador (7,0)', $q->m[7][0], 0);
check_true('separador es función', $q->f[7][0]);

check('timing (6,8)', $q->m[6][8], 1);
check('timing (6,9)', $q->m[6][9], 0);

check('modulo oscuro', $q->m[13][8], 1);

check_true('formato reservado (8,0)', $q->f[8][0]);

check_true('matriz v1 tamaño', $n === 21);
done();
