<?php
session_start();
require_once 'conexion/conexion.php';

// Redirigir a index.php si no es administrador
if (!isset($_SESSION['es_admin']) || $_SESSION['es_admin'] !== true) {
    header('Location: index.php');
    exit;
}

// Obtener el parámetro de tab de la URL
$tab_actual = isset($_GET['tab']) ? htmlspecialchars($_GET['tab']) : 'usuarios';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - ISFT 177</title>
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <button class="logout-button" onclick="cerrarSesion()">Cerrar Sesión</button>
    <div class="container">
        <div class="header">
            <h1>🔧 Panel de Administración - Sistema de Votación</h1>
            <p>ISFT 177 - Instituto Superior de Formación Técnica</p>
        </div>

        <div id="adminPanel">
            <div class="admin-header">
                <h2>🔧 Panel de Administración</h2>
                <div class="admin-actions">
                    <button class="view-votes-button" onclick="verPaginaVotos()">📊 Ver Página de Votos</button>
                    <button class="logout-button" onclick="cerrarSesion()">Cerrar Sesión</button>
                </div>
            </div>
            
            <div class="admin-tabs">
                <button class="admin-tab active" onclick="cambiarTabAdmin('usuarios')">👥 Usuarios</button>
                <button class="admin-tab" onclick="cambiarTabAdmin('subgrupos')">📋 Grupos</button>
                <button class="admin-tab" onclick="cambiarTabAdmin('ganadores')">🏆 Ganadores</button>
                <button class="admin-tab" onclick="cambiarTabAdmin('configuracion')">⚙️ Configuración</button>
            </div>
            
            <div id="usuariosTab" class="admin-tab-content">
                <div class="admin-section">
                    <h3>👥 Gestión de Usuarios</h3>
                    <div class="admin-actions">
                        <button class="admin-btn" onclick="cargarUsuarios()">🔄 Actualizar</button>
                        <button class="admin-btn clean-btn" onclick="limpiarVotosHuerfanos()">🧹 Limpiar Huérfanos</button>
                        <button class="admin-btn reset-all-btn" onclick="resetearTodosLosVotos()">🗑️ Resetear Todo</button>
                    </div>
                    <div id="usuariosList" class="admin-list">
                        <div class="loading">Cargando usuarios...</div>
                    </div>
                </div>
            </div>
            
            <div id="subgruposTab" class="admin-tab-content" style="display: none;">
                <div class="admin-section">
                    <h3>📋 Gestión de Grupos</h3>
                    <div class="admin-actions">
                        <button class="admin-btn" onclick="mostrarAgregarSubgrupo()">➕ Agregar</button>
                        <button class="admin-btn" onclick="cargarSubgrupos()">🔄 Actualizar</button>
                    </div>
                    <div id="subgruposList" class="admin-list">
                        <div class="loading">Cargando subgrupos...</div>
                    </div>
                </div>
            </div>
            
            <div id="ganadoresTab" class="admin-tab-content" style="display: none;">
                <div class="admin-section">
                    <h3>🏆 Resultados de la Votación</h3>
                    <div class="admin-actions">
                        <button class="admin-btn" onclick="cargarGanadores()">🔄 Actualizar</button>
                    </div>
                    
                    <div class="winners-section-admin">
                        <h4>🏆 Ganadores por Año</h4>
                        <div class="winners-grid-admin" id="winnersByYear">
                            <div class="loading">Cargando ganadores...</div>
                        </div>
                    </div>
                    
                    <div class="overall-winner-section-admin">
                        <h4>👑 Ganador General</h4>
                        <div class="overall-winner-admin" id="overallWinnerAdmin">
                            <div class="loading">Calculando ganador general...</div>
                        </div>
                    </div>
                    
                    <div class="detailed-results-admin">
                        <h4>📊 Resultados Detallados</h4>
                        <div class="results-grid-admin" id="detailedResultsAdmin">
                            <div class="loading">Cargando resultados detallados...</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="configuracionTab" class="admin-tab-content" style="display: none;">
                <div class="admin-section">
                    <h3>⚙️ Configuración del Horario de Votación</h3>
                    <div class="admin-actions">
                        <button class="admin-btn" onclick="cargarConfiguracion()">🔄 Actualizar</button>
                    </div>
                    
                    <div id="configuracionContainer">
                        <div class="loading">Cargando configuración...</div>
                    </div>
                </div>
            </div>
        </div>

        <div id="agregarSubgrupoModal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>➕ Agregar Subgrupo</h2>
                    <button class="close-modal-btn" onclick="cerrarAgregarSubgrupo()">✕</button>
                </div>
                <form id="agregarSubgrupoForm">
                    <div class="form-group">
                        <label for="grupoSelect">Grupo:</label>
                        <select id="grupoSelect" name="grupo_id" required>
                            <option value="1">1er Año</option>
                            <option value="2">2do Año</option>
                            <option value="3">3er Año</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="subgrupoNombre">Nombre del Subgrupo:</label>
                        <input type="text" id="subgrupoNombre" name="nombre" required>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="submit-button">Agregar</button>
                        <button type="button" class="cancel-button" onclick="cerrarAgregarSubgrupo()">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="messageContainer"></div>
    </div>
    
    <footer class="main-footer">
        <p>Desarrollado por Alumnos de 2do de Sistemas - ISFT N° 177</p>
    </footer>

    <script src="js/admin.js"></script>
    <script>
        // Inicializar el tab basado en el parámetro de la URL
        document.addEventListener('DOMContentLoaded', function() {
            const tabActual = '<?php echo $tab_actual; ?>';
            
            // Remover 'active' de todos los tabs
            document.querySelectorAll('.admin-tab').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Ocultar todos los contenidos de tabs
            document.querySelectorAll('.admin-tab-content').forEach(content => {
                content.style.display = 'none';
            });
            
            // Activar el tab correspondiente
            const tabButton = document.querySelector(`.admin-tab[onclick="cambiarTabAdmin('${tabActual}')"]`);
            const tabContent = document.getElementById(`${tabActual}Tab`);
            
            if (tabButton && tabContent) {
                tabButton.classList.add('active');
                tabContent.style.display = 'block';
                
                // Cargar el contenido del tab según corresponda
                if (tabActual === 'usuarios') {
                    cargarUsuarios();
                } else if (tabActual === 'subgrupos') {
                    cargarSubgrupos();
                } else if (tabActual === 'ganadores') {
                    cargarGanadores();
                } else if (tabActual === 'configuracion') {
                    cargarConfiguracion();
                }
            }
        });
    </script>
</body>
</html>