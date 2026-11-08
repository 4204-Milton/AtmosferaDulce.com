<?php
session_start();

// Si el usuario ya inició sesión, se redirige directamente a main.php
if (isset($_SESSION['usuario_nombre'])) {
    header("Location: main.php");
    exit();
}

$error = isset($_GET['error']) ? "Usuario o contraseña incorrectos." : "";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido - Atmósfera Dulce</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            display: flex;
            height: 100vh;
            width: 100vw;
            overflow: hidden;
        }

        /* Lado Izquierdo (Rosa con Logo Central) */
        .left-panel {
            flex: 1;
            background-color: #d0767f;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .left-panel img {
            width: 320px;
            height: 320px;
            object-fit: contain;
        }

        /* Lado Derecho (Gris claro con Formulario) */
        .right-panel {
            flex: 1;
            background-color: #eef0f2;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .login-box {
            width: 100%;
            max-width: 360px;
            text-align: center;
        }

        .main-title {
            font-size: 42px;
            font-weight: bold;
            color: #000000;
            margin-bottom: 8px;
        }

        .subtitle {
            font-size: 18px;
            color: #444444;
            margin-bottom: 35px;
        }

        .error-msg {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            border-radius: 4px;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .input-group {
            margin-bottom: 25px;
            text-align: center;
        }

        .input-group label {
            display: block;
            font-size: 18px;
            color: #333333;
            margin-bottom: 10px;
        }

        .input-group input {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            border: 1px solid #c0c0c0;
            background-color: #ffffff;
            outline: none;
            border-radius: 2px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
        }

        .btn-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            margin-top: 10px;
        }

        .btn {
            width: 140px;
            padding: 8px 0;
            background-color: #ffffff;
            border: 1px solid #a6a6a6;
            border-radius: 3px;
            font-size: 16px;
            color: #000000;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: background-color 0.2s, transform 0.1s;
        }

        .btn:hover {
            background-color: #f5f5f5;
        }

        .btn:active {
            transform: scale(0.97);
        }
    </style>
</head>
<body>

    <div class="left-panel">
        <img src="AtmosferaLogo.png" alt="Atmósfera Dulce Logo">
    </div>

    <div class="right-panel">
        <div class="login-box">
            <h1 class="main-title">Bienvenido</h1>
            <p class="subtitle">Iniciar Sesión</p>

            <?php if (!empty($error)): ?>
                <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- CORREGIDO: Apunta a autenticar.php para procesar el acceso -->
            <form action="autenticar.php" method="POST">
                <div class="input-group">
                    <label for="usuario">Nombre de usuario</label>
                    <input type="text" id="usuario" name="usuario" required autocomplete="off">
                </div>

                <div class="input-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="btn-container">
                    <button type="submit" class="btn">Entrar</button>
                    <a href="registro.php" class="btn">Registrar</a>
                </div>
            </form>
        </div>
    </div>

</body>
</html>