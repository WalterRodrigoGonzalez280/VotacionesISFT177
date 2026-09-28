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
            <?php /* El escudo se pinta a 3rem (48 px), asi que servir siempre el
                      de 480 era descargar 10x lo que se dibuja: Lighthouse lo
                      marca como "imagen mas grande de lo necesario" y sobran
                      unos 20 KiB por visita.

                      El srcset deja que elija el navegador segun la pantalla.
                      Con sizes="48px" y DPR 2 (casi todo telefono) elige el de
                      96 px, que se dibuja 1:1 sobre los pixeles reales: mas
                      nitido que el 480 reducido, y no reintroduce el borron de
                      la version unica de 192 px que se probo antes (ese era un
                      solo archivo para todas las pantallas, que es el problema
                      que el srcset viene a evitar).

                      Los width/height NO fijan el tamano en pantalla -- eso lo
                      hace el CSS (inline-size: 3rem) --: quedan para que el
                      navegador reserve el espacio y la pagina no salte. Todas
                      las variantes son cuadradas, asi que la relacion de aspecto
                      no cambia y el CLS tampoco. */ ?>
            <img src="/Logo/logoisft177-480.png" alt="Logo del ISFT 177"
                 class="sitefoot__logo" width="480" height="480"
                 sizes="48px"
                 srcset="/Logo/logoisft177-96.png 96w,
                         /Logo/logoisft177-160.png 160w,
                         /Logo/logoisft177-192.png 192w,
                         /Logo/logoisft177-480.png 480w">
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
        Desarrollado por Alumnos de 3ero de Sistemas - ISFT N° 177 · <?= (int)$anio ?>
    </p>
</footer>
