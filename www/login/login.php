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
                    if($usuario['rol_id'] == 1){
                        $_SESSION['es_admin'] = true;
                    } else{
                        $_SESSION['es_admin'] = false;
                    }
                    
                    header("Location: ../index.php");
                    exit;
                } else {
                    $mensaje = "Email o contraseña incorrectos";
                }
            } else {
                $mensaje = "Email o contraseña incorrectos";
            }
        }     
         
                     catch (PDOException $e) {
                        $mensaje = "Error en el sistema: " . $e->getMessage();
                       
                    }
           
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
        <div class="logo-container">
            <img src="../Logo/logoisft177.png" alt="Logo ISFT 177" class="logo">
        </div>
        <h2>Iniciar Sesión</h2>

        <?php if (!empty($mensaje)): ?>
            <p style="color:red;"><?= htmlspecialchars($mensaje) ?></p>
        <?php endif; ?>

        <form action="" method="post">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>

            <label for="password">Contraseña</label>
            <div class="password-wrapper">
                <input type="password" id="password" name="password" required placeholder="Tu contraseña">
                <span class="toggle-password" data-target="password">👁️‍🗨️</span>
            </div>

            <button type="submit">Ingresar</button>
            <p><a href="../registro/registro.php">¿No tenés cuenta? Registrate aquí</a></p>
            <p><a href="recuperar.php">¿Olvidaste tu contraseña?</a></p>
        </form>
    </div>
    
    <footer class="main-footer">
        <p>Desarrollado por Alumnos de 2do de Sistemas - ISFT N° 177</p>
    </footer>
    
    <script src="../js/ocultar_contraseña.js"></script>
</body>
</html>