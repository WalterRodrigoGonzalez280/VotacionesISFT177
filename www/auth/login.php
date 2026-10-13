<?php
session_start();
require_once __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../core/funciones_login.php';

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
                header('Location: activar.php');
                exit;
            } elseif (password_verify($password, $usuario['password'])) {
                session_regenerate_id(true);
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_email'] = $usuario['email'];
                $_SESSION['usuario_nombre'] = $usuario['nombre'];
                $_SESSION['logueado'] = true;
                // es_admin se deriva de rol_id en la BD (fuente de verdad).
                // Bug 4: login.php decidía por rol_id, api.php leía es_admin.
                // Ahora ambos usan rol_id === 1. La columna es_admin queda decorativa.
                $es_admin = ((int)$usuario['rol_id'] === 1);
                $_SESSION['es_admin'] = $es_admin;

                // Sincronizar en BD si estaba desincronizado (migración 2026-09-30)
                if ((int)$usuario['es_admin'] !== ($es_admin ? 1 : 0)) {
                    try {
                        $stmt = $pdo->prepare('UPDATE usuarios SET es_admin = ? WHERE id = ?');
                        $stmt->execute([$es_admin ? 1 : 0, $usuario['id']]);
                    } catch (PDOException $e) {
                        error_log('Login: sincronizar es_admin: ' . $e->getMessage());
                    }
                }

                header('Location: ../public/votacion.php');
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
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
<div class="auth">

    <aside class="auth__brand">
        <?php /* El escudo se pinta entre 7rem y 11rem segun el breakpoint
                  (112-176 px), asi que el unico 480 descargaba entre 1,4x y
                  2,7x de mas. El srcset lo elige el navegador segun la pantalla
                  real, y el sizes replica los breakpoints de auth.css para no
                  pedir de mas en el movil.

                  No se usa <picture> con webp: para este logo el PNG le gana al
                  webp en TODOS los tamanos (son PNG de paleta de 8 bits, no
                  fotos), y el <source> webp ganaba siempre, dejando el srcset
                  del <img> sin efecto. */ ?>
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
                ¿No tenés cuenta? <a class="link" href="registro.php">Registrate acá</a>
            </p>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/site-footer.php'; ?>

</div>

<script type="module" src="../assets/js/validate.js"></script>
<script type="module" src="../assets/js/toggle-password.js"></script>

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
