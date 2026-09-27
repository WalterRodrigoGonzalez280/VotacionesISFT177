<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hasta pronto | ISFT 177</title>
        <!-- Titulares en Cinzel (capitular romana), cuerpo en Lato.
         preconnect evita el round-trip extra al pedir la hoja de estilos. -->
    <?php include __DIR__ . '/includes/theme-inline.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Lato:wght@300;400;700&display=swap">
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="css/components.css">
    <link rel="stylesheet" href="css/icons.css">
    <link rel="stylesheet" href="css/theme-toggle.css">
    <link rel="stylesheet" href="css/nav.css">
    <link rel="stylesheet" href="css/closing.css">
</head>
<body>
<div class="shell">
    <main class="shell__main closing" id="contenido">

        <div class="closing__card">
            <picture>
                <source srcset="Logo/logoisft177.webp" type="image/webp">
                <img src="Logo/logoisft177-480.png" alt="Logo del ISFT 177" class="closing__logo">
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
