/**
 * Selector de tema claro / oscuro.
 *
 * Reglas:
 *  1. Sin preferencia guardada, se sigue al del sistema (prefers-color-scheme).
 *  2. Con preferencia guardada, manda la eleccion de la persona.
 *  3. El atributo data-theme se escribe ANTES de pintar, desde un script en
 *     linea en el <head> (ver theme-inline.php), para que no haya un destello
 *     del tema equivocado al cargar. Este modulo solo maneja el boton.
 *
 * El boton usa aria-pressed en vez de role="switch" porque el patron
 * "activado/desactivado" seAnnuncia mejor asi y no necesita aria-checked.
 *
 * ES module, sin `var`, con delegacion de eventos.
 */

const STORAGE_KEY = 'isft177-tema';

let RAIZ = null;
let mql = null;

function cacheDOM() {
  RAIZ = document.documentElement;
  mql = window.matchMedia('(prefers-color-scheme: dark)');
}

/** El tema que se esta mostrando ahora. */
function temaActual() {
  return RAIZ?.getAttribute('data-theme') || (mql?.matches ? 'dark' : 'light');
}

/** Guarda la preferencia y la aplica al momento. */
function aplicar(tema) {
  RAIZ.setAttribute('data-theme', tema);
  try {
    localStorage.setItem(STORAGE_KEY, tema);
  } catch {
    // Modo privado o storage bloqueado: el tema igual funciona en esta carga.
  }
  pintarBotones(tema);
}

/** Refleja el estado actual en todos los botones [data-theme-toggle]. */
function pintarBotones(tema) {
  const oscuro = tema === 'dark';
  document.querySelectorAll('[data-theme-toggle]').forEach((boton) => {
    boton.setAttribute('aria-pressed', String(oscuro));
    boton.setAttribute('aria-label', oscuro ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro');
    const use = boton.querySelector('use');
    if (use) use.setAttribute('href', oscuro ? '#i-sol' : '#i-luna');
  });
}

function init() {
  cacheDOM();

  document.addEventListener('click', (event) => {
    const boton = event.target.closest('[data-theme-toggle]');
    if (!boton) return;
    aplicar(temaActual() === 'dark' ? 'light' : 'dark');
  });

  /* Si la persona cambia el tema del sistema y no eligio uno a mano, se
     acompanha el cambio en vivo. */
  mql?.addEventListener('change', () => {
    let guardado = null;
    try {
      guardado = localStorage.getItem(STORAGE_KEY);
    } catch {
      guardado = null;
    }
    if (!guardado) {
      RAIZ?.setAttribute('data-theme', mql?.matches ? 'dark' : 'light');
      pintarBotones(temaActual());
    }
  });

  pintarBotones(temaActual());
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
