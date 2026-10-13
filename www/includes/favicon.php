<?php
/**
 * Los favicons del sitio desde el <head> de cada pagina.
 *
 * Por que existe: favicon-32.png y favicon-180.png estaban en www/assets/Logo/
 * pero NADA los linkeaba, asi que el navegador siempre caia a pedir
 * /favicon.ico (que no existe) y un cambio de favicon no se llegaba a ver.
 * Como son dos lineas identicas que tienen que estar en TODA pagina con <head>,
 * van en un parcial y no escritas a mano en cada una (mismo criterio que
 * fonts.php: si una pagina se olvida una, ya no es un detalle).
 *
 * Las rutas son ABSOLUTAS desde la raiz web porque este parcial se incluye
 * desde paginas a distinta profundidad (auth/, public/, admin/, core/): con
 * rutas relativas el mismo include resolveria distinto en cada pagina.
 * (Mismo criterio que site-header.php.)
 *
 * favicon-32.png es el icono de pestania; favicon-180.png es el que usa
 * Safari/iOS via apple-touch-icon. No hay .ico: los dos tamaños cubren los
 * navegadores actuales.
 */
?>
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/Logo/favicon-32.png">
    <link rel="apple-touch-icon" type="image/png" sizes="180x180" href="/assets/Logo/favicon-180.png">
