<?php
// Necesario para leer el mensaje y los valores previos que dejo
// registro_validar.php al redirigir.
session_start();
require_once __DIR__ . '/../includes/flash.php';

$errores = flash_get();
$old = flash_old();
$titulo = 'Crear una cuenta';
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
            <p class="auth__lead">Te enviamos un código de 6 dígitos para activar la cuenta.</p>

            <?php if ($errores): ?>
                <div class="alert alert--error" role="alert">
                    <svg class="icon" aria-hidden="true"><use href="#i-alerta"></use></svg>
                    <div>
                        <?php foreach (($errores['lista'] ?? [$errores['mensaje']]) as $mensaje): ?>
                            <p><?= htmlspecialchars($mensaje) ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form class="auth__form" method="post" action="registro_validar.php"
                  novalidate data-validate="registro">

                <div class="field">
                    <label class="field__label" for="usuario">Nombre de usuario</label>
                    <input
                        class="field__input"
                        type="text"
                        id="usuario"
                        name="usuario"
                        value="<?= htmlspecialchars($old['usuario'] ?? '') ?>"
                        maxlength="50"
                        autocomplete="username"
                        aria-describedby="hint-usuario"
                        <?= isset($old['usuario']) && $old['usuario'] !== '' ? 'autofocus' : '' ?>
                        required>
                    <p class="field__hint" id="hint-usuario">
                        <svg class="icon icon--sm" aria-hidden="true"><use href="#i-info"></use></svg>
                        Hasta 50 caracteres. Letras, números, espacios y los signos . - _ '.
                    </p>
                </div>

                <div class="field">
                    <label class="field__label" for="correo">Correo electrónico</label>
                    <input
                        class="field__input"
                        type="email"
                        id="correo"
                        name="correo"
                        value="<?= htmlspecialchars($old['correo'] ?? '') ?>"
                        maxlength="100"
                        autocomplete="email"
                        inputmode="email"
                        aria-describedby="hint-correo"
                        <?= isset($old['correo']) && $old['correo'] !== '' && empty($old['usuario']) ? 'autofocus' : '' ?>
                        required>
                    <p class="field__hint" id="hint-correo">
                        <svg class="icon icon--sm" aria-hidden="true"><use href="#i-correo"></use></svg>
                        Es donde vas a recibir el código de activación.
                    </p>
                </div>

                <button class="btn btn--primary btn--block" type="submit">
                    <svg class="icon" aria-hidden="true"><use href="#i-llave"></use></svg>
                    Crear cuenta
                </button>
            </form>

            <p class="auth__meta">
                ¿Ya tenés cuenta? <a class="link" href="login.php">Iniciá sesión</a>
            </p>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/site-footer.php'; ?>

</div>

<script type="module" src="../assets/js/validate.js"></script>

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
