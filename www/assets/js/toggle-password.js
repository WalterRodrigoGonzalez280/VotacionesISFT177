/**
 * Muestra y oculta la contraseña.
 *
 * ES module, sin `var`, con delegacion de eventos: un solo listener para
 * todos los botones `[data-toggle-password]`, aunque se agreguen despues.
 *
 * El estado se expone con aria-pressed y el etiqueta cambia, para que un
 * lector de pantalla diga si la contraseña esta visible o no.
 */

const PASSWORD_TOGGLED = 'is-visible';

const LABELS = {
  hidden: 'Mostrar contraseña',
  visible: 'Ocultar contraseña',
};

const EYE = { hidden: 'ojo', visible: 'ojo-off' };

/** Aplica el estado visible/oculto a un input y su boton. */
function setVisibility(input, button, visible) {
  input.type = visible ? 'text' : 'password';

  // Mover el foco al campo mantiene el flujo de teclado: si el usuario
  // acaba de tabular hasta el boton, al pulsarlo quiere seguir escribiendo.
  input.focus({ preventScroll: true });

  button.setAttribute('aria-pressed', String(visible));
  button.setAttribute('aria-label', visible ? LABELS.visible : LABELS.hidden);

  const use = button.querySelector('use');
  if (use) {
    use.setAttribute('href', `#i-${visible ? EYE.visible : EYE.hidden}`);
  }

  button.classList.toggle(PASSWORD_TOGGLED, visible);
}

document.addEventListener('click', (event) => {
  const button = event.target.closest('[data-toggle-password]');
  if (!button) return;

  const input = document.getElementById(button.dataset.togglePassword);
  if (!input) return;

  event.preventDefault();
  setVisibility(input, button, input.type === 'password');
});
