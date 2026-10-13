<?php
session_start();
require_once __DIR__ . '/../core/conexion.php';

if (empty($_SESSION['es_admin']) || $_SESSION['es_admin'] !== true) {
    header('Location: ../public/votacion.php');
    exit;
}

$currentUserId = (int)($_SESSION['usuario_id'] ?? 0);

// Las pestañas validas, para no dejar que ?tab= invente una.
$tabs = [
    'usuarios' => 'Usuarios',
    'subgrupos' => 'Grupos',
    'ganadores' => 'Ganadores',
    'configuracion' => 'Configuración',
];
$tab_actual = isset($_GET['tab']) && isset($tabs[$_GET['tab']]) ? $_GET['tab'] : 'usuarios';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel de administración | ISFT 177</title>
    <!-- Titulares en Cinzel (capitular romana), cuerpo en Lato.
         Las tipografias van en un parcial, con la precarga de los .woff2 y la
         hoja de @font-face: ver includes/fonts.php. -->
    <?php include __DIR__ . '/../includes/theme-inline.php'; ?>
    <?php include __DIR__ . '/../includes/fonts.php'; ?>
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="shell">
    <a class="skip-link" href="#contenido">Ir al contenido</a>

    <?php $nav_actual = 'admin'; include __DIR__ . '/../includes/site-header.php'; ?>

    <main class="shell__main" id="contenido">
        <div class="wrap wrap--wide">
            <h1 class="page__title">Administración</h1>

            <!-- Tabs: aca si hace falta aria-* porque HTML nativo no tiene
                 el patron tablist/tab/tabpanel ni la navegacion con flechas. -->
            <div class="tabs">
                <div class="tabs__list" role="tablist" aria-label="Secciones del panel">
                    <?php foreach ($tabs as $clave => $etiqueta): ?>
                        <button
                            class="tabs__tab"
                            type="button"
                            role="tab"
                            id="tab-<?= htmlspecialchars($clave) ?>"
                            aria-controls="panel-<?= htmlspecialchars($clave) ?>"
                            aria-selected="<?= $clave === $tab_actual ? 'true' : 'false' ?>"
                            tabindex="<?= $clave === $tab_actual ? '0' : '-1' ?>"
                            data-tab="<?= htmlspecialchars($clave) ?>">
                            <?= htmlspecialchars($etiqueta) ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Usuarios -->
                <section class="tabs__panel"
                         role="tabpanel"
                         id="panel-usuarios"
                         aria-labelledby="tab-usuarios"
                         data-panel="usuarios"
                         <?= $tab_actual !== 'usuarios' ? 'hidden' : '' ?>>
                    <div class="panel__head">
                        <h2>Gestión de usuarios</h2>
                        <div class="panel__actions">
                            <button class="btn btn--secondary btn--sm" type="button" data-action="reload-users">
                                Actualizar
                            </button>
                            <button class="btn btn--ghost btn--sm" type="button" data-action="clean-orphans">
                                Limpiar huérfanos
                            </button>
                            <button class="btn btn--danger btn--sm" type="button" data-action="reset-all">
                                Borrar todos los votos
                            </button>
                        </div>
                    </div>
                    <div data-users-list>
                        <p class="panel__loading">Cargando usuarios…</p>
                    </div>
                </section>

                <!-- Grupos -->
                <section class="tabs__panel"
                         role="tabpanel"
                         id="panel-subgrupos"
                         aria-labelledby="tab-subgrupos"
                         data-panel="subgrupos"
                         <?= $tab_actual !== 'subgrupos' ? 'hidden' : '' ?>>
                    <div class="panel__head">
                        <h2>Gestión de grupos</h2>
                        <div class="panel__actions">
                            <button class="btn btn--primary btn--sm" type="button" data-action="open-add">
                                Agregar proyecto
                            </button>
                            <button class="btn btn--secondary btn--sm" type="button" data-action="reload-groups">
                                Actualizar
                            </button>
                        </div>
                    </div>
                    <div data-groups-list>
                        <p class="panel__loading">Cargando grupos…</p>
                    </div>
                </section>

                <!-- Ganadores -->
                <section class="tabs__panel"
                         role="tabpanel"
                         id="panel-ganadores"
                         aria-labelledby="tab-ganadores"
                         data-panel="ganadores"
                         <?= $tab_actual !== 'ganadores' ? 'hidden' : '' ?>>
                    <div class="panel__head">
                        <h2>Resultados</h2>
                        <div class="panel__actions">
                            <button class="btn btn--secondary btn--sm" type="button" data-action="reload-winners">
                                Actualizar
                            </button>
                        </div>
                    </div>
                    <div data-winners>
                        <p class="panel__loading">Calculando ganadores…</p>
                    </div>
                </section>

                <!-- Configuracion -->
                <section class="tabs__panel"
                         role="tabpanel"
                         id="panel-configuracion"
                         aria-labelledby="tab-configuracion"
                         data-panel="configuracion"
                         <?= $tab_actual !== 'configuracion' ? 'hidden' : '' ?>>
                    <div class="panel__head">
                        <h2>Horario de votación</h2>
                        <div class="panel__actions">
                            <button class="btn btn--secondary btn--sm" type="button" data-action="reload-config">
                                Actualizar
                            </button>
                        </div>
                    </div>
                    <div data-config>
                        <p class="panel__loading">Cargando configuración…</p>
                    </div>
                </section>
            </div>

        </div>
    </main>

    <?php include __DIR__ . '/../includes/site-footer.php'; ?>
</div>

<!-- <dialog> nativo: modal, foco atrapado y Escape sin una linea de JS -->
<dialog class="dialog" data-dialog>
    <form method="dialog" class="dialog__form">
        <h2 class="dialog__title">Agregar proyecto</h2>

        <div class="field">
            <label class="field__label" for="grupo_id">Año</label>
            <select class="field__input" id="grupo_id" name="grupo_id" required>
                <option value="1">1.º año</option>
                <option value="2">2.º año</option>
                <option value="3">3.º año</option>
            </select>
        </div>

        <div class="field">
            <label class="field__label" for="nombre">Nombre del proyecto</label>
            <input class="field__input" type="text" id="nombre" name="nombre" maxlength="150" required>
        </div>

        <div class="dialog__actions">
            <!-- `formnovalidate` NO es opcional, aunque el boton no manda nada:
                 el form es method="dialog" y la validacion de constraints corre
                 ANTES de cerrar el dialog. Con "Nombre del proyecto" vacio -- que
                 es como esta siempre al abrir -- el navegador bloquea el envio,
                 muestra el burbujon y el dialog no se cierra: Cancelar queda
                 muerto. Medido en Chromium headless: sin el atributo dialog.open
                 sigue en true, con el atributo pasa a false.

                 Ojo con "arreglarlo" sacando el required del input: la
                 validacion del form no es solo la del navegador, `required`
                 tambien le avisa a la gente que usa lector de pantalla. -->
            <button class="btn btn--secondary" type="submit" value="cancel" formnovalidate>Cancelar</button>
            <button class="btn btn--primary" type="button" data-action="add-subgroup">Agregar</button>
        </div>
    </form>
</dialog>

<script>
  window.CURRENT_USER_ID = <?= json_encode($currentUserId) ?>;
</script>
<script type="module" src="../assets/js/admin.js"></script>

<?php include __DIR__ . '/../includes/iconos.php'; ?>
</body>
</html>
