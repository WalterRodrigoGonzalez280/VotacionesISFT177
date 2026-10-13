<?php
/**
 * Mensajes de una peticion a la siguiente.
 *
 * Antes los formularios usaban alert() para avisar. Eso tiene dos problemas:
 *  - la persona tiene que apretar OK y despues escribirlo todo de nuevo
 *  - un string de error en PHP es truthy, asi que `if (enviarCorreo(...))`
 *    daba por bueno un envio fallido
 *
 * Con esto, el controlador guarda el error y redirige; la pagina lo lee al
 * renderizarse. El input del usuario tambien se conserva.
 */

/** Guarda un mensaje para la proxima peticion. */
function flash_set(string $tipo, string $mensaje): void
{
    $_SESSION['_flash'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

/** Guarda varios mensajes a la vez (uno por error de validacion). */
function flash_set_many(array $mensajes, string $tipo = 'error'): void
{
    $_SESSION['_flash'] = ['tipo' => $tipo, 'lista' => array_values($mensajes)];
}

/** Lee y limpia el mensaje. Devuelve null si no hay. */
function flash_get(): ?array
{
    $flash = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return $flash;
}

/** Guarda lo que la persona habia escrito, para repoblar el formulario. */
function flash_remember(array $campos): void
{
    $_SESSION['_old'] = $campos;
}

/** Lee y limpia los valores previos. */
function flash_old(): array
{
    $old = $_SESSION['_old'] ?? [];
    unset($_SESSION['_old']);
    return $old;
}
