<?php
session_start();
require_once __DIR__ . '/../conexion/conexion.php';
require_once __DIR__ . '/../registro/generar_token.php';

$mensaje = '';
$mensaje_tipo = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $mensaje = "Ingresa tu email";
        $mensaje_tipo = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, email, activo FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario) {
                // Generar nuevo token y enviarlo por email
                $resultado = generarNuevoToken($pdo, $email);
                
                if ($resultado) {
                    $mensaje = "Se ha enviado un nuevo código de activación a tu correo electrónico. Revisa tu bandeja de entrada.";
                    $mensaje_tipo = 'success';
                    
                    // Guardar email en sesión para la activación
                    $_SESSION['user_email'] = $email;
                    
                    // Obtener el token para guardarlo en sesión
                    $stmt = $pdo->prepare("SELECT token FROM usuarios WHERE email = ?");
                    $stmt->execute([$email]);
                    $usuario_token = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($usuario_token) {
                        $_SESSION['user_token'] = $usuario_token['token'];
                    }
                    
                    // Redirigir a la página de activación después de 2 segundos
                    echo "<script>setTimeout(function() { window.location.href = '../registro/activar.php'; }, 2000);</script>";
                } else {
                    $mensaje = "Error al enviar el correo. Intenta nuevamente.";
                    $mensaje_tipo = 'error';
                }
            } else {
                $mensaje = "No existe una cuenta asociada a este email.";
                $mensaje_tipo = 'error';
            }
        } catch (PDOException $e) {
            $mensaje = "Error en el sistema: " . $e->getMessage();
            $mensaje_tipo = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - ISFT 177</title>
    <link rel="stylesheet" href="../css/login.css">
    <link rel="icon" href="../assets/img/Logo.png" type="image/png">
</head>

<body>
    <div class="login-container">
        <div class="logo-container">
            <img src="../Logo/logoisft177.png" alt="Logo ISFT 177" class="logo">
        </div>
        
        <h2>🔑 Recuperar Contraseña</h2>
        
        <p style="text-align: center; color: #666; margin-bottom: 20px;">
            Ingresa tu email y te enviaremos un nuevo código de activación.
        </p>

        <?php if (!empty($mensaje)): ?>
            <div style="padding: 12px; border-radius: 8px; margin-bottom: 20px; background: <?= $mensaje_tipo === 'success' ? '#d4edda' : '#f8d7da' ?>; color: <?= $mensaje_tipo === 'success' ? '#155724' : '#721c24' ?>; border: 1px solid <?= $mensaje_tipo === 'success' ? '#c3e6cb' : '#f5c6cb' ?>;">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <form action="" method="post">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required placeholder="tu@email.com">

            <button type="submit">Enviar Código</button>
            
            <p style="text-align: center; margin-top: 20px;">
                <a href="login.php">← Volver al Login</a>
            </p>
        </form>
    </div>
</body>
</html>
