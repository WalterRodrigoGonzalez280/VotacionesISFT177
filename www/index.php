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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .header p {
            font-size: 1.2em;
            opacity: 0.9;
        }
        
        
        .info-section {
            padding: 30px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-left: 4px solid #3498db;
        }
        
        .info-card h3 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1.1em;
        }
        
        .info-card p {
            color: #666;
            line-height: 1.5;
        }
        
        .voting-section {
            padding: 30px;
        }
        
        .voting-title {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .voting-title h2 {
            color: #2c3e50;
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { 
                opacity: 0;
                transform: scale(0.8) translateY(-50px);
            }
            to { 
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        .modal[style*="display: flex"] {
            display: flex !important;
        }
        
        .modal-content {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            padding: 0;
            border-radius: 20px;
            max-width: 500px;
            width: 90%;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3), 0 0 0 1px rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            transform: scale(1);
            transition: all 0.3s ease;
            animation: slideIn 0.4s ease;
        }
        
        .modal-tabs {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .tab-button {
            flex: 1;
            padding: 15px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 1em;
            font-weight: bold;
            color: #666;
            transition: all 0.3s ease;
        }
        
        .tab-button.active {
            background: #3498db;
            color: white;
        }
        
        .tab-button:hover:not(.active) {
            background: #e9ecef;
        }
        
        .admin-tab {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border: 2px solid #c0392b;
        }
        
        .admin-tab:hover {
            background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }
        
        .admin-tab.active {
            background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
            color: white;
        }
        
        .admin-submit {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }
        
        .admin-submit:hover {
            background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
        }
        
        .tab-content {
            padding: 30px;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding: 25px 30px 20px 30px;
            border-bottom: 2px solid #e9ecef;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border-radius: 20px 20px 0 0;
        }
        
        .modal-header h2 {
            color: white;
            margin: 0;
            font-size: 1.4em;
            font-weight: 600;
            text-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        
        .close-modal-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
            padding: 10px 12px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.1em;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .close-modal-btn:hover {
            background: rgba(255,255,255,0.3);
            border-color: rgba(255,255,255,0.5);
            transform: scale(1.1);
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding: 20px 30px 30px 30px;
            background: #f8f9fa;
            border-radius: 0 0 20px 20px;
        }
        
        .submit-button {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1em;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
        }
        
        .submit-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(46, 204, 113, 0.4);
        }
        
        .cancel-button {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1em;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(149, 165, 166, 0.3);
        }
        
        .cancel-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(149, 165, 166, 0.4);
        }
        
        .form-group {
            margin-bottom: 25px;
            padding: 0 30px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 0.95em;
        }
        
        .form-group select,
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s ease;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            transform: translateY(-1px);
        }
        
        .form-group select:hover,
        .form-group input:hover {
            border-color: #bdc3c7;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1em;
        }
        
        .submit-button {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 1em;
            cursor: pointer;
            width: 100%;
        }
        
        .user-card {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .admin-card {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.3);
        }
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .admin-header h3 {
            margin: 0;
            font-size: 1.3em;
        }
        
        .admin-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .admin-panel-btn {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
        }
        
        .admin-panel-btn:hover {
            background: linear-gradient(135deg, #2980b9 0%, #1f4e79 100%);
            border-color: rgba(255,255,255,0.5);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.4);
        }
        
        .admin-logout-button {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .admin-logout-button:hover {
            background: rgba(255,255,255,0.3);
            border-color: rgba(255,255,255,0.5);
            transform: translateY(-1px);
        }
        
        .admin-logout-complete-button {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
        }
        
        .admin-logout-complete-button:hover {
            background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
            border-color: rgba(255,255,255,0.5);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.4);
        }
        
        .admin-status {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .user-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .user-header h3 {
            margin: 0;
        }
        
        .logout-button {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.3s ease;
        }
        
        .logout-button:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.05);
        }
        
        .vote-status {
            display: flex;
            justify-content: space-around;
            margin-top: 15px;
        }
        
        .vote-status span {
            padding: 8px 15px;
            background: rgba(255,255,255,0.2);
            border-radius: 20px;
            font-size: 0.9em;
        }
        
        .groups-container {
            margin-bottom: 30px;
        }
        
        .group-section {
            margin-bottom: 40px;
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
        }
        
        .group-title {
            color: #2c3e50;
            font-size: 1.5em;
            margin-bottom: 20px;
            text-align: center;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        
        .subgrupos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .subgrupo-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .subgrupo-card:hover {
            border-color: #3498db;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(52, 152, 219, 0.2);
        }
        
        .subgrupo-card.voted {
            border-color: #27ae60;
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
        }
        
        .subgrupo-card.not-voted {
            border-color: #e74c3c;
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }
        
        .subgrupo-card h4 {
            font-size: 1.2em;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .subgrupo-card.voted h4 {
            color: white;
        }
        
        .subgrupo-card.not-voted h4 {
            color: white;
        }
        
        .vote-count {
            font-size: 1.1em;
            font-weight: bold;
            color: #3498db;
            margin: 10px 0;
        }
        
        .subgrupo-card.voted .vote-count {
            color: white;
        }
        
        .subgrupo-card.not-voted .vote-count {
            color: white;
        }
        
        .vote-button {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 1em;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 15px;
        }
        
        .vote-button:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        
        .vote-button:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
        }
        
        .results-section {
            background: #f8f9fa;
            padding: 30px;
            border-top: 1px solid #e9ecef;
        }
        
        .results-title {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .results-title h2 {
            color: #2c3e50;
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .group-results {
            background: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .group-results h3 {
            color: #2c3e50;
            font-size: 1.5em;
            margin-bottom: 20px;
            text-align: center;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        
        .subgrupos-results {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .winners-section {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .winners-section h3 {
            text-align: center;
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        
        .winners-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .winner-card {
            background: rgba(255,255,255,0.2);
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            backdrop-filter: blur(10px);
        }
        
        .winner-card h4 {
            font-size: 1.2em;
            margin-bottom: 10px;
        }
        
        .winner-votes {
            font-size: 2em;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .winner-name {
            font-size: 1.3em;
            font-weight: bold;
            margin: 10px 0;
            color: #f1c40f;
        }
        
        .overall-winner-section {
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .overall-winner-section h3 {
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        
        .overall-winner {
            background: rgba(255,255,255,0.2);
            padding: 30px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
        }
        
        .overall-winner h4 {
            font-size: 1.5em;
            margin-bottom: 15px;
        }
        
        .overall-winner .total-votes {
            font-size: 2.5em;
            font-weight: bold;
            margin: 15px 0;
        }
        
        .detailed-results {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
        }
        
        .detailed-results h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            text-align: center;
        }
        
        /* Estilos del Panel de Administración */
        .admin-panel {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            z-index: 2000;
            overflow-y: auto;
        }
        
        .admin-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .close-admin-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2em;
        }
        
        .admin-tabs {
            display: flex;
            background: #34495e;
        }
        
        .admin-tab {
            flex: 1;
            padding: 15px;
            border: none;
            background: transparent;
            color: white;
            cursor: pointer;
            font-size: 1em;
            transition: all 0.3s ease;
        }
        
        .admin-tab.active {
            background: #3498db;
        }
        
        .admin-tab:hover:not(.active) {
            background: #2c3e50;
        }
        
        .admin-tab-content {
            padding: 30px;
            background: white;
            min-height: 70vh;
        }
        
        .admin-section h3 {
            color: #2c3e50;
            margin-bottom: 20px;
        }
        
        .admin-actions {
            margin-bottom: 20px;
        }
        
        .admin-btn {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-right: 10px;
            margin-bottom: 10px;
        }
        
        .admin-btn:hover {
            transform: scale(1.05);
        }
        
        .admin-list {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
        
        .admin-item {
            background: white;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        /* Estilos para lista de usuarios */
        .usuarios-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .usuarios-table th {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 15px 10px;
            text-align: left;
            font-weight: bold;
        }
        
        .usuarios-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .usuarios-table tr:hover {
            background: #f8f9fa;
        }
        
        .usuarios-table tr:last-child td {
            border-bottom: none;
        }
        
        .vote-status-inline {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .vote-status-inline span {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .vote-status-inline .voted {
            background: #d4edda;
            color: #155724;
        }
        
        .vote-status-inline .not-voted {
            background: #f8d7da;
            color: #721c24;
        }
        
        .user-details {
            font-size: 0.9em;
            color: #666;
        }
        
        .user-name {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .user-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85em;
            font-weight: 600;
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        
        .reset-votes-btn {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(243, 156, 18, 0.3);
        }
        
        .reset-votes-btn:hover {
            background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(243, 156, 18, 0.4);
        }
        
        .delete-user-btn {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
        }
        
        .delete-user-btn:hover {
            background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.4);
        }
        
        .reset-all-btn {
            background: linear-gradient(135deg, #8e44ad 0%, #9b59b6 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(142, 68, 173, 0.3);
        }
        
        .reset-all-btn:hover {
            background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(142, 68, 173, 0.4);
        }
        
        .clean-btn {
            background: linear-gradient(135deg, #16a085 0%, #1abc9c 100%);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(22, 160, 133, 0.3);
        }
        
        .clean-btn:hover {
            background: linear-gradient(135deg, #1abc9c 0%, #16a085 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(22, 160, 133, 0.4);
        }
        
        /* Estilos para secciones de grupos */
        .grupo-section {
            margin-bottom: 30px;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }
        
        .grupo-title {
            color: #2c3e50;
            font-size: 1.3em;
            margin-bottom: 15px;
            text-align: center;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        
        .subgrupos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .subgrupo-item {
            background: white;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .subgrupo-item:hover {
            border-color: #3498db;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.2);
        }
        
        /* Estilos para tarjetas de grupos */
        .grupo-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .grupo-card:hover {
            border-color: #3498db;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(52, 152, 219, 0.2);
        }
        
        .grupo-header h3 {
            color: #2c3e50;
            margin: 0 0 10px 0;
            font-size: 1.3em;
        }
        
        .grupo-stats {
            display: flex;
            gap: 15px;
        }
        
        .stat-item {
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 0.9em;
            color: #666;
        }
        
        .grupo-arrow {
            font-size: 1.5em;
            color: #3498db;
        }
        
        /* Estilos para navegación de grupos */
        .grupo-detalle-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .back-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
        }
        
        .back-btn:hover {
            background: #5a6268;
        }
        
        .grupo-detalle-header h3 {
            color: #2c3e50;
            margin: 0;
        }
        
        .subgrupos-detalle {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .admin-item-info h4 {
            margin: 0 0 5px 0;
            color: #2c3e50;
        }
        
        .admin-item-info p {
            margin: 0;
            color: #666;
            font-size: 0.9em;
        }
        
        .admin-item-actions {
            display: flex;
            gap: 10px;
        }
        
        .edit-btn {
            background: #f39c12;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8em;
        }
        
        .delete-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8em;
        }
        
        .edit-input {
            width: 200px;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        
        .save-btn {
            background: #27ae60;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8em;
            margin-left: 5px;
        }
        
        .cancel-btn {
            background: #95a5a6;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8em;
            margin-left: 5px;
        }
        
        .result-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .result-card h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .vote-count {
            font-size: 2em;
            font-weight: bold;
            color: #3498db;
            margin: 10px 0;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            transition: width 0.5s ease;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-online {
            background: #27ae60;
            animation: pulse 2s infinite;
        }
        
        .status-offline {
            background: #e74c3c;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
        }
        
        .error {
            background: #e74c3c;
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: center;
        }
        
        .success {
            background: #27ae60;
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 2em;
            }
            
            .voting-options {
                grid-template-columns: 1fr;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Estilos para el ícono de mostrar/ocultar contraseña */
        .password-container {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .password-container input {
            padding-right: 40px;
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            cursor: pointer;
            font-size: 18px;
            user-select: none;
            transition: opacity 0.3s ease;
        }
        
        .password-toggle:hover {
            opacity: 0.7;
        }
        
        .password-toggle.active {
            opacity: 0.5;
        }
    </style>
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

    <script>
        let currentUser = null;
        let userVotes = {1: false, 2: false, 3: false};
        
        // Mostrar modal de registro al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            // Verificar si hay una sesión de administrador
            const adminSession = localStorage.getItem('adminSession');
            if (adminSession === 'true') {
                // Mostrar información de administrador
                document.getElementById('adminInfo').style.display = 'block';
                mostrarMensaje('Sesión de administrador activa. Usa el botón "📊 Panel Admin" para acceder al panel.', 'info');
            } else {
                // Verificar si hay un usuario guardado en localStorage
                const savedUser = localStorage.getItem('currentUser');
                if (savedUser) {
                    try {
                        currentUser = JSON.parse(savedUser);
                        document.getElementById('userName').textContent = currentUser.nombre;
                        document.getElementById('userInfo').style.display = 'block';
                        verificarEstadoVotos();
                    } catch (e) {
                        mostrarModalRegistro();
                    }
                } else {
                    mostrarModalRegistro();
                }
            }
            obtenerResultados();
            
            // Agregar event listener adicional para el botón de agregar subgrupo
            const btnAgregar = document.getElementById('btnAgregarSubgrupo');
            if (btnAgregar) {
                btnAgregar.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Botón de agregar subgrupo clickeado via event listener');
                    mostrarAgregarSubgrupo();
                });
            }
            
            // Función de prueba alternativa
            window.testModal = function() {
                console.log('=== PRUEBA ALTERNATIVA DEL MODAL ===');
                const modal = document.getElementById('agregarSubgrupoModal');
                if (modal) {
                    modal.style.display = 'flex';
                    modal.style.visibility = 'visible';
                    modal.style.opacity = '1';
                    console.log('Modal mostrado con método alternativo');
                } else {
                    console.error('Modal no encontrado en método alternativo');
                }
            };
        });
        
        // Función para mostrar modal de registro
        function mostrarModalRegistro() {
            document.getElementById('registrationModal').style.display = 'flex';
        }
        
        // Función para ocultar modal
        function ocultarModal() {
            document.getElementById('registrationModal').style.display = 'none';
        }
        
        // Función para cambiar pestañas
        function cambiarTab(tabName) {
            // Ocultar todas las pestañas
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.style.display = 'none';
            });
            
            // Remover clase active de todos los botones
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Mostrar pestaña seleccionada
            if (tabName === 'registro') {
                document.getElementById('registroTab').style.display = 'block';
                document.querySelector('[onclick="cambiarTab(\'registro\')"]').classList.add('active');
            } else if (tabName === 'login') {
                document.getElementById('loginTab').style.display = 'block';
                document.querySelector('[onclick="cambiarTab(\'login\')"]').classList.add('active');
            } else if (tabName === 'admin') {
                document.getElementById('adminTab').style.display = 'block';
                document.querySelector('[onclick="cambiarTab(\'admin\')"]').classList.add('active');
            }
        }
        
        // Manejar registro de usuario
        document.getElementById('registrationForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const nombre = document.getElementById('nombre').value;
            const email = document.getElementById('email').value;
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=registrar_usuario&nombre=${encodeURIComponent(nombre)}&email=${encodeURIComponent(email)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    currentUser = {
                        id: data.usuario_id,
                        nombre: nombre,
                        email: email
                    };
                    
                    // Guardar usuario en localStorage
                    localStorage.setItem('currentUser', JSON.stringify(currentUser));
                    
                    document.getElementById('userName').textContent = nombre;
                    document.getElementById('userInfo').style.display = 'block';
                    
                    ocultarModal();
                    mostrarMensaje('¡Registro exitoso! Ya puedes votar.', 'success');
                    
                    // Verificar estado de votos del usuario
                    verificarEstadoVotos();
                } else {
                    mostrarMensaje('Error al registrarse: ' + data.error, 'error');
                }
            } catch (error) {
                mostrarMensaje('Error de conexión. Intenta nuevamente.', 'error');
            }
        });
        
        // Manejar login de usuario
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.getElementById('loginEmail').value;
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=login_usuario&email=${encodeURIComponent(email)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    currentUser = {
                        id: data.usuario.id,
                        nombre: data.usuario.nombre,
                        email: data.usuario.email
                    };
                    
                    // Guardar usuario en localStorage
                    localStorage.setItem('currentUser', JSON.stringify(currentUser));
                    
                    document.getElementById('userName').textContent = data.usuario.nombre;
                    document.getElementById('userInfo').style.display = 'block';
                    
                    ocultarModal();
                    mostrarMensaje('¡Bienvenido de vuelta!', 'success');
                    
                    // Verificar estado de votos del usuario
                    verificarEstadoVotos();
                } else {
                    mostrarMensaje('Error al iniciar sesión: ' + data.error, 'error');
                }
            } catch (error) {
                mostrarMensaje('Error de conexión. Intenta nuevamente.', 'error');
            }
        });
        
        // Manejar login de administrador
        document.getElementById('adminLoginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.getElementById('adminEmail').value;
            const password = document.getElementById('adminPassword').value;
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=login_admin&email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Guardar sesión de administrador en localStorage
                    localStorage.setItem('adminSession', 'true');
                    
                    ocultarModal();
                    mostrarAdminPanel();
                    mostrarMensaje('Acceso de administrador autorizado', 'success');
                } else {
                    mostrarMensaje('Credenciales incorrectas', 'error');
                }
            } catch (error) {
                mostrarMensaje('Error de conexión. Intenta nuevamente.', 'error');
            }
        });
        
        // Función para cerrar sesión
        function cerrarSesion() {
            if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
                // Limpiar datos del usuario
                currentUser = null;
                userVotes = {1: false, 2: false, 3: false};
                
                // Limpiar localStorage
                localStorage.removeItem('currentUser');
                
                // Ocultar información del usuario
                document.getElementById('userInfo').style.display = 'none';
                
                // Limpiar estado de votación
                document.querySelectorAll('.subgrupo-card').forEach(card => {
                    card.classList.remove('voted', 'not-voted');
                    const button = card.querySelector('.vote-button');
                    button.disabled = false;
                    button.textContent = 'Votar';
                });
                
                // Mostrar modal de login/registro
                mostrarModalRegistro();
                
                mostrarMensaje('Sesión cerrada correctamente', 'success');
            }
        }
        
        // Función para verificar estado de votos del usuario
        async function verificarEstadoVotos() {
            if (!currentUser) return;
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=verificar_usuario&usuario_id=${currentUser.id}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    userVotes = {
                        1: data.votos.votos_1er_año,
                        2: data.votos.votos_2do_año,
                        3: data.votos.votos_3er_año
                    };
                    
                    actualizarEstadoVotos();
                }
            } catch (error) {
                console.error('Error verificando votos:', error);
            }
        }
        
        // Función para actualizar estado visual de votos
        function actualizarEstadoVotos() {
            const status1er = document.getElementById('voteStatus1er');
            const status2do = document.getElementById('voteStatus2do');
            const status3er = document.getElementById('voteStatus3er');
            
            status1er.textContent = userVotes[1] ? '✅ 1er Año' : '❌ 1er Año';
            status2do.textContent = userVotes[2] ? '✅ 2do Año' : '❌ 2do Año';
            status3er.textContent = userVotes[3] ? '✅ 3er Año' : '❌ 3er Año';
            
            // Deshabilitar botones de votación según el estado
            document.querySelectorAll('.subgrupo-card').forEach(card => {
                const grupoId = parseInt(card.dataset.grupo);
                const button = card.querySelector('.vote-button');
                
                if (userVotes[grupoId]) {
                    card.classList.remove('not-voted');
                    card.classList.add('voted');
                    button.textContent = '✓ Votado';
                    button.disabled = true;
                } else {
                    card.classList.remove('voted');
                    card.classList.add('not-voted');
                    button.textContent = 'Votar';
                    button.disabled = false;
                }
            });
        }
        
        // Función para resetear estado visual de votos (cuando se blanquean)
        function resetearEstadoVisualVotos() {
            userVotes = {1: false, 2: false, 3: false};
            actualizarEstadoVotos();
        }
        
        // Función para actualizar estado visual basado en resultados del servidor
        function actualizarEstadoVisualDesdeResultados(resultados) {
            if (!currentUser || !resultados) return;
            
            // Limpiar todos los estados visuales primero
            document.querySelectorAll('.subgrupo-card').forEach(card => {
                card.classList.remove('voted', 'not-voted');
                const button = card.querySelector('.vote-button');
                button.textContent = 'Votar';
                button.disabled = false;
            });
            
            // Marcar los subgrupos según el estado del usuario
            resultados.forEach(resultado => {
                const card = document.querySelector(`[data-id="${resultado.id}"]`);
                if (card) {
                    if (resultado.usuario_voto == 1) {
                        // Usuario votó en este subgrupo
                        card.classList.add('voted');
                        const button = card.querySelector('.vote-button');
                        button.textContent = '✓ Votado';
                        button.disabled = true;
                    } else {
                        // Usuario no votó en este subgrupo
                        card.classList.add('not-voted');
                        const button = card.querySelector('.vote-button');
                        button.textContent = 'Votar';
                        button.disabled = false;
                    }
                }
            });
        }
        
        // Función para mostrar panel de administración
        function mostrarAdminPanel() {
            // Ocultar información de usuario si está visible
            document.getElementById('userInfo').style.display = 'none';
            
            // Mostrar información de administrador
            document.getElementById('adminInfo').style.display = 'block';
            
            // Mostrar panel de administración
            document.getElementById('adminPanel').style.display = 'block';
            cargarUsuarios();
            cargarSubgrupos();
        }
        
        // Función para cerrar sesión de administrador (solo oculta el panel)
        function cerrarSesionAdmin() {
            // Ocultar panel de administración
            document.getElementById('adminPanel').style.display = 'none';
            
            mostrarMensaje('Panel de administración cerrado. Puedes volver a abrirlo con el botón "📊 Panel Admin"', 'info');
        }
        
        // Función para cerrar completamente la sesión de administrador
        function cerrarSesionAdminCompleta() {
            if (confirm('¿Estás seguro de que quieres cerrar completamente la sesión de administrador?')) {
                // Ocultar información de administrador
                document.getElementById('adminInfo').style.display = 'none';
                
                // Ocultar panel de administración
                document.getElementById('adminPanel').style.display = 'none';
                
                // Limpiar cualquier estado de administrador
                localStorage.removeItem('adminSession');
                
                // Mostrar modal de registro
                mostrarModalRegistro();
                
                mostrarMensaje('Sesión de administrador cerrada completamente', 'success');
            }
        }
        
        // Función para resetear votos de un usuario
        async function resetearVotosUsuario(usuarioId, nombreUsuario) {
            if (confirm(`¿Estás seguro de que quieres resetear los votos de ${nombreUsuario}?`)) {
                try {
                    const response = await fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=resetear_votos_usuario&usuario_id=${usuarioId}`
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        mostrarMensaje(`Votos de ${nombreUsuario} reseteados correctamente. El usuario puede volver a votar.`, 'success');
                        // Actualizar la lista de usuarios
                        cargarUsuarios();
                        // Actualizar resultados en tiempo real para reflejar los votos eliminados
                        obtenerResultados();
                        // Si el usuario reseteado está logueado, actualizar su estado visual
                        if (currentUser && currentUser.id == usuarioId) {
                            resetearEstadoVisualVotos();
                            mostrarMensaje('Tus votos han sido reseteados. Puedes votar nuevamente.', 'info');
                        }
                    } else {
                        mostrarMensaje('Error: ' + data.error, 'error');
                    }
                } catch (error) {
                    mostrarMensaje('Error de conexión', 'error');
                }
            }
        }
        
        // Función para eliminar un usuario
        async function eliminarUsuario(usuarioId, nombreUsuario) {
            if (confirm(`⚠️ ELIMINACIÓN COMPLETA DE USUARIO\n\n¿Estás seguro de que quieres eliminar completamente al usuario "${nombreUsuario}"?\n\nEsta acción eliminará:\n• Todos los votos del usuario\n• Toda la información del usuario\n• Su historial completo\n\nEsta acción NO se puede deshacer.`)) {
                try {
                    const response = await fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=eliminar_usuario&usuario_id=${usuarioId}`
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        mostrarMensaje(`✅ Usuario "${nombreUsuario}" eliminado completamente del sistema.\n\nSe han eliminado:\n• Todos sus votos\n• Toda su información\n• Su historial completo\n\nLos conteos de votos se han actualizado automáticamente.`, 'success');
                        // Actualizar la lista de usuarios
                        cargarUsuarios();
                        // Actualizar resultados en tiempo real para reflejar los votos eliminados
                        obtenerResultados();
                    } else {
                        mostrarMensaje('Error: ' + data.error, 'error');
                    }
                } catch (error) {
                    mostrarMensaje('Error de conexión', 'error');
                }
            }
        }
        
        // Función para resetear TODOS los votos del sistema
        async function resetearTodosLosVotos() {
            if (confirm('⚠️ ADVERTENCIA: ¿Estás seguro de que quieres resetear TODOS los votos del sistema?\n\nEsta acción eliminará todos los votos de todos los usuarios y no se puede deshacer.')) {
                if (confirm('🚨 CONFIRMACIÓN FINAL: Esta acción eliminará TODOS los votos del sistema.\n\n¿Estás completamente seguro?')) {
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'action=resetear_todos_los_votos'
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            mostrarMensaje('✅ TODOS los votos han sido reseteados. El conteo está en cero.', 'success');
                            // Actualizar todas las listas
                            cargarUsuarios();
                            cargarSubgrupos();
                            // Actualizar resultados para mostrar conteos en cero
                            obtenerResultados();
                            // Si hay usuarios logueados, resetear su estado visual
                            if (currentUser) {
                                resetearEstadoVisualVotos();
                                mostrarMensaje('Tus votos han sido reseteados por el administrador.', 'info');
                            }
                        } else {
                            mostrarMensaje('Error: ' + data.error, 'error');
                        }
                    } catch (error) {
                        mostrarMensaje('Error de conexión', 'error');
                    }
                }
            }
        }
        
        // Función para limpiar votos huérfanos
        async function limpiarVotosHuerfanos() {
            if (confirm('🧹 ¿Limpiar votos huérfanos?\n\nEsta acción eliminará votos que no tienen usuario asociado.\n\n¿Continuar?')) {
                try {
                    const response = await fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=limpiar_votos_huerfanos'
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        mostrarMensaje(`✅ ${data.message}`, 'success');
                        // Actualizar todas las listas
                        cargarUsuarios();
                        cargarSubgrupos();
                        // Actualizar resultados para mostrar conteos corregidos
                        obtenerResultados();
                    } else {
                        mostrarMensaje('Error: ' + data.error, 'error');
                    }
                } catch (error) {
                    mostrarMensaje('Error de conexión', 'error');
                }
            }
        }
        
        // Función para votar
        async function votar(subgrupoId, grupoId) {
            if (!currentUser) {
                mostrarMensaje('Debes registrarte primero', 'error');
                return;
            }
            
            if (userVotes[grupoId]) {
                mostrarMensaje('Ya has votado en este grupo', 'error');
                return;
            }
            
            const button = document.querySelector(`[data-id="${subgrupoId}"] .vote-button`);
            button.disabled = true;
            button.textContent = 'Votando...';
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=votar&usuario_id=${currentUser.id}&subgrupo_id=${subgrupoId}&grupo_id=${grupoId}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    userVotes[grupoId] = true;
                    mostrarMensaje('¡Voto registrado exitosamente!', 'success');
                    
                    // Marcar la opción como votada
                    const card = document.querySelector(`[data-id="${subgrupoId}"]`);
                    card.classList.add('voted');
                    button.textContent = '✓ Votado';
                    
                    // Actualizar estado visual
                    actualizarEstadoVotos();
                    
                    // Actualizar resultados
                    actualizarResultados(data.resultados);
                    
                    // Actualizar automáticamente las listas del panel de administración si está abierto
                    if (document.getElementById('adminPanel').style.display === 'block') {
                        actualizarUsuariosAutomaticamente();
                        actualizarListaAutomaticamente();
                    }
                } else {
                    mostrarMensaje('Error al registrar el voto: ' + data.error, 'error');
                    button.disabled = false;
                    button.textContent = 'Votar';
                }
            } catch (error) {
                mostrarMensaje('Error de conexión. Intenta nuevamente.', 'error');
                button.disabled = false;
                button.textContent = 'Votar';
            }
        }
        
        // Función para obtener resultados
        async function obtenerResultados() {
            try {
                const usuarioId = currentUser ? currentUser.id : null;
                const body = usuarioId ? 
                    `action=obtener_resultados&usuario_id=${usuarioId}` : 
                    'action=obtener_resultados';
                
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: body
                });
                
                const data = await response.json();
                
                if (data.success) {
                    actualizarResultados(data.resultados);
                }
            } catch (error) {
                console.error('Error obteniendo resultados:', error);
            }
        }
        
        // Función para actualizar la visualización de resultados
        function actualizarResultados(resultados) {
            if (!resultados || resultados.length === 0) {
                document.getElementById('resultsContainer').innerHTML = '<div class="loading">No hay resultados disponibles</div>';
                document.getElementById('winnersContainer').innerHTML = '<div class="loading">No hay ganadores</div>';
                document.getElementById('overallWinnerContainer').innerHTML = '<div class="loading">No hay ganador general</div>';
                return;
            }
            
            // Organizar resultados por grupos
            const gruposResultados = {};
            resultados.forEach(resultado => {
                const grupoId = resultado.grupo_id;
                if (!gruposResultados[grupoId]) {
                    gruposResultados[grupoId] = {
                        nombre: resultado.grupo_nombre,
                        subgrupos: []
                    };
                }
                gruposResultados[grupoId].subgrupos.push(resultado);
            });
            
            // Actualizar estado visual basado en los votos del usuario
            actualizarEstadoVisualDesdeResultados(resultados);
            
            // Actualizar ganadores por grupo
            actualizarGanadoresPorGrupo(gruposResultados);
            
            // Actualizar ganador general
            actualizarGanadorGeneral(resultados);
            
            // Actualizar resultados detallados
            actualizarResultadosDetallados(gruposResultados);
            
            // Actualizar timestamp
            document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString();
        }
        
        // Función para actualizar ganadores por grupo
        function actualizarGanadoresPorGrupo(gruposResultados) {
            const winnersContainer = document.getElementById('winnersContainer');
            let html = '';
            
            Object.values(gruposResultados).forEach(grupo => {
                if (grupo.subgrupos.length > 0) {
                    // Encontrar el subgrupo con más votos
                    const ganador = grupo.subgrupos.reduce((max, current) => 
                        parseInt(current.votos) > parseInt(max.votos) ? current : max
                    );
                    
                    html += `
                        <div class="winner-card">
                            <h4>${grupo.nombre}</h4>
                            <div class="winner-name">${ganador.nombre}</div>
                            <div class="winner-votes">${ganador.votos} votos</div>
                        </div>
                    `;
                }
            });
            
            winnersContainer.innerHTML = html || '<div class="loading">No hay ganadores</div>';
        }
        
        // Función para actualizar ganador general
        function actualizarGanadorGeneral(resultados) {
            const overallWinnerContainer = document.getElementById('overallWinnerContainer');
            
            if (resultados.length === 0) {
                overallWinnerContainer.innerHTML = '<div class="loading">No hay ganador general</div>';
                return;
            }
            
            // Encontrar el subgrupo con más votos de todos
            const ganadorGeneral = resultados.reduce((max, current) => 
                parseInt(current.votos) > parseInt(max.votos) ? current : max
            );
            
            const totalVotosGenerales = resultados.reduce((sum, item) => sum + parseInt(item.votos), 0);
            
            overallWinnerContainer.innerHTML = `
                <h4>${ganadorGeneral.nombre}</h4>
                <div class="total-votes">${ganadorGeneral.votos} votos</div>
                <p>Del grupo: ${ganadorGeneral.grupo_nombre}</p>
                <p>Total de votos en el sistema: ${totalVotosGenerales}</p>
            `;
        }
        
        // Función para actualizar resultados detallados
        function actualizarResultadosDetallados(gruposResultados) {
            const container = document.getElementById('resultsContainer');
            
            let html = '';
            Object.values(gruposResultados).forEach(grupo => {
                const totalVotosGrupo = grupo.subgrupos.reduce((sum, item) => sum + parseInt(item.votos), 0);
                
                html += `
                    <div class="group-results">
                        <h3>${grupo.nombre}</h3>
                        <div class="subgrupos-results">
                `;
                
                grupo.subgrupos.forEach(subgrupo => {
                    const porcentaje = totalVotosGrupo > 0 ? (subgrupo.votos / totalVotosGrupo) * 100 : 0;
                    
                    html += `
                        <div class="result-card">
                            <h4>${subgrupo.nombre}</h4>
                            <div class="vote-count">${subgrupo.votos} votos</div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: ${porcentaje}%"></div>
                            </div>
                            <p>${porcentaje.toFixed(1)}% del grupo</p>
                        </div>
                    `;
                });
                
                html += `
                        </div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        // Función para mostrar mensajes
        function mostrarMensaje(mensaje, tipo) {
            const container = document.getElementById('messageContainer');
            container.innerHTML = `<div class="${tipo}">${mensaje}</div>`;
            
            setTimeout(() => {
                container.innerHTML = '';
            }, 5000);
        }
        
        // Actualizar resultados cada 3 segundos
        setInterval(obtenerResultados, 3000);
        
        // Funciones del Panel de Administración
        function mostrarLoginAdmin() {
            document.getElementById('adminLoginModal').style.display = 'flex';
        }
        
        function cerrarLoginAdmin() {
            document.getElementById('adminLoginModal').style.display = 'none';
        }
        
        function mostrarPanelAdmin() {
            document.getElementById('adminPanel').style.display = 'block';
            cargarUsuarios();
        }
        
        function cerrarPanelAdmin() {
            document.getElementById('adminPanel').style.display = 'none';
        }
        
        function cambiarTabAdmin(tabName) {
            // Ocultar todas las pestañas
            document.querySelectorAll('.admin-tab-content').forEach(tab => {
                tab.style.display = 'none';
            });
            
            // Remover clase active de todos los botones
            document.querySelectorAll('.admin-tab').forEach(button => {
                button.classList.remove('active');
            });
            
            // Mostrar pestaña seleccionada
            if (tabName === 'usuarios') {
                document.getElementById('usuariosTab').style.display = 'block';
                document.querySelector('[onclick="cambiarTabAdmin(\'usuarios\')"]').classList.add('active');
            } else {
                document.getElementById('subgruposTab').style.display = 'block';
                document.querySelector('[onclick="cambiarTabAdmin(\'subgrupos\')"]').classList.add('active');
            }
        }
        
        // Manejar login de administrador
        document.getElementById('adminLoginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.getElementById('adminEmail').value;
            const password = document.getElementById('adminPassword').value;
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=login_admin&email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    cerrarLoginAdmin();
                    mostrarPanelAdmin();
                    mostrarMensaje('Acceso de administrador exitoso', 'success');
                } else {
                    mostrarMensaje('Error de acceso: ' + data.error, 'error');
                }
            } catch (error) {
                mostrarMensaje('Error de conexión. Intenta nuevamente.', 'error');
            }
        });
        
        // Cargar usuarios
        async function cargarUsuarios() {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=obtener_usuarios'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    mostrarUsuarios(data.usuarios);
                } else {
                    document.getElementById('usuariosList').innerHTML = '<div class="error">Error al cargar usuarios</div>';
                }
            } catch (error) {
                document.getElementById('usuariosList').innerHTML = '<div class="error">Error al cargar usuarios</div>';
            }
        }
        
        // Función para actualizar automáticamente la lista de usuarios
        function actualizarUsuariosAutomaticamente() {
            cargarUsuarios();
        }
        
        function mostrarUsuarios(usuarios) {
            const container = document.getElementById('usuariosList');
            
            if (usuarios.length === 0) {
                container.innerHTML = '<div class="loading">No hay usuarios registrados</div>';
                return;
            }
            
            let html = `
                <table class="usuarios-table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Email</th>
                            <th>Total Votos</th>
                            <th>Estado por Grupo</th>
                            <th>Votos Detalle</th>
                            <th>Registrado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            usuarios.forEach(usuario => {
                const fechaRegistro = new Date(usuario.fecha_registro).toLocaleString();
                const votosDetalle = usuario.votos_detalle || 'Sin votos';
                
                html += `
                    <tr>
                        <td>
                            <div class="user-name">${usuario.nombre}</div>
                        </td>
                        <td>
                            <div class="user-details">${usuario.email}</div>
                        </td>
                        <td>
                            <div class="user-details"><strong>${usuario.total_votos}</strong></div>
                        </td>
                        <td>
                            <div class="vote-status-inline">
                                <span class="${usuario.votos_1er_año ? 'voted' : 'not-voted'}">
                                    ${usuario.votos_1er_año ? '✅ 1er' : '❌ 1er'}
                                </span>
                                <span class="${usuario.votos_2do_año ? 'voted' : 'not-voted'}">
                                    ${usuario.votos_2do_año ? '✅ 2do' : '❌ 2do'}
                                </span>
                                <span class="${usuario.votos_3er_año ? 'voted' : 'not-voted'}">
                                    ${usuario.votos_3er_año ? '✅ 3er' : '❌ 3er'}
                                </span>
                            </div>
                        </td>
                        <td>
                            <div class="user-details" style="max-width: 300px; word-wrap: break-word;">
                                ${votosDetalle}
                            </div>
                        </td>
                        <td>
                            <div class="user-details">${fechaRegistro}</div>
                        </td>
                        <td>
                            <div class="user-actions">
                                <button class="action-btn reset-votes-btn" onclick="resetearVotosUsuario(${usuario.id}, '${usuario.nombre}')" title="Resetear votos">
                                    🔄 Resetear Votos
                                </button>
                                <button class="action-btn delete-user-btn" onclick="eliminarUsuario(${usuario.id}, '${usuario.nombre}')" title="Eliminar usuario">
                                    🗑️ Eliminar
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                    </tbody>
                </table>
            `;
            
            container.innerHTML = html;
        }
        
        // Cargar subgrupos
        async function cargarSubgrupos() {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=obtener_subgrupos_admin'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    mostrarSubgrupos(data.subgrupos);
                } else {
                    document.getElementById('subgruposList').innerHTML = '<div class="error">Error al cargar subgrupos</div>';
                }
            } catch (error) {
                document.getElementById('subgruposList').innerHTML = '<div class="error">Error al cargar subgrupos</div>';
            }
        }
        
        function mostrarSubgrupos(subgrupos) {
            const container = document.getElementById('subgruposList');
            
            // Organizar subgrupos por grupos
            const gruposOrganizados = {};
            subgrupos.forEach(subgrupo => {
                const grupoId = subgrupo.grupo_id;
                if (!gruposOrganizados[grupoId]) {
                    gruposOrganizados[grupoId] = {
                        nombre: subgrupo.grupo_nombre,
                        subgrupos: []
                    };
                }
                gruposOrganizados[grupoId].subgrupos.push(subgrupo);
            });
            
            let html = '';
            
            // Mostrar los 3 grupos principales
            const grupos = [
                { id: 1, nombre: '1er Año' },
                { id: 2, nombre: '2do Año' },
                { id: 3, nombre: '3er Año' }
            ];
            
            grupos.forEach(grupo => {
                const subgruposDelGrupo = gruposOrganizados[grupo.id] ? gruposOrganizados[grupo.id].subgrupos : [];
                const totalSubgrupos = subgruposDelGrupo.length;
                const totalVotos = subgruposDelGrupo.reduce((sum, sg) => sum + parseInt(sg.total_votos), 0);
                
                html += `
                    <div class="grupo-card" onclick="mostrarSubgruposDelGrupo(${grupo.id}, '${grupo.nombre}')">
                        <div class="grupo-header">
                            <h3>${grupo.nombre}</h3>
                            <div class="grupo-stats">
                                <span class="stat-item">${totalSubgrupos} subgrupos</span>
                                <span class="stat-item">${totalVotos} votos totales</span>
                            </div>
                        </div>
                        <div class="grupo-arrow">▶️</div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        function mostrarSubgruposDelGrupo(grupoId, grupoNombre) {
            // Obtener subgrupos del grupo específico
            cargarSubgruposDelGrupo(grupoId, grupoNombre);
        }
        
        async function cargarSubgruposDelGrupo(grupoId, grupoNombre) {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=obtener_subgrupos_admin&grupo_id=${grupoId}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    mostrarSubgruposDetallados(data.subgrupos, grupoNombre);
                } else {
                    document.getElementById('subgruposList').innerHTML = '<div class="error">Error al cargar subgrupos</div>';
                }
            } catch (error) {
                document.getElementById('subgruposList').innerHTML = '<div class="error">Error al cargar subgrupos</div>';
            }
        }
        
        function mostrarSubgruposDetallados(subgrupos, grupoNombre) {
            const container = document.getElementById('subgruposList');
            
            let html = `
                <div class="grupo-detalle-header">
                    <button class="back-btn" onclick="cargarSubgrupos()">← Volver a Grupos</button>
                    <h3>Subgrupos de ${grupoNombre}</h3>
                </div>
                <div class="subgrupos-detalle">
            `;
            
            if (subgrupos.length === 0) {
                html += '<div class="loading">No hay subgrupos en este grupo</div>';
            } else {
                subgrupos.forEach(subgrupo => {
                    html += `
                        <div class="admin-item subgrupo-item" id="subgrupo-${subgrupo.id}">
                            <div class="admin-item-info">
                                <h4>${subgrupo.nombre}</h4>
                                <p><strong>Total votos:</strong> ${subgrupo.total_votos}</p>
                            </div>
                            <div class="admin-item-actions">
                                <button class="edit-btn" onclick="editarSubgrupo(${subgrupo.id}, '${subgrupo.nombre}')">✏️ Editar</button>
                                <button class="delete-btn" onclick="eliminarSubgrupo(${subgrupo.id})">🗑️ Eliminar</button>
                            </div>
                        </div>
                    `;
                });
            }
            
            html += `
                </div>
            `;
            
            container.innerHTML = html;
        }
        
        function editarSubgrupo(id, nombreActual) {
            const item = document.getElementById(`subgrupo-${id}`);
            const infoDiv = item.querySelector('.admin-item-info h4');
            
            infoDiv.innerHTML = `
                <input type="text" class="edit-input" value="${nombreActual}" id="edit-${id}">
                <button class="save-btn" onclick="guardarSubgrupo(${id})">💾 Guardar</button>
                <button class="cancel-btn" onclick="cancelarEdicion(${id}, '${nombreActual}')">❌ Cancelar</button>
            `;
        }
        
        async function guardarSubgrupo(id) {
            const nuevoNombre = document.getElementById(`edit-${id}`).value;
            
            if (!nuevoNombre.trim()) {
                mostrarMensaje('El nombre no puede estar vacío', 'error');
                return;
            }
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=actualizar_subgrupo&id=${id}&nombre=${encodeURIComponent(nuevoNombre)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    mostrarMensaje('Subgrupo actualizado correctamente', 'success');
                    // Actualizar automáticamente la lista
                    actualizarListaAutomaticamente();
                    // Actualizar resultados en tiempo real
                    obtenerResultados();
                } else {
                    mostrarMensaje('Error: ' + data.error, 'error');
                }
            } catch (error) {
                mostrarMensaje('Error de conexión', 'error');
            }
        }
        
        function cancelarEdicion(id, nombreOriginal) {
            const item = document.getElementById(`subgrupo-${id}`);
            const infoDiv = item.querySelector('.admin-item-info h4');
            infoDiv.textContent = nombreOriginal;
        }
        
        async function eliminarSubgrupo(id) {
            if (!confirm('¿Estás seguro de que quieres eliminar este subgrupo? Esta acción eliminará todos los votos asociados.')) {
                return;
            }
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=eliminar_subgrupo&id=${id}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    mostrarMensaje('Subgrupo eliminado correctamente', 'success');
                    // Actualizar automáticamente la lista
                    actualizarListaAutomaticamente();
                    // Actualizar resultados en tiempo real
                    obtenerResultados();
                } else {
                    mostrarMensaje('Error: ' + data.error, 'error');
                }
            } catch (error) {
                mostrarMensaje('Error de conexión', 'error');
            }
        }
        
        function mostrarAgregarSubgrupo() {
            console.log('=== INICIANDO mostrarAgregarSubgrupo ===');
            console.log('Intentando mostrar modal de agregar subgrupo');
            
            try {
                // Verificar que el DOM esté listo
                if (document.readyState !== 'complete') {
                    console.log('DOM no está completo, esperando...');
                    setTimeout(mostrarAgregarSubgrupo, 100);
                    return;
                }
                
                const modal = document.getElementById('agregarSubgrupoModal');
                console.log('Modal encontrado:', modal);
                
                if (modal) {
                    console.log('Estilos del modal antes:', modal.style.display);
                    modal.style.display = 'flex';
                    modal.style.zIndex = '3000';
                    console.log('Estilos del modal después:', modal.style.display);
                    console.log('Modal mostrado correctamente');
                    
                    // Verificar que el modal sea visible
                    const rect = modal.getBoundingClientRect();
                    console.log('Posición del modal:', rect);
                    
                    // Enfocar el primer campo del formulario
                    const nombreInput = document.getElementById('subgrupoNombre');
                    if (nombreInput) {
                        setTimeout(() => {
                            nombreInput.focus();
                            console.log('Campo de nombre enfocado');
                        }, 100);
                    } else {
                        console.warn('Campo de nombre no encontrado');
                    }
                } else {
                    console.error('Modal no encontrado');
                    alert('Error: No se pudo encontrar el modal de agregar subgrupo');
                }
            } catch (error) {
                console.error('Error al mostrar modal:', error);
                alert('Error al abrir el modal: ' + error.message);
            }
            console.log('=== FINALIZANDO mostrarAgregarSubgrupo ===');
        }
        
        function cerrarAgregarSubgrupo() {
            document.getElementById('agregarSubgrupoModal').style.display = 'none';
            document.getElementById('agregarSubgrupoForm').reset();
        }
        
        // Cerrar modal al hacer clic fuera de él
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('agregarSubgrupoModal');
            if (e.target === modal) {
                cerrarAgregarSubgrupo();
            }
        });
        
        // Manejar formulario de agregar subgrupo
        document.getElementById('agregarSubgrupoForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const grupoId = document.getElementById('grupoSelect').value;
            const nombre = document.getElementById('subgrupoNombre').value;
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=agregar_subgrupo&grupo_id=${grupoId}&nombre=${encodeURIComponent(nombre)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    mostrarMensaje('Subgrupo agregado correctamente', 'success');
                    cerrarAgregarSubgrupo();
                    // Actualizar automáticamente la lista
                    actualizarListaAutomaticamente();
                    // Actualizar resultados en tiempo real
                    obtenerResultados();
                } else {
                    mostrarMensaje('Error: ' + data.error, 'error');
                }
            } catch (error) {
                mostrarMensaje('Error de conexión', 'error');
            }
        });
        
        // Función para actualizar automáticamente la lista según el contexto
        function actualizarListaAutomaticamente() {
            // Detectar si estamos en la vista de grupos o en vista detallada
            const container = document.getElementById('subgruposList');
            const backButton = container.querySelector('.back-btn');
            
            if (backButton) {
                // Estamos en vista detallada de un grupo específico
                // Obtener el grupo actual del título
                const titulo = container.querySelector('.grupo-detalle-header h3');
                if (titulo) {
                    const texto = titulo.textContent;
                    if (texto.includes('1er Año')) {
                        cargarSubgruposDelGrupo(1, '1er Año');
                    } else if (texto.includes('2do Año')) {
                        cargarSubgruposDelGrupo(2, '2do Año');
                    } else if (texto.includes('3er Año')) {
                        cargarSubgruposDelGrupo(3, '3er Año');
                    }
                }
            } else {
                // Estamos en la vista principal de grupos
                cargarSubgrupos();
            }
        }
        
        // Función para mostrar/ocultar contraseña
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const toggle = input.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                toggle.textContent = '🙈';
                toggle.classList.add('active');
            } else {
                input.type = 'password';
                toggle.textContent = '👁️';
                toggle.classList.remove('active');
            }
        }
    </script>
</body>
</html>
