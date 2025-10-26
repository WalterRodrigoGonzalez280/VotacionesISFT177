<?php
session_start();
require_once '../conexion/conexion.php';
require_once 'generar_token.php'; 


if (!isset($_SESSION['user_email']) || !isset($_SESSION['user_token'])) {
    header('Location: registro.php');
    exit;
}

$user_email = $_SESSION['user_email'];
$user_token = $_SESSION['user_token'];
$error_message = '';
$success_message = '';
$redirigir_login = false;


$stmt = $pdo->prepare("SELECT token, activo FROM usuarios WHERE email = ?");
$stmt->execute([$user_email]);
$user = $stmt->fetch();

 
if (!$user) {
    session_destroy();
    header('Location: registro.php');
    exit;
}


if ($user['activo']) {
    $success_message = '¡Tu cuenta ya está activada! Puedes iniciar sesión.';
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {

   
    if (isset($_POST['generar_token'])) {
        $resultado = generarNuevoToken($pdo, $user_email);
        if ($resultado) {
            $success_message = '✓ Token generado y enviado por correo exitosamente.';
        } else {
            $error_message = '✗ Error al generar o enviar el token.';
        }
       
        $stmt = $pdo->prepare("SELECT token, activo FROM usuarios WHERE email = ?");
        $stmt->execute([$user_email]);
        $user = $stmt->fetch();
    }
  
    else {
        $input_token = trim($_POST['token'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

       
        if (empty($input_token) || empty($password) || empty($confirm_password)) {
            $error_message = 'Todos los campos son requeridos.';
        } elseif (!preg_match('/^[0-9]{4}$/', $input_token)) {
            $error_message = 'El código debe ser de exactamente 4 dígitos numéricos.';
        } elseif ($password !== $confirm_password) {
            $error_message = 'Las contraseñas no coinciden.';
        } elseif (!preg_match('/^(?=.*[A-Z])(?=.*[0-9]).{6,}$/', $password)) {
            $error_message = 'La contraseña debe tener al menos 6 caracteres, una letra mayúscula y un número.';
        } elseif ($input_token !== $user['token']) {
            $error_message = 'Código incorrecto. Verifica que hayas ingresado correctamente.';
        } else {
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("UPDATE usuarios SET password = ?, activo = 1,  token = NULL WHERE email = ?");
                $stmt->execute([$hashed_password, $user_email]);

                $success_message = '¡Cuenta activada exitosamente! Redirigiendo al login...';
                $redirigir_login = true;
                
                unset($_SESSION['user_email']);
                unset($_SESSION['user_token']);
            } catch (PDOException $e) {
                $error_message = 'Error al activar la cuenta. Inténtalo de nuevo.';
                error_log("Error en activación: " . $e->getMessage());
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
    <title>Activación de Cuenta</title>
    <link rel="stylesheet" href="../css/activar.css">
    <link rel="icon" href="../assets/img/Logo.png" type="image/png">
</head>
<div class="form-container">
    <h1>Activación de Cuenta</h1>
    <div id="mensajes">
        <?php if ($error_message): ?>
            <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
    </div>
    <!-- Spinner -->
    <div id="loading" class="loading" style="display:none;">
        <div class="spinner"></div>
        <p>Procesando...</p>
    </div>

    <?php if (!$user['is_active']): ?>
        <form method="POST" id="form-activacion">
            <div class="form-group">
                <label for="token">Ingresa el código de 4 dígitos:</label>
                <input type="text" id="token" name="token" required placeholder="Ejemplo: 1234"
                    pattern="[0-9]{4}" maxlength="4" minlength="4" 
                    title="Ingresa un código de 4 dígitos numéricos"
                    value="<?php echo htmlspecialchars($_POST['token'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="password">Contraseña:</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" required
                        pattern="(?=.*[A-Z])(?=.*\d).{6,}"
                        title="La contraseña debe tener al menos 6 caracteres, una letra mayúscula y un número">
                    <span class="toggle-password" data-target="password">👁️‍🗨️</span>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirmar contraseña:</label>
                <div class="password-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password" required
                        placeholder="Confirma tu contraseña" minlength="6">
                    <span class="toggle-password" data-target="confirm_password">👁️‍🗨️</span>
                </div>
            </div>

            <button type="submit" class="btn btn-success" id="submitBtn">Activar Cuenta</button>
        </form>

       
        <form method="POST" action="" style="margin-top: 15px;">
            <button type="submit" name="generar_token" value="1" class="btn btn-secondary"
                onclick="return confirm('¿Estás seguro de que quieres generar un nuevo token? El actual será invalidado.')">
                Generar Nuevo Token
            </button>
        </form>
    <?php endif; ?>
    <div class="register-link" style="margin-top: 20px; display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
        <p><a href="registro.php">← Volver al registro</a></p>
        <p><a href="../login/login.php">→ Iniciar sesión</a></p>
    </div>
</div>
<script>
    <?php if ($redirigir_login): ?>
        // Redirigir al login después de 2 segundos
        setTimeout(function() {
            window.location.href = '../login/login.php';
        }, 2000);
    <?php endif; ?>
</script>
<script src="../js/ocultar_contraseña.js"></script>
<script src="../js/activar_correo.js"></script>

<footer class="main-footer">
    <p>Desarrollado por Alumnos de 2do de Sistemas - ISFT N° 177</p>
</footer>
</body>

</html>