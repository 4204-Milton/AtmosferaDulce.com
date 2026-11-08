<?php
session_start();

// Validar que el usuario haya iniciado sesión
if (!isset($_SESSION['usuario_nombre'])) {
    header("Location: login.php?error=1");
    exit();
}

require_once 'conexion.php';

// Configurar zona horaria local
date_default_timezone_set('America/Mexico_City');

$nombre_usuario = isset($_POST['nombre']) ? trim($_POST['nombre']) : $_SESSION['usuario_nombre'];
$fecha_reporte = isset($_POST['fecha']) ? trim($_POST['fecha']) : date('d/m/Y');
$contenido_texto = isset($_POST['contenido']) ? trim($_POST['contenido']) : '';
$insumo_seleccionado_nombre = isset($_POST['insumo_nombre']) ? trim($_POST['insumo_nombre']) : '';
$cantidad_solicitada = isset($_POST['cantidad_solicitada']) ? intval($_POST['cantidad_solicitada']) : 0;

// Tomar directamente el puesto guardado al iniciar sesión
$puesto_usuario = $_SESSION['usuario_puesto'] ?? ($_SESSION['usuario_rol'] ?? 'Cajero'); 

$generando = isset($_POST['generar']);
$mensaje_solicitud_pdf = "";
$movimiento_actual = null; // Guardará únicamente la solicitud del reporte en curso

// Obtener la lista completa de insumos de la BD para el datalist
$res_todos_insumos = $conexion->query("SELECT id, nombre, cantidad FROM insumos ORDER BY nombre ASC");
$insumos_lista = [];
if ($res_todos_insumos && $res_todos_insumos->num_rows > 0) {
    while ($i = $res_todos_insumos->fetch_assoc()) {
        $insumos_lista[] = $i;
    }
}

// Guardar el reporte, registrar la solicitud y descontar de inventario si aplica
if ($generando) {
    if (!empty($contenido_texto)) {
        // 1. Guardar el reporte de texto en la BD
        $stmt = $conexion->prepare("INSERT INTO reportes (nombre, puesto, fecha, contenido) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $nombre_usuario, $puesto_usuario, $fecha_reporte, $contenido_texto);
        $stmt->execute();
        $stmt->close();
    }

    // 2. Si se escribió/seleccionó un insumo y una cantidad válida
    if (!empty($insumo_seleccionado_nombre) && $cantidad_solicitada > 0) {
        // Buscar el insumo en la base de datos por su nombre exacto
        $stmt_ins = $conexion->prepare("SELECT id, nombre, cantidad FROM insumos WHERE nombre = ?");
        $stmt_ins->bind_param("s", $insumo_seleccionado_nombre);
        $stmt_ins->execute();
        $res_ins = $stmt_ins->get_result();

        if ($res_ins && $res_ins->num_rows > 0) {
            $datos_insumo = $res_ins->fetch_assoc();
            $insumo_id = intval($datos_insumo['id']);
            $nombre_insumo = $datos_insumo['nombre'];
            $stock_actual = intval($datos_insumo['cantidad']);

            $cantidad_descontar = min($cantidad_solicitada, $stock_actual);

            if ($cantidad_descontar > 0) {
                // Descontar del inventario
                $nuevo_stock = $stock_actual - $cantidad_descontar;
                $stmt_upd = $conexion->prepare("UPDATE insumos SET cantidad = ? WHERE id = ?");
                $stmt_upd->bind_param("ii", $nuevo_stock, $insumo_id);
                $stmt_upd->execute();
                $stmt_upd->close();

                // Registrar en movimientos_inventario
                $tipo_mov = "Solicitud / Retiro";
                $fecha_actual_full = date('Y-m-d H:i:s');
                $stmt_mov = $conexion->prepare("INSERT INTO movimientos_inventario (usuario, puesto, insumo, cantidad, tipo_movimiento, fecha) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_mov->bind_param("sssiss", $nombre_usuario, $puesto_usuario, $nombre_insumo, $cantidad_descontar, $tipo_mov, $fecha_actual_full);
                $stmt_mov->execute();
                $stmt_mov->close();

                $mensaje_solicitud_pdf = "Solicitud realizada: " . $nombre_insumo . " (Cantidad: " . $cantidad_descontar . " unidades)";
                
                // Estructura de evidencia exclusiva para este reporte
                $movimiento_actual = [
                    'fecha' => $fecha_actual_full,
                    'usuario' => $nombre_usuario,
                    'puesto' => $puesto_usuario,
                    'insumo' => $nombre_insumo,
                    'cantidad' => $cantidad_descontar,
                    'tipo_movimiento' => $tipo_mov
                ];
            }
        }
        $stmt_ins->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Atmósfera Dulce</title>
    
    <!-- Librería HTML2PDF para descarga directa en PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Georgia', serif;
        }

        body {
            background-color: #bdc8cd;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        /* Ventana del Formulario */
        .report-window {
            width: 100%;
            max-width: 800px;
            background-color: #4a7584;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            overflow: hidden;
            position: relative;
        }

        .report-header {
            background-color: #e5737d;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }

        .report-title {
            font-size: 32px;
            font-weight: bold;
            color: #000000;
            letter-spacing: 1px;
            margin: 0 auto;
        }

        .btn-close {
            position: absolute;
            right: 25px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
        }

        .img-close {
            width: 40px;
            height: 40px;
            object-fit: contain;
            transition: transform 0.2s ease;
        }

        .img-close:hover {
            transform: scale(1.1);
        }

        .report-body {
            padding: 30px;
        }

        .form-group {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .form-group label {
            width: 140px;
            font-size: 18px;
            font-weight: bold;
            color: #000000;
        }

        .form-input {
            flex: 1;
            padding: 10px 15px;
            font-size: 16px;
            border: none;
            border-radius: 4px;
            background-color: #ffffff;
            outline: none;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
        }

        .textarea-container {
            margin-bottom: 10px;
        }

        .report-textarea {
            width: 100%;
            height: 180px;
            padding: 15px;
            font-size: 16px;
            border: none;
            border-radius: 4px;
            background-color: #ffffff;
            resize: none;
            outline: none;
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.15);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .char-counter {
            text-align: right;
            font-size: 14px;
            color: #ffffff;
            margin-bottom: 20px;
            font-family: sans-serif;
        }

        .footer-action {
            text-align: center;
        }

        .btn-generate {
            background-color: #008cba;
            color: #ffffff;
            border: none;
            padding: 12px 45px;
            font-size: 22px;
            font-weight: bold;
            border-radius: 25px;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            transition: background-color 0.2s, transform 0.1s;
        }

        .btn-generate:hover {
            background-color: #007399;
            transform: translateY(-2px);
        }

        /* --- TABLA DE EVIDENCIAS EN PDF --- */
        .evidence-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 13px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .evidence-table th, .evidence-table td {
            border: 1px solid #ccc;
            padding: 8px 10px;
            text-align: left;
        }

        .evidence-table th {
            background-color: #f2f2f2;
            font-weight: bold;
            color: #333;
        }

        .badge-add { color: #28a745; font-weight: bold; }
        .badge-remove { color: #dc3545; font-weight: bold; }

        /* --- PLANTILLA HOJA MEMBRETADA PARA CONVERSIÓN PDF --- */
        #pdfTemplate { display: none; }

        .pdf-container {
            position: relative;
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            background: #ffffff;
            box-sizing: border-box;
            font-family: 'Georgia', serif;
        }

        .watermark-pdf {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 380px;
            opacity: 0.12;
            pointer-events: none;
        }

        .sheet-inner-pdf {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            min-height: 250mm;
        }

        .sheet-header-pdf {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1px dotted #333;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }

        .logo-pdf { width: 180px; object-fit: contain; }
        .sheet-body-pdf { flex: 1; }
        .footer-pdf-img {
            position: absolute;
            bottom: 0;
            left: -20mm;
            right: -20mm;
            width: 210mm;
            height: 25px;
            object-fit: cover;
        }
    </style>
</head>
<body>

    <!-- Ventana del Formulario (Interfaz visual) -->
    <div class="report-window">
        <div class="report-header">
            <h1 class="report-title">Reportes</h1>
            <button class="btn-close" onclick="location.href='main.php'" title="Cerrar / Volver al Menú">
                <img src="X.png" alt="Cerrar" class="img-close">
            </button>
        </div>

        <div class="report-body">
            <form action="reportes.php" method="POST" id="reportForm">
                <div class="form-group">
                    <label for="nombre">Nombre</label>
                    <input type="text" id="nombre" name="nombre" class="form-input" value="<?php echo htmlspecialchars($nombre_usuario); ?>" required>
                </div>

                <div class="form-group">
                    <label for="fecha">Fecha</label>
                    <input type="text" id="fecha" name="fecha" class="form-input" value="<?php echo htmlspecialchars($fecha_reporte); ?>" required>
                </div>

                <!-- Campo único con autocompletado y desplegable integrado (Datalist) -->
                <div class="form-group">
                    <label for="insumo_nombre">Insumo</label>
                    <input type="text" id="insumo_nombre" name="insumo_nombre" class="form-input" list="listaInsumos" placeholder="🔍 Escribe o selecciona un insumo...">
                    <datalist id="listaInsumos">
                        <?php foreach ($insumos_lista as $ins): ?>
                            <option value="<?php echo htmlspecialchars($ins['nombre']); ?>">
                                <?php echo "Stock disponible: " . intval($ins['cantidad']); ?>
                            </option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <!-- Cantidad del Insumo Solicitado -->
                <div class="form-group">
                    <label for="cantidad_solicitada">Cantidad</label>
                    <input type="number" id="cantidad_solicitada" name="cantidad_solicitada" class="form-input" min="0" value="0" placeholder="0">
                </div>

                <!-- Campo de texto con límite de 3000 caracteres -->
                <div class="textarea-container">
                    <textarea name="contenido" id="contenido" class="report-textarea" maxlength="3000" placeholder="Escribe los detalles del reporte aquí..." oninput="actualizarContador()" required><?php echo htmlspecialchars($contenido_texto); ?></textarea>
                </div>
                
                <div class="char-counter">
                    <span id="charCount">0</span> / 3000 caracteres
                </div>

                <div class="footer-action">
                    <button type="submit" name="generar" class="btn-generate">Generar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hoja Membretada para Renderizado y Descarga de PDF Directo -->
    <div id="pdfTemplate">
        <div class="pdf-container" id="pdfContent">
            <!-- Marca de agua -->
            <img src="Azul.png" alt="Marca de agua" class="watermark-pdf">

            <div class="sheet-inner-pdf">
                <!-- Encabezado con Logo -->
                <div class="sheet-header-pdf">
                    <div>
                        <img src="Logo color.png" alt="Atmósfera Dulce" class="logo-pdf">
                    </div>
                    <div style="text-align: right; font-size: 14px; color: #333;">
                        <strong>Fecha:</strong> <span id="lblFecha"></span>
                    </div>
                </div>

                <!-- Contenido Principal -->
                <div class="sheet-body-pdf">
                    <div style="margin-bottom: 20px; font-size: 15px; color: #222;">
                        <strong>Emitido por:</strong> <span id="lblNombre"></span> (<span id="lblPuesto"></span>)
                    </div>
                    
                    <h3 style="margin-bottom: 10px; color: #444; border-bottom: 1px solid #ccc; padding-bottom: 5px;">Reporte de Operaciones</h3>
                    
                    <div id="lblSolicitudEspecifica" style="font-size: 15px; font-weight: bold; color: #dc3545; margin-bottom: 15px; display: none;"></div>

                    <div id="lblContenido" style="font-size: 15px; line-height: 1.6; color: #111; white-space: pre-wrap; margin-top: 10px;"></div>

                    <!-- Sección de Evidencias Exclusiva del Reporte Actual -->
                    <h4 style="margin-top: 25px; margin-bottom: 10px; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Evidencia de Movimiento de Insumo (Solicitud Actual)</h4>
                    
                    <table class="evidence-table">
                        <thead>
                            <tr>
                                <th>Fecha y Hora</th>
                                <th>Solicitante / Usuario</th>
                                <th>Puesto</th>
                                <th>Insumo</th>
                                <th>Cantidad</th>
                                <th>Movimiento</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($movimiento_actual)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($movimiento_actual['fecha']); ?></td>
                                    <td><?php echo htmlspecialchars($movimiento_actual['usuario']); ?></td>
                                    <td><?php echo htmlspecialchars($movimiento_actual['puesto']); ?></td>
                                    <td><?php echo htmlspecialchars($movimiento_actual['insumo']); ?></td>
                                    <td><?php echo htmlspecialchars($movimiento_actual['cantidad']); ?></td>
                                    <td><span class="badge-remove">- Solicitado</span></td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #777;">No se incluyeron solicitudes de insumos en este reporte.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Franja Rosa inferior -->
                <div>
                    <img src="Rosa.png" alt="Pie de página" class="footer-pdf-img">
                </div>
            </div>
        </div>
    </div>

    <script>
        // Contador de caracteres
        function actualizarContador() {
            const textarea = document.getElementById('contenido');
            const counter = document.getElementById('charCount');
            counter.innerText = textarea.value.length;
        }

        actualizarContador();
    </script>

    <?php if ($generando): ?>
    <script>
        document.getElementById('lblNombre').innerText = "<?php echo addslashes($nombre_usuario); ?>";
        document.getElementById('lblFecha').innerText = "<?php echo addslashes($fecha_reporte); ?>";
        document.getElementById('lblPuesto').innerText = "<?php echo addslashes($puesto_usuario); ?>";
        document.getElementById('lblContenido').innerText = `<?php echo addslashes($contenido_texto); ?>`;

        <?php if (!empty($mensaje_solicitud_pdf)): ?>
            const lblSol = document.getElementById('lblSolicitudEspecifica');
            lblSol.innerText = "<?php echo addslashes($mensaje_solicitud_pdf); ?>";
            lblSol.style.display = "block";
        <?php endif; ?>

        window.onload = function() {
            const elemento = document.getElementById('pdfContent');
            const opciones = {
                margin:       0,
                filename:     'Reporte_Atmosfera_Dulce_<?php echo date("Ymd_His"); ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, logging: false },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            html2pdf().set(opciones).from(elemento).save();
        };
    </script>
    <?php endif; ?>

</body>
</html>