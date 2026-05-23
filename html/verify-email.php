<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/SecurityHandler.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/audit.php';
require_once __DIR__ . '/../includes/tokens.php';
require_once __DIR__ . '/../api/conexion.php';

$token = (string) ($_GET['token'] ?? '');
$result = null;

if ($token === '') {
    $result = ['ok' => false, 'msg' => 'Falta el token de verificación.'];
} else {
    $userId = consume_email_verification_token($pdo, $token);
    if ($userId === null) {
        $result = ['ok' => false, 'msg' => 'Este enlace ha caducado o ya ha sido usado. Solicita uno nuevo iniciando sesión.'];
    } else {
        audit_log($pdo, 'email_verified', 'usuario', $userId);
        $result = ['ok' => true, 'msg' => 'Email verificado correctamente. Ya puedes iniciar sesión con normalidad.'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de email | Alquileres Tarancón</title>
    <link rel="stylesheet" href="../css/home.css">
    <style>
        .verify-wrapper { min-height: 70vh; display: flex; align-items: center; justify-content: center; padding: 2rem; }
        .verify-card { background: #fff; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,.1); width: 100%; max-width: 460px; padding: 2.5rem; text-align: center; }
        .verify-icon { font-size: 4rem; margin-bottom: 0.8rem; }
        .verify-card.ok { border-top: 4px solid #2e7d32; }
        .verify-card.err { border-top: 4px solid #c62828; }
        .verify-card.ok h2 { color: #2e7d32; }
        .verify-card.err h2 { color: #c62828; }
        .verify-card p { color: #555; margin: 1rem 0 1.5rem; }
        .btn-primary { display: inline-block; background: linear-gradient(135deg,#d15615,#a44310); color: white; padding: 0.8rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main id="contenido-principal" class="verify-wrapper">
        <div class="verify-card <?= $result['ok'] ? 'ok' : 'err' ?>">
            <div class="verify-icon" aria-hidden="true"><?= $result['ok'] ? '&check;' : '!' ?></div>
            <h2><?= $result['ok'] ? 'Email verificado' : 'No se ha podido verificar' ?></h2>
            <p><?= esc_html($result['msg']) ?></p>
            <a href="<?= $result['ok'] ? 'user-login.php' : 'home.php' ?>" class="btn-primary">
                <?= $result['ok'] ? 'Ir a iniciar sesión' : 'Volver al inicio' ?>
            </a>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html>
