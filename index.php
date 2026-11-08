<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atmósfera Dulce - Inicio</title>
    
    <style>
        /* --- ESTILOS BASE --- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            /* Fondo con imagen y respaldo por si no carga */
            background-color: #d0767f; 
            background-image: url('fondo.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* --- CONTENEDOR DE BIENVENIDA --- */
        .welcome-container {
            text-align: center;
            background-color: rgba(255, 255, 255, 0.95); /* Ligeramente traslúcido para resaltar sobre el fondo */
            padding: 50px 40px;
            border-radius: 12px;
            box-shadow: 0 6px 15px rgba(0,0,0,0.25);
            border: 2px solid #ff66b2;
            width: 100%;
            max-width: 550px;
            transition: transform 0.3s;
        }

        /* --- LOGO CENTRAL --- */
        .logo-box {
            width: 180px;
            height: 180px;
            margin: 0 auto 25px auto;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .img-logo {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        /* --- TÍTULOS --- */
        .main-title {
            font-size: 42px;
            font-weight: bold;
            color: #000000;
            margin-bottom: 10px;
        }

        .subtitle {
            font-size: 18px;
            color: #555555;
            margin-bottom: 40px;
            font-style: italic;
        }

        /* --- BOTONES DE ACCESO --- */
        .menu-actions {
            display: flex;
            flex-direction: column;
            gap: 15px;
            align-items: center;
        }

        .btn-home {
            width: 220px;
            padding: 12px 0;
            background-color: #ffffff;
            border: 1px solid #a6a6a6;
            border-radius: 6px;
            font-size: 18px;
            font-weight: bold;
            color: #000000;
            cursor: pointer;
            box-shadow: 0 3px 6px rgba(0,0,0,0.05);
            transition: background 0.2s, transform 0.1s, border-color 0.2s;
        }

        .btn-home:hover {
            background-color: #ffebf5;
            border-color: #ff99cc;
        }

        .btn-home:active {
            transform: scale(0.96);
        }

        /* --- PIE DE PÁGINA --- */
        .footer-text {
            margin-top: 30px;
            font-size: 14px;
            color: #666666;
        }
    </style>
</head>
<body>

    <div class="welcome-container">
        
        <div class="logo-box">
            <img src="Atmosfera Logo.png" alt="Logo Atmósfera Dulce" class="img-logo">
        </div>

        <h1 class="main-title">Atmósfera Dulce</h1>
        <p class="subtitle">Sistema de Gestión de Repostería</p>

        <div class="menu-actions">
            <button class="btn-home" onclick="location.href='login.php'">Iniciar Sesión</button> 
        </div>

        <p class="footer-text">© 2026 Todos los derechos reservados.</p>
    </div>

</body>
</html>