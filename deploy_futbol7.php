<?php
// deploy_futbol7.php — Sube solo los archivos de la pista Fútbol 7 v2.7.0
$ftp_server   = "ftpupload.net";
$ftp_user_name = "if0_41609770";
$ftp_user_pass = "OllJ5CCGubeoe";
$remote_dir   = "/htdocs";
$base_dir     = realpath(__DIR__);

$files = [
    'html/futbol7.php',        // NUEVO
    'css/tenis.css',           // Modificado: .futbol7-hero
    'html/home.php',           // Modificado: tarjeta + hero
    'admin/admin-dashboard.php', // Modificado: 3 dropdowns
    'docs/CHANGELOG.md',       // Modificado: v2.7.0
];

echo "=== DEPLOY FÚTBOL 7 v2.7.0 ===\n\n";
echo "Conectando al servidor FTP...\n";
$conn_id = ftp_connect($ftp_server) or die("No se pudo conectar a $ftp_server\n");
$ok_login = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
ftp_pasv($conn_id, true);
if (!$ok_login) die("Error logueando FTP.\n");
echo "Conexión OK.\n\n";

$ok = 0; $fail = 0;
foreach ($files as $file) {
    $local = $base_dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    $remote = $remote_dir . '/' . $file;
    if (!file_exists($local)) { echo "  [SKIP] $file (no existe)\n"; $fail++; continue; }
    @ftp_mkdir($conn_id, dirname($remote));
    if (@ftp_put($conn_id, $remote, $local, FTP_BINARY)) { echo "  [OK]   $file\n"; $ok++; }
    else { echo "  [FAIL] $file\n"; $fail++; }
}
ftp_close($conn_id);
echo "\n=== RESULTADO ===\nSubidos: $ok | Fallidos: $fail\n";
if ($fail === 0) echo "\n¡Todo subido! La pista de Fútbol 7 ya está en producción.\n";
