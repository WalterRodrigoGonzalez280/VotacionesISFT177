/**
 * Redireccion diferida desde la pagina de despedida.
 *
 * ES module, `const` en vez de `var`, y respeta prefers-reduced-motion
 * cortando la espera para no dejar la pagina cambiando sola.
 */

const DESTINO = '/auth/login.php';
const ESPERA_MS = 5000;

/** Reduce el movimiento no significa "no mover": acorta la espera. */
const espera = () => (window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 1200 : ESPERA_MS);

const redirect = setTimeout(() => {
  window.location.replace(DESTINO);
}, espera());

// Si el usuario elige el link antes, no dejamos el timer corriendo.
window.addEventListener('pagehide', () => clearTimeout(redirect), { once: true });
