<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/SecurityHandler.php';
require_once __DIR__ . '/../includes/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit;
}

$error_msg = "";
if (isset($_GET['error'])) {
    $errores = explode(',', $_GET['error']);
    $mensajes = [];
    foreach ($errores as $e) {
        switch ($e) {
            case 'campos': $mensajes[] = "Rellena todos los campos obligatorios."; break;
            case 'email': $mensajes[] = "El formato del correo electrónico no es válido."; break;
            case 'email_existe': $mensajes[] = "Ya existe una cuenta con ese correo electrónico."; break;
            case 'password_corta': $mensajes[] = "La contraseña debe tener al menos 8 caracteres."; break;
            case 'password_mismatch': $mensajes[] = "Las contraseñas no coinciden."; break;
            case 'dni': $mensajes[] = "El formato del DNI/NIE no es válido."; break;
            case 'telefono': $mensajes[] = "El teléfono debe tener 9 dígitos."; break;
            case 'policy': $mensajes[] = "Debes aceptar la política de privacidad para continuar."; break;
            case 'csrf': $mensajes[] = "Sesión caducada. Recarga la página e inténtalo de nuevo."; break;
            case 'ratelimit': $mensajes[] = "Demasiados registros desde tu IP. Espera 15 minutos."; break;
            case 'db': $mensajes[] = "Error de conexión. Inténtalo más tarde."; break;
        }
    }
    $error_msg = implode('<br>', $mensajes);
}

$redirect = $_GET['redirect'] ?? '';
$policyVersion = defined('POLICY_VERSION') ? POLICY_VERSION : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cuenta - Alquileres Tarancón</title>
    <link rel="preload" href="../assets/imagenes/LOGO-AYUNTAMIENTO-BLANCO.png" as="image">
    <link rel="stylesheet" href="../css/home.css">
    <style>
        .register-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 85vh;
            background: linear-gradient(135deg, #f4f6f8 0%, #e8ecf1 100%);
            padding: 2rem;
        }
        .register-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 520px;
            padding: 2.5rem;
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .register-card h2 {
            color: #0b1c3c;
            margin-bottom: 0.3rem;
            font-size: 1.75rem;
            text-align: center;
        }
        .register-card .subtitle {
            color: #666;
            margin-bottom: 2rem;
            font-size: 0.95rem;
            text-align: center;
        }
        .form-row {
            display: flex;
            gap: 1rem;
        }
        .form-row .form-group {
            flex: 1;
        }
        .form-group {
            margin-bottom: 1.1rem;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.4rem;
            color: #333;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s;
            box-sizing: border-box;
            background: #fafafa;
        }
        .form-group input:focus {
            outline: none;
            border-color: #d15615;
            box-shadow: 0 0 0 3px rgba(209, 86, 21, 0.1);
            background: #fff;
        }
        .form-group input.invalid {
            border-color: #d32f2f;
            box-shadow: 0 0 0 3px rgba(211, 47, 47, 0.1);
        }
        .form-group input.valid {
            border-color: #2e7d32;
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
        }
        .form-group small {
            display: block;
            margin-top: 0.3rem;
            font-size: 0.8rem;
            color: #999;
        }
        .form-group small.error-hint {
            color: #d32f2f;
        }
        .section-label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #d15615;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 0.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f0f0f0;
        }
        .btn-register {
            background: linear-gradient(135deg, #0b1c3c 0%, #1a3673 100%);
            color: white;
            border: none;
            padding: 0.9rem;
            width: 100%;
            border-radius: 8px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 0.5rem;
        }
        .btn-register:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(11, 28, 60, 0.3);
        }
        .btn-register:active { transform: translateY(1px); }
        .alert-error {
            background-color: #ffebee;
            color: #c62828;
            padding: 0.8rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            border: 1px solid #ffcdd2;
        }
        .register-links {
            margin-top: 1.5rem;
            font-size: 0.9rem;
            text-align: center;
        }
        .register-links a {
            color: #d15615;
            text-decoration: none;
            font-weight: 600;
        }
        .register-links a:hover { color: #a44310; }
        .password-strength {
            height: 4px;
            background: #eee;
            border-radius: 4px;
            margin-top: 0.4rem;
            overflow: hidden;
        }
        .password-strength .bar {
            height: 100%;
            width: 0;
            border-radius: 4px;
            transition: width 0.3s, background 0.3s;
        }

        /* Responsive mejoras */
        @media (max-width: 768px) {
            .register-wrapper {
                padding: 1.5rem 1rem;
            }

            .register-card {
                padding: 2rem 1.5rem;
            }

            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .form-group {
                margin-bottom: 1rem;
            }
        }

        @media (max-width: 480px) {
            .register-wrapper {
                padding: 1rem 0.75rem;
                min-height: auto;
            }

            .register-card {
                padding: 1.5rem 1rem;
                border-radius: 12px;
            }

            .register-card h2 {
                font-size: 1.4rem;
            }

            .register-card .subtitle {
                font-size: 0.9rem;
                margin-bottom: 1.5rem;
            }

            .section-label {
                font-size: 0.75rem;
                margin-top: 0.25rem;
                margin-bottom: 0.75rem;
            }

            .form-group input {
                padding: 0.7rem 0.9rem;
                font-size: 16px; /* Previene zoom en iOS */
            }

            .form-group label {
                font-size: 0.85rem;
            }

            .form-group small {
                font-size: 0.75rem;
            }

            .btn-register {
                padding: 0.85rem;
                font-size: 1rem;
            }

            .register-links {
                font-size: 0.85rem;
            }

            .alert-error {
                font-size: 0.85rem;
                padding: 0.7rem;
            }
        }
    </style>
</head>
<body>

    <header class="main-header" style="position: sticky;">
        <div class="header-container">
            <div class="header-logo">
                <a href="home.php" class="logo-home">
                    <div class="logo-image-box">
                        <img class="logo-ayuntamiento" src="../assets/imagenes/LOGO-AYUNTAMIENTO-BLANCO.png" alt="Logo">
                    </div>
                </a>
            </div>
            <div class="header-title">
                <h1>Alquileres Tarancón</h1>
            </div>
            <div style="width: 120px;"></div>
        </div>
    </header>

    <main class="register-wrapper">
        <div class="register-card">
            <h2>Crear Cuenta</h2>
            <p class="subtitle">Regístrate para reservar pistas deportivas en Tarancón.</p>

            <?php if ($error_msg): ?>
                <div class="alert-error"><?= $error_msg ?></div>
            <?php endif; ?>

            <form action="../api/user-register.php" method="POST" id="register-form">
                <?= csrf_input() ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
                <input type="hidden" name="policy_version" value="<?= htmlspecialchars($policyVersion) ?>">

                <p class="section-label">Datos Personales</p>

                <div class="form-row">
                    <div class="form-group">
                        <label for="nombre">Nombre *</label>
                        <input type="text" id="nombre" name="nombre" required placeholder="Ej: Juan">
                    </div>
                    <div class="form-group">
                        <label for="apellidos">Apellidos *</label>
                        <input type="text" id="apellidos" name="apellidos" required placeholder="Ej: Pérez García">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Correo Electrónico *</label>
                    <input type="email" id="email" name="email" required placeholder="tucorreo@email.com">
                    <small>Será tu usuario para iniciar sesión.</small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="dni">DNI / NIE</label>
                        <input type="text" id="dni" name="dni" placeholder="12345678A" maxlength="9">
                        <small>Opcional. Se usará para auto-rellenar reservas.</small>
                    </div>
                    <div class="form-group">
                        <label for="telefono">Teléfono</label>
                        <input type="tel" id="telefono" name="telefono" placeholder="600123456" maxlength="9">
                    </div>
                </div>

                <p class="section-label" style="margin-top: 1.5rem;">Seguridad</p>

                <div class="form-group">
                    <label for="password">Contraseña *</label>
                    <input type="password" id="password" name="password" required placeholder="Mínimo 8 caracteres" minlength="8">
                    <div class="password-strength"><div class="bar" id="pw-bar"></div></div>
                </div>

                <div class="form-group">
                    <label for="password2">Repetir Contraseña *</label>
                    <input type="password" id="password2" name="password2" required placeholder="Repite la contraseña" minlength="8">
                </div>

                <div class="form-group" style="margin-top:1.2rem; display:flex; gap:0.6rem; align-items:flex-start;">
                    <input type="checkbox" id="accept_policy" name="accept_policy" value="1" required style="margin-top:0.25rem; width:auto;">
                    <label for="accept_policy" style="font-weight:400; font-size:0.85rem; line-height:1.4;">
                        He leído y acepto la
                        <a href="politica-privacidad.php" target="_blank" rel="noopener" style="color:#d15615; font-weight:600;">Política de Privacidad</a>
                        y el
                        <a href="aviso-legal.php" target="_blank" rel="noopener" style="color:#d15615; font-weight:600;">Aviso Legal</a>
                        del Ayuntamiento de Tarancón. *
                    </label>
                </div>

                <button type="submit" class="btn-register">Crear Mi Cuenta</button>
            </form>

            <div class="register-links">
                <p>¿Ya tienes cuenta? <a href="user-login.php<?= $redirect ? '?redirect=' . urlencode($redirect) : '' ?>">Inicia sesión</a></p>
            </div>
        </div>
    </main>

    <script>
        // Validación en tiempo real
        const dniInput = document.getElementById('dni');
        const telInput = document.getElementById('telefono');
        const pwInput = document.getElementById('password');
        const pw2Input = document.getElementById('password2');
        const pwBar = document.getElementById('pw-bar');

        if (dniInput) {
            dniInput.addEventListener('input', (e) => {
                e.target.value = e.target.value.toUpperCase().replace(/[^0-9A-Z]/g, '');
            });
        }

        if (telInput) {
            telInput.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/\D/g, '').substring(0, 9);
            });
        }

        if (pwInput && pwBar) {
            pwInput.addEventListener('input', () => {
                const len = pwInput.value.length;
                let width = 0, color = '#d32f2f';
                if (len >= 2) { width = 25; color = '#d32f2f'; }
                if (len >= 5) { width = 50; color = '#f57c00'; }
                if (len >= 8) { width = 75; color = '#fbc02d'; }
                if (len >= 12) { width = 100; color = '#2e7d32'; }
                pwBar.style.width = width + '%';
                pwBar.style.background = color;
            });
        }

        if (pw2Input && pwInput) {
            pw2Input.addEventListener('input', () => {
                if (pw2Input.value && pw2Input.value !== pwInput.value) {
                    pw2Input.classList.add('invalid');
                    pw2Input.classList.remove('valid');
                } else if (pw2Input.value && pw2Input.value === pwInput.value) {
                    pw2Input.classList.remove('invalid');
                    pw2Input.classList.add('valid');
                } else {
                    pw2Input.classList.remove('invalid', 'valid');
                }
            });
        }

        // Validación antes de enviar
        document.getElementById('register-form').addEventListener('submit', (e) => {
            if (pwInput.value !== pw2Input.value) {
                e.preventDefault();
                pw2Input.classList.add('invalid');
                alert('Las contraseñas no coinciden.');
            }
        });
    </script>

</body>
</html>
