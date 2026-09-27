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
            <picture>
                <source srcset="Logo/logoisft177.webp" type="image/webp">
                <img src="Logo/logoisft177-480.png" width="480" height="480" alt="Logo del ISFT 177" class="closing__logo">
            </picture>

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
