<?php
// patch_setups.php
$files = [
    'setup_db.php',
    'setup_db_consultas.php',
    'setup_db_users.php',
    'setup_db_anuncios.php',
    'setup_db_update.php',
    'update_reservas_table.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // Añadimos require de config.php al principio
    if (strpos($content, 'config.php') === false) {
        $content = preg_replace('/(<\?php\s*)/', "$1require_once __DIR__ . '/config.php';\n", $content);
    }
    
    $content = preg_replace('/\$host\s*=\s*(["\'])127\.0\.0\.1\1;/', '$host = DB_HOST;', $content);
    $content = preg_replace('/\$user\s*=\s*(["\'])root\1;/', '$user = DB_USER;', $content);
    $content = preg_replace('/\$pass\s*=\s*(["\']).*?\1;/', '$pass = DB_PASS;', $content);
    $content = preg_replace('/\$dbname\s*=\s*(["\'])ayuntamiento_reservas\1;/', '$dbname = DB_NAME;', $content);
    
    // En setup_db.php:
    $content = str_replace('ayuntamiento_reservas', "' . DB_NAME . '", $content);
    // Reparar posibles inyecciones de string roto
    $content = str_replace("' . DB_NAME . '' . DB_NAME . '", "' . DB_NAME . '", $content);
    // Para USE database:
    $content = str_replace('USE \' . DB_NAME . \'', 'USE `" . DB_NAME . "`', $content);
    
    file_put_contents($file, $content);
    echo "Patched $file\n";
}
