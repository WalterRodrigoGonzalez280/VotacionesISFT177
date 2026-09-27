<?php
/**
 * Pie de pagina compartido.
 *
 * A diferencia del encabezado, el pie NO depende de la sesion: tiene que
 * poder mostrarse igual en una pagina de salida de sesion, donde ya no hay
 * nada en $_SESSION que leer.
 *
 * Grid de dos columnas en mobile, cuatro desde 48rem: la marca ocupa la
 * primera y las tres listas de enlaces las siguientes.
 */
$anio = date('Y');
?>
<footer class="sitefoot">
    <div class="sitefoot__inner wrap wrap--wide">

        <div class="sitefoot__brand">
            <picture>
                <source srcset="/Logo/logoisft177.webp" type="image/webp">
                <img src="/Logo/logoisft177-480.png" alt="Logo del ISFT 177" class="sitefoot__logo">
            </picture>
            <p class="sitefoot__name">Votación de Proyectos</p>
            <p class="sitefoot__tagline">
                Exposición de proyectos de Prácticas Profesionalizantes.<br>
                Técnico en Análisis de Sistemas.
            </p>
        </div>

        <nav class="sitefoot__group" aria-labelledby="foot-sistema">
            <h2 class="sitefoot__title" id="foot-sistema">Sistema</h2>
            <ul class="sitefoot__links">
                <li><a class="sitefoot__link" href="/index.php">Inicio</a></li>
                <li><a class="sitefoot__link" href="/votacion.php">Votación</a></li>
                <li><a class="sitefoot__link" href="/gracias.php">Tu voto</a></li>
            </ul>
        </nav>

        <nav class="sitefoot__group" aria-labelledby="foot-participar">
            <h2 class="sitefoot__title" id="foot-participar">Participar</h2>
            <ul class="sitefoot__links">
                <li><a class="sitefoot__link" href="/registro/registro.php">Crear cuenta</a></li>
                <li><a class="sitefoot__link" href="/login/login.php">Ingresar</a></li>
                <li><a class="sitefoot__link" href="/login/recuperar.php">Recuperar contraseña</a></li>
            </ul>
        </nav>

        <div class="sitefoot__group">
            <h2 class="sitefoot__title">Reglas</h2>
            <ul class="sitefoot__links sitefoot__links--static">
                <li>Un voto por año</li>
                <li>Tres votos en total</li>
                <li>No se pueden repetir</li>
            </ul>
        </div>

    </div>

    <p class="sitefoot__legal">
        Desarrollado por Alumnos de 2do de Sistemas - ISFT N° 177 · <?= (int)$anio ?>
    </p>
</footer>
