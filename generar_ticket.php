<?php
session_start();

// Configurar la zona horaria correcta para México
date_default_timezone_set('America/Mexico_City');

// Validar que el usuario haya iniciado sesión por seguridad
if (!isset($_SESSION['usuario_nombre'])) {
    header("Location: login.php?error=1");
    exit();
}

require_once 'conexion.php';

// Validar que se haya pasado por GET el ID del ticket o venta a generar
if (!isset($_GET['id'])) {
    die("Error: No se especificó el ticket a generar.");
}

$id_venta = intval($_GET['id']);

// 1. Obtener el registro de la venta directamente desde la tabla 'ventas'
$stmt = $conexion->prepare("SELECT * FROM ventas WHERE id = ?");
$stmt->bind_param("i", $id_venta);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado && $resultado->num_rows > 0) {
    $venta = $resultado->fetch_assoc();
} else {
    die("Error: El ticket de venta solicitado no existe en la base de datos.");
}
$stmt->close();

// 2. Procesar los productos almacenados para extraer nombre, cantidad y subtotal exacto
$items_raw = explode(', ', $venta['productos']);
$productos_detalle = "";

foreach ($items_raw as $item_str) {
    // Expresión regular corregida para capturar nombre, cantidad y subtotal individual guardados
    if (preg_match('/^(.*) \(x(\d+)\) - \$([\d\.]+)$/', trim($item_str), $matches)) {
        $nombre_prod = trim($matches[1]);
        $cant_prod = intval($matches[2]);
        $subtotal_item = floatval($matches[3]);

        $productos_detalle .= sprintf(
            "%-18s x%-3d   $%8.2f\n", 
            substr($nombre_prod, 0, 18), 
            $cant_prod, 
            $subtotal_item
        );
    }
}

// 3. Construir el contenido del ticket de texto plano formateado
$fecha_formateada = date('d/m/Y H:i:s', strtotime($venta['fecha']));

$contenido_ticket = "========================================\n";
$contenido_ticket .= "           TICKET DE COMPRA            \n";
$contenido_ticket .= "========================================\n";
$contenido_ticket .= "Ticket ID: #" . $venta['id'] . "\n";
$contenido_ticket .= "Fecha: " . $fecha_formateada . "\n";
$contenido_ticket .= "Cajero: " . $venta['usuario'] . "\n";
$contenido_ticket .= "----------------------------------------\n";
$contenido_ticket .= "PRODUCTO           CANT    SUBTOTAL\n";
$contenido_ticket .= "----------------------------------------\n";
$contenido_ticket .= $productos_detalle;
$contenido_ticket .= "----------------------------------------\n";
$contenido_ticket .= sprintf("SUBTOTAL:                 $%8.2f\n", $venta['subtotal']);
$contenido_ticket .= "========================================\n";
$contenido_ticket .= sprintf("TOTAL A PAGAR:            $%8.2f\n", $venta['total']);
$contenido_ticket .= "========================================\n";
$contenido_ticket .= "         ¡Gracias por su compra!        \n";

// 4. Forzar la descarga directa del archivo de ticket
$nombre_archivo = "Ticket_" . $venta['id'] . "_" . date('Ymd_His') . ".txt";

header('Content-Description: File Transfer');
header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($contenido_ticket));

echo $contenido_ticket;
exit();
?>