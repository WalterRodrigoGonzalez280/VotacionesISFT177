<?php
/**
 * Las tipografias web (Cinzel + Lato) desde el <head>.
 *
 * Son tres lineas que tienen que estar SIEMPRE juntas, y por eso van en un
 * parcial y no en cada pagina: si una se olvida de la precarga, la fuente
 * sigue apareciendo pero un rato despues, y eso se ve como un salto.
 *
 * El `rel="stylesheet"` va NORMAL, sin `media="print"` ni `onload`. Se probo
 * la tecnica de print (bajar la hoja sin que bloquee el pintado) y el
 * resultado fue malo: una hoja con media="print" no aplica sus @font-face
 * hasta que el onload cambia el media, y hasta ahi la pagina se dibuja
 * entera con la tipografia de respaldo. Con las fuentes ya locales no hay
 * nada que ganar con ese truco, solo el riesgo.
 *
 * `crossorigin` es obligatorio en los preload: sin el, el navegador baja el
 * archivo DOS veces (una como fuente y otra como recurso normal).
 *
 * Cinzel es una fuente VARIABLE: un solo archivo cubre los tres pesos, asi que
 * se precarga ese. Lato es ESTATICA y Google sirve un archivo por peso, asi que
 * se precargan el 400 (el del cuerpo de texto, que es lo que mas pinta) y el
 * 700 (botones y titulos del nav). El 300 casi no se usa en el sitio.
 *
 * El latin-ext no se precarga: se deja para que lo pida solo si aparece un
 * caracter que lo necesita, que en una pagina en castellano casi nunca pasa.
 */
?>
    <link rel="preload" href="/assets/fonts/cinzel-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/lato-400-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/lato-700-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/assets/css/fonts.css">
