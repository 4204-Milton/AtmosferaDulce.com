<?php
session_start();
require_once 'conexion.php';

$mensaje = "";
$tipo_mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre           = trim($_POST['nombre'] ?? '');
    $correo           = trim($_POST['correo'] ?? '');
    $rol              = trim($_POST['rol'] ?? '');
    $contrasena       = $_POST['contrasena'] ?? '';
    $confirmar_contra = $_POST['confirmar_contrasena'] ?? '';

    // Validaciones básicas
    if (empty($nombre) || empty($correo) || empty($rol) || empty($contrasena) || empty($confirmar_contra)) {
        $mensaje = "Todos los campos son obligatorios.";
        $tipo_mensaje = "error";
    } elseif ($contrasena !== $confirmar_contra) {
        $mensaje = "Las contraseñas no coinciden.";
        $tipo_mensaje = "error";
    } else {
        // Verificar si el correo ya existe
        $stmt_check = $conexion->prepare("SELECT id FROM registro WHERE correo = ?");
        $stmt_check->bind_param("s", $correo);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $mensaje = "El correo electrónico ya está registrado.";
            $tipo_mensaje = "error";
        } else {
            // Encriptar la contraseña para coincidir con el formato del hash ($2y$10$...) de tu BD
            $pass_encriptada = password_hash($contrasena, PASSWORD_DEFAULT);

            // Insertar en la tabla 'usuarios' usando la columna 'password'
            $stmt_ins = $conexion->prepare("INSERT INTO registro (nombre, correo, password, rol) VALUES (?, ?, ?, ?)");
            $stmt_ins->bind_param("ssss", $nombre, $correo, $pass_encriptada, $rol);

            if ($stmt_ins->execute()) {
                // Configurar y enviar correo de bienvenida
                $to      = $correo;
                $subject = "Bienvenido a Atmósfera Dulce - Tus Credenciales";
                
                $message = "
                <html>
                <head>
                  <title>Registro Exitoso</title>
                </head>
                <body>
                  <h2>¡Hola, " . htmlspecialchars($nombre) . "!</h2>
                  <p>Se ha creado con éxito tu cuenta en el sistema de <strong>Atmósfera Dulce</strong>.</p>
                  <p><strong>Detalles de tu cuenta:</strong></p>
                  <ul>
                    <li><strong>Nombre:</strong> " . htmlspecialchars($nombre) . "</li>
                    <li><strong>Rol Asignado:</strong> " . htmlspecialchars($rol) . "</li>
                    <li><strong>Contraseña:</strong> " . htmlspecialchars($contrasena) . "</li>
                  </ul>
                  <p>Por favor, guarda este correo en un lugar seguro.</p>
                </body>
                </html>
                ";

                $headers  = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
                $headers .= "From: Atmósfera Dulce <no-reply@atmosferadulce.com>" . "\r\n";

                @mail($to, $subject, $message, $headers);

                $mensaje = "¡Registro exitoso! Se ha enviado un correo con tus credenciales.";
                $tipo_mensaje = "exito";
            } else {
                $mensaje = "Error al registrar en el sistema: " . $stmt_ins->error;
                $tipo_mensaje = "error";
            }
            $stmt_ins->close();
        }
        $stmt_check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Atmósfera Dulce</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #bdc8cd;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 20px;
        }

        .top-left-logo {
            position: absolute;
            top: 25px;
            left: 25px;
            width: 110px;
            height: auto;
        }

        .register-card {
            width: 100%;
            max-width: 440px;
            background-color: #e5737d;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            position: relative;
        }

        .card-header {
            background-color: #427888;
            padding: 25px;
            text-align: center;
            position: relative;
        }

        .card-header h2 {
            color: #ffffff;
            font-size: 38px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .btn-close {
            position: absolute;
            top: 18px;
            right: 20px;
            color: #ffffff;
            text-decoration: none;
            font-size: 22px;
            font-weight: bold;
            background: rgba(255,255,255,0.2);
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .card-body {
            padding: 30px 35px 40px 35px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 12px 18px;
            border-radius: 20px;
            border: none;
            outline: none;
            font-size: 15px;
            color: #444;
            background-color: #ffffff;
            text-align: center;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.08);
        }

        .form-input::placeholder {
            color: #999;
            text-align: center;
        }

        .form-select {
            text-align-last: center;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }

        .btn-submit {
            margin-top: 10px;
            align-self: center;
            background-color: #427888;
            color: #ffffff;
            border: none;
            padding: 12px 40px;
            font-size: 16px;
            font-weight: bold;
            border-radius: 20px;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.1s ease;
        }

        .btn-submit:hover {
            background-color: #335d6a;
            transform: translateY(-1px);
        }

        .login-link {
            text-align: center;
            margin-top: 10px;
            font-size: 14px;
            color: #ffffff;
            text-decoration: underline;
        }

        .login-link:hover {
            color: #f2f2f2;
        }

        .alert {
            padding: 10px 15px;
            border-radius: 10px;
            font-size: 14px;
            text-align: center;
            font-weight: bold;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .alert-exito {
            background-color: #d4edda;
            color: #155724;
        }
    </style>
</head>
<body>

    <img src="Azul.png" alt="Logo Atmósfera Dulce" class="top-left-logo">

    <div class="register-card">
        <div class="card-header">
            <h2>Registrarse</h2>
            <a href="login.php" class="btn-close" title="Volver al Login">×</a>
        </div>

        <form action="registro.php" method="POST" class="card-body">
            
            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                    <?php echo htmlspecialchars($mensaje); ?>
                </div>
            <?php endif; ?>

            <input type="text" name="nombre" class="form-input" placeholder="Nombre completo" value="<?php echo htmlspecialchars($_POST['nombre'] ?? ''); ?>" required>
            
            <input type="email" name="correo" class="form-input" placeholder="Correo electrónico" value="<?php echo htmlspecialchars($_POST['correo'] ?? ''); ?>" required>
            
            <select name="rol" class="form-select" required>
                <option value="" disabled <?php echo !isset($_POST['rol']) ? 'selected' : ''; ?>>Seleccionar Rol...</option>
                <option value="Administrador" <?php echo (($_POST['rol'] ?? '') === 'Administrador') ? 'selected' : ''; ?>>Administrador</option>
                <option value="Repostero" <?php echo (($_POST['rol'] ?? '') === 'Repostero') ? 'selected' : ''; ?>>Repostero</option>
                <option value="Cajero" <?php echo (($_POST['rol'] ?? '') === 'Cajero') ? 'selected' : ''; ?>>Cajero</option>
                <option value="Almacenista" <?php echo (($_POST['rol'] ?? '') === 'Almacenista') ? 'selected' : ''; ?>>Almacenista</option>
            </select>

            <input type="password" name="contrasena" class="form-input" placeholder="Establecer Contraseña" required>
            
            <input type="password" name="confirmar_contrasena" class="form-input" placeholder="Confirmar Contraseña" required>

            <button type="submit" class="btn-submit">Registrar</button>

            <a href="login.php" class="login-link">¿Ya tienes una cuenta? Inicia sesión aquí</a>
        </form>
    </div>

</body>
</html>