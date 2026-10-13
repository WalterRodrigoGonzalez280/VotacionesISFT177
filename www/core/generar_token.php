<?php
require_once __DIR__ . '/../phpMail/enviar_correo.php';

/**
 * Genera un codigo de activacion nuevo y lo envia por correo.
 *
 * Contrato: devuelve `true` si se guardo Y se envio, o un STRING con el
 * motivo del fallo. El que llama TIENE que comparar con `=== true`:
 * un string de error es truthy en PHP.
 *
 * ORDEN IMPORTANTE: se envia primero y recien despues se persiste el token.
 * Al reves (persistir y despues enviar) un fallo de SMTP invalidaba el token
 * anterior sin que nadie recibiera nada: quien estaba a mitad de una
 * activacion perdia su codigo por un error que no era suyo.
 */
function generarNuevoToken($pdo, $correo)
{
    $token = generarCodigoToken();

    // 1) enviar primero
    $envio = enviarTokenPorEmail($correo, $token);

    if ($envio !== true) {
        error_log("No se pudo enviar el token a {$correo}: {$envio}");
        return is_string($envio) ? $envio : 'No se pudo enviar el correo.';
    }

    // 2) solo si salio bien, guardar el token nuevo con su vencimiento
    //    (15 minutos), sin intentos y con la marca de reenvio para el
    //    throttle de 60 segundos.
    try {
        $stmt = $pdo->prepare(
            "UPDATE usuarios
                SET token = ?,
                    token_expira_en = now() + interval '15 minutes',
                    token_intentos = 0,
                    token_ultimo_envio = now()
              WHERE email = ?"
        );
        $stmt->execute([$token, $correo]);

        if ($stmt->rowCount() === 0) {
            return 'No encontramos una cuenta con ese correo.';
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['user_email'] = $correo;
            $_SESSION['user_token'] = $token;
        }

        return true;
    } catch (PDOException $e) {
        error_log("Token: no se pudo guardar para {$correo}: " . $e->getMessage());
        return 'El correo se envió pero no pudimos registrar el código. Intentá de nuevo.';
    }
}

/**
 * El codigo de activacion: 6 digitos con relleno de ceros.
 *
 * random_int es CSPRNG. rand()/mt_rand() son predecibles, y con solo 10^6
 * combinaciones alcanza con observar unos cuantos numeros para predecir el
 * siguiente. Es una funcion aparte para que registro_validar.php use el
 * mismo generador sin repetir la logica.
 */
function generarCodigoToken()
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Estado de la configuracion de SMTP, para diagnostico.
 * @return array { listo: bool, faltantes: string[] }
 */
function diagnosticoSmtp($pdo)
{
    try {
        $stmt = $pdo->prepare('SELECT clave, valor FROM email_config WHERE activo = 1');
        $stmt->execute();

        $config = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $config[$fila['clave']] = $fila['valor'];
        }

        $faltantes = [];
        foreach (['MAIL_USERNAME', 'MAIL_PASSWORD'] as $requerida) {
            if (empty($config[$requerida])) {
                $faltantes[] = $requerida;
            }
        }

        return ['listo' => empty($faltantes), 'faltantes' => $faltantes];
    } catch (PDOException $e) {
        return ['listo' => false, 'faltantes' => ['(no se pudo leer email_config)']];
    }
}
