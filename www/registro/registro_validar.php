<?php
session_start();
require_once '../conexion/conexion.php';
require_once '../phpMail/enviar_correo.php';

$usuario = $_POST['usuario'] ?? '';
$correo = $_POST['correo'] ?? '';

$errores = [];
if (empty($usuario) || !preg_match('/^[a-zA-Z]{3,20}$/', $usuario)) {
    $errores[] = "El nombre de usuario debe tener entre 3 y 20 caracteres y solo puede contener letras";
}


if (empty($correo) || !preg_match('/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i', $correo)) {
    $errores[] = "El correo electrónico no es válido.";
}

if (!empty($errores)) {
    foreach ($errores as $error) {
         echo "<script>alert('$error');</script>"; 
    }
   echo "<script> window.location.href = 'registro.php';</script>"; 
    exit;
} else {
    try {
    
        $query = "SELECT email, activo FROM usuarios WHERE email = :correo LIMIT 1";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':correo', $correo);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $usuario_existente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            
            if ($usuario_existente['activo']== 0) {
        
                $token = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                 if ($usuario_existente)
                
                if(enviarTokenPorEmail($correo, $token)) {
                  
                    $update_query = "UPDATE usuarios SET token= :token WHERE email = :correo";
                    $update_stmt = $pdo->prepare($update_query);
                    $update_stmt->bindParam(':token', $token);
                   
                    $update_stmt->bindParam(':correo', $correo);
                    $update_stmt->execute();
                    
                    $_SESSION['user_email'] = $correo;
                    $_SESSION['user_token'] = $token;
                     echo "<script>alert('El correo ya está registrado pero activo. Utiliza el token que te enviaremos.'); window.location.href = '../registro/activar.php';</script>" ; 
                 
                    exit;
                } else {
                    echo "<script>alert('Error al enviar el correo de activación. Intenta nuevamente.');</script>"; //
                    exit;
                }
            } else {
                 echo "<script>alert('El correo ya está registrado y activo. Por favor, inicia sesión.'); window.location.href = '../login/login.php';</script>" ; //cambio
                echo "script window.location.href = '../login/login.php';</script>"; //cambio
            }
        } else {
        
            $token = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
          
            if(enviarTokenPorEmail($correo, $token)) {
               
                $insert_query = "INSERT INTO usuarios (nombre, email, token, activo) VALUES (:usuario, :correo, :token,  0)";
                $insert_stmt = $pdo->prepare($insert_query);
                $insert_stmt->bindParam(':usuario', $usuario);
                $insert_stmt->bindParam(':correo', $correo);
                $insert_stmt->bindParam(':token', $token);
                $insert_stmt->execute();
                $_SESSION['user_email'] = $correo;
                $_SESSION['user_token'] = $token;
                header("Location: activar.php");
                exit;
            } else {
                echo "<script>alert('Error al enviar el correo de activación. Intenta nuevamente.');</script>"; //cambio

                exit;
            }
        }
    } catch(Exception $e) {
        echo "<script>alert('Error en la base de datos: " . $e->getMessage() . "');</script>"; //cambio
        exit;
    }
}
?>
