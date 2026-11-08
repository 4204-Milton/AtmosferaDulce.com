<?php
session_start();

if (!isset($_SESSION['usuario_nombre'])) {
    header("Location: login.php?error=1");
    exit();
}

require_once 'conexion.php';

// Detectar el puesto o rol del usuario actual
$puesto_actual = strtolower(trim($_SESSION['usuario_puesto'] ?? $_SESSION['usuario_rol'] ?? ''));

// Definir permisos:
// - Administrador y Almacenista pueden eliminar insumos o gestionarlos
$es_admin_o_almacenista = (
    strpos($puesto_actual, 'almacen') !== false || 
    strpos($puesto_actual, 'admin') !== false
);

// Proceso para eliminar un insumo (Solo Admin o Almacenista)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_id'])) {
    if (!$es_admin_o_almacenista) {
        header("Location: inventario.php?error=sin_permiso");
        exit();
    }

    $id_eliminar = intval($_POST['eliminar_id']);
    if ($id_eliminar > 0) {
        $stmt_del = $conexion->prepare("DELETE FROM insumos WHERE id = ?");
        $stmt_del->bind_param("i", $id_eliminar);
        $stmt_del->execute();
        $stmt_del->close();
    }
    
    header("Location: inventario.php");
    exit();
}

$query = "SELECT * FROM insumos ORDER BY id DESC";
$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insumos - Atmósfera Dulce</title>
    
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
            padding: 40px 20px;
            position: relative;
        }

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

        .table-container {
            max-width: 1000px;
            margin: 40px auto 0 auto;
            background-color: rgba(255, 255, 255, 0.96);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 6px 20px rgba(0,0,0,0.25);
            padding-bottom: 25px;
        }

        .card-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #ddd;
        }

        .card-title {
            font-size: 26px;
            font-weight: bold;
            color: #333;
        }

        .search-box {
            position: relative;
            width: 260px;
        }

        .search-input {
            width: 100%;
            padding: 8px 35px 8px 15px;
            border-radius: 20px;
            border: 1px solid #ccc;
            outline: none;
            font-size: 14px;
            background-color: #fff;
        }

        .search-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #888;
            font-size: 14px;
            pointer-events: none;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
        }

        th, td {
            padding: 15px;
            border-bottom: 1px solid #ddd;
            font-size: 16px;
        }

        th {
            background-color: #f2f2f2;
            color: #000;
            font-weight: bold;
        }

        .stock-verde { color: #28a745; font-weight: bold; }
        .stock-amarillo { color: #d39e00; font-weight: bold; }
        .stock-rojo { color: #dc3545; font-weight: bold; }

        .btn-delete-x {
            background-color: #dc3545;
            color: #ffffff;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s ease;
        }

        .btn-delete-x:hover { background-color: #bd2130; }

        .footer-actions {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 25px;
            padding: 0 20px;
        }

        .btn-action {
            padding: 10px 30px;
            font-size: 16px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid rgba(0,0,0,0.1);
        }

        .btn-add { background-color: #28a745; color: #ffffff; }
        .btn-add:hover { background-color: #218838; }

        .btn-modify { background-color: #ffc107; color: #000000; }
        .btn-modify:hover { background-color: #e0a800; }

        .no-data { padding: 30px; font-size: 18px; color: #666; }

        .alert-aviso {
            background-color: #fff3cd;
            color: #856404;
            padding: 10px;
            text-align: center;
            font-size: 14px;
            border-bottom: 1px solid #ffeeba;
        }
    </style>
</head>
<body>

    <button class="btn-back" onclick="location.href='main.php'" title="Volver al Menú">
        <img src="BTN Izquierda.png" alt="Volver" class="img-btn-back">
    </button>

    <div class="logo-container">
        <img src="Atmosfera Logo.png" alt="Logo" class="img-logo">
    </div>

    <div class="table-container">
        <div class="card-header-bar">
            <div class="card-title">Inventario de Insumos</div>
            <div class="search-box">
                <input type="text" id="busquedaRapida" class="search-input" placeholder="Buscar insumo..." onkeyup="filtrarTabla()">
                <span class="search-icon">🔍</span>
            </div>
        </div>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'sin_permiso'): ?>
            <div class="alert-aviso">No tienes permisos para realizar esta acción.</div>
        <?php endif; ?>

        <table id="tablaInsumos">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Insumo</th>
                    <th>Cantidad Disponible</th>
                    <th>Fecha Ingreso</th>
                    <th>Caducidad</th>
                    <?php if ($es_admin_o_almacenista): ?>
                        <th>Acción</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($resultado && $resultado->num_rows > 0): ?>
                    <?php while ($fila = $resultado->fetch_assoc()): 
                        $nombre_prod = $fila['nombre'] ?? $fila['producto'] ?? 'Sin nombre';
                        $cantidad = intval($fila['cantidad']);
                        
                        if ($cantidad == 0) {
                            $clase_stock = "stock-rojo";
                            $texto_stock = "Agotado (0)";
                        } elseif ($cantidad <= 3) {
                            $clase_stock = "stock-amarillo";
                            $texto_stock = "$cantidad (Poco stock)";
                        } else {
                            $clase_stock = "stock-verde";
                            $texto_stock = $cantidad;
                        }

                        $caducidad = (!empty($fila['caducidad']) && $fila['caducidad'] !== '0000-00-00') 
                                    ? htmlspecialchars($fila['caducidad']) 
                                    : 'Sin registrar';
                    ?>
                        <tr class="fila-producto">
                            <td><?php echo htmlspecialchars($fila['id']); ?></td>
                            <td class="nombre-producto"><?php echo htmlspecialchars($nombre_prod); ?></td>
                            <td class="<?php echo $clase_stock; ?>"><?php echo $texto_stock; ?></td>
                            <td><?php echo htmlspecialchars($fila['fecha_entrada']); ?></td>
                            <td><?php echo $caducidad; ?></td>
                            <?php if ($es_admin_o_almacenista): ?>
                                <td>
                                    <form method="POST" action="inventario.php" onsubmit="return confirm('¿Estás seguro de eliminar este insumo?');" style="display:inline;">
                                        <input type="hidden" name="eliminar_id" value="<?php echo $fila['id']; ?>">
                                        <button type="submit" class="btn-delete-x" title="Eliminar insumo">✕</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo $es_admin_o_almacenista ? '6' : '5'; ?>" class="no-data">No hay insumos registrados actualmente.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Acciones exclusivas para Administrador o Almacenista -->
        <?php if ($es_admin_o_almacenista): ?>
            <div class="footer-actions">
                <a href="añadir.php" class="btn-action btn-add">+ Añadir Insumo</a>
                <a href="modificar.php" class="btn-action btn-modify">Modificar</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function filtrarTabla() {
            let input = document.getElementById('busquedaRapida');
            let filtro = input.value.toLowerCase();
            let filas = document.querySelectorAll('.fila-producto');

            filas.forEach(fila => {
                let textoProducto = fila.querySelector('.nombre-producto').textContent.toLowerCase();
                if (textoProducto.includes(filtro)) {
                    fila.style.display = "";
                } else {
                    fila.style.display = "none";
                }
            });
        }
    </script>

</body>
</html>