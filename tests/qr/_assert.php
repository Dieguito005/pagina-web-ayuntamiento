<?php
$GLOBALS['__fails'] = 0;
function check(string $name, $got, $expected): void {
    if ($got === $expected) { echo "  ok  $name\n"; return; }
    $GLOBALS['__fails']++;
    echo "  FAIL $name\n";
    echo "       esperado: " . var_export($expected, true) . "\n";
    echo "       obtenido: " . var_export($got, true) . "\n";
}
function check_true(string $name, bool $cond): void { check($name, $cond, true); }
function done(): void {
    if ($GLOBALS['__fails'] > 0) { echo "FALLOS: {$GLOBALS['__fails']}\n"; exit(1); }
    echo "TODO OK\n"; exit(0);
}
