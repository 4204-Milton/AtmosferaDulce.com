<?php
session_start();

if (!isset($_SESSION['usuario_nombre'])) {
    header("Location: login.php?error=1");
    exit();
}

require_once 'conexion.php';

// Detectar el puesto o rol del usuario actual (soportando ambas variables de sesión)
$puesto_actual = strtolower(trim($_SESSION['usuario_puesto'] ?? $_SESSION['usuario_rol'] ?? ''));

// Definir permisos según las reglas solicitadas:
// - Cajero y Administrador pueden añadir productos a caja
$puede_agregar_a_caja = (
    strpos($puesto_actual, 'cajero') !== false || 
    strpos($puesto_actual, 'admin') !== false
);

// - Administrador y Repostero pueden añadir nuevos productos (ver la tarjeta "+")
$puede_añadir_productos = (
    strpos($puesto_actual, 'repostero') !== false || 
    strpos($puesto_actual, 'pastel') !== false || 
    strpos($puesto_actual, 'admin') !== false
);

// - Administrador y Repostero pueden editar las existencias de los productos
$puede_editar_productos = $puede_añadir_productos;

// Consultar los productos registrados
$query = "SELECT * FROM productos ORDER BY id ASC";
$resultado = $conexion->query($query);
$total_productos = ($resultado) ? $resultado->num_rows : 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - Atmósfera Dulce</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #d0767f;
            background-image: url('fondo.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
            padding: 40px 20px 90px 20px;
            position: relative;
        }

        /* Botón de volver con imagen BTN Izquierda.png */
        .btn-back {
            position: absolute;
            top: 30px;
            left: 30px;
            background: none;
            border: none;
            cursor: pointer;
        }

        .img-btn-back {
            width: 55px;
            height: 55px;
            object-fit: contain;
            transition: transform 0.2s ease;
        }

        .img-btn-back:hover {
            transform: scale(1.08);
        }

        .logo-container {
            position: absolute;
            top: 40px;
            right: 40px;
        }

        .img-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
        }

        /* Contenedor Principal tipo Tarjeta */
        .main-container {
            max-width: 1100px;
            margin: 40px auto 0 auto;
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.25);
            padding: 40px;
            text-align: center;
        }

        .section-title {
            font-size: 38px;
            font-weight: bold;
            color: #000000;
            margin-bottom: 20px;
            letter-spacing: 2px;
            text-transform: uppercase;
            font-family: 'Georgia', serif;
        }

        /* Estilo para el Buscador */
        .search-container {
            margin-bottom: 30px;
            display: flex;
            justify-content: center;
        }

        .search-input {
            width: 100%;
            max-width: 450px;
            padding: 12px 20px;
            font-size: 16px;
            border: 2px solid #ddd;
            border-radius: 30px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            background-color: #fff;
        }

        .search-input:focus {
            border-color: #ff66b2;
            box-shadow: 0 0 8px rgba(255, 102, 178, 0.3);
        }

        /* Grid de Tarjetas de Productos */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            justify-content: center;
        }

        /* Tarjeta individual */
        .product-card {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            position: relative;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        /* Checkbox de selección múltiple en la esquina superior izquierda */
        .product-checkbox {
            position: absolute;
            top: 15px;
            left: 15px;
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #ff66b2;
        }

        /* Icono de Editar en la esquina superior derecha */
        .btn-edit-icon {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 28px;
            height: 28px;
            object-fit: contain;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .btn-edit-icon:hover {
            transform: scale(1.15);
        }

        /* Contenedor de la Imagen del Producto */
        .product-img-box {
            width: 120px;
            height: 120px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .product-name {
            font-size: 18px;
            font-weight: bold;
            color: #333333;
            margin-bottom: 6px;
            text-transform: capitalize;
        }

        .product-price {
            font-size: 16px;
            color: #28a745;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .product-stock {
            font-size: 14px;
            color: #666666;
            font-weight: 500;
            margin-bottom: 12px;
        }

        .product-stock span {
            font-weight: bold;
            color: #437385;
        }

        /* Controles de cantidad por producto */
        .quantity-box {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            margin-top: 5px;
        }

        .quantity-box label {
            font-size: 14px;
            font-weight: 600;
            color: #444;
        }

        .quantity-input {
            width: 60px;
            padding: 5px;
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            border: 1px solid #ccc;
            border-radius: 6px;
            outline: none;
        }

        /* Botón flotante en la parte inferior derecha */
        .floating-action-container {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 100;
        }

        .btn-add-multiple {
            background: linear-gradient(135deg, #ff66b2, #e65c9e);
            color: white;
            border: none;
            padding: 16px 30px;
            font-size: 18px;
            font-weight: bold;
            border-radius: 35px;
            cursor: pointer;
            box-shadow: 0 6px 20px rgba(230, 92, 158, 0.5);
            transition: transform 0.2s, background 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-add-multiple:hover {
            transform: translateY(-3px);
            background: linear-gradient(135deg, #e65c9e, #cc4d8b);
        }

        /* Estilo para las tarjetas vacías con el botón "+" */
        .card-empty {
            text-decoration: none;
            border: 2px dashed #cccccc;
            background-color: #fafafa;
            min-height: 280px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .card-empty:hover {
            border-color: #ff66b2;
            background-color: #fff0f5;
        }

        .img-plus {
            width: 60px;
            height: 60px;
            opacity: 0.7;
            transition: transform 0.2s;
        }

        .card-empty:hover .img-plus {
            transform: scale(1.15);
            opacity: 1;
        }
    </style>
</head>
<body>

    <!-- Botón para volver al menú principal -->
    <button class="btn-back" onclick="location.href='main.php'" title="Volver al Menú">
        <img src="BTN Izquierda.png" alt="Volver" class="img-btn-back">
    </button>

    <!-- Logo de la esquina superior derecha -->
    <div class="logo-container">
        <img src="Atmosfera Logo.png" alt="Logo" class="img-logo">
    </div>

    <div class="main-container">
        <h1 class="section-title">PRODUCTOS</h1>

        <!-- Campo de búsqueda en tiempo real -->
        <div class="search-container">
            <input type="text" id="buscadorProductos" class="search-input" placeholder="🔍 Buscar producto por nombre..." onkeyup="filtrarProductos()">
        </div>

        <!-- Formulario general para enviar múltiples productos seleccionados -->
        <form action="caja.php" method="POST" id="formProductos">
            <div class="products-grid" id="gridProductos">
                <?php if ($total_productos > 0): ?>
                    <!-- Mostrar productos existentes de la base de datos -->
                    <?php while ($prod = $resultado->fetch_assoc()): 
                        $stock = intval($prod['existencias']);
                    ?>
                        <div class="product-card" data-nombre="<?php echo strtolower(htmlspecialchars($prod['nombre'])); ?>">
                            <!-- Checkbox de selección -->
                            <?php if ($puede_agregar_a_caja && $stock > 0): ?>
                                <input type="checkbox" name="productos_seleccionados[]" value="<?php echo $prod['id']; ?>" class="product-checkbox" title="Seleccionar producto">
                            <?php endif; ?>

                            <!-- Icono de edición (Solo Admin y Repostero) -->
                            <?php if ($puede_editar_productos): ?>
                                <a href="editar_producto.php?id=<?php echo $prod['id']; ?>" title="Editar existencias">
                                    <img src="editar.png" alt="Editar" class="btn-edit-icon">
                                </a>
                            <?php endif; ?>

                            <div class="product-img-box">
                                <img src="<?php echo htmlspecialchars($prod['imagen']); ?>" alt="Producto" class="product-img">
                            </div>
                            <div class="product-name"><?php echo htmlspecialchars($prod['nombre']); ?></div>
                            <div class="product-price">$<?php echo number_format($prod['precio'], 2); ?></div>
                            <div class="product-stock">Existencias: <span><?php echo $stock; ?></span></div>
                            
                            <!-- Input de cantidad para este producto -->
                            <?php if ($puede_agregar_a_caja): ?>
                                <div class="quantity-box">
                                    <label for="cant_<?php echo $prod['id']; ?>">Cant:</label>
                                    <input type="number" id="cant_<?php echo $prod['id']; ?>" name="cantidades[<?php echo $prod['id']; ?>]" value="1" min="1" max="<?php echo max(1, $stock); ?>" class="quantity-input">
                                </div>

                                <!-- Inputs ocultos para procesar los datos del producto al enviar -->
                                <input type="hidden" name="nombres[<?php echo $prod['id']; ?>]" value="<?php echo htmlspecialchars($prod['nombre']); ?>">
                                <input type="hidden" name="precios[<?php echo $prod['id']; ?>]" value="<?php echo $prod['precio']; ?>">
                                <input type="hidden" name="imagenes[<?php echo $prod['id']; ?>]" value="<?php echo htmlspecialchars($prod['imagen']); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>

                    <!-- La tarjeta "+" para añadir nuevos productos (solo Admin o Repostero) -->
                    <?php if ($puede_añadir_productos): ?>
                        <a href="añadir_productos.php" class="product-card card-empty" data-nombre="añadir nuevo producto" title="Añadir nuevo producto">
                            <img src="mas.png" alt="Agregar" class="img-plus">
                        </a>
                    <?php endif; ?>

                <?php else: ?>
                    <?php if ($puede_añadir_productos): ?>
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <a href="añadir_productos.php" class="product-card card-empty" data-nombre="añadir nuevo producto" title="Añadir nuevo producto">
                                <img src="mas.png" alt="Agregar" class="img-plus">
                            </a>
                        <?php endfor; ?>
                    <?php else: ?>
                        <div id="sinResultados" style="grid-column: 1 / -1; padding: 30px; color: #555; font-size: 18px;">
                            No hay productos registrados en este momento.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Botón flotante en la parte inferior derecha para añadir a caja -->
            <?php if ($puede_agregar_a_caja && $total_productos > 0): ?>
                <div class="floating-action-container">
                    <button type="submit" name="agregar_multiples" class="btn-add-multiple">
                        🛒 Añadir seleccionados a la caja
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Script de filtrado en tiempo real -->
    <script>
        function filtrarProductos() {
            let input = document.getElementById('buscadorProductos').value.toLowerCase().trim();
            let tarjetas = document.querySelectorAll('.product-card');

            tarjetas.forEach(tarjeta => {
                let nombreProducto = tarjeta.getAttribute('data-nombre') || '';
                // Permitir siempre mostrar la tarjeta del botón "+" si es el admin/repostero
                if (tarjeta.classList.contains('card-empty')) {
                    tarjeta.style.display = 'flex';
                    return;
                }

                if (nombreProducto.includes(input)) {
                    tarjeta.style.display = 'flex';
                } else {
                    tarjeta.style.display = 'none';
                }
            });
        }
    </script>

</body>
</html>