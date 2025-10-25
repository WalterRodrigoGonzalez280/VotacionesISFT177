<?php
require_once 'class.smtp.php';
require_once 'class.pop3.php';
require_once 'class.phpmailer.php';

function enviarTokenPorEmail($correo, $token) {
    global $pdo; 
    
    try {
      
        $stmt = $pdo->prepare("SELECT clave, valor FROM email_config WHERE activo = 1");
        $stmt->execute();
        $resultados = $stmt->fetchAll();

        $config = [];
        foreach ($resultados as $fila) {
            $config[$fila['clave']] = $fila['valor'];
        }
        
       
        if (empty($config['MAIL_USERNAME']) || empty($config['MAIL_PASSWORD'])) {
            return "Error: Configuración de email incompleta";
        }
    } catch (PDOException $e) {
        error_log("Error cargando configuración email: " . $e->getMessage());
        return "Error cargando configuración de email";
    }

    $mail = new PHPMailer(true);

    try{
        $mail->isSMTP();
        $mail->Host = $config['MAIL_HOST'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $config['MAIL_USERNAME'];
        $mail->Password = $config['MAIL_PASSWORD'];
        $mail->SMTPSecure = $config['MAIL_ENCRYPTION'] ?? 'tls';
        $mail->Port = $config['MAIL_PORT'] ?? 587;

        $mail->CharSet = 'UTF-8';

   
        $mail->setFrom($config['MAIL_FROM_EMAIL'] ?? $config['MAIL_USERNAME'], 
                      'isft177 Votacion Proyectos 2025');
        $mail->addAddress($correo);
       
        $mail->isHTML(true);
        $mail->Subject = 'Activación de cuenta votacion proyectos 2025';
        $mail->addEmbeddedImage('../assets/img/Logo.png', 'logoimg');
       
        $enlace_activacion = $token;

        $mail->Body = "
           <body style='background-color: #000000ff; color: #FFFFFF; font-family: Arial, sans-serif;'>
        <div style='text-align: center; padding: 20px;'>
            <img src='cid:logoimg'  style='width: 160px;'>
            <h2 style='color: #0077ffff;'>¡Hola, $correo!</h2>
            <p>Si has solicitado activar, usa el código que encontrarás a continuación.</p>
            <div style='background-color: #007BFF; color: #FFFFFF; display: inline-block; padding: 10px 20px; border-radius: 5px; font-size: 24px; margin: 20px 0;'>
         $token
            </div>



            <p>Si no solicitaste esta cuenta, puedes ignorar este correo.</p>
        ";
       
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error enviando email: " . $e->getMessage());
        return "Error del servidor de correo: {$mail->ErrorInfo}";
    }
}
