<?php
$ftp_server = "ftpupload.net";
$ftp_user_name = "if0_41609770";
$ftp_user_pass = "OllJ5CCGubeoe";
$conn_id = ftp_connect($ftp_server);
ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
ftp_pasv($conn_id, true);

echo "Subiendo archivos corregidos...\n";

// HTML checkout.php
ftp_put($conn_id, '/htdocs/html/checkout.php', __DIR__ . '/html/checkout.php', FTP_BINARY);
echo "Subido html/checkout.php\n";

// Admin dashboard.php
ftp_put($conn_id, '/htdocs/admin/admin-dashboard.php', __DIR__ . '/admin/admin-dashboard.php', FTP_BINARY);
echo "Subido admin/admin-dashboard.php\n";

// DB Fixer script
ftp_put($conn_id, '/htdocs/fix_db.php', __DIR__ . '/fix_db.php', FTP_BINARY);
echo "Subido fix_db.php\n";

ftp_close($conn_id);
echo "Todo subido correctamente.\n";
