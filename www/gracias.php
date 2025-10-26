<?php
session_start();
require_once 'conexion/conexion.php';

// Si no está logueado, redirigir al login
if (empty($_SESSION["logueado"]) || $_SESSION["logueado"] !== true) {
    header('Location: login/login.php');
    exit;
}

// Si es administrador, redirigir al panel
if (isset($_SESSION['es_admin']) && $_SESSION['es_admin'] === true) {
    header('Location: admin.php');
    exit;
}

// Verificar que el usuario haya completado sus 3 votos
if (isset($_SESSION['usuario_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT nombre, votos_1er_año, votos_2do_año, votos_3er_año FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['usuario_id']]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Si no ha completado los 3 votos, redirigir al index
        if (!$usuario || $usuario['votos_1er_año'] != 1 || $usuario['votos_2do_año'] != 1 || $usuario['votos_3er_año'] != 1) {
            header('Location: index.php');
            exit;
        }
        
        $nombre_usuario = $usuario['nombre'];
    } catch (PDOException $e) {
        error_log("Error verificando votos del usuario: " . $e->getMessage());
        header('Location: index.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gracias por tu Voto - ISFT 177</title>
    <link rel="stylesheet" href="css/gracias.css">
</head>
<body>
    <div class="container">
        <div class="logo-container">
            <img src="Logo/logoisft177.png" alt="Logo ISFT 177" class="logo">
        </div>
        
        <div class="thank-you-section">
            <div class="icon-container">
                <svg width="120" height="120" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="60" cy="60" r="60" fill="#3A3F44"/>
                    <path d="M35 60L50 75L85 40" stroke="#F2C94C" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            
            <h1>¡Muchas Gracias por tu Voto!</h1>
            
            <p class="message">
                Hola <strong><?= htmlspecialchars($nombre_usuario) ?></strong>,<br>
                Tu participación es muy importante para nosotros.
            </p>
            
            <p class="info">
                Has completado tu votación exitosamente. Gracias por formar parte de este proceso democrático del ISFT 177.
            </p>
            
            <div class="vote-summary">
                <h3>✅ Votación Completada</h3>
                <div class="votes-check">
                    <p>✓ 1er Año</p>
                    <p>✓ 2do Año</p>
                    <p>✓ 3er Año</p>
                </div>
            </div>
            
            <div class="actions">
                <button class="logout-btn" onclick="cerrarSesion()">Cerrar Sesión</button>
            </div>
        </div>
    </div>
    
    <footer class="main-footer">
        <p>Desarrollado por Alumnos de 2do de Sistemas - ISFT N° 177</p>
    </footer>
    
    <script>
    function cerrarSesion() {
        // Redirigir a logout.php que llevará a despedida.php
        window.location.href = 'logout.php';
    }
    </script>
</body>
</html>
