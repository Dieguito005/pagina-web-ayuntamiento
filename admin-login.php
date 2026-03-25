<?php
session_start();
if (isset($_SESSION['admin_id'])) {
    header("Location: admin-dashboard.php");
    exit;
}

$error_msg = "";
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'empty') $error_msg = "Por favor, completa todos los campos.";
    else if ($_GET['error'] === 'invalid') $error_msg = "Usuario o contraseña incorrectos.";
    else if ($_GET['error'] === 'db') $error_msg = "Error al conectar con la base de datos.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Empleados - Ayuntamiento de Tarancón</title>
    <link rel="stylesheet" href="home.css">
    <style>
        .admin-login-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 80vh;
            background-color: #f4f6f8;
            padding: 2rem;
        }
        .admin-login-card {
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            padding: 2.5rem;
            text-align: center;
        }
        .admin-login-card img {
            max-width: 150px;
            margin-bottom: 1.5rem;
        }
        .admin-login-card h2 {
            color: #0b1c3c;
            margin-bottom: 0.5rem;
            font-size: 1.75rem;
        }
        .admin-login-card p {
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
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .form-group input {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: #d15615;
            box-shadow: 0 0 0 3px rgba(209, 86, 21, 0.1);
        }
        .btn-submit {
            background-color: #d15615;
            color: white;
            border: none;
            padding: 1rem;
            width: 100%;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
            margin-top: 1rem;
        }
        .btn-submit:hover {
            background-color: #a44310;
        }
        .btn-submit:active {
            transform: translateY(1px);
        }
        .alert-error {
            background-color: #ffebee;
            color: #c62828;
            padding: 0.8rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            border: 1px solid #ffcdd2;
        }
    </style>
</head>
<body>

    <header class="main-header" style="position: sticky;">
        <div class="header-container">
            <div class="header-logo">
                <a href="home.html" class="logo-home">
                    <div class="logo-image-box">
                         <!-- Reusing the black logo logic from navbar if wanted, or forcing the white one for dark header -->
                        <img class="logo-ayuntamiento" src="assets/imagenes/LOGO-AYUNTAMIENTO-BLANCO.png" alt="Logo">
                    </div>
                </a>
            </div>
            <div class="header-title">
                <h1> Acceso Portal Empleados </h1>
            </div>
            <div style="width: 120px;"></div> <!-- Spacer para equilibrar logo -->
        </div>
    </header>

    <main class="admin-login-wrapper">
        <div class="admin-login-card">
            <h2>Acceso Restringido</h2>
            <p>Introduce tus credenciales para administrar las reservas.</p>

            <?php if ($error_msg): ?>
                <div class="alert-error"><?= htmlspecialchars($error_msg) ?></div>
            <?php endif; ?>

            <form action="admin-auth.php" method="POST">
                <div class="form-group">
                    <label for="usuario">Usuario</label>
                    <input type="text" id="usuario" name="usuario" required autofocus placeholder="Ej. admin_juan">
                </div>
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required placeholder="••••••••">
                </div>
                <button type="submit" class="btn-submit">Entrar al Panel</button>
            </form>
            <div style="margin-top: 20px;">
                <a href="home.html" style="color: #666; text-decoration: none; font-size: 0.9rem;">← Volver al inicio</a>
            </div>
        </div>
    </main>

</body>
</html>
