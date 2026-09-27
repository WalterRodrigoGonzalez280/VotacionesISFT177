<?php
/**
 * Sprite de iconos SVG.
 *
 * Un solo <svg> oculto con todos los <symbol>, y en cada pagina se usan con:
 *   <svg class="icon"><use href="#i-alerta"></use>
 *
 * Por que SVG y no emoji:
 *  - escala sin pixelarse
 *  - toma el color del texto con currentColor
 *  - no depende de la fuente de emojis del sistema, que varia por SO
 *  - se marca aria-hidden porque el texto alrededor ya dice lo mismo
 *
 * Trazo de 2px, extremos redondeados, caja de 24x24: mismo estilo en todos.
 *
 * Se incluye una vez por pagina, cerca del final del <body>.
 */
?>
<svg class="sprite" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <style>
            .sprite { position: absolute; width: 0; height: 0; overflow: hidden; }
        </style>
    </defs>

    <symbol id="i-alerta" viewBox="0 0 24 24">
        <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/>
        <path d="M12 9v4"/><path d="M12 17h.01"/>
    </symbol>

    <symbol id="i-check" viewBox="0 0 24 24">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
        <path d="m9 11 3 3L22 4"/>
    </symbol>

    <symbol id="i-circulo" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
    </symbol>

    <symbol id="i-ojo" viewBox="0 0 24 24">
        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/>
        <circle cx="12" cy="12" r="3"/>
    </symbol>

    <symbol id="i-ojo-off" viewBox="0 0 24 24">
        <path d="M10.7 5.1A10.4 10.4 0 0 1 12 5c6.5 0 10 7 10 7a13.2 13.2 0 0 1-1.7 2.7"/>
        <path d="M6.6 6.6A13.5 13.5 0 0 0 2 12s3.5 7 10 7a9.7 9.7 0 0 0 5.4-1.6"/>
        <path d="m2 2 20 20"/>
    </symbol>

    <symbol id="i-refresh" viewBox="0 0 24 24">
        <path d="M21 12a9 9 0 1 1-9-9c2.5 0 4.9 1 6.7 2.7L21 8"/>
        <path d="M21 3v5h-5"/>
    </symbol>

    <symbol id="i-borrar" viewBox="0 0 24 24">
        <path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>
        <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
        <path d="M10 11v6"/><path d="M14 11v6"/>
    </symbol>

    <symbol id="i-editar" viewBox="0 0 24 24">
        <path d="M17 3a2.8 2.8 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
        <path d="m15 5 4 4"/>
    </symbol>

    <symbol id="i-config" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="3"/>
        <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1A1.7 1.7 0 0 0 9 19.4a1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 4.6 9a1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>
    </symbol>

    <symbol id="i-flecha-izq" viewBox="0 0 24 24">
        <path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>
    </symbol>

    <symbol id="i-trofeo" viewBox="0 0 24 24">
        <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/>
        <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/>
        <path d="M4 22h16"/><path d="M10 14.7V17c0 .6-.5 1-1 1.2C7.9 18.8 7 20.2 7 22"/>
        <path d="M14 14.7V17c0 .6.5 1 1 1.2 1.1.6 2 2 2 3.8"/>
        <path d="M18 2H6v7a6 6 0 0 0 12 0Z"/>
    </symbol>

    <symbol id="i-urna" viewBox="0 0 24 24">
        <path d="M9 4h6"/><path d="M9 8h6"/><path d="M9 12h6"/>
        <path d="M5 3h14a1 1 0 0 1 1 1v17a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/>
    </symbol>

    <symbol id="i-correo" viewBox="0 0 24 24">
        <rect x="2" y="4" width="20" height="16" rx="2"/>
        <path d="m22 7-8.97 5.7a1.9 1.9 0 0 1-2.06 0L2 7"/>
    </symbol>

    <symbol id="i-llave" viewBox="0 0 24 24">
        <circle cx="7.5" cy="15.5" r="4.5"/>
        <path d="m10.7 12.3 8.8-8.8"/><path d="m17 6 2 2"/><path d="m14 9 2 2"/>
    </symbol>

    <symbol id="i-personas" viewBox="0 0 24 24">
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>
    </symbol>

    <symbol id="i-reloj" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
    </symbol>

    <symbol id="i-calendario" viewBox="0 0 24 24">
        <rect x="3" y="5" width="18" height="16" rx="2"/>
        <path d="M16 3v4"/><path d="M8 3v4"/><path d="M3 11h18"/>
    </symbol>

    <symbol id="i-info" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/><path d="M12 16v-4"/><path d="M12 8h.01"/>
    </symbol>

    <symbol id="i-bloqueo" viewBox="0 0 24 24">
        <rect x="4" y="10" width="16" height="11" rx="2"/>
        <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
    </symbol>
    <symbol id="i-usuario-mas" viewBox="0 0 24 24">
        <path d="M15 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/>
        <path d="M19 8v6"/><path d="M22 11h-6"/>
    </symbol>

    <symbol id="i-enviar" viewBox="0 0 24 24">
        <path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>
    </symbol>

    <symbol id="i-documento" viewBox="0 0 24 24">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/>
        <path d="M14 2v6h6"/><path d="M9 13h6"/><path d="M9 17h4"/>
    </symbol>

    <symbol id="i-banda" viewBox="0 0 24 24">
        <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V4s-1 1-4 1-5-2-8-2-4 1-4 1Z"/>
        <path d="M4 22v-7"/>
    </symbol>
    <symbol id="i-sol" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="4"/>
        <path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.9 4.9 1.4 1.4"/>
        <path d="m17.7 17.7 1.4 1.4"/><path d="M2 12h2"/><path d="M20 12h2"/>
        <path d="m6.3 17.7-1.4 1.4"/><path d="m19.1 4.9-1.4 1.4"/>
    </symbol>

    <symbol id="i-luna" viewBox="0 0 24 24">
        <path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>
    </symbol>
</svg>
