/**
 * Menu hamburguesa del encabezado, para pantallas angostas.
 *
 * El panel es un panel de revelacion, no un modal: el foco se queda en el
 * boton al abrirlo y no se atrapa. Escape lo cierra y devuelve el foco al
 * boton, que es lo que espera quien esta navegando con teclado.
 *
 * El estado vive en dos lugares a proposito y se mantienen en sincronia:
 *   - la clase `is-open` del panel, que es lo que el CSS lee para plegarlo;
 *   - `aria-expanded` del boton, que es lo que anuncia el lector de pantalla.
 * Con el atributo `hidden` no se puede trabajar: el panel tiene que verse
 * igual en desktop, donde el boton no existe, asi que el CSS tiene que poder
 * ganarle al atributo. Una clase no compite con nadie.
 *
 * Sin JS el menu no se pliega: el panel se dibuja siempre y el boton no
 * aparece, porque ambos dependen de la clase `js` que pone el script inline
 * del <head> (includes/theme-inline.php). Un control que no funciona es peor
 * que un control que no esta.
 *
 * ES module, sin `var`, con delegacion de eventos.
 */

const BOTON = '[data-menu-toggle]';
const PANEL = '[data-menu-panel]';

const ETIQUETAS = {
  cerrado: 'Abrir el menú',
  abierto: 'Cerrar el menú',
};

/** El mismo breakpoint que nav.css: desde 48rem el menu es una fila. */
const ESCRITORIO = window.matchMedia('(min-width: 48rem)');

const boton = document.querySelector(BOTON);
const panel = document.querySelector(PANEL);

function setAbierto(abierto) {
  panel.classList.toggle('is-open', abierto);
  boton.setAttribute('aria-expanded', String(abierto));
  boton.setAttribute('aria-label', abierto ? ETIQUETAS.abierto : ETIQUETAS.cerrado);
}

if (boton && panel) {

  const estaAbierto = () => boton.getAttribute('aria-expanded') === 'true';

  document.addEventListener('click', (event) => {
    if (event.target.closest(BOTON)) {
      setAbierto(!estaAbierto());
      return;
    }

    // Un toque fuera del panel lo cierra. Un enlace del nav no hace falta
    // cerrarlo: navegan, y la pagina siguiente arranca cerrada.
    if (estaAbierto() && !event.target.closest(PANEL)) {
      setAbierto(false);
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape' || !estaAbierto()) return;
    setAbierto(false);
    boton.focus();
  });

  // Al pasar a escritorio el panel se ve igual con el atributo puesto o sin
  // el, asi que el estado se limpia para que al volver a mobile no quede
  // abierto solo.
  ESCRITORIO.addEventListener('change', (event) => {
    if (event.matches) setAbierto(false);
  });

  // El HTML ya sale cerrado (aria-expanded="false" y sin `is-open`). Esto solo
  // pone el par de atributos en un estado conocido por si otro script los toco.
  setAbierto(false);
}
