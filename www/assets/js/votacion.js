/**
 * Logica de la pagina de votacion.
 *
 * ES module. Sin `var`. Toda llamada a la API pasa por `api()`, que hace
 * async/await con try/catch y devuelve siempre un resultado tipado, nunca
 * una excepcion sin manejar.
 *
 * Delegacion de eventos en los contenedores: los botones de votar y los de
 * cancelar/confirmar se resuelven con un solo listener cada uno, aunque el
 * DOM cambie.
 *
 * El voto NO se emite al hacer clic: se abre un <dialog> de confirmacion y
 * recien ahi se llama a la API. Es importante por dos razones: el voto no se
 * puede repetir, asi que conviene que la persona lo vea antes de confirmarlo;
 * y evita el doble envio por doble clic.
 */

const ENDPOINT = '/api/';

let dialog = null;
let projectName = null;
let yearName = null;

function cacheDOM() {
  dialog = document.querySelector('[data-confirm]');
  projectName = document.querySelector('[data-confirm-proyecto]');
  yearName = document.querySelector('[data-confirm-anio]');
}

/** Voto pendiente de confirmar: lo guarda el boton que abrio el dialog. */
let pendiente = null;

/**
 * Muestra un aviso con `window.alert`, igual que hace admin.js.
 *
 * Antes era un recuadro propio en una region `[data-messages]` que vivia en el
 * flujo normal: el mensaje salia debajo de la lista de proyectos, que es lo
 * unico que crece, y con muchas tarjetas quedaba enterrado fuera de pantalla.
 * Y el `role="status"` tampoco anunciaba nada, porque `.messages:empty` dejaba
 * la region en `display: none`, y a una region `aria-live` que no esta
 * renderizada no la anuncia ningun lector de pantalla.
 *
 * Con `alert` los dos problemas se van y ademas tapa el `<dialog>` de
 * confirmacion, que esta en el top layer y le gana a cualquier elemento de la
 * pagina.
 *
 * `kind` queda solo para el prefijo: `alert` no se puede estilizar.
 */
function notify(text, kind = 'success') {
  // `data.message || data.error` deja `undefined` cuando la API no manda
  // ninguno, y `alert(undefined)` muestra la palabra "undefined".
  if (!text) return;
  window.alert(kind === 'error' ? `Error: ${text}` : text);
}

/**
 * Habla con api.php.
 * @returns {Promise<{ok: boolean, status: number, data: object}>} nunca lanza
 */
async function api(action, fields = {}) {
  const body = new URLSearchParams({ action, ...fields });

  try {
    const response = await fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body,
    });
    const data = await response.json();

    if (!response.ok || data.success === false) {
      return { ok: false, status: response.status, data };
    }
    return { ok: true, status: response.status, data };
  } catch (error) {
    // Falla de red o JSON invalido: el error nunca debe romper la pagina.
    console.error(`[api] ${action}:`, error);
    return {
      ok: false,
      status: 0,
      data: { error: 'No pudimos comunicarnos con el servidor.' },
    };
  }
}

/* ---------------------------------------------------------------- votacion */

/** Marca en la UI el grupo que el usuario acaba de votar. */
function markGroupVoted(groupId, subgrupoId) {
  document.querySelectorAll(`[data-group="${groupId}"] .project`).forEach((card) => {
    const isChosen = card.dataset.projectId === String(subgrupoId);
    card.dataset.voted = String(isChosen);
    card.dataset.muted = String(!isChosen);

    const button = card.querySelector('[data-vote]');
    if (button) {
      button.disabled = true;
      button.replaceChildren(document.createTextNode(isChosen ? 'Votaste' : 'Ya votaste'));
    }
  });
}

/** Abre el dialog con los datos del proyecto que se quiere votar. */
function pedirConfirmacion(button) {
  pendiente = {
    subgrupoId: button.dataset.subgrupoId,
    groupId: button.dataset.grupoId,
    button,
  };

  if (projectName) projectName.textContent = button.dataset.proyecto || '';
  if (yearName) yearName.textContent = button.dataset.anio || '';

  dialog?.showModal();
}

async function confirmarVoto() {
  if (!pendiente) return;

  const { subgrupoId, groupId, button } = pendiente;
  pendiente = null;

  // Doble clic en "Confirmar" no debe duplicar la peticion.
  button.disabled = true;

  const { ok, status, data } = await api('votar', {
    subgrupo_id: subgrupoId,
    grupo_id: groupId,
  });

  if (!ok) {
    button.disabled = false;

    // 401 = la sesion caduco mientras la pagina estaba abierta.
    if (status === 401) {
      notify('Tu sesión venció. Volvé a iniciar sesión para votar.', 'error');
      window.setTimeout(() => {
        window.location.href = '/auth/login.php';
      }, 2000);
      return;
    }

    notify(data.error || 'No pudimos registrar tu voto.', 'error');
    return;
  }

  // El contador solo existe para el admin, asi que este if no hace nada
  // para el resto. Se deja explicito para que quede claro el porque.
  const counter = document.querySelector(`[data-vote-count="${subgrupoId}"]`);
  if (counter) {
    const current = Number.parseInt(counter.textContent, 10) || 0;
    counter.textContent = `${current + 1} votos`;
  }

  markGroupVoted(groupId, subgrupoId);
  notify(data.message || 'Voto registrado.');

  // Ruta absoluta desde la raiz del docroot (www/), que es donde Apache
  // sirve. Tras la migracion de carpetas la pagina quedo en /public/, pero
  // este redirect seguia apuntando a /gracias.php: devolvia 404 al cerrar la
  // votacion. Mismo criterio que /auth/login.php del bloque de sesion vencida.
  if (data.votos_completos === true) {
    window.setTimeout(() => {
      window.location.href = '/public/gracias.php';
    }, 2000);
  }
}

/* ------------------------------------------------------- estado del usuario */

const VOTES = { 1: 'votos_1er_ano', 2: 'votos_2do_ano', 3: 'votos_3er_ano' };

/** Pinta los tres indicadores de "ya votaste" segun la respuesta del server. */
function paintUserStatus(usuario) {
  document.querySelectorAll('[data-vote-status]').forEach((item) => {
    const field = VOTES[item.dataset.voteStatus];
    const voted = Boolean(usuario?.[field]);
    item.dataset.voted = String(voted);
    const use = item.querySelector('use');
    if (use) use.setAttribute('href', voted ? '#i-check' : '#i-circulo');
  });
}

/** El admin ve la pagina y vota: no se deshabilitan los botones. */
function lockForAdmin() {
  // Los administradores ahora pueden votar como cualquier participante.
  // Esta función se mantiene por compatibilidad pero no hace nada.
}

async function loadUserStatus() {
  const { ok, status, data } = await api('verificar_usuario');

  if (!ok) {
    // Sin sesion (401) es lo normal en esta pagina: se muestran las
    // tarjetas sin el panel de "mi estado". No hay que hacer nada.
    if (status === 0) {
      console.warn('[votacion] no se pudo consultar el estado del usuario');
    }
    return;
  }

  paintUserStatus(data.usuario);
}

/* --------------------------------------------------------------- countdown */

function startCountdown() {
  const root = document.querySelector('[data-countdown]');
  if (!root) return;

  const fields = {
    dias: root.querySelector('[data-dias]'),
    horas: root.querySelector('[data-horas]'),
    minutos: root.querySelector('[data-minutos]'),
    segundos: root.querySelector('[data-segundos]'),
  };

  const read = () => {
    const dias = Number.parseInt(fields.dias.textContent, 10) || 0;
    const horas = Number.parseInt(fields.horas.textContent, 10) || 0;
    const minutos = Number.parseInt(fields.minutos.textContent, 10) || 0;
    return dias * 86400 + horas * 3600 + minutos * 60;
  };

  const tick = () => {
    let total = read();

    if (total <= 0) {
      window.location.reload();
      return;
    }

    total -= 1;

    fields.dias.textContent = Math.floor(total / 86400);
    fields.horas.textContent = String(Math.floor((total % 86400) / 3600)).padStart(2, '0');
    fields.minutos.textContent = String(Math.floor((total % 3600) / 60)).padStart(2, '0');
    fields.segundos.textContent = String(total % 60).padStart(2, '0');
  };

  window.setInterval(tick, 1000);
}

/* --------------------------------------------------- delegacion de eventos */

// Un listener para todos los botones de votar, aunque se agreguen despues.
document.addEventListener('click', (event) => {
  const button = event.target.closest('[data-vote]');
  if (button) {
    event.preventDefault();
    pedirConfirmacion(button);
    return;
  }

  if (event.target.closest('[data-confirm-accept]')) {
    event.preventDefault();
    dialog?.close();
    confirmarVoto();
    return;
  }

  if (event.target.closest('[data-confirm-cancel]')) {
    event.preventDefault();
    pendiente = null;
    dialog?.close();
  }
});

function init() {
  cacheDOM();

  // Escape cierra el dialog: se limpia el voto pendiente para que al reabrir
  // no quede el de un proyecto anterior.
  dialog?.addEventListener('cancel', () => {
    pendiente = null;
  });

  loadUserStatus();
  startCountdown();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
