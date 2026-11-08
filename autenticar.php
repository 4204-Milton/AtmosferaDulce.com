<?php

session_start();

require_once 'conexion.php';



if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $ingreso = trim($_POST['usuario']); // Lo que el usuario escribe en el input de login

    $password = $_POST['password'];



    // CORREGIDO: Buscamos por 'nombre' o 'correo' ya que no existe la columna 'usuario'

    $stmt = $conexion->prepare("SELECT * FROM registro WHERE nombre = ? OR correo = ?");

    $stmt->bind_param("ss", $ingreso, $ingreso);

    $stmt->execute();

    $resultado = $stmt->get_result();



    if ($resultado->num_rows === 1) {

        $row = $resultado->fetch_assoc();



        // Validamos la contraseña cifrada

        if (password_verify($password, $row['password']) || $password === $row['password']) {

           

            $_SESSION['usuario_nombre'] = $row['nombre'];

            $_SESSION['usuario_rol'] = $row['rol']; // Almacenista, Pastelero, Administrador, etc.



            header("Location: main.php");

            exit();

        }

    }



    // Si las credenciales son incorrectas

    header("Location: login.php?error=1");

    exit();

} else {

    header("Location: login.php");

    exit();

}

?>