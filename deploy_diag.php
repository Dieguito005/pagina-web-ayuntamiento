<?php
$ftp_server = "ftpupload.net";
$ftp_user_name = "if0_41609770";
$ftp_user_pass = "OllJ5CCGubeoe";
$conn_id = ftp_connect($ftp_server);
ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
ftp_pasv($conn_id, true);

echo "Subiendo Home v3.0 con diagnóstico...\n";

ftp_put($conn_id, '/htdocs/html/home_v3.php', __DIR__ . '/html/home_v3.php', FTP_BINARY);

ftp_close($conn_id);
echo "Subido. Comprueba tu-dominio.com/html/home_v3.php\n";
