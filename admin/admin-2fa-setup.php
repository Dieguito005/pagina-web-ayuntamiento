<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/SecurityHandler.php';
require_once __DIR__ . '/../includes/helpers.php';
session_start();
send_security_headers();
SecurityHandler::requireAdmin();
require_once __DIR__ . '/../api/conexion.php';

$stmt = $pdo->prepare("SELECT totp_enabled FROM usuarios_admin WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$totpEnabled = (int) ($stmt->fetch()['totp_enabled'] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación en dos pasos | Panel Admin</title>
    <link rel="stylesheet" href="../css/home.css">
    <?= csrf_meta_tag() ?>
    <style>
        body { background: #f4f6f8; }
        .container { max-width: 640px; margin: 2rem auto; padding: 2rem; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,.06); }
        h2 { color: #0b1c3c; margin-top: 0; }
        .status-box { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 600; }
        .status-on { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .status-off { background: #fff4e5; color: #c36a33; border: 1px solid #ffcc80; }
        .btn { padding: 0.7rem 1.2rem; border-radius: 6px; border: none; cursor: pointer; font-weight: 600; }
        .btn-primary { background: #d15615; color: white; }
        .btn-danger { background: #c62828; color: white; }
        .qr-box { text-align: center; padding: 1.5rem; background: #fafafa; border-radius: 8px; margin: 1.5rem 0; }
        .qr-box img { max-width: 240px; }
        .secret-box { background: #f0f4f8; padding: 1rem; border-radius: 8px; font-family: monospace; font-size: 1.1rem; letter-spacing: 0.1rem; word-break: break-all; text-align: center; margin: 1rem 0; }
        .recovery-codes { background: #fff8e1; border: 1px dashed #ff9800; padding: 1rem; border-radius: 8px; margin: 1rem 0; }
        .recovery-codes code { display: inline-block; margin: 0.3rem; padding: 0.3rem 0.6rem; background: white; border-radius: 4px; font-size: 1rem; }
        .input-code { font-size: 1.4rem; letter-spacing: 0.3rem; text-align: center; padding: 0.8rem; width: 100%; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
        .form-row { margin: 1rem 0; }
        .alert { padding: 0.8rem; border-radius: 6px; margin: 1rem 0; }
        .alert-error { background: #ffebee; color: #c62828; }
        .alert-success { background: #e8f5e9; color: #2e7d32; }
    </style>
</head>
<body>
    <div class="container">
        <p><a href="admin-dashboard.php" style="color:#666;text-decoration:none;">← Volver al panel</a></p>
        <h2>Verificación en dos pasos (2FA)</h2>

        <div class="status-box <?= $totpEnabled ? 'status-on' : 'status-off' ?>">
            Estado: <?= $totpEnabled ? '✓ 2FA ACTIVADO' : '✗ 2FA NO ACTIVADO' ?>
        </div>

        <?php if (!$totpEnabled): ?>
            <p>Activar la verificación en dos pasos añade una segunda capa de seguridad. Necesitarás una aplicación como <strong>Google Authenticator</strong>, <strong>Authy</strong>, <strong>1Password</strong> o <strong>Microsoft Authenticator</strong> instalada en tu teléfono.</p>

            <div id="step-1">
                <button id="btn-init" class="btn btn-primary">Iniciar configuración</button>
            </div>

            <div id="step-2" style="display:none;">
                <p>1. Escanea este código QR con tu aplicación de autenticación:</p>
                <div class="qr-box">
                    <img id="qr-img" alt="Código QR de configuración 2FA">
                </div>
                <p>O introduce este secreto manualmente:</p>
                <div class="secret-box" id="secret-text"></div>

                <p>2. Introduce el código de 6 dígitos que muestra la aplicación:</p>
                <div class="form-row">
                    <input type="text" id="verify-code" class="input-code" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" placeholder="000000" autocomplete="off">
                </div>
                <button id="btn-enable" class="btn btn-primary">Verificar y activar</button>
                <div id="enable-msg"></div>
            </div>

            <div id="step-3" style="display:none;">
                <div class="alert alert-success">2FA activado correctamente.</div>
                <p><strong>Guarda estos códigos de respaldo</strong> en un lugar seguro (gestor de contraseñas, papel impreso). Cada uno se puede usar UNA VEZ si pierdes el acceso a tu aplicación:</p>
                <div class="recovery-codes" id="recovery-list"></div>
                <p><a href="admin-dashboard.php" class="btn btn-primary" style="text-decoration:none;">Volver al panel</a></p>
            </div>
        <?php else: ?>
            <p>La verificación en dos pasos está activada. Para iniciar sesión necesitas tu contraseña y un código TOTP.</p>
            <p>Si quieres <strong>desactivarla</strong>, introduce un código TOTP actual:</p>
            <div class="form-row">
                <input type="text" id="disable-code" class="input-code" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" placeholder="000000" autocomplete="off">
            </div>
            <button id="btn-disable" class="btn btn-danger">Desactivar 2FA</button>
            <div id="disable-msg"></div>

            <hr style="margin: 2rem 0;">

            <p>Si has perdido tus códigos de respaldo, puedes regenerarlos (los anteriores quedarán invalidados):</p>
            <div class="form-row">
                <input type="text" id="regen-code" class="input-code" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" placeholder="000000" autocomplete="off">
            </div>
            <button id="btn-regen" class="btn btn-primary">Regenerar códigos de respaldo</button>
            <div id="regen-msg"></div>
            <div class="recovery-codes" id="regen-list" style="display:none;"></div>
        <?php endif; ?>
    </div>

    <script>
        const csrfToken = window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.content || '';

        async function post(payload) {
            payload.csrf_token = csrfToken;
            const resp = await fetch('../api/admin-2fa.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            });
            return resp.json();
        }

        function renderCodes(container, codes) {
            while (container.firstChild) container.removeChild(container.firstChild);
            for (const c of codes) {
                const el = document.createElement('code');
                el.textContent = c;
                container.appendChild(el);
                container.appendChild(document.createTextNode(' '));
            }
        }

        document.getElementById('btn-init')?.addEventListener('click', async () => {
            const data = await post({ action: 'init' });
            if (data.status !== 'success') return alert(data.message || 'Error');
            document.getElementById('step-1').style.display = 'none';
            document.getElementById('step-2').style.display = 'block';
            document.getElementById('qr-img').src = data.qr_url;
            document.getElementById('secret-text').textContent = data.secret;
        });

        document.getElementById('btn-enable')?.addEventListener('click', async () => {
            const code = document.getElementById('verify-code').value;
            const msg = document.getElementById('enable-msg');
            const data = await post({ action: 'enable', code });
            if (data.status !== 'success') {
                msg.className = 'alert alert-error';
                msg.textContent = data.message || 'Error';
                return;
            }
            document.getElementById('step-2').style.display = 'none';
            document.getElementById('step-3').style.display = 'block';
            renderCodes(document.getElementById('recovery-list'), data.recovery_codes);
        });

        document.getElementById('btn-disable')?.addEventListener('click', async () => {
            const code = document.getElementById('disable-code').value;
            const msg = document.getElementById('disable-msg');
            const data = await post({ action: 'disable', code });
            if (data.status === 'success') {
                msg.className = 'alert alert-success';
                msg.textContent = data.message;
                setTimeout(() => location.reload(), 1500);
            } else {
                msg.className = 'alert alert-error';
                msg.textContent = data.message;
            }
        });

        document.getElementById('btn-regen')?.addEventListener('click', async () => {
            const code = document.getElementById('regen-code').value;
            const msg = document.getElementById('regen-msg');
            const list = document.getElementById('regen-list');
            const data = await post({ action: 'regen_recovery', code });
            if (data.status === 'success') {
                msg.className = 'alert alert-success';
                msg.textContent = data.message;
                list.style.display = 'block';
                renderCodes(list, data.recovery_codes);
            } else {
                msg.className = 'alert alert-error';
                msg.textContent = data.message;
            }
        });
    </script>
</body>
</html>
