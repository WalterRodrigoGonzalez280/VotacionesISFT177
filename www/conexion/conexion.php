<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');

$host   = $_ENV['DB_HOST'] ?? 'db';           
$puerto = $_ENV['DB_PORT'] ?? '3306';
$usuario = $_ENV['DB_USER'] ?? 'votacion_user';
$clave   = $_ENV['DB_PASS'] ?? 'develop2025';
$base    = $_ENV['DB_NAME'] ?? 'votacion_db';


if (empty($host) || empty($usuario) || empty($base)) {
    error_log("Configuración de base de datos incompleta");
    http_response_code(500);
    echo "Error de configuración de base de datos.";
    exit;
}

$dsn = "mysql:host={$host};port={$puerto};dbname={$base};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_TIMEOUT            => 10,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
];

try {
    $pdo = new PDO($dsn, $usuario, $clave, $options);
    $pdo->exec("SET time_zone = '-03:00'");
    
} catch (PDOException $e) {
    
    error_log("DB connection error: " . $e->getMessage());
    
    $isDev = isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'development';
    
    http_response_code(500);
    
    if ($isDev) {
        echo "Error de conexión: " . $e->getMessage();
    } else {
        echo "Error de conexión a la base de datos.";
    }
    
    exit;
}

function verificarConexion() {
    global $pdo;
    try {
        $pdo->query('SELECT 1');
        return true;
    } catch (PDOException $e) {
        error_log("Connection check failed: " . $e->getMessage());
        return false;
    }
}
