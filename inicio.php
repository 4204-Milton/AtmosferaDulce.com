<?php
session_start();

// Si el usuario ya tiene sesión activa, redirige al menú principal
if (isset($_SESSION['usuario_nombre'])) {
    header("Location: main.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atmósfera Dulce</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }

        body {
            background-color: #d0767f; /* Color rosa del fondo exacto */
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* Tarjeta Blanca Central */
        .card-container {
            background-color: #ffffff;
            width: 100%;
            max-width: 480px;
            padding: 50px 40px 30px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Imagen del Logo 'Azul.png' */
        .logo-img {
            width: 140px;
            height: 140px;
            object-fit: contain;
            margin-bottom: 25px;
        }

        /* Título Principal */
        .main-title {
            font-size: 32px;
            font-weight: 800;
            color: #000000;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        /* Subtítulo en cursiva */
        .subtitle {
            font-size: 16px;
            font-style: italic;
            color: #555555;
            margin-bottom: 35px;
        }

        /* Botón Iniciar Sesión */
        .btn-login {
            display: inline-block;
            text-decoration: none;
            color: #000000;
            font-size: 16px;
            font-weight: 700;
            padding: 10px 28px;
            border: 1px solid #b0b0b0;
            border-radius: 6px;
            background-color: #ffffff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
            margin-bottom: 50px;
        }

        .btn-login:hover {
            background-color: #f8f8f8;
            border-color: #888888;
            transform: translateY(-1px);
        }

        .btn-login:active {
            transform: scale(0.98);
        }

        /* Copyright inferior dentro de la tarjeta */
        .footer-text {
            font-size: 13px;
            color: #888888;
        }
    </style>
</head>
<body>

    <div class="card-container">
        <!-- Imagen A azul solicitada -->
        <img src="Azul.png" alt="Atmósfera Dulce Logo" class="logo-img">
        
        <h1 class="main-title">Atmósfera Dulce</h1>
        <p class="subtitle">Sistema de Gestión de Repostería</p>

        <!-- Redirección al login -->
        <a href="login.php" class="btn-login">Iniciar Sesión</a>

        <p class="footer-text">&copy; <?php echo date('Y'); ?> Todos los derechos reservados.</p>
    </div>

</body>
</html>