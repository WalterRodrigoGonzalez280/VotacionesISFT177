<?php
session_start();
require_once __DIR__ . '/conexion/conexion.php';

if (empty($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    header('Location: login/login.php');
    exit;
}

if (!empty($_SESSION['es_admin']) && $_SESSION['es_admin'] === true) {
    header('Location: admin.php');
    exit;
}

$nombre_usuario = '';
$votos = ['votos_1er_año' => 0, 'votos_2do_año' => 0, 'votos_3er_año' => 0];

if (!empty($_SESSION['usuario_id'])) {
    try {
        $stmt = $pdo->prepare(
            'SELECT nombre, votos_1er_año, votos_2do_año, votos_3er_año
               FROM usuarios
              WHERE id = ?'
        );
        $stmt->execute([$_SESSION['usuario_id']]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        $completo = $usuario
            && $usuario['votos_1er_año'] == 1
            && $usuario['votos_2do_año'] == 1
            && $usuario['votos_3er_año'] == 1;

        if (!$completo) {
            header('Location: votacion.php');
            exit;
        }

        $nombre_usuario = $usuario['nombre'];
    } catch (PDOException $e) {
        error_log('Gracias: ' . $e->getMessage());
        header('Location: votacion.php');
        exit;
    }
}

$titulo = '¡Gracias por tu voto!';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo) ?> | ISFT 177</title>
        <!-- Titulares en Cinzel (capitular romana), cuerpo en Lato.
         preconnect evita el round-trip extra al pedir la hoja de estilos. -->
    <?php include __DIR__ . '/includes/theme-inline.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Lato:wght@300;400;700&display=swap">
    <link rel="stylesheet" href="css/tokens.css">
    <link rel="stylesheet" href="css/components.css">
    <link rel="stylesheet" href="css/icons.css">
    <link rel="stylesheet" href="css/theme-toggle.css">
    <link rel="stylesheet" href="css/nav.css">
    <link rel="stylesheet" href="css/closing.css">
</head>
<body>
<div class="shell">
<?php $nav_actual = 'gracias'; include __DIR__ . '/includes/site-header.php'; ?>

        <main class="shell__main closing" id="contenido">

        <div class="closing__card">
            <svg class="closing__icon" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 12.5l5 5L20 6.5"/>
            </svg>

            <h1 class="closing__title"><?= htmlspecialchars($titulo) ?></h1>

            <p class="closing__lead">
                Hola <strong><?= htmlspecialchars($nombre_usuario) ?></strong>,
                tu participación es muy importante para nosotros.
            </p>

            <ol class="closing__votes">
                <li class="closing__vote">
                    <span class="closing__check" aria-hidden="true"><svg class="icon icon--sm"><use href="#i-check"></use></svg></span>
                    Votaste el proyecto de 1.º año
                </li>
                <li class="closing__vote">
                    <span class="closing__check" aria-hidden="true"><svg class="icon icon--sm"><use href="#i-check"></use></svg></span>
                    Votaste el proyecto de 2.º año
                </li>
                <li class="closing__vote">
                    <span class="closing__check" aria-hidden="true"><svg class="icon icon--sm"><use href="#i-check"></use></svg></span>
                    Votaste el proyecto de 3.º año
                </li>
            </ol>

            <p class="closing__note">
                Una vez terminada la votación, un docente dará a conocer el proyecto ganador
                de cada año y el ganador general entre todos los grupos.
            </p>

            <a class="btn btn--primary" href="logout.php">Cerrar sesión</a>
        </div>

    </main>

    <?php include __DIR__ . '/includes/site-footer.php'; ?>
</div>

<?php /* theme.js lo carga site-header.php, que es donde esta el boton. */ ?>
<?php include __DIR__ . '/includes/iconos.php'; ?>
</body>
</html>
