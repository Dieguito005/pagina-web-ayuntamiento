<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/SecurityHandler.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/tokens.php';
require_once __DIR__ . '/../api/conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$token = (string) ($_GET['token'] ?? '');
$tokenValid = $token !== '' && lookup_password_reset_token($pdo, $token) !== null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer contraseña | Alquileres Tarancón</title>
    <link rel="stylesheet" href="../css/home.css">
    <?= csrf_meta_tag() ?>
    <style>
        .auth-wrapper { min-height: 80vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg,#f4f6f8,#e8ecf1); padding: 2rem; }
        .auth-card { background: #fff; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,.1); width: 100%; max-width: 460px; padding: 2rem; }
        .auth-card h2 { color: #0b1c3c; margin-bottom: 0.3rem; text-align: center; }
        .auth-card p.subtitle { color: #666; margin-bottom: 1.5rem; text-align: center; font-size: 0.92rem; }
        .form-group { margin-bottom: 1.1rem; }
        .form-group label { display: block; margin-bottom: 0.4rem; color: #333; font-weight: 600; font-size: 0.9rem; }
        .form-group input { width: 100%; padding: 0.8rem 1rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; box-sizing: border-box; background: #fafafa; }
        .form-group small { display:block; margin-top:0.3rem; color:#888; font-size:0.8rem; }
        .btn-primary { background: linear-gradient(135deg,#d15615,#a44310); color: white; border: none; padding: 0.9rem; width: 100%; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; }
        .alert { padding: 0.8rem; border-radius: 8px; margin-top: 1rem; font-size: 0.9rem; }
        .alert-info { background: #e3f2fd; color: #1565c0; }
        .alert-error { background: #ffebee; color: #c62828; }
        .alert-success { background: #e8f5e9; color: #2e7d32; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main id="contenido-principal" class="auth-wrapper">
        <div class="auth-card">
            <h2>Restablecer contraseña</h2>

            <?php if (!$tokenValid): ?>
                <div class="alert alert-error">
                    Este enlace ha caducado o ya ha sido utilizado. <a href="forgot-password.php" style="color:#c62828;text-decoration:underline;">Solicita uno nuevo</a>.
                </div>
            <?php else: ?>
                <p class="subtitle">Introduce tu nueva contraseña. Debe tener al menos 12 caracteres, con mayúsculas, minúsculas y números.</p>
                <form id="reset-form">
                    <?= csrf_input() ?>
                    <input type="hidden" name="token" value="<?= esc_html($token) ?>">
                    <div class="form-group">
                        <label for="password">Nueva contraseña</label>
                        <input type="password" id="password" name="password" required minlength="12" maxlength="256" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label for="password2">Repetir contraseña</label>
                        <input type="password" id="password2" name="password2" required minlength="12" maxlength="256" autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn-primary">Cambiar contraseña</button>
                    <div id="result"></div>
                </form>
            <?php endif; ?>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>

    <?php if ($tokenValid): ?>
    <script>
        const form = document.getElementById('reset-form');
        const result = document.getElementById('result');
        const csrfToken = window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.content || '';

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const p1 = form.querySelector('[name=password]').value;
            const p2 = form.querySelector('[name=password2]').value;
            if (p1 !== p2) {
                result.className = 'alert alert-error';
                result.textContent = 'Las contraseñas no coinciden.';
                return;
            }
            const btn = form.querySelector('button');
            btn.disabled = true;
            btn.textContent = 'Cambiando…';
            try {
                const fd = new FormData(form);
                const resp = await fetch('../api/reset-password.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrfToken },
                    credentials: 'same-origin',
                    body: fd
                });
                const data = await resp.json();
                if (data.status === 'success') {
                    result.className = 'alert alert-success';
                    result.textContent = data.message;
                    setTimeout(() => { window.location.href = data.redirect || 'user-login.php'; }, 1800);
                } else {
                    result.className = 'alert alert-error';
                    result.textContent = data.message || 'Error al cambiar la contraseña.';
                    btn.disabled = false;
                    btn.textContent = 'Cambiar contraseña';
                }
            } catch (err) {
                result.className = 'alert alert-error';
                result.textContent = 'Error de conexión. Inténtalo de nuevo.';
                btn.disabled = false;
                btn.textContent = 'Cambiar contraseña';
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
