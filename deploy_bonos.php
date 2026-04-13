<?php
// deploy_bonos.php — Sube SOLO los archivos del sistema de bonos v2.6.0
// Uso: php deploy_bonos.php

$ftp_server = "ftpupload.net";
$ftp_user_name = "if0_41609770";
$ftp_user_pass = "OllJ5CCGubeoe";
$remote_dir = "/htdocs";

$base_dir = realpath(__DIR__);

// Lista exacta de archivos a subir (nuevos + modificados)
$files = [
    // Nuevos
    'api/setup-bonos-db.php',
    'api/bonos.php',
    'api/bonos-checkout.php',
    'api/admin-bonos.php',
    'html/bonos.php',
    'html/bono-exitoso.php',
    'css/bonos.css',
    // Modificados
    'html/checkout.php',
    'js/checkout.js',
    'html/home.php',
    'html/perfil.php',
    'includes/header.php',
    'admin/admin-dashboard.php',
    'js/admin.js',
    'docs/CHANGELOG.md',
];

echo "=== DEPLOY BONOS v2.6.0 ===\n\n";
echo "Conectando al servidor FTP...\n";
$conn_id = ftp_connect($ftp_server) or die("No se pudo conectar a $ftp_server\n");
$login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
ftp_pasv($conn_id, true);

if (!$login_result) {
    die("Error logueando FTP.\n");
}
echo "Conexión OK.\n\n";

$ok = 0;
$fail = 0;

foreach ($files as $file) {
    $local_path = $base_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    $remote_path = $remote_dir . '/' . $file;

    // Verificar que existe localmente
    if (!file_exists($local_path)) {
        echo "  [SKIP] $file (no existe localmente)\n";
        $fail++;
        continue;
    }

    // Asegurar que el directorio remoto existe
    $remote_dir_path = dirname($remote_path);
    @ftp_mkdir($conn_id, $remote_dir_path);

    // Subir archivo
    if (@ftp_put($conn_id, $remote_path, $local_path, FTP_BINARY)) {
        echo "  [OK]   $file\n";
        $ok++;
    } else {
        echo "  [FAIL] $file\n";
        $fail++;
    }
}

ftp_close($conn_id);

echo "\n=== RESULTADO ===\n";
echo "Subidos: $ok | Fallidos: $fail\n\n";

if ($fail === 0) {
    echo "¡Todo subido correctamente!\n";
    echo "SIGUIENTE PASO: Ejecuta la migración abriendo en tu navegador:\n";
    echo "  https://tu-dominio.com/api/setup-bonos-db.php\n";
} else {
    echo "Algunos archivos fallaron. Revisa los errores arriba.\n";
}
