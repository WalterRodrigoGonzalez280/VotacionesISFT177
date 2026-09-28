<?php
/**
 * Encabezado compartido con navegacion.
 *
 * Variables esperadas (opcionales):
 *   $nav_actual  clave de la seccion activa, para marcar aria-current
 *
 * El estado de la sesion cambia la navegacion: si hay sesion aparecen las
 * secciones privadas y la accion de salir; si no, las de account.
 *
 * El boton de tema va ACÁ y no flotando: en las paginas de autenticacion,
 * que no tienen encabezado, sigue estando flotante (ver theme.js).
 */
$es_admin = !empty($_SESSION['es_admin']) && $_SESSION['es_admin'] === true;
$logueado = !empty($_SESSION['logueado']) && $_SESSION['logueado'] === true;
$nombre   = $_SESSION['usuario_nombre'] ?? '';

$nav_actual = $nav_actual ?? '';

// Rutas: el header vive en la raiz, asi que no necesita prefijo.
// Rutas CON raiz: este parcial se incluye desde /, /login/ y /registro/, y el
// navegador resuelve un href relativo contra la URL de la pagina, no contra
// la del parcial.
$secciones = [
    'inicio' => ['Inicio', '/index.php'],
];
if ($logueado) {
    $secciones['votacion'] = ['Votar', '/votacion.php'];
    if ($es_admin) {
        $secciones['admin'] = ['Panel', '/admin.php'];
    }
}
if (!$logueado) {
    $secciones['registro'] = ['Crear cuenta', '/registro/registro.php'];
    $secciones['login'] = ['Ingresar', '/login/login.php'];
}
?>
<header class="sitebar">
    <div class="sitebar__inner wrap wrap--wide">

        <a class="sitebar__brand" href="/index.php">
            <?php /* Durante un tiempo se sirvio un unico 480 px. Se probo bajar
                      el logo a 192 px para ahorrar 30 KB y se vio borroso: el
                      escudo tiene lettering chico adentro y a 40 px de alto se
                      nota. Volver a 480 px era mas liviano que el .webp de 480
                      que se servia antes (35 KB contra 23 de este PNG), asi que
                      la nitidez no costaba nada.

                      El srcset mantiene esa nitidez y ahora ademas no descarga de
                      mas: sizes="44px" es el ancho real del escudo (2.5rem, o
                      2.75rem desde el breakpoint), asi que con DPR 2 el
                      navegador toma el archivo de 96 px y lo dibuja 1:1 sobre
                      los pixeles reales -- mas nitido todavia que reducir el de
                      480, y con menos bytes. El 480 queda en el srcset solo para
                      pantallas de densidad muy alta.

                      Los width/height NO fijan el tamano en pantalla -- eso lo
                      hace el CSS --: estan para que el navegador reserve el
                      espacio y la pagina no salte cuando la imagen llega, que
                      era lo que Lighthouse medi como CLS 0,112. Todas las
                      variantes son cuadradas, asi que la relacion de aspecto
                      sigue siendo 1:1 y el CLS no cambia. */ ?>
            <img src="/Logo/logoisft177-480.png" alt="" class="sitebar__logo"
                 width="480" height="480" aria-hidden="true"
                 sizes="44px"
                 srcset="/Logo/logoisft177-96.png 96w,
                         /Logo/logoisft177-160.png 160w,
                         /Logo/logoisft177-192.png 192w,
                         /Logo/logoisft177-480.png 480w">
            <span class="sitebar__text">
                <span class="sitebar__title">Votación de Proyectos</span>
                <?php /* El año sale de la base (anioVotacion). El function_exists
                          esta porque este parcial tambien lo usan paginas que no
                          conectan a la base: que falte una decoracion es mucho
                          menos grave que que el encabezado se rompa. */ ?>
                <span class="sitebar__sub">ISFT N° 177 · Exposición <?= function_exists('anioVotacion') ? anioVotacion() : (int)date('Y') ?></span>
            </span>
        </a>

        <?php /* Boton de menu hamburguesa: SOLO mobile, y solo si hay JS.
                  La clase `js` la pone el script inline del <head>
                  (theme-inline.php), asi que sin JS no se dibuja y el nav de
                  abajo queda navegable a la vista. El icono se cambia por CSS
                  con [aria-expanded]; los dos van en el DOM y se muestra uno.

                  El panel (nav + sesion) va adentro del <header> y no del
                  <main>: es parte del encabezado, asi que debe caer dentro del
                  punto de referencia del skip-link. */ ?>
        <button class="sitebar__burger" type="button"
                data-menu-toggle aria-expanded="false" aria-controls="sitebar-panel"
                aria-label="Abrir el menú">
            <svg class="icon icon--cerrar" aria-hidden="true"><use href="#i-cerrar"></use></svg>
            <svg class="icon icon--menu" aria-hidden="true"><use href="#i-menu"></use></svg>
        </button>

        <div class="sitebar__panel" id="sitebar-panel" data-menu-panel>

            <?php /* En mobile el switch de tema va PRIMERO del menu y a la
                      izquierda, con el usuario y "Salir" a la derecha de la
                      misma fila. El orden lo fija nav.css, no el HTML. */ ?>
            <div class="sitebar__aside">
                <?php if ($logueado && $nombre !== ''): ?>
                    <span class="sitebar__user" title="<?= htmlspecialchars($nombre) ?>">
                        <?= htmlspecialchars($nombre) ?>
                    </span>
                <?php endif; ?>

                <button class="theme-toggle__btn" type="button"
                        data-theme-toggle aria-pressed="false" aria-label="Cambiar a tema oscuro">
                    <svg class="icon" aria-hidden="true"><use href="#i-luna"></use></svg>
                </button>

                <?php if ($logueado): ?>
                    <a class="btn btn--secondary btn--sm" href="/logout.php">Salir</a>
                <?php endif; ?>
            </div>

            <nav class="sitebar__nav" aria-label="Navegación principal">
                <ul class="navlist">
                    <?php foreach ($secciones as $clave => [$etiqueta, $href]): ?>
                        <li>
                            <a class="navlist__link<?= $nav_actual === $clave ? ' is-current' : '' ?>"
                               href="<?= htmlspecialchars($href) ?>"
                               <?= $nav_actual === $clave ? 'aria-current="page"' : '' ?>>
                                <?= htmlspecialchars($etiqueta) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>

        </div>
    </div>
</header>

<?php /* El boton de menu y el de tema viven ACÁ, en el parcial que los
       renderiza, y no en cada pagina: si el <script> viviera en la pagina,
       alcanza con que una se olvide para dejar un control muerto, y nada lo
       detecta (ver bug 7 en README.md).

       Ruta con raiz absoluta a proposito: el navegador resuelve un src relativo
       contra la URL de la pagina, no contra la del parcial, asi que
       "js/menu.js" se romperia (/login/js/menu.js) en cuanto una pagina de
       subdirectorio use este encabezado. Igual que los href de arriba. */ ?>
<script type="module" src="/js/menu.js"></script>
<script type="module" src="/js/theme.js"></script>
