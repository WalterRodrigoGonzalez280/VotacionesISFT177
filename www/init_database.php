<?php
require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

if ($conn) {
    echo "<h2>Inicializando Base de Datos...</h2>";
    
    // Crear tablas
    $sql = "
    CREATE TABLE IF NOT EXISTS usuarios (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        apellido VARCHAR(100) NOT NULL,
        email VARCHAR(150) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        dni VARCHAR(20) UNIQUE NOT NULL,
        telefono VARCHAR(20),
        fecha_nacimiento DATE,
        rol ENUM('votante', 'admin', 'supervisor') DEFAULT 'votante',
        activo BOOLEAN DEFAULT TRUE,
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ultimo_acceso TIMESTAMP NULL
    );
    
    CREATE TABLE IF NOT EXISTS sesiones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        token VARCHAR(255) NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_expiracion TIMESTAMP NOT NULL,
        activa BOOLEAN DEFAULT TRUE,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    );
    
    CREATE TABLE IF NOT EXISTS candidatos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        apellido VARCHAR(100) NOT NULL,
        partido VARCHAR(100),
        propuesta TEXT,
        foto VARCHAR(255),
        activo BOOLEAN DEFAULT TRUE,
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    
    CREATE TABLE IF NOT EXISTS votaciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(200) NOT NULL,
        descripcion TEXT,
        fecha_inicio DATETIME NOT NULL,
        fecha_fin DATETIME NOT NULL,
        activa BOOLEAN DEFAULT TRUE,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    
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
    );
    ";
    
    try {
        $conn->exec($sql);
        echo "<p style='color: green;'>✓ Tablas creadas exitosamente</p>";
        
        // Insertar usuario administrador
        $admin_password = password_hash('password', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO usuarios (nombre, apellido, email, password, dni, rol) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['Admin', 'Sistema', 'admin@votaciones.com', $admin_password, '00000000', 'admin']);
        echo "<p style='color: green;'>✓ Usuario administrador creado (admin@votaciones.com / password)</p>";
        
        // Insertar candidatos de ejemplo
        $candidatos = [
            ['Juan', 'Pérez', 'Partido A', 'Mejoras en educación y tecnología'],
            ['María', 'González', 'Partido B', 'Desarrollo económico y empleo'],
            ['Carlos', 'López', 'Partido C', 'Medio ambiente y sostenibilidad']
        ];
        
        $stmt = $conn->prepare("INSERT INTO candidatos (nombre, apellido, partido, propuesta) VALUES (?, ?, ?, ?)");
        foreach ($candidatos as $candidato) {
            $stmt->execute($candidato);
        }
        echo "<p style='color: green;'>✓ Candidatos de ejemplo creados</p>";
        
        echo "<p style='color: blue;'><strong>Base de datos inicializada correctamente!</strong></p>";
        echo "<p><a href='login.php' class='btn btn-primary'>Ir al Login</a></p>";
        
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Error de conexión a la base de datos</p>";
}
?>
