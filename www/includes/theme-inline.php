<?php
/**
 * Script inline que aplica el tema ANTES de que se pinte la pagina.
 *
 * Va en el <head> a proposito: si el atributo data-theme se escribiera desde
 * un module en el final del body, la pagina se pintaria una vez con el tema
 * del sistema y recien despues saltaria al elegido. Eso se ve como un
 * destello blanco en una pantalla oscura, y molesta.
 *
 * Son pocas lineas a proposito: es lo unico que se puede ejecutar de forma
 * sincrona antes del primer pintado.
 *
 * La clase `js` va por el mismo motivo que el tema, y por el mismo precio:
 * el menu hamburguesa se dibuja SOLO con esta clase, asi que sin JS el nav
 * queda visible y navegable en vez de quedar escondido detras de un boton
 * que no hace nada (la leccion del bug 7, aplicada al reves). Como la clase
 * se escribe antes del primer pintado, el menu tampoco produce un salto de
 * layout al plegarse.
 */
$script = <<<'JS'
(function () {
  document.documentElement.classList.add('js');
  try {
    var guardado = localStorage.getItem('isft177-tema');
    var sistema = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', guardado || sistema);
  } catch (e) {
    document.documentElement.setAttribute('data-theme', 'dark');
  }
})();
JS;
?>
<script><?= $script ?></script>
