<?php
/**
 * Pantalla de activacion Y de definicion de contrasena.
 *
 * Son el mismo formulario con una diferencia: si la cuenta tiene un token
 * pendiente hay que escribir el codigo de 6 digitos; si ya esta activa y solo
 * vino a cambiar la contrasena, el codigo no aplica y se pide solo la nueva.
 *
 * El flag `activo` NO servia para decidir esto. Antes la condicion era
 * `if (!$user['activo'])` para mostrar el formulario, asi que un usuario que ya
 * estaba activo (justo a quien se le olvido la contrasena) caia en un callejon
 * sin salida: le decian "tu cuenta ya esta activada" y no le dejaban cambiar
 * la contrasena. La condicion correcta es si hay token pendiente o no.
 */
session_start();

// La contraseña viaja en el POST y, cuando la validación falla, se vuelve a
// escribir en el HTML (value de los inputs) para no perder lo que la persona
// escribió. Sin estas cabeceras el navegador podría guardarla en caché o
// mostrarla al volver con el botón "atrás".
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../core/generar_token.php';

if (empty($_SESSION['user_email'])) {
    header('Location: registro.php');
    exit;
}

$user_email = $_SESSION['user_email'];
$error = '';
$info = '';
$redirigir = false;

// Lo que se escribió en el POST, para poder repoblar el formulario si la
// validación falla. Solo se usan en el camino de error: ver $repoblar.
$viejo_token = '';
$viejo_password = '';
$viejo_confirm = '';

$stmt = $pdo->prepare('SELECT token, activo, token_expira_en, token_intentos, token_ultimo_envio FROM usuarios WHERE email = ?');
$stmt->execute([$user_email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: registro.php');
    exit;
}

/** Hay un codigo esperando que se ingrese. */
$pide_codigo = ($user['token'] !== null && $user['token'] !== '');

/** El codigo pendiente ya vencio: sin fecha de expiracion tambien cuenta. */
$codigo_vencido = false;
if ($pide_codigo) {
    $codigo_vencido = $user['token_expira_en'] === null
        || (int) strtotime($user['token_expira_en']) <= time();
}

if ($pide_codigo && $codigo_vencido) {
    $info = 'El código expiró. Pedí uno nuevo.';
} elseif ($pide_codigo) {
    $info = 'Ingresá el código que te enviamos por correo para confirmar la operación.';
} elseif ((int)$user['activo'] === 1) {
    $info = 'Elegí tu contraseña nueva para seguir entrando con esta cuenta.';
} else {
    $info = 'Ingresá el código que te enviamos por correo y definí tu contraseña.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Se capturan antes del branch de validación: si algo falla, el formulario
    // se re-renderiza y hay que devolver lo que se escribió (a diferencia del
    // registro, que redirige y lo guarda en sesión).
    $viejo_token = trim($_POST['token'] ?? '');
    $viejo_password = $_POST['password'] ?? '';
    $viejo_confirm = $_POST['confirm_password'] ?? '';

    if (isset($_POST['generar_token'])) {
        // Throttle de reenvio: si el ultimo codigo salio hace menos de
        // 60 segundos no se manda otro.
        $ultimo = $user['token_ultimo_envio'] !== null
            ? strtotime($user['token_ultimo_envio'])
            : false;

        if ($ultimo !== false && (time() - $ultimo) < 60) {
            $error = 'Esperá un minuto antes de pedir otro código.';
        } else {
            // `=== true` es obligatorio: el string de error es truthy en PHP.
            $resultado = generarNuevoToken($pdo, $user_email);

            if ($resultado === true) {
                $info = 'Te enviamos un código nuevo por correo.';
            } else {
                $error = is_string($resultado) ? $resultado : 'No pudimos enviar el correo.';
            }
        }

        $stmt = $pdo->prepare('SELECT token, activo, token_expira_en, token_intentos, token_ultimo_envio FROM usuarios WHERE email = ?');
        $stmt->execute([$user_email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $pide_codigo = ($user['token'] !== null && $user['token'] !== '');
    } else {
        $input_token = trim($_POST['token'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        // El codigo solo es obligatorio si hay uno pendiente.
        $falta_codigo = $pide_codigo && $input_token === '';
        $falta_password = $password === '';
        $falta_confirm = $confirm === '';

        if ($falta_codigo || $falta_password || $falta_confirm) {
            $error = 'Completá todos los campos pedidos.';
        } elseif ($pide_codigo && $codigo_vencido) {
            // Sin fecha de expiracion o vencida: no se compara siquiera. El
            // token NO se vacia a NULL (Enmienda T1): el formulario decide
            // pedir o no codigo segun el token, y sin token quedaria solo la
            // contraseña, que permitiria tomar la cuenta sin ningun codigo.
            $error = 'El código expiró. Pedí uno nuevo.';
        } elseif ($pide_codigo && !preg_match('/^\d{6}$/', $input_token)) {
            $error = 'El código tiene 6 dígitos.';
        } elseif ($password !== $confirm) {
            $error = 'Las contraseñas no coinciden.';
        } elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d).{6,}$/', $password)) {
            $error = 'La contraseña necesita al menos 6 caracteres, una mayúscula y un número.';
        } elseif ($pide_codigo && !hash_equals((string) $user['token'], $input_token)) {
            // Cada codigo incorrecto cuenta, en la BD y no en la sesion.
            $intentos = (int) $user['token_intentos'] + 1;

            try {
                if ($intentos >= 5) {
                    // Se invalida venciendo el codigo en el momento, sin
                    // vaciarlo: mismo motivo del branch de arriba.
                    $stmt = $pdo->prepare(
                        'UPDATE usuarios SET token_expira_en = now(), token_intentos = 0 WHERE email = ?'
                    );
                    $stmt->execute([$user_email]);
                    $error = 'Superaste los intentos permitidos. Pedí un código nuevo.';
                } else {
                    $stmt = $pdo->prepare('UPDATE usuarios SET token_intentos = ? WHERE email = ?');
                    $stmt->execute([$intentos, $user_email]);
                    $error = 'El código no es correcto. Revisá el correo.';
                }
            } catch (PDOException $e) {
                error_log('Activación: no se pudo registrar el intento: ' . $e->getMessage());
                $error = 'No pudimos registrar el intento. Intentá de nuevo.';
            }
        } else {
            try {
                // Unico camino que vacia el token: la operacion se completo.
                $stmt = $pdo->prepare(
                    'UPDATE usuarios SET password = ?, activo = 1, token = NULL,
                            token_expira_en = NULL, token_intentos = 0
                       WHERE email = ?'
                );
                $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $user_email]);

                $info = (int)$user['activo'] === 1
                    ? '¡Contraseña actualizada! Te llevamos al inicio de sesión.'
                    : '¡Cuenta activada! Te llevamos al inicio de sesión.';
                $redirigir = true;

                unset($_SESSION['user_email'], $_SESSION['user_token']);
            } catch (PDOException $e) {
                error_log('Activación: ' . $e->getMessage());
                $error = 'No pudimos guardar la contraseña. Intentá de nuevo.';
            }
        }
    }
}

$titulo = $pide_codigo ? 'Confirmar operación' : 'Definir contraseña';

/* Trade-off de seguridad: la contraseña vuelve al HTML solo en el camino de
   error ($error !== ''), que es la propia persona en su propio equipo. En el
   camino exitoso los campos quedan vacíos, así nada sensible queda escrito en
   la página de éxito que se muestra 2,5 s antes del redirect a login; además
   el no-store de arriba evita que el navegador lo cachee. */
$repoblar = ($error !== '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo) ?> | ISFT 177</title>
    <!-- Titulares en Cinzel (capitular romana), cuerpo en Lato.
         Las tipografias van en un parcial, con la precarga de los .woff2 y la
         hoja de @font-face: ver includes/fonts.php. -->
    <?php include __DIR__ . '/../includes/theme-inline.php'; ?>
    <?php include __DIR__ . '/../includes/fonts.php'; ?>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
<div class="auth">

    <aside class="auth__brand">
        <?php /* Mismo srcset que en login.php, y por el mismo motivo: el webp
                  de 480 pesa mas que el PNG y bloqueaba el srcset. Los
                  breakpoints del sizes son los de auth.css. */ ?>
        <img src="../assets/Logo/logoisft177-480.png" width="480" height="480" alt="Logo del ISFT 177"
             class="auth__logo"
             sizes="(min-width: 64rem) 176px, (min-width: 40rem) 136px, 112px"
             srcset="../assets/Logo/logoisft177-96.png 96w,
                     ../assets/Logo/logoisft177-160.png 160w,
                     ../assets/Logo/logoisft177-192.png 192w,
                     ../assets/Logo/logoisft177-256.png 256w,
                     ../assets/Logo/logoisft177-480.png 480w">
        <p class="auth__institution">ISFT N° 177</p>
        <p class="auth__tagline">
            Exposición de proyectos de Prácticas Profesionalizantes.
            Cada persona emite un voto por año.
        </p>
    </aside>

    <main class="auth__main" id="contenido">
        <div class="auth__card">
            <h1 class="auth__title"><?= htmlspecialchars($titulo) ?></h1>
            <p class="auth__lead"><?= htmlspecialchars($info) ?></p>

            <?php if ($error !== ''): ?>
                <div class="alert alert--error" role="alert">
                    <svg class="icon icon--sm" aria-hidden="true"><use href="#i-alerta"></use></svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form class="auth__form" method="post" action="" novalidate data-validate="activar">

                <?php if ($pide_codigo): ?>
                    <div class="field">
                        <label class="field__label" for="token">Código de 6 dígitos</label>
                        <input
                            class="field__input token-input"
                            type="text"
                            id="token"
                            name="token"
                            inputmode="numeric"
                            data-digits-only
                            pattern="\d{6}"
                            maxlength="6"
                            autocomplete="one-time-code"
                            placeholder="000000"
                            value="<?= $repoblar ? htmlspecialchars($viejo_token) : '' ?>"
                            required>
                    </div>
                <?php endif; ?>

                <div class="field field--password">
                    <label class="field__label" for="password">
                        <?= $pide_codigo ? 'Contraseña nueva' : 'Contraseña' ?>
                    </label>
                    <input
                        class="field__input"
                        type="password"
                        id="password"
                        name="password"
                        value="<?= $repoblar ? htmlspecialchars($viejo_password) : '' ?>"
                        autocomplete="new-password"
                        aria-describedby="reqs"
                        required>
                    <button type="button" class="field__toggle" data-toggle-password="password"
                            aria-label="Mostrar contraseña" aria-pressed="false">
                        <svg class="icon" aria-hidden="true"><use href="#i-ojo"></use></svg>
                    </button>
                    <ul class="reqs" id="reqs">
                        <li>Mínimo 6 caracteres.</li>
                        <li>Al menos una mayúscula.</li>
                        <li>Al menos un número.</li>
                    </ul>
                </div>

                <div class="field field--password">
                    <label class="field__label" for="confirm_password">Repetí la contraseña</label>
                    <input
                        class="field__input"
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        value="<?= $repoblar ? htmlspecialchars($viejo_confirm) : '' ?>"
                        autocomplete="new-password"
                        data-match="password"
                        data-match-label="la contraseña"
                        required>
                    <button type="button" class="field__toggle" data-toggle-password="confirm_password"
                            aria-label="Mostrar contraseña" aria-pressed="false">
                        <svg class="icon" aria-hidden="true"><use href="#i-ojo"></use></svg>
                    </button>
                </div>

                <button class="btn btn--primary btn--block" type="submit">
                    <svg class="icon" aria-hidden="true"><use href="#i-check"></use></svg>
                    <?= $pide_codigo ? 'Confirmar' : 'Guardar contraseña' ?>
                </button>
            </form>

            <form class="auth__meta" method="post" action="">
                <button class="btn btn--ghost" type="submit" name="generar_token" value="1">
                    ¿No llegó el código? Enviar otro
                </button>
            </form>

            <p class="auth__meta">
                <a class="link" href="registro.php">← Volver al registro</a>
            </p>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/site-footer.php'; ?>

</div>

<script type="module" src="../assets/js/validate.js"></script>
<script type="module" src="../assets/js/toggle-password.js"></script>
<?php if ($redirigir): ?>
<script>
    setTimeout(function () { window.location.href = 'login.php'; }, 2500);
</script>
<?php endif; ?>

<!-- Boton de tema: flotante, asi no hay que insertarlo en el markup
     diferente de cada pagina. -->
<div class="theme-toggle">
    <button class="theme-toggle__btn" type="button"
            data-theme-toggle aria-pressed="false" aria-label="Cambiar a tema oscuro">
        <svg class="icon" aria-hidden="true"><use href="#i-luna"></use></svg>
    </button>
</div>
<script type="module" src="../assets/js/theme.js"></script>

<?php include __DIR__ . '/../includes/iconos.php'; ?>
</body>
</html>
