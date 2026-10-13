<?php
require_once __DIR__ . '/class.smtp.php';
require_once __DIR__ . '/class.pop3.php';
require_once __DIR__ . '/class.phpmailer.php';

/**
 * Unico mensaje de fallo que ve la persona que se registra.
 *
 * El motivo REAL va siempre al error_log (y al diagnostico del admin via
 * diagnosticoSmtp). Antes el usuario veia "falta configurar el SMTP
 * (email_config)", que ademas de incomprendible le expone el nombre de la
 * tabla y le pide arreglar algo que es del servidor y no suyo: no tiene
 * forma de hacer nada con esa informacion. Un mensaje generico le deja claro
 * que puede reintentar, que es lo unico que esta en sus manos.
 */
const MAIL_FALLA = 'El servicio de correo no está disponible en este momento. Intentá de nuevo más tarde.';

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
            // El detalle va al log, no a la pantalla.
            error_log('Email: falta MAIL_USERNAME/MAIL_PASSWORD en email_config.');
            return MAIL_FALLA;
        }
    } catch (PDOException $e) {
        error_log('Email: cargando configuración: ' . $e->getMessage());
        return MAIL_FALLA;
    }

    $mail = new PHPMailer(true);

    // El logo va embebido (cid:), no con URL: si el cliente bloquea las
    // imagenes remotas se veria roto. is_file() antes de agregarlo porque
    // addEmbeddedImage() LANZA si no encuentra el archivo, y con excepciones
    // activadas eso tiraba abajo el envio entero por una imagen que no esta.
    //
    // La variante de 256 y no la de 480: en el correo se muestra a 112px, y
    // 256 cubre DPR 2. Pesa 9,4 KB contra 23,3 KB, en cada activacion.
    $rutaLogo = __DIR__ . '/../assets/Logo/logoisft177-256.png';
    $conLogo = is_file($rutaLogo);

    $contenido = plantillaActivacion(
        $token,
        $config['SITE_URL'] ?? '',
        $conLogo
    );

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

        if ($conLogo) {
            $mail->addEmbeddedImage($rutaLogo, 'logoimg');
        }

        $mail->Body    = $contenido['html'];
        $mail->AltBody = $contenido['texto'];

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Email: enviando a ' . $correo . ': ' . $e->getMessage());
        return MAIL_FALLA;
    }
}

/**
 * Paleta del correo: los mismos valores que www/css/base.css declara en el
 * bloque de tema claro (seccion de tokens), escritos en hexadecimal.
 *
 * Por que se repiten aca y no se leen del CSS: los clientes de correo no
 * resuelven variables CSS ni cargan hojas de estilo, asi que el HTML va con
 * estilos inline y el color literal. La correspondencia con el token queda
 * escrita al lado de cada valor para que actualizar uno obligue a ver el otro.
 *
 * Solo hubo que aplastar los tres que usan alfa: `rgb(... / 0.20)` sobre
 * blanco equivale al hex de acá. Sobre el fondo de pagina darian un tono
 * apenas distinto, y el borde se ve contra la tarjeta blanca, no contra el
 * fondo.
 *
 * El tema oscuro no se replica: el correo no puede consultar la preferencia
 * guardada en el navegador, y ademas la marca dorada sobre carb tiene menos
 * contraste para texto pequeno. Va siempre en claro, y las etiquetas
 * `color-scheme` del <head> evitan que el cliente lo invierta solo.
 */
function correoPaleta() {
    return [
        'bg'         => '#faf8f0', // --c-bg          fondo de pagina, "hueso"
        'surface'    => '#ffffff', // --c-surface     la tarjeta
        'line'       => '#e8e0d3', // --c-line        rgb(138 102 34 / .20) sobre blanco
        'lineFuerte' => '#cebfa2', // --c-line-strong rgb(138 102 34 / .42) sobre blanco
        'ink'        => '#1a1a1a', // --c-white       texto fuerte
        'muted'      => '#5c574d', // --c-muted       texto secundario, 6,75:1
        'gold'       => '#d4a73e', // --c-gold        dorado de superficie
        'goldTexto'  => '#8a6622', // --c-gold-text   dorado como texto, 4,93:1
        'onGold'     => '#0d0d0d', // --c-on-gold     texto sobre dorado, 8,70:1
        'warnBg'     => '#fbf3dd', // --c-warn-bg
        'warnLine'   => '#e3cf9a', // --c-warn-line
        'warnInk'    => '#7c5b1c', // --c-warn-ink
    ];
}

/**
 * Arma el cuerpo del correo: la parte HTML y la version en texto plano.
 *
 * Devuelve `['html' => ..., 'texto' => ...]`.
 *
 * @param string $token     Codigo de 4 digitos.
 * @param string $sitioUrl  Origen del sitio (`email_config.SITE_URL`). Si
 *                          viene vacio el correo sale sin ningun link: es
 *                          opcional a proposito, asi el envio no depende de
 *                          que alguien haya configurado esa clave.
 * @param bool   $conLogo  Si hay que dibujar el <img> embebido.
 */
function plantillaActivacion($token, $sitioUrl, $conLogo) {
    $c = correoPaleta();

    // Alias cortos: en el HTML se leen mejor que $c['lineFuerte'] repetido.
    $bg = $c['bg'];
    $surface = $c['surface'];
    $line = $c['line'];
    $ink = $c['ink'];
    $muted = $c['muted'];
    $gold = $c['gold'];
    $goldTexto = $c['goldTexto'];
    $onGold = $c['onGold'];
    $warnBg = $c['warnBg'];
    $warnLine = $c['warnLine'];
    $warnInk = $c['warnInk'];

    // El token sale de un rand, pero el HTML se arma con interpolacion: se
    // escapa igual. Un entero no necesita escape y un XSS no.
    // Para la version en texto plano se usa el crudo: alla no hay HTML que
    // escapar y una entidad ahi se veria literal.
    $tokenTexto = (string)$token;
    $token = htmlspecialchars($tokenTexto, ENT_QUOTES, 'UTF-8');

    // Solo http(s): sin esto, un SITE_URL con "javascript:" en la tabla
    // config convertia el boton en un vector. Y con barra final de mas, el
    // link al sitio quedaba con dos barras.
    $sitioUrl = rtrim(trim((string)$sitioUrl), '/');
    if (!preg_match('#^https?://#i', $sitioUrl)) {
        $sitioUrl = '';
    }

    $urlActivar = $sitioUrl !== '' ? $sitioUrl . '/auth/activar.php' : '';

    $logo = $conLogo
        ? '<img src="cid:logoimg" width="112" alt="ISFT 177"'
          . ' style="display:block;width:112px;max-width:112px;height:auto;'
          . 'border:0;outline:none;text-decoration:none;margin:0 auto;">'
        : '';

    // Boton "a prueba de clientes": una tabla con el <a> adentro. Un <button>
    // o un div con onclick se pierde en Outlook, y el padding del <a> es lo
    // que le da el alto. El color va por bgcolor y por style, porque hay
    // clientes que leen solo uno de los dos.
    $boton = '';
    if ($urlActivar !== '') {
        $url = htmlspecialchars($urlActivar, ENT_QUOTES, 'UTF-8');
        $boton = <<<HTML
              <table role="presentation" cellpadding="0" cellspacing="0" border="0"
                     style="margin:0 auto;border-collapse:separate;">
                <tr>
                  <td align="center" bgcolor="{$gold}"
                      style="background-color:{$gold};border:1px solid {$goldTexto};
                             border-radius:999px;box-shadow:0 1px 2px rgba(60,50,20,0.08);
                             mso-padding-alt:14px 32px;">
                    <a href="{$url}" target="_blank"
                       style="display:inline-block;padding:14px 32px;
                              font-family:'Lato',-apple-system,'Segoe UI',Roboto,Arial,sans-serif;
                              font-size:15px;font-weight:700;line-height:1.2;
                              color:{$onGold};text-decoration:none;">
                      Activar mi cuenta
                    </a>
                  </td>
                </tr>
              </table>
              <!-- URL suelta abajo del boton: si el cliente no arma el boton,
                   el link sigue siendo legible y copiable. -->
              <p style="margin:10px 0 0;font-size:12px;line-height:1.5;
                        text-align:center;word-break:break-all;color:{$muted};">
                {$url}
              </p>
HTML;
    }

    $html = <<<HTML
<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml"
      xmlns:v="urn:schemas-microsoft-com:vml"
      xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="x-apple-disable-message-reformatting">
<!-- color-scheme: el correo es siempre claro. Sin esto, Apple Mail y Gmail
     lo dan vuelta a oscuro y los dorados quedan ilegibles. -->
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>Activá tu cuenta &middot; Votación de Proyectos</title>
<!--[if mso]>
<noscript><xml><o:OfficeDocumentSettings>
  <o:PixelsPerInch>96</o:PixelsPerInch><o:AllowPNG/>
</o:OfficeDocumentSettings></xml></noscript>
<![endif]-->
<style>
  /* Unica hoja de estilo del correo, y solo para lo que inline no puede
     hacer: el ajuste en pantallas angostas. Lo que importa va inline porque
     hay clientes (y el modo sin estilos de Outlook) que ignoran esta
     etiqueta entera. */
  @media only screen and (max-width: 600px) {
    .wrap { width: 100% !important; }
    .pad  { padding-left: 18px !important; padding-right: 18px !important; }
    .code { font-size: 30px !important; letter-spacing: 0.25em !important; }
  }
</style>
</head>
<body style="margin:0;padding:0;background-color:{$bg};color:{$ink};
             font-family:'Lato',-apple-system,'Segoe UI',Roboto,Arial,sans-serif;
             -webkit-text-size-adjust:100%;">

<!-- Texto de vista previa: lo que se lee arriba del asunto en la bandeja.
     El div invisible es el placeholder; los &#847; de abajo son un corte de
     texto invisible que algunos clientes necesitan para que no se asome el
     cuerpo del correo en el preview. -->
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;
            line-height:1px;color:{$bg};opacity:0;">
  Tu código de activación es {$token}. Ingresalo en la página de activación
  para confirmar tu cuenta y poder votar.
  &#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
       style="width:100%;border-collapse:collapse;background-color:{$bg};">
  <tr>
    <td align="center" style="padding:32px 16px;">

      <table role="presentation" class="wrap" width="560" cellpadding="0" cellspacing="0"
             border="0" style="width:100%;max-width:560px;border-collapse:collapse;">

        <!-- Marca, como el panel .auth__brand de la pagina -->
        <tr>
          <td align="center" style="padding-bottom:22px;">
            {$logo}
            <p style="margin:12px 0 0;font-family:Georgia,'Times New Roman',serif;
                      font-size:19px;line-height:1.2;font-weight:bold;
                      color:{$goldTexto};">ISFT N&deg; 177</p>
            <p style="margin:4px 0 0;font-size:13px;line-height:1.5;color:{$muted};">
              Votaci&oacute;n de Proyectos &middot; Exposici&oacute;n 2025
            </p>
          </td>
        </tr>

        <!-- Tarjeta, como .card / .auth__card -->
        <tr>
          <td class="pad" bgcolor="{$surface}"
              style="background-color:{$surface};border:1px solid {$line};
                     border-radius:12px;padding:32px;">

            <h1 style="margin:0 0 10px;font-family:Georgia,'Times New Roman',serif;
                       font-size:23px;line-height:1.15;font-weight:bold;
                       color:{$goldTexto};">Activ&aacute; tu cuenta</h1>

            <p style="margin:0 0 24px;font-size:15px;line-height:1.65;color:{$muted};">
              Hola. Ingres&aacute; este c&oacute;digo en la p&aacute;gina de
              activaci&oacute;n para confirmar tu cuenta y poder votar.
            </p>

            <table role="presentation" cellpadding="0" cellspacing="0" border="0"
                   style="margin:0 auto;border-collapse:separate;">
              <tr>
                <td align="center" bgcolor="{$gold}"
                    style="background-color:{$gold};border:1px solid {$goldTexto};
                           border-radius:14px;padding:18px 30px;">
                  <span class="code"
                        style="font-family:Consolas,'SF Mono',Menlo,Courier,monospace;
                               font-size:34px;line-height:1;font-weight:bold;
                               letter-spacing:0.35em;text-indent:0.35em;
                               font-variant-numeric:tabular-nums;
                               color:{$onGold};">{$token}</span>
                </td>
              </tr>
            </table>

            <p style="margin:14px 0 0;font-size:12px;line-height:1.5;color:{$muted};
                      text-align:center;">El c&oacute;digo no vence. Si lo perdiste,
                      ped&iacute; otro desde la misma pantalla.</p>

            {$boton}

            <!-- Aviso, con los tokens de --c-warn-* igual que .dialog__warn -->
            <div style="margin:24px 0 0;padding:12px 16px;background-color:{$warnBg};
                        border:1px solid {$warnLine};border-left:3px solid {$warnLine};
                        border-radius:6px;font-size:13px;line-height:1.6;
                        color:{$warnInk};">
              Si no pediste esto, ignor&aacute; este mensaje. La cuenta no queda
              activa hasta que se ingrese el c&oacute;digo.
            </div>

          </td>
        </tr>

        <!-- Pie -->
        <tr>
          <td class="pad" style="padding:20px 8px 0;font-size:12px;line-height:1.6;
                                 text-align:center;color:{$muted};">
            Recibiste este correo porque pediste crear una cuenta en
            Votaci&oacute;n de Proyectos.<br>
            ISFT N&deg; 177 &middot; Exposici&oacute;n de proyectos de
            Pr&aacute;cticas Profesionalizantes &middot;
            T&eacute;cnico en An&aacute;lisis de Sistemas
          </td>
        </tr>

      </table>

    </td>
  </tr>
</table>
</body>
</html>
HTML;

    $texto = "ISFT N° 177 - Votación de Proyectos\n\n"
        . "Tu código de activación es: {$tokenTexto}\n\n"
        . "Ingresalo en la página de activación para confirmar tu cuenta y "
        . "poder votar.";

    if ($urlActivar !== '') {
        $texto .= "\n\n{$urlActivar}";
    }

    $texto .= "\n\nEl código no vence. Si lo perdiste, pedí otro desde la misma "
        . "pantalla.\nSi no pediste esto, ignorá este mensaje: la cuenta no "
        . "queda activa hasta que ingreses el código.\n";

    return ['html' => $html, 'texto' => $texto];
}
