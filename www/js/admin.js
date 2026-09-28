/**
 * Panel de administracion.
 *
 * ES module, sin `var`. Toda llamada a la API pasa por `api()`, que hace
 * async/await con try/catch y devuelve { ok, data } sin lanzar nunca.
 *
 * Dos reglas que se respetan aca:
 *  - Delegacion de eventos: los listeners viven en los contenedores, no en
 *    cada fila. Las tablas se re-renderizan enteras y los handlers siguen.
 *  - No se innerHTML para construir filas: se crean nodos. El contenido que
 *    viene de la base (nombres de usuario, de proyecto) no se interpola
 *    como HTML, asi que un nombre con "<script>" no puede ejecutarse.
 */

const ENDPOINT = 'api.php';

const messages = document.querySelector('[data-messages]');

/** Muestra un mensaje temporal. */
function notify(text, kind = 'success') {
  if (!messages) return;
  messages.replaceChildren();
  const p = document.createElement('p');
  p.className = `message message--${kind}`;
  p.textContent = text;
  messages.append(p);
  window.setTimeout(() => p.remove(), 4000);
}

/** @returns {Promise<{ok: boolean, data: object}>} */
async function api(action, fields = {}) {
  const body = new URLSearchParams({ action, ...fields });

  try {
    const response = await fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body,
    });
    const data = await response.json();

    if (!response.ok || data.success === false) return { ok: false, data };
    return { ok: true, data };
  } catch (error) {
    console.error(`[api] ${action}:`, error);
    return { ok: false, data: { error: 'No pudimos comunicarnos con el servidor.' } };
  }
}

/* ----------------------------------------------------------------- helpers */

/** Crea un elemento con clase, texto y atributos, en una sola llamada. */
function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
}

/** Reemplaza el contenido de un contenedor. */
function render(container, ...nodes) {
  if (!container) return;
  container.replaceChildren(...nodes);
}

/* ------------------------------------------------------------------ tabs */

const tabs = [...document.querySelectorAll('[data-tab]')];
const panels = [...document.querySelectorAll('[data-panel]')];

/**
 * Muestra una pestana. `focus` se pasa en true cuando el cambio vino de las
 * flechas del teclado, para que el foco acompanhe al tab activo.
 */
function selectTab(name, { focus = false } = {}) {
  for (const tab of tabs) {
    const active = tab.dataset.tab === name;
    tab.setAttribute('aria-selected', String(active));
    tab.tabIndex = active ? 0 : -1;
    if (active && focus) tab.focus();
  }

  for (const panel of panels) {
    panel.hidden = panel.dataset.panel !== name;
  }
}

// Cambia el ?tab= sin recargar, para que la URL quede compartible.
function syncUrl(name) {
  const url = new URL(window.location.href);
  url.searchParams.set('tab', name);
  window.history.replaceState({}, '', url);
}

function onTabClick(event) {
  const tab = event.target.closest('[data-tab]');
  if (!tab) return;
  selectTab(tab.dataset.tab);
  syncUrl(tab.dataset.tab);
  loadPanel(tab.dataset.tab);
}

function onTabKeydown(event) {
  const tab = event.target.closest('[data-tab]');
  if (!tab) return;

  const index = tabs.indexOf(tab);
  let next = null;

  if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
  else if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
  else if (event.key === 'Home') next = 0;
  else if (event.key === 'End') next = tabs.length - 1;
  else return;

  event.preventDefault();
  selectTab(tabs[next].dataset.tab, { focus: true });
}

/* ---------------------------------------------------------------- usuarios */

async function loadUsers() {
  const container = document.querySelector('[data-users-list]');
  render(container, el('p', 'panel__loading', 'Cargando usuarios…'));

  const { ok, data } = await api('obtener_usuarios');

  if (!ok) {
    render(container, el('p', 'panel__error', data.error || 'No pudimos cargar los usuarios.'));
    return;
  }

  if (data.usuarios.length === 0) {
    render(container, el('p', 'panel__empty', 'No hay usuarios registrados.'));
    return;
  }

  // DocumentFragment: una sola insercion en el DOM en vez de una por fila.
  const fragment = document.createDocumentFragment();
  const table = el('table', 'table');
  table.append(buildHead(['ID', 'Usuario', 'Email', 'Votos', 'Detalle', 'Acciones']));

  const tbody = el('tbody');
  for (const user of data.usuarios) {
    tbody.append(buildUserRow(user));
  }
  table.append(tbody);
  const wrap = el('div', 'table-wrap');
  wrap.append(table);
  fragment.append(wrap);
  render(container, fragment);
}

function buildHead(columns) {
  const thead = el('thead');
  const tr = el('tr');
  for (const column of columns) {
    const th = el('th', null, column);
    th.scope = 'col';
    tr.append(th);
  }
  thead.append(tr);
  return thead;
}

function buildUserRow(user) {
  const tr = el('tr');

  tr.append(el('td', 'table__num', String(user.id)));

  const name = el('td');
  name.append(el('strong', null, user.nombre));
  tr.append(name);

  tr.append(el('td', 'table__email', user.email));
  tr.append(el('td', 'table__num', String(user.total_votos ?? 0)));
  tr.append(el('td', 'table__detail', user.votos_detalle || 'Sin votos'));

  // Las acciones viajan como data-attributes; el listener esta delegado.
  const actions = el('td', 'table__actions');
  actions.append(
    iconButton('refresh', `resetear-votos:${user.id}`, `Resetear los votos de ${user.nombre}`),
    iconButton('borrar', `eliminar-usuario:${user.id}`, `Eliminar a ${user.nombre}`, 'is-danger'),
  );
  tr.append(actions);

  return tr;
}

/** Boton de icono: recibe el id del <symbol> del sprite, no un emoji. */
function iconButton(icon, action, label, extra = '') {
  const button = el('button', `icon-btn ${extra}`.trim());
  button.type = 'button';
  button.dataset.rowAction = action;
  // El icono es decorativo; el nombre accesible va en aria-label.
  button.setAttribute('aria-label', label);
  button.title = label;
  button.append(iconNode(icon));
  return button;
}

/** Crea un <svg><use href="#i-..."></svg> apuntando al sprite. */
function iconNode(name) {
  const NS = 'http://www.w3.org/2000/svg';
  const svg = document.createElementNS(NS, 'svg');
  svg.setAttribute('class', 'icon');
  svg.setAttribute('aria-hidden', 'true');
  const use = document.createElementNS(NS, 'use');
  use.setAttribute('href', `#i-${name}`);
  svg.append(use);
  return svg;
}

/* ----------------------------------------------------------------- grupos */

async function loadGroups() {
  const container = document.querySelector('[data-groups-list]');
  render(container, el('p', 'panel__loading', 'Cargando grupos…'));

  const { ok, data } = await api('obtener_subgrupos_admin');

  if (!ok) {
    render(container, el('p', 'panel__error', data.error || 'No pudimos cargar los grupos.'));
    return;
  }

  if (data.subgrupos.length === 0) {
    render(container, el('p', 'panel__empty', 'Todavía no hay proyectos cargados.'));
    return;
  }

  // Agrupar por año en una sola pasada sobre un Map.
  const byGroup = new Map();
  for (const sub of data.subgrupos) {
    if (!byGroup.has(sub.grupo_id)) {
      byGroup.set(sub.grupo_id, { nombre: sub.grupo_nombre, items: [] });
    }
    byGroup.get(sub.grupo_id).items.push(sub);
  }

  const fragment = document.createDocumentFragment();

  for (const group of [...byGroup.values()].sort((a, b) => a.nombre.localeCompare(b.nombre))) {
    const section = el('section', 'groupbox');
    section.append(el('h3', 'groupbox__title', group.nombre));

    const list = el('ul', 'grouplist');

    for (const sub of group.items) {
      const item = el('li', 'groupitem');

      const info = el('div', 'groupitem__info');
      info.append(
        el('strong', 'groupitem__name', sub.nombre),
        el('span', 'groupitem__count', `${sub.total_votos} votos`),
      );

      const actions = el('div', 'groupitem__actions');
      actions.append(
        iconButton('editar', `editar-subgrupo:${sub.id}`, `Renombrar ${sub.nombre}`),
        iconButton('borrar', `eliminar-subgrupo:${sub.id}`, `Eliminar ${sub.nombre}`, 'is-danger'),
      );

      item.append(info, actions);
      list.append(item);
    }

    section.append(list);
    fragment.append(section);
  }

  render(container, fragment);
}

/* -------------------------------------------------------------- ganadores */

async function loadWinners() {
  const container = document.querySelector('[data-winners]');
  render(container, el('p', 'panel__loading', 'Calculando ganadores…'));

  const { ok, data } = await api('obtener_resultados');

  if (!ok) {
    render(container, el('p', 'panel__error', data.error || 'No pudimos calcular los ganadores.'));
    return;
  }

  if (data.resultados.length === 0) {
    render(container, el('p', 'panel__empty', 'Todavía no hay proyectos para comparar.'));
    return;
  }

  // Mismo criterio que antes: maximo de votos, gana el primero en empate.
  const byGroup = new Map();
  for (const row of data.resultados) {
    if (!byGroup.has(row.grupo_id)) {
      byGroup.set(row.grupo_id, { nombre: row.grupo_nombre, rows: [] });
    }
    byGroup.get(row.grupo_id).rows.push(row);
  }

  const fragment = document.createDocumentFragment();
  const best = [];

  for (const group of byGroup.values()) {
    const max = Math.max(...group.rows.map((r) => Number(r.votos)));
    const winner = group.rows.find((r) => Number(r.votos) === max);
    if (winner) best.push(winner);

    const box = el('article', 'winnerbox');
    box.append(el('h3', 'winnerbox__group', group.nombre));
    box.append(el('p', 'winnerbox__name', winner ? winner.nombre : 'Sin votos'));
    box.append(el('p', 'winnerbox__count', winner ? `${winner.votos} votos` : '0 votos'));
    fragment.append(box);
  }

  if (best.length > 0) {
    const overall = el('article', 'winnerbox winnerbox--overall');
    const top = Math.max(...best.map((r) => Number(r.votos)));
    const champ = best.find((r) => Number(r.votos) === top);
    overall.append(el('h3', 'winnerbox__group', 'Ganador general'));
    overall.append(el('p', 'winnerbox__name', champ.nombre));
    overall.append(el('p', 'winnerbox__count', `${champ.grupo_nombre} · ${champ.votos} votos`));
    fragment.append(overall);
  }

  render(container, fragment);
}

/* ---------------------------------------------------------- configuracion */

const toInputValue = (value) => (value ? String(value).slice(0, 16) : '');

async function loadConfig() {
  const container = document.querySelector('[data-config]');
  render(container, el('p', 'panel__loading', 'Cargando configuración…'));

  const { ok, data } = await api('obtener_configuracion');

  if (!ok) {
    render(container, el('p', 'panel__error', data.error || 'No pudimos cargar la configuración.'));
    return;
  }

  const config = data.configuracion || {};
  const form = el('form', 'configform');

  const buildField = (id, label, value) => {
    const wrap = el('div', 'field');
    const lbl = el('label', 'field__label', label);
    lbl.htmlFor = id;
    const input = el('input', 'field__input');
    input.type = 'datetime-local';
    input.id = id;
    input.value = toInputValue(value);
    input.required = true;
    wrap.append(lbl, input);
    return { wrap, input };
  };

  /* El año de la edición, que se guarda en la tabla anio_votacion. */
  const buildYearField = (value) => {
    const wrap = el('div', 'field');
    const lbl = el('label', 'field__label', 'Año de la exposición');
    lbl.htmlFor = 'anio';
    const input = el('input', 'field__input');
    input.type = 'number';
    input.id = 'anio';
    input.name = 'anio';
    input.value = value || new Date().getFullYear();
    input.min = '2000';
    input.max = '2100';
    input.step = '1';
    input.required = true;
    const hint = el('p', 'field__hint', 'Es el año que se muestra en el título y en el encabezado.');
    wrap.append(lbl, input, hint);
    return { wrap, input };
  };

  const start = buildField('inicio_votacion', 'Inicio', config.inicio_votacion);
  const end = buildField('fin_votacion', 'Fin', config.fin_votacion);
  const year = buildYearField(config.anio);

  const submit = el('button', 'btn btn--primary', 'Guardar configuración');
  submit.type = 'button';
  submit.dataset.action = 'save-config';

  form.append(start.wrap, end.wrap, year.wrap, submit);
  render(container, form);
}

async function saveConfig() {
  const inicio = document.getElementById('inicio_votacion')?.value;
  const fin = document.getElementById('fin_votacion')?.value;
  const anio = Number(document.getElementById('anio')?.value);

  if (!inicio || !fin) {
    notify('Completá las dos fechas.', 'error');
    return;
  }
  if (inicio >= fin) {
    notify('La fecha de fin tiene que ser posterior a la de inicio.', 'error');
    return;
  }
  if (!Number.isInteger(anio) || anio < 2000 || anio > 2100) {
    notify('El año tiene que ser un número entre 2000 y 2100.', 'error');
    return;
  }

  const { ok, data } = await api('guardar_configuracion', {
    inicio_votacion: inicio,
    fin_votacion: fin,
    anio: String(anio),
  });

  if (!ok) {
    notify(data.error || 'No pudimos guardar la configuración.', 'error');
    return;
  }
  notify(data.message || 'Configuración guardada.');
  loadConfig();
}

/* ---------------------------------------------------------------- acciones */

const dialog = document.querySelector('[data-dialog]');

/** Delegacion: un listener para todas las acciones declarativas. */
async function onActionClick(event) {
  const trigger = event.target.closest('[data-action], [data-row-action]');
  if (!trigger) return;

  // Acciones de fila: "accion:id"
  if (trigger.dataset.rowAction) {
    await handleRowAction(trigger.dataset.rowAction);
    return;
  }

  switch (trigger.dataset.action) {
    case 'reload-users':      loadUsers(); break;
    case 'reload-groups':     loadGroups(); break;
    case 'reload-winners':    loadWinners(); break;
    case 'reload-config':     loadConfig(); break;
    case 'clean-orphans':     cleanOrphans(); break;
    case 'reset-all':         resetAll(); break;
    case 'open-add':          dialog?.showModal(); break;
    case 'add-subgroup':      addSubgroup(); break;
    case 'save-config':       saveConfig(); break;
    default: break;
  }
}

async function handleRowAction(token) {
  const [action, id] = token.split(':');

  switch (action) {
    case 'resetear-votos-usuario': {
      if (!window.confirm(`¿Resetear los votos de este usuario?`)) return;
      const { ok, data } = await api('resetear_votos_usuario', { usuario_id: id });
      notify(data.message || data.error, ok ? 'success' : 'error');
      if (ok) loadUsers();
      break;
    }
    case 'eliminar-usuario': {
      if (!window.confirm('¿Eliminar este usuario y todos sus votos?')) return;
      const { ok, data } = await api('eliminar_usuario', { usuario_id: id });
      notify(data.message || data.error, ok ? 'success' : 'error');
      if (ok) loadUsers();
      break;
    }
    case 'editar-subgrupo': {
      const input = window.prompt('Nuevo nombre del proyecto:');
      if (!input) return;
      const { ok, data } = await api('actualizar_subgrupo', { id, nombre: input });
      notify(data.message || data.error, ok ? 'success' : 'error');
      if (ok) loadGroups();
      break;
    }
    case 'eliminar-subgrupo': {
      if (!window.confirm('¿Eliminar este proyecto y los votos que recibió?')) return;
      const { ok, data } = await api('eliminar_subgrupo', { id });
      notify(data.message || data.error, ok ? 'success' : 'error');
      if (ok) loadGroups();
      break;
    }
    default: break;
  }
}

async function addSubgroup() {
  const groupId = document.getElementById('grupo_id')?.value;
  const name = document.getElementById('nombre')?.value.trim();

  if (!groupId || !name) {
    notify('Completá el año y el nombre.', 'error');
    return;
  }

  const { ok, data } = await api('agregar_subgrupo', { grupo_id: groupId, nombre: name });

  if (!ok) {
    notify(data.error || 'No pudimos agregar el proyecto.', 'error');
    return;
  }

  notify(data.message || 'Proyecto agregado.');
  dialog?.close();
  document.getElementById('nombre').value = '';
  loadGroups();
}

async function cleanOrphans() {
  if (!window.confirm('¿Revisar y borrar votos huérfanos?')) return;
  const { ok, data } = await api('limpiar_votos_huerfanos');
  notify(data.message || data.error, ok ? 'success' : 'error');
  if (ok) loadUsers();
}

async function resetAll() {
  if (!window.confirm('Esto borra TODOS los votos de todos los usuarios. ¿Seguís?')) return;
  const { ok, data } = await api('resetear_todos_los_votos');
  notify(data.message || data.error, ok ? 'success' : 'error');
  if (ok) {
    loadUsers();
    loadGroups();
  }
}

/* ------------------------------------------------------------------ arranque */

function loadPanel(name) {
  if (name === 'usuarios') loadUsers();
  else if (name === 'subgrupos') loadGroups();
  else if (name === 'ganadores') loadWinners();
  else if (name === 'configuracion') loadConfig();
}

document.addEventListener('click', (event) => {
  onTabClick(event);
  onActionClick(event);
});

document.addEventListener('keydown', onTabKeydown);

// El ?tab= de la URL decide que se muestra al entrar.
const inicial = tabs.find((t) => t.getAttribute('aria-selected') === 'true')?.dataset.tab
  ?? tabs[0]?.dataset.tab;

if (inicial) {
  selectTab(inicial);
  loadPanel(inicial);
}
