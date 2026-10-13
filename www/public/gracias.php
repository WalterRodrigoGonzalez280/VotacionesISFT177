<?php
session_start();
require_once __DIR__ . '/../core/conexion.php';

if (empty($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    header('Location: ../auth/login.php');
    exit;
}

if (!empty($_SESSION['es_admin']) && $_SESSION['es_admin'] === true) {
    header('Location: ../admin/');
    exit;
}

$nombre_usuario = '';
$votos = ['votos_1er_ano' => 0, 'votos_2do_ano' => 0, 'votos_3er_ano' => 0];

if (!empty($_SESSION['usuario_id'])) {
    try {
        $stmt = $pdo->prepare(
            'SELECT nombre, votos_1er_ano, votos_2do_ano, votos_3er_ano
               FROM usuarios
              WHERE id = ?'
        );
        $stmt->execute([$_SESSION['usuario_id']]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        $completo = $usuario
            && $usuario['votos_1er_ano'] == 1
            && $usuario['votos_2do_ano'] == 1
            && $usuario['votos_3er_ano'] == 1;

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
         Las tipografias van en un parcial, con la precarga de los .woff2 y la
         hoja de @font-face: ver includes/fonts.php. -->
    <?php include __DIR__ . '/../includes/theme-inline.php'; ?>
    <?php include __DIR__ . '/../includes/fonts.php'; ?>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/closing.css">
</head>
<body>
<div class="shell">
<?php $nav_actual = 'gracias'; include __DIR__ . '/../includes/site-header.php'; ?>

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

            <a class="btn btn--primary" href="../logout.php">Cerrar sesión</a>
        </div>

    </main>

    <?php include __DIR__ . '/../includes/site-footer.php'; ?>
</div>

<?php /* theme.js lo carga site-header.php, que es donde esta el boton. */ ?>
<?php include __DIR__ . '/../includes/iconos.php'; ?>
</body>
</html>
