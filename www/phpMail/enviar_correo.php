<?php
require_once 'class.smtp.php';
require_once 'class.pop3.php';
require_once 'class.phpmailer.php';

/**
 * Envia el codigo de activacion por correo.
 *
 * Contrato: devuelve `true` si se envio, o un STRING con el motivo del fallo.
 * Nunca devuelve otro tipo de valor.
 *
 * Importante: el string de error es truthy en PHP, asi que el que llama TIENE
 * que comparar con `=== true`. Antes se usaba `if (enviarTokenPorEmail(...))`
 * y eso daba por bueno un envio fallido.
 */
function enviarTokenPorEmail($correo, $token) {
    global $pdo;

    try {
        $stmt = $pdo->prepare('SELECT clave, valor FROM email_config WHERE activo = 1');
        $stmt->execute();

        $config = [];
        foreach ($stmt->fetchAll() as $fila) {
            $config[$fila['clave']] = $fila['valor'];
        }

        if (empty($config['MAIL_USERNAME']) || empty($config['MAIL_PASSWORD'])) {
            return 'No se pudo enviar el correo: falta configurar el SMTP (email_config).';
        }
    } catch (PDOException $e) {
        error_log('Email: cargando configuración: ' . $e->getMessage());
        return 'No se pudo leer la configuración de correo.';
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $config['MAIL_HOST'] ?? 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $config['MAIL_USERNAME'];
        $mail->Password = $config['MAIL_PASSWORD'];
        $mail->SMTPSecure = $config['MAIL_ENCRYPTION'] ?? 'tls';
        $mail->Port = (int)($config['MAIL_PORT'] ?? 587);

        $mail->CharSet = 'UTF-8';
        $mail->setFrom(
            $config['MAIL_FROM_EMAIL'] ?? $config['MAIL_USERNAME'],
            'ISFT 177 - Votacion de Proyectos'
        );
        $mail->addAddress($correo);

        $mail->isHTML(true);
        $mail->Subject = 'Activacion de cuenta - Votacion de Proyectos ISFT 177';
        $mail->addEmbeddedImage(__DIR__ . '/../Logo/logoisft177-480.png', 'logoimg');

        $mail->Body = "
        <body style='margin:0;background:#FDFBF4;color:#2B2823;
                     font-family:Arial,Helvetica,sans-serif'>
          <div style='max-width:520px;margin:0 auto;padding:32px 20px'>
            <div style='text-align:center;padding-bottom:16px'>
              <img src='cid:logoimg' alt='ISFT 177' style='width:120px;height:auto'>
            </div>

            <h1 style='font-size:20px;margin:0 0 12px'>Hola</h1>
            <p style='font-size:15px;line-height:1.6;margin:0 0 20px;color:#6B6459'>
              Ingres&aacute; este c&oacute;digo en la p&aacute;gina de activaci&oacute;n
              para confirmar tu cuenta y poder votar.
            </p>

            <div style='text-align:center;margin:0 0 24px'>
              <span style='display:inline-block;background:#D8C03C;color:#2B2823;
                           border:1px solid #A88A1E;border-radius:12px;
                           padding:14px 28px;font-size:30px;font-weight:bold;
                           letter-spacing:10px;text-indent:10px'>
                {$token}
              </span>
            </div>

            <p style='font-size:13px;line-height:1.6;color:#6B6459;margin:0 0 8px'>
              El c&oacute;digo no vence, pero si lo perdiste pod&eacute;s pedir otro
              desde la misma pantalla.
            </p>
            <p style='font-size:13px;line-height:1.6;color:#9A9184;margin:0'>
              Si no pediste esto, ignor&aacute; este mensaje.
            </p>
          </div>
        </body>";

        $mail->AltBody = "Tu codigo de activacion es: {$token}";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Email: enviando a ' . $correo . ': ' . $e->getMessage());
        return 'No pudimos enviar el correo. Revisá la configuración de SMTP.';
    }
}
