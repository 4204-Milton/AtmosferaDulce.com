<?php
session_start();

if (!isset($_SESSION['usuario_nombre'])) {
    header("Location: login.php?error=1");
    exit();
}

require_once 'conexion.php';

// Manejar la acción de eliminar un ticket individual y reordenar los IDs
if (isset($_GET['accion']) && $_GET['accion'] === 'eliminar' && isset($_GET['id'])) {
    $id_a_eliminar = intval($_GET['id']);

    $stmt_del = $conexion->prepare("DELETE FROM ventas WHERE id = ?");
    $stmt_del->bind_param("i", $id_a_eliminar);
    $stmt_del->execute();
    $stmt_del->close();

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
    $conexion->query("ALTER TABLE ventas AUTO_INCREMENT = $nuevo_id");

    header("Location: ventas.php?tab=" . (isset($_GET['tab']) ? $_GET['tab'] : 'ventas'));
    exit();
}

// Manejar la acción de Corte de Caja (Limpiar todas las ventas)
if (isset($_GET['accion']) && $_GET['accion'] === 'cortecaja') {
    $conexion->query("TRUNCATE TABLE ventas");
    header("Location: ventas.php?tab=estadisticas");
    exit();
}

// Obtener la pestaña activa (por defecto 'ventas')
$tab_activa = isset($_GET['tab']) ? $_GET['tab'] : 'ventas';

// Consultar todas las ventas registradas
$resultado_ventas = $conexion->query("SELECT * FROM ventas ORDER BY id ASC");

// Calcular estadísticas globales para la pestaña de reportes
$query_stats = $conexion->query("SELECT COUNT(*) as total_tickets, SUM(total) as ingresos_totales, SUM(cantidad) as productos_totales FROM ventas");
$stats = $query_stats->fetch_assoc();
$total_tickets = intval($stats['total_tickets'] ?? 0);
$ingresos_totales = floatval($stats['ingresos_totales'] ?? 0);
$productos_totales = intval($stats['productos_totales'] ?? 0);
$promedio_ticket = ($total_tickets > 0) ? ($ingresos_totales / $total_tickets) : 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Ventas - Atmósfera Dulce</title>
    <!-- Librería HTML2PDF para descarga de PDF directa -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #bdc8cd; min-height: 100vh; padding: 40px 20px; position: relative; }
        .btn-back { position: absolute; top: 30px; left: 30px; background: none; border: none; cursor: pointer; }
        .img-btn-back { width: 55px; height: 55px; object-fit: contain; transition: transform 0.2s ease; }
        .img-btn-back:hover { transform: scale(1.08); }
        .logo-container { position: absolute; top: 40px; right: 40px; }
        .img-logo { width: 80px; height: 80px; object-fit: contain; }
        
        .sales-container { max-width: 1100px; margin: 50px auto 0 auto; background-color: #ffffff; border-radius: 20px; box-shadow: 0 12px 30px rgba(0,0,0,0.15); padding: 40px; }
        .sales-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .sales-title { font-size: 32px; font-weight: bold; color: #222222; }

        /* --- SISTEMA DE PESTAÑAS --- */
        .admin-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #eaeaea;
            padding-bottom: 10px;
        }

        .tab-btn {
            background-color: #e2e8f0;
            color: #4a5568;
            border: none;
            padding: 10px 25px;
            font-size: 16px;
            font-weight: bold;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .tab-btn:hover { background-color: #cbd5e0; }
        .tab-btn.active { background-color: #008cba; color: #ffffff; }

        /* Tablas */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 14px 15px; text-align: left; border-bottom: 1px solid #eaeaea; font-size: 15px; }
        th { background-color: #f8f9fa; color: #444; font-weight: 600; }
        tr:hover { background-color: #fdfdfd; }
        
        .badge-id { background-color: #e3f2fd; color: #1976d2; padding: 6px 10px; border-radius: 8px; font-weight: bold; font-size: 14px; }
        .total-amount { color: #28a745; font-weight: bold; }
        .empty-sales { text-align: center; padding: 40px; color: #777; font-size: 18px; }

        /* Tarjetas de Estadísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: #f8f9fa;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
        }

        .stat-card h3 {
            font-size: 15px;
            color: #718096;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #2d3748;
        }

        .stat-card.highlight .stat-value {
            color: #28a745;
        }

        /* Contenedor de Botones Inferiores en Estadísticas */
        .stats-actions-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 25px;
            border-top: 1px solid #eaeaea;
            padding-top: 20px;
        }

        .btn-pdf-stats {
            background-color: #008cba;
            color: #ffffff;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: bold;
            border-radius: 25px;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            transition: background 0.2s, transform 0.1s;
        }
        .btn-pdf-stats:hover { background-color: #007399; transform: translateY(-2px); }

        .btn-corte-caja {
            background-color: #e53e3e;
            color: #ffffff;
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: bold;
            border-radius: 25px;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            transition: background 0.2s, transform 0.1s;
        }
        .btn-corte-caja:hover { background-color: #c53030; transform: translateY(-2px); }

        /* Iconos de acción */
        .action-buttons { display: flex; gap: 12px; align-items: center; }
        .action-btn { background: none; border: none; cursor: pointer; padding: 4px; display: inline-flex; align-items: center; justify-content: center; transition: transform 0.2s ease; text-decoration: none; }
        .action-btn:hover { transform: scale(1.15); }
        .action-icon { width: 26px; height: 26px; object-fit: contain; }
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
            <div class="sales-title">Gestión de Ventas</div>
        </div>

        <!-- Pestañas de Navegación -->
        <div class="admin-tabs">
            <a href="ventas.php?tab=ventas" class="tab-btn <?php echo ($tab_activa === 'ventas') ? 'active' : ''; ?>">Ventas Diarias</a>
            <a href="ventas.php?tab=estadisticas" class="tab-btn <?php echo ($tab_activa === 'estadisticas') ? 'active' : ''; ?>">Reporte Estadístico</a>
        </div>

        <?php if ($tab_activa === 'ventas'): ?>
            <!-- PESTAÑA 1: TABLA DE VENTAS DIARIAS -->
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
                                        <a href="ventas.php?accion=eliminar&id=<?php echo $venta['id']; ?>&tab=ventas" class="action-btn" title="Eliminar Ticket" onclick="return confirm('¿Estás seguro de eliminar este ticket? Los IDs se reordenarán automáticamente.');">
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

        <?php else: ?>
            <!-- PESTAÑA 2: REPORTE ESTADÍSTICO DE VENTAS TOTALES -->
            <div style="padding: 10px 0;" id="areaEstadisticas">
                <h3 style="font-size: 20px; color: #333; margin-bottom: 5px;">Resumen Estadístico General</h3>
                <p style="color: #666; font-size: 14px; margin-bottom: 20px;">Métricas consolidadas de todas las transacciones registradas en el sistema.</p>

                <div class="stats-grid">
                    <div class="stat-card highlight">
                        <h3>Ingresos Totales</h3>
                        <div class="stat-value">$<?php echo number_format($ingresos_totales, 2); ?></div>
                    </div>

                    <div class="stat-card">
                        <h3>Tickets Emitidos</h3>
                        <div class="stat-value"><?php echo number_format($total_tickets); ?></div>
                    </div>

                    <div class="stat-card">
                        <h3>Productos Vendidos</h3>
                        <div class="stat-value"><?php echo number_format($productos_totales); ?></div>
                    </div>

                    <div class="stat-card">
                        <h3>Venta Promedio / Ticket</h3>
                        <div class="stat-value">$<?php echo number_format($promedio_ticket, 2); ?></div>
                    </div>
                </div>
            </div>

            <!-- Botones Inferiores (PDF y Corte de Caja) -->
            <div class="stats-actions-footer">
                <button type="button" class="btn-pdf-stats" onclick="descargarEstadisticasPDF()">Descargar PDF</button>
                <a href="ventas.php?accion=cortecaja" class="btn-corte-caja" onclick="return confirm('¡ATENCIÓN! Estás a punto de realizar el corte de caja. Esto eliminará TODOS los tickets y datos de ventas registrados permanentemente. ¿Deseas continuar?');" style="text-decoration: none;">Corte de caja</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Plantilla Oculta para la Hoja Membretada del PDF Estadístico -->
    <div id="printStatsSheet" style="display: none;">
        <div style="position: relative; width: 210mm; min-height: 297mm; padding: 20mm; background: #ffffff; font-family: 'Georgia', serif; box-sizing: border-box;">
            <!-- Marca de agua -->
            <img src="Azul.png" alt="Marca de agua" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 380px; opacity: 0.12; pointer-events: none;">

            <div style="position: relative; z-index: 1; display: flex; flex-direction: column; min-height: 250mm;">
                <!-- Encabezado con Logo -->
                <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px dotted #333; padding-bottom: 15px; margin-bottom: 25px;">
                    <div>
                        <img src="Logo color.png" alt="Atmósfera Dulce" style="width: 180px; object-fit: contain;">
                    </div>
                    <div style="text-align: right; font-size: 14px; color: #333;">
                        <strong>Fecha de Emisión:</strong> <?php echo date('d/m/Y H:i'); ?>
                    </div>
                </div>

                <!-- Cuerpo del Reporte -->
                <div style="flex: 1;">
                    <h3 style="margin-bottom: 15px; color: #222; font-size: 22px; border-bottom: 2px solid #e5737d; padding-bottom: 8px;">Reporte Estadístico de Ventas Totales</h3>
                    
                    <p style="font-size: 15px; color: #444; margin-bottom: 25px; line-height: 1.5;">
                        A continuación se presentan las métricas globales acumuladas correspondientes al historial de transacciones registradas en el sistema comercial de <strong>Atmósfera Dulce</strong>.
                    </p>

                    <table style="width: 100%; border-collapse: collapse; margin-top: 20px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
                        <thead>
                            <tr style="background-color: #f2f2f2;">
                                <th style="border: 1px solid #ccc; padding: 12px; text-align: left;">Métrica General</th>
                                <th style="border: 1px solid #ccc; padding: 12px; text-align: right;">Resultado Consolidado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="border: 1px solid #ccc; padding: 12px;"><strong>Ingresos Totales Generados</strong></td>
                                <td style="border: 1px solid #ccc; padding: 12px; text-align: right; color: #28a745; font-weight: bold;">$<?php echo number_format($ingresos_totales, 2); ?></td>
                            </tr>
                            <tr>
                                <td style="border: 1px solid #ccc; padding: 12px;"><strong>Total de Tickets Emitidos</strong></td>
                                <td style="border: 1px solid #ccc; padding: 12px; text-align: right;"><?php echo number_format($total_tickets); ?></td>
                            </tr>
                            <tr>
                                <td style="border: 1px solid #ccc; padding: 12px;"><strong>Total de Productos Vendidos</strong></td>
                                <td style="border: 1px solid #ccc; padding: 12px; text-align: right;"><?php echo number_format($productos_totales); ?></td>
                            </tr>
                            <tr>
                                <td style="border: 1px solid #ccc; padding: 12px;"><strong>Venta Promedio por Ticket</strong></td>
                                <td style="border: 1px solid #ccc; padding: 12px; text-align: right;">$<?php echo number_format($promedio_ticket, 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pie de página con franja rosa -->
                <div style="position: absolute; bottom: 0; left: -20mm; right: -20mm; width: 210mm; height: 25px;">
                    <img src="Rosa.png" alt="Pie de página" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
            </div>
        </div>
    </div>

    <script>
        function descargarEstadisticasPDF() {
            let elemento = document.getElementById('printStatsSheet').firstElementChild.cloneNode(true);
            elemento.style.display = 'block';

            let opciones = {
                margin:       0,
                filename:     'Reporte_Estadistico_Ventas_<?php echo date("Ymd_His"); ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, logging: false, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            html2pdf().set(opciones).from(elemento).save();
        }
    </script>
</body>
</html>
