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
            <picture>
                <source srcset="/Logo/logoisft177.webp" type="image/webp">
                <img src="/Logo/logoisft177-480.png" alt="" class="sitebar__logo" aria-hidden="true">
            </picture>
            <span class="sitebar__text">
                <span class="sitebar__title">Votación de Proyectos</span>
                <span class="sitebar__sub">ISFT N° 177 · Exposición 2025</span>
            </span>
        </a>

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

    </div>
</header>

<?php /* El boton de tema de este encabezado lo maneja theme.js, asi que el
       modulo se carga ACÁ y no en cada pagina. Cuando el include vivia solo en
       la pagina, index, votacion y admin se olvidaban de cargarlo y el boton
       quedaba sin hacer nada: era el unico modo oscuro roto.

       Ruta con raiz absoluta a proposito: el navegador resuelve un src relativo
       contra la URL de la pagina, no contra la del parcial, asi que
       "js/theme.js" se romperia (/login/js/theme.js) en cuanto una pagina de
       subdirectorio use este encabezado. Igual que los href de arriba. */ ?>
<script type="module" src="/js/theme.js"></script>
