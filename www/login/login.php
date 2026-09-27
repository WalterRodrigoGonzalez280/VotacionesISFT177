<?php
session_start();
require_once __DIR__ . '/../conexion/conexion.php';
require_once __DIR__ . '/funciones_login.php';

$mensaje = '';
$tipo_mensaje = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $mensaje = 'Completá el correo y la contraseña.';
    } else {
        try {
            $usuario = obtenerUsuario($pdo, $email);

            if (!$usuario) {
                // Mismo mensaje para usuario inexistente y contraseña incorrecta:
                // no revela qué correos están registrados.
                $mensaje = 'Correo o contraseña incorrectos.';
            } elseif ($usuario['activo'] != 1) {
                $_SESSION['user_email'] = $email;
                $_SESSION['user_token'] = $usuario['token'];
                header('Location: ../registro/activar.php');
                exit;
            } elseif (password_verify($password, $usuario['password'])) {
                session_regenerate_id(true);
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_email'] = $usuario['email'];
                $_SESSION['usuario_nombre'] = $usuario['nombre'];
                $_SESSION['logueado'] = true;
                $_SESSION['es_admin'] = ((int)$usuario['rol_id'] === 1);

                header('Location: ../votacion.php');
                exit;
            } else {
                $mensaje = 'Correo o contraseña incorrectos.';
            }
        } catch (PDOException $e) {
            error_log('Login: ' . $e->getMessage());
            $mensaje = 'No pudimos iniciar sesión en este momento. Intentá de nuevo.';
        }
    }
}

$titulo = 'Iniciar sesión';
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
    <link rel="stylesheet" href="../css/base.css">
    <link rel="stylesheet" href="../css/auth.css">
</head>
<body>
<div class="auth">

    <aside class="auth__brand">
        <picture>
            <source srcset="../Logo/logoisft177.webp" type="image/webp">
            <img src="../Logo/logoisft177-480.png" width="480" height="480" alt="Logo del ISFT 177" class="auth__logo">
        </picture>
        <p class="auth__institution">ISFT N° 177</p>
        <p class="auth__tagline">
            Exposición de proyectos de Prácticas Profesionalizantes.
            Cada persona emite un voto por año.
        </p>
    </aside>

    <main class="auth__main" id="contenido">
        <div class="auth__card">
            <h1 class="auth__title"><?= htmlspecialchars($titulo) ?></h1>
            <p class="auth__lead">Ingresá con el correo con el que te registraste.</p>

            <?php if ($mensaje !== ''): ?>
                <div class="alert alert--error" role="alert">
                    <svg class="icon icon--sm" aria-hidden="true"><use href="#i-alerta"></use></svg>
                    <span><?= htmlspecialchars($mensaje) ?></span>
                </div>
            <?php endif; ?>

            <form class="auth__form" method="post" action="" novalidate data-validate="login">

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
                        required
                        <?= $mensaje !== '' ? 'aria-invalid="true"' : '' ?>>
                </div>

                <div class="field field--password">
                    <label class="field__label" for="password">Contraseña</label>
                    <input
                        class="field__input"
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="current-password"
                        required
                        <?= $mensaje !== '' ? 'aria-invalid="true"' : '' ?>>
                    <button type="button" class="field__toggle" data-toggle-password="password"
                            aria-label="Mostrar contraseña" aria-pressed="false">
                        <svg class="icon icon--sm" aria-hidden="true"><use href="#i-ojo"></use></svg>
                    </button>
                </div>

                <button class="btn btn--primary btn--block" type="submit">Ingresar</button>
            </form>

            <p class="auth__meta">
                <a class="link" href="recuperar.php">¿Olvidaste tu contraseña?</a>
            </p>
            <p class="auth__meta">
                ¿No tenés cuenta? <a class="link" href="../registro/registro.php">Registrate acá</a>
            </p>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/site-footer.php'; ?>

</div>

<script type="module" src="../js/validate.js"></script>
<script type="module" src="../js/toggle-password.js"></script>

<!-- Boton de tema: flotante, asi no hay que insertarlo en el markup
     diferente de cada pagina. -->
<div class="theme-toggle">
    <button class="theme-toggle__btn" type="button"
            data-theme-toggle aria-pressed="false" aria-label="Cambiar a tema oscuro">
        <svg class="icon" aria-hidden="true"><use href="#i-luna"></use></svg>
    </button>
</div>
<script type="module" src="../js/theme.js"></script>

<?php include __DIR__ . '/../includes/iconos.php'; ?>
</body>
</html>
