<?php
// env.php - Variables de entorno
// Se usa define() porque putenv() suele estar deshabilitado en hostings gratuitos
define('ENV_APP_ENV', 'production');

define('ENV_DB_HOST', 'sql111.infinityfree.com');
define('ENV_DB_NAME', 'if0_41609770_web');
define('ENV_DB_USER', 'if0_41609770');
define('ENV_DB_PASS', 'OllJ5CCGubeoe');

define('ENV_BREVO_API_KEY', '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');
define('ENV_MAIL_FROM_EMAIL', 'reservas@tarancon.es');
define('ENV_MAIL_FROM_NAME', 'Ayuntamiento de Tarancón');

define('ENV_STRIPE_SECRET_KEY', 'sk_test_51TJtLo47CCQKfyfKtKj1UCGfiMHbWbEcT7UiU6hcYZIbwH0nWHq1q8imSdIQJ5aI25BONV0VFqIbWiOTuxQYDBbX00qBFGWh8n');
define('ENV_STRIPE_PUBLISHABLE_KEY', 'pk_test_51TJtLo47CCQKfyfKHQtpQ4Wh3ISlBJemBvgenOb3Q6pvB4gGaRw6kW9XyJYGzVCrr8CwNJZbFCqUXind16yqutYz00n3Os5dlF');
