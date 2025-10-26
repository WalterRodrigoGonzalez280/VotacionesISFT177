<?php
require_once '../phpMail/enviar_correo.php';
function generarNuevoToken($pdo, $correo) {
    try {
        $token = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("UPDATE usuarios SET token = :token WHERE email = :correo");
        $stmt->execute([
            ':token' => $token,
            ':correo' => $correo
        ]);
        
        // Intentar enviar el token por email
        $resultado = enviarTokenPorEmail($correo, $token);
        
        if ($resultado === true) {
            // Guardar en sesión si está disponible
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['user_email'] = $correo;
                $_SESSION['user_token'] = $token;
            }
            return true;
        } else {
            error_log("Error al enviar el token por correo a: " . $correo);
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Error al generar token: " . $e->getMessage());
        return false;
    }
}

function validarToken($pdo, $correo, $token) {
    try {
        $stmt = $pdo->prepare("SELECT token FROM usuarios WHERE email = :correo AND token = :token LIMIT 1");
        $stmt->execute([
            ':correo' => $correo,
            ':token' => $token
        ]);
        
        return $stmt->rowCount() > 0;
        
    } catch (Exception $e) {
        error_log("Error al validar token: " . $e->getMessage());
        return false;
    }
}
?>