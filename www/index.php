<?php
require_once 'conexion/conexion.php';

// Verificar conexión a la base de datos
if (!verificarConexion()) {
    die("Error: No se puede conectar a la base de datos");
}

// Crear tablas del sistema
try {
    // Tabla de grupos
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS grupos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL,
            activo BOOLEAN DEFAULT TRUE,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Tabla de subgrupos
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS subgrupos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            grupo_id INT NOT NULL,
            nombre VARCHAR(255) NOT NULL,
            votos INT DEFAULT 0,
            activo BOOLEAN DEFAULT TRUE,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (grupo_id) REFERENCES grupos(id)
        )
    ");
    
    // Tabla de usuarios (solo para control de votos)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            votos_1er_año BOOLEAN DEFAULT FALSE,
            votos_2do_año BOOLEAN DEFAULT FALSE,
            votos_3er_año BOOLEAN DEFAULT FALSE,
            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Tabla de votos (historial)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS votos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            subgrupo_id INT NOT NULL,
            grupo_id INT NOT NULL,
            fecha_voto TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
            FOREIGN KEY (subgrupo_id) REFERENCES subgrupos(id),
            FOREIGN KEY (grupo_id) REFERENCES grupos(id)
        )
    ");
    
    // Insertar grupos si no existen
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM grupos");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $grupos = [
            ['nombre' => '1er Año'],
            ['nombre' => '2do Año'],
            ['nombre' => '3er Año']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO grupos (nombre) VALUES (?)");
        foreach ($grupos as $grupo) {
            $stmt->execute([$grupo['nombre']]);
        }
        
        // Insertar subgrupos de ejemplo (3-5 por grupo)
        $subgrupos = [
            // 1er Año
            [1, 'Subgrupo A1'], [1, 'Subgrupo B1'], [1, 'Subgrupo C1'], [1, 'Subgrupo D1'],
            // 2do Año  
            [2, 'Subgrupo A2'], [2, 'Subgrupo B2'], [2, 'Subgrupo C2'], [2, 'Subgrupo D2'], [2, 'Subgrupo E2'],
            // 3er Año
            [3, 'Subgrupo A3'], [3, 'Subgrupo B3'], [3, 'Subgrupo C3'], [3, 'Subgrupo D3']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO subgrupos (grupo_id, nombre) VALUES (?, ?)");
        foreach ($subgrupos as $subgrupo) {
            $stmt->execute($subgrupo);
        }
    }
} catch (PDOException $e) {
    error_log("Error creando tablas: " . $e->getMessage());
}

// Manejar acciones via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'registrar_usuario') {
        $nombre = trim($_POST['nombre']);
        $email = trim($_POST['email']);
        
        if (empty($nombre) || empty($email)) {
            echo json_encode(['success' => false, 'error' => 'Nombre y email son requeridos']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email) VALUES (?, ?)");
            $stmt->execute([$nombre, $email]);
            $usuario_id = $pdo->lastInsertId();
            
            echo json_encode(['success' => true, 'usuario_id' => $usuario_id, 'message' => 'Usuario registrado correctamente']);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                echo json_encode(['success' => false, 'error' => 'El email ya está registrado']);
            } else {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }
        exit;
    }
    
    if ($_POST['action'] === 'votar') {
        $usuario_id = (int)$_POST['usuario_id'];
        $subgrupo_id = (int)$_POST['subgrupo_id'];
        $grupo_id = (int)$_POST['grupo_id'];
        
        try {
            $pdo->beginTransaction();
            
            // Verificar si el usuario ya votó en este grupo
            $stmt = $pdo->prepare("SELECT votos_1er_año, votos_2do_año, votos_3er_año FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario_id]);
            $usuario = $stmt->fetch();
            
            if (!$usuario) {
                throw new Exception('Usuario no encontrado');
            }
            
            $campo_voto = '';
            if ($grupo_id == 1) $campo_voto = 'votos_1er_año';
            elseif ($grupo_id == 2) $campo_voto = 'votos_2do_año';
            elseif ($grupo_id == 3) $campo_voto = 'votos_3er_año';
            
            if ($usuario[$campo_voto]) {
                throw new Exception('Ya has votado en este grupo');
            }
            
            // Registrar voto
            $stmt = $pdo->prepare("INSERT INTO votos (usuario_id, subgrupo_id, grupo_id) VALUES (?, ?, ?)");
            $stmt->execute([$usuario_id, $subgrupo_id, $grupo_id]);
            
            // Actualizar contador de votos del subgrupo
            $stmt = $pdo->prepare("UPDATE subgrupos SET votos = votos + 1 WHERE id = ?");
            $stmt->execute([$subgrupo_id]);
            
            // Marcar que el usuario votó en este grupo
            $stmt = $pdo->prepare("UPDATE usuarios SET {$campo_voto} = TRUE WHERE id = ?");
            $stmt->execute([$usuario_id]);
            
            $pdo->commit();
            
            // Obtener resultados actualizados
            $stmt = $pdo->prepare("
                SELECT s.*, g.nombre as grupo_nombre 
                FROM subgrupos s 
                JOIN grupos g ON s.grupo_id = g.id 
                WHERE s.activo = TRUE 
                ORDER BY s.grupo_id, s.id
            ");
            $stmt->execute();
            $resultados = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'resultados' => $resultados]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'obtener_resultados') {
        $usuario_id = isset($_POST['usuario_id']) ? (int)$_POST['usuario_id'] : null;
        
        try {
            $stmt = $pdo->prepare("
                SELECT s.*, g.nombre as grupo_nombre,
                       CASE WHEN v.id IS NOT NULL THEN 1 ELSE 0 END as usuario_voto
                FROM subgrupos s 
                JOIN grupos g ON s.grupo_id = g.id 
                LEFT JOIN votos v ON s.id = v.subgrupo_id AND v.usuario_id = ?
                WHERE s.activo = TRUE 
                ORDER BY s.grupo_id, s.id
            ");
            $stmt->execute([$usuario_id]);
            $resultados = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'resultados' => $resultados]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'verificar_usuario') {
        $usuario_id = (int)$_POST['usuario_id'];
        
        try {
            $stmt = $pdo->prepare("SELECT votos_1er_año, votos_2do_año, votos_3er_año FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario_id]);
            $usuario = $stmt->fetch();
            
            if (!$usuario) {
                echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
            } else {
                echo json_encode(['success' => true, 'votos' => $usuario]);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'login_usuario') {
        $email = trim($_POST['email']);
        
        if (empty($email)) {
            echo json_encode(['success' => false, 'error' => 'Email es requerido']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT id, nombre, email, votos_1er_año, votos_2do_año, votos_3er_año FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();
            
            if (!$usuario) {
                echo json_encode(['success' => false, 'error' => 'Usuario no encontrado. Regístrate primero.']);
            } else {
                echo json_encode([
                    'success' => true, 
                    'usuario' => $usuario,
                    'message' => 'Login exitoso'
                ]);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    // Funciones de administración
    if ($_POST['action'] === 'login_admin') {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        
        // Credenciales de administrador (en producción debería ser más seguro)
        if ($email === 'admin@isft177.com' && $password === 'admin2025') {
            echo json_encode(['success' => true, 'message' => 'Login de administrador exitoso']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Credenciales incorrectas']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'obtener_usuarios') {
        try {
            $stmt = $pdo->prepare("
                SELECT u.*, 
                       COUNT(v.id) as total_votos,
                       GROUP_CONCAT(CONCAT(s.nombre, ' (', g.nombre, ')') SEPARATOR ', ') as votos_detalle
                FROM usuarios u
                LEFT JOIN votos v ON u.id = v.usuario_id
                LEFT JOIN subgrupos s ON v.subgrupo_id = s.id
                LEFT JOIN grupos g ON s.grupo_id = g.id
                GROUP BY u.id
                ORDER BY u.fecha_registro DESC
            ");
            $stmt->execute();
            $usuarios = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'usuarios' => $usuarios]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'obtener_subgrupos_admin') {
        $grupo_id = isset($_POST['grupo_id']) ? (int)$_POST['grupo_id'] : null;
        
        try {
            if ($grupo_id) {
                // Obtener subgrupos de un grupo específico
                $stmt = $pdo->prepare("
                    SELECT s.*, g.nombre as grupo_nombre,
                           COUNT(v.id) as total_votos
                    FROM subgrupos s
                    JOIN grupos g ON s.grupo_id = g.id
                    LEFT JOIN votos v ON s.id = v.subgrupo_id
                    WHERE s.activo = TRUE AND s.grupo_id = ?
                    GROUP BY s.id
                    ORDER BY s.id
                ");
                $stmt->execute([$grupo_id]);
            } else {
                // Obtener todos los subgrupos
                $stmt = $pdo->prepare("
                    SELECT s.*, g.nombre as grupo_nombre,
                           COUNT(v.id) as total_votos
                    FROM subgrupos s
                    JOIN grupos g ON s.grupo_id = g.id
                    LEFT JOIN votos v ON s.id = v.subgrupo_id
                    WHERE s.activo = TRUE
                    GROUP BY s.id
                    ORDER BY s.grupo_id, s.id
                ");
                $stmt->execute();
            }
            
            $subgrupos = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'subgrupos' => $subgrupos]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'actualizar_subgrupo') {
        $id = (int)$_POST['id'];
        $nombre = trim($_POST['nombre']);
        
        if (empty($nombre)) {
            echo json_encode(['success' => false, 'error' => 'El nombre es requerido']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE subgrupos SET nombre = ? WHERE id = ?");
            $stmt->execute([$nombre, $id]);
            
            echo json_encode(['success' => true, 'message' => 'Subgrupo actualizado correctamente']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'agregar_subgrupo') {
        $grupo_id = (int)$_POST['grupo_id'];
        $nombre = trim($_POST['nombre']);
        
        if (empty($nombre)) {
            echo json_encode(['success' => false, 'error' => 'El nombre es requerido']);
            exit;
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO subgrupos (grupo_id, nombre) VALUES (?, ?)");
            $stmt->execute([$grupo_id, $nombre]);
            
            echo json_encode(['success' => true, 'message' => 'Subgrupo agregado correctamente']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'eliminar_subgrupo') {
        $id = (int)$_POST['id'];
        
        try {
            $pdo->beginTransaction();
            
            // Eliminar votos relacionados
            $stmt = $pdo->prepare("DELETE FROM votos WHERE subgrupo_id = ?");
            $stmt->execute([$id]);
            
            // Marcar subgrupo como inactivo
            $stmt = $pdo->prepare("UPDATE subgrupos SET activo = FALSE WHERE id = ?");
            $stmt->execute([$id]);
            
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Subgrupo eliminado correctamente']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'resetear_votos_usuario') {
        $usuario_id = (int)$_POST['usuario_id'];
        
        try {
            $pdo->beginTransaction();
            
            // Eliminar todos los votos del usuario
            $stmt = $pdo->prepare("DELETE FROM votos WHERE usuario_id = ?");
            $stmt->execute([$usuario_id]);
            
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'eliminar_usuario') {
        $usuario_id = (int)$_POST['usuario_id'];
        
        try {
            $pdo->beginTransaction();
            
            // Eliminar todos los votos del usuario
            $stmt = $pdo->prepare("DELETE FROM votos WHERE usuario_id = ?");
            $stmt->execute([$usuario_id]);
            
            // Eliminar el usuario
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$usuario_id]);
            
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'resetear_todos_los_votos') {
        try {
            $pdo->beginTransaction();
            
            // Eliminar TODOS los votos del sistema
            $stmt = $pdo->prepare("DELETE FROM votos");
            $stmt->execute();
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Todos los votos han sido eliminados']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'limpiar_votos_huerfanos') {
        try {
            $pdo->beginTransaction();
            
            // Eliminar votos que no tienen usuario asociado
            $stmt = $pdo->prepare("DELETE FROM votos WHERE usuario_id NOT IN (SELECT id FROM usuarios)");
            $stmt->execute();
            
            $votosEliminados = $stmt->rowCount();
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "Se eliminaron {$votosEliminados} votos huérfanos"]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// Obtener grupos y subgrupos
try {
    $stmt = $pdo->prepare("
        SELECT s.*, g.nombre as grupo_nombre 
        FROM subgrupos s 
        JOIN grupos g ON s.grupo_id = g.id 
        WHERE s.activo = TRUE 
        ORDER BY s.grupo_id, s.id
    ");
    $stmt->execute();
    $subgrupos = $stmt->fetchAll();
    
    // Organizar por grupos
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
            <h1>🗳️ Sistema de Votación en Tiempo Real</h1>
            <p>ISFT 177 - Instituto Superior de Formación Técnica</p>
        </div>
        
        <div class="info-section">
            <div class="info-grid">
                <div class="info-card">
                    <h3>📊 Información del Sistema</h3>
                    <p><strong>Estado:</strong> <span class="status-indicator status-online"></span>En línea</p>
                    <p><strong>Base de datos:</strong> Conectada</p>
                    <p><strong>Última actualización:</strong> <span id="lastUpdate">Cargando...</span></p>
                </div>
                
                <div class="info-card">
                    <h3>🎯 Instrucciones</h3>
                    <p>Selecciona una opción y haz clic en "Votar" para participar en la votación en tiempo real.</p>
                    <p>Los resultados se actualizan automáticamente cada 3 segundos.</p>
                </div>
                
                <div class="info-card">
                    <h3>⚡ Características</h3>
                    <p>• Votación en tiempo real</p>
                    <p>• Resultados instantáneos</p>
                    <p>• Interfaz responsive</p>
                    <p>• Seguridad garantizada</p>
                </div>
            </div>
        </div>
        
        <!-- Modal de registro/login -->
        <div id="registrationModal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-tabs">
                    <button class="tab-button active" onclick="cambiarTab('registro')">Registrarse</button>
                    <button class="tab-button" onclick="cambiarTab('login')">Ya tengo cuenta</button>
                    <button class="tab-button admin-tab" onclick="cambiarTab('admin')">🔧 Panel de Administrador</button>
                </div>
                
                <!-- Formulario de registro -->
                <div id="registroTab" class="tab-content">
                    <h2>Registro de Usuario</h2>
                    <form id="registrationForm">
                        <div class="form-group">
                            <label for="nombre">Nombre completo:</label>
                            <input type="text" id="nombre" name="nombre" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <button type="submit" class="submit-button">Registrarse</button>
                    </form>
                </div>
                
                <!-- Formulario de login -->
                <div id="loginTab" class="tab-content" style="display: none;">
                    <h2>Iniciar Sesión</h2>
                    <form id="loginForm">
                        <div class="form-group">
                            <label for="loginEmail">Email:</label>
                            <input type="email" id="loginEmail" name="email" required>
                        </div>
                        <button type="submit" class="submit-button">Iniciar Sesión</button>
                    </form>
                </div>
                
                <!-- Formulario de administrador -->
                <div id="adminTab" class="tab-content" style="display: none;">
                    <h2>🔧 Acceso de Administrador</h2>
                    <form id="adminLoginForm">
                        <div class="form-group">
                            <label for="adminEmail">Email de Administrador:</label>
                            <input type="email" id="adminEmail" name="email" required placeholder="admin@isft177.com">
                        </div>
                        <div class="form-group">
                            <label for="adminPassword">Contraseña:</label>
                            <div class="password-container">
                                <input type="password" id="adminPassword" name="password" required placeholder="Ingresa tu contraseña">
                                <span class="password-toggle" onclick="togglePassword('adminPassword')">👁️</span>
                            </div>
                        </div>
                        <button type="submit" class="submit-button admin-submit">Acceder al Panel</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal de login de administrador -->
        <div id="adminLoginModal" class="modal" style="display: none;">
            <div class="modal-content">
                <h2>🔧 Acceso de Administrador</h2>
                <form id="adminLoginForm">
                    <div class="form-group">
                        <label for="adminEmail">Email:</label>
                        <input type="email" id="adminEmail" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="adminPassword">Contraseña:</label>
                        <div class="password-container">
                            <input type="password" id="adminPassword" name="password" required>
                            <span class="password-toggle" onclick="togglePassword('adminPassword')">👁️</span>
                        </div>
                    </div>
                    <button type="submit" class="submit-button">Acceder</button>
                </form>
            </div>
        </div>

        <!-- Panel de Administración -->
        <div id="adminPanel" class="admin-panel" style="display: none;">
            <div class="admin-header">
                <h2>🔧 Panel de Administración</h2>
                <button class="close-admin-btn" onclick="cerrarPanelAdmin()">✕</button>
            </div>
            
            <div class="admin-tabs">
                <button class="admin-tab active" onclick="cambiarTabAdmin('usuarios')">👥 Usuarios</button>
                <button class="admin-tab" onclick="cambiarTabAdmin('subgrupos')">📋 Subgrupos</button>
            </div>
            
            <!-- Tab de Usuarios -->
            <div id="usuariosTab" class="admin-tab-content">
                <div class="admin-section">
                    <h3>👥 Gestión de Usuarios</h3>
                    <div class="admin-actions">
                        <button class="admin-btn" onclick="cargarUsuarios()">🔄 Actualizar Lista</button>
                        <button class="admin-btn clean-btn" onclick="limpiarVotosHuerfanos()">🧹 Limpiar Votos Huérfanos</button>
                        <button class="admin-btn reset-all-btn" onclick="resetearTodosLosVotos()">🗑️ Resetear TODOS los Votos</button>
                    </div>
                    <div id="usuariosList" class="admin-list">
                        <div class="loading">Cargando usuarios...</div>
                    </div>
                </div>
            </div>
            
            <!-- Tab de Subgrupos -->
            <div id="subgruposTab" class="admin-tab-content" style="display: none;">
                <div class="admin-section">
                    <h3>📋 Gestión de Subgrupos</h3>
                    <div class="admin-actions">
                        <button class="admin-btn" onclick="mostrarAgregarSubgrupo()" id="btnAgregarSubgrupo">➕ Agregar Subgrupo</button>
                        <button class="admin-btn" onclick="cargarSubgrupos()">🔄 Actualizar Lista</button>
                        <button class="admin-btn clean-btn" onclick="limpiarVotosHuerfanos()">🧹 Limpiar Votos Huérfanos</button>
                        <button class="admin-btn reset-all-btn" onclick="resetearTodosLosVotos()">🗑️ Resetear TODOS los Votos</button>
                    </div>
                    <div id="subgruposList" class="admin-list">
                        <div class="loading">Cargando subgrupos...</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para agregar subgrupo -->
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
            
        <div id="adminInfo" class="admin-card" style="display: none;">
            <div class="admin-header">
                <h3>🔧 Administrador</h3>
                <div class="admin-actions">
                    <button class="admin-panel-btn" onclick="mostrarAdminPanel()">📊 Panel Admin</button>
                    <button class="admin-logout-button" onclick="cerrarSesionAdmin()">Cerrar Panel</button>
                    <button class="admin-logout-complete-button" onclick="cerrarSesionAdminCompleta()">🚪 Cerrar Sesión</button>
                </div>
            </div>
            <div class="admin-status">
                <p>Panel de administración activo</p>
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
                            <div class="vote-count"><?= $subgrupo['votos'] ?> votos</div>
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
        
        <div class="results-section">
            <div class="results-title">
                <h2>Resultados en Tiempo Real</h2>
                <p>Los resultados se actualizan automáticamente</p>
            </div>
            
            <!-- Ganadores por grupo -->
            <div class="winners-section">
                <h3>🏆 Ganadores por Grupo</h3>
                <div class="winners-grid" id="winnersContainer">
                    <div class="loading">Cargando ganadores...</div>
                </div>
            </div>
            
            <!-- Ganador general -->
            <div class="overall-winner-section">
                <h3>👑 Ganador General</h3>
                <div class="overall-winner" id="overallWinnerContainer">
                    <div class="loading">Calculando ganador general...</div>
                </div>
            </div>
            
            <!-- Resultados detallados -->
            <div class="detailed-results">
                <h3>📊 Resultados Detallados</h3>
                <div class="results-grid" id="resultsContainer">
                    <div class="loading">Cargando resultados...</div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/index.js"></script>    
</body>
</html>
