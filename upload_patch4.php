<?php
$ftp_server = "ftpupload.net";
$ftp_user_name = "if0_41609770";
$ftp_user_pass = "OllJ5CCGubeoe";
$conn_id = ftp_connect($ftp_server);
ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
ftp_pasv($conn_id, true);
echo "Subiendo admin-dashboard.php con fixes responsive...\n";
ftp_put($conn_id, '/htdocs/admin/admin-dashboard.php', __DIR__ . '/admin/admin-dashboard.php', FTP_BINARY);
ftp_close($conn_id);
echo "Terminado.\n";
