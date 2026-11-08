<?php
session_start();

if (!isset($_SESSION['usuario_nombre'])) {
    header("Location: login.php?error=1");
    exit();
}

require_once 'conexion.php';

// 1. Agregar uno o múltiples productos a la caja (viniendo de productos.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_multiples'])) {
    if (isset($_POST['productos_seleccionados']) && is_array($_POST['productos_seleccionados'])) {
        foreach ($_POST['productos_seleccionados'] as $prod_id) {
            $prod_id = intval($prod_id);
            $cantidad_deseada = intval($_POST['cantidades'][$prod_id] ?? 1);
            $nombre = $_POST['nombres'][$prod_id] ?? '';
            $precio = floatval($_POST['precios'][$prod_id] ?? 0);
            $imagen = $_POST['imagenes'][$prod_id] ?? '';

            // Consultar las existencias reales actuales en la tabla productos
            $stmt_prod = $conexion->prepare("SELECT existencias FROM productos WHERE id = ?");
            $stmt_prod->bind_param("i", $prod_id);
            $stmt_prod->execute();
            $res_prod = $stmt_prod->get_result();
            $stock_disponible = 0;
            if ($row_prod = $res_prod->fetch_assoc()) {
                $stock_disponible = intval($row_prod['existencias']);
            }
            $stmt_prod->close();

            if ($cantidad_deseada > $stock_disponible) {
                $cantidad_deseada = $stock_disponible;
            }

            if ($cantidad_deseada > 0) {
                // Verificar si el producto ya se encuentra en la caja
                $check = $conexion->prepare("SELECT id, cantidad FROM caja WHERE producto_id = ?");
                $check->bind_param("i", $prod_id);
                $check->execute();
                $res_check = $check->get_result();

                if ($res_check->num_rows > 0) {
                    $fila_caja = $res_check->fetch_assoc();
                    $nueva_cant = $fila_caja['cantidad'] + $cantidad_deseada;

                    // Respetar el tope máximo del stock disponible
                    if ($nueva_cant > $stock_disponible) {
                        $nueva_cant = $stock_disponible;
                    }

                    $update = $conexion->prepare("UPDATE caja SET cantidad = ? WHERE producto_id = ?");
                    $update->bind_param("ii", $nueva_cant, $prod_id);
                    $update->execute();
                    $update->close();
                } else {
                    $insert = $conexion->prepare("INSERT INTO caja (producto_id, nombre, precio, cantidad, imagen) VALUES (?, ?, ?, ?, ?)");
                    $insert->bind_param("isdis", $prod_id, $nombre, $precio, $cantidad_deseada, $imagen);
                    $insert->execute();
                    $insert->close();
                }
                $check->close();
            }
        }
    }
    header("Location: caja.php");
    exit();
}

// 2. Modificar cantidad (+ o -) respetando el tope de existencias, o eliminar elemento
if (isset($_GET['accion']) && isset($_GET['id'])) {
    $id_caja = intval($_GET['id']);
    $accion = $_GET['accion'];

    $stmt_item = $conexion->prepare("SELECT c.*, p.existencias FROM caja c INNER JOIN productos p ON c.producto_id = p.id WHERE c.id = ?");
    $stmt_item->bind_param("i", $id_caja);
    $stmt_item->execute();
    $res_item = $stmt_item->get_result();

    if ($res_item->num_rows > 0) {
        $item = $res_item->fetch_assoc();
        $cant_actual = intval($item['cantidad']);
        $stock_max = intval($item['existencias']);

        if ($accion === 'mas') {
            if ($cant_actual + 1 <= $stock_max) {
                $conexion->query("UPDATE caja SET cantidad = cantidad + 1 WHERE id = $id_caja");
            }
        } elseif ($accion === 'menos') {
            if ($cant_actual > 1) {
                $conexion->query("UPDATE caja SET cantidad = cantidad - 1 WHERE id = $id_caja");
            } else {
                $conexion->query("DELETE FROM caja WHERE id = $id_caja");
            }
        } elseif ($accion === 'eliminar') {
            $conexion->query("DELETE FROM caja WHERE id = $id_caja");
        }
    }
    $stmt_item->close();
    header("Location: caja.php");
    exit();
}

// 3. Cancelar compra: vaciar caja
if (isset($_GET['accion']) && $_GET['accion'] === 'cancelar') {
    $conexion->query("DELETE FROM caja");
    header("Location: caja.php");
    exit();
}

// 4. Realizar compra: procesar, registrar en 'ventas', vaciar caja y redirigir al ticket
if (isset($_GET['accion']) && $_GET['accion'] === 'comprar') {
    $resultado_caja = $conexion->query("SELECT * FROM caja");
    if ($resultado_caja && $resultado_caja->num_rows > 0) {
        $usuario_emisor = $_SESSION['usuario_nombre'];
        $fecha_actual = date('Y-m-d H:i:s');
        $subtotal_general = 0;
        $gran_total_compra = 0;
        $cantidad_total_articulos = 0;
        $detalle_productos = [];

        while ($c = $resultado_caja->fetch_assoc()) {
            $sub = $c['precio'] * $c['cantidad'];
            $subtotal_general += $sub;
            $gran_total_compra += $sub;
            $cantidad_total_articulos += intval($c['cantidad']);
            
            $detalle_productos[] = $c['nombre'] . ' (x' . $c['cantidad'] . ') - $' . number_format($sub, 2, '.', '');

            // Descontar inventario de la tabla productos
            $pid = intval($c['producto_id']);
            $cant_vendida = intval($c['cantidad']);
            $conexion->query("UPDATE productos SET existencias = existencias - $cant_vendida WHERE id = $pid");
        }

        $resumen_productos = implode(', ', $detalle_productos);

        // Guardar registro en la tabla 'ventas'
        $stmt_venta = $conexion->prepare("INSERT INTO ventas (usuario, fecha, productos, cantidad, subtotal, total) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_venta->bind_param("sssidd", $usuario_emisor, $fecha_actual, $resumen_productos, $cantidad_total_articulos, $subtotal_general, $gran_total_compra);
        $stmt_venta->execute();
        $id_venta_generada = $stmt_venta->insert_id;
        $stmt_venta->close();

        // VACIAR LA CAJA EXPLÍCITAMENTE (Elimina todos los elementos de la tabla caja al completar la compra)
        $conexion->query("DELETE FROM caja");

        // Redirigir al script de generación de ticket pasando el ID de la venta
        header("Location: generar_ticket.php?id=" . $id_venta_generada);
        exit();
    }
    header("Location: caja.php");
    exit();
}

$resultado = $conexion->query("SELECT * FROM caja ORDER BY id DESC");
$gran_total = 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caja - Atmósfera Dulce</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #bdc8cd; min-height: 100vh; padding: 40px 20px; position: relative; }
        .btn-back { position: absolute; top: 30px; left: 30px; background: none; border: none; cursor: pointer; }
        .img-btn-back { width: 55px; height: 55px; object-fit: contain; transition: transform 0.2s ease; }
        .img-btn-back:hover { transform: scale(1.08); }
        .logo-container { position: absolute; top: 40px; right: 40px; }
        .img-logo { width: 80px; height: 80px; object-fit: contain; }
        .cart-container { max-width: 950px; margin: 50px auto 0 auto; background-color: #ffffff; border-radius: 20px; box-shadow: 0 12px 30px rgba(0,0,0,0.15); padding: 40px; }
        .cart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .cart-title { font-size: 32px; font-weight: bold; color: #222222; }
        .cart-table-header { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 60px; align-items: center; padding: 10px 15px; color: #666; font-weight: 600; font-size: 14px; border-bottom: 2px solid #eee; margin-bottom: 15px; }
        .cart-item { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 60px; align-items: center; background: #fdfdfd; border: 1px solid #eaeaea; border-radius: 12px; padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
        .item-info { display: flex; align-items: center; gap: 15px; }
        .item-img { width: 60px; height: 60px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd; background-color: #fff; }
        .item-name { font-size: 16px; font-weight: bold; color: #333; }
        .quantity-controls { display: flex; align-items: center; gap: 8px; }
        .btn-qty { background-color: #ff9800; color: #fff; border: none; width: 26px; height: 26px; border-radius: 6px; font-weight: bold; cursor: pointer; display: flex; align-items: center; justify-content: center; text-decoration: none; font-size: 14px; }
        .qty-value { font-weight: bold; font-size: 16px; min-width: 20px; text-align: center; }
        .item-price, .item-amount { font-size: 16px; font-weight: 600; color: #444; }
        .btn-delete { background-color: #ff4d4d; color: white; border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-weight: bold; text-decoration: none; }
        .cart-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
        .total-section { font-size: 20px; font-weight: bold; color: #333; }
        .total-amount { color: #28a745; font-size: 24px; }
        .btn-cancel { background-color: #e5737d; color: white; border: none; padding: 12px 25px; font-size: 16px; font-weight: bold; border-radius: 30px; cursor: pointer; text-decoration: none; transition: background 0.2s; }
        .btn-cancel:hover { background-color: #d4636f; }
        .btn-order { background: linear-gradient(135deg, #23b2e8, #189ad3); color: white; border: none; padding: 14px 35px; font-size: 18px; font-weight: bold; border-radius: 30px; cursor: pointer; box-shadow: 0 4px 15px rgba(24, 154, 211, 0.4); text-decoration: none; transition: transform 0.2s, background 0.2s; }
        .btn-order:hover { transform: translateY(-2px); background: linear-gradient(135deg, #189ad3, #107cad); }
        .empty-cart { text-align: center; padding: 40px; color: #777; font-size: 18px; }
    </style>
</head>
<body>

    <button class="btn-back" onclick="location.href='main.php'" title="Volver a Menu">
        <img src="BTN Izquierda.png" alt="Volver" class="img-btn-back">
    </button>

    <div class="logo-container">
        <img src="Atmosfera Logo.png" alt="Logo" class="img-logo">
    </div>

    <div class="cart-container">
        <div class="cart-header">
            <div class="cart-title">Caja</div>
        </div>

        <div class="cart-table-header">
            <div>Item</div>
            <div>Cantidad</div>
            <div>Precio</div>
            <div>Total</div>
            <div></div>
        </div>

        <?php if ($resultado && $resultado->num_rows > 0): ?>
            <?php while ($row = $resultado->fetch_assoc()): 
                $subtotal = $row['precio'] * $row['cantidad'];
                $gran_total += $subtotal;
            ?>
                <div class="cart-item">
                    <div class="item-info">
                        <img src="<?php echo htmlspecialchars($row['imagen']); ?>" alt="Img" class="item-img">
                        <div class="item-name"><?php echo htmlspecialchars($row['nombre']); ?></div>
                    </div>
                    
                    <div class="quantity-controls">
                        <a href="caja.php?accion=menos&id=<?php echo $row['id']; ?>" class="btn-qty">-</a>
                        <span class="qty-value"><?php echo $row['cantidad']; ?></span>
                        <a href="caja.php?accion=mas&id=<?php echo $row['id']; ?>" class="btn-qty">+</a>
                    </div>
                    
                    <div class="item-price">$<?php echo number_format($row['precio'], 2); ?></div>
                    <div class="item-amount">$<?php echo number_format($subtotal, 2); ?></div>
                    
                    <div>
                        <a href="caja.php?accion=eliminar&id=<?php echo $row['id']; ?>" class="btn-delete" title="Eliminar">✕</a>
                    </div>
                </div>
            <?php endwhile; ?>

            <div class="cart-footer">
                <a href="caja.php?accion=cancelar" class="btn-cancel" onclick="return confirm('¿Estás seguro de cancelar y limpiar la caja?');">
                    Cancelar Compra
                </a>

                <div class="total-section">
                    Total: <span class="total-amount">$<?php echo number_format($gran_total, 2); ?></span>
                </div>

                <a href="caja.php?accion=comprar" class="btn-order" onclick="return confirm('¿Confirmar compra y generar el ticket?');">
                    Realizar Compra →
                </a>
            </div>

        <?php else: ?>
            <div class="empty-cart">
                No hay productos en la caja actualmente. ¡Ve a la sección de productos para añadir algunos!
            </div>
        <?php endif; ?>
    </div>

</body>
</html>