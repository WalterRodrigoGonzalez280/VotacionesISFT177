<?php
/**
 * El icono de la pestaña del navegador, o sea el logo del ISFT al lado del
 * <title>.
 *
 * Va en un parcial y no en cada pagina por la misma razon que fonts.php: son
 * cuatro lineas que tienen que estar SIEMPRE juntas. Si una pagina se olvida,
 * el navegador pide /favicon.ico igual y da un 404 en cada carga, sin que nada
 * lo delate.
 *
 * Rutas con raiz absoluta a proposito: este parcial lo incluyen paginas de
 * /, /login/, /registro/ y /phpMail/, y el navegador resuelve un href relativo
 * contra la URL de la pagina, no contra la del parcial (mismo criterio que los
 * href del menu en site-header.php).
 *
 * PNG y NO webp, aunque el logo pese menos en webp para el resto del sitio.
 * En los favicon el soporte de webp es parcial: Firefox lo acepta, pero Safari
 * y Safari en iOS no, y ahi se caeria al PNG de todos modos. Un solo formato
 * que funciona en todos es menos codigo que dos con un <picture>.
 *
 * El apple-touch-icon va APLANO (sin alfa) sobre el fondo oscuro del sitio: iOS
 * compone los transparentes sobre negro y queda con el halo. Ver el comentario
 * de includes/iconos.php para el criterio de SVG en vez de emoji; el favicon es
 * el unico caso donde hace falta un raster, porque el sprite no se puede usar
 * como icono de pestana.
 */
?>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="/Logo/favicon-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/Logo/favicon-180.png">
