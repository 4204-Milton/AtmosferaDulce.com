<?php
session_start();

if (!isset($_SESSION['usuario_nombre'])) {
    header("Location: login.php?error=1");
    exit();
}

require_once 'conexion.php';

$mensaje = "";
$tipo_mensaje = "";

// Procesar la modificación cuando se envía el formulario
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['producto_id'])) {
    $id = intval($_POST['producto_id']);
    $cantidad = intval($_POST['cantidad'] ?? 0);
    $fecha_entrada = $_POST['fecha_entrada'] ?? '';
    $es_objeto = isset($_POST['es_objeto']);
    
    // Si es objeto, la caducidad se guarda como 'No aplica'; de lo contrario, toma la fecha ingresada
    $caducidad = $es_objeto ? 'No aplica' : trim($_POST['caducidad'] ?? '');
    $hoy = date('Y-m-d');

    if ($id > 0 && $cantidad >= 0 && !empty($fecha_entrada) && ($es_objeto || !empty($caducidad))) {
        // Validar que la fecha de caducidad no sea una fecha pasada (solo si no es objeto)
        if (!$es_objeto && $caducidad < $hoy) {
            $mensaje = "Error: La fecha de caducidad no puede ser una fecha pasada.";
            $tipo_mensaje = "error";
        } else {
            $stmt = $conexion->prepare("UPDATE insumos SET cantidad = ?, fecha_entrada = ?, caducidad = ? WHERE id = ?");
            $stmt->bind_param("issi", $cantidad, $fecha_entrada, $caducidad, $id);

            if ($stmt->execute()) {
                $mensaje = "¡Insumo modificado correctamente!";
                $tipo_mensaje = "exito";
                header("Refresh: 1.5; URL=inventario.php");
            } else {
                $mensaje = "Error al actualizar: " . $stmt->error;
                $tipo_mensaje = "error";
            }
            $stmt->close();
        }
    } else {
        $mensaje = "Por favor completa todos los campos obligatorios.";
        $tipo_mensaje = "error";
    }
}

// Obtener lista de insumos
$resultado_insumos = $conexion->query("SELECT * FROM insumos ORDER BY id DESC");
$insumos = [];
if ($resultado_insumos) {
    while ($row = $resultado_insumos->fetch_assoc()) {
        $row['nombre_real'] = $row['nombre'] ?? $row['producto'] ?? $row['nombre_producto'] ?? 'Sin nombre';
        $insumos[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modificar Insumos - Atmósfera Dulce</title>
    
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
            position: relative;
        }

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

        /* Contenedor del buscador unificado con lista desplegable flotante */
        .search-select-container {
            position: relative;
            width: 100%;
        }

        .dropdown-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            max-height: 180px;
            overflow-y: auto;
            display: none;
            z-index: 99;
            margin-top: 4px;
            text-align: left;
        }

        .dropdown-item {
            padding: 10px 16px;
            font-size: 14px;
            color: #333;
            cursor: pointer;
            border-bottom: 1px solid #f1f1f1;
            transition: background 0.15s ease;
        }

        .dropdown-item:hover {
            background-color: #f0f8ff;
            color: #008cba;
            font-weight: bold;
        }

        /* Botón Guardar Principal */
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
        <!-- Cabecera con Título -->
        <div class="card-header">
            <h2>Modificar insumos</h2>
        </div>

        <!-- Formulario -->
        <form action="modificar.php" method="POST" class="card-body">
            
            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label">Buscar e identificar insumo</label>
                
                <div class="search-select-container">
                    <!-- Campo único de búsqueda y selección -->
                    <input type="text" id="buscadorInsumo" class="form-input" placeholder="🔍 Escribe o selecciona un insumo..." autocomplete="off" onfocus="mostrarLista()" onkeyup="filtrarInsumos()" required>
                    
                    <!-- Campo oculto para enviar el ID real al procesar el POST -->
                    <input type="hidden" name="producto_id" id="producto_id" required>

                    <!-- Lista desplegable flotante automatizada -->
                    <div id="dropdownList" class="dropdown-list">
                        <?php foreach ($insumos as $p): ?>
                            <div class="dropdown-item" 
                                 onclick="seleccionarInsumo('<?php echo $p['id']; ?>', '<?php echo htmlspecialchars($p['nombre_real'], ENT_QUOTES); ?>', '<?php echo $p['cantidad']; ?>', '<?php echo $p['fecha_entrada']; ?>', '<?php echo htmlspecialchars($p['caducidad'], ENT_QUOTES); ?>')"
                                 data-nombre="<?php echo strtolower(htmlspecialchars($p['nombre_real'])); ?>">
                                <?php echo htmlspecialchars($p['nombre_real']); ?> (ID: <?php echo $p['id']; ?>)
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Cantidad</label>
                <input type="number" id="cantidad" name="cantidad" class="form-input" min="0" placeholder="Ej. 10" required>
            </div>

            <div class="form-group">
                <label class="form-label">Fecha de Ingreso</label>
                <input type="date" id="fecha_entrada" name="fecha_entrada" class="form-input" required>
            </div>

            <div class="form-group">
                <div class="label-container">
                    <label class="form-label" id="label_caducidad">Caducidad</label>
                    <!-- Casilla en miniatura al lado de la etiqueta de caducidad -->
                    <label class="checkbox-container" title="Marcar si es un objeto no comestible">
                        <input type="checkbox" id="es_objeto" name="es_objeto" onchange="toggleCaducidad()"> Objeto
                    </label>
                </div>
                <input type="date" id="caducidad" name="caducidad" class="form-input" min="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <button type="submit" class="btn-submit">Guardar</button>
        </form>
    </div>

    <script>
        // Mostrar la lista flotante al hacer clic en el input
        function mostrarLista() {
            document.getElementById('dropdownList').style.display = 'block';
        }

        // Filtrar elementos de la lista en tiempo real según lo que escribe el usuario
        function filtrarInsumos() {
            const filtro = document.getElementById('buscadorInsumo').value.toLowerCase();
            const items = document.querySelectorAll('.dropdown-item');
            document.getElementById('dropdownList').style.display = 'block';

            items.forEach(item => {
                const nombre = item.getAttribute('data-nombre');
                if (nombre.includes(filtro)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        // Seleccionar un insumo de la lista flotante, rellenar los datos y verificar si es objeto
        function seleccionarInsumo(id, nombre, cantidad, entrada, caducidad) {
            document.getElementById('buscadorInsumo').value = nombre + ' (ID: ' + id + ')';
            document.getElementById('producto_id').value = id;
            document.getElementById('cantidad').value = cantidad;
            document.getElementById('fecha_entrada').value = entrada;
            
            const checkboxObjeto = document.getElementById('es_objeto');
            const inputCaducidad = document.getElementById('caducidad');

            if (caducidad === 'No aplica') {
                checkboxObjeto.checked = true;
                inputCaducidad.value = '';
            } else {
                checkboxObjeto.checked = false;
                inputCaducidad.value = caducidad;
            }

            toggleCaducidad();
            document.getElementById('dropdownList').style.display = 'none';
        }

        // Función para bloquear/desbloquear y limpiar el campo de caducidad si el checkbox está marcado
        function toggleCaducidad() {
            const esObjeto = document.getElementById('es_objeto').checked;
            const inputCaducidad = document.getElementById('caducidad');
            const labelCaducidad = document.getElementById('label_caducidad');

            if (esObjeto) {
                inputCaducidad.value = '';
                inputCaducidad.disabled = true;
                inputCaducidad.required = false;
                labelCaducidad.style.opacity = '0.5';
            } else {
                inputCaducidad.disabled = false;
                inputCaducidad.required = true;
                labelCaducidad.style.opacity = '1';
            }
        }

        // Ocultar la lista si se hace clic fuera del buscador
        document.addEventListener('click', function(e) {
            const contenedor = document.querySelector('.search-select-container');
            if (!contenedor.contains(e.target)) {
                document.getElementById('dropdownList').style.display = 'none';
            }
        });
    </script>

</body>
</html>