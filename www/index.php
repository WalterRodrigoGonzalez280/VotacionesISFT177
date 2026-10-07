<?php
/**
 * Landing publica de bienvenida.
 *
 * No exige sesion: es la puerta de entrada del sitio. Si el visitante ya
 * esta logueado, el boton principal lo manda a votacion.php o a admin.php.
 *
 * Decision de diseno: la landing NO muestra los ganadores, ni al terminar.
 * Ver el conteo mientras la votacion esta abierta sesga el voto de quien llega
 * despues, y una vez terminada tampoco es de este lugar: el resultado es del
 * panel de administracion, que es donde ya se calcula y se muestra (pestana
 * "Ganadores"). Duplicarlo aca era una segunda fuente de la verdad.
 */
session_start();
require_once __DIR__ . '/conexion/conexion.php';

$es_admin = !empty($_SESSION['es_admin']) && $_SESSION['es_admin'] === true;
$logueado = !empty($_SESSION['logueado']) && $_SESSION['logueado'] === true;

$estado = 'desconocido';
$ventana = null;
$error_bd = false;

try {
    $stmt = $pdo->prepare(
        'SELECT inicio_votacion, fin_votacion
           FROM configuracion_votacion
          WHERE activo = 1
          ORDER BY id DESC
          LIMIT 1'
    );
    $stmt->execute();
    $ventana = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($ventana) {
        $ahora = new DateTime();
        $inicio = new DateTime($ventana['inicio_votacion']);
        $fin = new DateTime($ventana['fin_votacion']);

        if ($ahora < $inicio) {
            $estado = 'pendiente';
        } elseif ($ahora > $fin) {
            $estado = 'finalizada';
        } else {
            $estado = 'abierta';
        }
    }

    /* Los ganadores NO se consultan aca: la landing no los muestra. El calculo
       queda solo en api.php (accion "calcular_ganadores"), que es la que usa la
       pestana "Ganadores" del panel. Antes esta pagina arrastraba su propia
       copia del calculo, que era una segunda fuente de la verdad. */
} catch (PDOException $e) {
    error_log('Landing: ' . $e->getMessage());
    $error_bd = true;
}

$estados = [
    'abierta' => [
        'icono' => 'check', 'titulo' => 'Votación abierta',
        'detalle' => 'Podés ingresar y votar.', 'clase' => 'badge--open',
    ],
    'pendiente' => [
        'icono' => 'reloj', 'titulo' => 'Todavía no comenzó',
        'detalle' => 'Abre en la fecha indicada.', 'clase' => 'badge--soon',
    ],
    'finalizada' => [
        'icono' => 'bloqueo', 'titulo' => 'Votación finalizada',
        'detalle' => 'Ya no se pueden emitir votos.', 'clase' => 'badge--closed',
    ],
    'desconocido' => [
        'icono' => 'calendario', 'titulo' => 'Votación sin configurar',
        'detalle' => 'Todavía no se definieron las fechas.', 'clase' => 'badge--soon',
    ],
];

if ($es_admin) {
    $cta = ['texto' => 'Ir al panel de administración', 'href' => 'admin.php', 'icono' => 'config'];
} elseif ($logueado) {
    $cta = [
        'texto' => $estado === 'abierta' ? 'Ir a votar' : 'Ir a la votación',
        'href' => 'votacion.php',
        'icono' => 'urna',
    ];
} else {
    $cta = ['texto' => 'Ingresar para votar', 'href' => 'login/login.php', 'icono' => 'llave'];
}

// El año sale de la tabla anio_votacion (ver anioVotacion() en conexion.php).
// Antes estaba escrito aca a mano, que es exactamente el tipo de dato que
// queda viejo en un archivo que nadie vuelve a abrir.
$titulo = 'Exposición de Proyectos ' . anioVotacion();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($titulo) ?> | ISFT 177</title>
    <meta name="description" content="Exposición de proyectos del Técnico en Análisis de Sistemas del ISFT 177. Votación de proyectos por año.">
    <!-- Titulares en Cinzel (capitular romana), cuerpo en Lato.
         Las tipografias van en un parcial, con la precarga de los .woff2 y la
         hoja de @font-face: ver includes/fonts.php. -->
    <?php include __DIR__ . '/includes/theme-inline.php'; ?>
    <?php include __DIR__ . '/includes/fonts.php'; ?>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/landing.css">
</head>
<body>
<div class="shell">
    <a class="skip-link" href="#contenido">Ir al contenido</a>

    <?php $nav_actual = 'inicio'; include __DIR__ . '/includes/site-header.php'; ?>

    <main class="shell__main" id="contenido">
        <div class="wrap landing">

            <section class="hero" aria-labelledby="hero-title">
                <p class="hero__eyebrow">
                    <svg class="icon" aria-hidden="true"><use href="#i-banda"></use></svg>
                    Prácticas Profesionalizantes
                </p>

                <h1 class="hero__title" id="hero-title"><?= htmlspecialchars($titulo) ?></h1>

                <p class="hero__lead">
                    Cada persona emite <strong>un voto</strong> para los proyectos
                    de 1.º, 2.º y 3.º año.
                </p>

                <div class="hero__state">
                    <p class="badge badge--lg <?= $estados[$estado]['clase'] ?>">
                        <svg class="icon" aria-hidden="true">
                            <use href="#i-<?= $estados[$estado]['icono'] ?>"></use>
                        </svg>
                        <?= htmlspecialchars($estados[$estado]['titulo']) ?>
                    </p>
                    <p class="hero__detail"><?= htmlspecialchars($estados[$estado]['detalle']) ?></p>
                </div>

                <?php if ($ventana): ?>
                    <p class="hero__range">
                        <svg class="icon icon--sm" aria-hidden="true"><use href="#i-calendario"></use></svg>
                        Del
                        <time datetime="<?= htmlspecialchars($ventana['inicio_votacion']) ?>">
                            <?= (new DateTime($ventana['inicio_votacion']))->format('d/m/Y H:i') ?>
                        </time>
                        al
                        <time datetime="<?= htmlspecialchars($ventana['fin_votacion']) ?>">
                            <?= (new DateTime($ventana['fin_votacion']))->format('d/m/Y H:i') ?>
                        </time>
                    </p>
                <?php endif; ?>

                <div class="hero__actions">
                    <a class="btn btn--primary" href="<?= htmlspecialchars($cta['href']) ?>">
                        <svg class="icon" aria-hidden="true"><use href="#i-<?= $cta['icono'] ?>"></use></svg>
                        <?= htmlspecialchars($cta['texto']) ?>
                    </a>
                    <?php if (!$logueado): ?>
                        <a class="btn btn--secondary" href="registro/registro.php">
                            <svg class="icon" aria-hidden="true"><use href="#i-usuario-mas"></use></svg>
                            Crear una cuenta
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($error_bd): ?>
                    <p class="alert alert--error hero__alert" role="status">
                        <svg class="icon" aria-hidden="true"><use href="#i-alerta"></use></svg>
                        <span>No pudimos consultar el estado de la votación. Probá de nuevo en unos minutos.</span>
                    </p>
                <?php endif; ?>
            </section>

            <section class="card landing__block" aria-labelledby="que-es">
                <h2 class="card__title" id="que-es">
                    <svg class="icon" aria-hidden="true"><use href="#i-documento"></use></svg>
                    ¿De qué se trata?
                </h2>
                <div class="card__body">
                    <p>
                        Los tres grupos de Prácticas Profesionalizantes presentaron durante el
                        ciclo lectivo su <strong>plataforma, proyecto o página</strong>
                        desarrollada en la carrera.
                    </p>
                    <p>
                        La votación está dirigida a <strong>alumnos, docentes, autores y
                        participantes</strong> de la exposición, para elegir qué proyecto se
                        destacó en cada año.
                    </p>
                </div>
            </section>

            <section class="card landing__block" aria-labelledby="modalidad">
                <h2 class="card__title" id="modalidad">
                    <svg class="icon" aria-hidden="true"><use href="#i-urna"></use></svg>
                    Modalidad
                </h2>
                <ol class="steps">
                    <li class="steps__item"><span>Un voto para los proyectos de <strong>1.º año</strong>.</span></li>
                    <li class="steps__item"><span>Un voto para los proyectos de <strong>2.º año</strong>.</span></li>
                    <li class="steps__item"><span>Un voto para los proyectos de <strong>3.º año</strong>.</span></li>
                </ol>
                <p class="landing__note">
                    <svg class="icon icon--sm" aria-hidden="true"><use href="#i-info"></use></svg>
                    Son tres votos en total, uno por grupo. No se pueden repetir ni cambiar.
                </p>
            </section>

            <?php /* No va seccion de ganadores aca a proposito: el resultado es
                      del panel de administracion. Ver el comentario de arriba
                      del archivo y la pestana "Ganadores" de admin.php. */ ?>

            <section class="card landing__block" aria-labelledby="como-participar">
                <h2 class="card__title" id="como-participar">
                    <svg class="icon" aria-hidden="true"><use href="#i-personas"></use></svg>
                    Cómo participar
                </h2>
                <ol class="howto">
                    <li class="howto__step">
                        <svg class="icon icon--lg howto__icon" aria-hidden="true"><use href="#i-usuario-mas"></use></svg>
                        <h3 class="howto__title">Creá tu cuenta</h3>
                        <p class="howto__text">Registrate con tu nombre y tu correo.</p>
                    </li>
                    <li class="howto__step">
                        <svg class="icon icon--lg howto__icon" aria-hidden="true"><use href="#i-enviar"></use></svg>
                        <h3 class="howto__title">Activá la cuenta</h3>
                        <p class="howto__text">Te enviamos un código de 4 dígitos por correo.</p>
                    </li>
                    <li class="howto__step">
                        <svg class="icon icon--lg howto__icon" aria-hidden="true"><use href="#i-urna"></use></svg>
                        <h3 class="howto__title">Votá</h3>
                        <p class="howto__text">Elegí un proyecto por cada año.</p>
                    </li>
                </ol>
            </section>

        </div>
    </main>

    <?php include __DIR__ . '/includes/site-footer.php'; ?>
</div>


<?php include __DIR__ . '/includes/iconos.php'; ?>
</body>
</html>
