<?php
session_start();
require_once __DIR__ . '../../conexion/conexion.php';
require_once __DIR__ . '/funciones_login.php';

$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $mensaje = "Complete todos los campos";
    } else {
        try {
            $usuario = obtenerUsuario($pdo, $email);

            if ($usuario) {
                
                if ($usuario['activo'] != 1) {
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_token'] = $usuario['token'];
                    echo "<script>
                        alert('Tu cuenta no está activada. Serás redirigido a la página de activación.');
                        window.location.href = '../registro/activar.php';
                    </script>";
                    exit;
                } elseif (password_verify($password, $usuario['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['usuario_email'] = $usuario['email'];
                    $_SESSION['usuario_nombre'] = $usuario['nombre'];
                    $_SESSION['logueado'] = true;
                    
                    header("Location: ../index.php");
                    exit;
                } else {
                    $mensaje = "Email o contraseña incorrectos";
                }
            } else {
                $mensaje = "Email o contraseña incorrectos";
            }
        }     
            // ...existing code...
                     catch (PDOException $e) {
                        $mensaje = "Error en el sistema: " . $e->getMessage();
                        error_log("Error de login: " . $e->getMessage());
                        echo "<div class='error-message' style='background: #ffebee; color: #c62828; padding: 10px; margin: 10px 0; border: 1px solid #ef9a9a; border-radius: 4px;'>";
                        echo "<strong>Error de Base de Datos:</strong> ";
                        echo htmlspecialchars($e->getMessage());
                        echo "</div>";
                    }
            // ...existing code...error_log("Error de login: " . $e->getMessage());
        }
    }

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="../css/login.css">
    <link rel="icon" href="../assets/img/Logo.png" type="image/png">
</head>

<body>
    <div class="login-container">
        <h2>Iniciar Sesión</h2>

        <?php if (!empty($mensaje)): ?>
            <p style="color:red;"><?= htmlspecialchars($mensaje) ?></p>
        <?php endif; ?>

        <form action="" method="post">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>

            <label for="password">Contraseña</label>
            <div class="password-wrapper">
                <input type="password" id="password" name="password" required>
                <img src="../assets/img/ojo_abierto.png" alt="Mostrar contraseña" class="toggle-password" data-target="password">
            </div>

            <button type="submit">Ingresar</button>
            <p><a href="../registro/registro.php">¿No tenés cuenta? Registrate aquí</a></p>
            <p><a href="recuperar.php">¿Olvidaste tu contraseña?</a></p>
        </form>
    </div>
    <script src="../js/ocultar_contraseña.js"></script>
</body>
</html>