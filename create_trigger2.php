<?php
// create_trigger2.php
$ftp_server = "ftpupload.net";
$ftp_user_name = "if0_41609770";
$ftp_user_pass = "OllJ5CCGubeoe";
$conn_id = ftp_connect($ftp_server);
ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
ftp_pasv($conn_id, true);

$trigger_content = <<<PHP
<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once 'env.php';
echo "Ejecutando setup_db.php...<br>";
require_once 'setup_db.php';
echo "Ejecutando setup_db_users.php...<br>";
require_once 'setup_db_users.php';
echo "Ejecutando setup_db_consultas.php...<br>";
require_once 'setup_db_consultas.php';
echo "Ejecutando setup_db_anuncios.php...<br>";
require_once 'setup_db_anuncios.php';
echo "Ejecutando setup_db_update.php...<br>";
require_once 'setup_db_update.php';
echo "Ejecutando update_reservas_table.php...<br>";
require_once 'update_reservas_table.php';

echo "<h1>Base de datos inicializada completamente ✅</h1>";
// Se auto-borra
@unlink(__FILE__);
PHP;

$tmp_file = tempnam(sys_get_temp_dir(), 'ftptrigger2');
file_put_contents($tmp_file, $trigger_content);
ftp_put($conn_id, '/htdocs/trigger.php', $tmp_file, FTP_BINARY);
ftp_close($conn_id);
unlink($tmp_file);
echo "New trigger updated on server.\n";
