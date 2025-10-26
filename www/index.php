<?php
session_start();
require_once 'conexion/conexion.php';
if (empty($_SESSION["logueado"]) || $_SESSION["logueado"] !== true) {
    header('Location: login/login.php');
    exit;
}

// Verificar horario de votación
$votacion_abierta = true;
$tiempo_restante = null;
$mensaje_espera = null;

try {
    $stmt = $pdo->prepare("SELECT inicio_votacion, fin_votacion FROM configuracion_votacion WHERE activo = 1 ORDER BY id DESC LIMIT 1");
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
                'segundos' => $intervalo->s
            ];
            $mensaje_espera = "La votación comenzará el " . $inicio->format('d/m/Y \a \l\a\s H:i');
        } else if ($ahora > $fin) {
            $votacion_abierta = false;
            $mensaje_espera = "La votación ha finalizado";
        }
    }
} catch (PDOException $e) {
    error_log("Error verificando horario de votación: " . $e->getMessage());
}

// NO redirigir automáticamente a admin.php - permitir que el admin vea la página de votos
// Los administradores pueden usar el botón en admin.php para volver al panel

// Si el usuario es administrador, mostrar mensaje especial
$es_admin = isset($_SESSION['es_admin']) && $_SESSION['es_admin'] === true;

// Verificar si el usuario ya completó sus 3 votos (solo para usuarios NO admin)
if (isset($_SESSION['usuario_id']) && !$es_admin) {
    try {
        $stmt = $pdo->prepare("SELECT votos_1er_año, votos_2do_año, votos_3er_año FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['usuario_id']]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario) {
            // Verificar si todos los votos están completos
            $votos_completos = ($usuario['votos_1er_año'] == 1 && $usuario['votos_2do_año'] == 1 && $usuario['votos_3er_año'] == 1);
            
            if ($votos_completos) {
                // El usuario ya completó sus votos, redirigir a la página de agradecimiento
                header('Location: gracias.php');
                exit;
            }
        }
    } catch (PDOException $e) {
        error_log("Error verificando votos del usuario: " . $e->getMessage());
    }
}

// Obtener grupos y subgrupos
try {
    $stmt = $pdo->prepare("
        SELECT s.id, s.nombre, s.grupo_id, g.nombre as grupo_nombre,
               COUNT(v.id) as votos
        FROM subgrupos s 
        JOIN grupos g ON s.grupo_id = g.id 
        LEFT JOIN votos v ON s.id = v.subgrupo_id
        WHERE s.activo = TRUE 
        GROUP BY s.id, s.nombre, s.grupo_id, g.nombre
        ORDER BY s.grupo_id, s.id
    ");
    $stmt->execute();
    $subgrupos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $grupos_organizados = [];
    foreach ($subgrupos as $subgrupo) {
        $grupo_id = $subgrupo['grupo_id'];
        if (!isset($grupos_organizados[$grupo_id])) {
            $grupos_organizados[$grupo_id] = [
                'nombre' => $subgrupo['grupo_nombre'],
                'subgrupos' => []
            ];
        }
        $grupos_organizados[$grupo_id]['subgrupos'][] = $subgrupo;
    }
} catch (PDOException $e) {
    $grupos_organizados = [];
    error_log("Error obteniendo grupos: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Votación en Tiempo Real - ISFT 177</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logout-header-button">
                <?php if (isset($_SESSION['es_admin']) && $_SESSION['es_admin'] === true): ?>
                    <button class="admin-panel-button" onclick="volverAlPanel()">🔧 Panel de Administración</button>
                <?php endif; ?>
                <button class="logout-button-header" onclick="cerrarSesion()">Cerrar Sesión</button>
            </div>
            <div class="logo-container-header">
                <img src="Logo/logoisft177.png" alt="Logo ISFT 177" class="logo-header">
            </div>
            <h1>🗳️ Sistema de Votación en Tiempo Real</h1>
            <p>ISFT 177 - Instituto Superior de Formación Técnica</p>
        </div>
        
        <div id="eventInfoSection" class="event-info-section">
            <div class="event-info-card">
                <h2>🎓 Sistema de Votación – Carrera Técnico en Análisis de Sistemas</h2>
                <div class="event-description">
                    <p>Este sistema de votación se encuentra destinado a la <strong>exposición de proyectos</strong> de la carrera Técnico en Analista de Sistemas, en la cual participan los tres grupos de Prácticas Profesionalizantes: <strong>1º, 2º y 3º año</strong>.</p>
                    <p>Cada grupo ha presentado su <strong>plataforma, proyecto o página desarrollada</strong> durante el ciclo lectivo.</p>
                    <p>La votación está dirigida a los alumnos, profesores, autores y participantes de la exposición, quienes podrán elegir cuál proyecto consideran que se destacó en cada año.</p>
                </div>
                
                <div class="modalidad-section">
                    <h3>📝 Modalidad de Votación</h3>
                    <div class="modalidad-list">
                        <div class="modalidad-item">
                            <span class="modalidad-icon">1️⃣</span>
                            <p>Cada usuario podrá emitir <strong>1 (un) voto</strong> para los proyectos de <strong>1º año</strong>.</p>
                        </div>
                        <div class="modalidad-item">
                            <span class="modalidad-icon">2️⃣</span>
                            <p>Cada usuario podrá emitir <strong>1 (un) voto</strong> para los proyectos de <strong>2º año</strong>.</p>
                        </div>
                        <div class="modalidad-item">
                            <span class="modalidad-icon">3️⃣</span>
                            <p>Cada usuario podrá emitir <strong>1 (un) voto</strong> para los proyectos de <strong>3º año</strong>.</p>
                        </div>
                    </div>
                </div>
                
                <div class="resultado-info">
                    <p>📌 Una vez finalizada la votación, un docente designado dará a conocer, mediante la proyección en pantalla, el <strong>proyecto ganador de cada año</strong> y, además, el <strong>proyecto ganador general</strong> entre todos los grupos participantes.</p>
                </div>
            </div>
        </div>

        <?php if (!$votacion_abierta): ?>
        <div class="waiting-section">
            <div class="waiting-container">
                <div class="waiting-icon">⏳</div>
                <h2>La votación aún no está disponible</h2>
                <p class="waiting-message"><?= htmlspecialchars($mensaje_espera) ?></p>
                <?php if ($tiempo_restante): ?>
                <div class="countdown-container">
                    <div class="countdown-item">
                        <span class="countdown-number" id="dias"><?= $tiempo_restante['dias'] ?></span>
                        <span class="countdown-label">Días</span>
                    </div>
                    <div class="countdown-separator">:</div>
                    <div class="countdown-item">
                        <span class="countdown-number" id="horas"><?= str_pad($tiempo_restante['horas'], 2, '0', STR_PAD_LEFT) ?></span>
                        <span class="countdown-label">Horas</span>
                    </div>
                    <div class="countdown-separator">:</div>
                    <div class="countdown-item">
                        <span class="countdown-number" id="minutos"><?= str_pad($tiempo_restante['minutos'], 2, '0', STR_PAD_LEFT) ?></span>
                        <span class="countdown-label">Minutos</span>
                    </div>
                    <div class="countdown-separator">:</div>
                    <div class="countdown-item">
                        <span class="countdown-number" id="segundos"><?= str_pad($tiempo_restante['segundos'], 2, '0', STR_PAD_LEFT) ?></span>
                        <span class="countdown-label">Segundos</span>
                    </div>
                </div>
                <p class="countdown-message">La página se actualizará automáticamente cuando la votación esté disponible</p>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <!-- Mensaje especial para administradores -->
        <?php if ($es_admin): ?>
        <div class="admin-info-section">
            <div class="admin-info-card">
                <div class="admin-icon">⚙️</div>
                <h2>Panel de Administración</h2>
                <p class="admin-message">Como administrador, puedes ver la página de votación pero no puedes votar. Usa los botones para navegar entre las secciones del sistema.</p>
                <div class="admin-buttons">
                    <button class="admin-nav-btn" onclick="volverAlPanel()">🔧 Ir al Panel de Administración</button>
                    <button class="admin-nav-btn secondary" onclick="verResultados()">📊 Ver Resultados Detallados</button>
                </div>
                <div class="admin-separator">
                    <span>Vista de Votación</span>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="voting-section">
            <div class="voting-title">
                <h2>Sistema de Votación por Grupos</h2>
                <p>Tienes 3 votos disponibles: uno para cada grupo (1er, 2do y 3er año)</p>
            </div>
            
            <div id="userInfo" style="display: none;">
                <div class="user-card">
                    <div class="user-header">
                        <h3>Usuario: <span id="userName"></span></h3>
                        <button class="logout-button" onclick="cerrarSesion()">Cerrar Sesión</button>
                    </div>
                    <div class="vote-status">
                        <span id="voteStatus1er">❌ 1er Año</span>
                        <span id="voteStatus2do">❌ 2do Año</span>
                        <span id="voteStatus3er">❌ 3er Año</span>
                    </div>
                </div>
            </div>
            
            <div class="groups-container">
                <?php foreach ($grupos_organizados as $grupo_id => $grupo): ?>
                <div class="group-section">
                    <h3 class="group-title"><?= htmlspecialchars($grupo['nombre']) ?></h3>
                    <div class="subgrupos-grid">
                        <?php foreach ($grupo['subgrupos'] as $subgrupo): ?>
                        <div class="subgrupo-card" data-id="<?= $subgrupo['id'] ?>" data-grupo="<?= $grupo_id ?>">
                            <h4><?= htmlspecialchars($subgrupo['nombre']) ?></h4>
                            <div class="vote-count" id="vote-count-<?= $subgrupo['id'] ?>"><?= $subgrupo['votos'] ?> votos</div>
                            <button class="vote-button" onclick="votar(<?= $subgrupo['id'] ?>, <?= $grupo_id ?>)">
                                Votar
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div id="messageContainer"></div>
        </div>
        <?php endif; ?>
        
        <!-- Esta sección está oculta para usuarios normales -->
        <!-- Solo el administrador puede ver los resultados en admin.php -->
    </div>
    
    <footer class="main-footer">
        <p>Desarrollado por Alumnos de 2do de Sistemas - ISFT N° 177</p>
    </footer>

    <script src="js/index.js"></script>
    <?php if (!$votacion_abierta && $tiempo_restante): ?>
    <script>
        // Convertir el tiempo restante a total de segundos
        let totalSeconds = <?= ($tiempo_restante['dias'] * 86400) + ($tiempo_restante['horas'] * 3600) + ($tiempo_restante['minutos'] * 60) + $tiempo_restante['segundos'] ?>;
        
        function updateCountdown() {
            if (totalSeconds <= 0) {
                // Recargar la página cuando termine el tiempo
                window.location.reload();
                return;
            }
            
            const days = Math.floor(totalSeconds / 86400);
            const hours = Math.floor((totalSeconds % 86400) / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;
            
            document.getElementById('dias').textContent = days;
            document.getElementById('horas').textContent = String(hours).padStart(2, '0');
            document.getElementById('minutos').textContent = String(minutes).padStart(2, '0');
            document.getElementById('segundos').textContent = String(seconds).padStart(2, '0');
            
            totalSeconds--;
        }
        
        // Actualizar cada segundo
        updateCountdown();
        setInterval(updateCountdown, 1000);
    </script>
    <?php endif; ?>
</body>
</html>