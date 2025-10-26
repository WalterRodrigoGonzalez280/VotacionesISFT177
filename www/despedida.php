<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasta Pronto - ISFT 177</title>
    <link rel="stylesheet" href="css/despedida.css">
</head>
<body>
    <div class="container">
        <div class="logo-container">
            <img src="Logo/logoisft177.png" alt="Logo ISFT 177" class="logo">
        </div>
        
        <div class="goodbye-section">
            <div class="icon-container">
                <svg width="100" height="100" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="50" cy="50" r="50" fill="#3A3F44"/>
                    <path d="M35 50L45 60L65 40" stroke="#F2C94C" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            
            <h1>¡Hasta Pronto!</h1>
            
            <p class="message">
                Gracias por participar en la votación del ISFT 177.
            </p>
            
            <p class="info">
                Tu sesión se ha cerrado correctamente.<br>
                ¡Esperamos verte pronto!
            </p>
            
            <div class="actions">
                <button class="login-btn" onclick="volverAlLogin()">Iniciar Sesión</button>
            </div>
        </div>
    </div>
    
    <footer class="main-footer">
        <p>Desarrollado por Alumnos de 2do de Sistemas - ISFT N° 177</p>
    </footer>
    
    <script>
    function volverAlLogin() {
        window.location.href = 'login/login.php';
    }
    
    // Redirigir automáticamente después de 5 segundos
    setTimeout(() => {
        window.location.href = 'login/login.php';
    }, 5000);
    </script>
</body>
</html>
