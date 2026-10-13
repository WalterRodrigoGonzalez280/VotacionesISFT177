<?php
session_start();
require_once __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../core/generar_token.php';

$mensaje = '';
$tipo = 'error';
$destino = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    if ($email === '') {
        $mensaje = 'Ingresá el correo con el que te registraste.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT id, activo, token_ultimo_envio FROM usuarios WHERE email = ?');
            $stmt->execute([$email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                $mensaje = 'No existe una cuenta asociada a ese correo.';
            } else {
                // Throttle de reenvio: si el ultimo codigo salio hace menos
                // de 60 segundos no se manda otro. El mensaje no revela nada
                // nuevo sobre la existencia del correo: la rama de arriba ya
                // distingue el caso inexistente.
                $ultimo = $usuario['token_ultimo_envio'] !== null
                    ? strtotime($usuario['token_ultimo_envio'])
                    : false;

                if ($ultimo !== false && (time() - $ultimo) < 60) {
                    $mensaje = 'Esperá un minuto antes de pedir otro código.';
                } else {
                    // `=== true` es obligatorio: el string de error es truthy.
                    $resultado = generarNuevoToken($pdo, $email);

                    if ($resultado === true) {
                        $_SESSION['user_email'] = $email;

                        $stmt = $pdo->prepare('SELECT token FROM usuarios WHERE email = ?');
                        $stmt->execute([$email]);
                        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($fila) {
                            $_SESSION['user_token'] = $fila['token'];
                        }

                        $tipo = 'ok';
                        $mensaje = 'Te enviamos un código de 6 dígitos. Te llevamos a la pantalla de activación.';
                        $destino = 'activar.php';
                    } else {
                        $mensaje = is_string($resultado) ? $resultado : 'No pudimos enviar el correo.';
                    }
                }
            }
        } catch (PDOException $e) {
            error_log('Recuperar: ' . $e->getMessage());
            $mensaje = 'No pudimos procesar la solicitud en este momento.';
        }
    }
}

$titulo = 'Recuperar contraseña';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo) ?> | ISFT 177</title>
    <!-- Titulares en Cinzel (capitular romana), cuerpo en Lato.
         Las tipografias van en un parcial, con la precarga de los .woff2 y la
         hoja de @font-face: ver includes/fonts.php. -->
    <?php include __DIR__ . '/../includes/theme-inline.php'; ?>
    <?php include __DIR__ . '/../includes/fonts.php'; ?>
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
<div class="auth">

    <aside class="auth__brand">
        <?php /* Mismo srcset que en login.php, y por el mismo motivo: el webp
                  de 480 pesa mas que el PNG y bloqueaba el srcset. Los
                  breakpoints del sizes son los de auth.css. */ ?>
        <img src="../assets/Logo/logoisft177-480.png" width="480" height="480" alt="Logo del ISFT 177"
             class="auth__logo"
             sizes="(min-width: 64rem) 176px, (min-width: 40rem) 136px, 112px"
             srcset="../assets/Logo/logoisft177-96.png 96w,
                     ../assets/Logo/logoisft177-160.png 160w,
                     ../assets/Logo/logoisft177-192.png 192w,
                     ../assets/Logo/logoisft177-256.png 256w,
                     ../assets/Logo/logoisft177-480.png 480w">
        <p class="auth__institution">ISFT N° 177</p>
        <p class="auth__tagline">
            Exposición de proyectos de Prácticas Profesionalizantes.
            Cada persona emite un voto por año.
        </p>
    </aside>

    <main class="auth__main" id="contenido">
        <div class="auth__card">
            <h1 class="auth__title"><?= htmlspecialchars($titulo) ?></h1>
            <p class="auth__lead">
                Ingresá tu correo y te enviamos un código para que elijas una contraseña nueva.
            </p>

            <?php if ($mensaje !== ''): ?>
                <div class="alert alert--<?= $tipo === 'ok' ? 'ok' : 'error' ?>" role="status">
                    <svg class="icon icon--sm" aria-hidden="true"><use href="#i-<?= $tipo === 'ok' ? 'check' : 'alerta' ?>"></use></svg>
                    <span><?= htmlspecialchars($mensaje) ?></span>
                </div>
            <?php endif; ?>

            <form class="auth__form" method="post" action="" novalidate data-validate="recuperar">
                <div class="field">
                    <label class="field__label" for="email">Correo electrónico</label>
                    <input
                        class="field__input"
                        type="email"
                        id="email"
                        name="email"
                        autocomplete="email"
                        inputmode="email"
                        placeholder="nombre@correo.com"
                        required>
                </div>

                <button class="btn btn--primary btn--block" type="submit">
                    Enviar código
                </button>
            </form>

            <p class="auth__meta">
                <a class="link" href="login.php">Volver al inicio de sesión</a>
            </p>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/site-footer.php'; ?>

</div>

<script type="module" src="../assets/js/validate.js"></script>

<?php if ($destino !== ''): ?>
<script>
    // Redireccion tras enviar el correo. Va aca y no en un listener porque es
    // una sola vez, no un evento repetible.
    setTimeout(function () {
        window.location.href = <?= json_encode($destino) ?>;
    }, 2500);
</script>
<?php endif; ?>

<!-- Boton de tema: flotante, asi no hay que insertarlo en el markup
     diferente de cada pagina. -->
<div class="theme-toggle">
    <button class="theme-toggle__btn" type="button"
            data-theme-toggle aria-pressed="false" aria-label="Cambiar a tema oscuro">
        <svg class="icon" aria-hidden="true"><use href="#i-luna"></use></svg>
    </button>
</div>
<script type="module" src="../assets/js/theme.js"></script>

<?php include __DIR__ . '/../includes/iconos.php'; ?>
</body>
</html>
