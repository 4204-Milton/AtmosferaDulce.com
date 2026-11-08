<?php
session_start();

if (!isset($_SESSION['usuario_nombre'])) {
    header("Location: login.php?error=1");
    exit();
}

require_once 'conexion.php';

$mensaje = "";
$tipo_mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre         = trim($_POST['nombre'] ?? '');
    $cantidad       = intval($_POST['cantidad'] ?? 0);
    $fecha_entrada  = trim($_POST['fecha_entrada'] ?? '');
    $es_objeto      = isset($_POST['es_objeto']); // Verifica si marcó la casilla de objeto
    
    // Si es objeto, la caducidad no aplica; de lo contrario, toma la fecha ingresada
    $caducidad      = $es_objeto ? 'No aplica' : trim($_POST['caducidad'] ?? '');
    $hoy            = date('Y-m-d');

    // Validar que no estén vacíos
    if (empty($nombre) || empty($fecha_entrada) || (!$es_objeto && empty($caducidad))) {
        $mensaje = "Por favor, completa todos los campos obligatorios.";
        $tipo_mensaje = "error";
    } elseif (!$es_objeto && $caducidad < $hoy) {
        // Validar que la fecha de caducidad no sea una fecha pasada (solo si no es objeto)
        $mensaje = "Error: La fecha de caducidad no puede ser una fecha pasada.";
        $tipo_mensaje = "error";
    } else {
        // Insertar en la tabla 'insumos'
        $stmt = $conexion->prepare("INSERT INTO insumos (nombre, cantidad, fecha_entrada, caducidad) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("siss", $nombre, $cantidad, $fecha_entrada, $caducidad);

        if ($stmt->execute()) {
            $mensaje = "¡Insumo añadido correctamente!";
            $tipo_mensaje = "exito";
            header("Refresh: 1.5; URL=inventario.php");
        } else {
            $mensaje = "Error al registrar el insumo: " . $stmt->error;
            $tipo_mensaje = "error";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Añadir Insumo - Atmósfera Dulce</title>
    
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
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 20px;
        }

        /* Botón de volver superior izquierdo con BTN Izquierda.png */
        .btn-back {
            position: absolute;
            top: 25px;
            left: 25px;
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

        /* Logo superior derecho Azul.png */
        .top-right-logo {
            position: absolute;
            top: 25px;
            right: 25px;
            width: 100px;
            height: auto;
        }

        /* Card / Tarjeta Estilo Modal */
        .form-card {
            width: 100%;
            max-width: 440px;
            background-color: #427888;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            position: relative;
            margin-top: 30px;
        }

        /* Cabecera Coral / Rosa */
        .card-header {
            background-color: #e5737d;
            padding: 24px;
            text-align: center;
        }

        .card-header h2 {
            color: #ffffff;
            font-size: 32px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        /* Cuerpo del Formulario */
        .card-body {
            padding: 25px 35px 35px 35px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        /* Contenedor flexible para alinear etiqueta y la casilla miniatura "Objeto" */
        .label-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-left: 5px;
            padding-right: 5px;
        }

        .form-label {
            color: #ffffff;
            font-size: 14px;
            font-weight: 500;
        }

        /* Estilo para la casilla en miniatura "Objeto" */
        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #ffffff;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
        }

        .checkbox-container input {
            width: 15px;
            height: 15px;
            cursor: pointer;
            accent-color: #e5737d;
        }

        .form-input {
            width: 100%;
            padding: 12px 18px;
            border-radius: 20px;
            border: none;
            outline: none;
            font-size: 15px;
            color: #444;
            background-color: #ffffff;
            text-align: center;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.08);
        }

        .form-input:disabled {
            background-color: #e0e0e0;
            color: #888;
            cursor: not-allowed;
        }

        .form-input::placeholder {
            color: #999;
            text-align: center;
        }

        /* Botón Añadir Principal */
        .btn-submit {
            margin-top: 10px;
            align-self: center;
            background-color: #e5737d;
            color: #ffffff;
            border: none;
            padding: 12px 40px;
            font-size: 16px;
            font-weight: bold;
            border-radius: 20px;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.1s ease;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }

        .btn-submit:hover {
            background-color: #d4636f;
            transform: translateY(-1px);
        }

        /* Alertas */
        .alert {
            padding: 10px 12px;
            border-radius: 12px;
            font-size: 13px;
            text-align: center;
            font-weight: bold;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .alert-exito {
            background-color: #d4edda;
            color: #155724;
        }
    </style>
</head>
<body>

    <!-- Botón Izquierdo para Volver -->
    <button class="btn-back" onclick="location.href='inventario.php'" title="Volver al Inventario">
        <img src="BTN Izquierda.png" alt="Volver" class="img-btn-back">
    </button>

    <!-- Logo Derecho -->
    <img src="Azul.png" alt="Logo Atmósfera Dulce" class="top-right-logo">

    <div class="form-card">
        <!-- Cabecera con Título limpio -->
        <div class="card-header">
            <h2>Añadir insumos</h2>
        </div>

        <!-- Formulario -->
        <form action="añadir.php" method="POST" class="card-body">
            
            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <div class="label-container">
                    <label class="form-label">Nombre del producto</label>
                    <!-- Casilla en miniatura al lado del primer campo -->
                    <label class="checkbox-container" title="Marcar si es un objeto no comestible">
                        <input type="checkbox" id="es_objeto" name="es_objeto" onchange="toggleCaducidad()"> Objeto
                    </label>
                </div>
                <input type="text" name="nombre" class="form-input" placeholder="Ej. Vasos o Chispas" value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Cantidad</label>
                <input type="number" name="cantidad" class="form-input" placeholder="Ej. 10" min="0" value="<?php echo htmlspecialchars($_POST['cantidad'] ?? '1'); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label">Fecha de Ingreso</label>
                <input type="date" name="fecha_entrada" class="form-input" value="<?php echo htmlspecialchars($_POST['fecha_entrada'] ?? date('Y-m-d')); ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" id="label_caducidad">Caducidad</label>
                <input type="date" id="caducidad" name="caducidad" class="form-input" min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($_POST['caducidad'] ?? ''); ?>" required>
            </div>

            <button type="submit" class="btn-submit">Añadir</button>
        </form>
    </div>

    <script>
        // Función para bloquear/desbloquear y limpiar el campo de caducidad si es objeto
        function toggleCaducidad() {
            const esObjeto = document.getElementById('es_objeto').checked;
            const inputCaducidad = document.getElementById('caducidad');
            const labelCaducidad = document.getElementById('label_caducidad');

            if (esObjeto) {
                inputCaducidad.value = ''; // Limpiar valor
                inputCaducidad.disabled = true; // Bloquear campo
                inputCaducidad.required = false; // Quitar requisito obligatorio
                labelCaducidad.style.opacity = '0.5';
            } else {
                inputCaducidad.disabled = false; // Habilitar campo
                inputCaducidad.required = true; // Volver obligatorio
                labelCaducidad.style.opacity = '1';
            }
        }

        // Ejecutar al cargar por si recarga con error
        window.onload = function() {
            if (document.getElementById('es_objeto').checked) {
                toggleCaducidad();
            }
        };
    </script>

</body>
</html>