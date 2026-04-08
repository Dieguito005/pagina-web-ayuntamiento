<?php
$ftp_server = "ftpupload.net";
$ftp_user_name = "if0_41609770";
$ftp_user_pass = "OllJ5CCGubeoe";
$conn_id = ftp_connect($ftp_server);
ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
ftp_pasv($conn_id, true);

echo "Subiendo correcciones de servidor (Patch 5)...\n";

ftp_put($conn_id, '/htdocs/api/admin-consultas.php', __DIR__ . '/api/admin-consultas.php', FTP_BINARY);
echo "Actualizado api/admin-consultas.php\n";

ftp_put($conn_id, '/htdocs/api/mailer.php', __DIR__ . '/api/mailer.php', FTP_BINARY);
echo "Actualizado api/mailer.php\n";

ftp_put($conn_id, '/htdocs/html/pago-exitoso.php', __DIR__ . '/html/pago-exitoso.php', FTP_BINARY);
echo "Actualizado html/pago-exitoso.php\n";

ftp_close($conn_id);
echo "✅ Despliegue de parches completado.\n";
