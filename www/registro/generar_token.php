<?php
require_once '../phpMail/enviar_correo.php';
function generarNuevoToken($pdo, $correo) {
   echo " <script >
    alert('Generando se ha generado un nuevo token, revisa tu correo $correo');
    </script>";
    try {
        $token = bin2hex(random_bytes(4));
        $stmt = $pdo->prepare("UPDATE usuarios SET token = :token WHERE email = :correo");
        $stmt->execute([
            ':token' => $token,
           
            ':correo' => $correo
        ]);
        
        if (enviarTokenPorEmail($correo, $token)) {
            $_SESSION['user_email'] = $correo;
            $_SESSION['user_token'] = $token;
           
            exit;
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