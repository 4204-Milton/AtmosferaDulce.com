<?php
session_start();
if (!isset($_SESSION['usuario_nombre'])) {
    header("Location: login.php?error=1");
    exit();
}

require_once 'conexion.php';

// Eliminar reporte si se solicita
if (isset($_GET['eliminar'])) {
    $id_eliminar = intval($_GET['eliminar']);
    $conexion->query("DELETE FROM reportes WHERE id = $id_eliminar");
    header("Location: reportes_admin.php");
    exit();
}

// Obtener la pestaña activa (por defecto 'reportes')
$tab_activa = isset($_GET['tab']) ? $_GET['tab'] : 'reportes';

// Obtener la lista de reportes uniendo con la tabla registro para asegurar el puesto real actualizado
$resultado_reportes = $conexion->query("SELECT r.*, COALESCE(reg.rol, r.puesto, 'Cajero') as puesto_real FROM reportes r LEFT JOIN registro reg ON r.nombre = reg.nombre ORDER BY r.id DESC");

// Obtener la lista de solicitudes / movimientos de insumos registrados
$resultado_solicitudes = $conexion->query("SELECT * FROM movimientos_inventario ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Atmósfera Dulce</title>
    <!-- Librería HTML2PDF para descarga de PDF directa -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Georgia', serif;
        }

        body {
            background-color: #e5737d; /* Fondo rosa exterior institucional */
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        /* Ventana principal del panel administrador */
        .admin-window {
            width: 100%;
            max-width: 900px;
            background-color: #f7f9fa; /* Fondo interior claro */
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            overflow: hidden;
            position: relative;
            padding: 25px;
        }

        /* Cabecera */
        .admin-header {
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            margin-bottom: 20px;
        }

        .admin-title {
            font-size: 32px;
            font-weight: bold;
            color: #000000;
            letter-spacing: 1px;
        }

        .btn-close {
            position: absolute;
            right: 0;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
        }

        .img-close {
            width: 35px;
            height: 35px;
            object-fit: contain;
            transition: transform 0.2s ease;
        }

        .img-close:hover {
            transform: scale(1.1);
        }

        /* --- SISTEMA DE PESTAÑAS --- */
        .admin-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
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

        .tab-btn:hover {
            background-color: #cbd5e0;
        }

        .tab-btn.active {
            background-color: #008cba;
            color: #ffffff;
        }

        /* Contenedor de listas con scroll */
        .reports-list {
            max-height: 380px;
            overflow-y: auto;
            margin-bottom: 25px;
            padding-right: 5px;
        }

        /* Fila de cada reporte */
        .report-row {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border: 1px solid #eaeaea;
            cursor: pointer;
            transition: all 0.2s;
        }

        .report-row:hover {
            border-color: #b0c4de;
        }

        .report-row.selected {
            border: 2px solid #008cba;
            background-color: #f0f8ff;
        }

        .report-info {
            display: flex;
            gap: 20px;
            font-size: 16px;
            font-weight: bold;
            color: #000000;
            flex: 1;
        }

        .report-info span {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .report-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .action-icon {
            width: 30px;
            height: 30px;
            object-fit: contain;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .action-icon:hover {
            transform: scale(1.15);
        }

        /* --- TABLA DE SOLICITUDES --- */
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .admin-table th, .admin-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eaeaea;
        }

        .admin-table th {
            background-color: #edf2f7;
            font-weight: bold;
            color: #2d3748;
        }

        .badge-solicitud {
            color: #2b6cb0;
            background-color: #ebf8ff;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 12px;
        }

        /* Botón inferior Previsualizar */
        .footer-action {
            text-align: center;
        }

        .btn-preview {
            background-color: #008cba;
            color: #ffffff;
            border: none;
            padding: 10px 40px;
            font-size: 20px;
            font-weight: bold;
            border-radius: 25px;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            transition: background-color 0.2s;
        }

        .btn-preview:hover {
            background-color: #007399;
        }

        .empty-msg {
            text-align: center;
            color: #666;
            font-size: 18px;
            padding: 30px;
        }
    </style>
</head>
<body>

    <div class="admin-window">
        <!-- Cabecera -->
        <div class="admin-header">
            <h1 class="admin-title">Panel de Administración</h1>
            <button class="btn-close" onclick="location.href='main.php'" title="Volver al Menú Principal">
                <img src="X.png" alt="Cerrar" class="img-close">
            </button>
        </div>

        <!-- Pestañas de Navegación -->
        <div class="admin-tabs">
            <a href="reportes_admin.php?tab=reportes" class="tab-btn <?php echo ($tab_activa === 'reportes') ? 'active' : ''; ?>">Reportes de Empleados</a>
            <a href="reportes_admin.php?tab=solicitudes" class="tab-btn <?php echo ($tab_activa === 'solicitudes') ? 'active' : ''; ?>">Solicitudes de Insumos</a>
        </div>

        <?php if ($tab_activa === 'reportes'): ?>
            <!-- VISTA 1: LISTA DE REPORTES -->
            <div class="reports-list">
                <?php if ($resultado_reportes && $resultado_reportes->num_rows > 0): ?>
                    <?php while ($row = $resultado_reportes->fetch_assoc()): 
                        $contenido_seguro = base64_encode($row['contenido']);
                        $nombre_emitido = htmlspecialchars($row['nombre'], ENT_QUOTES);
                        $fecha_emitida = htmlspecialchars($row['fecha'], ENT_QUOTES);
                        $puesto_emitido = htmlspecialchars($row['puesto_real'], ENT_QUOTES);
                    ?>
                        <div class="report-row" onclick="seleccionarReporte(this, '<?php echo $nombre_emitido; ?>', '<?php echo $fecha_emitida; ?>', '<?php echo $puesto_emitido; ?>', '<?php echo $contenido_seguro; ?>')">
                            <div class="report-info">
                                <span><?php echo $nombre_emitido; ?></span>
                                <span><?php echo $fecha_emitida; ?></span>
                                <span><?php echo $puesto_emitido; ?></span>
                            </div>
                            <div class="report-actions" onclick="event.stopPropagation();">
                                <!-- Botón de descarga directa a PDF con Download.png -->
                                <a href="#" onclick="descargarPDFAdmin('<?php echo $nombre_emitido; ?>', '<?php echo $fecha_emitida; ?>', '<?php echo $puesto_emitido; ?>', '<?php echo $contenido_seguro; ?>')">
                                    <img src="Download.png" alt="Descargar PDF" class="action-icon" title="Descargar PDF">
                                </a>
                                <!-- Botón de eliminar con X.png -->
                                <a href="reportes_admin.php?eliminar=<?php echo $row['id']; ?>" onclick="return confirm('¿Estás seguro de eliminar este reporte?');">
                                    <img src="X.png" alt="Eliminar" class="action-icon" title="Eliminar Reporte" style="filter: invert(27%) sepia(51%) saturate(2878%) hue-rotate(346deg) brightness(104%) contrast(97%);">
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-msg">No hay reportes registrados por los empleados.</div>
                <?php endif; ?>
            </div>

            <!-- Botón Inferior Previsualizar -->
            <div class="footer-action">
                <button type="button" class="btn-preview" onclick="previsualizarSeleccionado()">Previsualizar</button>
            </div>

        <?php else: ?>
            <!-- VISTA 2: SOLICITUDES DE INSUMOS -->
            <div class="reports-list">
                <?php if ($resultado_solicitudes && $resultado_solicitudes->num_rows > 0): ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Fecha y Hora</th>
                                <th>Usuario</th>
                                <th>Puesto</th>
                                <th>Insumo Solicitado</th>
                                <th>Cantidad</th>
                                <th>Tipo de Movimiento</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($sol = $resultado_solicitudes->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($sol['fecha']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($sol['usuario']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($sol['puesto']); ?></td>
                                    <td><?php echo htmlspecialchars($sol['insumo']); ?></td>
                                    <td><span style="font-weight: bold; color: #e53e3e;"><?php echo htmlspecialchars($sol['cantidad']); ?></span></td>
                                    <td><span class="badge-solicitud"><?php echo htmlspecialchars($sol['tipo_movimiento']); ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-msg">No hay solicitudes de insumos registradas todavía.</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Plantilla Oculta para Renderizar Hoja Membretada en PDF -->
    <div id="printSheet" style="display: none;">
        <div style="position: relative; width: 210mm; min-height: 297mm; padding: 20mm; background: #ffffff; font-family: 'Georgia', serif; box-sizing: border-box;">
            <!-- Marca de agua (Azul.png) -->
            <img src="Azul.png" alt="Marca de agua" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 380px; opacity: 0.12; pointer-events: none;">

            <div style="position: relative; z-index: 1; display: flex; flex-direction: column; min-height: 250mm;">
                <!-- Encabezado con Logo (Logo color.png) -->
                <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px dotted #333; padding-bottom: 15px; margin-bottom: 25px;">
                    <div>
                        <img src="Logo color.png" alt="Atmósfera Dulce" style="width: 180px; object-fit: contain;">
                    </div>
                    <div style="text-align: right; font-size: 14px; color: #333;">
                        <strong>Fecha:</strong> <span id="pFecha"></span>
                    </div>
                </div>

                <!-- Cuerpo -->
                <div style="flex: 1;">
                    <div style="margin-bottom: 15px; font-size: 15px; color: #222;">
                        <strong>Emitido por:</strong> <span id="pNombre"></span> (<span id="pPuesto"></span>)
                    </div>
                    <h3 style="margin-bottom: 10px; color: #444; border-bottom: 1px solid #ccc; padding-bottom: 5px;">Reporte de Operaciones</h3>
                    <div id="pContenido" style="font-size: 15px; line-height: 1.6; color: #111; white-space: pre-wrap; margin-top: 10px;"></div>
                </div>

                <!-- Pie de página con franja rosa (Rosa.png) -->
                <div style="position: absolute; bottom: 0; left: -20mm; right: -20mm; width: 210mm; height: 25px;">
                    <img src="Rosa.png" alt="Pie de página" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
            </div>
        </div>
    </div>

    <script>
        let reporteSeleccionado = null;

        function seleccionarReporte(elemento, nombre, fecha, puesto, contenidoBase64) {
            document.querySelectorAll('.report-row').forEach(row => row.classList.remove('selected'));
            elemento.classList.add('selected');
            
            let contenido = atob(contenidoBase64);
            reporteSeleccionado = { nombre, fecha, puesto, contenido };
        }

        // Descarga directa a PDF con el icono Download.png
        function descargarPDFAdmin(nombre, fecha, puesto, contenidoBase64) {
            let contenido = atob(contenidoBase64);

            document.getElementById('pNombre').innerText = nombre;
            document.getElementById('pFecha').innerText = fecha;
            document.getElementById('pPuesto').innerText = puesto;
            document.getElementById('pContenido').innerText = contenido;

            let elemento = document.getElementById('printSheet').firstElementChild.cloneNode(true);
            elemento.style.display = 'block';

            let opciones = {
                margin:       0,
                filename:     'Reporte_' + nombre.replace(/\s+/g, '_') + '_' + fecha.replace(/\//g, '') + '.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, logging: false, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            html2pdf().set(opciones).from(elemento).save();
        }

        // Previsualizar el reporte seleccionado abriendo una nueva pestaña
        function previsualizarSeleccionado() {
            if (!reporteSeleccionado) {
                alert('Por favor, selecciona un reporte de la lista haciendo clic sobre él.');
                return;
            }

            let ventanaPrevia = window.open('', '_blank');
            let contenidoHTML = `
                <!DOCTYPE html>
                <html lang="es">
                <head>
                    <meta charset="UTF-8">
                    <title>Previsualización - Reporte de ${reporteSeleccionado.nombre}</title>
                    <style>
                        body { background: #bdc8cd; display: flex; justify-content: center; padding: 20px; margin: 0; }
                    </style>
                </head>
                <body>
                    <div style="position: relative; width: 210mm; min-height: 297mm; padding: 20mm; background: #ffffff; font-family: 'Georgia', serif; box-sizing: border-box; box-shadow: 0 0 15px rgba(0,0,0,0.3);">
                        <img src="Azul.png" alt="Marca de agua" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 380px; opacity: 0.12; pointer-events: none;">
                        <div style="position: relative; z-index: 1; display: flex; flex-direction: column; min-height: 250mm;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px dotted #333; padding-bottom: 15px; margin-bottom: 25px;">
                                <div>
                                    <img src="Logo color.png" alt="Atmósfera Dulce" style="width: 180px; object-fit: contain;">
                                </div>
                                <div style="text-align: right; font-size: 14px; color: #333;">
                                    <strong>Fecha:</strong> ${reporteSeleccionado.fecha}
                                </div>
                            </div>
                            <div style="flex: 1;">
                                <div style="margin-bottom: 15px; font-size: 15px; color: #222;">
                                    <strong>Emitido por:</strong> ${reporteSeleccionado.nombre} (${reporteSeleccionado.puesto})
                                </div>
                                <h3 style="margin-bottom: 10px; color: #444; border-bottom: 1px solid #ccc; padding-bottom: 5px;">Reporte de Operaciones</h3>
                                <div style="font-size: 15px; line-height: 1.6; color: #111; white-space: pre-wrap; margin-top: 10px;">${reporteSeleccionado.contenido}</div>
                            </div>
                            <div style="position: absolute; bottom: 0; left: -20mm; right: -20mm; width: 210mm; height: 25px;">
                                <img src="Rosa.png" alt="Pie de página" style="width: 100%; height: 100%; object-fit: cover;">
                            </div>
                        </div>
                    </div>
                </body>
                </html>
            `;
            ventanaPrevia.document.write(contenidoHTML);
            ventanaPrevia.document.close();
        }
    </script>
</body>
</html>