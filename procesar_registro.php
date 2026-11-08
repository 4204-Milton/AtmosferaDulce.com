<?php
session_start();
require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $pass = $_POST['password'];
    $confirm_pass = $_POST['confirm_password'];

    // 1. Validar coincidencia de contraseñas en servidor
    if ($pass !== $confirm_pass) {
        header("Location: registro.php?error=password_mismatch");
        exit();
    }

    // 2. Verificar si el correo ya existe en la tabla registro
    $stmt_check = $conexion->prepare("SELECT id FROM registro WHERE correo = ?");
    $stmt_check->bind_param("s", $correo);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();

    if ($res_check->num_rows > 0) {
        $stmt_check->close();
        header("Location: registro.php?error=user_exists");
        exit();
    }
    $stmt_check->close();

    // 3. Cifrar la contraseña por seguridad
    $password_hashed = password_hash($pass, PASSWORD_DEFAULT);

    // 4. Insertar datos en la tabla 'registro'
    $stmt = $conexion->prepare("INSERT INTO registro (nombre, correo, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $nombre, $correo, $password_hashed);

    if ($stmt->execute()) {
        $stmt->close();
        $conexion->close();
        header("Location: registro.php?success=1");
        exit();
    } else {
        $stmt->close();
        $conexion->close();
        header("Location: registro.php?error=db_error");
        exit();
    }
} else {
    header("Location: registro.php");
    exit();
}
?>