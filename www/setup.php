<?php
/**
 * Script de configuración inicial del sistema
 */
require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    die("Error de conexión a la base de datos");
}

echo "<h1>Configuración del Sistema de Votaciones</h1>";

try {
    // Crear tabla usuarios
    $sql_usuarios = "
    CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        apellido VARCHAR(100) NOT NULL,
        email VARCHAR(150) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        dni VARCHAR(20) DEFAULT '00000000',
        telefono VARCHAR(20),
        fecha_nacimiento DATE,
        rol ENUM('votante', 'admin', 'supervisor') DEFAULT 'votante',
        activo BOOLEAN DEFAULT TRUE,
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ultimo_acceso TIMESTAMP NULL
    )";
    
    $conn->exec($sql_usuarios);
    echo "<p style='color: green;'>✓ Tabla 'usuarios' creada</p>";
    
    // Crear tabla candidatos
    $sql_candidatos = "
    CREATE TABLE IF NOT EXISTS candidatos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        apellido VARCHAR(100) NOT NULL,
        partido VARCHAR(100),
        propuesta TEXT,
        foto VARCHAR(255),
        activo BOOLEAN DEFAULT TRUE,
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $conn->exec($sql_candidatos);
    echo "<p style='color: green;'>✓ Tabla 'candidatos' creada</p>";
    
    // Crear tabla votaciones
    $sql_votaciones = "
    CREATE TABLE IF NOT EXISTS votaciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(200) NOT NULL,
        descripcion TEXT,
        fecha_inicio DATETIME NOT NULL,
        fecha_fin DATETIME NOT NULL,
        activa BOOLEAN DEFAULT TRUE,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $conn->exec($sql_votaciones);
    echo "<p style='color: green;'>✓ Tabla 'votaciones' creada</p>";
    
    // Crear tabla votos
    $sql_votos = "
    CREATE TABLE IF NOT EXISTS votos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        candidato_id INT NOT NULL,
        votacion_id INT NOT NULL,
        fecha_voto TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(45),
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        FOREIGN KEY (candidato_id) REFERENCES candidatos(id) ON DELETE CASCADE,
        FOREIGN KEY (votacion_id) REFERENCES votaciones(id) ON DELETE CASCADE,
        UNIQUE KEY unique_voto (usuario_id, votacion_id)
    )";
    
    $conn->exec($sql_votos);
    echo "<p style='color: green;'>✓ Tabla 'votos' creada</p>";
    
    // Insertar usuario administrador
    $admin_password = password_hash('password', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT IGNORE INTO usuarios (nombre, apellido, email, password, dni, rol) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['Admin', 'Sistema', 'admin@votaciones.com', $admin_password, '00000000', 'admin']);
    echo "<p style='color: green;'>✓ Usuario administrador creado</p>";
    
    // Insertar candidatos de ejemplo
    $candidatos = [
        ['Juan', 'Pérez', 'Partido A', 'Mejoras en educación y tecnología'],
        ['María', 'González', 'Partido B', 'Desarrollo económico y empleo'],
        ['Carlos', 'López', 'Partido C', 'Medio ambiente y sostenibilidad']
    ];
    
    $stmt = $conn->prepare("INSERT IGNORE INTO candidatos (nombre, apellido, partido, propuesta) VALUES (?, ?, ?, ?)");
    foreach ($candidatos as $candidato) {
        $stmt->execute($candidato);
    }
    echo "<p style='color: green;'>✓ Candidatos de ejemplo creados</p>";
    
    echo "<h2 style='color: blue;'>¡Sistema configurado exitosamente!</h2>";
    echo "<p><strong>Credenciales de acceso:</strong></p>";
    echo "<ul>";
    echo "<li>Email: admin@votaciones.com</li>";
    echo "<li>Contraseña: password</li>";
    echo "</ul>";
    echo "<p><a href='login.php' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Ir al Login</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
