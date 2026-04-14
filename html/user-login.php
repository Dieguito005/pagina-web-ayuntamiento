<?php
if (session_status() === PHP_SESSION_NONE) session_start();
// Si ya está logueado, redirigir a home
if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit;
}

$error_msg = "";
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'empty': $error_msg = "Por favor, completa todos los campos."; break;
        case 'invalid': $error_msg = "Email o contraseña incorrectos."; break;
        case 'db': $error_msg = "Error al conectar con la base de datos. Inténtalo más tarde."; break;
        default: $error_msg = "Ha ocurrido un error. Inténtalo de nuevo.";
    }
}

$redirect = $_GET['redirect'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Alquileres Tarancón</title>
    <link rel="preload" href="../assets/imagenes/LOGO-AYUNTAMIENTO-BLANCO.png" as="image">
    <link rel="stylesheet" href="../css/home.css">
    <style>
        .login-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 80vh;
            background: linear-gradient(135deg, #f4f6f8 0%, #e8ecf1 100%);
            padding: 2rem;
        }
        .login-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 440px;
            padding: 2.5rem;
            text-align: center;
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-card .logo-mini {
            width: 80px;
            margin-bottom: 1rem;
            opacity: 0.9;
        }
        .login-card h2 {
            color: #0b1c3c;
            margin-bottom: 0.3rem;
            font-size: 1.75rem;
        }
        .login-card .subtitle {
            color: #666;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }
        .form-group {
            margin-bottom: 1.25rem;
            text-align: left;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.4rem;
            color: #333;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .form-group input {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
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
        .btn-login {
            background: linear-gradient(135deg, #d15615 0%, #a44310 100%);
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
        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(209, 86, 21, 0.3);
        }
        .btn-login:active { transform: translateY(1px); }
        .alert-error {
            background-color: #ffebee;
            color: #c62828;
            padding: 0.8rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            border: 1px solid #ffcdd2;
        }
        .login-links {
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }
        .login-links a {
            color: #d15615;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }
        .login-links a:hover { color: #a44310; }
        .login-links .divider {
            display: block;
            margin: 0.8rem 0;
            color: #ccc;
            font-size: 0.85rem;
        }
        .login-links .back-link {
            color: #888;
            font-weight: 400;
        }
        .login-links .back-link:hover { color: #555; }

        /* Responsive mejoras */
        @media (max-width: 480px) {
            .login-wrapper {
                padding: 1rem 0.75rem;
                min-height: auto;
            }

            .login-card {
                padding: 1.75rem 1.25rem;
                border-radius: 12px;
            }

            .login-card .logo-mini {
                width: 60px;
            }

            .login-card h2 {
                font-size: 1.4rem;
            }

            .login-card .subtitle {
                font-size: 0.9rem;
                margin-bottom: 1.5rem;
            }

            .form-group input {
                padding: 0.75rem 0.9rem;
                font-size: 16px; /* Previene zoom en iOS */
            }

            .btn-login {
                padding: 0.85rem;
                font-size: 1rem;
            }

            .login-links {
                font-size: 0.85rem;
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

    <main class="login-wrapper">
        <div class="login-card">
            <h2>Iniciar Sesión</h2>
            <p class="subtitle">Accede a tu cuenta para reservar pistas deportivas.</p>

            <?php if ($error_msg): ?>
                <div class="alert-error"><?= htmlspecialchars($error_msg) ?></div>
            <?php endif; ?>

            <form action="../api/user-auth.php" method="POST">
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

                <div class="form-group">
                    <label for="email">Correo Electrónico</label>
                    <input type="email" id="email" name="email" required autofocus placeholder="tucorreo@email.com">
                </div>
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required placeholder="••••••••" minlength="8">
                </div>
                <button type="submit" class="btn-login">Entrar</button>
            </form>

            <div class="login-links">
                <p>¿No tienes cuenta? <a href="user-registro.php<?= $redirect ? '?redirect=' . urlencode($redirect) : '' ?>">Regístrate aquí</a></p>
                <span class="divider">— o —</span>
                <a href="home.php" class="back-link">← Volver al inicio sin cuenta</a>
            </div>
        </div>
    </main>

</body>
</html>
