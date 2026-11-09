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

// Arreglos recibidos del formulario para múltiples insumos
$insumos_nombres = $_POST['insumo_nombre'] ?? [];
$cantidades_solicitadas = $_POST['cantidad_solicitada'] ?? [];

// Tomar directamente el puesto guardado al iniciar sesión
$puesto_usuario = $_SESSION['usuario_puesto'] ?? ($_SESSION['usuario_rol'] ?? 'Cajero'); 

$generando = isset($_POST['generar']);
$mensajes_solicitud_pdf = [];
$movimientos_actuales = []; // Array con los insumos descontados en este reporte

// Obtener la lista completa de insumos de la BD para los datalists
$res_todos_insumos = $conexion->query("SELECT id, nombre, cantidad FROM insumos ORDER BY nombre ASC");
$insumos_lista = [];
if ($res_todos_insumos && $res_todos_insumos->num_rows > 0) {
    while ($i = $res_todos_insumos->fetch_assoc()) {
        $insumos_lista[] = $i;
    }
}

// Guardar el reporte, registrar las solicitudes y descontar del inventario
if ($generando) {
    if (!empty($contenido_texto)) {
        // 1. Guardar el reporte de texto en la BD
        $stmt = $conexion->prepare("INSERT INTO reportes (nombre, puesto, fecha, contenido) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $nombre_usuario, $puesto_usuario, $fecha_reporte, $contenido_texto);
        $stmt->execute();
        $stmt->close();
    }

    // 2. Procesar la lista de múltiples insumos
    if (is_array($insumos_nombres) && count($insumos_nombres) > 0) {
        $fecha_actual_full = date('Y-m-d H:i:s');
        $tipo_mov = "Solicitud / Retiro";

        for ($idx = 0; $idx < count($insumos_nombres); $idx++) {
            $nombre_ins_item = trim($insumos_nombres[$idx]);
            $cant_req_item = intval($cantidades_solicitadas[$idx] ?? 0);

            if (!empty($nombre_ins_item) && $cant_req_item > 0) {
                // Buscar el insumo por nombre exacto
                $stmt_ins = $conexion->prepare("SELECT id, nombre, cantidad FROM insumos WHERE nombre = ?");
                $stmt_ins->bind_param("s", $nombre_ins_item);
                $stmt_ins->execute();
                $res_ins = $stmt_ins->get_result();

                if ($res_ins && $res_ins->num_rows > 0) {
                    $datos_insumo = $res_ins->fetch_assoc();
                    $insumo_id = intval($datos_insumo['id']);
                    $nombre_real = $datos_insumo['nombre'];
                    $stock_actual = intval($datos_insumo['cantidad']);

                    $cantidad_descontar = min($cant_req_item, $stock_actual);

                    if ($cantidad_descontar > 0) {
                        // Descontar stock
                        $nuevo_stock = $stock_actual - $cantidad_descontar;
                        $stmt_upd = $conexion->prepare("UPDATE insumos SET cantidad = ? WHERE id = ?");
                        $stmt_upd->bind_param("ii", $nuevo_stock, $insumo_id);
                        $stmt_upd->execute();
                        $stmt_upd->close();

                        // Auditoría en movimientos_inventario
                        $stmt_mov = $conexion->prepare("INSERT INTO movimientos_inventario (usuario, puesto, insumo, cantidad, tipo_movimiento, fecha) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt_mov->bind_param("sssiss", $nombre_usuario, $puesto_usuario, $nombre_real, $cantidad_descontar, $tipo_mov, $fecha_actual_full);
                        $stmt_mov->execute();
                        $stmt_mov->close();

                        $mensajes_solicitud_pdf[] = "• " . $nombre_real . ": " . $cantidad_descontar . " unidades";

                        $movimientos_actuales[] = [
                            'fecha' => $fecha_actual_full,
                            'usuario' => $nombre_usuario,
                            'puesto' => $puesto_usuario,
                            'insumo' => $nombre_real,
                            'cantidad' => $cantidad_descontar
                        ];
                    }
                }
                $stmt_ins->close();
            }
        }
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

        .report-window {
            width: 100%;
            max-width: 850px;
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

        /* Bloque Dinámico de Insumos */
        .receta-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .receta-title {
            font-size: 18px;
            font-weight: bold;
            color: #ffffff;
        }

        .btn-add-item {
            background-color: #28a745;
            color: #fff;
            border: none;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: background 0.2s;
        }

        .btn-add-item:hover {
            background-color: #218838;
        }

        .insumo-row-item {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 10px;
        }

        .insumo-row-item .form-input {
            margin-bottom: 0;
        }

        .input-cant {
            max-width: 120px;
        }

        .btn-remove-item {
            background-color: #dc3545;
            color: #fff;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }

        .btn-remove-item:hover {
            background-color: #bd2130;
        }

        .textarea-container {
            margin-top: 15px;
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

        /* PDF Styles */
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

        .badge-remove { color: #dc3545; font-weight: bold; }

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

    <datalist id="listaInsumos">
        <?php foreach ($insumos_lista as $ins): ?>
            <option value="<?php echo htmlspecialchars($ins['nombre']); ?>">
                <?php echo "Stock disponible: " . intval($ins['cantidad']); ?>
            </option>
        <?php endforeach; ?>
    </datalist>

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

                <!-- Lista dinámica de insumos para la receta -->
                <div class="receta-header">
                    <span class="receta-title">Seleccion de insumos solicitados</span>
                    <button type="button" class="btn-add-item" onclick="agregarFilaInsumo()">+ Añadir Insumo</button>
                </div>

                <div id="contenedorInsumos">
                    <div class="insumo-row-item">
                        <input type="text" name="insumo_nombre[]" class="form-input" list="listaInsumos" placeholder="🔍 Buscar o seleccionar insumo...">
                        <input type="number" name="cantidad_solicitada[]" class="form-input input-cant" min="0" value="0" placeholder="Cant.">
                        <button type="button" class="btn-remove-item" onclick="eliminarFilaInsumo(this)" title="Quitar este insumo">✕</button>
                    </div>
                </div>

                <div class="textarea-container">
                    <textarea name="contenido" id="contenido" class="report-textarea" maxlength="3000" placeholder="Escribe los detalles del reporte o receta aquí..." oninput="actualizarContador()" required><?php echo htmlspecialchars($contenido_texto); ?></textarea>
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

    <!-- Hoja Membretada para PDF -->
    <div id="pdfTemplate">
        <div class="pdf-container" id="pdfContent">
            <img src="Azul.png" alt="Marca de agua" class="watermark-pdf">

            <div class="sheet-inner-pdf">
                <div class="sheet-header-pdf">
                    <div>
                        <img src="Logo color.png" alt="Atmósfera Dulce" class="logo-pdf">
                    </div>
                    <div style="text-align: right; font-size: 14px; color: #333;">
                        <strong>Fecha:</strong> <span id="lblFecha"></span>
                    </div>
                </div>

                <div class="sheet-body-pdf">
                    <div style="margin-bottom: 20px; font-size: 15px; color: #222;">
                        <strong>Emitido por:</strong> <span id="lblNombre"></span> (<span id="lblPuesto"></span>)
                    </div>
                    
                    <h3 style="margin-bottom: 10px; color: #444; border-bottom: 1px solid #ccc; padding-bottom: 5px;">Reporte de Operaciones</h3>
                    
                    <div id="lblSolicitudEspecifica" style="font-size: 14px; font-weight: bold; color: #dc3545; margin-bottom: 15px; display: none;"></div>

                    <div id="lblContenido" style="font-size: 15px; line-height: 1.6; color: #111; white-space: pre-wrap; margin-top: 10px;"></div>

                    <h4 style="margin-top: 25px; margin-bottom: 10px; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Evidencia de Movimientos de Insumos (Receta Solicitada)</h4>
                    
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
                            <?php if (!empty($movimientos_actuales)): ?>
                                <?php foreach ($movimientos_actuales as $mov_item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($mov_item['fecha']); ?></td>
                                        <td><?php echo htmlspecialchars($mov_item['usuario']); ?></td>
                                        <td><?php echo htmlspecialchars($mov_item['puesto']); ?></td>
                                        <td><?php echo htmlspecialchars($mov_item['insumo']); ?></td>
                                        <td><?php echo htmlspecialchars($mov_item['cantidad']); ?></td>
                                        <td><span class="badge-remove">- Solicitado</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: #777;">No se incluyeron solicitudes de insumos en este reporte.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div>
                    <img src="Rosa.png" alt="Pie de página" class="footer-pdf-img">
                </div>
            </div>
        </div>
    </div>

    <script>
        function actualizarContador() {
            const textarea = document.getElementById('contenido');
            const counter = document.getElementById('charCount');
            counter.innerText = textarea.value.length;
        }

        function agregarFilaInsumo() {
            const contenedor = document.getElementById('contenedorInsumos');
            const div = document.createElement('div');
            div.className = 'insumo-row-item';
            div.innerHTML = `
                <input type="text" name="insumo_nombre[]" class="form-input" list="listaInsumos" placeholder="🔍 Buscar o seleccionar insumo...">
                <input type="number" name="cantidad_solicitada[]" class="form-input input-cant" min="0" value="0" placeholder="Cant.">
                <button type="button" class="btn-remove-item" onclick="eliminarFilaInsumo(this)" title="Quitar este insumo">✕</button>
            `;
            contenedor.appendChild(div);
        }

        function eliminarFilaInsumo(btn) {
            const contenedor = document.getElementById('contenedorInsumos');
            if (contenedor.children.length > 1) {
                btn.parentElement.remove();
            } else {
                // Si es la única fila, limpiar sus campos
                const row = btn.parentElement;
                row.querySelector('input[type="text"]').value = '';
                row.querySelector('input[type="number"]').value = '0';
            }
        }

        actualizarContador();
    </script>

    <?php if ($generando): ?>
    <script>
        document.getElementById('lblNombre').innerText = "<?php echo addslashes($nombre_usuario); ?>";
        document.getElementById('lblFecha').innerText = "<?php echo addslashes($fecha_reporte); ?>";
        document.getElementById('lblPuesto').innerText = "<?php echo addslashes($puesto_usuario); ?>";
        document.getElementById('lblContenido').innerText = `<?php echo addslashes($contenido_texto); ?>`;

        <?php if (!empty($mensajes_solicitud_pdf)): ?>
            const lblSol = document.getElementById('lblSolicitudEspecifica');
            lblSol.innerHTML = "<strong>Insumos descontados para esta receta:</strong><br>" + `<?php echo implode('<br>', array_map('addslashes', $mensajes_solicitud_pdf)); ?>`;
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
