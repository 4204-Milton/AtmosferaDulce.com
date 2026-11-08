<?php
session_start();

if (!isset($_SESSION['usuario_nombre'])) {
    header("Location: login.php?error=1");
    exit();
}

require_once 'conexion.php';

// Detectar rol y validar permisos (Solo Administrador y Repostero)
$puesto_actual = strtolower(trim($_SESSION['usuario_puesto'] ?? $_SESSION['usuario_rol'] ?? ''));
$puede_editar = (
    strpos($puesto_actual, 'repostero') !== false || 
    strpos($puesto_actual, 'pastel') !== false || 
    strpos($puesto_actual, 'admin') !== false
);

if (!$puede_editar) {
    header("Location: productos.php?error=sin_permiso");
    exit();
}

// Validar que se reciba el ID del producto
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: productos.php");
    exit();
}

$id_producto = intval($_GET['id']);

// Obtener la información actual del producto primero (para conservar la imagen anterior si no se cambia)
$stmt_prod = $conexion->prepare("SELECT * FROM productos WHERE id = ?");
$stmt_prod->bind_param("i", $id_producto);
$stmt_prod->execute();
$resultado = $stmt_prod->get_result();

if ($resultado && $resultado->num_rows > 0) {
    $producto = $resultado->fetch_assoc();
} else {
    header("Location: productos.php");
    exit();
}
$stmt_prod->close();

// Procesar formulario al enviar la actualización completa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_producto'])) {
    $nuevo_nombre = trim($_POST['nombre']);
    $nuevo_precio = floatval($_POST['precio']);
    $nuevas_existencias = intval($_POST['existencias']);
    $imagen_ruta = $producto['imagen']; // Por defecto conservamos la actual

    // Verificar si se subió una nueva imagen
    if (isset($_FILES['nueva_imagen']) && $_FILES['nueva_imagen']['error'] === UPLOAD_ERR_OK) {
        $nombre_archivo = $_FILES['nueva_imagen']['name'];
        $tmp_name = $_FILES['nueva_imagen']['tmp_name'];
        
        // Carpeta destino (asegúrate de que exista en tu proyecto)
        $carpeta_destino = ""; 
        $ruta_final = $carpeta_destino . basename($nombre_archivo);

        if (move_uploaded_file($tmp_name, $ruta_final)) {
            $imagen_ruta = $ruta_final; // Actualizamos con la nueva ruta
        }
    }

    // Validar datos mínimos antes de actualizar
    if (!empty($nuevo_nombre) && $nuevo_precio >= 0 && $nuevas_existencias >= 0) {
        $stmt_update = $conexion->prepare("UPDATE productos SET nombre = ?, precio = ?, existencias = ?, imagen = ? WHERE id = ?");
        $stmt_update->bind_param("sdisi", $nuevo_nombre, $nuevo_precio, $nuevas_existencias, $imagen_ruta, $id_producto);
        $stmt_update->execute();
        $stmt_update->close();

        header("Location: productos.php?exito=actualizado");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto - Atmósfera Dulce</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
        }

        /* Botón de volver */
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

        /* Tarjeta de Edición */
        .edit-container {
            width: 100%;
            max-width: 480px;
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 12px 30px rgba(0,0,0,0.25);
            padding: 35px;
            text-align: center;
            margin-top: 40px;
            margin-bottom: 40px;
        }

        .edit-title {
            font-size: 28px;
            font-weight: bold;
            color: #222;
            margin-bottom: 20px;
            font-family: 'Georgia', serif;
        }

        .product-preview-img {
            width: 100px;
            height: 100px;
            object-fit: contain;
            margin-bottom: 10px;
            border-radius: 10px;
            border: 1px solid #ddd;
            background: #fff;
            padding: 5px;
        }

        .form-group {
            margin-bottom: 18px;
            text-align: left;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #444;
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            font-size: 16px;
            font-weight: 500;
            border: 1px solid #ccc;
            border-radius: 10px;
            outline: none;
            background-color: #fff;
        }

        .form-control:focus {
            border-color: #ff66b2;
            box-shadow: 0 0 5px rgba(255, 102, 178, 0.3);
        }

        .file-input {
            padding: 6px;
            font-size: 14px;
        }

        .btn-save {
            background: linear-gradient(135deg, #23b2e8, #189ad3);
            color: white;
            border: none;
            width: 100%;
            padding: 14px;
            font-size: 18px;
            font-weight: bold;
            border-radius: 30px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(24, 154, 211, 0.4);
            transition: transform 0.2s, background 0.2s;
            margin-top: 10px;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #189ad3, #107cad);
        }
    </style>
</head>
<body>

    <!-- Botón para volver -->
    <button class="btn-back" onclick="location.href='productos.php'" title="Volver a Productos">
        <img src="BTN Izquierda.png" alt="Volver" class="img-btn-back">
    </button>

    <div class="logo-container">
        <img src="Atmosfera Logo.png" alt="Logo" class="img-logo">
    </div>

    <div class="edit-container">
        <h1 class="edit-title">Editar Producto</h1>

        <!-- Vista previa de la imagen actual -->
        <div>
            <img src="<?php echo htmlspecialchars($producto['imagen']); ?>" alt="Actual" class="product-preview-img" title="Imagen actual">
        </div>

        <form action="editar_producto.php?id=<?php echo $producto['id']; ?>" method="POST" enctype="multipart/form-data">
            
            <div class="form-group">
                <label for="nombre">Nombre del Producto:</label>
                <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($producto['nombre']); ?>" required class="form-control">
            </div>

            <div class="form-group">
                <label for="precio">Precio ($):</label>
                <input type="number" step="0.01" id="precio" name="precio" value="<?php echo htmlspecialchars($producto['precio']); ?>" min="0" required class="form-control">
            </div>

            <div class="form-group">
                <label for="existencias">Cantidad de Existencias:</label>
                <input type="number" id="existencias" name="existencias" value="<?php echo intval($producto['existencias']); ?>" min="0" required class="form-control">
            </div>

            <div class="form-group">
                <label for="nueva_imagen">Cambiar Imagen (Opcional):</label>
                <input type="file" id="nueva_imagen" name="nueva_imagen" accept="image/*" class="form-control file-input">
            </div>

            <button type="submit" name="actualizar_producto" class="btn-save">Guardar Cambios</button>
        </form>
    </div>

</body>
</html>