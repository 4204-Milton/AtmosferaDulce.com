<?php
session_start();

// Validar que el usuario haya iniciado sesión
if (!isset($_SESSION['usuario_nombre']) || !isset($_SESSION['usuario_rol'])) {
    header("Location: login.php?error=1");
    exit();
}

$nombre_usuario = $_SESSION['usuario_nombre'];
$rol = strtolower(trim($_SESSION['usuario_rol'])); // Ej: 'administrador', 'repostero', 'cajero', 'almacenista'

// Definir el nombre del puesto legible para mostrar en pantalla
$puesto_legible = ucfirst($rol);
if ($rol === 'cajero') {
    $puesto_legible = 'Cajera';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Principal - Atmósfera Dulce</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Georgia', serif;
        }

        body {
            background-image: url('Fondo_Postres.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }

        /* Capa oscura semitransparente opcional para mejorar legibilidad */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.25);
            z-index: 1;
        }

        .top-nav, .middle-section, footer {
            position: relative;
            z-index: 2;
        }

        /* Contenedor superior para los botones de navegación y apagado */
        .top-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 25px 40px;
            width: 100%;
        }

        .nav-buttons {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: center;
        }

        /* Estilo de los botones superiores */
        .nav-btn {
            background-color: #ffffff;
            color: #000000;
            text-decoration: none;
            padding: 8px 22px;
            font-size: 15px;
            font-weight: bold;
            font-family: 'Georgia', serif;
            border-radius: 4px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.2);
            transition: background 0.2s ease, transform 0.1s ease;
            display: inline-block;
            text-align: center;
        }

        .nav-btn:hover {
            background-color: #f2f2f2;
            transform: translateY(-2px);
        }

        /* Botón de apagado superior derecho */
        .btn-logout {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
        }

        .img-logout {
            width: 60px;
            height: 60px;
            object-fit: contain;
            transition: transform 0.2s ease;
        }

        .img-logout:hover {
            transform: scale(1.08);
        }

        /* Logo circular central superior */
        .logo-container {
            display: flex;
            justify-content: center;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            top: 15px;
            z-index: 3;
        }

        .img-circular {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            object-fit: cover;
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
            background-color: #ffffff;
        }

        /* Franja Central Transparente (Linea transparente.png) */
        .middle-section {
            background-image: url('Linea transparente.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            width: 100%;
            padding: 35px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            margin: auto 0;
        }

        /* Bloque de Bienvenida dentro de la franja */
        .welcome-container {
            text-align: center;
        }

        .welcome-container h2 {
            font-size: 28px;
            font-weight: bold;
            color: #000000;
        }

        .welcome-container p {
            font-size: 24px;
            font-weight: bold;
            color: #000000;
            margin-top: 5px;
        }

        /* Derechos reservados debajo del nombre y rol dentro de la misma franja */
        .rights-text {
            font-size: 14px;
            color: #333333;
            margin-top: 12px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Footer vacío para mantener distribución flex si es necesario, o se puede omitir */
        footer {
            height: 20px;
        }
    </style>
</head>
<body>

    <!-- Logo Circular Superior Central -->
    <div class="logo-container">
        <img src="Circular.png" alt="Logo Circular Atmósfera Dulce" class="img-circular">
    </div>

    <!-- Cabecera con Botones y Apagado -->
    <div class="top-nav">
        <div class="nav-buttons">
            <?php if ($rol === 'administrador'): ?>
                <!-- Opciones para Administrador -->
                <a href="reportes_admin.php" class="nav-btn">Panel</a>
                <a href="inventario.php" class="nav-btn">Inventario</a>
                <a href="productos.php" class="nav-btn">Productos</a>
                <a href="caja.php" class="nav-btn">Caja</a>
                <a href="ventas.php" class="nav-btn">Ventas</a>

            <?php elseif ($rol === 'repostero'): ?>
                <!-- Opciones para Repostero -->
                <a href="inventario.php" class="nav-btn">Inventario</a>
                <a href="productos.php" class="nav-btn">Productos</a>
                <a href="reportes.php" class="nav-btn">Reportes</a>

            <?php elseif ($rol === 'cajero'): ?>
                <!-- Opciones para Cajero/a -->
                <a href="caja.php" class="nav-btn">Caja</a>
                <a href="productos.php" class="nav-btn">Productos</a>
                <a href="ventas.php" class="nav-btn">Ventas</a>
                

            <?php elseif ($rol === 'almacenista'): ?>
                <!-- Opciones para Almacenista -->
                <a href="inventario.php" class="nav-btn">Inventario</a>
                <a href="reportes.php" class="nav-btn">Reportes</a>
            <?php endif; ?>
        </div>

        <!-- Botón de Apagado (Cerrar Sesión) -->
        <button class="btn-logout" onclick="location.href='logout.php'" title="Cerrar Sesión">
            <img src="boton-de-apagado.png" alt="Apagar sistema" class="img-logout">
        </button>
    </div>

    <!-- Franja Central Transparente con Bienvenida y Derechos Reservados -->
    <div class="middle-section">
        <div class="welcome-container">
            <h2>Bienvenido</h2>
            <p><?php echo htmlspecialchars($nombre_usuario); ?> - (<?php echo htmlspecialchars($puesto_legible); ?>)</p>
            <div class="rights-text">
                © 2026 Todos los derechos reservados.
            </div>
        </div>
    </div>

    <footer></footer>

</body>
</html>