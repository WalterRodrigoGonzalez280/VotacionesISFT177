<?php
echo "<h1>Bienvenido al Sistema de Votaciones</h1>";
echo "<p>El entorno Docker está funcionando correctamente.</p>";
echo "<p>Base de datos: " . getenv('MYSQL_DATABASE') . "</p>";
echo "<p>Usuario: " . getenv('MYSQL_USER') . "</p>";

// Test de conexión a la base de datos
try {
    $host = getenv('MYSQL_SERVER') ?: 'db';
    $dbname = getenv('MYSQL_DATABASE');
    $username = getenv('MYSQL_USER');
    $password = getenv('MYSQL_PASSWORD');
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    echo "<p style='color: green;'>✓ Conexión a la base de datos exitosa</p>";
} catch(PDOException $e) {
    echo "<p style='color: red;'>✗ Error de conexión: " . $e->getMessage() . "</p>";
}
?>
