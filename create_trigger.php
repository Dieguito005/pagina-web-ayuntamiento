<?php
// trigger_setup.php
$ftp_server = "ftpupload.net";
$ftp_user_name = "if0_41609770";
$ftp_user_pass = "OllJ5CCGubeoe";
$conn_id = ftp_connect($ftp_server);
ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
ftp_pasv($conn_id, true);

$trigger_content = <<<PHP
<?php
require_once 'env.php';
require_once 'setup_db.php';
echo "<h1>Base de datos inicializada correctamente ✅</h1>";
// Se auto-borra
@unlink(__FILE__);
PHP;

$tmp_file = tempnam(sys_get_temp_dir(), 'ftptrigger');
file_put_contents($tmp_file, $trigger_content);
ftp_put($conn_id, '/htdocs/trigger.php', $tmp_file, FTP_BINARY);
ftp_close($conn_id);
unlink($tmp_file);

echo "Trigger file created successfully on remote server.\n";
