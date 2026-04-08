<?php
// deploy_ftp.php
$ftp_server = "ftpupload.net";
$ftp_user_name = "if0_41609770";
$ftp_user_pass = "OllJ5CCGubeoe";
$remote_dir = "/htdocs"; // Generalmente InfinityFree pide subir en htdocs

echo "Conectando al servidor FTP...\n";
$conn_id = ftp_connect($ftp_server) or die("No se pudo conectar a $ftp_server");
$login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
ftp_pasv($conn_id, true); // Passive mode es necesario

if (!$login_result) {
    die("Error logueando FTP.");
}
echo "Conexión y login OK.\n";

$base_dir = realpath(__DIR__);
$exclude_dirs = ['.git', '.vscode', '.idea', 'node_modules', 'vendor'];
$exclude_files = ['deploy_ftp.php', '.gitignore'];

function ftp_upload_dir($conn_id, $local_dir, $remote_dir, $base_dir) {
    global $exclude_dirs, $exclude_files;
    
    // Check if remote dir exists, if not, create it
    if (!@ftp_chdir($conn_id, $remote_dir)) {
        if (@ftp_mkdir($conn_id, $remote_dir)) {
            // echo "Creado directorio remoto: $remote_dir\n";
        } else {
            echo "Aviso: No se pudo crear o acceder al directorio remoto: $remote_dir\n";
        }
        ftp_chdir($conn_id, '/');
    } else {
        ftp_chdir($conn_id, '/');
    }

    $d = dir($local_dir);
    while (false !== ($entry = $d->read())) {
        if ($entry == "." || $entry == "..") continue;
        
        $local_path = $local_dir . DIRECTORY_SEPARATOR . $entry;
        $relative_path = str_replace($base_dir . DIRECTORY_SEPARATOR, '', $local_path);
        $relative_path = str_replace('\\', '/', $relative_path); // Para Windows
        
        $remote_path = $remote_dir . '/' . $entry;
        
        if (is_dir($local_path)) {
            if (in_array($entry, $exclude_dirs)) continue;
            ftp_upload_dir($conn_id, $local_path, $remote_path, $base_dir);
        } else {
            if (in_array($entry, $exclude_files)) continue;
            if (substr($entry, 0, 6) === 'debug_') continue;
            
            // Upload file
            // echo "Subiendo: $relative_path -> $remote_path\n";
            if (!@ftp_put($conn_id, $remote_path, $local_path, FTP_BINARY)) {
                echo "Error subiendo $local_path a $remote_path\n";
            }
        }
    }
    $d->close();
}

echo "Empezando subida de archivos (esto puede tardar unos minutos)...\n";
ftp_upload_dir($conn_id, $base_dir, $remote_dir, $base_dir);
ftp_close($conn_id);
echo "Subida completada.\n";
