<?php
session_start();
require_once 'conexion/conexion.php';

$logueado = !empty($_SESSION['logueado']) && $_SESSION['logueado'] === true;
$es_admin = !empty($_SESSION['es_admin']) && $_SESSION['es_admin'] === true;

// La votacion es PUBLICA: se ven los proyectos sin iniciar sesion. Antes esta
// pagina redirigia al login, con lo cual nadie sin cuenta podia ver siquiera
// que se estaba votando. Ahora sin sesion se ven las tarjetas y se les pide
// iniciar sesion para emitir el voto.
//
// El backend no cambia: api.php sigue rechazando el voto sin sesion con 401.

$votacion_abierta = true;
$tiempo_restante = null;
$mensaje_espera = null;
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
    $configuracion = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($configuracion) {
        $ahora = new DateTime();
        $inicio = new DateTime($configuracion['inicio_votacion']);
        $fin = new DateTime($configuracion['fin_votacion']);

        if ($ahora < $inicio) {
            $votacion_abierta = false;
            $intervalo = $ahora->diff($inicio);
            $tiempo_restante = [
                'dias' => $intervalo->days,
                'horas' => $intervalo->h,
                'minutos' => $intervalo->i,
                'segundos' => $intervalo->s,
            ];
            $mensaje_espera = 'La votación comenzará el ' . $inicio->format('d/m/Y \a \l\a\s H:i');
        } elseif ($ahora > $fin) {
            $votacion_abierta = false;
            $mensaje_espera = 'La votación ha finalizado';
        }
    }
} catch (PDOException $e) {
    error_log('Votación: horario: ' . $e->getMessage());

    /* Sin esto la pagina se dibuja IGUAL que con la votacion abierta, porque
       $votacion_abierta arranca en true: con la base caida alguien vota, el
       backend le responde que no, y no entiende por que. Aca se cierra la
       votacion y se avisa. */
    $error_bd = true;
    $votacion_abierta = false;
}

// Solo tiene sentido con sesion: sin ella no hay votos propios que mirar ni
// a quien mandar a gracias.php.
if ($logueado && !empty($_SESSION['usuario_id']) && !$es_admin) {
    try {
        $stmt = $pdo->prepare(
            'SELECT votos_1er_ano, votos_2do_ano, votos_3er_ano
               FROM usuarios
              WHERE id = ?'
        );
        $stmt->execute([$_SESSION['usuario_id']]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario
            && $usuario['votos_1er_ano'] == 1
            && $usuario['votos_2do_ano'] == 1
            && $usuario['votos_3er_ano'] == 1
        ) {
            header('Location: gracias.php');
            exit;
        }
    } catch (PDOException $e) {
        error_log('Votación: votos del usuario: ' . $e->getMessage());
    }
}

$grupos_organizados = [];

try {
    $stmt = $pdo->prepare(
        'SELECT s.id, s.nombre, s.anio_id AS grupo_id, g.nombre AS grupo_nombre, COUNT(v.id) AS votos
           FROM proyectos s
           JOIN anios g ON s.anio_id = g.id
           LEFT JOIN votos v ON s.id = v.proyecto_id
          WHERE s.activo = TRUE
          GROUP BY s.id, s.nombre, s.anio_id, g.nombre
          ORDER BY s.anio_id, s.id'
    );
    $stmt->execute();

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $subgrupo) {
        $gid = $subgrupo['grupo_id'];
        if (!isset($grupos_organizados[$gid])) {
            $grupos_organizados[$gid] = ['nombre' => $subgrupo['grupo_nombre'], 'subgrupos' => []];
        }
        $grupos_organizados[$gid]['subgrupos'][] = $subgrupo;
    }
} catch (PDOException $e) {
    error_log('Votación: grupos: ' . $e->getMessage());
}

$nombre_usuario = $_SESSION['usuario_nombre'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Votación | ISFT 177</title>
    <!-- Titulares en Cinzel (capitular romana), cuerpo en Lato.
         Las tipografias van en un parcial, con la precarga de los .woff2 y la
         hoja de @font-face: ver includes/fonts.php. -->
    <?php include __DIR__ . '/includes/theme-inline.php'; ?>
    <?php include __DIR__ . '/includes/fonts.php'; ?>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/votacion.css">
</head>
<body>
<div class="shell">
    <a class="skip-link" href="#contenido">Ir al contenido</a>

    <?php $nav_actual = 'votacion'; include __DIR__ . '/includes/site-header.php'; ?>

    <main class="shell__main" id="contenido">
        <div class="wrap wrap--wide">

            <h1 class="page__title">Elegí un proyecto por año</h1>

                <?php if ($es_admin): ?>
                    <p class="alert alert--info page__note" role="status">
                        <svg class="icon icon--sm" aria-hidden="true"><use href="#i-config"></use></svg>
                        <span>
                            Estás como administrador: podés ver la votación pero no emitir votos.
                            Usá el panel para gestionar usuarios y proyectos.
                        </span>
                    </p>
                <?php elseif (!$logueado): ?>
                    <p class="alert alert--info page__note" role="status">
                        <svg class="icon icon--sm" aria-hidden="true"><use href="#i-bloqueo"></use></svg>
                        <span>
                            Podés ver los proyectos, pero para emitir tu voto necesitás
                            <a class="link" href="/login/login.php">iniciar sesión</a>.
                            Si no tenés cuenta,
                            <a class="link" href="/registro/registro.php">creá una</a>.
                        </span>
                    </p>
                <?php endif; ?>

            <?php if ($error_bd): ?>
                <?php /* Va PRIMERO porque con la base caida tambien se pone
                          $votacion_abierta = false: si no, caeria en el bloque de
                          abajo, que habla de fechas y muestro un texto vacio. */ ?>
                <section class="card waiting" aria-labelledby="sin-bd">
                    <h2 class="card__title" id="sin-bd">No pudimos consultar la votación</h2>
                    <p class="waiting__lead">
                        El sistema no está respondiendo en este momento, así que
                        por ahora no se puede votar. Tu voto no se registró:
                        intentá de nuevo en unos minutos.
                    </p>
                </section>
            <?php elseif (!$votacion_abierta): ?>
                <section class="card waiting" aria-labelledby="espera">
                    <h2 class="card__title" id="espera">Votación no disponible</h2>
                    <p class="waiting__lead"><?= htmlspecialchars($mensaje_espera) ?></p>

                    <?php if ($tiempo_restante): ?>
                        <div class="countdown" data-countdown aria-label="Tiempo restante para el inicio">
                            <div class="countdown__cell">
                                <span class="countdown__value" data-dias><?= (int)$tiempo_restante['dias'] ?></span>
                                <span class="countdown__label">Días</span>
                            </div>
                            <div class="countdown__cell">
                                <span class="countdown__value" data-horas><?= (int)$tiempo_restante['horas'] ?></span>
                                <span class="countdown__label">Horas</span>
                            </div>
                            <div class="countdown__cell">
                                <span class="countdown__value" data-minutos><?= (int)$tiempo_restante['minutos'] ?></span>
                                <span class="countdown__label">Min</span>
                            </div>
                            <div class="countdown__cell">
                                <span class="countdown__value" data-segundos><?= (int)$tiempo_restante['segundos'] ?></span>
                                <span class="countdown__label">Seg</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            <?php else: ?>

                <?php if ($logueado && !$es_admin): ?>
                    <section class="mystatus card" aria-labelledby="mi-estado">
                        <h2 class="visually-hidden">Mi estado de votación</h2>
                        <div class="mystatus__row">
                            <p class="mystatus__name">
                                <span class="mystatus__label">Usuario</span>
                                <strong data-user-name><?= htmlspecialchars($nombre_usuario) ?></strong>
                            </p>
                            <ul class="mystatus__votes">
                                <li class="mystatus__vote" data-vote-status="1">
                                    <svg class="icon icon--sm" aria-hidden="true"><use href="#i-circulo"></use></svg> 1.º año
                                </li>
                                <li class="mystatus__vote" data-vote-status="2">
                                    <svg class="icon icon--sm" aria-hidden="true"><use href="#i-circulo"></use></svg> 2.º año
                                </li>
                                <li class="mystatus__vote" data-vote-status="3">
                                    <svg class="icon icon--sm" aria-hidden="true"><use href="#i-circulo"></use></svg> 3.º año
                                </li>
                            </ul>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (empty($grupos_organizados)): ?>
                    <p class="card">Todavía no hay proyectos cargados.</p>
                <?php endif; ?>

                <?php foreach ($grupos_organizados as $grupo_id => $grupo): ?>
                    <section class="yeargroup" aria-labelledby="grupo-<?= (int)$grupo_id ?>">
                        <h2 class="yeargroup__title" id="grupo-<?= (int)$grupo_id ?>">
                            <?= htmlspecialchars($grupo['nombre']) ?>
                        </h2>

                        <ul class="projects" data-group="<?= (int)$grupo_id ?>">
                            <?php foreach ($grupo['subgrupos'] as $s): ?>
                                <li>
                                    <article class="project"
                                             data-project-id="<?= (int)$s['id'] ?>"
                                             data-group-id="<?= (int)$grupo_id ?>">
                                        <h3 class="project__name"><?= htmlspecialchars($s['nombre']) ?></h3>

                                        <?php // El contador es solo del admin: verlo
                                              // mientras se votaria sesga a quien llega
                                              // despues. El SELECT sigue calculandolo
                                              // (cuesta 1 ms); solo se deja de pintar. ?>
                                        <?php if ($es_admin): ?>
                                            <p class="project__count" data-vote-count="<?= (int)$s['id'] ?>">
                                                <?= (int)$s['votos'] ?> votos
                                            </p>
                                        <?php endif; ?>

                                        <?php if ($logueado && !$es_admin): ?>
                                            <button class="btn btn--primary project__cta"
                                                    type="button"
                                                    data-vote
                                                    data-subgrupo-id="<?= (int)$s['id'] ?>"
                                                    data-grupo-id="<?= (int)$grupo_id ?>"
                                                    data-proyecto="<?= htmlspecialchars($s['nombre']) ?>"
                                                    data-anio="<?= htmlspecialchars($grupo['nombre']) ?>">
                                                <svg class="icon" aria-hidden="true"><use href="#i-urna"></use></svg>
                                                Votar
                                            </button>
                                        <?php elseif (!$es_admin): ?>
                                            <a class="btn btn--secondary project__cta"
                                               href="/login/login.php">
                                                <svg class="icon" aria-hidden="true"><use href="#i-llave"></use></svg>
                                                Ingresar para votar
                                            </a>
                                        <?php endif; ?>
                                    </article>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endforeach; ?>

                <p class="messages" role="status" aria-live="polite" data-messages></p>
            <?php endif; ?>
        </div>
    </main>

    <?php include __DIR__ . '/includes/site-footer.php'; ?>
</div>

<script type="module" src="js/votacion.js"></script>

<!-- Confirmacion antes de emitir el voto. <dialog> nativo: foco atrapado,
     Escape y un alto accesible sin una linea de JS. El nombre del proyecto
     lo completa votacion.js al abrirlo. -->
<dialog class="dialog" data-confirm>
    <div class="dialog__form">
        <h2 class="dialog__title">¿Confirmás tu voto?</h2>
        <p class="dialog__text">
            Vas a elegir <strong data-confirm-proyecto></strong>
            del <strong data-confirm-anio></strong>.
        </p>
        <p class="dialog__warn">
            <svg class="icon icon--sm" aria-hidden="true"><use href="#i-alerta"></use></svg>
            Tu voto no se puede cambiar ni repetir.
        </p>
        <div class="dialog__actions">
            <button class="btn btn--secondary" type="button" data-confirm-cancel>Cancelar</button>
            <button class="btn btn--primary" type="button" data-confirm-accept>
                Confirmar voto
            </button>
        </div>
    </div>
</dialog>

<?php include __DIR__ . '/includes/iconos.php'; ?>
</body>
</html>
