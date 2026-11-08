<?php
// Configuración de conexión a la base de datos
$host = "localhost";
$usuario = "root";
$password = ""; // Cambia esto si asignaste contraseña a tu MySQL
$base_datos = "atmosferadulce";

$conexion = new mysqli($host, $usuario, $password, $base_datos);

// Verificar la conexión
if ($conexion->connect_error) {
    die("Error de conexión a la base de datos: " . $conexion->connect_error);
}

// Configurar conjunto de caracteres
$conexion->set_charset("utf8");
?>