<?php
session_start();

if (!isset($_SESSION['usuario_nombre'])) {
    header("Location: login.php?error=1");
    exit();
}

require_once 'conexion.php';

// Manejar la acción de eliminar un ticket y reordenar los IDs
if (isset($_GET['accion']) && $_GET['accion'] === 'eliminar' && isset($_GET['id'])) {
    $id_a_eliminar = intval($_GET['id']);

    // 1. Eliminar el registro seleccionado
    $stmt_del = $conexion->prepare("DELETE FROM ventas WHERE id = ?");
    $stmt_del->bind_param("i", $id_a_eliminar);
    $stmt_del->execute();
    $stmt_del->close();

    // 2. Reordenar los IDs restantes de forma secuencial (1, 2, 3...)
    $resultado_actuales = $conexion->query("SELECT id FROM ventas ORDER BY id ASC");
    $nuevo_id = 1;
    if ($resultado_actuales) {
        while ($row = $resultado_actuales->fetch_assoc()) {
            $id_antiguo = $row['id'];
            if ($id_antiguo != $nuevo_id) {
                $conexion->query("UPDATE ventas SET id = $nuevo_id WHERE id = $id_antiguo");
            }
            $nuevo_id++;
        }
    }

    // 3. Reiniciar el AUTO_INCREMENT al siguiente número correcto (si está vacío, vuelve a 1)
    $conexion->query("ALTER TABLE ventas AUTO_INCREMENT = $nuevo_id");

    header("Location: ventas.php");
    exit();
}

// Consultar todas las ventas registradas ordenadas cronológicamente para ver el orden secuencial correcto
$resultado_ventas = $conexion->query("SELECT * FROM ventas ORDER BY id ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas Diarias - Atmósfera Dulce</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #bdc8cd; min-height: 100vh; padding: 40px 20px; position: relative; }
        .btn-back { position: absolute; top: 30px; left: 30px; background: none; border: none; cursor: pointer; }
        .img-btn-back { width: 55px; height: 55px; object-fit: contain; transition: transform 0.2s ease; }
        .img-btn-back:hover { transform: scale(1.08); }
        .logo-container { position: absolute; top: 40px; right: 40px; }
        .img-logo { width: 80px; height: 80px; object-fit: contain; }
        
        .sales-container { max-width: 1100px; margin: 50px auto 0 auto; background-color: #ffffff; border-radius: 20px; box-shadow: 0 12px 30px rgba(0,0,0,0.15); padding: 40px; }
        .sales-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .sales-title { font-size: 32px; font-weight: bold; color: #222222; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 14px 15px; text-align: left; border-bottom: 1px solid #eaeaea; font-size: 15px; }
        th { background-color: #f8f9fa; color: #444; font-weight: 600; }
        tr:hover { background-color: #fdfdfd; }
        
        .badge-id { background-color: #e3f2fd; color: #1976d2; padding: 6px 10px; border-radius: 8px; font-weight: bold; font-size: 14px; }
        .total-amount { color: #28a745; font-weight: bold; }
        .empty-sales { text-align: center; padding: 40px; color: #777; font-size: 18px; }

        /* Estilos para los iconos de acción */
        .action-buttons {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s ease;
            text-decoration: none;
        }
        .action-btn:hover {
            transform: scale(1.15);
        }
        .action-icon {
            width: 26px;
            height: 26px;
            object-fit: contain;
        }
    </style>
</head>
<body>

    <button class="btn-back" onclick="location.href='main.php'" title="Volver a Menu">
        <img src="BTN Izquierda.png" alt="Volver" class="img-btn-back">
    </button>

    <div class="logo-container">
        <img src="Atmosfera Logo.png" alt="Logo" class="img-logo">
    </div>

    <div class="sales-container">
        <div class="sales-header">
            <div class="sales-title">Ventas Diarias</div>
        </div>

        <?php if ($resultado_ventas && $resultado_ventas->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Ticket ID</th>
                        <th>Fecha y Hora</th>
                        <th>Productos</th>
                        <th>Cant. Total</th>
                        <th>Subtotal</th>
                        <th>Total</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($venta = $resultado_ventas->fetch_assoc()): ?>
                        <tr>
                            <td><span class="badge-id">#<?php echo $venta['id']; ?></span></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($venta['fecha'])); ?></td>
                            <td><?php echo htmlspecialchars($venta['productos']); ?></td>
                            <td style="text-align: center;"><?php echo $venta['cantidad']; ?></td>
                            <td>$<?php echo number_format($venta['subtotal'], 2); ?></td>
                            <td class="total-amount">$<?php echo number_format($venta['total'], 2); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <!-- Botón Descargar Ticket -->
                                    <a href="generar_ticket.php?id=<?php echo $venta['id']; ?>" class="action-btn" title="Descargar Ticket">
                                        <img src="Download.png" alt="Descargar" class="action-icon">
                                    </a>
                                    <!-- Botón Eliminar Ticket -->
                                    <a href="ventas.php?accion=eliminar&id=<?php echo $venta['id']; ?>" class="action-btn" title="Eliminar Ticket" onclick="return confirm('¿Estás seguro de eliminar este ticket? Los IDs se reordenarán automáticamente.');">
                                        <img src="X.png" alt="Eliminar" class="action-icon">
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-sales">
                Aún no hay registros de ventas o tickets guardados en el sistema.
            </div>
        <?php endif; ?>
    </div>

</body>
</html>