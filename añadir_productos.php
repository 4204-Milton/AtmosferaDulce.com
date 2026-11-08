<?php
session_start();

if (!isset($_SESSION['usuario_nombre'])) {
    header("Location: login.php?error=1");
    exit();
}

require_once 'conexion.php';

$mensaje = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $precio = trim($_POST['precio'] ?? '');
    $existencias = intval($_POST['existencias'] ?? 0);
    
    // Validar campos vacíos
    if (empty($nombre) || empty($precio) || $existencias < 0) {
        $error = "Por favor, completa todos los campos correctamente.";
    } else {
        // Manejo de la subida de la imagen
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['imagen']['tmp_name'];
            $fileName = $_FILES['imagen']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            // Extensiones permitidas
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            
            if (in_array($fileExtension, $allowedExtensions)) {
                // Crear un nombre único para evitar sobrescribir archivos
                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                $uploadFileDir = 'uploads/';
                
                // Crear la carpeta uploads si no existe
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0755, true);
                }
                
                $dest_path = $uploadFileDir . $newFileName;
                
                if(move_uploaded_file($fileTmpPath, $dest_path)) {
                    // Insertar en la base de datos incluyendo el campo existencias
                    // Nota: Asegúrate de que tu tabla 'productos' tenga una columna llamada 'existencias' (INT)
                    $stmt = $conexion->prepare("INSERT INTO productos (nombre, precio, existencias, imagen) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("sdis", $nombre, $precio, $existencias, $dest_path);
                    
                    if ($stmt->execute()) {
                        $mensaje = "¡Producto añadido exitosamente!";
                        header("Refresh: 2; URL=productos.php");
                    } else {
                        $error = "Error al guardar en la base de datos: " . $conexion->error;
                    }
                    $stmt->close();
                } else {
                    $error = "Error al mover el archivo subido al servidor.";
                }
            } else {
                $error = "Formato de imagen no permitido. Usa JPG, JPEG, PNG o WEBP.";
            }
        } else {
            $error = "Por favor, selecciona una imagen para el producto.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Añadir Productos - Atmósfera Dulce</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #bdc8cd;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
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

        /* Contenedor principal del formulario */
        .form-container {
            width: 100%;
            max-width: 500px;
            background-color: #437385;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Cabecera del formulario */
        .form-header {
            background-color: #E27B83;
            color: #ffffff;
            padding: 25px;
            text-align: center;
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 1px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Cuerpo del formulario */
        .form-body {
            padding: 30px 40px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .input-label {
            color: #ffffff;
            font-size: 15px;
            font-weight: 500;
            margin-bottom: 5px;
            display: block;
        }

        .input-field {
            width: 100%;
            padding: 12px 20px;
            border-radius: 30px;
            border: none;
            outline: none;
            font-size: 15px;
            color: #333333;
            background-color: #ffffff;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.06);
        }

        .input-field::placeholder {
            color: #888888;
        }

        /* Estilo personalizado para el input file */
        .file-upload-wrapper {
            background-color: #ffffff;
            border-radius: 30px;
            padding: 11px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.06);
            cursor: pointer;
        }

        .file-upload-label {
            font-size: 15px;
            color: #888888;
            cursor: pointer;
            width: 100%;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        input[type="file"] {
            display: none;
        }

        /* Botón de Enviar */
        .btn-submit {
            width: 160px;
            padding: 12px;
            background-color: #E27B83;
            color: #ffffff;
            border: none;
            border-radius: 30px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.2s ease, transform 0.1s ease;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            margin: 10px auto 0 auto;
            display: block;
        }

        .btn-submit:hover {
            background-color: #cc6b73;
            transform: translateY(-2px);
        }

        /* Alertas de éxito o error */
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 14px;
        }
    </style>
</head>
<body>

    <!-- Botón para volver a la vista de productos -->
    <button class="btn-back" onclick="location.href='productos.php'" title="Volver a Productos">
        <img src="BTN Izquierda.png" alt="Volver" class="img-btn-back">
    </button>

    <!-- Logo institucional -->
    <div class="logo-container">
        <img src="AtmosferaLogo.png" alt="Logo" class="img-logo">
    </div>

    <div class="form-container">
        <div class="form-header">
            Añadir productos
        </div>

        <div class="form-body">
            <?php if (!empty($mensaje)): ?>
                <div class="alert-success"><?php echo $mensaje; ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form action="añadir_productos.php" method="POST" enctype="multipart/form-data">
                
                <div style="margin-bottom: 15px;">
                    <label class="input-label">Nombre del producto</label>
                    <input type="text" name="nombre" class="input-field" placeholder="Ej. Pastel de chocolate" required autocomplete="off">
                </div>

                <div style="margin-bottom: 15px;">
                    <label class="input-label">Precio</label>
                    <input type="number" step="0.01" name="precio" class="input-field" placeholder="Ej. 150.00" required autocomplete="off">
                </div>

                <div style="margin-bottom: 15px;">
                    <label class="input-label">Existencias</label>
                    <input type="number" name="existencias" class="input-field" placeholder="Ej. 20" min="0" required autocomplete="off">
                </div>

                <div style="margin-bottom: 25px;">
                    <label class="input-label">Imagen del producto</label>
                    <div class="file-upload-wrapper" onclick="document.getElementById('imagenInput').click();">
                        <span id="file-name-label" class="file-upload-label">Seleccionar archivo...</span>
                    </div>
                    <input type="file" name="imagen" id="imagenInput" accept="image/png, image/jpeg, image/jpg, image/webp" required onchange="actualizarNombreArchivo(this)">
                </div>

                <button type="submit" class="btn-submit">Añadir</button>
            </form>
        </div>
    </div>

    <script>
        function actualizarNombreArchivo(input) {
            const label = document.getElementById('file-name-label');
            if (input.files && input.files.length > 0) {
                label.textContent = input.files[0].name;
                label.style.color = "#333333";
            } else {
                label.textContent = "Seleccionar archivo...";
                label.style.color = "#888888";
            }
        }
    </script>

</body>
</html>