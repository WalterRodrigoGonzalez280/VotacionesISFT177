<?php
/**
 * Valida el formulario de registro.
 *
 * No devuelve HTML: guarda el error (y lo que la persona habia escrito) y
 * redirige. Asi registro.php puede mostrar el mensaje pegado al campo
 * correspondiente sin que se pierda nada de lo escrito.
 */
session_start();
require_once __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../core/generar_token.php';
require_once __DIR__ . '/../phpMail/enviar_correo.php';
require_once __DIR__ . '/../includes/flash.php';

$usuario = trim($_POST['usuario'] ?? '');
$correo = trim($_POST['correo'] ?? '');

/* ------------------------------------------- validacion, campo por campo */

$errores = [];

if ($usuario === '') {
    $errores['usuario'] = 'Escribí un nombre de usuario.';
} elseif (mb_strlen($usuario) > 50) {
    // El limite lo impone la columna: `nombre varchar(50)`.
    $errores['usuario'] = 'El nombre puede tener hasta 50 caracteres.';
} elseif (!preg_match('/^[\p{L}\p{N} .\'_-]+$/u', $usuario)) {
    // Se bloquea solo lo que puede generar conflictos (control, <>&", /, ;,
    // %, #, =, emoji, etc.). Las letras de cualquier alfabeto, los numeros,
    // los espacios y . ' _ - estan permitidos.
    $errores['usuario'] = 'El nombre solo puede tener letras, números, espacios y los signos . - _ \'.';
}

if ($correo === '') {
    $errores['correo'] = 'Escribí tu correo electrónico.';
} elseif (!preg_match('/^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i', $correo)) {
    $errores['correo'] = 'Revisá el correo: falta el @ o el dominio.';
}

if ($errores) {
    flash_remember(['usuario' => $usuario, 'correo' => $correo]);
    flash_set_many(array_values($errores));
    header('Location: registro.php');
    exit;
}

/* ----------------------------------------------------- correo ya registrado */

try {
    $stmt = $pdo->prepare('SELECT id, activo FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$correo]);
    $existente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existente && (int)$existente['activo'] === 1) {
        flash_remember(['usuario' => $usuario, 'correo' => $correo]);
        flash_set('error', 'Ese correo ya tiene una cuenta activa. Iniciá sesión.');
        header('Location: login.php');
        exit;
    }

    /* ------------------------------------------------ generacion y envio */

    $token = generarCodigoToken();

    // IMPORTANTE: `enviarTokenPorEmail` devuelve `true` o un STRING de error.
    // Un string no vacio es truthy, asi que hay que comparar con === true.
    $envio = enviarTokenPorEmail($correo, $token);

    if ($envio !== true) {
        flash_remember(['usuario' => $usuario, 'correo' => $correo]);
        flash_set('error', is_string($envio) ? $envio : 'No pudimos enviar el correo.');
        header('Location: registro.php');
        exit;
    }

    if ($existente) {
        // Cuenta sin activar: se le renueva el token, con 15 minutos de vida
        // y la marca de reenvio para el throttle (mismo esquema que
        // generarNuevoToken en core/generar_token.php).
        $stmt = $pdo->prepare(
            "UPDATE usuarios
                SET token = ?,
                    token_expira_en = now() + interval '15 minutes',
                    token_intentos = 0,
                    token_ultimo_envio = now()
              WHERE id = ?"
        );
        $stmt->execute([$token, $existente['id']]);
    } else {
        // El token va en el MISMO INSERT: jamas queda una cuenta inactiva
        // sin token, que el formulario de activacion interpretaria como
        // "no hace falta codigo" y dejaria definir la contraseña sin verificar
        // nada. El envio se hizo arriba, asi que el orden email-antes-que-
        // persistir se respeta.
        $stmt = $pdo->prepare(
            "INSERT INTO usuarios (nombre, email, token, activo, token_expira_en, token_intentos, token_ultimo_envio)
             VALUES (?, ?, ?, 0, now() + interval '15 minutes', 0, now())"
        );
        $stmt->execute([$usuario, $correo, $token]);
    }

    $_SESSION['user_email'] = $correo;
    $_SESSION['user_token'] = $token;

    header('Location: activar.php');
    exit;
} catch (PDOException $e) {
    // El detalle tecnico va al log, no a la pantalla.
    error_log('Registro: ' . $e->getMessage());
    flash_remember(['usuario' => $usuario, 'correo' => $correo]);
    flash_set('error', 'No pudimos crear la cuenta en este momento. Intentá de nuevo.');
    header('Location: registro.php');
    exit;
}
