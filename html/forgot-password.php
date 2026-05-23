<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/SecurityHandler.php';
require_once __DIR__ . '/../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar contraseña | Alquileres Tarancón</title>
    <link rel="stylesheet" href="../css/home.css">
    <?= csrf_meta_tag() ?>
    <style>
        .auth-wrapper { min-height: 80vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg,#f4f6f8,#e8ecf1); padding: 2rem; }
        .auth-card { background: #fff; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,.1); width: 100%; max-width: 440px; padding: 2rem; }
        .auth-card h2 { color: #0b1c3c; margin-bottom: 0.3rem; text-align: center; }
        .auth-card p.subtitle { color: #666; margin-bottom: 1.5rem; text-align: center; font-size: 0.92rem; }
        .form-group { margin-bottom: 1.1rem; }
        .form-group label { display: block; margin-bottom: 0.4rem; color: #333; font-weight: 600; font-size: 0.9rem; }
        .form-group input { width: 100%; padding: 0.8rem 1rem; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; box-sizing: border-box; background: #fafafa; }
        .form-group input:focus { outline: none; border-color: #d15615; box-shadow: 0 0 0 3px rgba(209,86,21,.1); background: #fff; }
        .btn-primary { background: linear-gradient(135deg,#d15615,#a44310); color: white; border: none; padding: 0.9rem; width: 100%; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; }
        .alert-info { background: #e3f2fd; color: #1565c0; padding: 0.8rem; border-radius: 8px; margin-top: 1rem; font-size: 0.9rem; }
        .alert-error { background: #ffebee; color: #c62828; padding: 0.8rem; border-radius: 8px; margin-top: 1rem; font-size: 0.9rem; }
        .auth-links { text-align: center; margin-top: 1.5rem; font-size: 0.9rem; }
        .auth-links a { color: #d15615; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main id="contenido-principal" class="auth-wrapper">
        <div class="auth-card">
            <h2>Recuperar contraseña</h2>
            <p class="subtitle">Introduce el correo de tu cuenta y te enviaremos un enlace para restablecer la contraseña. El enlace caduca en 15 minutos.</p>

            <form id="forgot-form">
                <?= csrf_input() ?>
                <div class="form-group">
                    <label for="email">Correo electrónico</label>
                    <input type="email" id="email" name="email" required autofocus autocomplete="email" placeholder="tucorreo@ejemplo.com">
                </div>
                <button type="submit" class="btn-primary">Enviar enlace de recuperación</button>
                <div id="result" style="margin-top:1rem;"></div>
            </form>

            <div class="auth-links">
                <a href="user-login.php">← Volver al inicio de sesión</a>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>

    <script>
        const form = document.getElementById('forgot-form');
        const result = document.getElementById('result');
        const csrfToken = window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.content || '';

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = form.querySelector('button');
            btn.disabled = true;
            btn.textContent = 'Enviando…';
            try {
                const fd = new FormData(form);
                const resp = await fetch('../api/forgot-password.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrfToken },
                    credentials: 'same-origin',
                    body: fd
                });
                const data = await resp.json();
                result.className = 'alert-info';
                result.textContent = data.message || 'Si la cuenta existe, recibirás un correo.';
                form.querySelector('input[name="email"]').value = '';
            } catch (err) {
                result.className = 'alert-error';
                result.textContent = 'Error de conexión. Inténtalo de nuevo.';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Enviar enlace de recuperación';
            }
        });
    </script>
</body>
</html>
