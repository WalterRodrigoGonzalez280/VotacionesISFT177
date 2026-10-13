/**
 * Validacion de formularios en el cliente.
 *
 * Por que en el cliente y no solo en PHP: el servidor ya valida (registro
 * exige un nombre de usuario con letras, numeros y signos permitidos, activar
 * exige mayuscula y numero, etc.), pero si el
 * error solo aparece despues del POST con un alert(), la persona pierde lo que
 * habia escrito. Aca se valida antes de enviar y el mensaje va pegado al
 * campo, sin perder nada.
 *
 * El servidor sigue siendo la autoridad: esto es una capa de confort, no de
 * seguridad. Todo se revalida en PHP.
 *
 * ES module, sin `var`. Sin dependencias.
 */

/** Marca un campo como invalido y escribe el mensaje debajo. */
function markField(input, message) {
  clearField(input);

  input.setAttribute('aria-invalid', 'true');

  const holder = document.createElement('p');
  holder.className = 'field__error';
  holder.id = `${input.id}-error`;

  const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  icon.setAttribute('class', 'icon icon--sm');
  icon.setAttribute('aria-hidden', 'true');
  const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
  use.setAttribute('href', '#i-alerta');
  icon.append(use);

  const text = document.createElement('span');
  text.textContent = message;

  holder.append(icon, text);
  input.insertAdjacentElement('afterend', holder);

  // aria-describedby suma el id del error sin pisar el del hint.
  const described = (input.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
  if (!described.includes(holder.id)) input.setAttribute('aria-describedby', [...described, holder.id].join(' '));
}

function clearField(input) {
  const holder = document.getElementById(`${input.id}-error`);
  if (holder) holder.remove();

  input.removeAttribute('aria-invalid');
  const described = (input.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
  const filtered = described.filter((id) => id !== `${input.id}-error`);
  if (filtered.length) input.setAttribute('aria-describedby', filtered.join(' '));
  else input.removeAttribute('aria-describedby');
}

/* ------------------------------------------------------------- reglas base */

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i;

const RULES = {
  required: {
    test: (value) => value.trim().length > 0,
    message: () => 'Este campo es obligatorio.',
  },
  email: {
    test: (value) => EMAIL_RE.test(value.trim()),
    message: () => 'Revisá el correo: falta el @ o el dominio.',
  },
  /** deps: array de ids que deben coincidir entre si. */
  match: {
    test: (value, input, form) => {
      const other = form.querySelector(`#${input.dataset.match}`);
      return Boolean(other) && value === other.value;
    },
    message: (input) => {
      const label = input.dataset.matchLabel || 'el campo anterior';
      return `Las contraseñas no coinciden con ${label}.`;
    },
  },
  /** 6+ caracteres, 1 mayuscula, 1 numero. */
  password: {
    test: (value) => /^(?=.*[A-Z])(?=.*\d).{6,}$/.test(value),
    message: () => 'Mínimo 6 caracteres, con al menos una mayúscula y un número.',
  },
  /**
   * Nombre de usuario: letras de cualquier alfabeto, numeros, espacio y los
   * signos . ' _ -. Se bloquea todo lo demas (control, <>&", /, ;, %, #, =,
   * emoji, etc.). El largo maximo lo fija el atributo maxlength del input
   * (50, coherente con la columna `nombre varchar(50)`) y el servidor.
   * Debe coincidir con la regex del servidor (registro_validar.php).
   */
  nombreSeguro: {
    test: (value) => /^[\p{L}\p{N} .'_-]+$/u.test(value),
    message: () => 'El nombre solo puede tener letras, números, espacios y los signos . - _ \'.',
  },
  /** 4 digitos exactos. */
  digits4: {
    test: (value) => /^\d{4}$/.test(value.trim()),
    message: () => 'El código tiene 4 dígitos.',
  },
};

/* ------------------------------------------------------------- aplicacion */

const FORMS = new Map([
  ['login', {
    fields: {
      email: ['required', 'email'],
      password: ['required'],
    },
  }],
  ['registro', {
    fields: {
      usuario: ['required', 'nombreSeguro'],
      correo: ['required', 'email'],
    },
  }],
  ['recuperar', {
    fields: {
      email: ['required', 'email'],
    },
  }],
  ['activar', {
    fields: {
      token: ['required', 'digits4'],
      password: ['required', 'password'],
      confirm_password: ['required', 'match'],
    },
  }],
]);

/**
 * Valida un formulario. Al primer error se enfoca el campo y se corta.
 * @returns {boolean} true si todo pasa
 */
function validate(formName) {
  const config = FORMS.get(formName);
  if (!config) return true;

  const form = document.querySelector(`[data-validate="${formName}"]`);
  if (!form) return true;

  for (const [id, ruleNames] of Object.entries(config.fields)) {
    const input = form.querySelector(`#${id}`);
    if (!input) continue;

    for (const ruleName of ruleNames) {
      const rule = RULES[ruleName];
      const value = input.value ?? '';

      if (!rule.test(value, input, form)) {
        markField(input, rule.message(input));
        input.focus();
        return false;
      }
    }

    clearField(input);
  }

  return true;
}

/* --------------------------------------------------- delegacion de eventos */

document.addEventListener('submit', (event) => {
  const form = event.target.closest('[data-validate]');
  if (!form) return;

  if (!validate(form.dataset.validate)) {
    event.preventDefault();
  }
});

// Mientras se escribe, el error del campo desaparece: si ya se corrigió,
// no hay que seguir marcándolo como inválido.
document.addEventListener('input', (event) => {
  if (event.target.getAttribute?.('aria-invalid') === 'true') {
    clearField(event.target);
  }
});

// Pega solo dígitos en el código de 4 dígitos.
document.addEventListener('input', (event) => {
  const input = event.target;
  if (!input.matches?.('[data-digits-only]')) return;
  input.value = input.value.replace(/\D/g, '').slice(0, 4);
});
