<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hasta pronto | ISFT 177</title>
    <!-- Titulares en Cinzel (capitular romana), cuerpo en Lato.
         Las tipografias van en un parcial, con la precarga de los .woff2 y la
         hoja de @font-face: ver includes/fonts.php. -->
    <?php include __DIR__ . '/includes/theme-inline.php'; ?>
    <?php include __DIR__ . '/includes/fonts.php'; ?>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/closing.css">
</head>
<body>
<div class="shell">
    <main class="shell__main closing" id="contenido">

        <div class="closing__card">
            <?php /* Mismo srcset que en login.php. Los breakpoints del sizes son
                      los de closing.css: 7rem base y 8.5rem desde 40rem. */ ?>
            <img src="Logo/logoisft177-480.png" width="480" height="480" alt="Logo del ISFT 177"
                 class="closing__logo"
                 sizes="(min-width: 40rem) 136px, 112px"
                 srcset="Logo/logoisft177-96.png 96w,
                         Logo/logoisft177-160.png 160w,
                         Logo/logoisft177-192.png 192w,
                         Logo/logoisft177-256.png 256w,
                         Logo/logoisft177-480.png 480w">

            <h1 class="closing__title">¡Hasta pronto!</h1>

            <p class="closing__lead">Gracias por participar en la votación del ISFT 177.</p>

            <p class="closing__note">Tu sesión se cerró correctamente.</p>

            <a class="btn btn--primary" href="login/login.php">Iniciar sesión</a>

            <p class="closing__auto">
                Te llevamos al inicio de sesión en unos segundos.
            </p>
        </div>

    </main>

    <?php include __DIR__ . '/includes/site-footer.php'; ?>
</div>

<script type="module" src="js/auto-redirect.js"></script>

<!-- Boton de tema: flotante, asi no hay que insertarlo en el markup
     diferente de cada pagina. -->
<div class="theme-toggle">
    <button class="theme-toggle__btn" type="button"
            data-theme-toggle aria-pressed="false" aria-label="Cambiar a tema oscuro">
        <svg class="icon" aria-hidden="true"><use href="#i-luna"></use></svg>
    </button>
</div>
<script type="module" src="js/theme.js"></script>

<?php include __DIR__ . '/includes/iconos.php'; ?>
</body>
</html>
