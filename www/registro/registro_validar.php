<?php
/**
 * Valida el formulario de registro.
 *
 * No devuelve HTML: guarda el error (y lo que la persona habia escrito) y
 * redirige. Asi registro.php puede mostrar el mensaje pegado al campo
 * correspondiente sin que se pierda nada de lo escrito.
 */
session_start();
require_once __DIR__ . '/../conexion/conexion.php';
require_once __DIR__ . '/../phpMail/enviar_correo.php';
require_once __DIR__ . '/../includes/flash.php';

$usuario = trim($_POST['usuario'] ?? '');
$correo = trim($_POST['correo'] ?? '');

/* ------------------------------------------- validacion, campo por campo */

$errores = [];

if ($usuario === '') {
    $errores['usuario'] = 'Escribí un nombre de usuario.';
} elseif (!preg_match('/^[a-zA-Z]{3,20}$/', $usuario)) {
    $errores['usuario'] = 'El nombre debe tener entre 3 y 20 letras, sin números ni espacios.';
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
        header('Location: ../login/login.php');
        exit;
    }

    /* ------------------------------------------------ generacion y envio */

    $token = str_pad((string)rand(0, 9999), 4, '0', STR_PAD_LEFT);

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
        // Cuenta sin activar: se le renueva el token.
        $stmt = $pdo->prepare('UPDATE usuarios SET token = ? WHERE id = ?');
        $stmt->execute([$token, $existente['id']]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO usuarios (nombre, email, token, activo) VALUES (?, ?, ?, 0)'
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
